<?php
declare(strict_types=1);
require_once __DIR__ . "/security.php";

function bois_p3_load_config(): array
{
    $path = getenv('BOIS_P3_CONFIG_PATH');
    if (!is_string($path) || $path === '') {
        $path = dirname(__DIR__, 2) . '/.bois-p3/config.php';
    }
    if (!is_file($path)) {
        throw new RuntimeException('P3 runtime configuration is missing.');
    }

    $config = require $path;
    if (!is_array($config)) {
        throw new RuntimeException('P3 runtime configuration is invalid.');
    }

    foreach (['mode','admin_token','db'] as $key) {
        if (!array_key_exists($key, $config)) {
            throw new RuntimeException('P3 runtime configuration missing: ' . $key);
        }
    }
    if (!is_array($config['db'])) {
        throw new RuntimeException('P3 database configuration is invalid.');
    }
    foreach (['host','port','database','user','password'] as $key) {
        if (!array_key_exists($key, $config['db'])) {
            throw new RuntimeException('P3 database configuration missing: ' . $key);
        }
    }

    $config['allowed_origins'] = array_values(array_filter($config['allowed_origins'] ?? [], 'is_string'));
    $config["_security_dir"] = dirname($path) . "/security-rate";
    return $config;
}

function bois_p3_pdo(array $config): PDO
{
    $db = $config['db'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        (string)$db['host'],
        (int)$db['port'],
        (string)$db['database']
    );

    $pdo = new PDO($dsn, (string)$db['user'], (string)$db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 8,
    ]);
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}

function bois_p3_schema_statements(): array
{
    return [
        "CREATE TABLE IF NOT EXISTS bois_schema_migrations (
            version VARCHAR(64) PRIMARY KEY,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS bois_suppliers (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            supplier_key VARCHAR(80) NOT NULL UNIQUE,
            name VARCHAR(160) NOT NULL,
            email VARCHAR(190) NULL,
            cc_email VARCHAR(190) NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            config_json JSON NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS bois_products (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_key VARCHAR(80) NOT NULL UNIQUE,
            name VARCHAR(160) NOT NULL,
            category VARCHAR(60) NOT NULL,
            description TEXT NULL,
            price_ore INT UNSIGNED NULL,
            currency CHAR(3) NOT NULL DEFAULT 'SEK',
            supplier_id BIGINT UNSIGNED NULL,
            fulfillment_type VARCHAR(40) NOT NULL,
            is_public TINYINT(1) NOT NULL DEFAULT 0,
            is_orderable TINYINT(1) NOT NULL DEFAULT 0,
            active_from DATETIME NULL,
            active_to DATETIME NULL,
            metadata_json JSON NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_bois_products_public (is_public, is_orderable),
            INDEX idx_bois_products_category (category),
            CONSTRAINT fk_bois_products_supplier FOREIGN KEY (supplier_id) REFERENCES bois_suppliers(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS bois_variants (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT UNSIGNED NOT NULL,
            sku VARCHAR(100) NOT NULL UNIQUE,
            name VARCHAR(160) NOT NULL,
            price_ore INT UNSIGNED NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            metadata_json JSON NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_bois_variants_product (product_id, active),
            CONSTRAINT fk_bois_variants_product FOREIGN KEY (product_id) REFERENCES bois_products(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS bois_fulfillment_rules (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            rule_key VARCHAR(100) NOT NULL UNIQUE,
            product_id BIGINT UNSIGNED NULL,
            supplier_id BIGINT UNSIGNED NULL,
            fulfillment_type VARCHAR(40) NOT NULL,
            threshold_qty INT UNSIGNED NULL,
            max_wait_hours INT UNSIGNED NULL,
            direct_send TINYINT(1) NOT NULL DEFAULT 0,
            enabled TINYINT(1) NOT NULL DEFAULT 0,
            config_json JSON NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_bois_rules_product FOREIGN KEY (product_id) REFERENCES bois_products(id),
            CONSTRAINT fk_bois_rules_supplier FOREIGN KEY (supplier_id) REFERENCES bois_suppliers(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS bois_customers (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_uuid CHAR(36) NOT NULL UNIQUE,
            name VARCHAR(160) NOT NULL,
            email VARCHAR(190) NOT NULL,
            phone VARCHAR(40) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_bois_customers_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS bois_orders (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            public_id VARCHAR(32) NOT NULL UNIQUE,
            public_token CHAR(64) NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(40) NOT NULL,
            payment_status VARCHAR(40) NOT NULL,
            fulfillment_status VARCHAR(40) NOT NULL,
            currency CHAR(3) NOT NULL DEFAULT 'SEK',
            subtotal_ore INT UNSIGNED NOT NULL,
            total_ore INT UNSIGNED NOT NULL,
            source VARCHAR(40) NOT NULL DEFAULT 'web',
            idempotency_key VARCHAR(100) NULL UNIQUE,
            metadata_json JSON NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_bois_orders_status (status, payment_status, fulfillment_status),
            INDEX idx_bois_orders_created (created_at),
            CONSTRAINT fk_bois_orders_customer FOREIGN KEY (customer_id) REFERENCES bois_customers(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS bois_order_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            variant_id BIGINT UNSIGNED NOT NULL,
            sku VARCHAR(100) NOT NULL,
            product_name VARCHAR(160) NOT NULL,
            variant_name VARCHAR(160) NOT NULL,
            quantity INT UNSIGNED NOT NULL DEFAULT 1,
            unit_price_ore INT UNSIGNED NOT NULL,
            line_total_ore INT UNSIGNED NOT NULL,
            fulfillment_type VARCHAR(40) NOT NULL,
            fulfillment_status VARCHAR(40) NOT NULL DEFAULT 'ON_HOLD',
            metadata_json JSON NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_bois_items_order (order_id),
            INDEX idx_bois_items_fulfillment (fulfillment_type, fulfillment_status),
            CONSTRAINT fk_bois_items_order FOREIGN KEY (order_id) REFERENCES bois_orders(id),
            CONSTRAINT fk_bois_items_product FOREIGN KEY (product_id) REFERENCES bois_products(id),
            CONSTRAINT fk_bois_items_variant FOREIGN KEY (variant_id) REFERENCES bois_variants(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS bois_memberships (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_item_id BIGINT UNSIGNED NOT NULL UNIQUE,
            customer_id BIGINT UNSIGNED NOT NULL,
            member_name VARCHAR(160) NOT NULL,
            membership_type VARCHAR(40) NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'PENDING_PAYMENT',
            valid_from DATE NULL,
            valid_to DATE NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_bois_memberships_customer (customer_id, status),
            CONSTRAINT fk_bois_memberships_item FOREIGN KEY (order_item_id) REFERENCES bois_order_items(id),
            CONSTRAINT fk_bois_memberships_customer FOREIGN KEY (customer_id) REFERENCES bois_customers(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS bois_payments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT UNSIGNED NOT NULL,
            provider VARCHAR(60) NULL,
            provider_ref VARCHAR(190) NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'NOT_ENABLED',
            amount_ore INT UNSIGNED NOT NULL,
            currency CHAR(3) NOT NULL DEFAULT 'SEK',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_bois_payments_order (order_id),
            UNIQUE KEY uq_bois_provider_ref (provider, provider_ref),
            CONSTRAINT fk_bois_payments_order FOREIGN KEY (order_id) REFERENCES bois_orders(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS bois_supplier_batches (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            public_id VARCHAR(40) NOT NULL UNIQUE,
            supplier_id BIGINT UNSIGNED NOT NULL,
            fulfillment_type VARCHAR(40) NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'OPEN',
            threshold_qty INT UNSIGNED NULL,
            max_wait_hours INT UNSIGNED NULL,
            order_count INT UNSIGNED NOT NULL DEFAULT 0,
            item_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            locked_at DATETIME NULL,
            sent_at DATETIME NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_bois_batches_status (supplier_id, status),
            CONSTRAINT fk_bois_batches_supplier FOREIGN KEY (supplier_id) REFERENCES bois_suppliers(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS bois_batch_items (
            batch_id BIGINT UNSIGNED NOT NULL,
            order_item_id BIGINT UNSIGNED NOT NULL UNIQUE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (batch_id, order_item_id),
            CONSTRAINT fk_bois_batch_items_batch FOREIGN KEY (batch_id) REFERENCES bois_supplier_batches(id),
            CONSTRAINT fk_bois_batch_items_item FOREIGN KEY (order_item_id) REFERENCES bois_order_items(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS bois_email_outbox (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            message_key VARCHAR(160) NOT NULL UNIQUE,
            order_id BIGINT UNSIGNED NULL,
            batch_id BIGINT UNSIGNED NULL,
            to_email VARCHAR(190) NOT NULL,
            cc_email VARCHAR(190) NULL,
            subject VARCHAR(255) NOT NULL,
            payload_json JSON NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'PENDING',
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            not_before DATETIME NULL,
            sent_at DATETIME NULL,
            last_error TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_bois_outbox_status (status, not_before),
            CONSTRAINT fk_bois_outbox_order FOREIGN KEY (order_id) REFERENCES bois_orders(id),
            CONSTRAINT fk_bois_outbox_batch FOREIGN KEY (batch_id) REFERENCES bois_supplier_batches(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS bois_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT UNSIGNED NULL,
            event_type VARCHAR(80) NOT NULL,
            payload_json JSON NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_bois_events_order (order_id, created_at),
            CONSTRAINT fk_bois_events_order FOREIGN KEY (order_id) REFERENCES bois_orders(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
}

function bois_p3_apply_schema(PDO $pdo): void
{
    foreach (bois_p3_schema_statements() as $sql) {
        $pdo->exec($sql);
    }
    $stmt = $pdo->prepare("INSERT IGNORE INTO bois_schema_migrations(version) VALUES (?)");
    $stmt->execute(['20260927_p3_commerce_core_v1']);
}

function bois_p3_seed_catalog(PDO $pdo): void
{
    $pdo->beginTransaction();
    try {
        $supplier = $pdo->prepare(
            "INSERT INTO bois_suppliers(supplier_key,name,email,cc_email,active,config_json)
             VALUES(?,?,?,?,1,?)
             ON DUPLICATE KEY UPDATE name=VALUES(name), email=VALUES(email), cc_email=VALUES(cc_email), active=VALUES(active), config_json=VALUES(config_json)"
        );
        $supplier->execute(['nordic-wellness-tranas','Nordic Wellness Tranås',null,null,json_encode(['flow'=>'to_confirm'], JSON_THROW_ON_ERROR)]);
        $supplier->execute(['matchkit-supplier','Matchställsleverantör – bekräftas',null,null,json_encode(['flow'=>'batch','supplier_details_pending'=>true], JSON_THROW_ON_ERROR)]);

        $supplierIds = [];
        foreach ($pdo->query("SELECT id,supplier_key FROM bois_suppliers WHERE supplier_key IN ('nordic-wellness-tranas','matchkit-supplier')") as $row) {
            $supplierIds[$row['supplier_key']] = (int)$row['id'];
        }

        $productSql =
            "INSERT INTO bois_products(product_key,name,category,description,price_ore,currency,supplier_id,fulfillment_type,is_public,is_orderable,active_from,metadata_json)
             VALUES(?,?,?,?,?,'SEK',?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
               name=VALUES(name), category=VALUES(category), description=VALUES(description), price_ore=VALUES(price_ore),
               supplier_id=VALUES(supplier_id), fulfillment_type=VALUES(fulfillment_type), is_public=VALUES(is_public),
               is_orderable=VALUES(is_orderable), active_from=VALUES(active_from), metadata_json=VALUES(metadata_json)";
        $product = $pdo->prepare($productSql);

        $product->execute([
            'membership','Medlemskap Tranås BoIS','membership',
            'Stöd föreningen och få tillgång till medlemsförmåner.',null,null,
            'DIGITAL_MEMBERSHIP',1,1,null,
            json_encode(['headline'=>'Bli medlem i BoIS','separate_accounting'=>true], JSON_THROW_ON_ERROR)
        ]);
        $product->execute([
            'nordic-gym','Nordic Wellness gymkort','member_benefit',
            'Förmånligt gymkort för aktiv BoIS-medlem. Högst 20 kort kan fördelas per kalenderår.',265000,$supplierIds['nordic-wellness-tranas'] ?? null,
            'MEMBER_BENEFIT',1,1,null,
            json_encode([
                'membership_required'=>true,
                'price_comparison_requires_confirmation'=>true,
                'annual_limit'=>20,
                'quota_period'=>'calendar_year',
                'quota_timezone'=>'Europe/Stockholm',
                'reservation_minutes'=>30
            ], JSON_THROW_ON_ERROR)
        ]);
        $product->execute([
            'match-kit','Matchställ','match_kit',
            'Matchställ med matchtröja, namn och nummer. Byxa ingår inte i nuvarande erbjudande. Leverantörsorder batchas för att minska frakt.',99800,$supplierIds['matchkit-supplier'] ?? null,
            'BATCH_SUPPLIER',1,1,null,
            json_encode(['staging_price'=>true,'real_price_pending'=>true], JSON_THROW_ON_ERROR)
        ]);

        $future = [
            ['bois-1941-hoodie','BoIS 1941 Hoodie','supporter','Heritage-hoodie för supporter och vardag.'],
            ['supporter-tee','Supporter-T-shirt','supporter','Matchday tee i BoIS-profil.'],
            ['bandy-parent-hoodie','Bandyförälder Hoodie','supporter','Hoodie för bandyföräldrar.'],
            ['winter-set','Mössa + halsduk','supporter','Vinterpaket för bandyarenan.'],
            ['gym-pack','BoIS Gym Pack','training','Tränings-T-shirt, handduk och flaska.'],
            ['knatte-pack','Knatte Pack','youth','Barn-T-shirt, mössa och vattenflaska.'],
            ['gift-card','BoIS Presentkort','digital','Digitalt presentkort för framtida supporterbutik.'],
        ];
        foreach ($future as $row) {
            $product->execute([
                $row[0],$row[1],$row[2],$row[3],null,null,
                $row[0] === 'gift-card' ? 'DIGITAL_GIFT' : 'DIRECT_SUPPLIER',
                0,0,'2027-01-01 00:00:00',
                json_encode(['launch_gate'=>'2027-01-01','supplier_pending'=>true], JSON_THROW_ON_ERROR)
            ]);
        }

        $productIds = [];
        foreach ($pdo->query("SELECT id,product_key FROM bois_products") as $row) {
            $productIds[$row['product_key']] = (int)$row['id'];
        }

        $variantSql =
            "INSERT INTO bois_variants(product_id,sku,name,price_ore,active,metadata_json)
             VALUES(?,?,?,?,1,?)
             ON DUPLICATE KEY UPDATE product_id=VALUES(product_id), name=VALUES(name), price_ore=VALUES(price_ore), active=VALUES(active), metadata_json=VALUES(metadata_json)";
        $variant = $pdo->prepare($variantSql);

        $variant->execute([$productIds['membership'],'MEM-YOUTH','Ungdom',20000,json_encode(['membership_type'=>'youth'], JSON_THROW_ON_ERROR)]);
        $variant->execute([$productIds['membership'],'MEM-ADULT','Vuxen',35000,json_encode(['membership_type'=>'adult'], JSON_THROW_ON_ERROR)]);
        $variant->execute([$productIds['membership'],'MEM-SENIOR','Pensionär',30000,json_encode(['membership_type'=>'senior'], JSON_THROW_ON_ERROR)]);
        $variant->execute([$productIds['nordic-gym'],'NW-GYM-ANNUAL','Gymkort 12 månader',265000,json_encode([
            'eligibility'=>'active_membership',
            'activation_flow'=>'pending_confirmation',
            'annual_limit'=>20,
            'quota_period'=>'calendar_year'
        ], JSON_THROW_ON_ERROR)]);
        $variant->execute([$productIds['match-kit'],'MATCHKIT-STAGING','Matchställ – testvariant',99800,json_encode([
            'shirt_sizes'=>['128','140','152','164','XS','S'],
            'personalization'=>['player_name','number','name_print','number_print'],
            'staging_only'=>true,
            'includes_shorts'=>false
        ], JSON_THROW_ON_ERROR)]);

        $ruleSql =
            "INSERT INTO bois_fulfillment_rules(rule_key,product_id,supplier_id,fulfillment_type,threshold_qty,max_wait_hours,direct_send,enabled,config_json)
             VALUES(?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE product_id=VALUES(product_id),supplier_id=VALUES(supplier_id),fulfillment_type=VALUES(fulfillment_type),
               threshold_qty=VALUES(threshold_qty),max_wait_hours=VALUES(max_wait_hours),direct_send=VALUES(direct_send),enabled=VALUES(enabled),config_json=VALUES(config_json)";
        $rule = $pdo->prepare($ruleSql);

        $rule->execute(['membership-digital',$productIds['membership'],null,'DIGITAL_MEMBERSHIP',null,null,0,0,json_encode(['activate_after_payment'=>true], JSON_THROW_ON_ERROR)]);
        $rule->execute(['nordic-member-benefit',$productIds['nordic-gym'],$supplierIds['nordic-wellness-tranas'] ?? null,'MEMBER_BENEFIT',null,null,0,0,json_encode(['requires_active_membership'=>true,'activation_flow'=>'to_confirm'], JSON_THROW_ON_ERROR)]);
        $rule->execute(['matchkit-batch',$productIds['match-kit'],$supplierIds['matchkit-supplier'] ?? null,'BATCH_SUPPLIER',8,168,0,0,json_encode(['manual_send_allowed'=>true,'send_only_paid'=>true], JSON_THROW_ON_ERROR)]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function bois_p3_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    $hex = bin2hex($data);
    return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
}

function bois_p3_public_id(): string
{
    return 'BOIS-' . gmdate('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 7));
}

function bois_p3_catalog(PDO $pdo, bool $includeHidden=false, ?DateTimeImmutable $asOf=null): array
{
    $where = $includeHidden ? '1=1' : 'p.is_public=1';
    $sql =
        "SELECT p.id product_id,p.product_key,p.name product_name,p.category,p.description,p.price_ore product_price_ore,
                p.currency,p.fulfillment_type,p.is_public,p.is_orderable,p.active_from,p.active_to,p.metadata_json product_metadata_json,
                v.id variant_id,v.sku,v.name variant_name,v.price_ore variant_price_ore,v.active,v.metadata_json variant_metadata_json
         FROM bois_products p
         LEFT JOIN bois_variants v ON v.product_id=p.id AND v.active=1
         WHERE {$where}
         ORDER BY p.id,v.id";

    $products = [];
    foreach ($pdo->query($sql) as $row) {
        $key = (string)$row['product_key'];
        $available=!function_exists('bois_p7_launch_allowed') || bois_p7_launch_allowed($pdo,$key,$asOf);
        if(!$includeHidden && !$available) continue;
        if (!isset($products[$key])) {
            $availability=$key==='nordic-gym' ? bois_p3_gym_quota_status($pdo,$asOf) : null;
            $products[$key] = [
                'product_key'=>$key,
                'name'=>(string)$row['product_name'],
                'category'=>(string)$row['category'],
                'description'=>(string)($row['description'] ?? ''),
                'price_ore'=>$row['product_price_ore'] === null ? null : (int)$row['product_price_ore'],
                'currency'=>(string)$row['currency'],
                'fulfillment_type'=>(string)$row['fulfillment_type'],
                'is_public'=>(bool)$row['is_public'] && $available,
                'is_orderable'=>(bool)$row['is_orderable'] && $available && !($availability['sold_out'] ?? false),
                'active_from'=>$row['active_from'],
                'metadata'=>$row['product_metadata_json'] ? json_decode((string)$row['product_metadata_json'],true) : [],
                'availability'=>$availability,
                'variants'=>[],
            ];
        }
        if ($row['variant_id'] !== null) {
            $products[$key]['variants'][] = [
                'sku'=>(string)$row['sku'],
                'name'=>(string)$row['variant_name'],
                'price_ore'=>$row['variant_price_ore'] === null ? null : (int)$row['variant_price_ore'],
                'metadata'=>$row['variant_metadata_json'] ? json_decode((string)$row['variant_metadata_json'],true) : [],
            ];
        }
    }
    return array_values($products);
}

function bois_p3_clean_string(mixed $value, int $max): string
{
    if (!is_string($value)) return '';
    $value = trim(preg_replace('/\s+/u',' ',$value) ?? '');
    return function_exists('mb_substr') ? mb_substr($value,0,$max) : substr($value,0,$max);
}

function bois_p3_bool(mixed $value): bool
{
    return $value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'on';
}

function bois_p3_gym_quota_status(PDO $pdo, ?DateTimeImmutable $at=null): array
{
    $tz=new DateTimeZone('Europe/Stockholm');
    $at=($at ?? new DateTimeImmutable('now',$tz))->setTimezone($tz);
    $year=(int)$at->format('Y');
    $limit=20;
    $reservationMinutes=30;

    $product=$pdo->prepare("SELECT metadata_json FROM bois_products WHERE product_key='nordic-gym' LIMIT 1");
    $product->execute();
    $metadata=$product->fetchColumn();
    if(is_string($metadata)&&$metadata!==''){
        $decoded=json_decode($metadata,true);
        if(is_array($decoded)){
            $limit=max(0,(int)($decoded['annual_limit']??$limit));
            $reservationMinutes=max(1,min(240,(int)($decoded['reservation_minutes']??$reservationMinutes)));
        }
    }

    $startLocal=new DateTimeImmutable($year.'-01-01 00:00:00',$tz);
    $endLocal=$startLocal->modify('+1 year');
    $utc=new DateTimeZone('UTC');
    $startUtc=$startLocal->setTimezone($utc)->format('Y-m-d H:i:s');
    $endUtc=$endLocal->setTimezone($utc)->format('Y-m-d H:i:s');
    $reservationCutoff=$at->modify('-'.$reservationMinutes.' minutes')->setTimezone($utc)->format('Y-m-d H:i:s');

    $stmt=$pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE
              WHEN o.payment_status IN ('PAID','PARTIALLY_REFUNDED','REFUND_PENDING') THEN oi.quantity
              WHEN o.payment_status IN ('NOT_ENABLED','PENDING') AND o.created_at>=? THEN oi.quantity
              ELSE 0 END),0) allocated_qty,
            COALESCE(SUM(CASE
              WHEN o.payment_status IN ('PAID','PARTIALLY_REFUNDED','REFUND_PENDING') THEN oi.quantity
              ELSE 0 END),0) sold_qty
         FROM bois_order_items oi
         JOIN bois_orders o ON o.id=oi.order_id
         WHERE oi.sku='NW-GYM-ANNUAL' AND o.created_at>=? AND o.created_at<?"
    );
    $stmt->execute([$reservationCutoff,$startUtc,$endUtc]);
    $row=$stmt->fetch() ?: [];
    $allocated=max(0,(int)($row['allocated_qty']??0));
    $sold=max(0,(int)($row['sold_qty']??0));
    $remaining=max(0,$limit-$allocated);

    return [
        'period'=>'calendar_year',
        'year'=>$year,
        'limit'=>$limit,
        'sold'=>$sold,
        'reserved'=>max(0,$allocated-$sold),
        'remaining'=>$remaining,
        'sold_out'=>$remaining<1,
        'reservation_minutes'=>$reservationMinutes,
    ];
}

function bois_p3_acquire_gym_quota_lock(PDO $pdo, int $year): string
{
    $name='bois:nordic-gym:'.$year;
    $stmt=$pdo->prepare('SELECT GET_LOCK(?,5)');
    $stmt->execute([$name]);
    if((int)$stmt->fetchColumn()!==1) throw new RuntimeException('Gymkortets lagersaldo kunde inte reserveras. Försök igen.');
    return $name;
}

function bois_p3_release_named_lock(PDO $pdo, ?string $name): void
{
    if(!$name) return;
    try{
        $stmt=$pdo->prepare('SELECT RELEASE_LOCK(?)');
        $stmt->execute([$name]);
    }catch(Throwable){
    }
}

function bois_p3_resolve_variant(PDO $pdo, string $sku, ?DateTimeImmutable $asOf=null): array
{
    $stmt = $pdo->prepare(
        "SELECT v.id variant_id,v.sku,v.name variant_name,v.price_ore variant_price_ore,v.metadata_json variant_metadata_json,
                p.id product_id,p.product_key,p.name product_name,p.price_ore product_price_ore,p.currency,p.fulfillment_type,
                p.is_public,p.is_orderable,p.metadata_json product_metadata_json
         FROM bois_variants v
         JOIN bois_products p ON p.id=v.product_id
         WHERE v.sku=? AND v.active=1
         LIMIT 1"
    );
    $stmt->execute([$sku]);
    $row = $stmt->fetch();
    if (!$row || !(bool)$row['is_public'] || !(bool)$row['is_orderable']) {
        throw new InvalidArgumentException('Produkten kan inte beställas.');
    }
    if(function_exists('bois_p7_launch_allowed') &&
       !bois_p7_launch_allowed($pdo,(string)$row['product_key'],$asOf)){
        throw new InvalidArgumentException('Produkten kan inte beställas.');
    }
    return $row;
}

function bois_p3_validate_line_meta(array $variant, array $meta): array
{
    $sku = (string)$variant['sku'];
    if ($sku === 'MATCHKIT-STAGING') {
        $team = bois_p3_clean_string($meta['team'] ?? '',60);
        $shirt = bois_p3_clean_string($meta['shirt_size'] ?? '',10);
        $player = bois_p3_clean_string($meta['player_name'] ?? '',80);
        $number = bois_p3_clean_string($meta['number'] ?? '',2);
        $allowed = ['128','140','152','164','XS','S'];
        $allowedTeams = ['P9','F9','P13','F14','P16','Skridsko- & bandyskola 26/27'];

        if (!in_array($team,$allowedTeams,true)) {
            throw new InvalidArgumentException('Välj ett giltigt lag.');
        }
        if (!in_array($shirt,$allowed,true)) {
            throw new InvalidArgumentException('Välj en giltig storlek på matchtröjan.');
        }
        if ($player === '') throw new InvalidArgumentException('Ange spelarens namn.');
        if (!preg_match('/^\d{1,2}$/',$number) || (int)$number < 1 || (int)$number > 99) {
            throw new InvalidArgumentException('Ange ett tröjnummer mellan 1 och 99.');
        }
        return [
            'team'=>$team,
            'shirt_size'=>$shirt,
            'player_name'=>$player,
            'number'=>(string)(int)$number,
            'name_print'=>bois_p3_bool($meta['name_print'] ?? true),
            'number_print'=>bois_p3_bool($meta['number_print'] ?? true),
            'price_note'=>'staging_only',
        ];
    }

    if (str_starts_with($sku,'MEM-')) {
        $memberName = bois_p3_clean_string($meta['member_name'] ?? '',160);
        if ($memberName === '') throw new InvalidArgumentException('Ange medlemmens namn.');
        return ['member_name'=>$memberName];
    }

    if ($sku === 'NW-GYM-ANNUAL') {
        return ['eligibility'=>'membership_required'];
    }

    return [];
}

function bois_p3_create_order(PDO $pdo, array $input, ?DateTimeImmutable $asOf=null): array
{
    if (bois_p3_clean_string($input['website'] ?? '',200) !== '') {
        throw new InvalidArgumentException('Beställningen kunde inte tas emot.');
    }
    if (!bois_p3_bool($input['consent'] ?? false)) {
        throw new InvalidArgumentException('Godkännande krävs för att registrera beställningen.');
    }

    $customer = is_array($input['customer'] ?? null) ? $input['customer'] : [];
    $name = bois_p3_clean_string($customer['name'] ?? '',160);
    $email = strtolower(bois_p3_clean_string($customer['email'] ?? '',190));
    $phone = bois_p3_clean_string($customer['phone'] ?? '',40);
    if ($name === '' || !filter_var($email,FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Ange namn och giltig e-postadress.');
    }

    $lines = $input['items'] ?? null;
    if (!is_array($lines) || count($lines) < 1 || count($lines) > 20) {
        throw new InvalidArgumentException('Beställningen saknar giltiga orderrader.');
    }

    $idempotency = bois_p3_clean_string($input['idempotency_key'] ?? '',100);
    if ($idempotency !== '') {
        $stmt = $pdo->prepare("SELECT public_id,public_token,status,payment_status,total_ore FROM bois_orders WHERE idempotency_key=? LIMIT 1");
        $stmt->execute([$idempotency]);
        $existing = $stmt->fetch();
        if ($existing) return $existing;
    }

    $resolved = [];
    $hasMembership = false;
    $hasGym = false;
    $gymQty = 0;
    $subtotal = 0;

    foreach ($lines as $line) {
        if (!is_array($line)) throw new InvalidArgumentException('Ogiltig orderrad.');
        $sku = bois_p3_clean_string($line['sku'] ?? '',100);
        $qty = max(1,min(10,(int)($line['quantity'] ?? 1)));
        $variant = bois_p3_resolve_variant($pdo,$sku,$asOf);
        $meta = bois_p3_validate_line_meta($variant,is_array($line['metadata'] ?? null) ? $line['metadata'] : []);
        $unit = $variant['variant_price_ore'] !== null ? (int)$variant['variant_price_ore'] : (int)$variant['product_price_ore'];
        $lineTotal = $unit * $qty;

        if (str_starts_with($sku,'MEM-')) $hasMembership = true;
        if ($sku === 'NW-GYM-ANNUAL') {
            $hasGym = true;
            $gymQty += $qty;
        }

        $subtotal += $lineTotal;
        $resolved[] = compact('variant','meta','qty','unit','lineTotal');
    }

    if ($hasGym && !$hasMembership && !bois_p3_bool($input['existing_member'] ?? false)) {
        throw new InvalidArgumentException('Gymkort kräver ett medlemskap i samma köp eller bekräftelse på befintligt medlemskap.');
    }

    $quotaLock=null;
    if($hasGym){
        $initialQuota=bois_p3_gym_quota_status($pdo,$asOf);
        $quotaLock=bois_p3_acquire_gym_quota_lock($pdo,(int)$initialQuota['year']);
        try{
            $quota=bois_p3_gym_quota_status($pdo,$asOf);
            if((int)$quota['remaining']<$gymQty){
                throw new DomainException('Nordic Wellness-gymkorten är slutsålda för '.$quota['year'].'.');
            }
        }catch(Throwable $e){
            bois_p3_release_named_lock($pdo,$quotaLock);
            throw $e;
        }
    }

    $publicId = bois_p3_public_id();
    $publicToken = bin2hex(random_bytes(32));
    $customerUuid = bois_p3_uuid();

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO bois_customers(customer_uuid,name,email,phone) VALUES(?,?,?,?)");
        $stmt->execute([$customerUuid,$name,$email,$phone ?: null]);
        $customerId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare(
            "INSERT INTO bois_orders(public_id,public_token,customer_id,status,payment_status,fulfillment_status,currency,subtotal_ore,total_ore,source,idempotency_key,metadata_json)
             VALUES(?,?,?,'PENDING_PAYMENT','NOT_ENABLED','ON_HOLD','SEK',?,?, 'web', ?, ?)"
        );
        $stmt->execute([
            $publicId,$publicToken,$customerId,$subtotal,$subtotal,$idempotency ?: null,
            json_encode(['existing_member'=>bois_p3_bool($input['existing_member'] ?? false)],JSON_THROW_ON_ERROR)
        ]);
        $orderId = (int)$pdo->lastInsertId();

        $itemStmt = $pdo->prepare(
            "INSERT INTO bois_order_items(order_id,product_id,variant_id,sku,product_name,variant_name,quantity,unit_price_ore,line_total_ore,fulfillment_type,fulfillment_status,metadata_json)
             VALUES(?,?,?,?,?,?,?,?,?,?, 'ON_HOLD', ?)"
        );
        $membershipStmt = $pdo->prepare(
            "INSERT INTO bois_memberships(order_item_id,customer_id,member_name,membership_type,status)
             VALUES(?,?,?,?, 'PENDING_PAYMENT')"
        );

        foreach ($resolved as $row) {
            $v = $row['variant'];
            $itemStmt->execute([
                $orderId,(int)$v['product_id'],(int)$v['variant_id'],(string)$v['sku'],
                (string)$v['product_name'],(string)$v['variant_name'],$row['qty'],$row['unit'],$row['lineTotal'],
                (string)$v['fulfillment_type'],json_encode($row['meta'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)
            ]);
            $orderItemId = (int)$pdo->lastInsertId();

            if (str_starts_with((string)$v['sku'],'MEM-')) {
                $membershipType = match ((string)$v['sku']) {
                    'MEM-YOUTH' => 'youth',
                    'MEM-ADULT' => 'adult',
                    'MEM-SENIOR' => 'senior',
                    default => 'unknown',
                };
                $membershipStmt->execute([$orderItemId,$customerId,(string)$row['meta']['member_name'],$membershipType]);
            }
        }

        $payment = $pdo->prepare(
            "INSERT INTO bois_payments(order_id,status,amount_ore,currency) VALUES(?,'NOT_ENABLED',?,'SEK')"
        );
        $payment->execute([$orderId,$subtotal]);

        $event = $pdo->prepare("INSERT INTO bois_events(order_id,event_type,payload_json) VALUES(?,?,?)");
        $event->execute([$orderId,'ORDER_CREATED',json_encode([
            'public_id'=>$publicId,
            'payment_status'=>'NOT_ENABLED',
            'item_count'=>count($resolved)
        ],JSON_THROW_ON_ERROR)]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    } finally {
        bois_p3_release_named_lock($pdo,$quotaLock);
    }

    return [
        'public_id'=>$publicId,
        'public_token'=>$publicToken,
        'status'=>'PENDING_PAYMENT',
        'payment_status'=>'NOT_ENABLED',
        'total_ore'=>$subtotal,
    ];
}

function bois_p3_public_order(PDO $pdo, string $publicId, string $token): array
{
    $stmt = $pdo->prepare(
        "SELECT o.id,o.public_id,o.public_token,o.status,o.payment_status,o.fulfillment_status,o.currency,o.total_ore,o.created_at,
                c.name customer_name,c.email customer_email
         FROM bois_orders o
         JOIN bois_customers c ON c.id=o.customer_id
         WHERE o.public_id=? LIMIT 1"
    );
    $stmt->execute([$publicId]);
    $order = $stmt->fetch();

    if (!$order || $token === '' || !hash_equals((string)$order['public_token'],$token)) {
        throw new BoisAuthenticationException('Ej behörig.');
    }

    $itemsStmt = $pdo->prepare(
        "SELECT sku,product_name,variant_name,quantity,unit_price_ore,line_total_ore,fulfillment_type,fulfillment_status,metadata_json
         FROM bois_order_items WHERE order_id=? ORDER BY id"
    );
    $itemsStmt->execute([(int)$order['id']]);
    $items = [];
    foreach ($itemsStmt->fetchAll() as $item) {
        $item['metadata'] = $item['metadata_json'] ? json_decode((string)$item['metadata_json'],true) : [];
        unset($item['metadata_json']);
        $items[] = $item;
    }

    unset($order['id'],$order['public_token']);
    $order['items'] = $items;
    return $order;
}

function bois_p3_admin_orders(PDO $pdo, int $limit=100): array
{
    $limit = max(1,min(500,$limit));
    $orders = $pdo->query(
        "SELECT o.id,o.public_id,o.status,o.payment_status,o.fulfillment_status,o.total_ore,o.currency,o.created_at,
                c.name customer_name,c.email customer_email,c.phone customer_phone
         FROM bois_orders o
         JOIN bois_customers c ON c.id=o.customer_id
         ORDER BY o.id DESC LIMIT {$limit}"
    )->fetchAll();

    $itemStmt = $pdo->prepare(
        "SELECT sku,product_name,variant_name,quantity,line_total_ore,fulfillment_type,fulfillment_status,metadata_json
         FROM bois_order_items WHERE order_id=? ORDER BY id"
    );
    foreach ($orders as &$order) {
        $itemStmt->execute([(int)$order['id']]);
        $order['items'] = array_map(function(array $item): array {
            $item['metadata'] = $item['metadata_json'] ? json_decode((string)$item['metadata_json'],true) : [];
            unset($item['metadata_json']);
            return $item;
        }, $itemStmt->fetchAll());
        unset($order['id']);
    }
    unset($order);
    return $orders;
}

function bois_p3_stats(PDO $pdo): array
{
    $tables = [];
    foreach ([
        'bois_products','bois_variants','bois_orders','bois_order_items','bois_memberships',
        'bois_supplier_batches','bois_email_outbox'
    ] as $table) {
        $tables[$table] = (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }
    return $tables;
}
