<?php
declare(strict_types=1);
require_once __DIR__.'/p12-common.php';

function bois_p16_settings(array $config): array
{
    if(!in_array($config['mode']??null,['test','staging','production'],true))throw new RuntimeException('Unknown retention environment.');
    $r=$config['retention']??[];
    if(!is_array($r)||array_diff(array_keys($r),['rules','batch_size','apply_enabled','legal_hold','policy_reference','backup_reference','approved_target']))throw new RuntimeException('Invalid retention settings.');
    $rules=$r['rules']??[];
    $known=['sales_events_days','sales_sessions_days','consent_inactive_days'];
    if(!is_array($rules)||array_diff(array_keys($rules),$known))throw new RuntimeException('Unknown retention rule.');
    $normalized=[];
    foreach($known as $key){
        $days=$rules[$key]??null;
        if($days!==null&&(!is_int($days)||$days<1||$days>36500))throw new RuntimeException('Invalid retention window.');
        $normalized[$key]=$days;
    }
    $size=$r['batch_size']??100;
    if(!is_int($size)||$size<1||$size>1000)throw new RuntimeException('Invalid retention batch.');
    return ['rules'=>$normalized,'batch_size'=>$size]+$r;
}
function bois_p16_target(PDO $pdo,array $config): string
{
    $db=$config['db'];$actual=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if($actual!==$db['database'])throw new RuntimeException('Retention target mismatch.');
    return hash('sha256',json_encode([$db['host'],$db['port'],$actual,$db['user']],JSON_THROW_ON_ERROR));
}
function bois_p16_collect(PDO $pdo,array $config,DateTimeImmutable $at,bool $lock=false): array
{
    if(!$pdo->inTransaction())throw new RuntimeException('Retention transaction required.');
    $r=bois_p16_settings($config);$size=$r['batch_size'];$suffix=$lock?' FOR UPDATE':'';
    $utc=$at->setTimezone(new DateTimeZone('UTC'));
    if($utc->getTimestamp()>time())throw new RuntimeException('Future retention clock refused.');
    $cutoffs=[];foreach($r['rules'] as $key=>$days)$cutoffs[$key]=$days===null?null:$utc->modify('-'.$days.' days')->format('Y-m-d H:i:s');
    $select=function(string $sql,array $params=[])use($pdo,$suffix){$s=$pdo->prepare($sql.$suffix);$s->execute($params);return $s->fetchAll(PDO::FETCH_ASSOC);};
    $events=$cutoffs['sales_events_days']===null?[]:$select("SELECT id,created_at FROM bois_sales_events WHERE created_at<? ORDER BY id LIMIT $size",[$cutoffs['sales_events_days']]);
    $sessions=[];$links=[];
    if($cutoffs['sales_sessions_days']!==null){
        $params=[$cutoffs['sales_sessions_days']];
        $remaining='';if($events){$remaining=' AND e.id NOT IN ('.implode(',',array_fill(0,count($events),'?')).')';$params=array_merge($params,array_column($events,'id'));}
        $params[]=$cutoffs['sales_sessions_days'];
        $sessions=$select("SELECT s.session_id,s.last_seen_at FROM bois_sales_sessions s WHERE s.last_seen_at<?
            AND NOT EXISTS(SELECT 1 FROM bois_sales_events e WHERE e.session_id=s.session_id $remaining)
            AND NOT EXISTS(SELECT 1 FROM bois_sales_order_links l WHERE l.session_id=s.session_id AND l.created_at>=?)
            ORDER BY s.session_id LIMIT $size",$params);
        if($sessions){
            $links=$select('SELECT order_id,session_id,created_at FROM bois_sales_order_links WHERE session_id IN ('.implode(',',array_fill(0,count($sessions),'?')).') ORDER BY order_id LIMIT 1001',array_column($sessions,'session_id'));
            if(count($links)>1000)throw new RuntimeException('Retention linkage batch too large; reduce batch size.');
        }
    }
    $consents=$cutoffs['consent_inactive_days']===null?[]:$select("SELECT token_hash,expires_at,revoked_at FROM bois_consent_choices
        WHERE CASE WHEN revoked_at IS NOT NULL THEN revoked_at ELSE expires_at END <? ORDER BY token_hash LIMIT $size",[$cutoffs['consent_inactive_days']]);
    $rows=['sales_events'=>$events,'sales_links'=>$links,'sales_sessions'=>$sessions,'consent_choices'=>$consents];
    $report=['format'=>1,'operation'=>'dry_run','as_of'=>$utc->format('Y-m-d\TH:i:s\Z'),
        'target_fingerprint'=>bois_p16_target($pdo,$config),
        'approval_fingerprint'=>hash('sha256',json_encode([$r['policy_reference']??'',$r['backup_reference']??'',$r['approved_target']??[]],JSON_THROW_ON_ERROR)),
        'batch_size'=>$size,'rules'=>$r['rules'],'cutoffs_utc'=>$cutoffs,
        'would_delete'=>array_map('count',$rows),'unresolved_rules'=>array_keys(array_filter($r['rules'],static fn($days)=>$days===null)),
        'legal_hold'=>($r['legal_hold']??true)!==false,'apply_enabled'=>($r['apply_enabled']??false)===true,
        'protected_commerce'=>'all order/payment/customer/member/benefit/fulfilment/outbox/event/ledger rows retained'];
    // Digest binds exact candidates and eligibility timestamps without emitting their IDs.
    $report['plan_hash']=hash('sha256',json_encode([$report,$rows],JSON_THROW_ON_ERROR));
    return ['report'=>$report,'rows'=>$rows];
}
function bois_p16_dry_run(PDO $pdo,array $config,DateTimeImmutable $at): array
{
    if($pdo->inTransaction())throw new RuntimeException('Separate retention connection required.');
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->exec('SET TRANSACTION READ ONLY');$pdo->beginTransaction();
    try{return bois_p16_collect($pdo,$config,$at)['report'];}finally{$pdo->rollBack();}
}
function bois_p16_apply(PDO $pdo,array $config,DateTimeImmutable $at,string $confirmedHash): array
{
    $r=bois_p16_settings($config);
    if(($r['apply_enabled']??false)!==true||($r['legal_hold']??true)!==false
        ||!is_string($r['policy_reference']??null)||trim($r['policy_reference'])===''
        ||!is_string($r['backup_reference']??null)||trim($r['backup_reference'])===''
        ||!preg_match('/^[a-f0-9]{64}$/D',$confirmedHash))throw new RuntimeException('Retention apply not authorized.');
    foreach(['host','database','user'] as $key)if(($r['approved_target'][$key]??null)!==($config['db'][$key]??null))throw new RuntimeException('Unapproved retention target.');
    if(($config['mode']??'')==='production'){
        $decision=$config['production_decisions']['privacy_retention']??[];
        if(($decision['approved']??false)!==true||($decision['reference']??null)!==$r['policy_reference'])throw new RuntimeException('Production retention decision required.');
        bois_p12_verify_target($pdo,$config);bois_p15_storage($config);
    }
    if($pdo->inTransaction())throw new RuntimeException('Separate retention connection required.');
    if((int)$pdo->query("SELECT GET_LOCK('bois_p16_retention',0)")->fetchColumn()!==1)throw new RuntimeException('Retention job already running.');
    try{
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->beginTransaction();
        try{
            $plan=bois_p16_collect($pdo,$config,$at,true);
            if(!hash_equals($confirmedHash,$plan['report']['plan_hash']))throw new RuntimeException('Retention plan changed; new dry-run required.');
            $allowed=['sales_events'=>['bois_sales_events','id'],'sales_links'=>['bois_sales_order_links','order_id'],
                'sales_sessions'=>['bois_sales_sessions','session_id'],'consent_choices'=>['bois_consent_choices','token_hash']];
            $deleted=[];
            foreach($allowed as $group=>[$table,$key]){
                $ids=array_column($plan['rows'][$group],$key);$deleted[$group]=0;if(!$ids)continue;
                $stmt=$pdo->prepare("DELETE FROM $table WHERE $key IN (".implode(',',array_fill(0,count($ids),'?')).')');
                $stmt->execute($ids);$deleted[$group]=$stmt->rowCount();
                if($deleted[$group]!==count($ids))throw new RuntimeException('Retention changed concurrently.');
            }
            $pdo->commit();return ['operation'=>'applied','plan_hash'=>$confirmedHash,'deleted'=>$deleted];
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }finally{$pdo->query("SELECT RELEASE_LOCK('bois_p16_retention')");}
}
