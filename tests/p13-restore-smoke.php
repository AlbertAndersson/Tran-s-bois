<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/ops/p13-common.php';
// This destructive fixture tool is restricted to disposable, loopback CI databases.
$host=getenv('BOIS_P3_TEST_DB_HOST');
$name=getenv('BOIS_P3_TEST_DB_NAME');
if($host!=='127.0.0.1'||!in_array($name,['bois_p13_source','bois_p13_restore'],true)||getenv('BOIS_P13_DISPOSABLE')!=='YES')
    throw new RuntimeException('Disposable local P13 database required.');
$config=['db'=>['host'=>$host,'port'=>3306,'database'=>$name,'user'=>getenv('BOIS_P3_TEST_DB_USER'),'password'=>getenv('BOIS_P3_TEST_DB_PASSWORD')]];
$pdo=bois_p3_pdo($config);
$mode=$argv[1]??'';$path=$argv[2]??'';
if($mode==='seed'&&$name==='bois_p13_source') {
    if($pdo->query('SHOW TABLES')->fetchColumn()!==false) throw new RuntimeException('Nonempty source refused.');
    // Existing fixture exercises mock paid, failed, cancelled and refunded orders,
    // memberships, benefits, receipt retries and idempotent signed mock events.
    require __DIR__.'/p6-smoke.php';
    bois_p7_apply_schema($pdo);bois_p7_seed_assortment($pdo);
    bois_p8_apply_schema($pdo);bois_p9_apply_schema($pdo);bois_consent_schema($pdo);
    $pdo->prepare('INSERT INTO bois_schema_migrations(version) VALUES(?)')->execute(['20261003_p12_production_bootstrap_v1']);
    echo "P13_SYNTHETIC_FIXTURE: pass\n";
} elseif($mode==='snapshot') {
    file_put_contents($path,json_encode(bois_p13_snapshot($pdo),JSON_THROW_ON_ERROR));chmod($path,0600);
    echo "P13_SNAPSHOT: pass\n";
} elseif($mode==='verify'&&$name==='bois_p13_restore') {
    $before=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    $after=bois_p13_snapshot($pdo);bois_p13_equal($before,$after);
    foreach(['bois_orders','bois_order_items','bois_memberships','bois_members','bois_benefit_entitlements','bois_payments','bois_payment_events'] as $table)
        if($after['tables'][$table]['rows']===0) throw new RuntimeException('Missing nonempty business fixture.');
    $joined=(int)$pdo->query('SELECT COUNT(*) FROM bois_members m JOIN bois_memberships ms ON ms.id=m.source_membership_id JOIN bois_order_items i ON i.id=ms.order_item_id JOIN bois_orders o ON o.id=i.order_id JOIN bois_payments p ON p.order_id=o.id')->fetchColumn();
    if($joined===0||$after['foreign_keys_checked']===0) throw new RuntimeException('Missing restored business relations.');
    // Prove detector rejects real restored-data and restored-schema corruption.
    $pdo->beginTransaction();$pdo->exec("UPDATE bois_orders SET payment_status='P13_CORRUPT' LIMIT 1");$pdo->commit();
    $refused=false;try{bois_p13_equal($before,bois_p13_snapshot($pdo));}catch(RuntimeException){$refused=true;}
    if(!$refused) throw new RuntimeException('Data corruption was accepted.');
    $pdo->exec('ALTER TABLE bois_orders ADD COLUMN p13_corruption INT NULL');
    $refused=false;try{bois_p13_equal($before,bois_p13_snapshot($pdo));}catch(RuntimeException){$refused=true;}
    if(!$refused) throw new RuntimeException('Schema corruption was accepted.');
    echo "P13_RESTORE_SCHEMA_LEDGER_DATA_FKS: pass\nP13_CORRUPTION_REJECTED: pass\nREAL_PAYMENT_EMAIL_PRODUCTION_ACCESS: no\n";
} else throw new RuntimeException('Invalid isolated test operation.');
