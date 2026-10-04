<?php
declare(strict_types=1);

// All credentials and recipients below are isolated CI fixtures. mail() is disabled.
if(($argv[1]??'')==='--worker'){
    require dirname(__DIR__).'/server/p6_payment.php';
    $dir=$argv[2];$queue=$argv[3];$id=$argv[4];
    $config=['mode'=>'test','mail_transport'=>'disabled','payment_mail_transport'=>'disabled','db'=>[
        'host'=>getenv('BOIS_P3_TEST_DB_HOST'),'database'=>getenv('BOIS_P3_TEST_DB_NAME'),
        'user'=>getenv('BOIS_P3_TEST_DB_USER'),'password'=>getenv('BOIS_P3_TEST_DB_PASSWORD')]];
    $pdo=bois_p3_pdo($config);
    touch($dir.'/ready-'.$id);
    $deadline=microtime(true)+10;
    while(!file_exists($dir.'/start')){if(microtime(true)>$deadline)exit(2);usleep(10000);}
    $fn=$queue==='supplier'?'bois_p5_deliver_outbox':'bois_p6_deliver_outbox';
    $r=$fn($pdo,$config,static function()use($dir):bool{
        file_put_contents($dir.'/accepted',"accepted\n",FILE_APPEND|LOCK_EX);
        usleep(500000);return true;
    },1);
    echo json_encode($r);exit;
}

require __DIR__.'/p6-smoke.php';
function p17_check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('P17 assertion: '.$label);}
function p17_denied(callable $fn):void{
    try{$fn();}catch(Throwable){return;}throw new RuntimeException('P17 expected refusal');
}
$dir=sys_get_temp_dir().'/bois-p17-'.bin2hex(random_bytes(8));
mkdir($dir,0700);mkdir($dir.'/sink',0700);mkdir($dir.'/public',0700);
$base=$config;
$sink=array_replace($config,['mail_transport'=>'sink','payment_mail_transport'=>'sink',
    'mail'=>['sink_dir'=>$dir.'/sink','public_root'=>$dir.'/public']]);

// Real order → signed PAID → supplier batch/outbox, not manually fabricated success.
$kit=p6_order($pdo,[['sku'=>'MATCHKIT-STAGING','quantity'=>8,'metadata'=>[
    'team'=>'P13','player_name'=>'Bo Test','shirt_size'=>'S',
    'number'=>'17','name_print'=>false,'number_print'=>true]]],'p17-supplier');
$checkout=bois_p6_checkout($pdo,$base,$kit['public_id'],$kit['public_token'],'card');
bois_p6_mock_event($pdo,$base,$checkout['session_ref'],$checkout['session_token'],'paid');
$supplier=$pdo->query("SELECT * FROM bois_email_outbox ORDER BY id DESC LIMIT 1")->fetch();
p17_check((bool)$supplier,'supplier queue from verified paid');
$envelope=bois_p17_render($supplier,'supplier');
p17_check(count($envelope['attachments'])===1,'CSV attachment');
$csv=base64_decode($envelope['attachments'][0]['content_base64'],true);
p17_check(str_contains($csv,'P13')&&str_contains($csv,'Bo Test'),'CSV content preserved');
$mime=bois_p17_mime($envelope,'shop@example.invalid');
p17_check(str_contains($mime['headers'],'Message-ID:')&&str_contains($mime['body'],'Content-Disposition: attachment;'),'MIME body and stable ID');

$rows=$pdo->query('SELECT * FROM bois_payment_outbox')->fetchAll();
$kinds=[];
foreach($rows as $row){$e=bois_p17_render($row,'payment');$p=json_decode($row['payload_json'],true);$kinds[]=$p['kind'];
    p17_check(str_contains($e['text'],$p['order'])&&str_contains($e['text'],'SEK'),'payment envelope');}
foreach(['PAYMENT_RECEIPT','PAYMENT_FAILED','PAYMENT_CANCELLED','REFUND_RECEIPT'] as $kind)p17_check(in_array($kind,$kinds,true),'template '.$kind);

$snapshot=$pdo->query('SELECT id,status,attempts,not_before FROM bois_payment_outbox')->fetchAll();
p17_check(bois_p6_deliver_outbox($pdo,$base)['processed']===0,'disabled leaves queue untouched');
p17_check($snapshot===$pdo->query('SELECT id,status,attempts,not_before FROM bois_payment_outbox')->fetchAll(),'disabled no mutations');
$prod=array_replace($base,['mode'=>'production']);
p17_check(bois_p6_deliver_outbox($pdo,$prod)['processed']===0,'production default disabled');
p17_denied(fn()=>bois_p6_deliver_outbox($pdo,$prod,fn()=>true));
foreach(['sink','php_mail','unknown'] as $transport){
    p17_denied(fn()=>bois_p17_sender($prod,$transport,'payment'));
    if($transport==='php_mail')p17_denied(fn()=>bois_p17_sender($base,$transport,'payment'));
}
$settings=['from'=>'shop@example.invalid','external_delivery_approved'=>true,'domain_verified'=>true,'provider_verified'=>true];
foreach(['production_launch_enabled','external_delivery_approved','domain_verified','provider_verified'] as $gate){
    $candidate=array_replace($prod,['production_launch_enabled'=>true,'mail'=>$settings]);
    if($gate==='production_launch_enabled')$candidate[$gate]=false;else $candidate['mail'][$gate]=false;
    p17_denied(fn()=>bois_p17_sender($candidate,'php_mail','payment'));
}

// Strict synthetic recipient/CC boundary, header injection, malformed templates/CSV.
$test=bois_p17_sender($base,'disabled','supplier',fn()=>true);
foreach(['to_email','cc_email'] as $field){p17_denied(fn()=>$test(array_replace($supplier,[$field=>'real@example.com'])));}
p17_denied(fn()=>$test(array_replace($supplier,['subject'=>"subject\r\nBcc: real@example.com"])));
$payload=json_decode($supplier['payload_json'],true);$payload['csv_base64']=base64_encode('tampered');
p17_denied(fn()=>bois_p17_render(array_replace($supplier,['payload_json'=>json_encode($payload)]),'supplier'));
p17_denied(fn()=>bois_p17_render(array_replace($supplier,['payload_json'=>'{}']),'supplier'));
$badSink=$sink;$badSink['mail']['sink_dir']=$dir.'/public';p17_denied(fn()=>bois_p17_sink_dir($badSink));
symlink($dir.'/sink',$dir.'/linked');$badSink['mail']['sink_dir']=$dir.'/linked';p17_denied(fn()=>bois_p17_sink_dir($badSink));
chmod($dir.'/sink',0755);p17_denied(fn()=>bois_p17_sink_dir($sink));chmod($dir.'/sink',0700);

// Both queues drain through the same local adapter. Existing future retries stay deferred.
$pdo->exec("UPDATE bois_payment_outbox SET not_before=CURRENT_TIMESTAMP WHERE status IN ('PENDING','RETRY')");
$r=bois_p6_deliver_outbox($pdo,$sink,null,100);p17_check($r['sent']>0&&$r['failed']===0,'payment sink drain');
$r=bois_p5_deliver_outbox($pdo,$sink,null,100);p17_check($r['sent']>0&&$r['failed']===0,'supplier sink drain');
$files=glob($dir.'/sink/*.json');p17_check(count($files)>4,'full mail chain');
foreach($files as $f){p17_check((fileperms($f)&0077)===0,'private sink file');$e=json_decode(file_get_contents($f),true);
    p17_check(str_ends_with($e['to'],'@example.invalid'),'no real sink recipient');}
$before=count($files);p17_check(bois_p17_sink($envelope,$sink),'sink idempotent');
p17_check(count(glob($dir.'/sink/*.json'))===$before,'no duplicate sink artifact');
$changed=$envelope;$changed['text'].=' changed';p17_denied(fn()=>bois_p17_sink($changed,$sink));
p17_check(bois_p5_deliver_outbox($pdo,$sink)['processed']===0&&bois_p6_deliver_outbox($pdo,$sink)['processed']===0,'sent queues not sent again');

// Retry/backoff/dead-letter and manual retry remain capped and privacy safe.
$paymentId=(int)$rows[0]['id'];
$pdo->exec("UPDATE bois_payment_outbox SET status='PENDING',attempts=0,not_before=CURRENT_TIMESTAMP WHERE id=$paymentId");
for($attempt=1;$attempt<=5;$attempt++){
    $r=bois_p6_deliver_outbox($pdo,$base,static function(){throw new RuntimeException('PRIVATE_ADDRESS_AND_SECRET');},1);
    p17_check($r['failed']===1,'failure attempt');
    $row=$pdo->query("SELECT * FROM bois_payment_outbox WHERE id=$paymentId")->fetch();
    p17_check((int)$row['attempts']===$attempt&&$row['last_error']==='transport_failed','bounded generic error');
    p17_check($row['status']===($attempt===5?'FAILED':'RETRY'),'retry/dead letter');
    $delay=strtotime($row['not_before'].' UTC')-time();
    p17_check(abs($delay-min(240,5*(2**($attempt-1)))*60)<5,'exponential backoff');
    p17_check(bois_p6_deliver_outbox($pdo,$base,fn()=>true,1)['processed']===0,'future/exhausted never sent');
    if($attempt<5)$pdo->exec("UPDATE bois_payment_outbox SET not_before=CURRENT_TIMESTAMP WHERE id=$paymentId");
}
bois_p6_retry_outbox($pdo,$paymentId);
p17_check(bois_p6_deliver_outbox($pdo,$base,fn()=>true,1)['processed']===0,'manual retry cannot bypass five attempts');

// Two independent processes both select the same due row; only one adapter runs.
foreach(['payment','supplier'] as $queue){
    $table=$queue==='payment'?'bois_payment_outbox':'bois_email_outbox';
    $id=(int)$pdo->query("SELECT MIN(id) FROM $table")->fetchColumn();
    $pdo->exec("UPDATE $table SET status='SENT'");
    $pdo->exec("UPDATE $table SET status='PENDING',attempts=0,not_before=CURRENT_TIMESTAMP WHERE id=$id");
    $race=$dir.'/race-'.$queue;mkdir($race,0700);$procs=[];$pipes=[];
    foreach([0,1] as $n){$procs[$n]=proc_open([PHP_BINARY,'-d','disable_functions=mail,curl_exec,curl_init',__FILE__,'--worker',$race,$queue,(string)$n],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes[$n]);fclose($pipes[$n][0]);}
    $deadline=microtime(true)+10;
    while(!file_exists($race.'/ready-0')||!file_exists($race.'/ready-1')){p17_check(microtime(true)<$deadline,'race ready');usleep(10000);}
    touch($race.'/start');
    foreach([0,1] as $n){$output=stream_get_contents($pipes[$n][1]);$err=stream_get_contents($pipes[$n][2]);fclose($pipes[$n][1]);fclose($pipes[$n][2]);p17_check(proc_close($procs[$n])===0,'race child');}
    p17_check(file_get_contents($race.'/accepted')==="accepted\n",'only one '.$queue.' delivery');
    p17_check((int)$pdo->query("SELECT attempts FROM $table WHERE id=$id")->fetchColumn()===1,'only one claim');
}
echo "P17_FULL_CHAIN_TEMPLATES_PRIVATE_SINK: pass\nP17_DISABLED_ACTIVATION_RECIPIENT_GATES: pass\nP17_RETRY_BACKOFF_CONCURRENT_WORKERS: pass\nEXTERNAL_MAIL_SENT: no\n";
