<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/ops/p13-common.php';
if(getenv('BOIS_P15_DISPOSABLE')!=='YES')throw new RuntimeException('Disposable CI only.');
$dir=$argv[1];$mode=$argv[2]??'seed';
$path=$dir.'/private/config.php';
if($mode==='seed'){
    mkdir($dir.'/private',0700);mkdir($dir.'/private/logs',0700);mkdir($dir.'/public',0755);
    $config=require dirname(__DIR__).'/server/production-config.example.php';
    $config['admin_token']=str_repeat('synthetic-only-',4);
    $config['db']=['host'=>'127.0.0.1','port'=>3306,'database'=>'bois_p15_ci','user'=>'root','password'=>'root'];
    $config['staging_db_identity']=['host'=>'127.0.0.1','database'=>'unrelated_disposable','user'=>'different'];
    $pdo=bois_p3_pdo($config);bois_p12_bootstrap($pdo,$config);
    $pdo->exec("CREATE USER 'p15_reader'@'%' IDENTIFIED BY 'synthetic-only'");
    $pdo->exec("GRANT SELECT ON bois_p15_ci.* TO 'p15_reader'@'%'");
    $config['db']['user']='p15_reader';$config['db']['password']='synthetic-only';
    $config['observability']=['enabled'=>true,'log_dir'=>$dir.'/private/logs','public_root'=>$dir.'/public'];
    $config['allowed_origins']=['http://127.0.0.1:8775'];
    file_put_contents($path,'<?php return '.var_export($config,true).';');chmod($path,0600);
    file_put_contents($dir.'/public/commerce-api.php',"<?php putenv('BOIS_P3_CONFIG_PATH=".$path."'); require ".var_export(dirname(__DIR__).'/server/commerce-api.php',true).";");
    file_put_contents($dir.'/private/baseline.json',json_encode(bois_p13_snapshot($pdo),JSON_THROW_ON_ERROR));
    echo "P15_SELECT_ONLY_FIXTURE: pass\n";exit;
}
$config=require $path;
if($mode==='bad-db'){$config['db']['password']='NEVER_LOG_THIS_CREDENTIAL';file_put_contents($path,'<?php return '.var_export($config,true).';');exit;}
$pdo=bois_p3_pdo($config);
if($mode==='unchanged'){
    bois_p13_equal(json_decode(file_get_contents($dir.'/private/baseline.json'),true),bois_p13_snapshot($pdo));
    echo "P15_NO_DB_WRITES_DDL_OR_LAUNCH: pass\n";exit;
}
// Mutation modes are isolated to this explicitly named loopback synthetic DB.
$config['db']['user']='root';$config['db']['password']='root';$pdo=bois_p3_pdo($config);
if($mode==='queue'){
    $pdo->exec("INSERT INTO bois_email_outbox(message_key,to_email,subject,payload_json,status,created_at) VALUES('synthetic','NEVER_LOG_THIS_PERSON@example.invalid','synthetic','{}','PENDING',CURRENT_TIMESTAMP-INTERVAL 20 MINUTE)");
}elseif($mode==='transport'){
    // P17 refuses injected transports in production. This disposable-only branch
    // exercises the same operational logger with a valid synthetic mail envelope.
    $config['mode']='test';
    $csv="synthetic,fixture\n";
    $payload=['kind'=>'SUPPLIER_BATCH','batch_id'=>'SYNTHETIC','order_count'=>1,'item_count'=>1,
        'csv_filename'=>'tranas-bois-synthetic.csv','csv_sha256'=>hash('sha256',$csv),'csv_base64'=>base64_encode($csv)];
    $pdo->prepare("UPDATE bois_email_outbox SET payload_json=? WHERE message_key='synthetic'")
        ->execute([json_encode($payload,JSON_THROW_ON_ERROR)]);
    bois_p15_begin($config,'worker');
    $result=bois_p5_deliver_outbox($pdo,$config,static function(){throw new RuntimeException('NEVER_LOG_THIS_PERSON NEVER_LOG_THIS_CREDENTIAL');});
    if($result['failed']!==1||$pdo->query("SELECT last_error FROM bois_email_outbox WHERE message_key='synthetic'")->fetchColumn()!=='transport_failed')throw new RuntimeException('Transport privacy failed.');
    // Exercise webhook exception privacy using its real persisted-error function.
    $e=new RuntimeException('NEVER_LOG_THIS_PERSON NEVER_LOG_THIS_CREDENTIAL');bois_p6_mark_event_error($pdo,0,$e);
    echo "P15_OUTBOX_WEBHOOK_PRIVATE_ERROR_CODES: pass\n";
}elseif($mode==='failed-queue'){$pdo->exec("UPDATE bois_email_outbox SET status='FAILED'");}
elseif($mode==='clean'){$pdo->exec("DELETE FROM bois_email_outbox WHERE message_key='synthetic'");file_put_contents($dir.'/private/baseline.json',json_encode(bois_p13_snapshot($pdo),JSON_THROW_ON_ERROR));}
else throw new RuntimeException('Unknown fixture mode.');
