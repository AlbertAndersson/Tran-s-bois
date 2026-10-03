<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/ops/p12-common.php';
if(getenv('BOIS_P14_DISPOSABLE')!=='YES')throw new RuntimeException('Disposable CI only.');
$dir=$argv[1];mkdir($dir.'/private',0700);
$config=require dirname(__DIR__).'/server/production-config.example.php';
$config['admin_token']=str_repeat('synthetic-rate-key-',4);
$config['db']=['host'=>'127.0.0.1','port'=>3306,'database'=>'bois_p14_ci','user'=>'root','password'=>'root'];
$config['staging_db_identity']=['host'=>'127.0.0.1','database'=>'other_disposable_db','user'=>'different_principal'];
bois_p12_bootstrap(bois_p3_pdo($config),$config);
$config['mode']='test';$config['allowed_origins']=['http://localhost:8765'];
$config['personal_admin']=['enabled'=>true,'users_file'=>$dir.'/private/users.json','state_dir'=>$dir.'/private','public_root'=>$dir.'/public'];
$users=[];foreach(['tech'=>'superadmin','club'=>'club_admin','reader'=>'operator'] as $id=>$role)
    $users[$id]=['enabled'=>true,'epoch'=>1,'role'=>$role,'password_hash'=>password_hash('fixture-'.$id,PASSWORD_BCRYPT,['cost'=>12]),'totp_secret'=>'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'];
file_put_contents($dir.'/private/users.json',json_encode($users));chmod($dir.'/private/users.json',0600);
file_put_contents($dir.'/private/config.php','<?php return '.var_export($config,true).';');chmod($dir.'/private/config.php',0600);
file_put_contents($dir.'/public/commerce-api.php',"<?php putenv('BOIS_P3_CONFIG_PATH=".$dir."/private/config.php'); require ".var_export(dirname(__DIR__).'/server/commerce-api.php',true).";");
file_put_contents($dir.'/public/config.js',"window.BOIS_COMMERCE_CONFIG=Object.freeze({apiBase:'./commerce-api.php',adminAuth:'personal',environmentLabel:'P14 SYNTHETIC',paymentEnabled:false});");
echo "P14_BROWSER_FIXTURE: ready\n";
