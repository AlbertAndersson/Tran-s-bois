<?php
declare(strict_types=1);
require_once __DIR__ . "/security.php";

require __DIR__ . '/p3_db.php';
require __DIR__ . '/p4_membership.php';

try{
    $config=bois_p3_load_config();
    $pdo=bois_p3_pdo($config);
    bois_p3_apply_schema($pdo);
    bois_p3_seed_catalog($pdo);
    bois_p4_apply_schema($pdo);

    echo "P4_MIGRATION: pass\n";
    echo "MEMBERSHIP_VALIDITY_DAYS: ".bois_p4_membership_validity_days($config)."\n";
    echo "NORDIC_INTEGRATION_MODE: manual_workflow\n";
    echo "PERSONNUMMER_REQUIRED: no\n";
} catch(Throwable $e){
    fwrite(STDERR,"P4_MIGRATION: fail\n");
    exit(1);
}
