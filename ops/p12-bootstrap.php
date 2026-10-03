<?php
declare(strict_types=1);
require_once __DIR__.'/p12-common.php';
try{
    if(($argv[2]??'')!=='BOOTSTRAP_CLOSED_BOIS_PRODUCTION') throw new RuntimeException('Explicit closed bootstrap required.');
    $config=bois_p12_load($argv[1]??'');
    bois_p12_bootstrap(bois_p3_pdo($config),$config);
    echo "P12_CLOSED_BOOTSTRAP: pass\nSTAGING_DATA_IMPORTED: no\nPRODUCTION_LAUNCH: disabled\n";
}catch(Throwable $e){fwrite(STDERR,"P12_CLOSED_BOOTSTRAP: blocked\n");exit(1);}
