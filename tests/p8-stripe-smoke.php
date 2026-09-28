<?php
declare(strict_types=1);

require dirname(__DIR__) . '/server/p3_db.php';
require dirname(__DIR__) . '/server/p5_batch.php';
require dirname(__DIR__) . '/server/p4_membership.php';
require dirname(__DIR__) . '/server/p6_payment.php';
require dirname(__DIR__) . '/server/p7_assortment.php';
require_once dirname(__DIR__) . '/server/p8_stripe.php';

$config=[
    'mode'=>'staging',
    'admin_token'=>'p8-admin',
    'allowed_origins'=>[],
    'mail_transport'=>'disabled',
    'payment_mail_transport'=>'disabled',
    'membership_validity_days'=>365,
    'payment_provider'=>'stripe',
    'payment_webhook_secret'=>str_repeat('legacy-',8),
    'stripe_mode'=>'test',
    'stripe_secret_key'=>'sk_test_'.str_repeat('a',32),
    'stripe_webhook_secret'=>'whsec_'.str_repeat('b',32),
    'stripe_swish_enabled'=>false,
    'public_base_url'=>'https://shop.example.test',
    'seller_legal_name'=>'',
    'seller_org_number'=>'',
    'support_email'=>'',
    'terms_url'=>'',
    'privacy_url'=>'',
    'merchant_verified'=>false,
    'stripe_fees_approved'=>false,
    'refund_policy_approved'=>false,
    'production_launch_enabled'=>false,
    'db'=>[
        'host'=>getenv('BOIS_P3_TEST_DB_HOST') ?: '127.0.0.1',
        'port'=>(int)(getenv('BOIS_P3_TEST_DB_PORT') ?: 3306),
        'database'=>getenv('BOIS_P3_TEST_DB_NAME') ?: 'bois_p8_test',
        'user'=>getenv('BOIS_P3_TEST_DB_USER') ?: 'root',
        'password'=>getenv('BOIS_P3_TEST_DB_PASSWORD') ?: 'root',
    ],
];

$pdo=bois_p3_pdo($config);
bois_p3_apply_schema($pdo);
bois_p3_seed_catalog($pdo);
bois_p5_apply_schema($pdo);
bois_p4_apply_schema($pdo);
bois_p6_apply_schema($pdo);
bois_p7_apply_schema($pdo);
bois_p8_apply_schema($pdo);

function p8_order(PDO $pdo,string $key): array
{
    $order=bois_p3_create_order($pdo,[
        'customer'=>[
            'name'=>'P8 Test',
            'email'=>$key.'@example.invalid',
            'phone'=>'070-800 00 00',
        ],
        'items'=>[
            ['sku'=>'MEM-ADULT','quantity'=>1,'metadata'=>['member_name'=>'P8 Test']],
            ['sku'=>'NW-GYM-ANNUAL','quantity'=>1,'metadata'=>[]],
        ],
        'existing_member'=>false,
        'consent'=>true,
        'website'=>'',
        'idempotency_key'=>$key,
    ]);
    bois_p4_register_order($pdo,$order['public_id']);
    return $order;
}

$order=p8_order($pdo,'p8-card');
$calls=[];
$transport=function(string $method,string $url,array $params,array $headers) use (&$calls): array {
    $calls[]=compact('method','url','params','headers');
    return [
        'id'=>'cs_test_p8card123',
        'object'=>'checkout.session',
        'url'=>'https://checkout.stripe.com/c/pay/cs_test_p8card123',
        'livemode'=>false,
        'payment_intent'=>null,
    ];
};

$checkout=bois_p6_checkout(
    $pdo,$config,$order['public_id'],$order['public_token'],'card',$transport
);
if(($checkout['provider']??'')!=='stripe' || ($checkout['redirect_url']??'')===''){
    throw new RuntimeException('Stripe Checkout was not created.');
}
if(count($calls)!==1 || $calls[0]['method']!=='POST' || !str_ends_with($calls[0]['url'],'/v1/checkout/sessions')){
    throw new RuntimeException('Unexpected Stripe Checkout API request.');
}
if(($calls[0]['params']['payment_method_types'][0]??'')!=='card'){
    throw new RuntimeException('Card was not constrained as requested payment method.');
}
if(($calls[0]['params']['line_items'][0]['price_data']['unit_amount']??0)!==(int)$order['total_ore']){
    throw new RuntimeException('Stripe amount differs from server order total.');
}
if(!str_starts_with((string)($calls[0]['headers']['Authorization']??''),'Bearer sk_test_')){
    throw new RuntimeException('Stripe auth header missing in test transport.');
}

$event=[
    'id'=>'evt_p8_paid_1',
    'type'=>'checkout.session.completed',
    'data'=>['object'=>[
        'id'=>$checkout['session_ref'],
        'client_reference_id'=>$order['public_id'],
        'metadata'=>['bois_order_public_id'=>$order['public_id']],
        'currency'=>'sek',
        'amount_total'=>$order['total_ore'],
        'payment_status'=>'paid',
        'payment_intent'=>'pi_p8_card_1',
    ]],
];
$raw=json_encode($event,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$timestamp=time();
$signature=bois_p8_stripe_signature_for_test($config,$raw,$timestamp);

$bad=false;
try{
    bois_p8_stripe_translate_webhook($pdo,$config,$raw,'t='.$timestamp.',v1='.str_repeat('0',64));
}catch(DomainException){$bad=true;}
if(!$bad) throw new RuntimeException('Invalid Stripe signature was accepted.');

$translated=bois_p8_stripe_translate_webhook($pdo,$config,$raw,$signature);
if(($translated['ignored']??true)!==false || ($translated['event']['type']??'')!=='payment.succeeded'){
    throw new RuntimeException('Stripe Checkout event was not normalized.');
}
$paid=bois_p6_process_verified_event($pdo,$config,$translated['event'],$raw);
if(($paid['result']['status']??'')!=='PAID') throw new RuntimeException('Stripe PAID did not reach P6.');
$stats=bois_p4_stats($pdo);
if($stats['active_members']!==1 || $stats['eligible_gym']!==1){
    throw new RuntimeException('Stripe PAID did not drive P4.');
}

$translatedAgain=bois_p8_stripe_translate_webhook($pdo,$config,$raw,$signature);
$duplicate=bois_p6_process_verified_event($pdo,$config,$translatedAgain['event'],$raw);
if(empty($duplicate['duplicate'])) throw new RuntimeException('Stripe event replay was not idempotent.');

$wrong=$event;
$wrong['id']='evt_p8_wrong_amount';
$wrong['data']['object']['amount_total']=(int)$order['total_ore']+1;
$wrongRaw=json_encode($wrong,JSON_THROW_ON_ERROR);
$wrongSig=bois_p8_stripe_signature_for_test($config,$wrongRaw,time());
$rejected=false;
try{
    $normalized=bois_p8_stripe_translate_webhook($pdo,$config,$wrongRaw,$wrongSig);
    bois_p6_process_verified_event($pdo,$config,$normalized['event'],$wrongRaw);
}catch(InvalidArgumentException){$rejected=true;}
if(!$rejected) throw new RuntimeException('Stripe amount mismatch was accepted.');

$swishOrder=p8_order($pdo,'p8-swish-disabled');
$swishBlocked=false;
try{
    bois_p6_checkout($pdo,$config,$swishOrder['public_id'],$swishOrder['public_token'],'swish',$transport);
}catch(InvalidArgumentException){$swishBlocked=true;}
if(!$swishBlocked) throw new RuntimeException('Swish was enabled without explicit access flag.');

$swishConfig=$config;
$swishConfig['stripe_swish_enabled']=true;
$swishTransport=function(string $method,string $url,array $params,array $headers): array {
    if(($params['payment_method_types'][0]??'')!=='swish') throw new RuntimeException('Swish request not configured.');
    return [
        'id'=>'cs_test_p8swish123',
        'object'=>'checkout.session',
        'url'=>'https://checkout.stripe.com/c/pay/cs_test_p8swish123',
        'livemode'=>false,
        'payment_intent'=>null,
    ];
};
$swishCheckout=bois_p6_checkout(
    $pdo,$swishConfig,$swishOrder['public_id'],$swishOrder['public_token'],'swish',$swishTransport
);
if(($swishCheckout['method']??'')!=='swish') throw new RuntimeException('Explicit Swish test checkout failed.');

$refundTransport=function(string $method,string $url,array $params,array $headers): array {
    if(!str_ends_with($url,'/v1/refunds')) throw new RuntimeException('Unexpected refund endpoint.');
    if(($params['payment_intent']??'')!=='pi_p8_card_1') throw new RuntimeException('Refund PaymentIntent mismatch.');
    if((int)($params['amount']??0)!==1000) throw new RuntimeException('Refund amount mismatch.');
    return ['id'=>'re_p8_partial_1','object'=>'refund','status'=>'pending','amount'=>1000,'payment_intent'=>'pi_p8_card_1'];
};
$refundRequest=bois_p8_stripe_request_refund($pdo,$config,$order['public_id'],1000,$refundTransport);
if(($refundRequest['payment_status']??'')!=='REFUND_PENDING') throw new RuntimeException('Refund request did not enter manual review.');

$refundEvent=[
    'id'=>'evt_p8_refund_1',
    'type'=>'refund.updated',
    'data'=>['object'=>[
        'id'=>'re_p8_partial_1',
        'status'=>'succeeded',
        'amount'=>1000,
        'currency'=>'sek',
        'payment_intent'=>'pi_p8_card_1',
    ]],
];
$refundRaw=json_encode($refundEvent,JSON_THROW_ON_ERROR);
$refundSig=bois_p8_stripe_signature_for_test($config,$refundRaw,time());
$refundTranslated=bois_p8_stripe_translate_webhook($pdo,$config,$refundRaw,$refundSig);
$refundDone=bois_p6_process_verified_event($pdo,$config,$refundTranslated['event'],$refundRaw);
if(($refundDone['result']['status']??'')!=='PARTIALLY_REFUNDED'){
    throw new RuntimeException('Stripe partial refund did not reach P6 state machine.');
}
if(empty($refundDone['result']['manual_fulfillment_review'])){
    throw new RuntimeException('Stripe refund bypassed manual fulfillment review.');
}

$ignoredEvent=[
    'id'=>'evt_p8_irrelevant',
    'type'=>'customer.created',
    'data'=>['object'=>['id'=>'cus_x']],
];
$ignoredRaw=json_encode($ignoredEvent,JSON_THROW_ON_ERROR);
$ignoredSig=bois_p8_stripe_signature_for_test($config,$ignoredRaw,time());
$ignored=bois_p8_stripe_translate_webhook($pdo,$config,$ignoredRaw,$ignoredSig);
if(empty($ignored['ignored'])) throw new RuntimeException('Irrelevant Stripe event was not ignored safely.');

$readiness=bois_p8_readiness($config);
if(!$readiness['ready_for_stripe_test']) throw new RuntimeException('Stripe test readiness should be true.');
if($readiness['ready_for_production_launch']) throw new RuntimeException('Production launch became ready without approvals.');

$badKey=$config;
$badKey['stripe_secret_key']='sk_live_'.str_repeat('x',32);
if(bois_p8_stripe_runtime_ready($badKey)) throw new RuntimeException('Live key accepted in Stripe test mode.');

echo "P8_STRIPE_CONTRACT: pass\n";
echo "STRIPE_CHECKOUT_SERVER_TOTAL: pass\n";
echo "STRIPE_SIGNATURE: pass\n";
echo "STRIPE_EVENT_IDEMPOTENCY: pass\n";
echo "STRIPE_P4_GATE: pass\n";
echo "STRIPE_REFUND_REVIEW: pass\n";
echo "SWISH_FEATURE_GATE: pass\n";
echo "PRODUCTION_FAIL_CLOSED: pass\n";
echo "REAL_STRIPE_CALLS: no\n";
echo "NEW_EXTERNAL_COST: 0\n";
