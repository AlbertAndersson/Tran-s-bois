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

foreach([
    ['order_public_id'=>'BOIS-WRONG'],
    ['currency'=>'EUR'],
    ['currency'=>''],
    ['amount_ore'=>(int)$session['total_ore']+1],
] as $index=>$change){
    $invalid=bois_p6_event_payload($config,$session,'payment.succeeded',0,'evt-invalid-'.$index);
    $invalid=array_replace($invalid,$change);
    $invalidRaw=json_encode($invalid,JSON_THROW_ON_ERROR);
    $invalidTs=(string)time();
    $rejected=false;
    try{
        bois_p6_process_webhook_raw($pdo,$config,$invalidRaw,$invalidTs,'sha256='.bois_p6_signature($config,$invalidRaw,$invalidTs));
    }catch(InvalidArgumentException){$rejected=true;}
    if(!$rejected) throw new RuntimeException('Signed mismatched webhook was accepted.');
}

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

$reused=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
$reused['amount_ore']=(int)$session['total_ore']+1;
$reusedRaw=json_encode($reused,JSON_THROW_ON_ERROR);
$reusedTs=(string)time();
$rejected=false;
try{
    bois_p6_process_webhook_raw($pdo,$config,$reusedRaw,$reusedTs,'sha256='.bois_p6_signature($config,$reusedRaw,$reusedTs));
}catch(DomainException){$rejected=true;}
if(!$rejected) throw new RuntimeException('Reused event ID with different content was accepted.');

$adminOrder=p6_order($pdo,[['sku'=>'MEM-YOUTH','quantity'=>1,'metadata'=>['member_name'=>'P6 Admin']]],'p6-admin');
$adminPaid=bois_p6_admin_simulate_paid($pdo,$config,$adminOrder['public_id']);
if(($adminPaid['result']['status']??'')!=='PAID') throw new RuntimeException('Admin mock did not use verified P6 path.');
$adminEvents=$pdo->prepare("SELECT COUNT(*) FROM bois_payment_events WHERE order_id=(SELECT id FROM bois_orders WHERE public_id=?) AND signature_verified=1 AND status='PROCESSED'");
$adminEvents->execute([$adminOrder['public_id']]);
if((int)$adminEvents->fetchColumn()!==1) throw new RuntimeException('Admin mock skipped signed event.');
$publicPayment=bois_p6_public_payment($pdo,$adminOrder['public_id']);
if($publicPayment['status']!=='PAID' || !$publicPayment['reference']){
    throw new RuntimeException('Public payment reference or status missing.');
}

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

[$partialRaw,$partialTs,$partialSig]=p6_signed($pdo,$config,$kitSession,'payment.refunded','evt-refund-partial',1000);
$partial=bois_p6_process_webhook_raw($pdo,$config,$partialRaw,$partialTs,$partialSig);
if(($partial['result']['status']??'')!=='PARTIALLY_REFUNDED') throw new RuntimeException('Partial refund failed.');
$partialRepeat=bois_p6_process_webhook_raw($pdo,$config,$partialRaw,$partialTs,$partialSig);
if(empty($partialRepeat['duplicate'])) throw new RuntimeException('Partial refund replay was processed twice.');
$kitReview=$pdo->prepare("SELECT fulfillment_status FROM bois_orders WHERE public_id=?");
$kitReview->execute([$kit['public_id']]);
if($kitReview->fetchColumn()!=='REVIEW_REQUIRED') throw new RuntimeException('Partial refund did not require review.');

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

$cancelOrder=p6_order($pdo,[['sku'=>'MEM-SENIOR','quantity'=>1,'metadata'=>['member_name'=>'P6 Avbruten']]],'p6-cancelled');
$cancelCheckout=bois_p6_checkout($pdo,$config,$cancelOrder['public_id'],$cancelOrder['public_token'],'card');
$cancelSession=bois_p6_session($pdo,$cancelCheckout['session_ref'],$cancelCheckout['session_token']);
[$cancelRaw,$cancelTs,$cancelSig]=p6_signed($pdo,$config,$cancelSession,'payment.cancelled','evt-cancelled');
$cancel=bois_p6_process_webhook_raw($pdo,$config,$cancelRaw,$cancelTs,$cancelSig);
if(($cancel['result']['status']??'')!=='CANCELLED') throw new RuntimeException('Cancellation failed.');

$refundSession=bois_p6_session($pdo,$checkout['session_ref'],$checkout['session_token']);
[$pendingRaw,$pendingTs,$pendingSig]=p6_signed($pdo,$config,$refundSession,'payment.refund_pending','evt-refund-pending');
$pending=bois_p6_process_webhook_raw($pdo,$config,$pendingRaw,$pendingTs,$pendingSig);
if(($pending['result']['status']??'')!=='REFUND_PENDING') throw new RuntimeException('Refund pending transition failed.');
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
if(bois_p4_stats($pdo)['active_members']!==2) throw new RuntimeException('Refund silently revoked membership; manual review invariant broken.');

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
