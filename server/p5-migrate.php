<?php
declare(strict_types=1);

require __DIR__ . '/p3_db.php';
require __DIR__ . '/p5_batch.php';

try {
    $config = bois_p3_load_config();
    $pdo = bois_p3_pdo($config);
    bois_p3_apply_schema($pdo);
    bois_p3_seed_catalog($pdo);
    bois_p5_apply_schema($pdo);

    $rule = bois_p5_matchkit_rule($pdo);
    echo "P5_MIGRATION: pass\n";
    echo "MATCHKIT_BATCH_ENABLED: ".((bool)$rule['enabled'] ? 'yes' : 'no')."\n";
    echo "THRESHOLD_QTY: ".(int)$rule['threshold_qty']."\n";
    echo "MAX_WAIT_HOURS: ".(int)$rule['max_wait_hours']."\n";
    echo "MAIL_TRANSPORT: ".($config['mail_transport'] ?? 'disabled')."\n";
} catch (Throwable $e) {
    fwrite(STDERR, "P5_MIGRATION: fail\n");
    exit(1);
}
