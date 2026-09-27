<?php
declare(strict_types=1);

require __DIR__ . '/p3_db.php';
require __DIR__ . '/p5_batch.php';
require __DIR__ . '/p4_membership.php';
require __DIR__ . '/p6_payment.php';

try {
    $config=bois_p3_load_config();
    $pdo=bois_p3_pdo($config);
    bois_p3_apply_schema($pdo);
    bois_p3_seed_catalog($pdo);
    bois_p5_apply_schema($pdo);
    bois_p4_apply_schema($pdo);
    bois_p6_apply_schema($pdo);

    echo "P6_MIGRATION: pass\n";
    echo "PAYMENT_PROVIDER: ".bois_p6_provider($config)."\n";
    echo "PAYMENT_ENABLED: ".(bois_p6_payment_enabled($config)?'yes':'no')."\n";
} catch(Throwable $e) {
    fwrite(STDERR,"P6_MIGRATION: fail\n");
    fwrite(STDERR,$e->getMessage()."\n");
    exit(1);
}
