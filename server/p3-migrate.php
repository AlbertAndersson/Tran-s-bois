<?php
declare(strict_types=1);
require_once __DIR__ . "/security.php";
require __DIR__ . '/p3_db.php';

try {
    $config=bois_p3_load_config();
    $pdo=bois_p3_pdo($config);
    bois_p3_apply_schema($pdo);
    bois_p3_seed_catalog($pdo);
    $stats=bois_p3_stats($pdo);

    echo "P3_MIGRATION: pass\n";
    echo "STORAGE_DRIVER: mysql\n";
    echo "PRODUCTS: ".$stats['bois_products']."\n";
    echo "VARIANTS: ".$stats['bois_variants']."\n";
    echo "PAYMENT_ENABLED: no\n";
} catch (Throwable $e) {
    fwrite(STDERR,"P3_MIGRATION: fail\n");
    exit(1);
}
