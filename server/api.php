<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

function respond(array $data, int $status=200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    exit;
}
function body_json(): array
{
    $raw=file_get_contents('php://input');
    if(!is_string($raw)||$raw==='') return [];
    $data=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    if(!is_array($data)) throw new InvalidArgumentException('Ogiltigt JSON-underlag.');
    return $data;
}
function auth_header(): ?string
{
    $h=$_SERVER['HTTP_AUTHORIZATION'] ?? null;
    if(!is_string($h)&&function_exists('getallheaders')){ $all=getallheaders(); $h=$all['Authorization']??$all['authorization']??null; }
    return is_string($h)?$h:null;
}
function check_origin(array $config): void
{
    $origin=$_SERVER['HTTP_ORIGIN']??'';
    if(!is_string($origin)||$origin==='') return;
    if(!in_array($origin,$config['allowed_origins'],true)) throw new DomainException('Otillåtet ursprung.');
    header('Access-Control-Allow-Origin: '.$origin); header('Vary: Origin');
}

try {
    $config=bois_load_config(); bois_prepare_storage($config); check_origin($config);
    if(($_SERVER['REQUEST_METHOD']??'')==='OPTIONS'){
        header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        http_response_code(204); exit;
    }
    $action=(string)($_GET['action']??'health'); $method=(string)($_SERVER['REQUEST_METHOD']??'GET');

    if($action==='health'&&$method==='GET'){
        respond(['ok'=>true,'service'=>'tranas-bois-order-api','schema_version'=>BOIS_SCHEMA_VERSION,'mode'=>$config['mode'],'storage_writable'=>is_writable($config['data_dir'])]);
    }
    if($action==='orders'&&$method==='POST'){
        $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown'); bois_rate_limit($config,'create:'.$ip);
        $order=bois_create_order($config,body_json(),['source'=>'web']);
        respond(['ok'=>true,'order_id'=>$order['order_id'],'public_token'=>$order['public_token'],'status'=>$order['status'],'total_ore'=>$order['total_ore']],201);
    }
    if($action==='order'&&$method==='GET'){
        $id=(string)($_GET['id']??''); $token=(string)($_GET['token']??''); $order=bois_get_order($config,$id);
        if($token===''||!hash_equals((string)$order['public_token'],$token)) throw new DomainException('Ej behörig.');
        respond(['ok'=>true,'order'=>bois_public_order($order)]);
    }
    if($action==='admin_orders'&&$method==='GET'){
        bois_require_admin($config,auth_header());
        $filters=['team'=>bois_clean_string($_GET['team']??'',40),'status'=>bois_clean_string($_GET['status']??'',40)];
        respond(['ok'=>true,'orders'=>bois_list_orders($config,$filters)]);
    }
    if($action==='admin_order'&&$method==='PATCH'){
        bois_require_admin($config,auth_header()); $id=(string)($_GET['id']??''); $data=body_json();
        $order=bois_update_status($config,$id,bois_clean_string($data['status']??'',40));
        respond(['ok'=>true,'order'=>$order]);
    }
    if($action==='admin_export'&&$method==='GET'){
        bois_require_admin($config,auth_header()); $orders=bois_list_orders($config);
        http_response_code(200); header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="tranas-bois-bestallningar.csv"'); header('Cache-Control: no-store');
        $out=fopen('php://output','wb'); fwrite($out,"\xEF\xBB\xBF"); fputcsv($out,['Order','Datum','Lag','Spelare','Tröja','Byxa','Nummer','Namntryck','Nummertryck','Förälder','E-post','Telefon','Summa SEK','Status'],';');
        foreach($orders as $o){ fputcsv($out,[$o['order_id'],$o['created_at'],$o['team'],$o['player_name'],$o['shirt_size'],$o['shorts_size'],$o['number'],$o['name_print']?'Ja':'Nej',$o['number_print']?'Ja':'Nej',$o['parent_name'],$o['email'],$o['phone'],number_format(((int)$o['total_ore'])/100,2,',',''),$o['status']],';'); }
        fclose($out); exit;
    }
    respond(['error'=>'Okänd endpoint.'],404);
} catch (DomainException $e) { respond(['error'=>$e->getMessage()],401); }
catch (OutOfBoundsException $e) { respond(['error'=>$e->getMessage()],404); }
catch (InvalidArgumentException $e) { respond(['error'=>$e->getMessage()],422); }
catch (JsonException) { respond(['error'=>'Ogiltigt JSON-underlag.'],400); }
catch (Throwable $e) { error_log('bois-api: '.$e->getMessage()); respond(['error'=>'Ett internt fel uppstod.'],500); }
