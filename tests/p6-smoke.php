<?php
declare(strict_types=1);

require dirname(__DIR__) . '/server/p3_db.php';
require dirname(__DIR__) . '/server/p5_batch.php';
require dirname(__DIR__) . '/server/p4_membership.php';
require dirname(__DIR__) . '/server/p6_payment.php';

$config=[
    'mode'=>'test',
    'admin_token'=>'test-admin',
    'allowed_origins'=>[],
    'mail_transport'=>'disabled',
    'payment_mail_transport'=>'disabled',
    'membership_validity_days'=>365,
    'payment_provider'=>'mock',
    'payment_webhook_secret'=>str_repeat('p6-test-secret-',4),
    'db'=>[
        'host'=>getenv('BOIS_P3_TEST_DB_HOST') ?: '127.0.0.1',
        'port'=>(int)(getenv('BOIS_P3_TEST_DB_PORT') ?: 3306),
        'database'=>getenv('BOIS_P3_TEST_DB_NAME') ?: 'bois_test',
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

function p6_order(PDO $pdo,array $items,string $key,bool $existing=false): array
{
    $order=bois_p3_create_order($pdo,[
        'customer'=>[
            'name'=>'P6 Test '.$key,
            'email'=>$key.'@example.invalid',
            'phone'=>'070-600 00 00',
        ],
        'items'=>$items,
        'existing_member'=>$existing,
        'consent'=>true,
        'website'=>'',
        'idempotency_key'=>$key,
    ]);
    bois_p4_register_order($pdo,$order['public_id']);
    return $order;
}

function p6_signed(PDO $pdo,array $config,array $session,string $type,string $eventId,int $amount=0): array
{
    $event=bois_p6_event_payload($config,$session,$type,$amount,$eventId);
    $raw=json_encode($event,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $ts=(string)time();
    $sig='sha256='.bois_p6_signature($config,$raw,$ts);
    return [$raw,$ts,$sig];
}

$new=p6_order($pdo,[
    ['sku'=>'MEM-ADULT','quantity'=>1,'metadata'=>['member_name'=>'P6 Medlem']],
    ['sku'=>'NW-GYM-ANNUAL','quantity'=>1,'metadata'=>[]],
],'p6-member');

$checkout=bois_p6_checkout($pdo,$config,$new['public_id'],$new['public_token'],'swish');
if($checkout['status']!=='PENDING' || $checkout['method']!=='swish') throw new RuntimeException('Checkout was not created.');
if(bois_p4_stats($pdo)['active_members']!==0) throw new RuntimeException('Membership activated before paid webhook.');

$session=bois_p6_session($pdo,$checkout['session_ref'],$checkout['session_token']);
[$raw,$ts,$sig]=p6_signed($pdo,$config,$session,'payment.succeeded','evt-paid-1');

$bad=false;
try{
    bois_p6_process_webhook_raw($pdo,$config,$raw,$ts,'sha256='.str_repeat('0',64));
}catch(DomainException){
    $bad=true;
}
if(!$bad) throw new RuntimeException('Invalid signature was accepted.');

$paid=bois_p6_process_webhook_raw($pdo,$config,$raw,$ts,$sig);
if(($paid['result']['status']??'')!=='PAID') throw new RuntimeException('Paid webhook failed.');

$stats=bois_p4_stats($pdo);
if($stats['active_members']!==1 || $stats['eligible_gym']!==1) throw new RuntimeException('P4 was not activated after verified PAID.');

$member=bois_p4_admin_members($pdo)[0];
$validTo=(string)$member['valid_to'];

$duplicate=bois_p6_process_webhook_raw($pdo,$config,$raw,$ts,$sig);
if(empty($duplicate['duplicate'])) throw new RuntimeException('Duplicate event was not ignored.');
if((string)bois_p4_admin_members($pdo)[0]['valid_to']!==$validTo) throw new RuntimeException('Duplicate event extended membership.');

[$raw2,$ts2,$sig2]=p6_signed($pdo,$config,$session,'payment.succeeded','evt-paid-2');
$second=bois_p6_process_webhook_raw($pdo,$config,$raw2,$ts2,$sig2);
if(($second['result']['effects']['already_applied']??false)!==true) throw new RuntimeException('Second PAID event reapplied downstream effects.');
if((string)bois_p4_admin_members($pdo)[0]['valid_to']!==$validTo) throw new RuntimeException('Second PAID event extended membership.');

$kit=p6_order($pdo,[[
    'sku'=>'MATCHKIT-STAGING','quantity'=>1,
    'metadata'=>[
        'team'=>'P13','player_name'=>'P6 Spelare','number'=>'17',
        'shirt_size'=>'140','shorts_size'=>'152','name_print'=>true,'number_print'=>true
    ]
]],'p6-kit');
$kitCheckout=bois_p6_checkout($pdo,$config,$kit['public_id'],$kit['public_token'],'card');
$kitSession=bois_p6_session($pdo,$kitCheckout['session_ref'],$kitCheckout['session_token']);
[$kitRaw,$kitTs,$kitSig]=p6_signed($pdo,$config,$kitSession,'payment.succeeded','evt-kit-paid');
bois_p6_process_webhook_raw($pdo,$config,$kitRaw,$kitTs,$kitSig);

$stmt=$pdo->prepare(
    "SELECT i.fulfillment_status,o.payment_status
     FROM bois_order_items i JOIN bois_orders o ON o.id=i.order_id
     WHERE o.public_id=? AND i.sku='MATCHKIT-STAGING'"
);
$stmt->execute([$kit['public_id']]);
$kitState=$stmt->fetch();
if(($kitState['payment_status']??'')!=='PAID' || ($kitState['fulfillment_status']??'')!=='WAITING_BATCH'){
    throw new RuntimeException('Paid match kit did not enter batch queue.');
}

$failedOrder=p6_order($pdo,[['sku'=>'MEM-YOUTH','quantity'=>1,'metadata'=>['member_name'=>'P6 Nekad']]],'p6-failed');
$failedCheckout=bois_p6_checkout($pdo,$config,$failedOrder['public_id'],$failedOrder['public_token'],'card');
$failedSession=bois_p6_session($pdo,$failedCheckout['session_ref'],$failedCheckout['session_token']);
[$failedRaw,$failedTs,$failedSig]=p6_signed($pdo,$config,$failedSession,'payment.failed','evt-failed');
$failed=bois_p6_process_webhook_raw($pdo,$config,$failedRaw,$failedTs,$failedSig);
if(($failed['result']['status']??'')!=='FAILED') throw new RuntimeException('Failed state transition failed.');

$unpaidMemberships=(int)$pdo->query(
    "SELECT COUNT(*) FROM bois_memberships WHERE status='ACTIVE' AND member_name='P6 Nekad'"
)->fetchColumn();
if($unpaidMemberships!==0) throw new RuntimeException('Failed payment activated membership.');

$refundSession=bois_p6_session($pdo,$checkout['session_ref'],$checkout['session_token']);
[$refundRaw,$refundTs,$refundSig]=p6_signed(
    $pdo,$config,$refundSession,'payment.refunded','evt-refund-full',(int)$refundSession['paid_ore']
);
$refund=bois_p6_process_webhook_raw($pdo,$config,$refundRaw,$refundTs,$refundSig);
if(($refund['result']['status']??'')!=='REFUNDED') throw new RuntimeException('Full refund failed.');

$refundOrder=$pdo->prepare("SELECT payment_status,fulfillment_status FROM bois_orders WHERE public_id=?");
$refundOrder->execute([$new['public_id']]);
$refundState=$refundOrder->fetch();
if(($refundState['payment_status']??'')!=='REFUNDED' || ($refundState['fulfillment_status']??'')!=='REVIEW_REQUIRED'){
    throw new RuntimeException('Refund did not enter manual fulfillment review.');
}
if(bois_p4_stats($pdo)['active_members']!==1) throw new RuntimeException('Refund silently revoked membership; manual review invariant broken.');

$outbox=bois_p6_admin_payments($pdo)['outbox'];
if(count($outbox)<3) throw new RuntimeException('Expected payment receipt outbox rows.');
foreach($outbox as $row){
    if(($row['to_email']??'')!=='customer@example.invalid') throw new RuntimeException('Unsafe staging receipt recipient.');
}

$failCount=0;
$mailFail=bois_p6_deliver_outbox($pdo,$config,function(array $message) use (&$failCount): bool {
    $failCount++;
    return false;
},1);
if($mailFail['failed']!==1) throw new RuntimeException('Receipt retry path did not record failure.');

$outbox=bois_p6_admin_payments($pdo)['outbox'];
$retryId=null;
foreach($outbox as $row){
    if(($row['status']??'')==='RETRY'){ $retryId=(int)$row['id']; break; }
}
if(!$retryId) throw new RuntimeException('Retry outbox row missing.');
bois_p6_retry_outbox($pdo,$retryId);
$mailOk=bois_p6_deliver_outbox($pdo,$config,fn(array $message): bool => true,1);
if($mailOk['sent']!==1) throw new RuntimeException('Receipt retry did not succeed.');

$eventCount=(int)$pdo->query(
    "SELECT COUNT(*) FROM bois_payment_events WHERE provider='mock' AND event_id='evt-paid-1'"
)->fetchColumn();
if($eventCount!==1) throw new RuntimeException('Duplicate payment event row created.');

echo "P6_SMOKE: pass\n";
echo "SIGNED_WEBHOOK: pass\n";
echo "INVALID_SIGNATURE_REJECTED: pass\n";
echo "EVENT_IDEMPOTENCY: pass\n";
echo "P4_AFTER_VERIFIED_PAID_ONLY: pass\n";
echo "P5_QUEUE_AFTER_VERIFIED_PAID_ONLY: pass\n";
echo "REFUND_STATE_MACHINE: pass\n";
echo "RECEIPT_OUTBOX_RETRY: pass\n";
echo "REAL_PAYMENT_SENT: no\n";
echo "REAL_EMAIL_SENT: no\n";
