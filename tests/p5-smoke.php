<?php
declare(strict_types=1);

require dirname(__DIR__) . '/server/p3_db.php';
require dirname(__DIR__) . '/server/p5_batch.php';

$config = [
    'mode'=>'test',
    'admin_token'=>'test-admin',
    'allowed_origins'=>[],
    'mail_transport'=>'disabled',
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

function p5_make_kit(PDO $pdo, int $n): array
{
    return bois_p3_create_order($pdo,[
        'customer'=>[
            'name'=>'P5 Förälder '.$n,
            'email'=>'p5-'.$n.'@example.invalid',
            'phone'=>'070-000 '.str_pad((string)$n,2,'0',STR_PAD_LEFT).' 00',
        ],
        'items'=>[[
            'sku'=>'MATCHKIT-STAGING',
            'quantity'=>1,
            'metadata'=>[
                'team'=>'P13',
                'player_name'=>'P5 Spelare '.$n,
                'number'=>(string)(10+$n),
                'shirt_size'=>'140',
                'shorts_size'=>'152',
                'name_print'=>true,
                'number_print'=>true,
            ],
        ]],
        'consent'=>true,
        'website'=>'',
        'idempotency_key'=>'p5-kit-'.$n,
    ]);
}

$createdBatch=null;
for($i=1;$i<=8;$i++){
    $order=p5_make_kit($pdo,$i);
    $paid=bois_p5_mark_order_paid($pdo,$config,$order['public_id']);
    if($i<8 && count($paid['auto_batches'])!==0){
        throw new RuntimeException('Batch created before threshold.');
    }
    if($i===8){
        if(count($paid['auto_batches'])!==1) throw new RuntimeException('Threshold batch was not created.');
        $createdBatch=$paid['auto_batches'][0];
    }
}

if(($createdBatch['trigger_reason']??'')!=='THRESHOLD') throw new RuntimeException('Wrong threshold reason.');
if(($createdBatch['item_count']??0)!==8) throw new RuntimeException('Wrong batch item count.');

$duplicate=bois_p5_evaluate_batches($pdo,$config,false);
if(count($duplicate)!==0) throw new RuntimeException('Duplicate batch was created.');

$batches=bois_p5_admin_batches($pdo);
if(count($batches)!==1) throw new RuntimeException('Expected exactly one batch.');
if(($batches[0]['outbox_status']??'')!=='PENDING') throw new RuntimeException('Outbox should be pending.');
if(($batches[0]['to_email']??'')!=='supplier@example.invalid') throw new RuntimeException('Unsafe staging recipient.');
if(($batches[0]['cc_email']??'')!=='erik@example.invalid') throw new RuntimeException('Unsafe staging CC recipient.');

$csv=bois_p5_batch_csv($pdo,$createdBatch['public_id']);
if(!str_contains($csv,'P13') || !str_contains($csv,'P5 Spelare 8')) throw new RuntimeException('Batch CSV missing content.');
if(hash('sha256',$csv)!==$createdBatch['csv_sha256']) throw new RuntimeException('CSV hash mismatch.');

$attempts=0;
$failed=bois_p5_deliver_outbox($pdo,$config,function(array $message) use (&$attempts): bool {
    $attempts++;
    return false;
});
if($failed['failed']!==1) throw new RuntimeException('Failed mail did not enter retry.');

$batches=bois_p5_admin_batches($pdo);
$outboxId=(int)$batches[0]['outbox_id'];
if(($batches[0]['outbox_status']??'')!=='RETRY') throw new RuntimeException('Expected RETRY after failed send.');

bois_p5_retry_outbox($pdo,$outboxId);

$sent=bois_p5_deliver_outbox($pdo,$config,function(array $message): bool {
    return true;
});
if($sent['sent']!==1) throw new RuntimeException('Retry send did not succeed.');

$batches=bois_p5_admin_batches($pdo);
if(($batches[0]['status']??'')!=='SENT' || ($batches[0]['outbox_status']??'')!=='SENT'){
    throw new RuntimeException('Batch/outbox were not marked SENT.');
}

$sentItems=(int)$pdo->query(
    "SELECT COUNT(*) FROM bois_order_items WHERE fulfillment_type='BATCH_SUPPLIER' AND fulfillment_status='SENT_TO_SUPPLIER'"
)->fetchColumn();
if($sentItems!==8) throw new RuntimeException('Sent batch items not finalized.');

for($i=9;$i<=10;$i++){
    $order=p5_make_kit($pdo,$i);
    $paid=bois_p5_mark_order_paid($pdo,$config,$order['public_id']);
    if(count($paid['auto_batches'])!==0) throw new RuntimeException('Unexpected automatic batch below threshold.');
}
$manual=bois_p5_evaluate_batches($pdo,$config,true);
if(count($manual)!==1 || ($manual[0]['trigger_reason']??'')!=='MANUAL' || ($manual[0]['item_count']??0)!==2){
    throw new RuntimeException('Manual batch failed.');
}

$order=p5_make_kit($pdo,11);
$paid=bois_p5_mark_order_paid($pdo,$config,$order['public_id']);
if(count($paid['auto_batches'])!==0) throw new RuntimeException('Unexpected threshold batch for age test.');

$pdo->prepare(
    "UPDATE bois_order_items i
     JOIN bois_orders o ON o.id=i.order_id
     SET i.created_at=DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 8 DAY)
     WHERE o.public_id=? AND i.fulfillment_type='BATCH_SUPPLIER'"
)->execute([$order['public_id']]);

$aged=bois_p5_evaluate_batches($pdo,$config,false);
if(count($aged)!==1 || ($aged[0]['trigger_reason']??'')!=='MAX_WAIT'){
    throw new RuntimeException('Max-wait batch failed.');
}

$waiting=bois_p5_waiting_summary($pdo);
if(($waiting['waiting_item_count']??-1)!==0) throw new RuntimeException('Waiting queue should be empty.');

echo "P5_SMOKE: pass\n";
echo "THRESHOLD_QTY: 8\n";
echo "MAX_WAIT_HOURS: 168\n";
echo "THRESHOLD_BATCH: pass\n";
echo "DUPLICATE_PROTECTION: pass\n";
echo "CSV_HASH: pass\n";
echo "MAIL_RETRY: pass\n";
echo "MANUAL_BATCH: pass\n";
echo "MAX_WAIT_BATCH: pass\n";
echo "REAL_EMAIL_SENT: no\n";
