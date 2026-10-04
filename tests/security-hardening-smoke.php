<?php
declare(strict_types=1);

// Real HTTP requests with an intentionally unavailable DB. Rejections must
// occur before DB initialization, and unexpected failures must stay opaque.
$root=dirname(__DIR__);
$tmp=sys_get_temp_dir().'/bois-hardening-'.bin2hex(random_bytes(8));
mkdir($tmp,0700);
$configPath=$tmp.'/runtime.php';
$config=['mode'=>'staging','admin_token'=>'synthetic-admin','allowed_origins'=>['https://example.test'],
    'db'=>['host'=>'127.0.0.1','port'=>1,'database'=>'unavailable','user'=>'fixture','password'=>'fixture']];
file_put_contents($configPath,'<?php return '.var_export($config,true).';');
$socket=stream_socket_server('tcp://127.0.0.1:0');
$address=stream_socket_get_name($socket,false);fclose($socket);
$base='http://'.$address;
$process=proc_open([PHP_BINARY,'-d','opcache.enable_cli=0','-d','disable_functions=curl_init,curl_exec,mail',
    '-S',$address,'-t',$root.'/server'],[0=>['file','/dev/null','r'],1=>['file',$tmp.'/server.log','a'],2=>['file',$tmp.'/server.log','a']],$pipes,$root,
    array_merge(getenv(),['BOIS_P3_CONFIG_PATH'=>$configPath]));

function hardening_request(string $url,string $method='GET',string $body='',array $headers=[]): array
{
    $ctx=stream_context_create(['http'=>['method'=>$method,'content'=>$body,'header'=>implode("\r\n",$headers),
        'ignore_errors'=>true,'timeout'=>3]]);
    $raw=@file_get_contents($url,false,$ctx);
    preg_match('/\s(\d{3})\s/',$http_response_header[0]??'',$match);
    return [(int)($match[1]??0),$raw?:'', $http_response_header??[]];
}
function hardening_expect(array $response,int $status): void
{
    if($response[0]!==$status) throw new RuntimeException('Expected '.$status.', got '.$response[0]);
    if($status!==204){
        json_decode($response[1],true,64,JSON_THROW_ON_ERROR);
        foreach(['SQLSTATE','Stack trace','runtime.php','synthetic-admin','unavailable'] as $secret){
            if(str_contains($response[1],$secret)) throw new RuntimeException('Internal error detail exposed.');
        }
    }
    foreach(['cache-control: no-store','x-frame-options: DENY','x-content-type-options: nosniff',
        'referrer-policy: no-referrer','content-security-policy:','permissions-policy:'] as $expected){
        if(!str_contains(strtolower(implode("\n",$response[2])),strtolower($expected))) throw new RuntimeException('Missing header '.$expected);
    }
}
try{
    for($i=0;$i<30;$i++){if(hardening_request($base.'/commerce-api.php?action=admin_orders')[0]!==0)break;usleep(100000);}
    hardening_expect(hardening_request($base.'/commerce-api.php?action=admin_orders',headers:['Authorization: Basic '.base64_encode('bois-demo:fixture')]),401);
    hardening_expect(hardening_request($base.'/commerce-api.php?action=admin_orders',headers:['X-Bois-Admin-Token: incorrect']),401);
    hardening_expect(hardening_request($base.'/commerce-api.php?action=admin_orders',headers:['X-Bois-Admin-Token: synthetic-admin']),500);
    hardening_expect(hardening_request($base.'/commerce-api.php?action=orders','GET'),405);
    hardening_expect(hardening_request($base.'/commerce-api.php?action=unknown'),404);
    hardening_expect(hardening_request($base.'/commerce-api.php?action=consent','POST','{}',['Content-Type: text/plain']),415);
    hardening_expect(hardening_request($base.'/commerce-api.php?action=consent','POST','{}',['Content-Type: application/json','Origin: https://attacker.invalid']),403);
    hardening_expect(hardening_request($base.'/commerce-api.php?action=consent','POST','{}',['Content-Type: application/json','Sec-Fetch-Site: cross-site']),403);
    hardening_expect(hardening_request($base.'/commerce-api.php?action=consent','POST',str_repeat('x',65537),['Content-Type: application/json']),413);
    hardening_expect(hardening_request($base.'/commerce-api.php?action=orders','OPTIONS',headers:['Origin: https://example.test']),204);
    foreach(['p3-migrate.php','p5-worker.php','seed-demo.php','p3_db.php','consent.php','security.php','http-errors.php'] as $path){
        hardening_expect(hardening_request($base.'/'.$path),403);
    }
    // Forwarded IP spoofing cannot reset the counter.
    $limited=false;
    for($i=0;$i<125;$i++){
        $response=hardening_request($base.'/commerce-api.php?action=admin_orders',headers:['X-Forwarded-For: 192.0.2.'.$i]);
        if($response[0]===429){hardening_expect($response,429);$limited=true;break;}
        hardening_expect($response,401);
    }
    if(!$limited)throw new RuntimeException('Admin abuse limit not enforced.');
    if(!str_contains(strtolower(implode("\n",$response[2])),'retry-after:')) throw new RuntimeException('Retry-After missing.');
    // Signed provider callbacks are not subject to a shared customer-IP quota.
    hardening_expect(hardening_request($base.'/commerce-api.php?action=payment_webhook','POST','{}',['Content-Type: application/json','Origin: https://provider.invalid']),500);
    $log=file_get_contents($tmp.'/server.log');
    if(str_contains($log,'SQLSTATE')) throw new RuntimeException('SQL details leaked to application log.');
    echo "P10_EARLY_HTTP_METHOD_ORIGIN_ADMIN_BODY_LIMITS: pass\nP10_HEADERS_ON_ERRORS: pass\nP10_INTERNAL_ENDPOINTS_DENIED: pass\nP10_ATOMIC_RATE_LIMIT_AND_PROXY_SPOOF_GUARD: pass\nP10_ERROR_DETAILS_REDACTED: pass\n";
}finally{
    if(is_resource($process)){proc_terminate($process);proc_close($process);}
    foreach(glob($tmp.'/security-rate/*')?:[] as $file)unlink($file);
    if(is_dir($tmp.'/security-rate'))rmdir($tmp.'/security-rate');
    foreach(glob($tmp.'/*')?:[] as $file)unlink($file);
    rmdir($tmp);
}
