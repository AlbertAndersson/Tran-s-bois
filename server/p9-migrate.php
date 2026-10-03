<?php
declare(strict_types=1);
require_once __DIR__ . "/security.php";

require_once __DIR__ . '/p3_db.php';
require_once __DIR__ . '/p5_batch.php';
require_once __DIR__ . '/p4_membership.php';
require_once __DIR__ . '/p6_payment.php';
require_once __DIR__ . '/p7_assortment.php';
require_once __DIR__ . '/p8_stripe.php';
require_once __DIR__ . '/p9_sales.php';

$config=bois_p3_load_config();
$pdo=bois_p3_pdo($config);
bois_p3_apply_schema($pdo);
bois_p5_apply_schema($pdo);
bois_p4_apply_schema($pdo);
bois_p6_apply_schema($pdo);
bois_p7_apply_schema($pdo);
bois_p8_apply_schema($pdo);
bois_p9_apply_schema($pdo);

echo "P9_MIGRATION: ready\n";
