<?php
declare(strict_types=1);
require __DIR__ . '/p3_db.php';

function commerce_respond(array $data, int $status=200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    exit;
}

function commerce_body(): array
{
    $raw=file_get_contents('php://input');
    if(!is_string($raw)||$raw==='') return [];
    $data=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    if(!is_array($data)) throw new InvalidArgumentException('Ogiltigt JSON-underlag.');
    return $data;
}

function commerce_auth_header(): ?string
{
    $header=$_SERVER['HTTP_AUTHORIZATION'] ?? null;
    if(!is_string($header)&&function_exists('getallheaders')){
        $all=getallheaders();
        $header=$all['Authorization']??$all['authorization']??null;
    }
    return is_string($header)?$header:null;
}

function commerce_require_admin(array $config): void
{
    $header=commerce_auth_header();
    $prefix='Bearer ';
    if(!is_string($header)||!str_starts_with($header,$prefix)) throw new DomainException('Ej behörig.');
    $token=substr($header,strlen($prefix));
    if($token===''||!hash_equals((string)$config['admin_token'],$token)) throw new DomainException('Ej behörig.');
}

function commerce_check_origin(array $config): void
{
    $origin=$_SERVER['HTTP_ORIGIN'] ?? '';
    if(!is_string($origin)||$origin==='') return;
    if(!in_array($origin,$config['allowed_origins'],true)) throw new DomainException('Otillåtet ursprung.');
    header('Access-Control-Allow-Origin: '.$origin);
    header('Vary: Origin');
}

try {
    $config=bois_p3_load_config();
    $pdo=bois_p3_pdo($config);
    commerce_check_origin($config);

    if(($_SERVER['REQUEST_METHOD']??'')==='OPTIONS'){
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        http_response_code(204);
        exit;
    }

    $action=(string)($_GET['action']??'health');
    $method=(string)($_SERVER['REQUEST_METHOD']??'GET');

    if($action==='health'&&$method==='GET'){
        commerce_respond([
            'ok'=>true,
            'service'=>'tranas-bois-commerce-api',
            'phase'=>'P3',
            'mode'=>$config['mode'],
            'storage_driver'=>'mysql',
            'payment_enabled'=>false,
            'db'=>$pdo->query("SELECT DATABASE()")->fetchColumn() ? 'ok' : 'unknown',
        ]);
    }

    if($action==='catalog'&&$method==='GET'){
        commerce_respond(['ok'=>true,'products'=>bois_p3_catalog($pdo,false)]);
    }

    if($action==='orders'&&$method==='POST'){
        $order=bois_p3_create_order($pdo,commerce_body());
        commerce_respond(['ok'=>true,'order'=>$order],201);
    }

    if($action==='order'&&$method==='GET'){
        $id=bois_p3_clean_string($_GET['id']??'',40);
        $token=bois_p3_clean_string($_GET['token']??'',80);
        commerce_respond(['ok'=>true,'order'=>bois_p3_public_order($pdo,$id,$token)]);
    }

    if($action==='admin_orders'&&$method==='GET'){
        commerce_require_admin($config);
        commerce_respond(['ok'=>true,'orders'=>bois_p3_admin_orders($pdo)]);
    }

    if($action==='admin_catalog'&&$method==='GET'){
        commerce_require_admin($config);
        commerce_respond(['ok'=>true,'products'=>bois_p3_catalog($pdo,true),'stats'=>bois_p3_stats($pdo)]);
    }

    commerce_respond(['error'=>'Okänd endpoint.'],404);

} catch (DomainException $e) {
    commerce_respond(['error'=>$e->getMessage()],401);
} catch (InvalidArgumentException $e) {
    commerce_respond(['error'=>$e->getMessage()],422);
} catch (JsonException) {
    commerce_respond(['error'=>'Ogiltigt JSON-underlag.'],400);
} catch (Throwable $e) {
    error_log('bois-commerce-api: '.$e->getMessage());
    commerce_respond(['error'=>'Ett internt fel uppstod.'],500);
}
