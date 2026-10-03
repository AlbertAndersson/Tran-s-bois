<?php
declare(strict_types=1);

// HTTP bootstrap regression: private sandbox config and the public callback.
$root=dirname(__DIR__);
$tmp=sys_get_temp_dir().'/bois-p11-'.bin2hex(random_bytes(8));
mkdir($tmp,0700);
$config=['mode'=>'staging','admin_token'=>'sandbox-fixture-token','allowed_origins'=>[],
    'payment_provider'=>'stripe','stripe_mode'=>'test',
    'db'=>['host'=>'127.0.0.1','port'=>1,'database'=>'fixture','user'=>'fixture','password'=>'fixture']];
file_put_contents($tmp.'/config.php','<?php return '.var_export($config,true).';');
file_put_contents($tmp.'/commerce-api.php',"<?php putenv('BOIS_P3_CONFIG_PATH=".$tmp."/config.php'); require ".var_export($root.'/server/commerce-api.php',true).";");
file_put_contents($tmp.'/bois-stripe-sandbox-webhook.php',"<?php putenv('BOIS_P3_CONFIG_PATH=".$tmp."/config.php'); require ".var_export($root.'/server/stripe-webhook.php',true).";");
$socket=stream_socket_server('tcp://127.0.0.1:0');
$address=stream_socket_get_name($socket,false);fclose($socket);
$process=proc_open([PHP_BINARY,'-S',$address,'-t',$tmp],[0=>['file','/dev/null','r'],1=>['file',$tmp.'/log','a'],2=>['file',$tmp.'/log','a']],$pipes,$root,
    array_merge(getenv(),['BOIS_P3_CONFIG_PATH'=>$tmp.'/intentionally-missing-mock-config.php']));
function p11_http(string $url,string $method='GET',array $headers=[]): int {
    $ctx=stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),'content'=>$method==='POST'?'{}':'','ignore_errors'=>true,'timeout'=>3]]);
    @file_get_contents($url,false,$ctx);
    preg_match('/\s(\d{3})\s/',$http_response_header[0]??'',$match);
    return (int)($match[1]??0);
}
try{
    $base='http://'.$address;
    for($i=0;$i<30;$i++){if(p11_http($base.'/bois-stripe-sandbox-webhook.php')!==0)break;usleep(100000);}
    if(p11_http($base.'/bois-stripe-sandbox-webhook.php')!==405)throw new RuntimeException('Public callback wrapper rejected before method gate.');
    if(p11_http($base.'/bois-stripe-sandbox-webhook.php','POST',['Content-Type: application/json'])!==400)throw new RuntimeException('Unsigned callback accepted or wrong config loaded.');
    if(p11_http($base.'/commerce-api.php?action=admin_orders',headers:['X-Bois-Admin-Token: mock-fixture-token'])!==401)throw new RuntimeException('Wrong runtime admin token accepted.');
    if(p11_http($base.'/commerce-api.php?action=admin_orders',headers:['X-Bois-Admin-Token: sandbox-fixture-token'])!==500)throw new RuntimeException('Sandbox token did not reach intentionally unavailable DB.');
    echo "P11_HTTP_SANDBOX_RUNTIME_ROUTING: pass\nP11_PUBLIC_CALLBACK_METHOD_SIGNATURE_GATES: pass\nREAL_STRIPE_CALLS: no\n";
}finally{
    if(is_resource($process)){proc_terminate($process);proc_close($process);}
    foreach(glob($tmp.'/security-rate/*')?:[] as $f)unlink($f);
    if(is_dir($tmp.'/security-rate'))rmdir($tmp.'/security-rate');
    foreach(glob($tmp.'/*')?:[] as $f)unlink($f);
    rmdir($tmp);
}
