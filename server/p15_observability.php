<?php
declare(strict_types=1);

// No caller-supplied strings, bodies, identifiers or exceptions enter operational logs.
function bois_p15_id(): string
{
    return $GLOBALS['bois_p15_request'] ??= bin2hex(random_bytes(12));
}
function bois_p15_component(string $action): string
{
    if(in_array($action,['payment_webhook','stripe_webhook','mock_payment_event'],true))return 'webhook';
    if(in_array($action,['checkout','checkout_status','admin_stripe_refund','admin_simulate_paid'],true))return 'payment';
    if(in_array($action,['worker','admin_run_worker','admin_run_payment_outbox','admin_retry_outbox','admin_retry_payment_outbox'],true))return 'outbox';
    if(in_array($action,['liveness','readiness','ops'],true))return 'probe';
    if(str_starts_with($action,'admin_'))return 'admin';
    return 'commerce';
}
function bois_p15_storage(array $config): string
{
    $obs=$config['observability']??[];
    $dir=$obs['log_dir']??'';$public=$obs['public_root']??'';
    if(($obs['enabled']??false)!==true||!is_string($dir)||!is_string($public)
        ||$dir===''||$public===''||!is_dir($dir)||!is_dir($public)||is_link($dir)
        ||realpath($dir)!==rtrim($dir,DIRECTORY_SEPARATOR)||realpath($public)===false
        ||(fileperms($dir)&0077)!==0)throw new RuntimeException('Operational storage unavailable.');
    $root=realpath($public);$real=realpath($dir);
    if($real===$root||str_starts_with($real,$root.DIRECTORY_SEPARATOR))throw new RuntimeException('Private operational storage required.');
    return $real;
}
function bois_p15_disk_ok(float $free,float $total,bool $critical=false): bool
{
    return $total>0&&$free>=($critical?50:256)*1024*1024&&$free/$total>=($critical?0.01:0.05);
}
function bois_p15_log(string $event,int $status=0): void
{
    if(!in_array($event,['request_started','request_completed','internal_error','fatal_error','outbox_failed','webhook_failed','ops_failed'],true))$event='internal_error';
    $component=$GLOBALS['bois_p15_component']??'commerce';
    if(!in_array($component,['commerce','admin','payment','webhook','outbox','probe'],true))$component='commerce';
    $row=['time'=>gmdate('Y-m-d\TH:i:s\Z'),'request'=>bois_p15_id(),'component'=>$component,
        'event'=>$event,'status'=>max(0,min(599,$status)),
        'duration_ms'=>max(0,min(3600000,(int)round((microtime(true)-($GLOBALS['bois_p15_start']??microtime(true)))*1000)))];
    $line=json_encode($row,JSON_THROW_ON_ERROR)."\n";
    $config=$GLOBALS['bois_p15_config']??[];
    if(($config['observability']['enabled']??false)!==true){error_log(rtrim($line));return;}
    $dir=bois_p15_storage($config);
    if(!bois_p15_disk_ok((float)@disk_free_space($dir),(float)@disk_total_space($dir),true))throw new RuntimeException('Operational disk unavailable.');
    $path=$dir.'/operations-'.gmdate('Y-m-d').'.jsonl';
    clearstatcache(true,$path);
    if(is_link($path)||(file_exists($path)&&(!is_file($path)||(fileperms($path)&0077)!==0)))throw new RuntimeException('Operational log refused.');
    $file=@fopen($path,'ab');if($file===false)throw new RuntimeException('Operational log unavailable.');
    try{
        if(!chmod($path,0600))throw new RuntimeException('Operational log unavailable.');
        // Apache serves dashboard reads concurrently. A short append by another
        // request must not turn a healthy request into 500. Keep a bounded wait
        // so an unavailable/stuck logger still refuses the request before writes.
        $deadline=hrtime(true)+200000000;
        while(!flock($file,LOCK_EX|LOCK_NB)){
            if(hrtime(true)>=$deadline)throw new RuntimeException('Operational log unavailable.');
            usleep(1000);
        }
        $stat=fstat($file);$named=lstat($path);
        if(!$stat||!$named||$stat['ino']!==$named['ino']||$stat['nlink']!==1
            ||$stat['size']+strlen($line)>10*1024*1024)throw new RuntimeException('Operational log capacity/refusal.');
        if(fwrite($file,$line)!==strlen($line)||!fflush($file))throw new RuntimeException('Operational log write failed.');
    }finally{flock($file,LOCK_UN);fclose($file);}
}
function bois_p15_emergency(): void
{
    error_log(json_encode(['event'=>'operational_log_unavailable','request'=>bois_p15_id()],JSON_THROW_ON_ERROR));
}
function bois_p15_begin(array $config,string $action): void
{
    $GLOBALS['bois_p15_config']=$config;$GLOBALS['bois_p15_component']=bois_p15_component($action);
    $GLOBALS['bois_p15_start']=microtime(true);
    if(PHP_SAPI!=='cli')header('X-Request-ID: '.bois_p15_id());
    if(!isset($GLOBALS['bois_p15_registered'])){
        $GLOBALS['bois_p15_registered']=true;
        register_shutdown_function(function(){
            $last=error_get_last();$fatal=$last&&in_array($last['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true);
            try{bois_p15_log($fatal?'fatal_error':'request_completed',$fatal?500:(PHP_SAPI==='cli'?($GLOBALS['bois_p15_cli_status']??200):(int)(http_response_code()?:200)));}
            catch(Throwable){bois_p15_emergency();}
        });
    }
    // Refuse mutations when the configured log cannot accept even the intent record.
    try{bois_p15_log('request_started');}catch(Throwable $e){bois_p15_emergency();throw $e;}
}
function bois_p15_error(string $event='internal_error'): void
{
    $GLOBALS['bois_p15_cli_status']=500;
    try{bois_p15_log($event,500);}catch(Throwable){bois_p15_emergency();}
}
function bois_p15_readiness(array $config): array
{
    $checks=['database'=>false,'schema'=>false,'storage'=>false,'queues'=>false];
    try{
        $dir=bois_p15_storage($config);
        $checks['storage']=bois_p15_disk_ok((float)disk_free_space($dir),(float)disk_total_space($dir));
    }catch(Throwable){/* Public result contains no paths or errors. */}
    try{
        $pdo=bois_p3_pdo($config);
        $pdo->exec('SET SESSION MAX_EXECUTION_TIME=2000');
        $pdo->exec('SET SESSION TRANSACTION READ ONLY');$pdo->beginTransaction();
        try{
            $checks['database']=(int)$pdo->query('SELECT 1')->fetchColumn()===1;
            $checks['schema']=(int)$pdo->query("SELECT COUNT(*) FROM bois_schema_migrations WHERE version='20261003_p12_production_bootstrap_v1'")->fetchColumn()===1;
            $issues=0;
            foreach(['bois_email_outbox','bois_payment_outbox'] as $table){
                $issues+=(int)$pdo->query("SELECT COUNT(*) FROM $table WHERE status='FAILED' OR (status IN ('PENDING','RETRY') AND (not_before IS NULL OR not_before<=CURRENT_TIMESTAMP) AND created_at<CURRENT_TIMESTAMP-INTERVAL 15 MINUTE)")->fetchColumn();
            }
            $issues+=(int)$pdo->query("SELECT COUNT(*) FROM bois_payment_events WHERE status='ERROR' OR (status='PROCESSING' AND created_at<CURRENT_TIMESTAMP-INTERVAL 5 MINUTE)")->fetchColumn();
            $issues+=(int)$pdo->query("SELECT COUNT(*) FROM bois_payments WHERE status='PAID' AND effects_status='PENDING' AND updated_at<CURRENT_TIMESTAMP-INTERVAL 5 MINUTE")->fetchColumn();
            $checks['queues']=$issues===0;
        }finally{$pdo->rollBack();}
    }catch(Throwable){bois_p15_error('ops_failed');}
    return ['ready'=>!in_array(false,$checks,true),'checks'=>$checks];
}
