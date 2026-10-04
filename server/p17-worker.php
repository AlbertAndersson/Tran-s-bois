<?php
declare(strict_types=1);
require_once __DIR__.'/security.php';
require_once __DIR__.'/p6_payment.php';
try{
    if(PHP_SAPI!=='cli')throw new RuntimeException('Private worker required.');
    $config=bois_p3_load_config();
    bois_p15_begin($config,'worker');
    if(($config['mode']??'')==='production'&&($config['production_launch_enabled']??false)!==true)throw new RuntimeException('Production worker is closed.');
    $pdo=bois_p3_pdo($config);
    // No schema bootstrap or automatic supplier batch creation in this mail-only worker.
    foreach(['supplier'=>'bois_p5_deliver_outbox','payment'=>'bois_p6_deliver_outbox'] as $queue=>$worker){
        $r=$worker($pdo,$config);
        echo strtoupper($queue).'_MAIL: '.json_encode($r,JSON_THROW_ON_ERROR)."\n";
    }
}catch(Throwable $e){bois_p15_error();fwrite(STDERR,"P17_WORKER: fail\n");exit(1);}
