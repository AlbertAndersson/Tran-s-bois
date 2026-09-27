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
bois_p3_apply_schema($pdo);bois_p3_seed_catalog($pdo);bois_p7_apply_schema($pdo);bois_p7_seed_assortment($pdo);
$before=new DateTimeImmutable('2026-12-31 23:59:59',new DateTimeZone('Europe/Stockholm'));
$after=new DateTimeImmutable('2027-01-01 00:00:00',new DateTimeZone('Europe/Stockholm'));
$pdo->exec("INSERT INTO bois_suppliers(supplier_key,name,email) VALUES('p7-ci-synthetic','Synthetic CI supplier',NULL)");
$supplier=(int)$pdo->query("SELECT id FROM bois_suppliers WHERE supplier_key='p7-ci-synthetic'")->fetchColumn();
$product=(int)$pdo->query("SELECT id FROM bois_products WHERE product_key='bois-1941-hoodie'")->fetchColumn();
$pdo->prepare("UPDATE bois_products SET supplier_id=?,is_public=1,is_orderable=1,price_ore=54900 WHERE id=?")
    ->execute([$supplier,$product]);
$pdo->prepare(
    "UPDATE bois_p7_assortment SET supplier_id=?,supplier_status='VERIFIED',supplier_sku='SYNTHETIC-PARENT',
     sku_status='VERIFIED',purchase_price_ore=20000,decoration_cost_ore=5000,shipping_handling_ore=3000,
     sale_price_ore=54900,sale_price_ex_vat_ore=43920,price_status='VERIFIED',moq=1,lead_time_days=7,
     verification_status='VERIFIED',approved=1,blockers_json=JSON_ARRAY(),verified_at='2026-09-27'
     WHERE product_id=?"
)->execute([$supplier,$product]);
$variants=$pdo->prepare('SELECT id,variant_key,size_label FROM bois_p7_variants WHERE product_id=?');
$variants->execute([$product]);
foreach($variants->fetchAll() as $v){
    $sku='P7-CI-'.strtoupper((string)$v['size_label']);
    $pdo->prepare("UPDATE bois_p7_variants SET supplier_sku=?,verification_status='VERIFIED' WHERE id=?")
        ->execute([$sku,(int)$v['id']]);
    $pdo->prepare("INSERT INTO bois_variants(product_id,sku,name,price_ore,active) VALUES(?,?,?,?,1)")
        ->execute([$product,$sku,(string)$v['variant_key'],54900]);
}
$sku='P7-CI-S';
if(bois_p7_launch_allowed($pdo,'bois-1941-hoodie',$before)) throw new RuntimeException('Prelaunch gate opened.');
if(array_filter(bois_p3_catalog($pdo,false,$before),fn($p)=>$p['product_key']==='bois-1941-hoodie')){
    throw new RuntimeException('Prelaunch product visible in catalog.');
}
$rejected=false;
try{bois_p3_resolve_variant($pdo,$sku,$before);}catch(InvalidArgumentException){$rejected=true;}
if(!$rejected) throw new RuntimeException('Direct SKU resolution bypassed prelaunch gate.');
$rejected=false;
try{
    bois_p3_create_order($pdo,[
        'customer'=>['name'=>'Synthetic P7','email'=>'p7@example.invalid'],
        'items'=>[['sku'=>$sku,'quantity'=>1,'metadata'=>[]]],'consent'=>true,'website'=>'',
        'idempotency_key'=>'p7-before'
    ],$before);
}catch(InvalidArgumentException){$rejected=true;}
if(!$rejected) throw new RuntimeException('Direct order bypassed prelaunch gate.');
if(!bois_p7_launch_allowed($pdo,'bois-1941-hoodie',$after)) throw new RuntimeException('Approved postlaunch product blocked.');
if(!array_filter(bois_p3_catalog($pdo,false,$after),fn($p)=>$p['product_key']==='bois-1941-hoodie')){
    throw new RuntimeException('Approved postlaunch product absent from catalog.');
}
$resolved=bois_p3_resolve_variant($pdo,$sku,$after);
if($resolved['sku']!==$sku) throw new RuntimeException('Approved SKU blocked after launch.');
if(bois_p7_launch_allowed($pdo,'supporter-tee',$after)) throw new RuntimeException('Unapproved product opened after date.');
$pdo->prepare("UPDATE bois_p7_assortment SET blockers_json=JSON_ARRAY('quote missing') WHERE product_id=?")
    ->execute([$product]);
if(bois_p7_launch_allowed($pdo,'bois-1941-hoodie',$after)) throw new RuntimeException('Blocker ignored after launch.');
$catalog=bois_p3_catalog($pdo,false,$before);
foreach(['membership','nordic-gym','match-kit'] as $key){
    if(!array_filter($catalog,fn($p)=>$p['product_key']===$key)) throw new RuntimeException('Existing category blocked: '.$key);
}
echo "P7_PRELAUNCH_CATALOG: pass\nP7_DIRECT_ORDER_BLOCKED: pass\nP7_POSTLAUNCH_APPROVED: pass\nP7_UNAPPROVED_BLOCKED: pass\nP3_CATEGORIES_PRESERVED: pass\n";
