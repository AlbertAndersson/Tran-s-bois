<?php
declare(strict_types=1);
require dirname(__DIR__).'/server/p3_db.php';
require dirname(__DIR__).'/server/p7_assortment.php';

$config=['db'=>[
    'host'=>getenv('BOIS_P3_TEST_DB_HOST') ?: '127.0.0.1',
    'port'=>(int)(getenv('BOIS_P3_TEST_DB_PORT') ?: 3306),
    'database'=>getenv('BOIS_P3_TEST_DB_NAME') ?: 'bois_test',
    'user'=>getenv('BOIS_P3_TEST_DB_USER') ?: 'root',
    'password'=>getenv('BOIS_P3_TEST_DB_PASSWORD') ?: 'root'
]];
$pdo=bois_p3_pdo($config);
$pdo->exec('CREATE TABLE IF NOT EXISTS unrelated_p7_sentinel (id INT PRIMARY KEY, marker VARCHAR(20))');
$pdo->exec("INSERT INTO unrelated_p7_sentinel VALUES (1,'untouched') ON DUPLICATE KEY UPDATE marker=marker");
$before=$pdo->query('SHOW CREATE TABLE unrelated_p7_sentinel')->fetch(PDO::FETCH_NUM)[1];
bois_p3_apply_schema($pdo);
bois_p3_seed_catalog($pdo);
$memberBefore=$pdo->query("SELECT price_ore FROM bois_variants WHERE sku='MEM-ADULT'")->fetchColumn();
bois_p7_apply_schema($pdo);
bois_p7_seed_assortment($pdo);
bois_p7_apply_schema($pdo);
bois_p7_seed_assortment($pdo);
$rows=bois_p7_admin_assortment($pdo);
if(count($rows)!==3) throw new RuntimeException('P7 seed is not idempotent.');
foreach($rows as $row){
    if($row['is_public'] || $row['is_orderable'] || $row['approved']) throw new RuntimeException('Preview product exposed.');
    if($row['purchase_price_ore']!==null || $row['margin_ore']!==null || $row['margin_pct']!==null){
        throw new RuntimeException('Unverified cost/margin invented.');
    }
    if(!$row['blockers'] || !$row['variants']) throw new RuntimeException('Commercial review incomplete.');
    foreach($row['variants'] as $variant){
        if($variant['supplier_sku']!==null) throw new RuntimeException('Unverified supplier SKU invented.');
    }
}
if((int)$pdo->query("SELECT price_ore FROM bois_variants WHERE sku='MEM-ADULT'")->fetchColumn()!==(int)$memberBefore){
    throw new RuntimeException('Membership price changed.');
}
$after=$pdo->query('SHOW CREATE TABLE unrelated_p7_sentinel')->fetch(PDO::FETCH_NUM)[1];
if($before!==$after || $pdo->query('SELECT marker FROM unrelated_p7_sentinel WHERE id=1')->fetchColumn()!=='untouched'){
    throw new RuntimeException('Non-BoIS table changed.');
}
echo "P7_MODEL: pass\nP7_SEED_IDEMPOTENT: pass\nNON_BOIS_UNCHANGED: pass\n";
