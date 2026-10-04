<?php
declare(strict_types=1);
require_once __DIR__ . "/security.php";

require_once __DIR__ . '/p3_db.php';
require_once __DIR__ . '/p17_mail.php';

function bois_p5_apply_schema(PDO $pdo): void
{
    $db = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();

    $hasColumn = function(string $table, string $column) use ($pdo, $db): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=? AND table_name=? AND column_name=?'
        );
        $stmt->execute([$db, $table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    };

    if (!$hasColumn('bois_supplier_batches', 'trigger_reason')) {
        $pdo->exec("ALTER TABLE bois_supplier_batches ADD COLUMN trigger_reason VARCHAR(40) NULL AFTER status");
    }
    if (!$hasColumn('bois_supplier_batches', 'csv_sha256')) {
        $pdo->exec("ALTER TABLE bois_supplier_batches ADD COLUMN csv_sha256 CHAR(64) NULL AFTER item_count");
    }
    if (!$hasColumn('bois_supplier_batches', 'config_json')) {
        $pdo->exec("ALTER TABLE bois_supplier_batches ADD COLUMN config_json JSON NULL AFTER csv_sha256");
    }

    $stmt = $pdo->prepare("INSERT IGNORE INTO bois_schema_migrations(version) VALUES (?)");
    $stmt->execute(['20260927_p5_matchkit_batching_v1']);

    $stmt = $pdo->prepare(
        "UPDATE bois_fulfillment_rules
         SET threshold_qty=?, max_wait_hours=?, enabled=1,
             config_json=JSON_SET(COALESCE(config_json, JSON_OBJECT()),
                '$.manual_send_allowed', true,
                '$.send_only_paid', true,
                '$.p5_version', 1)
         WHERE rule_key='matchkit-batch'"
    );
    $stmt->execute([8, 168]);
}

function bois_p5_public_batch_id(): string
{
    return 'BATCH-' . gmdate('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

function bois_p5_matchkit_rule(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT r.id rule_id,r.rule_key,r.product_id,r.supplier_id,r.fulfillment_type,
                r.threshold_qty,r.max_wait_hours,r.direct_send,r.enabled,r.config_json,
                s.supplier_key,s.name supplier_name,s.email supplier_email,s.cc_email supplier_cc
         FROM bois_fulfillment_rules r
         LEFT JOIN bois_suppliers s ON s.id=r.supplier_id
         WHERE r.rule_key='matchkit-batch'
         LIMIT 1"
    );
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Matchställsregeln saknas.');
    }
    return $row;
}

function bois_p5_apply_verified_paid(PDO $pdo, array $config, string $publicId, string $source='VERIFIED_PAYMENT', ?string $providerRef=null): array
{
    // Legacy P5 tests can simulate payment before P6 is installed. Once P6 exists,
    // only its verified event handler may enter the shared fulfillment function.
    $p6Installed=(int)$pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema=DATABASE() AND table_name='bois_payment_events'"
    )->fetchColumn()>0;
    if($p6Installed){
        if($source!=='P6_VERIFIED_WEBHOOK' || !$providerRef){
            throw new BoisForbiddenException('Verifierad P6-betalning krävs.');
        }
        $verified=$pdo->prepare(
            "SELECT COUNT(*) FROM bois_payments p JOIN bois_orders o ON o.id=p.order_id
             WHERE o.public_id=? AND p.provider_ref=? AND p.status='PAID'
               AND p.effects_status IN ('PENDING','APPLIED')"
        );
        $verified->execute([$publicId,$providerRef]);
        if((int)$verified->fetchColumn()!==1){
            throw new BoisForbiddenException('Betalningen är inte verifierad av P6.');
        }
    }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "SELECT id,public_id,payment_status,status FROM bois_orders WHERE public_id=? FOR UPDATE"
        );
        $stmt->execute([$publicId]);
        $order = $stmt->fetch();
        if (!$order) throw new OutOfBoundsException('Ordern finns inte.');

        $orderId = (int)$order['id'];
        $alreadyPaid = (string)$order['payment_status'] === 'PAID';

        $pdo->prepare(
            "UPDATE bois_orders
             SET payment_status='PAID', status='PAID',
                 fulfillment_status=CASE
                   WHEN EXISTS(
                     SELECT 1 FROM bois_order_items i
                     WHERE i.order_id=bois_orders.id AND i.fulfillment_type='BATCH_SUPPLIER'
                   ) THEN 'WAITING_BATCH'
                   ELSE fulfillment_status
                 END
             WHERE id=?"
        )->execute([$orderId]);

        $pdo->prepare(
            "UPDATE bois_payments
             SET status='PAID', updated_at=CURRENT_TIMESTAMP
             WHERE order_id=? AND status IN ('NOT_ENABLED','PENDING','PAID')"
        )->execute([$orderId]);

        $pdo->prepare(
            "UPDATE bois_order_items
             SET fulfillment_status='WAITING_BATCH'
             WHERE order_id=? AND fulfillment_type='BATCH_SUPPLIER' AND fulfillment_status='ON_HOLD'"
        )->execute([$orderId]);

        if (!$alreadyPaid) {
            $pdo->prepare(
                "INSERT INTO bois_events(order_id,event_type,payload_json) VALUES(?, 'PAYMENT_PAID', ?)"
            )->execute([
                $orderId,
                json_encode([
                    'source'=>$source,
                    'provider_ref'=>$providerRef
                ], JSON_THROW_ON_ERROR)
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $created = bois_p5_evaluate_batches($pdo, $config, false);

    return [
        'public_id'=>$publicId,
        'payment_status'=>'PAID',
        'already_paid'=>$alreadyPaid,
        'auto_batches'=>$created,
    ];
}

function bois_p5_mark_order_paid(PDO $pdo, array $config, string $publicId): array
{
    if (($config['mode'] ?? '') === 'production') {
        throw new BoisForbiddenException('Simulerad betalning är inte tillåten i produktion.');
    }

    $result = bois_p5_apply_verified_paid($pdo, $config, $publicId, 'P5_STAGING_ADMIN', null);

    $stmt=$pdo->prepare(
        "SELECT id FROM bois_orders WHERE public_id=? LIMIT 1"
    );
    $stmt->execute([$publicId]);
    $orderId=(int)$stmt->fetchColumn();
    if($orderId>0 && !$result['already_paid']){
        $pdo->prepare(
            "INSERT INTO bois_events(order_id,event_type,payload_json) VALUES(?, 'PAYMENT_SIMULATED_PAID', ?)"
        )->execute([
            $orderId,
            json_encode(['source'=>'P5_STAGING_ADMIN'], JSON_THROW_ON_ERROR)
        ]);
    }

    return $result;
}

function bois_p5_candidate_rows(PDO $pdo, array $rule, bool $forUpdate=false): array
{
    $lock = $forUpdate ? ' FOR UPDATE' : '';
    $stmt = $pdo->prepare(
        "SELECT i.id order_item_id,i.order_id,i.sku,i.product_name,i.variant_name,i.quantity,i.metadata_json,i.created_at item_created_at,
                o.public_id order_public_id,o.created_at order_created_at,o.payment_status,o.fulfillment_status order_fulfillment_status,
                c.name customer_name,c.email customer_email,
                p.supplier_id,p.product_key
         FROM bois_order_items i
         JOIN bois_orders o ON o.id=i.order_id
         JOIN bois_customers c ON c.id=o.customer_id
         JOIN bois_products p ON p.id=i.product_id
         LEFT JOIN bois_batch_items bi ON bi.order_item_id=i.id
         WHERE i.product_id=?
           AND i.fulfillment_type='BATCH_SUPPLIER'
           AND i.fulfillment_status='WAITING_BATCH'
           AND o.payment_status='PAID'
           AND bi.order_item_id IS NULL
         ORDER BY i.created_at ASC,i.id ASC" . $lock
    );
    $stmt->execute([(int)$rule['product_id']]);
    return $stmt->fetchAll();
}

function bois_p5_due_reason(array $rule, array $rows, bool $manual): ?string
{
    if (!$rows) return null;
    if ($manual) return 'MANUAL';

    $qty = array_sum(array_map(fn(array $row): int => (int)$row['quantity'], $rows));
    $threshold = max(1, (int)($rule['threshold_qty'] ?? 8));
    if ($qty >= $threshold) return 'THRESHOLD';

    $maxWait = max(1, (int)($rule['max_wait_hours'] ?? 168));
    $oldest = strtotime((string)$rows[0]['item_created_at'] . ' UTC');
    if ($oldest !== false && $oldest <= time() - ($maxWait * 3600)) return 'MAX_WAIT';

    return null;
}

function bois_p5_csv(array $rows): string
{
    $stream = fopen('php://temp', 'w+b');
    fwrite($stream, "\xEF\xBB\xBF");
    fputcsv($stream, [
        'Order','Lag','Spelare','Tröja','Byxa','Nummer','Namntryck','Nummertryck','Antal','SKU'
    ], ';');

    foreach ($rows as $row) {
        $meta = $row['metadata_json'] ? json_decode((string)$row['metadata_json'], true) : [];
        fputcsv($stream, [
            $row['order_public_id'],
            $meta['team'] ?? '',
            $meta['player_name'] ?? '',
            $meta['shirt_size'] ?? '',
            $meta['shorts_size'] ?? '',
            $meta['number'] ?? '',
            !empty($meta['name_print']) ? 'Ja' : 'Nej',
            !empty($meta['number_print']) ? 'Ja' : 'Nej',
            (int)$row['quantity'],
            $row['sku'],
        ], ';');
    }

    rewind($stream);
    $csv = stream_get_contents($stream);
    fclose($stream);
    return is_string($csv) ? $csv : '';
}

function bois_p5_recipients(array $config, array $rule): array
{
    if (($config['mode'] ?? '') !== 'production') {
        return [
            'to'=>'supplier@example.invalid',
            'cc'=>'erik@example.invalid',
            'safe_staging'=>true,
        ];
    }

    $to = trim((string)($rule['supplier_email'] ?? ''));
    $cc = trim((string)($rule['supplier_cc'] ?? ''));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Leverantörens e-postadress saknas.');
    }
    if ($cc !== '' && !filter_var($cc, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Kopia-adressen är ogiltig.');
    }
    return ['to'=>$to,'cc'=>$cc,'safe_staging'=>false];
}

function bois_p5_create_batch(PDO $pdo, array $config, array $rule, bool $manual=false): ?array
{
    $pdo->beginTransaction();
    try {
        $rows = bois_p5_candidate_rows($pdo, $rule, true);
        $reason = bois_p5_due_reason($rule, $rows, $manual);
        if ($reason === null) {
            $pdo->rollBack();
            return null;
        }

        $batchId = bois_p5_public_batch_id();
        $qty = array_sum(array_map(fn(array $row): int => (int)$row['quantity'], $rows));
        $orderIds = array_values(array_unique(array_map(fn(array $row): int => (int)$row['order_id'], $rows)));
        $csv = bois_p5_csv($rows);
        $csvHash = hash('sha256', $csv);
        $recipients = bois_p5_recipients($config, $rule);

        $stmt = $pdo->prepare(
            "INSERT INTO bois_supplier_batches(
                public_id,supplier_id,fulfillment_type,status,trigger_reason,
                threshold_qty,max_wait_hours,order_count,item_count,csv_sha256,config_json
             ) VALUES(?,?,'BATCH_SUPPLIER','QUEUED',?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $batchId,
            (int)$rule['supplier_id'],
            $reason,
            (int)$rule['threshold_qty'],
            (int)$rule['max_wait_hours'],
            count($orderIds),
            $qty,
            $csvHash,
            json_encode([
                'rule_key'=>$rule['rule_key'],
                'safe_staging_recipient'=>$recipients['safe_staging'],
            ], JSON_THROW_ON_ERROR),
        ]);
        $batchDbId = (int)$pdo->lastInsertId();

        $link = $pdo->prepare("INSERT INTO bois_batch_items(batch_id,order_item_id) VALUES(?,?)");
        $mark = $pdo->prepare("UPDATE bois_order_items SET fulfillment_status='BATCHED' WHERE id=? AND fulfillment_status='WAITING_BATCH'");

        foreach ($rows as $row) {
            $link->execute([$batchDbId, (int)$row['order_item_id']]);
            $mark->execute([(int)$row['order_item_id']]);
            if ($mark->rowCount() !== 1) {
                throw new RuntimeException('Batchlåsning misslyckades.');
            }
        }

        $updateOrder = $pdo->prepare(
            "UPDATE bois_orders o
             SET fulfillment_status='BATCHED'
             WHERE o.id=?
               AND NOT EXISTS(
                 SELECT 1 FROM bois_order_items i
                 WHERE i.order_id=o.id
                   AND i.fulfillment_type='BATCH_SUPPLIER'
                   AND i.fulfillment_status='WAITING_BATCH'
               )"
        );
        foreach ($orderIds as $orderId) $updateOrder->execute([$orderId]);

        $payload = [
            'kind'=>'SUPPLIER_BATCH',
            'batch_id'=>$batchId,
            'supplier'=>$rule['supplier_name'] ?: 'Leverantör',
            'trigger_reason'=>$reason,
            'order_count'=>count($orderIds),
            'item_count'=>$qty,
            'csv_filename'=>'tranas-bois-' . strtolower($batchId) . '.csv',
            'csv_sha256'=>$csvHash,
            'csv_base64'=>base64_encode($csv),
            'rows'=>array_map(function(array $row): array {
                $meta = $row['metadata_json'] ? json_decode((string)$row['metadata_json'], true) : [];
                return [
                    'order'=>$row['order_public_id'],
                    'team'=>$meta['team'] ?? '',
                    'player'=>$meta['player_name'] ?? '',
                    'shirt_size'=>$meta['shirt_size'] ?? '',
                    'shorts_size'=>$meta['shorts_size'] ?? '',
                    'number'=>$meta['number'] ?? '',
                    'quantity'=>(int)$row['quantity'],
                ];
            }, $rows),
        ];

        $subject = sprintf('Tranås BoIS – matchställ %s (%d st)', $batchId, $qty);
        $messageKey = 'supplier_batch:' . $batchId;

        $outbox = $pdo->prepare(
            "INSERT INTO bois_email_outbox(
                message_key,batch_id,to_email,cc_email,subject,payload_json,status,attempts,not_before
             ) VALUES(?,?,?,?,?,?,'PENDING',0,CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE message_key=message_key"
        );
        $outbox->execute([
            $messageKey,
            $batchDbId,
            $recipients['to'],
            $recipients['cc'] ?: null,
            $subject,
            json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),
        ]);

        $event = $pdo->prepare(
            "INSERT INTO bois_events(order_id,event_type,payload_json) VALUES(NULL,'SUPPLIER_BATCH_QUEUED',?)"
        );
        $event->execute([
            json_encode([
                'batch_id'=>$batchId,
                'trigger_reason'=>$reason,
                'order_count'=>count($orderIds),
                'item_count'=>$qty,
                'csv_sha256'=>$csvHash,
            ], JSON_THROW_ON_ERROR)
        ]);

        $pdo->commit();

        return [
            'public_id'=>$batchId,
            'status'=>'QUEUED',
            'trigger_reason'=>$reason,
            'order_count'=>count($orderIds),
            'item_count'=>$qty,
            'csv_sha256'=>$csvHash,
            'outbox_message_key'=>$messageKey,
            'to_email'=>$recipients['to'],
            'cc_email'=>$recipients['cc'],
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function bois_p5_evaluate_batches(PDO $pdo, array $config, bool $manual=false): array
{
    $rule = bois_p5_matchkit_rule($pdo);
    if (!(bool)$rule['enabled']) return [];

    $created = bois_p5_create_batch($pdo, $config, $rule, $manual);
    return $created ? [$created] : [];
}

function bois_p5_batch_rows(PDO $pdo, string $publicBatchId): array
{
    $stmt = $pdo->prepare(
        "SELECT b.public_id batch_public_id,b.status,b.trigger_reason,b.created_at,b.sent_at,
                o.public_id order_public_id,i.sku,i.product_name,i.variant_name,i.quantity,i.metadata_json,
                i.created_at item_created_at
         FROM bois_supplier_batches b
         JOIN bois_batch_items bi ON bi.batch_id=b.id
         JOIN bois_order_items i ON i.id=bi.order_item_id
         JOIN bois_orders o ON o.id=i.order_id
         WHERE b.public_id=?
         ORDER BY i.created_at ASC,i.id ASC"
    );
    $stmt->execute([$publicBatchId]);
    $rows = $stmt->fetchAll();
    if (!$rows) throw new OutOfBoundsException('Batchen finns inte.');
    return $rows;
}

function bois_p5_batch_csv(PDO $pdo, string $publicBatchId): string
{
    return bois_p5_csv(bois_p5_batch_rows($pdo, $publicBatchId));
}

function bois_p5_admin_batches(PDO $pdo): array
{
    $sql =
        "SELECT b.public_id,b.status,b.trigger_reason,b.threshold_qty,b.max_wait_hours,b.order_count,b.item_count,
                b.csv_sha256,b.created_at,b.locked_at,b.sent_at,
                s.name supplier_name,
                e.id outbox_id,e.status outbox_status,e.attempts,e.not_before,e.sent_at email_sent_at,e.last_error,
                e.to_email,e.cc_email
         FROM bois_supplier_batches b
         JOIN bois_suppliers s ON s.id=b.supplier_id
         LEFT JOIN bois_email_outbox e ON e.batch_id=b.id
         ORDER BY b.id DESC
         LIMIT 100";
    return $pdo->query($sql)->fetchAll();
}

function bois_p5_waiting_summary(PDO $pdo): array
{
    $rule = bois_p5_matchkit_rule($pdo);
    $rows = bois_p5_candidate_rows($pdo, $rule, false);
    $qty = array_sum(array_map(fn(array $row): int => (int)$row['quantity'], $rows));
    $oldestHours = null;

    if ($rows) {
        $oldest = strtotime((string)$rows[0]['item_created_at'] . ' UTC');
        if ($oldest !== false) $oldestHours = max(0, (int)floor((time() - $oldest) / 3600));
    }

    return [
        'waiting_order_count'=>count(array_unique(array_column($rows, 'order_id'))),
        'waiting_item_count'=>$qty,
        'threshold_qty'=>(int)$rule['threshold_qty'],
        'max_wait_hours'=>(int)$rule['max_wait_hours'],
        'oldest_wait_hours'=>$oldestHours,
        'threshold_remaining'=>max(0, (int)$rule['threshold_qty'] - $qty),
    ];
}

function bois_p5_retry_outbox(PDO $pdo, int $outboxId): array
{
    $stmt = $pdo->prepare(
        "UPDATE bois_email_outbox
         SET status='PENDING',not_before=CURRENT_TIMESTAMP,last_error=NULL
         WHERE id=? AND status IN ('RETRY','FAILED')"
    );
    $stmt->execute([$outboxId]);

    $read = $pdo->prepare(
        "SELECT id,message_key,status,attempts,not_before,last_error FROM bois_email_outbox WHERE id=?"
    );
    $read->execute([$outboxId]);
    $row = $read->fetch();
    if (!$row) throw new OutOfBoundsException('Outbox-posten finns inte.');
    return $row;
}

function bois_p5_deliver_outbox(PDO $pdo, array $config, ?callable $sender=null, int $limit=20): array
{
    $transport=(string)($config['mail_transport']??'disabled');
    $sender=bois_p17_sender($config,$transport,'supplier',$sender);
    if($sender===null)return ['transport'=>'disabled','processed'=>0,'sent'=>0,'failed'=>0];

    $rows = $pdo->query(
        "SELECT * FROM bois_email_outbox
         WHERE status IN ('PENDING','RETRY')
           AND (not_before IS NULL OR not_before<=CURRENT_TIMESTAMP)
           AND attempts<5
         ORDER BY id
         LIMIT " . max(1, min(100, $limit))
    )->fetchAll();

    $result = ['transport'=>$transport,'processed'=>0,'sent'=>0,'failed'=>0];

    foreach ($rows as $message) {
        $pdo->beginTransaction();
        try {
            $locked = $pdo->prepare("SELECT * FROM bois_email_outbox WHERE id=? FOR UPDATE");
            $locked->execute([(int)$message['id']]);
            $current = $locked->fetch();
            if (!$current || !in_array((string)$current['status'], ['PENDING','RETRY'], true)
                || (int)$current['attempts']>=5
                || (!empty($current['not_before']) && strtotime($current['not_before'].' UTC')>time())) {
                $pdo->rollBack();
                continue;
            }

            // Claim/validate before I/O; the row lock serializes concurrent workers.
            $result['processed']++;
            $ok=false;$error=null;
            try{$ok=(bool)$sender($current);if(!$ok)$error='transport_failed';}
            catch(Throwable $e){$error='transport_failed';}
            $attempts = (int)$current['attempts'] + 1;

            if ($ok) {
                $pdo->prepare(
                    "UPDATE bois_email_outbox
                     SET status='SENT',attempts=?,sent_at=CURRENT_TIMESTAMP,last_error=NULL
                     WHERE id=?"
                )->execute([$attempts,(int)$message['id']]);

                if ($current['batch_id'] !== null) {
                    $batchId = (int)$current['batch_id'];
                    $pdo->prepare(
                        "UPDATE bois_supplier_batches SET status='SENT',sent_at=CURRENT_TIMESTAMP WHERE id=?"
                    )->execute([$batchId]);
                    $pdo->prepare(
                        "UPDATE bois_order_items i
                         JOIN bois_batch_items bi ON bi.order_item_id=i.id
                         SET i.fulfillment_status='SENT_TO_SUPPLIER'
                         WHERE bi.batch_id=?"
                    )->execute([$batchId]);

                    $orderIdsStmt = $pdo->prepare(
                        "SELECT DISTINCT i.order_id
                         FROM bois_order_items i
                         JOIN bois_batch_items bi ON bi.order_item_id=i.id
                         WHERE bi.batch_id=?"
                    );
                    $orderIdsStmt->execute([$batchId]);
                    foreach ($orderIdsStmt->fetchAll() as $row) {
                        $orderId = (int)$row['order_id'];
                        $pdo->prepare(
                            "UPDATE bois_orders o SET fulfillment_status='SENT_TO_SUPPLIER'
                             WHERE o.id=?
                               AND NOT EXISTS(
                                 SELECT 1 FROM bois_order_items i
                                 WHERE i.order_id=o.id
                                   AND i.fulfillment_type='BATCH_SUPPLIER'
                                   AND i.fulfillment_status<>'SENT_TO_SUPPLIER'
                               )"
                        )->execute([$orderId]);
                    }
                }
                $result['sent']++;
            } else {
                $status = $attempts >= 5 ? 'FAILED' : 'RETRY';
                $delayMinutes = min(240, 5 * (2 ** max(0, $attempts - 1)));

                $stmt = $pdo->prepare(
                    "UPDATE bois_email_outbox
                     SET status=?,attempts=?,last_error=?,
                         not_before=DATE_ADD(CURRENT_TIMESTAMP, INTERVAL ? MINUTE)
                     WHERE id=?"
                );
                $stmt->execute([
                    $status,$attempts,substr((string)$error,0,2000),$delayMinutes,(int)$message['id']
                ]);
                $result['failed']++;
                bois_p15_error('outbox_failed');
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    return $result;
}
