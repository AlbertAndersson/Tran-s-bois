<?php
declare(strict_types=1);
if(getenv('BOIS_P18_DISPOSABLE')!=='YES')throw new RuntimeException('Disposable CI only.');
require_once dirname(__DIR__).'/server/p3_db.php';
$config=['mode'=>'test','admin_token'=>'p18-race','db'=>[
    'host'=>'127.0.0.1','port'=>3306,'database'=>'bois_p18_race','user'=>'root','password'=>'root']];
function race_order(string $key): array{return ['customer'=>['name'=>'P18 Race','email'=>'race@example.invalid'],
    'items'=>[['sku'=>'NW-GYM-ANNUAL','quantity'=>1,'metadata'=>[]]],'existing_member'=>true,
    'consent'=>true,'website'=>'','idempotency_key'=>$key];}
if(($argv[1]??'')==='child'){
    $pdo=bois_p3_pdo($config);file_put_contents($argv[2].'.'.$argv[3].'.ready','ready');
    $deadline=microtime(true)+10;
    while(!file_exists($argv[2])){if(microtime(true)>$deadline)throw new RuntimeException('Race gate timeout.');usleep(10000);}
    try{bois_p3_create_order($pdo,race_order('race-'.$argv[3]));echo 'accepted';}
    catch(DomainException){echo 'conflict';}
    exit;
}
$pdo=bois_p3_pdo($config);bois_p3_apply_schema($pdo);bois_p3_seed_catalog($pdo);
for($i=0;$i<19;$i++)bois_p3_create_order($pdo,race_order('seed-'.$i));
if(bois_p3_gym_quota_status($pdo)['remaining']!==1)throw new RuntimeException('Final-card fixture failed.');
$gate=sys_get_temp_dir().'/p18-race-'.bin2hex(random_bytes(10));$children=[];
try{
    foreach(['a','b'] as $id){
        $proc=proc_open([PHP_BINARY,__FILE__,'child',$gate,$id],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($proc))throw new RuntimeException('Child unavailable.');$children[]=[$proc,$pipes];
    }
    $deadline=microtime(true)+10;
    while(!file_exists($gate.'.a.ready')||!file_exists($gate.'.b.ready')){
        if(microtime(true)>$deadline)throw new RuntimeException('Both processes did not reach gate.');usleep(10000);
    }
    file_put_contents($gate,'go');$results=[];
    foreach($children as [$proc,$pipes]){
        $results[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);if(proc_close($proc)!==0)throw new RuntimeException('Race child failed: '.$err);
    }
    $children=[];sort($results);
    if($results!==['accepted','conflict'])throw new RuntimeException('Race admitted wrong number of orders.');
    if((int)$pdo->query('SELECT COUNT(*) FROM bois_orders')->fetchColumn()!==20||bois_p3_gym_quota_status($pdo)['remaining']!==0)
        throw new RuntimeException('Quota overflow.');
    // Expired unpaid reservations become available; paid cards remain allocated.
    $pdo->exec("UPDATE bois_orders SET created_at=UTC_TIMESTAMP()-INTERVAL 31 MINUTE");
    if(bois_p3_gym_quota_status($pdo)['remaining']!==20)throw new RuntimeException('Expired reservation not released.');
    $pdo->exec("UPDATE bois_orders SET payment_status='PAID'");
    if(bois_p3_gym_quota_status($pdo)['remaining']!==0)throw new RuntimeException('Paid allocation expired.');
    echo "P18_TWO_PROCESS_FINAL_CARD_AND_EXPIRY: pass\n";
}finally{
    foreach($children as [$proc,$pipes]){proc_terminate($proc);foreach($pipes as $p)fclose($p);proc_close($proc);}
    foreach([$gate,$gate.'.a.ready',$gate.'.b.ready'] as $p)if(file_exists($p))unlink($p);
}
