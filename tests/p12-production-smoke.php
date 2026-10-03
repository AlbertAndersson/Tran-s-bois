<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/ops/p12-common.php';

function p12_assert(bool $ok,string $message): void {if(!$ok) throw new RuntimeException($message);}
function p12_refused(callable $fn): void {
    try{$fn();}catch(Throwable $e){return;}throw new RuntimeException('Unsafe operation accepted.');
}
function p12_snapshot(PDO $pdo): string {
    $result=[];
    foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table){
        $result[$table]=[$pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1],
            $pdo->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC)];
    }
    ksort($result);return hash('sha256',serialize($result));
}
function p12_cli(array $args): array {
    $process=proc_open(array_merge([PHP_BINARY,'-d','disable_functions=curl_init,curl_exec,mail'],$args),[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $out=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    return [proc_close($process),$out];
}
function p12_http(string $url,string $method='GET'): int {
    $ctx=stream_context_create(['http'=>['method'=>$method,'header'=>'Content-Type: application/json','content'=>$method==='POST'?'{}':'','ignore_errors'=>true,'timeout'=>3]]);
    @file_get_contents($url,false,$ctx);preg_match('/\s(\d{3})\s/',$http_response_header[0]??'',$m);return (int)($m[1]??0);
}

$root=dirname(__DIR__);
$tmp=sys_get_temp_dir().'/bois-p12-'.bin2hex(random_bytes(6));mkdir($tmp,0700);
$adminConfig=['db'=>['host'=>getenv('BOIS_P3_TEST_DB_HOST')?:'127.0.0.1','port'=>(int)(getenv('BOIS_P3_TEST_DB_PORT')?:3306),
    'database'=>'bois_p12_candidate','user'=>getenv('BOIS_P3_TEST_DB_USER')?:'root','password'=>getenv('BOIS_P3_TEST_DB_PASSWORD')?:'root']];
$admin=bois_p3_pdo($adminConfig);
$staging=bois_p3_pdo(['db'=>array_replace($adminConfig['db'],['database'=>'bois_p12_staging'])]);
$stageBefore=p12_snapshot($staging);
$config=require $root.'/server/production-config.example.php';
$config['admin_token']=str_repeat('synthetic-admin-',4);
$config['db']=array_replace($adminConfig['db'],['user'=>'bois_p12_owner','password'=>'synthetic-owner-only']);
$config['staging_db_identity']=['host'=>$adminConfig['db']['host'],'database'=>'bois_p12_staging','user'=>$adminConfig['db']['user']];
$path=$tmp.'/config.php';
file_put_contents($path,'<?php return '.var_export($config,true).';');chmod($path,0600);
$pdo=bois_p3_pdo($config);
$server=null;
try{
    $empty=p12_snapshot($pdo);
    foreach(['same_db'=>['db'=>array_replace($config['db'],['database'=>'BOIS_P12_STAGING'])],
        'same_user'=>['db'=>array_replace($config['db'],['user'=>$config['staging_db_identity']['user']])],
        'launch'=>['production_launch_enabled'=>true],'mail'=>['mail_transport'=>'php_mail'],
        'provider'=>['payment_provider'=>'mock'],'statistics'=>['sales_tracking_enabled'=>true],
        'key'=>['stripe_secret_key'=>'synthetic-key']] as $label=>$change){
        $bad=array_replace($config,$change);
        p12_refused(fn()=>bois_p12_bootstrap($pdo,$bad));
        p12_assert(p12_snapshot($pdo)===$empty,'Rejected bootstrap changed target: '.$label);
    }
    p12_refused(fn()=>bois_p12_bootstrap($staging,$config));
    bois_p12_bootstrap($pdo,$config);
    $before=p12_snapshot($pdo);
    bois_p12_bootstrap($pdo,$config);
    p12_assert($before===p12_snapshot($pdo),'Second bootstrap modified data or schema.');
    p12_assert(bois_p12_readiness($pdo,$config)['ready_for_closed_verification'],'Closed candidate not ready.');
    p12_assert(!bois_p6_payment_enabled($config)&&!bois_p9_tracking_enabled($config),'External function enabled.');
    // SELECT-only user proves readiness cannot depend on DDL/DML privileges.
    $readConfig=$config;$readConfig['db']['user']='bois_p12_reader';$readConfig['db']['password']='synthetic-reader-only';
    $readPath=$tmp.'/reader.php';file_put_contents($readPath,'<?php return '.var_export($readConfig,true).';');chmod($readPath,0600);
    [$code,$out]=p12_cli([$root.'/ops/p12-readiness.php',$readPath]);
    p12_assert($code===0&&str_contains($out,'"ready_for_closed_verification": true'),'SELECT-only readiness failed.');
    p12_assert($before===p12_snapshot($pdo),'Readiness modified target.');
    chmod($readPath,0644);[$code]=p12_cli([$root.'/ops/p12-readiness.php',$readPath]);p12_assert($code===1,'Public config permissions accepted.');chmod($readPath,0600);
    // Real HTTP requests while closed must fail before DB even with admin token.
    file_put_contents($tmp.'/commerce-api.php',"<?php putenv('BOIS_P3_CONFIG_PATH=".$path."'); require ".var_export($root.'/server/commerce-api.php',true).";");
    $socket=stream_socket_server('tcp://127.0.0.1:0');$address=stream_socket_get_name($socket,false);fclose($socket);
    $server=proc_open([PHP_BINARY,'-d','disable_functions=curl_init,curl_exec,mail','-S',$address,'-t',$tmp],[0=>['file','/dev/null','r'],1=>['file',$tmp.'/http.log','a'],2=>['file',$tmp.'/http.log','a']],$pipes);
    for($i=0;$i<30;$i++){if(p12_http('http://'.$address.'/commerce-api.php')!==0)break;usleep(100000);}
    foreach(['health'=>'GET','catalog'=>'GET','orders'=>'POST','checkout'=>'POST','admin_orders'=>'GET','consent'=>'POST','sales_event'=>'POST'] as $action=>$method){
        p12_assert(p12_http('http://'.$address.'/commerce-api.php?action='.$action,$method)===503,'Closed HTTP traffic accepted: '.$action);
    }
    p12_assert($before===p12_snapshot($pdo),'HTTP requests changed target.');
    $accidentallyOpen=$config;$accidentallyOpen['production_launch_enabled']=true;
    file_put_contents($path,'<?php return '.var_export($accidentallyOpen,true).';');
    p12_assert(p12_http('http://'.$address.'/commerce-api.php?action=orders','POST')===503,'Launch flag alone accepted orders.');
    file_put_contents($path,'<?php return '.var_export($config,true).';');
    // Complete synthetic approvals validate only the checker, never business approval.
    $approved=$config;
    $approved=array_replace($approved,['admin_token'=>str_repeat('synthetic-future-',4),'checkout_enabled'=>true,
        'payment_provider'=>'stripe','stripe_mode'=>'live','stripe_secret_key'=>'rk_live_'.str_repeat('synthetic',6),
        'stripe_webhook_secret'=>'whsec_'.str_repeat('synthetic',6),'public_base_url'=>'https://bois.example.org/shop',
        'allowed_origins'=>['https://bois.example.org'],'seller_legal_name'=>'Synthetic seller','seller_org_number'=>'synthetic',
        'support_email'=>'fixture@example.invalid','terms_url'=>'https://bois.example.org/terms','privacy_url'=>'https://bois.example.org/privacy',
        'merchant_verified'=>true,'stripe_fees_approved'=>true,'refund_policy_approved'=>true,'production_launch_enabled'=>true,'payment_mail_transport'=>'php_mail']);
    foreach(['go_live','p18_release','backup_restore','personal_admin_mfa','seller_merchant_bank','legal_policies','product_partner_prices','membership_period','support_mail','domain_dns_tls','privacy_retention','final_smoke_rollback'] as $key){
        $approved['production_decisions'][$key]=['approved'=>true,'reference'=>'synthetic-test-only'];
    }
    p12_assert(!in_array(false,bois_p12_launch_checks($config,$approved),true),'Complete synthetic preflight rejected.');
    foreach(bois_p12_launch_checks($config,$approved) as $key=>$value){p12_assert($value===true,'Unexpected complete check.');}
    foreach(array_keys($approved['production_decisions']) as $key){$bad=$approved;unset($bad['production_decisions'][$key]);p12_assert(in_array(false,bois_p12_launch_checks($config,$bad),true),'Missing decision accepted.');}
    foreach(['stripe_secret_key','stripe_webhook_secret','public_base_url','admin_token'] as $key){$bad=$approved;$bad[$key]='';p12_assert(in_array(false,bois_p12_launch_checks($config,$bad),true),'Missing secret/URL accepted.');}
    $approvedPath=$tmp.'/approved.php';file_put_contents($approvedPath,'<?php return '.var_export($config,true).';');chmod($approvedPath,0600);
    [$code,$out]=p12_cli([$root.'/ops/p12-launch-preflight.php',$path,$approvedPath]);
    p12_assert($code===1&&str_contains($out,'decision_go_live')&&!str_contains($out,$config['db']['password']),'Launch preflight leaked or falsely approved.');
    $pdo->exec('CREATE TABLE unexpected_table(id INT)');$foreign=p12_snapshot($pdo);
    p12_refused(fn()=>bois_p12_bootstrap($pdo,$config));p12_assert($foreign===p12_snapshot($pdo),'Foreign-table refusal changed DB.');$pdo->exec('DROP TABLE unexpected_table');
    $pdo->exec("INSERT INTO bois_customers(customer_uuid,name,email) VALUES('synthetic-test-uuid','Synthetic','test@example.invalid')");
    $withData=p12_snapshot($pdo);p12_refused(fn()=>bois_p12_bootstrap($pdo,$config));p12_assert($withData===p12_snapshot($pdo),'Data refusal changed DB.');
    p12_assert(!bois_p12_readiness($pdo,$config)['ready_for_closed_verification'],'Customer data not detected.');$pdo->exec('DELETE FROM bois_customers');
    $pdo->exec('ALTER TABLE bois_payments DROP COLUMN checkout_url');$drift=p12_snapshot($pdo);
    p12_assert(!bois_p12_readiness($pdo,$config)['ready_for_closed_verification'],'Schema drift accepted.');
    p12_refused(fn()=>bois_p12_bootstrap($pdo,$config));p12_assert($drift===p12_snapshot($pdo),'Completed ledger hid schema drift.');
    p12_assert($stageBefore===p12_snapshot($staging),'Staging changed.');
    echo "P12_PRODUCTION_CONFIG_FAIL_CLOSED: pass\nP12_BOOTSTRAP_IDEMPOTENT: pass\nP12_READINESS_SELECT_ONLY_UNCHANGED: pass\nP12_DATABASE_AND_CREDENTIAL_ISOLATION: pass\nP12_HTTP_CLOSED_NO_DATA_WRITES: pass\nP12_LAUNCH_DECISIONS_AND_SECRETS_REQUIRED: pass\nP12_FOREIGN_DATA_SCHEMA_DRIFT_REFUSED: pass\nP12_STAGING_UNCHANGED: pass\nLIVE_STRIPE_CALLS: no\nEXTERNAL_MAIL: no\nNEW_EXTERNAL_COST: 0\n";
}finally{
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    foreach(glob($tmp.'/*')?:[] as $f)unlink($f);rmdir($tmp);
}
