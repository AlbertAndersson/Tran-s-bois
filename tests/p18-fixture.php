<?php
declare(strict_types=1);
if(getenv('BOIS_P18_DISPOSABLE')!=='YES')throw new RuntimeException('Disposable CI only.');
$dir=$argv[1];
foreach(['p3_db','p4_membership','p5_batch','p6_payment','p7_assortment','p8_stripe','p9_sales'] as $module)
    require_once $dir.'/private/server/'.$module.'.php';
$path=$dir.'/private/config.php';
if(($argv[2]??'')==='fill'){
    $config=require $path;$pdo=bois_p3_pdo($config);
    while(bois_p3_gym_quota_status($pdo)['remaining']>0){
        bois_p3_create_order($pdo,p18_order('fill-'.bin2hex(random_bytes(8))));
    }
    echo "P18_FIXTURE_SOLD_OUT: ready\n";exit;
}
$config=require $dir.'/templates/production-config.example.php';
$config=array_replace($config,[
    'mode'=>'test','admin_token'=>'p18-isolated-synthetic-admin',
    'allowed_origins'=>['http://localhost:8766'],'public_base_url'=>'http://localhost:8766',
    'payment_provider'=>'mock','payment_webhook_secret'=>str_repeat('p18-synthetic-',4),
    'checkout_enabled'=>true,'sales_tracking_enabled'=>true,
    'consent_validity_days'=>180,'consent_cookie_path'=>'/',
    'db'=>['host'=>'127.0.0.1','port'=>3306,'database'=>'bois_p18_ci','user'=>'root','password'=>'root'],
]);
$pdo=bois_p3_pdo($config);
bois_p3_apply_schema($pdo);bois_p3_seed_catalog($pdo);
foreach(['p4','p5','p6','p7','p8','p9'] as $phase){$function='bois_'.$phase.'_apply_schema';$function($pdo);}
bois_p7_seed_assortment($pdo);
file_put_contents($path,'<?php return '.var_export($config,true).';');chmod($path,0600);
file_put_contents($dir.'/public/commerce-api.php',"<?php putenv('BOIS_P3_CONFIG_PATH=".$path."');require dirname(__DIR__).'/private/server/commerce-api.php';");
file_put_contents($dir.'/public/config.js',"window.BOIS_COMMERCE_CONFIG=Object.freeze({apiBase:'./commerce-api.php',environmentLabel:'P18 ISOLATED TEST',paymentEnabled:true,adminAuth:'token'});");
echo "P18_ISOLATED_PACKAGED_FIXTURE: ready\n";
function p18_order(string $key): array{
    return ['customer'=>['name'=>'P18 Synthetic','email'=>'p18@example.invalid'],
        'items'=>[['sku'=>'NW-GYM-ANNUAL','quantity'=>1,'metadata'=>[]]],
        'existing_member'=>true,'consent'=>true,'website'=>'','idempotency_key'=>$key];
}
