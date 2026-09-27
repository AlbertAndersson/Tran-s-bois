<?php
declare(strict_types=1);
require __DIR__.'/p3_db.php';
require __DIR__.'/p7_assortment.php';
try{
    $config=bois_p3_load_config();
    $pdo=bois_p3_pdo($config);
    bois_p3_apply_schema($pdo);
    bois_p7_apply_schema($pdo);
    bois_p7_seed_assortment($pdo);
    echo "P7_MIGRATION: pass\n";
    echo "P7_PRODUCTS: ".count(bois_p7_admin_assortment($pdo))."\n";
}catch(Throwable $e){
    fwrite(STDERR,"P7_MIGRATION: fail\n".$e->getMessage()."\n");
    exit(1);
}
