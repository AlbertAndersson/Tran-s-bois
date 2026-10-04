<?php
declare(strict_types=1);

require dirname(__DIR__) . '/server/p3_db.php';

$config = [
    'mode' => 'test',
    'admin_token' => 'test-admin-token',
    'allowed_origins' => [],
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

$catalog = bois_p3_catalog($pdo, false);
if (count($catalog) !== 3) {
    throw new RuntimeException('Expected exactly three public P3 products.');
}

$allCatalog = bois_p3_catalog($pdo, true);
if (count($allCatalog) < 10) {
    throw new RuntimeException('Expected public plus hidden 2027 catalog.');
}

$membershipOrder = bois_p3_create_order($pdo, [
    'customer' => ['name'=>'Test Köpare','email'=>'buyer@example.invalid','phone'=>'070-000 00 00'],
    'items' => [
        ['sku'=>'MEM-ADULT','quantity'=>1,'metadata'=>['member_name'=>'Test Medlem']],
        ['sku'=>'NW-GYM-ANNUAL','quantity'=>1,'metadata'=>[]],
    ],
    'existing_member' => false,
    'consent' => true,
    'website' => '',
    'idempotency_key' => 'p3-membership-gym',
]);

if ($membershipOrder['total_ore'] !== 300000) {
    throw new RuntimeException('Membership + gym total is wrong.');
}
if ($membershipOrder['payment_status'] !== 'NOT_ENABLED') {
    throw new RuntimeException('Payment must stay disabled in P3.');
}

$membershipAgain = bois_p3_create_order($pdo, [
    'customer' => ['name'=>'Test Köpare','email'=>'buyer@example.invalid','phone'=>'070-000 00 00'],
    'items' => [
        ['sku'=>'MEM-ADULT','quantity'=>1,'metadata'=>['member_name'=>'Test Medlem']],
        ['sku'=>'NW-GYM-ANNUAL','quantity'=>1,'metadata'=>[]],
    ],
    'existing_member' => false,
    'consent' => true,
    'website' => '',
    'idempotency_key' => 'p3-membership-gym',
]);
if ($membershipAgain['public_id'] !== $membershipOrder['public_id']) {
    throw new RuntimeException('Idempotency failed.');
}

try {
    bois_p3_create_order($pdo, [
        'customer' => ['name'=>'Gym Only','email'=>'gym@example.invalid'],
        'items' => [['sku'=>'NW-GYM-ANNUAL','quantity'=>1,'metadata'=>[]]],
        'existing_member' => false,
        'consent' => true,
        'website' => '',
        'idempotency_key' => 'p3-invalid-gym',
    ]);
    throw new RuntimeException('Gym-only order without membership was accepted.');
} catch (InvalidArgumentException) {
}

$existingMemberGym = bois_p3_create_order($pdo, [
    'customer' => ['name'=>'Befintlig Medlem','email'=>'existing@example.invalid'],
    'items' => [['sku'=>'NW-GYM-ANNUAL','quantity'=>1,'metadata'=>[]]],
    'existing_member' => true,
    'consent' => true,
    'website' => '',
    'idempotency_key' => 'p3-existing-member-gym',
]);
if ($existingMemberGym['total_ore'] !== 265000) {
    throw new RuntimeException('Existing-member gym total is wrong.');
}

$matchKit = bois_p3_create_order($pdo, [
    'customer' => ['name'=>'Match Förälder','email'=>'kit@example.invalid','phone'=>'070-000 00 01'],
    'items' => [[
        'sku'=>'MATCHKIT-STAGING',
        'quantity'=>1,
        'metadata'=>[
            'team'=>'P13',
            'player_name'=>'Test Spelare',
            'number'=>'17',
            'shirt_size'=>'140',
            'name_print'=>true,
            'number_print'=>true,
        ],
    ]],
    'consent' => true,
    'website' => '',
    'idempotency_key' => 'p3-matchkit',
]);

if ($matchKit['total_ore'] !== 99800) {
    throw new RuntimeException('Match kit total is wrong.');
}

$public = bois_p3_public_order($pdo, $matchKit['public_id'], $matchKit['public_token']);
if (($public['items'][0]['fulfillment_type'] ?? '') !== 'BATCH_SUPPLIER') {
    throw new RuntimeException('Match kit fulfillment rule is wrong.');
}
if (($public['items'][0]['metadata']['team'] ?? '') !== 'P13') {
    throw new RuntimeException('Match kit team metadata was not persisted.');
}
if (array_key_exists('shorts_size',$public['items'][0]['metadata'] ?? [])) {
    throw new RuntimeException('Match kit must not persist shorts metadata.');
}

$stats = bois_p3_stats($pdo);
if ($stats['bois_orders'] !== 3) {
    throw new RuntimeException('Expected three unique test orders.');
}
if ($stats['bois_memberships'] !== 1) {
    throw new RuntimeException('Expected one pending membership row.');
}
if ($stats['bois_supplier_batches'] !== 0 || $stats['bois_email_outbox'] !== 0) {
    throw new RuntimeException('P3 must not send or batch before payment/fulfillment phases.');
}

// Two gym reservations already exist above. Fill the remaining annual quota
// and prove that the 21st card is rejected server-side.
for ($i=3; $i<=20; $i++) {
    bois_p3_create_order($pdo, [
        'customer' => ['name'=>'Kvottest '.$i,'email'=>'quota'.$i.'@example.invalid'],
        'items' => [['sku'=>'NW-GYM-ANNUAL','quantity'=>1,'metadata'=>[]]],
        'existing_member' => true,
        'consent' => true,
        'website' => '',
        'idempotency_key' => 'p3-gym-quota-'.$i,
    ]);
}
$quota = bois_p3_gym_quota_status($pdo);
if (($quota['limit'] ?? 0) !== 20 || ($quota['remaining'] ?? -1) !== 0 || ($quota['sold_out'] ?? false) !== true) {
    throw new RuntimeException('Annual Nordic quota did not reach sold-out state.');
}
$catalogAfterQuota = bois_p3_catalog($pdo,false);
$gymAfterQuota = null;
foreach ($catalogAfterQuota as $product) {
    if (($product['product_key'] ?? '') === 'nordic-gym') $gymAfterQuota = $product;
}
if (!$gymAfterQuota || ($gymAfterQuota['is_orderable'] ?? true) !== false || ($gymAfterQuota['availability']['remaining'] ?? -1) !== 0) {
    throw new RuntimeException('Sold-out Nordic product must be non-orderable in catalog.');
}
try {
    bois_p3_create_order($pdo, [
        'customer' => ['name'=>'Kvottest 21','email'=>'quota21@example.invalid'],
        'items' => [['sku'=>'NW-GYM-ANNUAL','quantity'=>1,'metadata'=>[]]],
        'existing_member' => true,
        'consent' => true,
        'website' => '',
        'idempotency_key' => 'p3-gym-quota-21',
    ]);
    throw new RuntimeException('21st annual Nordic card was accepted.');
} catch (DomainException $e) {
    if (!str_contains($e->getMessage(),'slutsålda')) throw $e;
}

echo "P3_SMOKE: pass\n";
echo "PUBLIC_PRODUCTS: 3\n";
echo "HIDDEN_2027_PRODUCTS: ".(count($allCatalog)-count($catalog))."\n";
echo "MEMBERSHIP_GYM_TOTAL: ".$membershipOrder['total_ore']."\n";
echo "MATCHKIT_FULFILLMENT: BATCH_SUPPLIER\n";
echo "MATCHKIT_SHORTS: no\n";
echo "NORDIC_ANNUAL_LIMIT: 20\n";
echo "NORDIC_SOLD_OUT_GATE: pass\n";
echo "PAYMENT_ENABLED: no\n";
echo "EMAIL_OUTBOX: 0\n";
