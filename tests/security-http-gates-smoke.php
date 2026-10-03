<?php
declare(strict_types=1);

// Seeds and verifies P9 in an isolated CI database, then tests the actual API.
require __DIR__.'/p9-sales-smoke.php';

$root=dirname(__DIR__);
$tmp=sys_get_temp_dir().'/bois-security-'.bin2hex(random_bytes(8));
if(!mkdir($tmp,0700)) throw new RuntimeException('Cannot create private test directory.');
$configPath=$tmp.'/runtime.php';
$writeConfig=function(array $value) use($configPath): void {
    $next=$configPath.'.next';
    file_put_contents($next,"<?php\nreturn ".var_export($value,true).";\n");
    chmod($next,0600);
    rename($next,$configPath);
};
$runtime=array_replace($config,['mode'=>'staging','sales_tracking_enabled'=>false]);
$writeConfig($runtime);
$socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
if($socket===false) throw new RuntimeException('Cannot reserve a loopback test port.');
$address=stream_socket_get_name($socket,false);
fclose($socket);
$base='http://'.$address.'/commerce-api.php';
$process=null;

function security_http_request(string $base,string $action,?array $data=null,string $cookie='',string $adminToken=''): array
{
    $context=stream_context_create(['http'=>[
        'method'=>$data===null?'GET':'POST',
        'header'=>"Content-Type: application/json\r\nAccept: application/json\r\n".($cookie!==''?"Cookie: boisConsent=$cookie\r\n":'').($adminToken!==''?"X-Bois-Admin-Token: $adminToken\r\n":''),
        'content'=>$data===null?'':json_encode($data,JSON_THROW_ON_ERROR),
        'ignore_errors'=>true,'timeout'=>5,
    ]]);
    $raw=@file_get_contents($base.'?action='.rawurlencode($action),false,$context);
    if($raw===false) return [0,[]];
    preg_match('/\s(\d{3})\s/',$http_response_header[0]??'',$match);
    return [(int)($match[1]??0),json_decode($raw,true,64,JSON_THROW_ON_ERROR),$http_response_header];
}

try{
    // cURL and mail are disabled in this local process, even if a guard regresses.
    $process=proc_open([
        PHP_BINARY,'-d','opcache.enable_cli=0','-d','disable_functions=curl_init,curl_exec,mail',
        '-S',$address,'-t',$root.'/server',
    ],[0=>['file','/dev/null','r'],1=>['file',$tmp.'/server.log','a'],2=>['file',$tmp.'/server.log','a']],$pipes,$root,
       array_merge(getenv(),['BOIS_P3_CONFIG_PATH'=>$configPath]));
    if(!is_resource($process)) throw new RuntimeException('Cannot start local test server.');
    $ready=false;
    for($attempt=0;$attempt<30;$attempt++){
        usleep(100000);
        [$status,$health]=security_http_request($base,'health');
        if($status===200){$ready=true;break;}
    }
    if(!$ready) throw new RuntimeException('Local API did not become ready.');

    $before=$snapshot();
    $httpOrder=null;
    foreach(['off','missing','production'] as $case){
        $runtime=array_replace($config,['mode'=>'staging','sales_tracking_enabled'=>false]);
        if($case==='missing') unset($runtime['sales_tracking_enabled']);
        if($case==='production'){$runtime['mode']='production';$runtime['sales_tracking_enabled']=true;}
        $writeConfig($runtime);
        [$status,$health]=security_http_request($base,'health');
        if($status!==200 || ($health['sales_tracking_enabled']??null)!==false) throw new RuntimeException('HTTP tracking gate not off: '.$case);
        [$status,$body]=security_http_request($base,'sales_event',$forged);
        if($status!==202 || ($body['sales']['disabled']??false)!==true) throw new RuntimeException('HTTP event was not disabled.');
        [$status,$body]=security_http_request($base,'orders',[
            'customer'=>['name'=>'HTTP Synthetic','email'=>'http-'.$case.'@example.invalid'],
            'items'=>[['sku'=>'MEM-ADULT','quantity'=>1,'metadata'=>['member_name'=>'HTTP Synthetic']]],
            'consent'=>true,'website'=>'','idempotency_key'=>'security-http-'.$case,
        ]+$forged);
        if($status!==201 || empty($body['order']['public_id'])) throw new RuntimeException('HTTP order failed with tracking off.');
        $httpOrder=$body['order'];
        if($snapshot()!==$before) throw new RuntimeException('HTTP order or event wrote disabled attribution.');
    }

    $runtime=array_replace($config,[
        'mode'=>'production','payment_provider'=>'stripe','stripe_mode'=>'live',
        'stripe_secret_key'=>'sk_live_'.str_repeat('fixture',6),
        'stripe_webhook_secret'=>'whsec_'.str_repeat('fixture',6),
        'production_launch_enabled'=>false,
    ]);
    $writeConfig($runtime);
    $paymentBefore=$pdo->query('SELECT * FROM bois_payments ORDER BY id')->fetchAll();
    [$status,$body]=security_http_request($base,'checkout',[
        'public_id'=>$httpOrder['public_id'],'public_token'=>$httpOrder['public_token'],'method'=>'card',
        'production_launch_enabled'=>true,
    ]);
    if($status!==401) throw new RuntimeException('HTTP production checkout bypassed launch gate.');
    if($paymentBefore!==$pdo->query('SELECT * FROM bois_payments ORDER BY id')->fetchAll()) throw new RuntimeException('Blocked HTTP checkout changed payments.');

    // Restore synthetic staging permission and prove the API still passes server config.
    $writeConfig(array_replace($config,['mode'=>'staging','sales_tracking_enabled'=>true]));
    [$status]=security_http_request($base,'admin_sales');
    if($status!==401)throw new RuntimeException('Admin API accepted missing token.');
    [$status]=security_http_request($base,'admin_sales',null,'','wrong-demo-token');
    if($status!==401)throw new RuntimeException('Admin API accepted wrong separate header.');
    [$status,$body]=security_http_request($base,'admin_sales',null,'',(string)$config['admin_token']);
    if($status!==200||!isset($body['sales']))throw new RuntimeException('Admin API separate header failed.');
    [$status,$body]=security_http_request($base,'sales_event',$forged);
    if($status!==202 || ($body['sales']['disabled']??false)!==true) throw new RuntimeException('No-choice direct event was accepted.');
    $noChoiceBefore=$snapshot();
    [$status,$body]=security_http_request($base,'orders',[
        'customer'=>['name'=>'No Choice Fixture','email'=>'no-choice@example.invalid'],
        'items'=>[['sku'=>'MEM-ADULT','quantity'=>1,'metadata'=>['member_name'=>'No Choice Fixture']]],
        'consent'=>true,'website'=>'','idempotency_key'=>'security-http-no-choice',
    ]+$forged);
    if($status!==201)throw new RuntimeException('No-choice order failed.');
    $noChoiceOrder=$body['order'];
    if($snapshot()!==$noChoiceBefore)throw new RuntimeException('No-choice order wrote sales data.');
    [$status,$body]=security_http_request($base,'checkout',[
        'public_id'=>$noChoiceOrder['public_id'],'public_token'=>$noChoiceOrder['public_token'],'method'=>'card',
    ]);
    if($status!==201||($body['checkout']['provider']??'')!=='mock')throw new RuntimeException('No-choice mock checkout failed.');
    $session=$body['checkout'];
    [$status,$body]=security_http_request($base,'mock_payment_event',[
        'session_ref'=>$session['session_ref'],'session_token'=>$session['session_token'],'outcome'=>'paid',
    ]);
    if($status!==200||($body['payment']['result']['status']??'')!=='PAID')throw new RuntimeException('No-choice mock payment failed.');
    [$status,$body,$headers]=security_http_request($base,'consent',['statistics'=>true]);
    if($status!==200 || ($body['choice']['statistics']??false)!==true) throw new RuntimeException('Consent choice failed.');
    $cookie='';
    foreach($headers as $header){if(preg_match('/^Set-Cookie:\s*boisConsent=([a-f0-9]{64})/i',$header,$match))$cookie=$match[1];}
    if($cookie==='')throw new RuntimeException('Consent capability missing.');
    [$status,$body]=security_http_request($base,'sales_event',$forged,$cookie);
    if($status!==202 || ($body['sales']['accepted']??false)!==true) throw new RuntimeException('Enabled synthetic HTTP event broken.');
    [$status,$body]=security_http_request($base,'orders',[
        'customer'=>['name'=>'HTTP Enabled','email'=>'http-enabled@example.invalid'],
        'items'=>[['sku'=>'MEM-ADULT','quantity'=>1,'metadata'=>['member_name'=>'HTTP Enabled']]],
        'consent'=>true,'website'=>'','idempotency_key'=>'security-http-enabled',
    ]+$forged,$cookie);
    if($status!==201) throw new RuntimeException('Enabled synthetic HTTP order failed.');
    $link=$pdo->prepare('SELECT l.session_id FROM bois_sales_order_links l JOIN bois_orders o ON o.id=l.order_id WHERE o.public_id=?');
    $link->execute([$body['order']['public_id']]);
    if($link->fetchColumn()!==$forged['sales_session_id']) throw new RuntimeException('Enabled HTTP order attribution missing.');
    [$status,$body]=security_http_request($base,'consent',['statistics'=>false],$cookie);
    if($status!==200 || ($body['choice']['statistics']??true)!==false)throw new RuntimeException('Withdrawal failed.');
    [$status,$body]=security_http_request($base,'sales_event',array_replace($forged,['event_key'=>'evt-revoked-must-not-exist']),$cookie);
    if($status!==202 || ($body['sales']['disabled']??false)!==true)throw new RuntimeException('Revoked cookie was accepted.');
    [$status,$body]=security_http_request($base,'sales_event',array_replace($forged,['event_key'=>'evt-tampered-must-not-exist']),str_repeat('a',64));
    if($status!==202 || ($body['sales']['disabled']??false)!==true)throw new RuntimeException('Tampered cookie was accepted.');
    foreach(['expired','policy'] as $case){
        [$status,$body,$headers]=security_http_request($base,'consent',['statistics'=>true]);
        $next='';foreach($headers as $header){if(preg_match('/^Set-Cookie:\s*boisConsent=([a-f0-9]{64})/i',$header,$m))$next=$m[1];}
        if($status!==200||$next==='')throw new RuntimeException('New consent fixture missing.');
        $column=$case==='expired'?'expires_at':'policy_version';
        $value=$case==='expired'?'2000-01-01 00:00:00':'obsolete-policy';
        $pdo->prepare("UPDATE bois_consent_choices SET $column=? WHERE token_hash=?")->execute([$value,hash('sha256',$next)]);
        [$status,$body]=security_http_request($base,'sales_event',array_replace($forged,['event_key'=>'evt-'.$case.'-must-not-exist']),$next);
        if($status!==202||($body['sales']['disabled']??false)!==true)throw new RuntimeException($case.' consent was accepted.');
    }
    echo "HTTP_TRACKING_OFF_ZERO_WRITES: pass\n";
    echo "HTTP_ORDER_WITHOUT_TRACKING: pass\n";
    echo "HTTP_CHECKOUT_CLOSED_NO_PAYMENT_MUTATION: pass\n";
    echo "HTTP_SYNTHETIC_TRACKING_ON: pass\n";
    echo "HTTP_ADMIN_SEPARATE_TOKEN_HEADER: pass\n";
    echo "EXTERNAL_NETWORK_TRANSPORTS_DISABLED: yes\n";
}finally{
    if(is_resource($process)){proc_terminate($process);proc_close($process);}
    foreach(glob($tmp.'/security-rate/*')?:[] as $file) unlink($file);
    if(is_dir($tmp.'/security-rate')) rmdir($tmp.'/security-rate');
    foreach(glob($tmp.'/*')?:[] as $file) unlink($file);
    rmdir($tmp);
}
