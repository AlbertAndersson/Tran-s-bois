<?php
declare(strict_types=1);

require_once __DIR__ . '/p3_db.php';
require_once __DIR__ . '/p5_batch.php';
require_once __DIR__ . '/p4_membership.php';

interface BoisPaymentProviderAdapter
{
    public function name(): string;
    public function enabled(array $config): bool;
    public function checkoutSession(): array;
    public function verifyWebhook(array $config,string $raw,string $timestamp,string $signature): void;
}

final class BoisMockPaymentProvider implements BoisPaymentProviderAdapter
{
    public function name(): string { return 'mock'; }
    public function enabled(array $config): bool { return ($config['mode']??'')!=='production'; }
    public function checkoutSession(): array
    {
        return [bois_p6_public_session_id(),bois_p6_provider_ref(),bin2hex(random_bytes(24))];
    }
    public function verifyWebhook(array $config,string $raw,string $timestamp,string $signature): void
    {
        bois_p6_verify_signature($config,$raw,$timestamp,$signature);
    }
}

final class BoisDisabledPaymentProvider implements BoisPaymentProviderAdapter
{
    public function name(): string { return 'disabled'; }
    public function enabled(array $config): bool { return false; }
    public function checkoutSession(): array { throw new DomainException('Betalprovider är avstängd.'); }
    public function verifyWebhook(array $config,string $raw,string $timestamp,string $signature): void
    {
        throw new DomainException('Betalprovider är avstängd.');
    }
}

function bois_p6_adapter(array $config): BoisPaymentProviderAdapter
{
    return bois_p6_provider($config)==='mock'
        ? new BoisMockPaymentProvider()
        : new BoisDisabledPaymentProvider();
}

function bois_p6_apply_schema(PDO $pdo): void
{
    $db=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $hasColumn=function(string $table,string $column) use($pdo,$db): bool {
        $stmt=$pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=? AND table_name=? AND column_name=?'
        );
        $stmt->execute([$db,$table,$column]);
        return (int)$stmt->fetchColumn()>0;
    };

    $columns=[
        'checkout_session_ref'=>"VARCHAR(190) NULL AFTER provider_ref",
        'checkout_token_hash'=>"CHAR(64) NULL AFTER checkout_session_ref",
        'method'=>"VARCHAR(40) NULL AFTER checkout_token_hash",
        'paid_ore'=>"INT UNSIGNED NOT NULL DEFAULT 0 AFTER amount_ore",
        'refunded_ore'=>"INT UNSIGNED NOT NULL DEFAULT 0 AFTER paid_ore",
        'effects_status'=>"VARCHAR(40) NOT NULL DEFAULT 'NOT_APPLICABLE' AFTER refunded_ore",
        'last_event_id'=>"VARCHAR(190) NULL AFTER effects_status",
        'paid_at'=>"DATETIME NULL AFTER last_event_id",
        'failed_at'=>"DATETIME NULL AFTER paid_at",
        'cancelled_at'=>"DATETIME NULL AFTER failed_at",
        'refunded_at'=>"DATETIME NULL AFTER cancelled_at",
    ];
    foreach($columns as $name=>$definition){
        if(!$hasColumn('bois_payments',$name)){
            $pdo->exec("ALTER TABLE bois_payments ADD COLUMN {$name} {$definition}");
        }
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS bois_payment_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            provider VARCHAR(60) NOT NULL,
            event_id VARCHAR(190) NOT NULL,
            event_type VARCHAR(80) NOT NULL,
            provider_ref VARCHAR(190) NULL,
            order_id BIGINT UNSIGNED NULL,
            payment_id BIGINT UNSIGNED NULL,
            payload_hash CHAR(64) NOT NULL,
            signature_verified TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(40) NOT NULL DEFAULT 'RECEIVED',
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            processed_at DATETIME NULL,
            last_error TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_bois_payment_event (provider,event_id),
            INDEX idx_bois_payment_events_status (status,created_at),
            INDEX idx_bois_payment_events_order (order_id,created_at),
            CONSTRAINT fk_bois_payment_events_order FOREIGN KEY (order_id) REFERENCES bois_orders(id),
            CONSTRAINT fk_bois_payment_events_payment FOREIGN KEY (payment_id) REFERENCES bois_payments(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS bois_payment_outbox (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            message_key VARCHAR(190) NOT NULL UNIQUE,
            order_id BIGINT UNSIGNED NOT NULL,
            payment_id BIGINT UNSIGNED NOT NULL,
            kind VARCHAR(60) NOT NULL,
            to_email VARCHAR(190) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            payload_json JSON NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'PENDING',
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            not_before DATETIME NULL,
            sent_at DATETIME NULL,
            last_error TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_bois_payment_outbox_status (status,not_before),
            CONSTRAINT fk_bois_payment_outbox_order FOREIGN KEY (order_id) REFERENCES bois_orders(id),
            CONSTRAINT fk_bois_payment_outbox_payment FOREIGN KEY (payment_id) REFERENCES bois_payments(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $stmt=$pdo->prepare("INSERT IGNORE INTO bois_schema_migrations(version) VALUES (?)");
    $stmt->execute(['20260927_p6_payment_v1']);
}

function bois_p6_provider(array $config): string
{
    return strtolower(trim((string)($config['payment_provider'] ?? 'disabled')));
}

function bois_p6_payment_enabled(array $config): bool
{
    return bois_p6_adapter($config)->enabled($config);
}

function bois_p6_require_mock(array $config): void
{
    if(($config['mode'] ?? '')==='production' || bois_p6_provider($config)!=='mock'){
        throw new DomainException('Mockbetalning är inte tillåten i denna miljö.');
    }
}

function bois_p6_webhook_secret(array $config): string
{
    $secret=(string)($config['payment_webhook_secret'] ?? '');
    if(strlen($secret)<32){
        throw new RuntimeException('Payment webhook secret saknas eller är för kort.');
    }
    return $secret;
}

function bois_p6_public_session_id(): string
{
    return 'P6SESS-'.gmdate('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(8)),0,12));
}

function bois_p6_provider_ref(): string
{
    return 'P6PAY-'.gmdate('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(8)),0,12));
}

function bois_p6_event_id(): string
{
    return 'P6EVT-'.gmdate('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(10)),0,16));
}

function bois_p6_checkout(PDO $pdo,array $config,string $publicId,string $publicToken,string $method): array
{
    if(!bois_p6_payment_enabled($config)){
        throw new DomainException('Betalning är inte aktiverad i denna miljö.');
    }
    $method=strtolower(trim($method));
    if(!in_array($method,['swish','card'],true)){
        throw new InvalidArgumentException('Välj Swish eller kort.');
    }

    [$sessionRef,$providerRef,$token]=bois_p6_adapter($config)->checkoutSession();
    $tokenHash=hash('sha256',$token);

    $pdo->beginTransaction();
    try{
        $stmt=$pdo->prepare(
            "SELECT o.id order_id,o.public_id,o.public_token,o.status order_status,o.payment_status,o.total_ore,o.currency,
                    p.id payment_id,p.status payment_status_row
             FROM bois_orders o
             JOIN bois_payments p ON p.order_id=o.id
             WHERE o.public_id=? FOR UPDATE"
        );
        $stmt->execute([$publicId]);
        $row=$stmt->fetch();
        if(!$row || $publicToken==='' || !hash_equals((string)$row['public_token'],$publicToken)){
            throw new DomainException('Ej behörig.');
        }

        $current=(string)$row['payment_status_row'];
        if(in_array($current,['PAID','PARTIALLY_REFUNDED','REFUNDED'],true)){
            throw new InvalidArgumentException('Ordern är redan betald eller återbetald.');
        }

        $provider=bois_p6_provider($config);
        $pdo->prepare(
            "UPDATE bois_payments
             SET provider=?,provider_ref=?,checkout_session_ref=?,checkout_token_hash=?,method=?,
                 status='PENDING',paid_ore=0,refunded_ore=0,effects_status='NOT_APPLICABLE',
                 last_event_id=NULL,paid_at=NULL,failed_at=NULL,cancelled_at=NULL,refunded_at=NULL
             WHERE id=?"
        )->execute([
            $provider,$providerRef,$sessionRef,$tokenHash,$method,(int)$row['payment_id']
        ]);

        $pdo->prepare(
            "UPDATE bois_orders SET status='PENDING_PAYMENT',payment_status='PENDING' WHERE id=?"
        )->execute([(int)$row['order_id']]);

        $pdo->prepare(
            "INSERT INTO bois_events(order_id,event_type,payload_json)
             VALUES(?,'PAYMENT_CHECKOUT_CREATED',?)"
        )->execute([
            (int)$row['order_id'],
            json_encode([
                'provider'=>$provider,
                'provider_ref'=>$providerRef,
                'checkout_session_ref'=>$sessionRef,
                'method'=>$method
            ],JSON_THROW_ON_ERROR)
        ]);

        $pdo->commit();

        return [
            'session_ref'=>$sessionRef,
            'session_token'=>$token,
            'provider'=>$provider,
            'provider_ref'=>$providerRef,
            'method'=>$method,
            'status'=>'PENDING',
            'amount_ore'=>(int)$row['total_ore'],
            'currency'=>(string)$row['currency'],
            'public_id'=>(string)$row['public_id'],
        ];
    } catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function bois_p6_session(PDO $pdo,string $sessionRef,string $token): array
{
    $stmt=$pdo->prepare(
        "SELECT p.id payment_id,p.order_id,p.provider,p.provider_ref,p.checkout_session_ref,p.checkout_token_hash,
                p.method,p.status,p.amount_ore,p.paid_ore,p.refunded_ore,p.effects_status,
                o.public_id,o.public_token,o.status order_status,o.payment_status,o.fulfillment_status,o.currency,o.total_ore
         FROM bois_payments p
         JOIN bois_orders o ON o.id=p.order_id
         WHERE p.checkout_session_ref=? LIMIT 1"
    );
    $stmt->execute([$sessionRef]);
    $row=$stmt->fetch();
    if(!$row || $token==='' || !hash_equals((string)$row['checkout_token_hash'],hash('sha256',$token))){
        throw new DomainException('Ogiltig betalningssession.');
    }
    unset($row['checkout_token_hash'],$row['public_token']);
    return $row;
}

function bois_p6_signature(array $config,string $raw,string $timestamp): string
{
    return hash_hmac('sha256',$timestamp.'.'.$raw,bois_p6_webhook_secret($config));
}

function bois_p6_verify_signature(array $config,string $raw,string $timestamp,string $signature,int $tolerance=300): void
{
    if(!preg_match('/^\d{10}$/',$timestamp)){
        throw new DomainException('Ogiltig webhook-tidsstämpel.');
    }
    if(abs(time()-(int)$timestamp)>$tolerance){
        throw new DomainException('Webhook-tidsstämpeln ligger utanför tillåtet intervall.');
    }
    $signature=preg_replace('/^sha256=/','',$signature) ?? '';
    $expected=bois_p6_signature($config,$raw,$timestamp);
    if($signature==='' || !hash_equals($expected,$signature)){
        throw new DomainException('Ogiltig webhook-signatur.');
    }
}

function bois_p6_event_payload(array $config,array $session,string $type,int $amountOre=0,?string $eventId=null): array
{
    bois_p6_require_mock($config);
    $eventId=$eventId ?: bois_p6_event_id();

    $payload=[
        'event_id'=>$eventId,
        'type'=>$type,
        'provider'=>(string)$session['provider'],
        'provider_ref'=>(string)$session['provider_ref'],
        'session_ref'=>(string)$session['checkout_session_ref'],
        'order_public_id'=>(string)$session['public_id'],
        'currency'=>(string)$session['currency'],
    ];

    if($type==='payment.succeeded'){
        $payload['amount_ore']=(int)$session['total_ore'];
    } elseif($type==='payment.refunded'){
        $payload['refund_ore']=$amountOre>0 ? $amountOre : (int)$session['paid_ore'];
    }

    return $payload;
}

function bois_p6_claim_event(PDO $pdo,array $event,string $payloadHash): array
{
    $provider=(string)$event['provider'];
    $eventId=(string)$event['event_id'];
    $eventType=(string)$event['type'];
    $providerRef=(string)($event['provider_ref'] ?? '');

    $pdo->beginTransaction();
    try{
        $read=$pdo->prepare(
            "SELECT id,status,attempts,order_id,payment_id,payload_hash FROM bois_payment_events
             WHERE provider=? AND event_id=? FOR UPDATE"
        );
        $read->execute([$provider,$eventId]);
        $existing=$read->fetch();

        if($existing){
            if(!hash_equals((string)$existing['payload_hash'],$payloadHash)){
                throw new DomainException('Event-ID har redan använts med annat innehåll.');
            }
            if((string)$existing['status']==='PROCESSED'){
                $pdo->commit();
                return [
                    'event_db_id'=>(int)$existing['id'],
                    'duplicate'=>true,
                    'status'=>'PROCESSED',
                    'attempts'=>(int)$existing['attempts'],
                ];
            }
            $pdo->prepare(
                "UPDATE bois_payment_events
                 SET event_type=?,provider_ref=?,payload_hash=?,signature_verified=1,
                     status='PROCESSING',attempts=attempts+1,last_error=NULL
                 WHERE id=?"
            )->execute([$eventType,$providerRef ?: null,$payloadHash,(int)$existing['id']]);
            $pdo->commit();
            return [
                'event_db_id'=>(int)$existing['id'],
                'duplicate'=>false,
                'status'=>'PROCESSING',
                'attempts'=>(int)$existing['attempts']+1,
            ];
        }

        $pdo->prepare(
            "INSERT INTO bois_payment_events(
                provider,event_id,event_type,provider_ref,payload_hash,signature_verified,status,attempts
             ) VALUES(?,?,?,?,?,1,'PROCESSING',1)"
        )->execute([$provider,$eventId,$eventType,$providerRef ?: null,$payloadHash]);
        $id=(int)$pdo->lastInsertId();
        $pdo->commit();
        return ['event_db_id'=>$id,'duplicate'=>false,'status'=>'PROCESSING','attempts'=>1];
    } catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function bois_p6_mark_event_error(PDO $pdo,int $eventDbId,Throwable $e): void
{
    $stmt=$pdo->prepare(
        "UPDATE bois_payment_events SET status='ERROR',last_error=? WHERE id=?"
    );
    $stmt->execute([substr($e->getMessage(),0,2000),$eventDbId]);
}

function bois_p6_finish_event(PDO $pdo,int $eventDbId,int $paymentId,int $orderId): void
{
    $stmt=$pdo->prepare(
        "UPDATE bois_payment_events
         SET payment_id=?,order_id=?,status='PROCESSED',processed_at=CURRENT_TIMESTAMP,last_error=NULL
         WHERE id=?"
    );
    $stmt->execute([$paymentId,$orderId,$eventDbId]);
}

function bois_p6_payment_by_provider_ref(PDO $pdo,string $provider,string $providerRef,bool $forUpdate=false): array
{
    $suffix=$forUpdate?' FOR UPDATE':'';
    $stmt=$pdo->prepare(
        "SELECT p.*,o.public_id,o.public_token,o.status order_status,o.payment_status order_payment_status,
                o.fulfillment_status,o.total_ore,o.currency,o.customer_id,c.name customer_name,c.email customer_email
         FROM bois_payments p
         JOIN bois_orders o ON o.id=p.order_id
         JOIN bois_customers c ON c.id=o.customer_id
         WHERE p.provider=? AND p.provider_ref=? LIMIT 1".$suffix
    );
    $stmt->execute([$provider,$providerRef]);
    $row=$stmt->fetch();
    if(!$row) throw new OutOfBoundsException('Betalningen finns inte.');
    return $row;
}

function bois_p6_queue_message(PDO $pdo,array $config,array $payment,string $kind,int $amountOre): void
{
    $production=($config['mode'] ?? '')==='production';
    $to=$production ? (string)$payment['customer_email'] : 'customer@example.invalid';
    if(!filter_var($to,FILTER_VALIDATE_EMAIL)){
        throw new RuntimeException('Kundens e-postadress är ogiltig.');
    }

    $key=strtolower($kind).':'.(int)$payment['id'].':'.$amountOre.':'.(int)$payment['refunded_ore'];
    $subject=match($kind){
        'PAYMENT_RECEIPT'=>'Tranås BoIS – orderbekräftelse och kvitto '.$payment['public_id'],
        'REFUND_RECEIPT'=>'Tranås BoIS – återbetalning '.$payment['public_id'],
        default=>'Tranås BoIS – betalningsinformation '.$payment['public_id'],
    };

    $payload=[
        'kind'=>$kind,
        'order'=>$payment['public_id'],
        'amount_ore'=>$amountOre,
        'currency'=>$payment['currency'],
        'payment_status'=>$payment['status'],
        'provider'=>$payment['provider'],
        'method'=>$payment['method'],
        'safe_staging_recipient'=>!$production,
    ];

    $stmt=$pdo->prepare(
        "INSERT INTO bois_payment_outbox(
            message_key,order_id,payment_id,kind,to_email,subject,payload_json,status,attempts,not_before
         ) VALUES(?,?,?,?,?,?,?,'PENDING',0,CURRENT_TIMESTAMP)
         ON DUPLICATE KEY UPDATE message_key=message_key"
    );
    $stmt->execute([
        $key,(int)$payment['order_id'],(int)$payment['id'],$kind,$to,$subject,
        json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)
    ]);
}

function bois_p6_apply_paid_effects(PDO $pdo,array $config,array $payment): array
{
    if((string)$payment['effects_status']==='APPLIED'){
        return ['already_applied'=>true,'p4'=>['members'=>[],'benefits'=>[]],'p5'=>['auto_batches'=>[]]];
    }

    $p5=bois_p5_apply_verified_paid(
        $pdo,$config,(string)$payment['public_id'],'P6_VERIFIED_WEBHOOK',(string)$payment['provider_ref']
    );
    $p4=bois_p4_apply_paid_order($pdo,$config,(string)$payment['public_id']);

    $pdo->prepare(
        "UPDATE bois_payments SET effects_status='APPLIED' WHERE id=? AND status='PAID'"
    )->execute([(int)$payment['id']]);

    $fresh=bois_p6_payment_by_provider_ref($pdo,(string)$payment['provider'],(string)$payment['provider_ref']);
    bois_p6_queue_message($pdo,$config,$fresh,'PAYMENT_RECEIPT',(int)$fresh['paid_ore']);

    return ['already_applied'=>false,'p4'=>$p4,'p5'=>$p5];
}

function bois_p6_process_succeeded(PDO $pdo,array $config,array $event): array
{
    $provider=(string)$event['provider'];
    $providerRef=(string)$event['provider_ref'];
    $amount=(int)($event['amount_ore'] ?? 0);
    $currency=(string)($event['currency'] ?? '');

    $pdo->beginTransaction();
    try{
        $payment=bois_p6_payment_by_provider_ref($pdo,$provider,$providerRef,true);
        if($currency==='' || $currency!==(string)$payment['currency']){
            throw new InvalidArgumentException('Webhookens valuta stämmer inte med ordern.');
        }
        if((string)($event['order_public_id'] ?? '')!==(string)$payment['public_id'] ||
           (string)($event['session_ref'] ?? '')!==(string)$payment['checkout_session_ref']){
            throw new InvalidArgumentException('Webhookens order eller session stämmer inte med betalningen.');
        }
        if($amount!==(int)$payment['amount_ore']){
            throw new InvalidArgumentException('Webhookens belopp stämmer inte med ordern.');
        }

        $current=(string)$payment['status'];
        if(!in_array($current,['PENDING','PAID'],true)){
            throw new InvalidArgumentException('Betalningen kan inte markeras betald från status '.$current.'.');
        }

        if($current==='PENDING'){
            $pdo->prepare(
                "UPDATE bois_payments
                 SET status='PAID',paid_ore=amount_ore,effects_status='PENDING',last_event_id=?,paid_at=CURRENT_TIMESTAMP
                 WHERE id=?"
            )->execute([(string)$event['event_id'],(int)$payment['id']]);
        } else {
            $pdo->prepare("UPDATE bois_payments SET last_event_id=? WHERE id=?")
                ->execute([(string)$event['event_id'],(int)$payment['id']]);
        }

        $pdo->commit();
    } catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $payment=bois_p6_payment_by_provider_ref($pdo,$provider,$providerRef);
    $effects=bois_p6_apply_paid_effects($pdo,$config,$payment);
    $fresh=bois_p6_payment_by_provider_ref($pdo,$provider,$providerRef);

    return [
        'payment_id'=>(int)$fresh['id'],
        'order_id'=>(int)$fresh['order_id'],
        'public_id'=>(string)$fresh['public_id'],
        'status'=>(string)$fresh['status'],
        'effects'=>$effects,
    ];
}

function bois_p6_process_failed_or_cancelled(PDO $pdo,array $event,string $target): array
{
    $provider=(string)$event['provider'];
    $providerRef=(string)$event['provider_ref'];

    $pdo->beginTransaction();
    try{
        $payment=bois_p6_payment_by_provider_ref($pdo,$provider,$providerRef,true);
        $current=(string)$payment['status'];

        if(in_array($current,['PAID','PARTIALLY_REFUNDED','REFUNDED'],true)){
            throw new InvalidArgumentException('En genomförd betalning kan inte markeras '.$target.'.');
        }
        if(!in_array($current,['PENDING','FAILED','CANCELLED'],true)){
            throw new InvalidArgumentException('Ogiltig betalstatus '.$current.'.');
        }

        $column=$target==='FAILED'?'failed_at':'cancelled_at';
        $pdo->prepare(
            "UPDATE bois_payments SET status=?,effects_status='NOT_APPLICABLE',last_event_id=?,{$column}=CURRENT_TIMESTAMP WHERE id=?"
        )->execute([$target,(string)$event['event_id'],(int)$payment['id']]);

        $pdo->prepare(
            "UPDATE bois_orders SET status='PENDING_PAYMENT',payment_status=? WHERE id=?"
        )->execute([$target,(int)$payment['order_id']]);

        $pdo->prepare(
            "INSERT INTO bois_events(order_id,event_type,payload_json) VALUES(?,?,?)"
        )->execute([
            (int)$payment['order_id'],
            'PAYMENT_'.$target,
            json_encode(['provider'=>$provider,'provider_ref'=>$providerRef],JSON_THROW_ON_ERROR)
        ]);

        $pdo->commit();
        return [
            'payment_id'=>(int)$payment['id'],
            'order_id'=>(int)$payment['order_id'],
            'public_id'=>(string)$payment['public_id'],
            'status'=>$target,
        ];
    } catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function bois_p6_process_refund(PDO $pdo,array $config,array $event): array
{
    $provider=(string)$event['provider'];
    $providerRef=(string)$event['provider_ref'];
    $refund=(int)($event['refund_ore'] ?? 0);
    if($refund<1) throw new InvalidArgumentException('Återbetalningsbelopp saknas.');

    $pdo->beginTransaction();
    try{
        $payment=bois_p6_payment_by_provider_ref($pdo,$provider,$providerRef,true);
        $current=(string)$payment['status'];
        if(!in_array($current,['PAID','PARTIALLY_REFUNDED','REFUND_PENDING'],true)){
            throw new InvalidArgumentException('Betalningen kan inte återbetalas från status '.$current.'.');
        }

        $paid=(int)$payment['paid_ore'];
        $newRefunded=(int)$payment['refunded_ore']+$refund;
        if($newRefunded>$paid){
            throw new InvalidArgumentException('Återbetalningen överstiger betalt belopp.');
        }
        $target=$newRefunded===$paid?'REFUNDED':'PARTIALLY_REFUNDED';

        $pdo->prepare(
            "UPDATE bois_payments
             SET status=?,refunded_ore=?,last_event_id=?,refunded_at=CURRENT_TIMESTAMP
             WHERE id=?"
        )->execute([$target,$newRefunded,(string)$event['event_id'],(int)$payment['id']]);

        $pdo->prepare(
            "UPDATE bois_orders
             SET status=?,payment_status=?,fulfillment_status='REVIEW_REQUIRED'
             WHERE id=?"
        )->execute([
            $target==='REFUNDED'?'REFUNDED':'PAYMENT_REVIEW',
            $target,
            (int)$payment['order_id']
        ]);

        $pdo->prepare(
            "INSERT INTO bois_events(order_id,event_type,payload_json)
             VALUES(?,'PAYMENT_REFUND_REVIEW_REQUIRED',?)"
        )->execute([
            (int)$payment['order_id'],
            json_encode([
                'refund_ore'=>$refund,
                'refunded_total_ore'=>$newRefunded,
                'payment_status'=>$target,
                'automatic_fulfillment_reversal'=>false
            ],JSON_THROW_ON_ERROR)
        ]);

        $pdo->commit();
    } catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $fresh=bois_p6_payment_by_provider_ref($pdo,$provider,$providerRef);
    bois_p6_queue_message($pdo,$config,$fresh,'REFUND_RECEIPT',$refund);

    return [
        'payment_id'=>(int)$fresh['id'],
        'order_id'=>(int)$fresh['order_id'],
        'public_id'=>(string)$fresh['public_id'],
        'status'=>(string)$fresh['status'],
        'refund_ore'=>$refund,
        'refunded_ore'=>(int)$fresh['refunded_ore'],
        'manual_fulfillment_review'=>true,
    ];
}

function bois_p6_process_refund_pending(PDO $pdo,array $event): array
{
    $pdo->beginTransaction();
    try{
        $payment=bois_p6_payment_by_provider_ref($pdo,(string)$event['provider'],(string)$event['provider_ref'],true);
        if(!in_array((string)$payment['status'],['PAID','PARTIALLY_REFUNDED','REFUND_PENDING'],true)){
            throw new InvalidArgumentException('Återbetalning kan inte begäras från aktuell status.');
        }
        $pdo->prepare("UPDATE bois_payments SET status='REFUND_PENDING',last_event_id=? WHERE id=?")
            ->execute([(string)$event['event_id'],(int)$payment['id']]);
        $pdo->prepare(
            "UPDATE bois_orders SET status='PAYMENT_REVIEW',payment_status='REFUND_PENDING',
             fulfillment_status='REVIEW_REQUIRED' WHERE id=?"
        )->execute([(int)$payment['order_id']]);
        $pdo->commit();
        return [
            'payment_id'=>(int)$payment['id'],
            'order_id'=>(int)$payment['order_id'],
            'public_id'=>(string)$payment['public_id'],
            'status'=>'REFUND_PENDING',
            'manual_fulfillment_review'=>true,
        ];
    }catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function bois_p6_process_webhook_raw(PDO $pdo,array $config,string $raw,string $timestamp,string $signature): array
{
    bois_p6_adapter($config)->verifyWebhook($config,$raw,$timestamp,$signature);
    $event=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    if(!is_array($event)) throw new InvalidArgumentException('Ogiltigt webhook-underlag.');

    foreach(['event_id','type','provider','provider_ref'] as $key){
        if(!is_string($event[$key] ?? null) || trim((string)$event[$key])===''){
            throw new InvalidArgumentException('Webhook saknar '.$key.'.');
        }
    }
    if(!in_array((string)$event['type'],[
        'payment.succeeded','payment.failed','payment.cancelled','payment.refund_pending','payment.refunded'
    ],true)){
        throw new InvalidArgumentException('Webhooktypen stöds inte.');
    }
    if((string)$event['provider']!==bois_p6_provider($config) ||
       !bois_p6_payment_enabled($config)){
        throw new DomainException('Betalprovider är inte aktiverad.');
    }

    // Serialize all events for one payment across the event claim and downstream effects.
    $lockName='bois:p6:'.substr(hash('sha256',(string)$event['provider'].':'.(string)$event['provider_ref']),0,50);
    $lockStmt=$pdo->prepare('SELECT GET_LOCK(?,10)');
    $lockStmt->execute([$lockName]);
    if((int)$lockStmt->fetchColumn()!==1) throw new RuntimeException('Betalningen är upptagen; försök igen.');
    try{
        $payloadHash=hash('sha256',$raw);
        $claim=bois_p6_claim_event($pdo,$event,$payloadHash);
        if($claim['duplicate']){
            return ['ok'=>true,'duplicate'=>true,'event_id'=>$event['event_id'],'status'=>'PROCESSED'];
        }

        $eventDbId=(int)$claim['event_db_id'];
        try{
            $linked=bois_p6_payment_by_provider_ref($pdo,(string)$event['provider'],(string)$event['provider_ref']);
            if((string)($event['order_public_id']??'')!==(string)$linked['public_id'] ||
               (string)($event['session_ref']??'')!==(string)$linked['checkout_session_ref'] ||
               (string)($event['currency']??'')!==(string)$linked['currency']){
                throw new InvalidArgumentException('Webhookens order, session eller valuta stämmer inte med betalningen.');
            }
            $result=match((string)$event['type']){
                'payment.succeeded'=>bois_p6_process_succeeded($pdo,$config,$event),
                'payment.failed'=>bois_p6_process_failed_or_cancelled($pdo,$event,'FAILED'),
                'payment.cancelled'=>bois_p6_process_failed_or_cancelled($pdo,$event,'CANCELLED'),
                'payment.refund_pending'=>bois_p6_process_refund_pending($pdo,$event),
                'payment.refunded'=>bois_p6_process_refund($pdo,$config,$event),
            };
            bois_p6_finish_event($pdo,$eventDbId,(int)$result['payment_id'],(int)$result['order_id']);
            return ['ok'=>true,'duplicate'=>false,'event_id'=>$event['event_id'],'result'=>$result];
        } catch(Throwable $e){
            bois_p6_mark_event_error($pdo,$eventDbId,$e);
            throw $e;
        }
    } finally {
        $release=$pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
}

function bois_p6_admin_simulate_paid(PDO $pdo,array $config,string $publicId): array
{
    bois_p6_require_mock($config);
    $read=$pdo->prepare(
        "SELECT o.public_token,p.status FROM bois_orders o
         JOIN bois_payments p ON p.order_id=o.id WHERE o.public_id=? LIMIT 1"
    );
    $read->execute([$publicId]);
    $order=$read->fetch();
    if(!$order) throw new OutOfBoundsException('Ordern finns inte.');
    if(in_array((string)$order['status'],['PAID','PARTIALLY_REFUNDED','REFUNDED'],true)){
        throw new InvalidArgumentException('Ordern är redan betald eller återbetald.');
    }
    $checkout=bois_p6_checkout($pdo,$config,$publicId,(string)$order['public_token'],'card');
    return bois_p6_mock_event($pdo,$config,$checkout['session_ref'],$checkout['session_token'],'paid');
}

function bois_p6_mock_event(PDO $pdo,array $config,string $sessionRef,string $token,string $outcome,int $refundOre=0): array
{
    bois_p6_require_mock($config);
    $session=bois_p6_session($pdo,$sessionRef,$token);

    $type=match($outcome){
        'paid'=>'payment.succeeded',
        'failed'=>'payment.failed',
        'cancelled'=>'payment.cancelled',
        'refund_pending'=>'payment.refund_pending',
        'refunded'=>'payment.refunded',
        default=>throw new InvalidArgumentException('Okänt betalutfall.')
    };

    $event=bois_p6_event_payload($config,$session,$type,$refundOre);
    $raw=json_encode($event,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $timestamp=(string)time();
    $signature='sha256='.bois_p6_signature($config,$raw,$timestamp);
    return bois_p6_process_webhook_raw($pdo,$config,$raw,$timestamp,$signature);
}

function bois_p6_admin_payments(PDO $pdo): array
{
    $payments=$pdo->query(
        "SELECT p.id,p.provider,p.provider_ref,p.checkout_session_ref,p.method,p.status,p.amount_ore,p.paid_ore,p.refunded_ore,
                p.effects_status,p.paid_at,p.failed_at,p.cancelled_at,p.refunded_at,p.created_at,p.updated_at,
                o.public_id,o.status order_status,o.payment_status,o.fulfillment_status,
                c.name customer_name,c.email customer_email
         FROM bois_payments p
         JOIN bois_orders o ON o.id=p.order_id
         JOIN bois_customers c ON c.id=o.customer_id
         ORDER BY p.id DESC LIMIT 200"
    )->fetchAll();

    $events=$pdo->query(
        "SELECT id,provider,event_id,event_type,provider_ref,status,attempts,processed_at,last_error,created_at
         FROM bois_payment_events ORDER BY id DESC LIMIT 200"
    )->fetchAll();

    $outbox=$pdo->query(
        "SELECT id,message_key,kind,to_email,subject,status,attempts,not_before,sent_at,last_error,created_at
         FROM bois_payment_outbox ORDER BY id DESC LIMIT 200"
    )->fetchAll();

    return ['payments'=>$payments,'events'=>$events,'outbox'=>$outbox];
}

function bois_p6_public_payment(PDO $pdo,string $publicId): array
{
    $stmt=$pdo->prepare(
        "SELECT p.provider_ref,p.method,p.status,p.paid_at,p.refunded_at
         FROM bois_payments p JOIN bois_orders o ON o.id=p.order_id
         WHERE o.public_id=? LIMIT 1"
    );
    $stmt->execute([$publicId]);
    $payment=$stmt->fetch();
    if(!$payment) throw new OutOfBoundsException('Betalningen finns inte.');
    return [
        'status'=>$payment['status'],
        'method'=>$payment['method'],
        'reference'=>$payment['provider_ref'],
        'paid_at'=>$payment['paid_at'],
        'refunded_at'=>$payment['refunded_at'],
    ];
}

function bois_p6_retry_outbox(PDO $pdo,int $id): array
{
    $stmt=$pdo->prepare(
        "UPDATE bois_payment_outbox
         SET status='PENDING',not_before=CURRENT_TIMESTAMP,last_error=NULL
         WHERE id=? AND status IN ('RETRY','FAILED')"
    );
    $stmt->execute([$id]);

    $read=$pdo->prepare("SELECT id,message_key,status,attempts,not_before,last_error FROM bois_payment_outbox WHERE id=?");
    $read->execute([$id]);
    $row=$read->fetch();
    if(!$row) throw new OutOfBoundsException('Payment-outbox-posten finns inte.');
    return $row;
}

function bois_p6_deliver_outbox(PDO $pdo,array $config,?callable $sender=null,int $limit=20): array
{
    $transport=(string)($config['payment_mail_transport'] ?? 'disabled');
    if($sender===null && $transport==='disabled'){
        return ['transport'=>'disabled','processed'=>0,'sent'=>0,'failed'=>0];
    }
    if($sender===null){
        if($transport!=='php_mail') throw new RuntimeException('Okänt payment-mailtransportläge.');
        $sender=function(array $message): bool {
            $payload=json_decode((string)$message['payload_json'],true) ?: [];
            $body="Tranås BoIS\n\n";
            $body.="Order: ".($payload['order'] ?? '')."\n";
            $body.="Status: ".($payload['payment_status'] ?? '')."\n";
            $body.="Belopp: ".number_format(((int)($payload['amount_ore'] ?? 0))/100,0,',',' ')." kr\n";
            return mail(
                (string)$message['to_email'],
                (string)$message['subject'],
                $body,
                "Content-Type: text/plain; charset=UTF-8\r\nX-Mailer: Tranås-BoIS-Shop"
            );
        };
    }

    $rows=$pdo->query(
        "SELECT * FROM bois_payment_outbox
         WHERE status IN ('PENDING','RETRY')
           AND (not_before IS NULL OR not_before<=CURRENT_TIMESTAMP)
           AND attempts<5
         ORDER BY id
         LIMIT ".max(1,min(100,$limit))
    )->fetchAll();

    $result=['transport'=>$transport,'processed'=>0,'sent'=>0,'failed'=>0];
    foreach($rows as $message){
        $result['processed']++;
        $ok=false;$error=null;
        try{
            $ok=(bool)$sender($message);
            if(!$ok) $error='Transport returned false.';
        } catch(Throwable $e){
            $error=$e->getMessage();
        }

        $pdo->beginTransaction();
        try{
            $locked=$pdo->prepare("SELECT * FROM bois_payment_outbox WHERE id=? FOR UPDATE");
            $locked->execute([(int)$message['id']]);
            $current=$locked->fetch();
            if(!$current || !in_array((string)$current['status'],['PENDING','RETRY'],true)){
                $pdo->rollBack();
                continue;
            }

            $attempts=(int)$current['attempts']+1;
            if($ok){
                $pdo->prepare(
                    "UPDATE bois_payment_outbox
                     SET status='SENT',attempts=?,sent_at=CURRENT_TIMESTAMP,last_error=NULL
                     WHERE id=?"
                )->execute([$attempts,(int)$message['id']]);
                $result['sent']++;
            } else {
                $status=$attempts>=5?'FAILED':'RETRY';
                $delay=min(240,5*(2 ** max(0,$attempts-1)));
                $pdo->prepare(
                    "UPDATE bois_payment_outbox
                     SET status=?,attempts=?,last_error=?,not_before=DATE_ADD(CURRENT_TIMESTAMP,INTERVAL ? MINUTE)
                     WHERE id=?"
                )->execute([$status,$attempts,substr((string)$error,0,2000),$delay,(int)$message['id']]);
                $result['failed']++;
            }
            $pdo->commit();
        } catch(Throwable $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
    return $result;
}
