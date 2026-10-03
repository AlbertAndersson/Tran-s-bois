<?php
declare(strict_types=1);
require_once __DIR__.'/p12-common.php';
try{
    $candidate=bois_p12_load($argv[1]??'');
    $path=$argv[2]??'';
    if($path===''||!is_file($path)||(fileperms($path)&0077)!==0) throw new RuntimeException('Private approval config missing.');
    $approved=require $path;
    if(!is_array($approved)) throw new RuntimeException('Invalid proposed config.');
    $checks=bois_p12_launch_checks($candidate,$approved);
    // Fail before DB/network when secrets or decisions are missing.
    bois_production_require_checks($checks);
    $pdo=bois_p3_pdo($candidate);
    $pdo->exec('SET SESSION TRANSACTION READ ONLY');$pdo->beginTransaction();
    try{bois_production_require_checks(bois_p12_readiness($pdo,$candidate)['checks']);}finally{$pdo->rollBack();}
    echo "P12_LAUNCH_PREFLIGHT: pass\nCONFIG_INSTALLED: no\nSTRIPE_API_CALLED: no\nLAUNCH_ACTIVATED: no\n";
}catch(Throwable $e){
    // Only validated check names, never exception text or submitted values.
    $missing=isset($checks)?array_keys(array_filter($checks,fn($ok)=>$ok!==true)):['private_config'];
    echo json_encode(['launch_ready'=>false,'missing_checks'=>$missing],JSON_THROW_ON_ERROR)."\n";exit(1);
}
