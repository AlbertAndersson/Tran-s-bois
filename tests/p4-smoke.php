<?php
declare(strict_types=1);

require dirname(__DIR__) . '/server/p3_db.php';
require dirname(__DIR__) . '/server/p5_batch.php';
require dirname(__DIR__) . '/server/p4_membership.php';

$config = [
    'mode' => 'test',
    'admin_token' => 'test-admin',
    'allowed_origins' => [],
    'mail_transport' => 'disabled',
    'membership_validity_days' => 365,
    'db' => [
        'host' => getenv('BOIS_P3_TEST_DB_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('BOIS_P3_TEST_DB_PORT') ?: 3306),
        'database' => getenv('BOIS_P3_TEST_DB_NAME') ?: 'bois_test',
        'user' => getenv('BOIS_P3_TEST_DB_USER') ?: 'root',
        'password' => getenv('BOIS_P3_TEST_DB_PASSWORD') ?: 'root',
    ],
];

$pdo = bois_p3_pdo($config);
bois_p3_apply_schema($pdo);
bois_p3_seed_catalog($pdo);
bois_p5_apply_schema($pdo);
bois_p4_apply_schema($pdo);

$new = bois_p3_create_order($pdo, [
    'customer' => ['name' => 'Ny Medlem', 'email' => 'new@example.invalid', 'phone' => '070-100 00 00'],
    'items' => [
        ['sku' => 'MEM-ADULT', 'quantity' => 1, 'metadata' => ['member_name' => 'Ny Medlem']],
        ['sku' => 'NW-GYM-ANNUAL', 'quantity' => 1, 'metadata' => []],
    ],
    'existing_member' => false,
    'consent' => true,
    'website' => '',
    'idempotency_key' => 'p4-new-member',
]);
bois_p4_register_order($pdo, $new['public_id']);

$before = bois_p4_admin_entitlements($pdo);
if (($before[0]['status'] ?? '') !== 'PENDING_PAYMENT') throw new RuntimeException('Benefit should start pending payment.');

bois_p5_mark_order_paid($pdo, $config, $new['public_id']);
$p4 = bois_p4_apply_paid_order($pdo, $config, $new['public_id']);
if (count($p4['members']) !== 1) throw new RuntimeException('Membership was not activated.');
if (($p4['benefits'][0]['status'] ?? '') !== 'ELIGIBLE') throw new RuntimeException('New member gym benefit should be eligible.');

$stats = bois_p4_stats($pdo);
if ($stats['active_members'] !== 1 || $stats['eligible_gym'] !== 1) throw new RuntimeException('P4 stats wrong after new member.');

$public = bois_p4_public_status_for_order($pdo, $new['public_id']);
if (($public['memberships'][0]['status'] ?? '') !== 'ACTIVE') throw new RuntimeException('Public membership not active.');
if (($public['benefits'][0]['status'] ?? '') !== 'ELIGIBLE') throw new RuntimeException('Public benefit not eligible.');

$existing = bois_p3_create_order($pdo, [
    'customer' => ['name' => 'Befintlig Medlem', 'email' => 'existing@example.invalid', 'phone' => '070-200 00 00'],
    'items' => [['sku' => 'NW-GYM-ANNUAL', 'quantity' => 1, 'metadata' => []]],
    'existing_member' => true,
    'consent' => true,
    'website' => '',
    'idempotency_key' => 'p4-existing-member',
]);
bois_p4_register_order($pdo, $existing['public_id']);
bois_p5_mark_order_paid($pdo, $config, $existing['public_id']);
$p4Existing = bois_p4_apply_paid_order($pdo, $config, $existing['public_id']);
if (($p4Existing['benefits'][0]['status'] ?? '') !== 'PENDING_MEMBER_VERIFICATION') throw new RuntimeException('Existing member should require verification.');

$verified = bois_p4_verify_existing_member($pdo, $config, $existing['public_id'], 'Befintlig Medlem', 'adult', 'TEST-123');
if (($verified['status'] ?? '') !== 'ELIGIBLE') throw new RuntimeException('Existing member verification failed.');

$ents = bois_p4_admin_entitlements($pdo);
$target = null;
foreach ($ents as $ent) if ($ent['order_public_id'] === $existing['public_id']) $target = $ent;
if (!$target) throw new RuntimeException('Verified entitlement missing.');
$id = (int)$target['id'];

$s1 = bois_p4_transition_entitlement($pdo, $id, 'SENT_TO_PARTNER', 'NW-TEST-001');
if ($s1['status'] !== 'SENT_TO_PARTNER') throw new RuntimeException('Send-to-partner transition failed.');
$s2 = bois_p4_transition_entitlement($pdo, $id, 'READY_FOR_PICKUP');
if ($s2['status'] !== 'READY_FOR_PICKUP') throw new RuntimeException('Ready-for-pickup transition failed.');
$s3 = bois_p4_transition_entitlement($pdo, $id, 'ACTIVATED');
if ($s3['status'] !== 'ACTIVATED') throw new RuntimeException('Activated transition failed.');

$csv = bois_p4_export_eligible_csv($pdo);
if (!str_contains($csv, 'new@example.invalid')) throw new RuntimeException('Nordic export missing eligible row.');
if (stripos($csv, 'personnummer') !== false) throw new RuntimeException('Nordic export must not contain personnummer column.');

$members = bois_p4_admin_members($pdo);
if (count($members) !== 2) throw new RuntimeException('Expected two active member records.');

$unpaid = bois_p3_create_order($pdo, [
    'customer' => ['name' => 'Obetald', 'email' => 'unpaid@example.invalid'],
    'items' => [['sku' => 'MEM-YOUTH', 'quantity' => 1, 'metadata' => ['member_name' => 'Obetald']]],
    'consent' => true,
    'website' => '',
    'idempotency_key' => 'p4-unpaid',
]);
bois_p4_register_order($pdo, $unpaid['public_id']);
$stats = bois_p4_stats($pdo);
if ($stats['active_members'] !== 2) throw new RuntimeException('Unpaid membership became active.');

echo "P4_SMOKE: pass\n";
echo "NEW_MEMBER_AUTO_ELIGIBLE: pass\n";
echo "EXISTING_MEMBER_MANUAL_VERIFY: pass\n";
echo "NORDIC_STATUS_FLOW: pass\n";
echo "NORDIC_EXPORT_NO_PERSONNUMMER: pass\n";
echo "PAYMENT_GATE: pass\n";
