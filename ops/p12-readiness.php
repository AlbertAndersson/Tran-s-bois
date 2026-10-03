<?php
declare(strict_types=1);
require_once __DIR__.'/p12-common.php';
try{
    $config=bois_p12_load($argv[1]??'');
    $pdo=bois_p3_pdo($config);
    $pdo->exec('SET SESSION TRANSACTION READ ONLY');
    $pdo->beginTransaction();
    try{$result=bois_p12_readiness($pdo,$config);}finally{$pdo->rollBack();}
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
    exit($result['ready_for_closed_verification']?0:1);
}catch(Throwable $e){fwrite(STDERR,"P12_READINESS: blocked\nSECRETS_EXPOSED: no\n");exit(1);}
