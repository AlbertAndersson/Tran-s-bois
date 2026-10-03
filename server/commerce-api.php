<?php
declare(strict_types=1);

require __DIR__ . '/p3_db.php';
require __DIR__ . '/p5_batch.php';
require __DIR__ . '/p4_membership.php';
require __DIR__ . '/p6_payment.php';
require __DIR__ . '/p7_assortment.php';
require __DIR__ . '/p9_sales.php';
require __DIR__ . '/consent.php';
require_once __DIR__ . '/production.php';
require_once __DIR__ . '/p14_admin.php';

function commerce_respond(array $data, int $status=200): never
{
    bois_p14_finish($status);
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
    $raw=bois_security_body();
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
    if(bois_p14_personal($config)){
        if(!isset($GLOBALS['bois_p14_actor']))throw new DomainException('Ej behörig.');return;
    }
    $separate=$_SERVER['HTTP_X_BOIS_ADMIN_TOKEN']??null;
    if(is_string($separate)&&$separate!==''){
        if(!hash_equals((string)$config['admin_token'],$separate)) throw new DomainException('Ej behörig.');
        return;
    }
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

function commerce_output_batch_csv(PDO $pdo, string $batchId): never
{
    $csv = bois_p5_batch_csv($pdo, $batchId);
    http_response_code(200);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tranas-bois-' . strtolower($batchId) . '.csv"');
    header('Cache-Control: no-store');
    bois_p14_finish(200);
    echo $csv;
    exit;
}

try {
    header('X-Request-ID: '.bois_p15_id());
    $action=is_string($_GET['action']??null)?$_GET['action']:'health';
    if($action==='liveness'){
        bois_security_headers();bois_p15_begin([],$action);
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')commerce_respond(['ok'=>false],405);
        commerce_respond(['ok'=>true]);
    }
    $config=bois_p3_load_config();
    bois_p15_begin($config,$action);
    if($action==='readiness'){
        bois_security_headers();bois_security_gate($config,$action,['readiness'=>['GET']]);
        bois_security_rate($config,'readiness',30);
        $ready=bois_p15_readiness($config)['ready'];
        commerce_respond(['ready'=>$ready],$ready?200:503);
    }
    bois_production_http_gate($config,$action);
    $method=(string)($_SERVER['REQUEST_METHOD']??'GET');
    bois_security_gate($config,$action,[
        'consent'=>['GET','POST'],
        'health'=>['GET'],
        'sales_event'=>['POST'],
        'sales_recommendations'=>['POST'],
        'catalog'=>['GET'],
        'checkout'=>['POST'],
        'checkout_status'=>['GET'],
        'mock_payment_event'=>['POST'],
        'payment_webhook'=>['POST'],
        'orders'=>['POST'],
        'order'=>['GET'],
        'admin_orders'=>['GET'],
        'admin_login'=>['POST'],
        'admin_session'=>['GET'],
        'admin_logout'=>['POST'],
        'admin_revoke'=>['POST'],
        'admin_catalog'=>['GET'],
        'admin_p7'=>['GET'],
        'admin_p4'=>['GET'],
        'admin_verify_existing_member'=>['POST'],
        'admin_benefit_status'=>['POST'],
        'admin_nordic_export'=>['GET'],
        'admin_sales'=>['GET'],
        'admin_p8_readiness'=>['GET'],
        'admin_stripe_refund'=>['POST'],
        'admin_payments'=>['GET'],
        'admin_retry_payment_outbox'=>['POST'],
        'admin_run_payment_outbox'=>['POST'],
        'admin_batches'=>['GET'],
        'admin_simulate_paid'=>['POST'],
        'admin_batch_now'=>['POST'],
        'admin_batch_csv'=>['GET'],
        'admin_retry_outbox'=>['POST'],
        'admin_run_worker'=>['POST'],
    ]);
    if(in_array($action,['admin_login','admin_session','admin_logout','admin_revoke'],true)){
        if(!bois_p14_personal($config))throw new DomainException('Personlig inloggning är inte tillgänglig.');
        if($action==='admin_login'){
            $login=bois_p14_login($config,commerce_body());bois_p14_cookie($login['token']);unset($login['token']);
            commerce_respond(['ok'=>true,'session'=>$login]);
        }
        $actor=$GLOBALS['bois_p14_actor'];
        if($action==='admin_logout'){bois_p14_logout($config,(string)($_COOKIE['__Host-BoISAdmin']??''));bois_p14_cookie('');commerce_respond(['ok'=>true]);}
        if($action==='admin_revoke'){
            $input=commerce_body();bois_p14_revoke($config,(string)($input['username']??''));commerce_respond(['ok'=>true]);
        }
        commerce_respond(['ok'=>true,'session'=>array_intersect_key($actor,array_flip(['id','role','csrf','permissions']))]);
    }
    $pdo=bois_p3_pdo($config);
    if(($config['mode']??'')!=='production'){
        bois_p5_apply_schema($pdo);
        bois_p4_apply_schema($pdo);
        bois_p6_apply_schema($pdo);
        bois_p7_apply_schema($pdo);
        bois_p8_apply_schema($pdo);
        bois_p9_apply_schema($pdo);
        bois_consent_schema($pdo);
    }
    commerce_check_origin($config);

    if(($_SERVER['REQUEST_METHOD']??'')==='OPTIONS'){
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Bois-Admin-Token');
        http_response_code(204);
        exit;
    }

    $action=(string)($_GET['action']??'health');
    $method=(string)($_SERVER['REQUEST_METHOD']??'GET');

    if($action==='consent'&&$method==='GET'){
        commerce_respond(['ok'=>true,'choice'=>bois_consent_choice($pdo),'policy_version'=>BOIS_CONSENT_POLICY,'validity_days'=>bois_consent_days($config)]);
    }
    if($action==='consent'&&$method==='POST'){
        $input=commerce_body();
        if(!isset($input['statistics'])||!is_bool($input['statistics'])) throw new InvalidArgumentException('Välj statistik ja eller nej.');
        commerce_respond(['ok'=>true,'choice'=>bois_consent_save($pdo,$config,$input['statistics'])]);
    }

    if($action==='health'&&$method==='GET'){
        $waiting=bois_p5_waiting_summary($pdo);
        $readiness=bois_p8_readiness($config);
        $provider=bois_p6_provider($config);
        commerce_respond([
            'ok'=>true,
            'service'=>'tranas-bois-commerce-api',
            'phase'=>'P9',
            'mode'=>$config['mode'],
            'storage_driver'=>'mysql',
            'payment_enabled'=>bois_p6_payment_enabled($config),
            'checkout_enabled'=>$provider==='stripe'?bois_p8_stripe_checkout_allowed($config):bois_p6_payment_enabled($config),
            'payment_provider'=>$provider,
            'payment_mode'=>$provider==='mock'?'testmode':($provider==='stripe'?bois_p8_stripe_mode($config):'disabled'),
            'payment_methods'=>bois_p8_payment_methods($config),
            'stripe_ready_for_test'=>$readiness['ready_for_stripe_test'],
            'production_launch_ready'=>$readiness['ready_for_production_launch'],
            'sales_engine'=>'first_party',
            'sales_tracking_enabled'=>bois_p9_tracking_enabled($config),
            'external_analytics'=>false,
            'mail_transport'=>$config['mail_transport'] ?? 'disabled',
            'batch_threshold_qty'=>$waiting['threshold_qty'],
            'batch_max_wait_hours'=>$waiting['max_wait_hours'],
            'membership_workflow'=>'manual_verify_ready',
            'nordic_workflow'=>'manual_partner_handoff',
            'db'=>$pdo->query("SELECT DATABASE()")->fetchColumn() ? 'ok' : 'unknown',
        ]);
    }

    if($action==='sales_event'&&$method==='POST'){
        if(!bois_p9_tracking_enabled($config)||!bois_consent_statistics_allowed($pdo)){
            commerce_respond(['ok'=>true,'sales'=>['accepted'=>false,'disabled'=>true]],202);
        }
        commerce_respond(['ok'=>true,'sales'=>bois_p9_capture_event($pdo,commerce_body(),$config+['_consent_verified'=>true])],202);
    }

    if($action==='sales_recommendations'&&$method==='POST'){
        commerce_respond(['ok'=>true,'recommendations'=>bois_p9_recommendations($pdo,commerce_body())]);
    }

    if($action==='catalog'&&$method==='GET'){
        commerce_respond(['ok'=>true,'products'=>bois_p3_catalog($pdo,false)]);
    }

    if($action==='checkout'&&$method==='POST'){
        $data=commerce_body();
        $publicId=bois_p3_clean_string($data['public_id']??'',40);
        $token=bois_p3_clean_string($data['public_token']??'',80);
        $paymentMethod=bois_p3_clean_string($data['method']??'',20);
        commerce_respond([
            'ok'=>true,
            'checkout'=>bois_p6_checkout($pdo,$config,$publicId,$token,$paymentMethod)
        ],201);
    }

    if($action==='checkout_status'&&$method==='GET'){
        $session=bois_p3_clean_string($_GET['session']??'',190);
        $token=bois_p3_clean_string($_GET['token']??'',120);
        commerce_respond(['ok'=>true,'checkout'=>bois_p6_session($pdo,$session,$token)]);
    }

    if($action==='mock_payment_event'&&$method==='POST'){
        $data=commerce_body();
        $session=bois_p3_clean_string($data['session_ref']??'',190);
        $token=bois_p3_clean_string($data['session_token']??'',120);
        $outcome=bois_p3_clean_string($data['outcome']??'',30);
        $refundOre=max(0,(int)($data['refund_ore']??0));
        commerce_respond([
            'ok'=>true,
            'payment'=>bois_p6_mock_event($pdo,$config,$session,$token,$outcome,$refundOre)
        ]);
    }

    if($action==='payment_webhook'&&$method==='POST'){
        $raw=bois_security_body(1048576);
        if(!is_string($raw)||$raw==='') throw new InvalidArgumentException('Webhook-underlag saknas.');

        if(bois_p6_provider($config)==='stripe'){
            $stripeSignature=(string)($_SERVER['HTTP_STRIPE_SIGNATURE']??'');
            $translated=bois_p8_stripe_translate_webhook($pdo,$config,$raw,$stripeSignature);
            if(($translated['ignored']??false)===true){
                commerce_respond([
                    'ok'=>true,
                    'ignored'=>true,
                    'stripe_event_id'=>$translated['stripe_event_id']??null,
                    'stripe_event_type'=>$translated['stripe_event_type']??null,
                    'reason'=>$translated['reason']??'ignored',
                ]);
            }
            commerce_respond(
                bois_p6_process_verified_event($pdo,$config,(array)$translated['event'],$raw)
            );
        }

        $timestamp=(string)($_SERVER['HTTP_X_BOIS_PAYMENT_TIMESTAMP']??'');
        $signature=(string)($_SERVER['HTTP_X_BOIS_PAYMENT_SIGNATURE']??'');
        commerce_respond(bois_p6_process_webhook_raw($pdo,$config,$raw,$timestamp,$signature));
    }

    if($action==='orders'&&$method==='POST'){
        $input=commerce_body();
        $order=bois_p3_create_order($pdo,$input);
        bois_p4_register_order($pdo,(string)$order['public_id']);
        if(bois_p9_tracking_enabled($config)&&bois_consent_statistics_allowed($pdo)){
            bois_p9_link_order($pdo,(string)$order['public_id'],$input,$config+['_consent_verified'=>true]);
        }
        commerce_respond(['ok'=>true,'order'=>$order],201);
    }

    if($action==='order'&&$method==='GET'){
        $id=bois_p3_clean_string($_GET['id']??'',40);
        $token=bois_p3_clean_string($_GET['token']??'',80);
        $order=bois_p3_public_order($pdo,$id,$token);
        $order['payment']=bois_p6_public_payment($pdo,$id);
        $order['p4']=bois_p4_public_status_for_order($pdo,$id);
        commerce_respond(['ok'=>true,'order'=>$order]);
    }

    if($action==='admin_orders'&&$method==='GET'){
        commerce_require_admin($config);
        commerce_respond(['ok'=>true,'orders'=>bois_p3_admin_orders($pdo)]);
    }

    if($action==='admin_catalog'&&$method==='GET'){
        commerce_require_admin($config);
        commerce_respond([
            'ok'=>true,
            'products'=>bois_p3_catalog($pdo,true),
            'stats'=>bois_p3_stats($pdo),
            'batch_waiting'=>bois_p5_waiting_summary($pdo),
            'p4_stats'=>bois_p4_stats($pdo),
        ]);
    }

    if($action==='admin_p7'&&$method==='GET'){
        commerce_require_admin($config);
        commerce_respond(['ok'=>true,'assortment'=>bois_p7_admin_assortment($pdo)]);
    }


    if($action==='admin_p4'&&$method==='GET'){
        commerce_require_admin($config);
        commerce_respond([
            'ok'=>true,
            'stats'=>bois_p4_stats($pdo),
            'members'=>bois_p4_admin_members($pdo),
            'entitlements'=>bois_p4_admin_entitlements($pdo),
        ]);
    }

    if($action==='admin_verify_existing_member'&&$method==='POST'){
        commerce_require_admin($config);
        $data=commerce_body();
        $publicId=bois_p3_clean_string($data['public_id']??'',40);
        $memberName=bois_p3_clean_string($data['member_name']??'',160);
        $membershipType=bois_p3_clean_string($data['membership_type']??'adult',40);
        $externalRef=bois_p3_clean_string($data['external_member_ref']??'',120);
        if($memberName==='') throw new InvalidArgumentException('Medlemsnamn krävs.');
        commerce_respond([
            'ok'=>true,
            'result'=>bois_p4_verify_existing_member(
                $pdo,$config,$publicId,$memberName,$membershipType,$externalRef!==''?$externalRef:null
            )
        ]);
    }

    if($action==='admin_benefit_status'&&$method==='POST'){
        commerce_require_admin($config);
        $data=commerce_body();
        $id=(int)($data['entitlement_id']??0);
        $status=bois_p3_clean_string($data['status']??'',50);
        $partnerRef=bois_p3_clean_string($data['partner_ref']??'',160);
        $notes=bois_p3_clean_string($data['notes']??'',500);
        if($id<1||$status==='') throw new InvalidArgumentException('Ogiltig förmånsuppdatering.');
        commerce_respond([
            'ok'=>true,
            'entitlement'=>bois_p4_transition_entitlement(
                $pdo,$id,$status,$partnerRef!==''?$partnerRef:null,$notes!==''?$notes:null
            )
        ]);
    }

    if($action==='admin_nordic_export'&&$method==='GET'){
        commerce_require_admin($config);
        http_response_code(200);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="tranas-bois-nordic-wellness.csv"');
        header('Cache-Control: no-store');
        bois_p14_finish(200);
        echo bois_p4_export_eligible_csv($pdo);
        exit;
    }

    if($action==='admin_sales'&&$method==='GET'){
        commerce_require_admin($config);
        commerce_respond(['ok'=>true,'sales'=>bois_p9_admin_dashboard($pdo)]);
    }

    if($action==='admin_p8_readiness'&&$method==='GET'){
        commerce_require_admin($config);
        commerce_respond(['ok'=>true,'readiness'=>bois_p8_readiness($config)]);
    }

    if($action==='admin_stripe_refund'&&$method==='POST'){
        commerce_require_admin($config);
        $data=commerce_body();
        $publicId=bois_p3_clean_string($data['public_id']??'',40);
        $amountOre=max(0,(int)($data['amount_ore']??0));
        if($publicId==='') throw new InvalidArgumentException('Orderreferens krävs.');
        commerce_respond([
            'ok'=>true,
            'refund'=>bois_p8_stripe_request_refund($pdo,$config,$publicId,$amountOre)
        ]);
    }

    if($action==='admin_payments'&&$method==='GET'){
        commerce_require_admin($config);
        commerce_respond(['ok'=>true]+bois_p6_admin_payments($pdo));
    }

    if($action==='admin_retry_payment_outbox'&&$method==='POST'){
        commerce_require_admin($config);
        $data=commerce_body();
        $id=(int)($data['outbox_id']??0);
        if($id<1) throw new InvalidArgumentException('Ogiltigt payment-outbox-id.');
        commerce_respond(['ok'=>true,'outbox'=>bois_p6_retry_outbox($pdo,$id)]);
    }

    if($action==='admin_run_payment_outbox'&&$method==='POST'){
        commerce_require_admin($config);
        commerce_respond(['ok'=>true,'mail'=>bois_p6_deliver_outbox($pdo,$config)]);
    }

    if($action==='admin_batches'&&$method==='GET'){
        commerce_require_admin($config);
        commerce_respond([
            'ok'=>true,
            'waiting'=>bois_p5_waiting_summary($pdo),
            'batches'=>bois_p5_admin_batches($pdo),
        ]);
    }

    if($action==='admin_simulate_paid'&&$method==='POST'){
        commerce_require_admin($config);
        $data=commerce_body();
        $publicId=bois_p3_clean_string($data['public_id']??'',40);
        $payment=bois_p6_admin_simulate_paid($pdo,$config,$publicId);
        commerce_respond([
            'ok'=>true,
            'result'=>$payment,
            'waiting'=>bois_p5_waiting_summary($pdo),
        ]);
    }

    if($action==='admin_batch_now'&&$method==='POST'){
        commerce_require_admin($config);
        commerce_respond([
            'ok'=>true,
            'batches'=>bois_p5_evaluate_batches($pdo,$config,true),
            'waiting'=>bois_p5_waiting_summary($pdo),
        ]);
    }

    if($action==='admin_batch_csv'&&$method==='GET'){
        commerce_require_admin($config);
        $batchId=bois_p3_clean_string($_GET['id']??'',50);
        commerce_output_batch_csv($pdo,$batchId);
    }

    if($action==='admin_retry_outbox'&&$method==='POST'){
        commerce_require_admin($config);
        $data=commerce_body();
        $outboxId=(int)($data['outbox_id']??0);
        if($outboxId<1) throw new InvalidArgumentException('Ogiltigt outbox-id.');
        commerce_respond(['ok'=>true,'outbox'=>bois_p5_retry_outbox($pdo,$outboxId)]);
    }

    if($action==='admin_run_worker'&&$method==='POST'){
        commerce_require_admin($config);
        $batches=bois_p5_evaluate_batches($pdo,$config,false);
        $mail=bois_p5_deliver_outbox($pdo,$config);
        commerce_respond([
            'ok'=>true,
            'batches'=>$batches,
            'mail'=>$mail,
            'waiting'=>bois_p5_waiting_summary($pdo),
        ]);
    }

    commerce_respond(['error'=>'Okänd endpoint.'],404);

} catch (DomainException $e) {
    commerce_respond(['error'=>$e->getMessage()],401);
} catch (OutOfBoundsException $e) {
    commerce_respond(['error'=>$e->getMessage()],404);
} catch (InvalidArgumentException $e) {
    commerce_respond(['error'=>$e->getMessage()],422);
} catch (JsonException) {
    commerce_respond(['error'=>'Ogiltigt JSON-underlag.'],400);
} catch (Throwable $e) {
    bois_p15_error();
    commerce_respond(['error'=>'Ett internt fel uppstod.'],500);
}
