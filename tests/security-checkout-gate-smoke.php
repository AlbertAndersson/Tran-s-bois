<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/server/p6_payment.php';
require_once dirname(__DIR__).'/server/p9_sales.php';

// No database connection or real provider transport is permitted in this test.
final class SecurityNoDatabase extends PDO
{
    public int $calls=0;
    public function __construct() {}
    public function prepare(string $query,array $options=[]): PDOStatement|false
    {
        $this->calls++;
        throw new RuntimeException('Unexpected database access before gate rejection.');
    }
    public function beginTransaction(): bool
    {
        $this->calls++;
        throw new RuntimeException('Unexpected database transaction before gate rejection.');
    }
}
function security_check(bool $value,string $message): void
{
    if(!$value) throw new RuntimeException($message);
}
function security_denied(callable $call): void
{
    try{$call();}catch(DomainException){return;}
    throw new RuntimeException('Expected checkout gate to reject the operation.');
}

$pdo=new SecurityNoDatabase();
$calls=0;
$transport=function(string $method,string $url,array $params,array $headers) use(&$calls): array {
    $calls++;
    return ['fixture'=>true];
};
$production=[
    'mode'=>'production',
    'payment_provider'=>'stripe',
    'stripe_mode'=>'live',
    'stripe_secret_key'=>'sk_live_'.str_repeat('fixture',6),
    'stripe_webhook_secret'=>'whsec_'.str_repeat('fixture',6),
    'public_base_url'=>'https://bois.example.test',
    'seller_legal_name'=>'Synthetic Merchant',
    'seller_org_number'=>'TEST-ONLY',
    'support_email'=>'test@example.invalid',
    'terms_url'=>'https://bois.example.test/terms',
    'privacy_url'=>'https://bois.example.test/privacy',
    'merchant_verified'=>true,
    'stripe_fees_approved'=>true,
    'refund_policy_approved'=>true,
    // In-memory fixture only; no outbox delivery is invoked.
    'payment_mail_transport'=>'php_mail',
    'production_launch_enabled'=>true,
];
security_check(bois_p8_stripe_checkout_allowed($production),'Complete synthetic readiness should pass.');

$denied=[];
foreach([false,null,0,1,'true','false','1'] as $flag){
    $denied[]=array_replace($production,['production_launch_enabled'=>$flag]);
}
$missing=$production;unset($missing['production_launch_enabled']);$denied[]=$missing;
foreach(['merchant_verified','stripe_fees_approved','refund_policy_approved'] as $approval){
    $denied[]=array_replace($production,[$approval=>false]);
}
$denied[]=array_replace($production,['seller_legal_name'=>'']);
$denied[]=array_replace($production,['payment_mail_transport'=>'disabled']);
$denied[]=array_replace($production,['mode'=>'staging']); // Live keys in staging.
$denied[]=array_replace($production,['mode'=>'test']);
$denied[]=array_replace($production,['mode'=>'invalid']);
$denied[]=array_replace($production,['mode'=>'']);
$denied[]=array_replace($production,['stripe_mode'=>'test','stripe_secret_key'=>'sk_test_'.str_repeat('fixture',6)]);
foreach($denied as $config){
    security_check(!bois_p8_stripe_checkout_allowed($config),'Unsafe checkout config accepted.');
    security_denied(fn()=>bois_p8_stripe_checkout($pdo,$config,'synthetic-order','token','card',$transport));
    security_denied(fn()=>bois_p6_checkout($pdo,$config,'synthetic-order','token','card',$transport));
    security_denied(fn()=>bois_p8_stripe_request($config,'POST','/v1/checkout/sessions',[],null,$transport));
    security_check($calls===0 && $pdo->calls===0,'Blocked checkout contacted transport or database.');
}

$test=array_replace($production,[
    'mode'=>'staging','stripe_mode'=>'test',
    'stripe_secret_key'=>'sk_test_'.str_repeat('fixture',6),
    'production_launch_enabled'=>false,
    'payment_mail_transport'=>'disabled',
]);
security_check(bois_p8_stripe_checkout_allowed($test),'Offline Stripe test contract broken.');
bois_p8_stripe_request($test,'POST','/v1/checkout/sessions',[],null,$transport);
bois_p8_stripe_request($production,'POST','/v1/checkout/sessions',[],null,$transport);
security_check($calls===2,'Approved fixtures did not reach fake transport.');

// Closing new checkout must NOT disable authenticated settlement of existing ones.
$closed=array_replace($production,['production_launch_enabled'=>false]);
security_check(bois_p6_payment_enabled($closed),'Provider settlement disabled by checkout gate.');
$raw=json_encode(['id'=>'evt_fixture','type'=>'checkout.session.completed'],JSON_THROW_ON_ERROR);
$signature=bois_p8_stripe_signature_for_test($closed,$raw,time());
bois_p8_stripe_verify_signature($closed,$raw,$signature);
$bad=false;
try{bois_p8_stripe_verify_signature($closed,$raw,'t='.time().',v1='.str_repeat('0',64));}
catch(DomainException){$bad=true;}
security_check($bad,'Closing checkout bypassed signature verification.');

// A pre-existing refund operation keeps its existing provider contract.
bois_p8_stripe_request($closed,'POST','/v1/refunds',[],null,$transport);
security_check($calls===3,'Checkout gate incorrectly blocked a refund fixture.');

foreach([[],['mode'=>'production','sales_tracking_enabled'=>true],['mode'=>'staging','sales_tracking_enabled'=>false]] as $off){
    security_check((bois_p9_capture_event($pdo,['sales_tracking_enabled'=>true],$off)['disabled']??false)===true,'Sales event gate failed.');
    bois_p9_link_order($pdo,'synthetic-order',['sales_session_id'=>'forged'],$off);
}
security_check($pdo->calls===0,'Disabled tracking accessed database.');
echo "CHECKOUT_GATE_BEFORE_DATABASE_AND_TRANSPORT: pass\n";
echo "MISSING_AND_NON_BOOLEAN_APPROVAL_REJECTED: pass\n";
echo "LIVE_CHECKOUT_OUTSIDE_PRODUCTION_REJECTED: pass\n";
echo "EXISTING_PAYMENT_SIGNATURE_HANDLING_PRESERVED: pass\n";
echo "DISABLED_TRACKING_NO_DATABASE_ACCESS: pass\n";
echo "REAL_STRIPE_CALLS: no\n";
echo "REAL_EMAIL_SENT: no\n";
