<?php
declare(strict_types=1);
require_once __DIR__ . "/security.php";

require_once __DIR__ . '/p3_db.php';

function bois_p4_apply_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS bois_members (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            member_uuid CHAR(36) NOT NULL UNIQUE,
            customer_id BIGINT UNSIGNED NOT NULL,
            source_membership_id BIGINT UNSIGNED NULL,
            member_name VARCHAR(160) NOT NULL,
            membership_type VARCHAR(40) NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'PENDING',
            valid_from DATE NULL,
            valid_to DATE NULL,
            source VARCHAR(40) NOT NULL,
            external_member_ref VARCHAR(120) NULL,
            verified_at DATETIME NULL,
            verified_by VARCHAR(120) NULL,
            notes TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_bois_members_status (status, valid_to),
            INDEX idx_bois_members_customer (customer_id),
            INDEX idx_bois_members_source_membership (source_membership_id),
            CONSTRAINT fk_bois_members_customer FOREIGN KEY (customer_id) REFERENCES bois_customers(id),
            CONSTRAINT fk_bois_members_source_membership FOREIGN KEY (source_membership_id) REFERENCES bois_memberships(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS bois_benefit_entitlements (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entitlement_uuid CHAR(36) NOT NULL UNIQUE,
            order_item_id BIGINT UNSIGNED NOT NULL UNIQUE,
            customer_id BIGINT UNSIGNED NOT NULL,
            member_id BIGINT UNSIGNED NULL,
            benefit_key VARCHAR(80) NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'PENDING_PAYMENT',
            partner VARCHAR(80) NOT NULL DEFAULT 'NORDIC_WELLNESS',
            partner_ref VARCHAR(160) NULL,
            eligible_at DATETIME NULL,
            sent_to_partner_at DATETIME NULL,
            ready_for_pickup_at DATETIME NULL,
            activated_at DATETIME NULL,
            rejected_at DATETIME NULL,
            notes TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_bois_entitlements_status (benefit_key, status),
            INDEX idx_bois_entitlements_customer (customer_id),
            INDEX idx_bois_entitlements_member (member_id),
            CONSTRAINT fk_bois_entitlements_item FOREIGN KEY (order_item_id) REFERENCES bois_order_items(id),
            CONSTRAINT fk_bois_entitlements_customer FOREIGN KEY (customer_id) REFERENCES bois_customers(id),
            CONSTRAINT fk_bois_entitlements_member FOREIGN KEY (member_id) REFERENCES bois_members(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $stmt=$pdo->prepare("INSERT IGNORE INTO bois_schema_migrations(version) VALUES (?)");
    $stmt->execute(['20260927_p4_membership_nordic_v1']);
}

function bois_p4_membership_validity_days(array $config): int
{
    return max(1, min(730, (int)($config['membership_validity_days'] ?? 365)));
}

function bois_p4_register_order(PDO $pdo, string $publicId): void
{
    $stmt=$pdo->prepare(
        "SELECT o.id order_id,o.customer_id,o.metadata_json,
                i.id order_item_id,i.sku,i.fulfillment_type
         FROM bois_orders o
         JOIN bois_order_items i ON i.order_id=o.id
         WHERE o.public_id=?"
    );
    $stmt->execute([$publicId]);
    $rows=$stmt->fetchAll();
    if(!$rows) throw new OutOfBoundsException('Ordern finns inte.');

    $insert=$pdo->prepare(
        "INSERT IGNORE INTO bois_benefit_entitlements(
            entitlement_uuid,order_item_id,customer_id,benefit_key,status,partner
         ) VALUES(?,?,?,'NORDIC_WELLNESS_ANNUAL','PENDING_PAYMENT','NORDIC_WELLNESS')"
    );

    foreach($rows as $row){
        if((string)$row['sku']==='NW-GYM-ANNUAL'){
            $insert->execute([
                bois_p3_uuid(),
                (int)$row['order_item_id'],
                (int)$row['customer_id']
            ]);
        }
    }
}

function bois_p4_customer_email(PDO $pdo, int $customerId): string
{
    $stmt=$pdo->prepare("SELECT email FROM bois_customers WHERE id=?");
    $stmt->execute([$customerId]);
    return strtolower((string)$stmt->fetchColumn());
}

function bois_p4_find_active_member_by_email(PDO $pdo, string $email): ?array
{
    $stmt=$pdo->prepare(
        "SELECT m.*,c.email,c.phone
         FROM bois_members m
         JOIN bois_customers c ON c.id=m.customer_id
         WHERE LOWER(c.email)=LOWER(?)
           AND m.status='ACTIVE'
           AND (m.valid_to IS NULL OR m.valid_to>=CURRENT_DATE)
         ORDER BY m.valid_to DESC,m.id DESC
         LIMIT 1"
    );
    $stmt->execute([$email]);
    $row=$stmt->fetch();
    return $row ?: null;
}

function bois_p4_activate_paid_memberships(PDO $pdo, array $config, int $orderId): array
{
    $days=bois_p4_membership_validity_days($config);
    $today=new DateTimeImmutable('today',new DateTimeZone('Europe/Stockholm'));
    $validFrom=$today->format('Y-m-d');
    $validTo=$today->modify('+'.$days.' days')->format('Y-m-d');

    $stmt=$pdo->prepare(
        "SELECT m.id membership_id,m.customer_id,m.member_name,m.membership_type,m.status,c.email
         FROM bois_memberships m
         JOIN bois_order_items i ON i.id=m.order_item_id
         JOIN bois_customers c ON c.id=m.customer_id
         WHERE i.order_id=?"
    );
    $stmt->execute([$orderId]);
    $rows=$stmt->fetchAll();
    $activated=[];

    foreach($rows as $row){
        $membershipId=(int)$row['membership_id'];

        if ((string)$row['status'] === 'ACTIVE') {
            $existingApplied=$pdo->prepare(
                "SELECT id FROM bois_members WHERE source_membership_id=? LIMIT 1"
            );
            $existingApplied->execute([$membershipId]);
            $existingMemberId=(int)$existingApplied->fetchColumn();
            if($existingMemberId>0){
                $activated[]=['membership_id'=>$membershipId,'member_id'=>$existingMemberId];
                continue;
            }
        }

        $pdo->prepare(
            "UPDATE bois_memberships
             SET status='ACTIVE',
                 valid_from=COALESCE(valid_from,?),
                 valid_to=COALESCE(valid_to,?)
             WHERE id=?"
        )->execute([$validFrom,$validTo,$membershipId]);

        $existing=$pdo->prepare("SELECT id,member_uuid,valid_to FROM bois_members WHERE source_membership_id=? LIMIT 1");
        $existing->execute([$membershipId]);
        $member=$existing->fetch();

        if(!$member){
            $member=bois_p4_find_active_member_by_email($pdo,(string)$row['email']);
        }

        if($member){
            $memberId=(int)$member['id'];
            $renewBase=$today;
            if(!empty($member['valid_to'])){
                $existingEnd=new DateTimeImmutable((string)$member['valid_to'],new DateTimeZone('Europe/Stockholm'));
                if($existingEnd>$renewBase) $renewBase=$existingEnd;
            }
            $renewTo=$renewBase->modify('+'.$days.' days')->format('Y-m-d');

            $pdo->prepare(
                "UPDATE bois_members
                 SET customer_id=?,source_membership_id=?,member_name=?,membership_type=?,status='ACTIVE',
                     valid_from=COALESCE(valid_from,?),valid_to=?,
                     source='ORDER',verified_at=CURRENT_TIMESTAMP,verified_by='ORDER_PAYMENT'
                 WHERE id=?"
            )->execute([
                (int)$row['customer_id'],
                $membershipId,
                (string)$row['member_name'],
                (string)$row['membership_type'],
                $validFrom,
                $renewTo,
                $memberId
            ]);
        } else {
            $pdo->prepare(
                "INSERT INTO bois_members(
                    member_uuid,customer_id,source_membership_id,member_name,membership_type,status,
                    valid_from,valid_to,source,verified_at,verified_by
                 ) VALUES(?,?,?,?,?,'ACTIVE',?,?,'ORDER',CURRENT_TIMESTAMP,'ORDER_PAYMENT')"
            )->execute([
                bois_p3_uuid(),
                (int)$row['customer_id'],
                $membershipId,
                (string)$row['member_name'],
                (string)$row['membership_type'],
                $validFrom,
                $validTo
            ]);
            $memberId=(int)$pdo->lastInsertId();
        }

        $activated[]=['membership_id'=>$membershipId,'member_id'=>$memberId];
    }

    return $activated;
}

function bois_p4_evaluate_order_benefits(PDO $pdo, int $orderId): array
{
    $stmt=$pdo->prepare(
        "SELECT e.id,e.customer_id,e.status,c.email
         FROM bois_benefit_entitlements e
         JOIN bois_order_items i ON i.id=e.order_item_id
         JOIN bois_customers c ON c.id=e.customer_id
         WHERE i.order_id=?"
    );
    $stmt->execute([$orderId]);
    $rows=$stmt->fetchAll();
    $result=[];

    foreach($rows as $row){
        $member=bois_p4_find_active_member_by_email($pdo,(string)$row['email']);

        if($member){
            $pdo->prepare(
                "UPDATE bois_benefit_entitlements
                 SET member_id=?,status='ELIGIBLE',eligible_at=COALESCE(eligible_at,CURRENT_TIMESTAMP)
                 WHERE id=? AND status IN ('PENDING_PAYMENT','PENDING_MEMBER_VERIFICATION','ELIGIBLE')"
            )->execute([(int)$member['id'],(int)$row['id']]);

            $result[]=[
                'entitlement_id'=>(int)$row['id'],
                'status'=>'ELIGIBLE',
                'member_id'=>(int)$member['id'],
            ];
        } else {
            $pdo->prepare(
                "UPDATE bois_benefit_entitlements
                 SET status='PENDING_MEMBER_VERIFICATION'
                 WHERE id=? AND status IN ('PENDING_PAYMENT','PENDING_MEMBER_VERIFICATION')"
            )->execute([(int)$row['id']]);

            $result[]=[
                'entitlement_id'=>(int)$row['id'],
                'status'=>'PENDING_MEMBER_VERIFICATION',
                'member_id'=>null,
            ];
        }
    }

    return $result;
}

function bois_p4_apply_paid_order(PDO $pdo, array $config, string $publicId): array
{
    $stmt=$pdo->prepare("SELECT id,payment_status FROM bois_orders WHERE public_id=? LIMIT 1");
    $stmt->execute([$publicId]);
    $order=$stmt->fetch();
    if(!$order) throw new OutOfBoundsException('Ordern finns inte.');
    if((string)$order['payment_status']!=='PAID'){
        throw new InvalidArgumentException('Ordern måste vara betald innan medlemskap/förmån aktiveras.');
    }

    $pdo->beginTransaction();
    try{
        $members=bois_p4_activate_paid_memberships($pdo,$config,(int)$order['id']);
        $benefits=bois_p4_evaluate_order_benefits($pdo,(int)$order['id']);

        $pdo->prepare(
            "INSERT INTO bois_events(order_id,event_type,payload_json) VALUES(?,'P4_PAYMENT_APPLIED',?)"
        )->execute([
            (int)$order['id'],
            json_encode([
                'members_activated'=>count($members),
                'benefits_evaluated'=>count($benefits)
            ],JSON_THROW_ON_ERROR)
        ]);

        $pdo->commit();
        return ['members'=>$members,'benefits'=>$benefits];
    } catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function bois_p4_verify_existing_member(PDO $pdo, array $config, string $publicId, string $memberName, string $membershipType='adult', ?string $externalRef=null): array
{
    $stmt=$pdo->prepare(
        "SELECT o.id order_id,o.customer_id,o.payment_status,c.email
         FROM bois_orders o
         JOIN bois_customers c ON c.id=o.customer_id
         WHERE o.public_id=? LIMIT 1"
    );
    $stmt->execute([$publicId]);
    $order=$stmt->fetch();
    if(!$order) throw new OutOfBoundsException('Ordern finns inte.');
    if((string)$order['payment_status']!=='PAID') throw new InvalidArgumentException('Ordern måste vara betald.');

    $ent=$pdo->prepare(
        "SELECT e.id entitlement_id
         FROM bois_benefit_entitlements e
         JOIN bois_order_items i ON i.id=e.order_item_id
         WHERE i.order_id=? AND e.benefit_key='NORDIC_WELLNESS_ANNUAL'
         LIMIT 1"
    );
    $ent->execute([(int)$order['order_id']]);
    $entitlementId=(int)$ent->fetchColumn();
    if($entitlementId<1) throw new InvalidArgumentException('Ordern innehåller inget Nordic-gymkort.');

    $days=bois_p4_membership_validity_days($config);
    $today=new DateTimeImmutable('today',new DateTimeZone('Europe/Stockholm'));
    $validFrom=$today->format('Y-m-d');
    $validTo=$today->modify('+'.$days.' days')->format('Y-m-d');
    $existing=bois_p4_find_active_member_by_email($pdo,(string)$order['email']);

    $pdo->beginTransaction();
    try{
        if($existing){
            $memberId=(int)$existing['id'];
            $pdo->prepare(
                "UPDATE bois_members
                 SET member_name=?,membership_type=?,status='ACTIVE',
                     valid_to=GREATEST(COALESCE(valid_to,?),?),
                     external_member_ref=COALESCE(?,external_member_ref),
                     verified_at=CURRENT_TIMESTAMP,verified_by='ADMIN'
                 WHERE id=?"
            )->execute([$memberName,$membershipType,$validFrom,$validTo,$externalRef,$memberId]);
        } else {
            $pdo->prepare(
                "INSERT INTO bois_members(
                    member_uuid,customer_id,member_name,membership_type,status,valid_from,valid_to,
                    source,external_member_ref,verified_at,verified_by
                 ) VALUES(?,?,?,?, 'ACTIVE',?,?,
                    'MANUAL_VERIFY',?,CURRENT_TIMESTAMP,'ADMIN')"
            )->execute([
                bois_p3_uuid(),
                (int)$order['customer_id'],
                $memberName,
                $membershipType,
                $validFrom,
                $validTo,
                $externalRef
            ]);
            $memberId=(int)$pdo->lastInsertId();
        }

        $pdo->prepare(
            "UPDATE bois_benefit_entitlements
             SET member_id=?,status='ELIGIBLE',eligible_at=COALESCE(eligible_at,CURRENT_TIMESTAMP)
             WHERE id=?"
        )->execute([$memberId,$entitlementId]);

        $pdo->prepare(
            "INSERT INTO bois_events(order_id,event_type,payload_json)
             VALUES(?,'EXISTING_MEMBER_VERIFIED',?)"
        )->execute([
            (int)$order['order_id'],
            json_encode([
                'member_id'=>$memberId,
                'entitlement_id'=>$entitlementId
            ],JSON_THROW_ON_ERROR)
        ]);

        $pdo->commit();
    } catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    return [
        'member_id'=>$memberId,
        'entitlement_id'=>$entitlementId,
        'status'=>'ELIGIBLE',
    ];
}

function bois_p4_transition_entitlement(PDO $pdo, int $entitlementId, string $targetStatus, ?string $partnerRef=null, ?string $notes=null): array
{
    $allowed=[
        'ELIGIBLE'=>['SENT_TO_PARTNER','REJECTED'],
        'SENT_TO_PARTNER'=>['READY_FOR_PICKUP','ACTIVATED','REJECTED'],
        'READY_FOR_PICKUP'=>['ACTIVATED','REJECTED'],
        'ACTIVATED'=>[],
        'REJECTED'=>[],
        'PENDING_MEMBER_VERIFICATION'=>['ELIGIBLE','REJECTED'],
        'PENDING_PAYMENT'=>[],
    ];

    $pdo->beginTransaction();
    try{
        $stmt=$pdo->prepare(
            "SELECT e.*,i.order_id,o.public_id
             FROM bois_benefit_entitlements e
             JOIN bois_order_items i ON i.id=e.order_item_id
             JOIN bois_orders o ON o.id=i.order_id
             WHERE e.id=? FOR UPDATE"
        );
        $stmt->execute([$entitlementId]);
        $row=$stmt->fetch();
        if(!$row) throw new OutOfBoundsException('Förmånen finns inte.');

        $current=(string)$row['status'];
        if(!in_array($targetStatus,$allowed[$current]??[],true)){
            throw new InvalidArgumentException("Ogiltig statusövergång: {$current} → {$targetStatus}");
        }

        $timestamps=[
            'ELIGIBLE'=>'eligible_at',
            'SENT_TO_PARTNER'=>'sent_to_partner_at',
            'READY_FOR_PICKUP'=>'ready_for_pickup_at',
            'ACTIVATED'=>'activated_at',
            'REJECTED'=>'rejected_at',
        ];
        $column=$timestamps[$targetStatus]??null;

        $sql="UPDATE bois_benefit_entitlements SET status=?,partner_ref=COALESCE(?,partner_ref),notes=COALESCE(?,notes)";
        if($column) $sql.=",{$column}=COALESCE({$column},CURRENT_TIMESTAMP)";
        $sql.=" WHERE id=?";

        $pdo->prepare($sql)->execute([$targetStatus,$partnerRef,$notes,$entitlementId]);

        $pdo->prepare(
            "INSERT INTO bois_events(order_id,event_type,payload_json) VALUES(?,'BENEFIT_STATUS_CHANGED',?)"
        )->execute([
            (int)$row['order_id'],
            json_encode([
                'entitlement_id'=>$entitlementId,
                'from'=>$current,
                'to'=>$targetStatus,
                'partner_ref'=>$partnerRef
            ],JSON_THROW_ON_ERROR)
        ]);

        $pdo->commit();
    } catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    return bois_p4_get_entitlement($pdo,$entitlementId);
}

function bois_p4_get_entitlement(PDO $pdo, int $entitlementId): array
{
    $stmt=$pdo->prepare(
        "SELECT e.id,e.entitlement_uuid,e.benefit_key,e.status,e.partner,e.partner_ref,
                e.eligible_at,e.sent_to_partner_at,e.ready_for_pickup_at,e.activated_at,e.rejected_at,e.notes,
                o.public_id order_public_id,o.payment_status,
                c.name customer_name,c.email customer_email,c.phone customer_phone,
                m.id member_id,m.member_uuid,m.member_name,m.membership_type,m.status member_status,m.valid_from,m.valid_to
         FROM bois_benefit_entitlements e
         JOIN bois_order_items i ON i.id=e.order_item_id
         JOIN bois_orders o ON o.id=i.order_id
         JOIN bois_customers c ON c.id=e.customer_id
         LEFT JOIN bois_members m ON m.id=e.member_id
         WHERE e.id=?"
    );
    $stmt->execute([$entitlementId]);
    $row=$stmt->fetch();
    if(!$row) throw new OutOfBoundsException('Förmånen finns inte.');
    return $row;
}

function bois_p4_admin_members(PDO $pdo): array
{
    return $pdo->query(
        "SELECT m.id,m.member_uuid,m.member_name,m.membership_type,m.status,m.valid_from,m.valid_to,
                m.source,m.external_member_ref,m.verified_at,m.verified_by,
                c.name customer_name,c.email,c.phone
         FROM bois_members m
         JOIN bois_customers c ON c.id=m.customer_id
         ORDER BY m.id DESC
         LIMIT 300"
    )->fetchAll();
}

function bois_p4_admin_entitlements(PDO $pdo): array
{
    return $pdo->query(
        "SELECT e.id,e.entitlement_uuid,e.benefit_key,e.status,e.partner,e.partner_ref,
                e.eligible_at,e.sent_to_partner_at,e.ready_for_pickup_at,e.activated_at,e.rejected_at,
                o.public_id order_public_id,o.payment_status,o.created_at order_created_at,
                c.name customer_name,c.email customer_email,c.phone customer_phone,
                m.id member_id,m.member_name,m.membership_type,m.status member_status,m.valid_from,m.valid_to
         FROM bois_benefit_entitlements e
         JOIN bois_order_items i ON i.id=e.order_item_id
         JOIN bois_orders o ON o.id=i.order_id
         JOIN bois_customers c ON c.id=e.customer_id
         LEFT JOIN bois_members m ON m.id=e.member_id
         ORDER BY e.id DESC
         LIMIT 300"
    )->fetchAll();
}

function bois_p4_stats(PDO $pdo): array
{
    $scalar=function(string $sql) use($pdo): int {
        return (int)$pdo->query($sql)->fetchColumn();
    };

    return [
        'active_members'=>$scalar("SELECT COUNT(*) FROM bois_members WHERE status='ACTIVE' AND (valid_to IS NULL OR valid_to>=CURRENT_DATE)"),
        'pending_member_verification'=>$scalar("SELECT COUNT(*) FROM bois_benefit_entitlements WHERE status='PENDING_MEMBER_VERIFICATION'"),
        'eligible_gym'=>$scalar("SELECT COUNT(*) FROM bois_benefit_entitlements WHERE status='ELIGIBLE'"),
        'sent_to_nordic'=>$scalar("SELECT COUNT(*) FROM bois_benefit_entitlements WHERE status='SENT_TO_PARTNER'"),
        'ready_for_pickup'=>$scalar("SELECT COUNT(*) FROM bois_benefit_entitlements WHERE status='READY_FOR_PICKUP'"),
        'activated_gym'=>$scalar("SELECT COUNT(*) FROM bois_benefit_entitlements WHERE status='ACTIVATED'"),
    ];
}

function bois_p4_export_eligible_csv(PDO $pdo): string
{
    $stmt=$pdo->query(
        "SELECT e.id entitlement_id,o.public_id,c.name,c.email,c.phone,
                m.member_name,m.membership_type,m.valid_from,m.valid_to,e.status
         FROM bois_benefit_entitlements e
         JOIN bois_order_items i ON i.id=e.order_item_id
         JOIN bois_orders o ON o.id=i.order_id
         JOIN bois_customers c ON c.id=e.customer_id
         LEFT JOIN bois_members m ON m.id=e.member_id
         WHERE e.status IN ('ELIGIBLE','SENT_TO_PARTNER','READY_FOR_PICKUP')
         ORDER BY e.id"
    );

    $stream=fopen('php://temp','w+b');
    fwrite($stream,"\xEF\xBB\xBF");
    fputcsv($stream,[
        'Entitlement','Order','Namn','E-post','Telefon','Medlemsnamn',
        'Medlemstyp','Medlem giltig från','Medlem giltig till','Status'
    ],';');

    foreach($stmt->fetchAll() as $row){
        fputcsv($stream,[
            $row['entitlement_id'],$row['public_id'],$row['name'],$row['email'],$row['phone'],
            $row['member_name'],$row['membership_type'],$row['valid_from'],$row['valid_to'],$row['status']
        ],';');
    }

    rewind($stream);
    $csv=stream_get_contents($stream);
    fclose($stream);
    return is_string($csv)?$csv:'';
}

function bois_p4_public_status_for_order(PDO $pdo, string $publicId): array
{
    $stmt=$pdo->prepare(
        "SELECT m.member_name,m.membership_type,m.status,m.valid_from,m.valid_to
         FROM bois_memberships m
         JOIN bois_order_items i ON i.id=m.order_item_id
         JOIN bois_orders o ON o.id=i.order_id
         WHERE o.public_id=?"
    );
    $stmt->execute([$publicId]);
    $memberships=$stmt->fetchAll();

    $stmt=$pdo->prepare(
        "SELECT e.status,e.partner,e.partner_ref,e.eligible_at,e.sent_to_partner_at,
                e.ready_for_pickup_at,e.activated_at,e.rejected_at
         FROM bois_benefit_entitlements e
         JOIN bois_order_items i ON i.id=e.order_item_id
         JOIN bois_orders o ON o.id=i.order_id
         WHERE o.public_id=?"
    );
    $stmt->execute([$publicId]);
    $benefits=$stmt->fetchAll();

    return ['memberships'=>$memberships,'benefits'=>$benefits];
}
