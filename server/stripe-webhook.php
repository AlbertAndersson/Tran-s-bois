<?php
declare(strict_types=1);

require_once __DIR__ . '/p6_payment.php';

function bois_stripe_webhook_respond(array $data,int $status=200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    exit;
}

try{
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){
        header('Allow: POST');
        bois_stripe_webhook_respond(['ok'=>false,'error'=>'method_not_allowed'],405);
    }

    $config=bois_p3_load_config();
    if(bois_p6_provider($config)!=='stripe' || bois_p8_stripe_mode($config)!=='test'){
        throw new DomainException('Stripe sandbox webhook is disabled.');
    }

    $raw=file_get_contents('php://input');
    if(!is_string($raw) || $raw==='') throw new InvalidArgumentException('Webhook body missing.');
    if(strlen($raw)>1048576) throw new InvalidArgumentException('Webhook body too large.');

    $signature=(string)($_SERVER['HTTP_STRIPE_SIGNATURE']??'');
    if($signature==='') throw new DomainException('Stripe-Signature missing.');

    $pdo=bois_p3_pdo($config);
    bois_p5_apply_schema($pdo);
    bois_p4_apply_schema($pdo);
    bois_p6_apply_schema($pdo);
    bois_p8_apply_schema($pdo);

    $translated=bois_p8_stripe_translate_webhook($pdo,$config,$raw,$signature);
    if(($translated['ignored']??false)===true){
        bois_stripe_webhook_respond([
            'ok'=>true,
            'ignored'=>true,
            'event_id'=>$translated['stripe_event_id']??null,
            'event_type'=>$translated['stripe_event_type']??null,
        ]);
    }

    $result=bois_p6_process_verified_event($pdo,$config,(array)$translated['event'],$raw);
    bois_stripe_webhook_respond([
        'ok'=>true,
        'processed'=>true,
        'duplicate'=>$result['duplicate']??false,
        'event_id'=>$result['event_id']??null,
    ]);
}catch(DomainException|InvalidArgumentException|JsonException $e){
    bois_stripe_webhook_respond(['ok'=>false,'error'=>'invalid_webhook'],400);
}catch(Throwable $e){
    error_log('BoIS Stripe webhook error: '.$e->getMessage());
    bois_stripe_webhook_respond(['ok'=>false,'error'=>'server_error'],500);
}
