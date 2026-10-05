<?php
declare(strict_types=1);
// Real independent processes exercise the same filesystem lock as Apache/FPM.
if(($argv[1]??'')==='--hold'){
    $h=fopen($argv[2],'ab');if(!$h||!flock($h,LOCK_EX))exit(2);
    touch($argv[3]);usleep((int)$argv[4]);flock($h,LOCK_UN);fclose($h);exit;
}
require dirname(__DIR__).'/server/p15_observability.php';
$dir=sys_get_temp_dir().'/bois-p18-log-'.bin2hex(random_bytes(10));
mkdir($dir,0700);mkdir($dir.'/public',0700);mkdir($dir.'/logs',0700);
$GLOBALS['bois_p15_config']=['observability'=>['enabled'=>true,'log_dir'=>$dir.'/logs','public_root'=>$dir.'/public']];
$log=$dir.'/logs/operations-'.gmdate('Y-m-d').'.jsonl';
bois_p15_log('request_started');
function competing_append(string $log,string $ready,int $duration,bool $shouldSucceed):void {
    $p=proc_open([PHP_BINARY,__FILE__,'--hold',$log,$ready,(string)$duration],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($p))throw new RuntimeException('Independent locker unavailable');
    try{
        $limit=microtime(true)+5;
        while(!is_file($ready)){clearstatcache(true,$ready);if(microtime(true)>$limit)throw new RuntimeException('Locker not ready');usleep(1000);}
        $accepted=true;
        try{bois_p15_log('request_completed',200);}catch(RuntimeException){$accepted=false;}
        if($accepted!==$shouldSucceed)throw new RuntimeException('Wrong concurrent/stuck log behavior');
    }finally{foreach($pipes as $pipe)fclose($pipe);$exit=proc_close($p);}
    if($exit!==0)throw new RuntimeException('Locker failed');
}
try{
    competing_append($log,$dir.'/short-ready',80000,true);
    competing_append($log,$dir.'/stuck-ready',600000,false);
    $rows=array_map(fn($s)=>json_decode($s,true,512,JSON_THROW_ON_ERROR),file($log,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES));
    if(count($rows)!==2||$rows[1]['status']!==200)throw new RuntimeException('Append completeness failed');
    foreach($rows as $row)if(array_keys($row)!==['time','request','component','event','status','duration_ms'])throw new RuntimeException('Log allowlist changed');
    echo "P18_CONCURRENT_APPEND_AND_STUCK_LOG_FAIL_CLOSED: pass\n";
}finally{
    foreach([$log,$dir.'/short-ready',$dir.'/stuck-ready'] as $p)if(is_file($p))unlink($p);
    rmdir($dir.'/logs');rmdir($dir.'/public');rmdir($dir);
}
