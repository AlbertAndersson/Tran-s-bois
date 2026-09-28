<?php
declare(strict_types=1);

/**
 * P8 Stripe adapter and production-readiness helpers.
 *
 * This file deliberately has no side effects and never activates Stripe by itself.
 * Network calls only occur when payment_provider=stripe and complete Stripe credentials
 * are supplied in private runtime configuration.
 */

function bois_p8_apply_schema(PDO $pdo): void
{
    $db=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $hasColumn=function(string $table,string $column) use($pdo,$db): bool {
        $stmt=$pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=? AND table_name=? AND column_name=?'
        );
        $stmt->execute([$db,$table,$column]);
        return (int)$stmt->fetchColumn()>0;
    };

    if(!$hasColumn('bois_payments','provider_payment_ref')){
        $pdo->exec("ALTER TABLE bois_payments ADD COLUMN provider_payment_ref VARCHAR(190) NULL AFTER provider_ref");
    }
    if(!$hasColumn('bois_payments','checkout_url')){
        $pdo->exec("ALTER TABLE bois_payments ADD COLUMN checkout_url TEXT NULL AFTER checkout_session_ref");
    }

    $stmt=$pdo->prepare("INSERT IGNORE INTO bois_schema_migrations(version) VALUES (?)");
    $stmt->execute(['20260928_p8_stripe_v1']);
}

function bois_p8_stripe_mode(array $config): string
{
    $mode=strtolower(trim((string)($config['stripe_mode'] ?? 'test')));
    return in_array($mode,['test','live'],true)?$mode:'invalid';
}

function bois_p8_stripe_swish_enabled(array $config): bool
{
    return ($config['stripe_swish_enabled'] ?? false)===true;
}

function bois_p8_payment_methods(array $config): array
{
    $provider=strtolower(trim((string)($config['payment_provider'] ?? 'disabled')));
    if($provider==='mock') return ['card','swish'];
    if($provider!=='stripe') return [];

    $methods=['card'];
    if(bois_p8_stripe_swish_enabled($config)) $methods[]='swish';
    return $methods;
}

function bois_p8_public_base_url(array $config): string
{
    $url=rtrim(trim((string)($config['public_base_url'] ?? '')),'/');
    if($url==='' || !str_starts_with($url,'https://')){
        throw new RuntimeException('P8 public_base_url måste vara en HTTPS-adress.');
    }
    return $url;
}

function bois_p8_stripe_runtime_ready(array $config): bool
{
    if(strtolower(trim((string)($config['payment_provider'] ?? 'disabled')))!=='stripe') return false;
    $mode=bois_p8_stripe_mode($config);
    if(!in_array($mode,['test','live'],true)) return false;

    $secret=(string)($config['stripe_secret_key'] ?? '');
    $webhook=(string)($config['stripe_webhook_secret'] ?? '');
    if($mode==='test' && !str_starts_with($secret,'sk_test_')) return false;
    if($mode==='live' && !str_starts_with($secret,'sk_live_')) return false;
    if(!str_starts_with($webhook,'whsec_') || strlen($webhook)<16) return false;

    try { bois_p8_public_base_url($config); } catch(Throwable) { return false; }
    return true;
}

/**
 * Permission to start a NEW checkout, not permission to settle an existing payment.
 * Keep webhook/refund processing independent so closing the shop does not lose
 * delayed, valid provider events for checkouts already created.
 */
function bois_p8_stripe_checkout_allowed(array $config): bool
{
    if(!bois_p8_stripe_runtime_ready($config)) return false;
    $environment=(string)($config['mode'] ?? '');
    $stripeMode=bois_p8_stripe_mode($config);
    if($environment==='production'){
        return $stripeMode==='live'
            && ($config['production_launch_enabled'] ?? false)===true
            && bois_p8_readiness($config)['ready_for_production_launch']===true;
    }
    return in_array($environment,['staging','test'],true) && $stripeMode==='test';
}

function bois_p8_require_stripe_checkout(array $config): void
{
    if(!bois_p8_stripe_checkout_allowed($config)){
        throw new DomainException('Nya Stripe-betalningar är spärrade i denna miljö.');
    }
}

function bois_p8_stripe_secret_key(array $config): string
{
    if(!bois_p8_stripe_runtime_ready($config)){
        throw new RuntimeException('Stripe-runtime är inte komplett konfigurerad.');
    }
    return (string)$config['stripe_secret_key'];
}

function bois_p8_stripe_webhook_secret(array $config): string
{
    if(!bois_p8_stripe_runtime_ready($config)){
        throw new RuntimeException('Stripe-webhook är inte komplett konfigurerad.');
    }
    return (string)$config['stripe_webhook_secret'];
}

/**
 * @param callable|null $transport Optional test transport:
 *   fn(string $method,string $url,array $params,array $headers): array
 */
function bois_p8_stripe_request(
    array $config,
    string $method,
    string $path,
    array $params=[],
    ?string $idempotencyKey=null,
    ?callable $transport=null
): array {
    // Defence in depth: direct transport use cannot bypass the checkout gate.
    if(strtoupper($method)==='POST' && rtrim($path,'/')==='/v1/checkout/sessions'){
        bois_p8_require_stripe_checkout($config);
    }
    $secret=bois_p8_stripe_secret_key($config);
    $url='https://api.stripe.com'.$path;
    $headers=[
        'Authorization'=>'Bearer '.$secret,
        'Content-Type'=>'application/x-www-form-urlencoded',
        'Accept'=>'application/json',
    ];
    if($idempotencyKey!==null && $idempotencyKey!==''){
        $headers['Idempotency-Key']=$idempotencyKey;
    }

    if($transport!==null){
        $result=$transport(strtoupper($method),$url,$params,$headers);
        if(!is_array($result)) throw new RuntimeException('Stripe testtransport returnerade ogiltigt svar.');
        return $result;
    }

    if(!function_exists('curl_init')) throw new RuntimeException('PHP cURL krävs för Stripe.');
    $curl=curl_init($url);
    if($curl===false) throw new RuntimeException('Kunde inte initiera Stripe-anrop.');

    $flatHeaders=[];
    foreach($headers as $name=>$value) $flatHeaders[]=$name.': '.$value;
    curl_setopt_array($curl,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_CUSTOMREQUEST=>strtoupper($method),
        CURLOPT_HTTPHEADER=>$flatHeaders,
        CURLOPT_CONNECTTIMEOUT=>10,
        CURLOPT_TIMEOUT=>25,
    ]);
    if(strtoupper($method)!=='GET'){
        curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($params,'','&',PHP_QUERY_RFC3986));
    }

    $raw=curl_exec($curl);
    $status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
    $error=curl_error($curl);
    curl_close($curl);

    if(!is_string($raw) || $raw===''){
        throw new RuntimeException('Stripe svarade inte'.($error!==''?': '.$error:'.'));
    }

    $decoded=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    if(!is_array($decoded)) throw new RuntimeException('Stripe returnerade ogiltigt JSON.');
    if($status<200 || $status>=300){
        $message=(string)($decoded['error']['message'] ?? 'Stripe-anrop misslyckades.');
        throw new RuntimeException(substr($message,0,500));
    }
    return $decoded;
}

function bois_p8_stripe_checkout(
    PDO $pdo,
    array $config,
    string $publicId,
    string $publicToken,
    string $method,
    ?callable $transport=null
): array {
    // Check before reading/updating an order or contacting the provider.
    bois_p8_require_stripe_checkout($config);

    $method=strtolower(trim($method));
    if(!in_array($method,bois_p8_payment_methods($config),true)){
        throw new InvalidArgumentException('Vald betalmetod är inte aktiverad.');
    }

    $read=$pdo->prepare(
        "SELECT o.id order_id,o.public_id,o.public_token,o.status order_status,o.payment_status,
                o.total_ore,o.currency,p.id payment_id,p.status payment_status_row,c.email customer_email
         FROM bois_orders o
         JOIN bois_payments p ON p.order_id=o.id
         JOIN bois_customers c ON c.id=o.customer_id
         WHERE o.public_id=? LIMIT 1"
    );
    $read->execute([$publicId]);
    $row=$read->fetch();
    if(!$row || $publicToken==='' || !hash_equals((string)$row['public_token'],$publicToken)){
        throw new DomainException('Ej behörig.');
    }
    if(in_array((string)$row['payment_status_row'],['PAID','PARTIALLY_REFUNDED','REFUNDED','REFUND_PENDING'],true)){
        throw new InvalidArgumentException('Ordern är redan betald eller återbetalas.');
    }

    $currency=strtolower((string)$row['currency']);
    $requestRef='BOIS-STRIPE-'.strtoupper(substr(bin2hex(random_bytes(12)),0,20));
    $base=bois_p8_public_base_url($config);
    $returnQuery='id='.rawurlencode((string)$row['public_id']).'&token='.rawurlencode((string)$row['public_token']);

    $params=[
        'mode'=>'payment',
        'success_url'=>$base.'/order.html?'.$returnQuery.'&checkout=success',
        'cancel_url'=>$base.'/payment.html?'.$returnQuery.'&checkout=cancelled',
        'client_reference_id'=>(string)$row['public_id'],
        'customer_email'=>(string)$row['customer_email'],
        'locale'=>'sv',
        'payment_method_types'=>[$method],
        'line_items'=>[[
            'price_data'=>[
                'currency'=>$currency,
                'unit_amount'=>(int)$row['total_ore'],
                'product_data'=>['name'=>'Tranås BoIS – order '.(string)$row['public_id']],
            ],
            'quantity'=>1,
        ]],
        'metadata'=>[
            'bois_order_public_id'=>(string)$row['public_id'],
            'bois_request_ref'=>$requestRef,
        ],
        'payment_intent_data'=>[
            'metadata'=>[
                'bois_order_public_id'=>(string)$row['public_id'],
                'bois_request_ref'=>$requestRef,
            ],
        ],
    ];

    $session=bois_p8_stripe_request(
        $config,'POST','/v1/checkout/sessions',$params,
        strtolower($requestRef),
        $transport
    );

    $sessionId=(string)($session['id'] ?? '');
    $checkoutUrl=(string)($session['url'] ?? '');
    $livemode=($session['livemode'] ?? null);
    if(!preg_match('/^cs_(test_|live_)?[A-Za-z0-9_]+$/',$sessionId)){
        throw new RuntimeException('Stripe Checkout Session saknar giltigt id.');
    }
    if(!str_starts_with($checkoutUrl,'https://checkout.stripe.com/')){
        throw new RuntimeException('Stripe Checkout saknar giltig redirect-URL.');
    }
    if(bois_p8_stripe_mode($config)==='test' && $livemode===true){
        throw new RuntimeException('Stripe returnerade live-session i testläge.');
    }
    if(bois_p8_stripe_mode($config)==='live' && $livemode!==true){
        throw new RuntimeException('Stripe returnerade inte en live-session.');
    }

    $sessionToken=bin2hex(random_bytes(24));
    $tokenHash=hash('sha256',$sessionToken);
    $paymentIntent=is_string($session['payment_intent'] ?? null)?(string)$session['payment_intent']:null;

    $pdo->beginTransaction();
    try{
        $lock=$pdo->prepare(
            "SELECT o.id order_id,o.total_ore,o.currency,p.id payment_id,p.status payment_status_row
             FROM bois_orders o JOIN bois_payments p ON p.order_id=o.id
             WHERE o.public_id=? FOR UPDATE"
        );
        $lock->execute([$publicId]);
        $fresh=$lock->fetch();
        if(!$fresh ||
           (int)$fresh['order_id']!==(int)$row['order_id'] ||
           (int)$fresh['total_ore']!==(int)$row['total_ore'] ||
           (string)$fresh['currency']!==(string)$row['currency'] ||
           in_array((string)$fresh['payment_status_row'],['PAID','PARTIALLY_REFUNDED','REFUNDED','REFUND_PENDING'],true)){
            throw new RuntimeException('Ordern ändrades under Stripe Checkout-skapandet.');
        }

        $pdo->prepare(
            "UPDATE bois_payments
             SET provider='stripe',provider_ref=?,provider_payment_ref=?,checkout_session_ref=?,checkout_url=?,
                 checkout_token_hash=?,method=?,status='PENDING',paid_ore=0,refunded_ore=0,
                 effects_status='NOT_APPLICABLE',last_event_id=NULL,paid_at=NULL,failed_at=NULL,
                 cancelled_at=NULL,refunded_at=NULL
             WHERE id=?"
        )->execute([
            $sessionId,$paymentIntent,$sessionId,$checkoutUrl,$tokenHash,$method,(int)$fresh['payment_id']
        ]);
        $pdo->prepare(
            "UPDATE bois_orders SET status='PENDING_PAYMENT',payment_status='PENDING' WHERE id=?"
        )->execute([(int)$fresh['order_id']]);
        $pdo->prepare(
            "INSERT INTO bois_events(order_id,event_type,payload_json)
             VALUES(?,'PAYMENT_CHECKOUT_CREATED',?)"
        )->execute([
            (int)$fresh['order_id'],
            json_encode([
                'provider'=>'stripe',
                'provider_ref'=>$sessionId,
                'checkout_session_ref'=>$sessionId,
                'method'=>$method,
                'stripe_mode'=>bois_p8_stripe_mode($config),
                'request_ref'=>$requestRef,
            ],JSON_THROW_ON_ERROR)
        ]);
        $pdo->commit();
    }catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    return [
        'session_ref'=>$sessionId,
        'session_token'=>$sessionToken,
        'provider'=>'stripe',
        'provider_ref'=>$sessionId,
        'method'=>$method,
        'status'=>'PENDING',
        'amount_ore'=>(int)$row['total_ore'],
        'currency'=>(string)$row['currency'],
        'public_id'=>(string)$row['public_id'],
        'redirect_url'=>$checkoutUrl,
        'provider_mode'=>bois_p8_stripe_mode($config),
    ];
}

function bois_p8_stripe_verify_signature(
    array $config,
    string $raw,
    string $signatureHeader,
    int $tolerance=300
): void {
    $timestamp=null;
    $signatures=[];
    foreach(explode(',',$signatureHeader) as $part){
        [$key,$value]=array_pad(explode('=',trim($part),2),2,'');
        if($key==='t' && ctype_digit($value)) $timestamp=(int)$value;
        if($key==='v1' && preg_match('/^[a-f0-9]{64}$/i',$value)) $signatures[]=strtolower($value);
    }
    if($timestamp===null || !$signatures) throw new DomainException('Ogiltig Stripe-Signature.');
    if(abs(time()-$timestamp)>$tolerance) throw new DomainException('Stripe-webhook ligger utanför tillåtet tidsintervall.');

    $expected=hash_hmac('sha256',$timestamp.'.'.$raw,bois_p8_stripe_webhook_secret($config));
    foreach($signatures as $candidate){
        if(hash_equals($expected,$candidate)) return;
    }
    throw new DomainException('Ogiltig Stripe-webhooksignatur.');
}

function bois_p8_stripe_signature_for_test(array $config,string $raw,int $timestamp): string
{
    return 't='.$timestamp.',v1='.hash_hmac(
        'sha256',$timestamp.'.'.$raw,bois_p8_stripe_webhook_secret($config)
    );
}

function bois_p8_stripe_payment_context_by_intent(PDO $pdo,string $paymentIntent): array
{
    $stmt=$pdo->prepare(
        "SELECT p.id,p.order_id,p.provider_ref,p.checkout_session_ref,p.provider_payment_ref,
                p.status,p.paid_ore,p.refunded_ore,o.public_id,o.currency,o.total_ore
         FROM bois_payments p JOIN bois_orders o ON o.id=p.order_id
         WHERE p.provider='stripe' AND p.provider_payment_ref=? LIMIT 1"
    );
    $stmt->execute([$paymentIntent]);
    $row=$stmt->fetch();
    if(!$row) throw new OutOfBoundsException('Stripe PaymentIntent är inte kopplad till någon BoIS-betalning.');
    return $row;
}

function bois_p8_stripe_translate_webhook(
    PDO $pdo,
    array $config,
    string $raw,
    string $signatureHeader
): array {
    bois_p8_stripe_verify_signature($config,$raw,$signatureHeader);
    $stripe=json_decode($raw,true,128,JSON_THROW_ON_ERROR);
    if(!is_array($stripe) || !is_string($stripe['id'] ?? null) || !is_string($stripe['type'] ?? null)){
        throw new InvalidArgumentException('Ogiltigt Stripe-event.');
    }
    $object=$stripe['data']['object'] ?? null;
    if(!is_array($object)) throw new InvalidArgumentException('Stripe-event saknar data.object.');

    $eventId=(string)$stripe['id'];
    $type=(string)$stripe['type'];

    if(in_array($type,['checkout.session.completed','checkout.session.async_payment_succeeded','checkout.session.async_payment_failed','checkout.session.expired'],true)){
        $sessionId=(string)($object['id'] ?? '');
        $publicId=(string)($object['metadata']['bois_order_public_id'] ?? $object['client_reference_id'] ?? '');
        $currency=strtoupper((string)($object['currency'] ?? ''));
        if($sessionId==='' || $publicId==='' || $currency===''){
            throw new InvalidArgumentException('Stripe Checkout-event saknar BoIS-koppling.');
        }

        $paymentIntent=is_string($object['payment_intent'] ?? null)?(string)$object['payment_intent']:'';
        if($paymentIntent!==''){
            $pdo->prepare(
                "UPDATE bois_payments SET provider_payment_ref=?
                 WHERE provider='stripe' AND provider_ref=? AND checkout_session_ref=?"
            )->execute([$paymentIntent,$sessionId,$sessionId]);
        }

        if($type==='checkout.session.completed' && (string)($object['payment_status'] ?? '')!=='paid'){
            return ['ignored'=>true,'stripe_event_id'=>$eventId,'stripe_event_type'=>$type,'reason'=>'checkout_not_paid'];
        }

        $normalizedType=match($type){
            'checkout.session.completed','checkout.session.async_payment_succeeded'=>'payment.succeeded',
            'checkout.session.async_payment_failed'=>'payment.failed',
            'checkout.session.expired'=>'payment.cancelled',
        };
        $event=[
            'event_id'=>$eventId,
            'type'=>$normalizedType,
            'provider'=>'stripe',
            'provider_ref'=>$sessionId,
            'session_ref'=>$sessionId,
            'order_public_id'=>$publicId,
            'currency'=>$currency,
        ];
        if($normalizedType==='payment.succeeded'){
            $event['amount_ore']=(int)($object['amount_total'] ?? 0);
        }
        return ['ignored'=>false,'event'=>$event,'stripe_event_type'=>$type];
    }

    if($type==='refund.updated'){
        $paymentIntent=(string)($object['payment_intent'] ?? '');
        $status=(string)($object['status'] ?? '');
        if($paymentIntent==='') throw new InvalidArgumentException('Stripe refund saknar PaymentIntent.');
        if(!in_array($status,['pending','succeeded'],true)){
            return ['ignored'=>true,'stripe_event_id'=>$eventId,'stripe_event_type'=>$type,'reason'=>'refund_'.$status];
        }
        $payment=bois_p8_stripe_payment_context_by_intent($pdo,$paymentIntent);
        return [
            'ignored'=>false,
            'stripe_event_type'=>$type,
            'event'=>[
                'event_id'=>$eventId,
                'type'=>$status==='succeeded'?'payment.refunded':'payment.refund_pending',
                'provider'=>'stripe',
                'provider_ref'=>(string)$payment['provider_ref'],
                'session_ref'=>(string)$payment['checkout_session_ref'],
                'order_public_id'=>(string)$payment['public_id'],
                'currency'=>(string)$payment['currency'],
                'refund_ore'=>(int)($object['amount'] ?? 0),
            ]
        ];
    }

    return ['ignored'=>true,'stripe_event_id'=>$eventId,'stripe_event_type'=>$type,'reason'=>'event_not_used'];
}

function bois_p8_stripe_request_refund(
    PDO $pdo,
    array $config,
    string $publicId,
    int $amountOre=0,
    ?callable $transport=null
): array {
    if(!bois_p8_stripe_runtime_ready($config)) throw new DomainException('Stripe är inte aktiverat.');

    $stmt=$pdo->prepare(
        "SELECT p.*,o.public_id,o.currency,o.total_ore
         FROM bois_payments p JOIN bois_orders o ON o.id=p.order_id
         WHERE o.public_id=? AND p.provider='stripe' LIMIT 1"
    );
    $stmt->execute([$publicId]);
    $payment=$stmt->fetch();
    if(!$payment) throw new OutOfBoundsException('Stripe-betalningen finns inte.');
    if(!in_array((string)$payment['status'],['PAID','PARTIALLY_REFUNDED'],true)){
        throw new InvalidArgumentException('Betalningen kan inte återbetalas från aktuell status.');
    }
    $paymentIntent=(string)($payment['provider_payment_ref'] ?? '');
    if(!preg_match('/^pi_[A-Za-z0-9_]+$/',$paymentIntent)){
        throw new RuntimeException('Stripe PaymentIntent saknas.');
    }

    $remaining=(int)$payment['paid_ore']-(int)$payment['refunded_ore'];
    $amount=$amountOre>0?$amountOre:$remaining;
    if($amount<1 || $amount>$remaining) throw new InvalidArgumentException('Ogiltigt återbetalningsbelopp.');

    $idempotency='bois-refund-'.(int)$payment['id'].'-'.(int)$payment['refunded_ore'].'-'.$amount;
    $refund=bois_p8_stripe_request($config,'POST','/v1/refunds',[
        'payment_intent'=>$paymentIntent,
        'amount'=>$amount,
        'metadata'=>['bois_order_public_id'=>$publicId],
    ],$idempotency,$transport);

    $refundId=(string)($refund['id'] ?? '');
    if(!preg_match('/^re_[A-Za-z0-9_]+$/',$refundId)) throw new RuntimeException('Stripe refund saknar giltigt id.');

    // Enter manual review immediately. Final financial state is driven by signed Stripe webhook.
    $pending=[
        'event_id'=>'refund-request-'.$refundId,
        'type'=>'payment.refund_pending',
        'provider'=>'stripe',
        'provider_ref'=>(string)$payment['provider_ref'],
        'session_ref'=>(string)$payment['checkout_session_ref'],
        'order_public_id'=>(string)$payment['public_id'],
        'currency'=>(string)$payment['currency'],
    ];
    $pendingRaw=json_encode($pending,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $transition=bois_p6_process_verified_event($pdo,$config,$pending,$pendingRaw);

    return [
        'refund_id'=>$refundId,
        'provider_status'=>(string)($refund['status'] ?? 'unknown'),
        'requested_ore'=>$amount,
        'payment_status'=>$transition['result']['status'] ?? 'REFUND_PENDING',
        'manual_fulfillment_review'=>true,
    ];
}

function bois_p8_readiness(array $config): array
{
    $mode=bois_p8_stripe_mode($config);
    $secret=(string)($config['stripe_secret_key'] ?? '');
    $webhook=(string)($config['stripe_webhook_secret'] ?? '');
    $base=(string)($config['public_base_url'] ?? '');
    $seller=(string)($config['seller_legal_name'] ?? '');
    $org=(string)($config['seller_org_number'] ?? '');
    $support=(string)($config['support_email'] ?? '');
    $terms=(string)($config['terms_url'] ?? '');
    $privacy=(string)($config['privacy_url'] ?? '');

    $checks=[
        'stripe_provider_selected'=>strtolower((string)($config['payment_provider'] ?? ''))==='stripe',
        'stripe_mode_valid'=>in_array($mode,['test','live'],true),
        'stripe_secret_configured'=>$mode==='live'?str_starts_with($secret,'sk_live_'):str_starts_with($secret,'sk_test_'),
        'stripe_webhook_configured'=>str_starts_with($webhook,'whsec_'),
        'https_public_base_url'=>str_starts_with($base,'https://'),
        'seller_legal_name'=>$seller!=='',
        'seller_org_number'=>$org!=='',
        'support_email'=>filter_var($support,FILTER_VALIDATE_EMAIL)!==false,
        'terms_url'=>str_starts_with($terms,'https://'),
        'privacy_url'=>str_starts_with($privacy,'https://'),
        'merchant_verified'=>($config['merchant_verified'] ?? false)===true,
        'fees_approved'=>($config['stripe_fees_approved'] ?? false)===true,
        'refund_policy_approved'=>($config['refund_policy_approved'] ?? false)===true,
        'production_launch_enabled'=>($config['production_launch_enabled'] ?? false)===true,
        'external_mail_enabled'=>(string)($config['payment_mail_transport'] ?? 'disabled')!=='disabled',
    ];

    $readyForStripeTest=$checks['stripe_provider_selected'] && $mode==='test' &&
        $checks['stripe_secret_configured'] && $checks['stripe_webhook_configured'] &&
        $checks['https_public_base_url'];

    $readyForLaunch=($config['mode'] ?? '')==='production' && $mode==='live';
    foreach($checks as $value) $readyForLaunch=$readyForLaunch && $value;

    return [
        'phase'=>'P8',
        'provider_target'=>'stripe',
        'stripe_mode'=>$mode,
        'payment_methods'=>bois_p8_payment_methods($config),
        'swish_enabled'=>bois_p8_stripe_swish_enabled($config),
        'checks'=>$checks,
        'ready_for_stripe_test'=>$readyForStripeTest,
        'ready_for_production_launch'=>$readyForLaunch,
    ];
}