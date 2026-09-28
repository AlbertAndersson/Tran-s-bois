<?php
declare(strict_types=1);

require_once __DIR__ . '/p3_db.php';

/**
 * P9 Sales Engine v1.
 *
 * First-party, privacy-minimised sales attribution for staging/production.
 * Public analytics never stores name, email, phone, IP address or user agent.
 */

function bois_p9_apply_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS bois_sales_sessions (
            session_id CHAR(36) PRIMARY KEY,
            source VARCHAR(80) NULL,
            medium VARCHAR(80) NULL,
            campaign VARCHAR(120) NULL,
            ref_code VARCHAR(80) NULL,
            landing_path VARCHAR(255) NULL,
            first_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            event_count INT UNSIGNED NOT NULL DEFAULT 0,
            INDEX idx_bois_sales_campaign (campaign,source),
            INDEX idx_bois_sales_first_seen (first_seen_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS bois_sales_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_key VARCHAR(80) NOT NULL UNIQUE,
            session_id CHAR(36) NOT NULL,
            event_type VARCHAR(40) NOT NULL,
            page_path VARCHAR(255) NULL,
            product_key VARCHAR(80) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_bois_sales_events_session (session_id,created_at),
            INDEX idx_bois_sales_events_type (event_type,created_at),
            INDEX idx_bois_sales_events_product (product_key,created_at),
            CONSTRAINT fk_bois_sales_events_session
              FOREIGN KEY (session_id) REFERENCES bois_sales_sessions(session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS bois_sales_order_links (
            order_id BIGINT UNSIGNED PRIMARY KEY,
            session_id CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_bois_sales_order_session (session_id,created_at),
            CONSTRAINT fk_bois_sales_order_order
              FOREIGN KEY (order_id) REFERENCES bois_orders(id),
            CONSTRAINT fk_bois_sales_order_session
              FOREIGN KEY (session_id) REFERENCES bois_sales_sessions(session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $stmt=$pdo->prepare("INSERT IGNORE INTO bois_schema_migrations(version) VALUES (?)");
    $stmt->execute(['20260928_p9_sales_engine_v1']);
}

function bois_p9_session_id(mixed $value): string
{
    if(!is_string($value)) return '';
    $value=strtolower(trim($value));
    return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/',$value)
        ? $value
        : '';
}

function bois_p9_token(mixed $value,int $max): string
{
    $value=bois_p3_clean_string($value,$max);
    if($value==='') return '';
    return preg_match('/^[\pL\pN._:+\/-]+$/u',$value) ? $value : '';
}

function bois_p9_path(mixed $value): string
{
    if(!is_string($value)) return '';
    $path=parse_url(trim($value),PHP_URL_PATH);
    if(!is_string($path) || $path==='') return '';
    if(!str_starts_with($path,'/')) $path='/'.$path;
    return substr($path,0,255);
}

function bois_p9_attribution(array $input): array
{
    $a=is_array($input['attribution'] ?? null)?$input['attribution']:[];
    return [
        'source'=>bois_p9_token($a['source']??'',80),
        'medium'=>bois_p9_token($a['medium']??'',80),
        'campaign'=>bois_p9_token($a['campaign']??'',120),
        'ref_code'=>bois_p9_token($a['ref']??'',80),
        'landing_path'=>bois_p9_path($a['landing_path']??''),
    ];
}

function bois_p9_upsert_session(PDO $pdo,string $sessionId,array $attribution): void
{
    $stmt=$pdo->prepare(
        "INSERT INTO bois_sales_sessions(session_id,source,medium,campaign,ref_code,landing_path)
         VALUES(?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
           last_seen_at=CURRENT_TIMESTAMP,
           source=COALESCE(source,NULLIF(VALUES(source),'')),
           medium=COALESCE(medium,NULLIF(VALUES(medium),'')),
           campaign=COALESCE(campaign,NULLIF(VALUES(campaign),'')),
           ref_code=COALESCE(ref_code,NULLIF(VALUES(ref_code),'')),
           landing_path=COALESCE(landing_path,NULLIF(VALUES(landing_path),''))"
    );
    $stmt->execute([
        $sessionId,
        $attribution['source']?:null,
        $attribution['medium']?:null,
        $attribution['campaign']?:null,
        $attribution['ref_code']?:null,
        $attribution['landing_path']?:null,
    ]);
}

function bois_p9_capture_event(PDO $pdo,array $input): array
{
    $sessionId=bois_p9_session_id($input['session_id']??'');
    if($sessionId==='') throw new InvalidArgumentException('Ogiltig sales session.');

    $eventKey=bois_p9_token($input['event_key']??'',80);
    if($eventKey==='' || strlen($eventKey)<8) throw new InvalidArgumentException('Ogiltigt sales event-ID.');

    $eventType=bois_p9_token($input['event_type']??'',40);
    $allowed=['page_view','product_view','checkout_view','checkout_started','order_created'];
    if(!in_array($eventType,$allowed,true)) throw new InvalidArgumentException('Sales-eventtypen stöds inte.');

    $pagePath=bois_p9_path($input['page_path']??'');
    $productKey=bois_p9_token($input['product_key']??'',80);
    $attribution=bois_p9_attribution($input);

    $pdo->beginTransaction();
    try{
        bois_p9_upsert_session($pdo,$sessionId,$attribution);
        $stmt=$pdo->prepare(
            "INSERT IGNORE INTO bois_sales_events(event_key,session_id,event_type,page_path,product_key)
             VALUES(?,?,?,?,?)"
        );
        $stmt->execute([
            $eventKey,$sessionId,$eventType,$pagePath?:null,$productKey?:null
        ]);
        $inserted=$stmt->rowCount()===1;
        if($inserted){
            $pdo->prepare(
                "UPDATE bois_sales_sessions SET event_count=event_count+1,last_seen_at=CURRENT_TIMESTAMP WHERE session_id=?"
            )->execute([$sessionId]);
        }
        $pdo->commit();
        return ['accepted'=>true,'duplicate'=>!$inserted];
    }catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function bois_p9_link_order(PDO $pdo,string $publicId,array $input): void
{
    $sessionId=bois_p9_session_id($input['sales_session_id']??'');
    if($sessionId==='') return;

    $attribution=bois_p9_attribution([
        'attribution'=>is_array($input['sales_attribution']??null)?$input['sales_attribution']:[]
    ]);

    $pdo->beginTransaction();
    try{
        bois_p9_upsert_session($pdo,$sessionId,$attribution);
        $read=$pdo->prepare("SELECT id FROM bois_orders WHERE public_id=? LIMIT 1");
        $read->execute([$publicId]);
        $orderId=(int)($read->fetchColumn()?:0);
        if($orderId<1) throw new OutOfBoundsException('Ordern finns inte.');

        $link=$pdo->prepare(
            "INSERT INTO bois_sales_order_links(order_id,session_id)
             VALUES(?,?)
             ON DUPLICATE KEY UPDATE session_id=VALUES(session_id)"
        );
        $link->execute([$orderId,$sessionId]);

        $eventKey='order-created:'.$publicId;
        $evt=$pdo->prepare(
            "INSERT IGNORE INTO bois_sales_events(event_key,session_id,event_type,page_path,product_key)
             VALUES(?,?,'order_created','/server/order',NULL)"
        );
        $evt->execute([$eventKey,$sessionId]);
        if($evt->rowCount()===1){
            $pdo->prepare(
                "UPDATE bois_sales_sessions SET event_count=event_count+1,last_seen_at=CURRENT_TIMESTAMP WHERE session_id=?"
            )->execute([$sessionId]);
        }
        $pdo->commit();
    }catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function bois_p9_recommendations(PDO $pdo,array $context): array
{
    $skus=[];
    foreach(($context['skus']??[]) as $sku){
        $clean=bois_p9_token($sku,100);
        if($clean!=='') $skus[$clean]=true;
    }
    $existingMember=bois_p3_bool($context['existing_member']??false);
    $hasMembership=(bool)array_filter(array_keys($skus),fn($s)=>str_starts_with($s,'MEM-'));
    $hasGym=isset($skus['NW-GYM-ANNUAL']);

    $recs=[];
    if($hasMembership && !$hasGym){
        $recs[]=[
            'key'=>'membership-to-gym',
            'product_key'=>'nordic-gym',
            'title'=>'Medlemsförmån: Nordic Wellness',
            'message'=>'Som medlem kan du även lägga till Nordic Wellness gymkort för 2 650 kr.',
            'cta'=>'Visa gymkort',
            'kind'=>'cross_sell',
            'discount_ore'=>0,
        ];
    }
    if($hasGym && !$hasMembership && !$existingMember){
        $recs[]=[
            'key'=>'gym-requires-membership',
            'product_key'=>'membership',
            'title'=>'Gymkort kräver medlemskap',
            'message'=>'Lägg till ett medlemskap i samma köp för att bli berättigad till gymkortet.',
            'cta'=>'Lägg till medlemskap',
            'kind'=>'eligibility',
            'discount_ore'=>0,
        ];
    }

    return $recs;
}

function bois_p9_admin_dashboard(PDO $pdo): array
{
    $sessions=(int)$pdo->query("SELECT COUNT(*) FROM bois_sales_sessions")->fetchColumn();
    $events=(int)$pdo->query("SELECT COUNT(*) FROM bois_sales_events")->fetchColumn();
    $sessionsWithOrders=(int)$pdo->query(
        "SELECT COUNT(DISTINCT session_id) FROM bois_sales_order_links"
    )->fetchColumn();
    $paidSessions=(int)$pdo->query(
        "SELECT COUNT(DISTINCT l.session_id)
         FROM bois_sales_order_links l
         JOIN bois_orders o ON o.id=l.order_id
         WHERE o.payment_status IN ('PAID','PARTIALLY_REFUNDED','REFUND_PENDING','REFUNDED')"
    )->fetchColumn();

    $paid=$pdo->query(
        "SELECT COUNT(*) paid_orders,
                COALESCE(SUM(p.paid_ore),0) gross_paid_ore,
                COALESCE(SUM(GREATEST(p.paid_ore-p.refunded_ore,0)),0) net_paid_ore
         FROM bois_sales_order_links l
         JOIN bois_orders o ON o.id=l.order_id
         JOIN bois_payments p ON p.order_id=o.id
         WHERE o.payment_status IN ('PAID','PARTIALLY_REFUNDED','REFUND_PENDING','REFUNDED')"
    )->fetch() ?: [];

    $funnel=[];
    foreach(['page_view','product_view','checkout_view','checkout_started','order_created'] as $type){
        $stmt=$pdo->prepare("SELECT COUNT(DISTINCT session_id) FROM bois_sales_events WHERE event_type=?");
        $stmt->execute([$type]);
        $funnel[$type]=(int)$stmt->fetchColumn();
    }
    $funnel['paid']=$paidSessions;

    $campaigns=$pdo->query(
        "SELECT COALESCE(NULLIF(s.source,''),'direct') source,
                COALESCE(NULLIF(s.medium,''),'none') medium,
                COALESCE(NULLIF(s.campaign,''),'none') campaign,
                COALESCE(NULLIF(s.ref_code,''),'none') ref_code,
                COUNT(DISTINCT s.session_id) sessions,
                COUNT(DISTINCT l.order_id) orders,
                COUNT(DISTINCT CASE WHEN o.payment_status IN ('PAID','PARTIALLY_REFUNDED','REFUND_PENDING','REFUNDED') THEN l.order_id END) paid_orders,
                COALESCE(SUM(CASE WHEN o.payment_status IN ('PAID','PARTIALLY_REFUNDED','REFUND_PENDING','REFUNDED') THEN GREATEST(p.paid_ore-p.refunded_ore,0) ELSE 0 END),0) net_paid_ore
         FROM bois_sales_sessions s
         LEFT JOIN bois_sales_order_links l ON l.session_id=s.session_id
         LEFT JOIN bois_orders o ON o.id=l.order_id
         LEFT JOIN bois_payments p ON p.order_id=o.id
         GROUP BY source,medium,campaign,ref_code
         ORDER BY net_paid_ore DESC,paid_orders DESC,sessions DESC
         LIMIT 100"
    )->fetchAll();

    $products=$pdo->query(
        "SELECT i.sku,i.product_name,
                SUM(i.quantity) quantity,
                COUNT(DISTINCT o.id) paid_orders,
                SUM(i.line_total_ore) gross_item_ore
         FROM bois_sales_order_links l
         JOIN bois_orders o ON o.id=l.order_id
         JOIN bois_order_items i ON i.order_id=o.id
         WHERE o.payment_status IN ('PAID','PARTIALLY_REFUNDED','REFUND_PENDING','REFUNDED')
         GROUP BY i.sku,i.product_name
         ORDER BY gross_item_ore DESC,quantity DESC
         LIMIT 100"
    )->fetchAll();

    $gross=(int)($paid['gross_paid_ore']??0);
    $paidOrders=(int)($paid['paid_orders']??0);
    return [
        'privacy'=>[
            'first_party_only'=>true,
            'personal_data_in_sales_tables'=>false,
            'ip_stored'=>false,
            'user_agent_stored'=>false,
            'external_analytics'=>false,
        ],
        'kpis'=>[
            'sessions'=>$sessions,
            'events'=>$events,
            'sessions_with_orders'=>$sessionsWithOrders,
            'paid_sessions'=>$paidSessions,
            'paid_orders'=>$paidOrders,
            'gross_paid_ore'=>$gross,
            'net_paid_ore'=>(int)($paid['net_paid_ore']??0),
            'average_paid_order_ore'=>$paidOrders>0?(int)round($gross/$paidOrders):0,
            'session_to_paid_pct'=>$sessions>0?round(($paidSessions/$sessions)*100,1):0.0,
        ],
        'funnel'=>$funnel,
        'campaigns'=>$campaigns,
        'products'=>$products,
    ];
}
