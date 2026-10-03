<?php
declare(strict_types=1);

// HTTP controls also run on failures before runtime/DB initialization.
function bois_security_headers(): void
{
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: DENY');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
    ini_set('display_errors', '0');
}

function bois_security_reject(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error'=>$message], JSON_THROW_ON_ERROR);
    exit;
}

function bois_security_admin(array $config): void
{
    require_once __DIR__.'/p14_admin.php';
    if(bois_p14_personal($config)){
        bois_p14_request_authorize($config,(string)($_GET['action']??''));return;
    }
    $expected=$config['admin_token']??'';
    $token=$_SERVER['HTTP_X_BOIS_ADMIN_TOKEN']??'';
    if($token===''){
        $header=$_SERVER['HTTP_AUTHORIZATION']??'';
        $token=is_string($header)&&str_starts_with($header,'Bearer ')?substr($header,7):'';
    }
    if(!is_string($expected)||$expected===''||!is_string($token)||$token===''||!hash_equals($expected,$token)){
        bois_security_reject(401,'Ej behörig.');
    }
}

// Atomic private file buckets: bounded number of files and entries, no raw IPs,
// no trust in forwarded client headers. A storage failure denies the request.
function bois_security_rate(array $config,string $group,int $limit): void
{
    $dir=$config['_security_dir']??null;
    if(!is_string($dir)||$dir==='') throw new RuntimeException('Security storage unavailable.');
    if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir)) throw new RuntimeException('Security storage unavailable.');
    $key=hash_hmac('sha256',$group.'|'.($_SERVER['REMOTE_ADDR']??'unknown'),(string)$config['admin_token']);
    $path=$dir.'/'.substr($key,0,2).'.json';
    $handle=fopen($path,'c+');
    if($handle===false) throw new RuntimeException('Security storage unavailable.');
    try{
        if(!chmod($path,0600)||!flock($handle,LOCK_EX)) throw new RuntimeException('Security lock unavailable.');
        $raw=stream_get_contents($handle,262145);
        if($raw===false||strlen($raw)>262144) throw new RuntimeException('Security storage invalid.');
        $rows=$raw===''?[]:json_decode($raw,true,16,JSON_THROW_ON_ERROR);
        if(!is_array($rows)) throw new RuntimeException('Security storage invalid.');
        $now=time();
        foreach($rows as $k=>$row){if(!is_array($row)||($row['until']??0)<=$now) unset($rows[$k]);}
        $row=$rows[$key]??['until'=>$now+60,'count'=>0];
        $blocked=$row['count']>=$limit || (!isset($rows[$key])&&count($rows)>=1024);
        if(!$blocked){$row['count']++;$rows[$key]=$row;}
        $encoded=json_encode($rows,JSON_THROW_ON_ERROR);
        rewind($handle);
        if(!ftruncate($handle,0)||fwrite($handle,$encoded)!==strlen($encoded)||!fflush($handle)) throw new RuntimeException('Security storage unavailable.');
    }finally{flock($handle,LOCK_UN);fclose($handle);}
    if($blocked){header('Retry-After: '.max(1,$row['until']-$now));bois_security_reject(429,'För många anrop. Försök igen senare.');}
}

function bois_security_gate(array $config,string $action,array $routes): void
{
    $method=(string)($_SERVER['REQUEST_METHOD']??'GET');
    if(!isset($routes[$action])) bois_security_reject(404,'Okänd endpoint.');
    $allowed=$routes[$action];
    if($method!=='OPTIONS'&&!in_array($method,$allowed,true)){
        header('Allow: '.implode(', ',$allowed).', OPTIONS');
        bois_security_reject(405,'Metoden är inte tillåten.');
    }
    $webhook=$action==='payment_webhook';
    $origin=$_SERVER['HTTP_ORIGIN']??'';
    if(!$webhook){
        if(!is_string($origin)||($origin!==''&&!in_array($origin,$config['allowed_origins']??[],true))||
           ($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site') bois_security_reject(403,'Otillåtet ursprung.');
        if($origin!==''){header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');}
    }
    if($method==='OPTIONS'){
        header('Access-Control-Allow-Methods: '.implode(', ',$allowed));
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Bois-Admin-Token');
        http_response_code(204);exit;
    }
    if(!$webhook){
        $admin=str_starts_with($action,'admin_');
        $sensitive=$admin||in_array($action,['order','checkout_status'],true)||$method!=='GET';
        if($sensitive) bois_security_rate($config,$admin?'admin':$action,$admin?120:($action==='sales_event'?300:60));
        if($admin&&$action!=='admin_login') bois_security_admin($config);
        if($action==='admin_login'){
            require_once __DIR__.'/p14_admin.php';
            if(!bois_p14_personal($config)||((($_SERVER['HTTPS']??'')!=='on')&&($config['mode']??'')!=='test')) bois_security_reject(401,'Personlig inloggning är inte tillgänglig.');
            if(!is_string($origin)||!in_array($origin,$config['allowed_origins']??[],true))bois_security_reject(403,'Otillåtet ursprung.');
        }
    }
    if(in_array($method,['POST','PATCH'],true)){
        $type=strtolower(trim(explode(';',(string)($_SERVER['CONTENT_TYPE']??''))[0]));
        if($type!=='application/json') bois_security_reject(415,'JSON krävs.');
        if((int)($_SERVER['CONTENT_LENGTH']??0)>($webhook?1048576:65536)) bois_security_reject(413,'Underlaget är för stort.');
    }
}

function bois_security_body(int $limit=65536): string
{
    $handle=fopen('php://input','rb');
    if($handle===false) throw new RuntimeException('Request body unavailable.');
    try{$raw=stream_get_contents($handle,$limit+1);}finally{fclose($handle);}
    if(!is_string($raw)) throw new RuntimeException('Request body unavailable.');
    if(strlen($raw)>$limit) bois_security_reject(413,'Underlaget är för stort.');
    return $raw;
}

if(PHP_SAPI!=='cli'){
    bois_security_headers();
    // Internal includes and maintenance scripts cannot be requested directly,
    // even on a PHP server which does not honor Apache .htaccess.
    if(!in_array(basename((string)($_SERVER['SCRIPT_FILENAME']??'')),['api.php','commerce-api.php','stripe-webhook.php','bois-stripe-sandbox-webhook.php'],true)){
        bois_security_reject(403,'Ej behörig.');
    }
}
