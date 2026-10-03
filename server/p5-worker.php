<?php
declare(strict_types=1);
require_once __DIR__ . "/security.php";

require __DIR__ . '/p3_db.php';
require __DIR__ . '/p5_batch.php';

try {
    $config = bois_p3_load_config();
    bois_p15_begin($config,'worker');
    if(($config['mode']??'')==='production'&&($config['production_launch_enabled']??false)!==true)throw new RuntimeException('Production worker is closed.');
    $pdo = bois_p3_pdo($config);
    if(($config['mode']??'')!=='production')bois_p5_apply_schema($pdo);

    $batches = bois_p5_evaluate_batches($pdo, $config, false);
    $mail = bois_p5_deliver_outbox($pdo, $config);

    echo "P5_WORKER: pass\n";
    echo "BATCHES_CREATED: ".count($batches)."\n";
    echo "MAIL_TRANSPORT: ".$mail['transport']."\n";
    echo "MAIL_PROCESSED: ".$mail['processed']."\n";
    echo "MAIL_SENT: ".$mail['sent']."\n";
    echo "MAIL_FAILED: ".$mail['failed']."\n";
} catch (Throwable $e) {
    bois_p15_error();
    fwrite(STDERR, "P5_WORKER: fail\n");
    exit(1);
}
