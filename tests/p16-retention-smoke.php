<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/ops/p16-common.php';
require_once dirname(__DIR__).'/ops/p13-common.php';
function p16_ok(bool $value,string $label): void {if(!$value)throw new RuntimeException($label);}
function p16_no(callable $fn): void {try{$fn();}catch(Throwable){return;}throw new RuntimeException('Unsafe retention accepted.');}
if(getenv('BOIS_P16_DISPOSABLE')!=='YES'||getenv('BOIS_P3_TEST_DB_NAME')!=='bois_p16_ci'||getenv('BOIS_P3_TEST_DB_HOST')!=='127.0.0.1')throw new RuntimeException('Disposable loopback fixture required.');
$config=['mode'=>'test','db'=>['host'=>'127.0.0.1','port'=>3306,'database'=>'bois_p16_ci','user'=>'root','password'=>'root']];
$pdo=bois_p3_pdo($config);
$process=proc_open([PHP_BINARY,'-d','disable_functions=mail,curl_exec,curl_init',__DIR__.'/p6-smoke.php'],[0=>['file','/dev/null','r'],1=>STDOUT,2=>STDERR],$pipes);
p16_ok(is_resource($process)&&proc_close($process)===0,'Synthetic payment seed failed.');
bois_p7_apply_schema($pdo);bois_p7_seed_assortment($pdo);bois_p8_apply_schema($pdo);bois_p9_apply_schema($pdo);bois_consent_schema($pdo);
$pdo->prepare('INSERT IGNORE INTO bois_schema_migrations(version) VALUES(?)')->execute(['20261003_p12_production_bootstrap_v1']);
// Checked-in field inventory must cover every actual column, including additive migrations.
$inventory=json_decode(file_get_contents(dirname(__DIR__).'/docs/P16-DATA-FIELDS.json'),true,512,JSON_THROW_ON_ERROR);
p16_ok(array_keys($inventory)===bois_p12_tables(),'Table inventory incomplete.');
foreach($inventory as $table=>$entry){$columns=$pdo->query('SHOW COLUMNS FROM '.$table)->fetchAll(PDO::FETCH_COLUMN);sort($columns);p16_ok($columns===array_keys($entry['fields']),'Field inventory incomplete: '.$table);}
$at=new DateTimeImmutable(gmdate('Y-m-d\TH:i:s\Z'),new DateTimeZone('UTC'));$old=$at->modify('-60 days')->format('Y-m-d H:i:s');$cut=$at->modify('-30 days')->format('Y-m-d H:i:s');$new=$at->format('Y-m-d H:i:s');
$sessions=['old','young','active','recentlink','empty'];$ids=[];
$stmt=$pdo->prepare('INSERT INTO bois_sales_sessions(session_id,source,last_seen_at,first_seen_at) VALUES(?,?,?,?)');
foreach($sessions as $i=>$key){$id='00000000-0000-4000-8000-'.str_pad((string)($i+1),12,'0',STR_PAD_LEFT);$ids[$key]=$id;$stmt->execute([$id,'NEVER_PRINT_PERSON', $key==='young'?$new:$old,$old]);}
$stmt=$pdo->prepare("INSERT INTO bois_sales_events(event_key,session_id,event_type,page_path,created_at) VALUES(?,?,'page_view','/NEVER_PRINT_PERSON',?)");
foreach(['old'=>$old,'young'=>$new,'active'=>$new,'recentlink'=>$old] as $key=>$time)$stmt->execute(['fixture-'.$key,$ids[$key],$time]);
$orders=$pdo->query('SELECT id FROM bois_orders ORDER BY id LIMIT 2')->fetchAll(PDO::FETCH_COLUMN);p16_ok(count($orders)===2,'Paid fixture missing.');
$pdo->prepare('INSERT INTO bois_sales_order_links(order_id,session_id,created_at) VALUES(?,?,?),(?,?,?)')->execute([$orders[0],$ids['old'],$old,$orders[1],$ids['recentlink'],$new]);
$stmt=$pdo->prepare('INSERT INTO bois_consent_choices(token_hash,policy_version,statistics,decided_at,expires_at,revoked_at) VALUES(?,?,?,?,?,?)');
foreach(['expired'=>[$old,null],'revoked'=>[$new,$old],'active'=>[$at->modify('+20 days')->format('Y-m-d H:i:s'),null],'boundary'=>[$cut,null]] as $key=>[$expires,$revoked])$stmt->execute([hash('sha256',$key),'statistics-v1',1,$old,$expires,$revoked]);
$before=bois_p13_snapshot($pdo);
$empty=bois_p16_dry_run($pdo,$config,$at);p16_ok(array_sum($empty['would_delete'])===0&&count($empty['unresolved_rules'])===3&&$empty['legal_hold'],'TBD defaults are destructive.');
$config['retention']=['rules'=>['sales_events_days'=>30,'sales_sessions_days'=>30,'consent_inactive_days'=>30],'batch_size'=>100,'apply_enabled'=>true,'legal_hold'=>false,'policy_reference'=>'synthetic-policy','backup_reference'=>'synthetic-backup','approved_target'=>array_intersect_key($config['db'],array_flip(['host','database','user']))];
// A real SELECT-only principal proves dry-run requires neither DDL nor DELETE.
$pdo->exec("CREATE USER 'p16_reader'@'%' IDENTIFIED BY 'synthetic-only'");$pdo->exec("GRANT SELECT ON bois_p16_ci.* TO 'p16_reader'@'%'");
$reader=$config;$reader['db']['user']='p16_reader';$reader['db']['password']='synthetic-only';
$read=bois_p16_dry_run(bois_p3_pdo($reader),$reader,$at);
p16_ok($read['would_delete']===['sales_events'=>2,'sales_links'=>1,'sales_sessions'=>2,'consent_choices'=>2],'Candidate eligibility incorrect.');
bois_p13_equal($before,bois_p13_snapshot($pdo));
$plan=bois_p16_dry_run($pdo,$config,$at);
foreach(['legal_hold'=>true,'apply_enabled'=>false,'policy_reference'=>'','backup_reference'=>'','approved_target'=>[]] as $key=>$value){$bad=$config;$bad['retention'][$key]=$value;p16_no(fn()=>bois_p16_apply($pdo,$bad,$at,$plan['plan_hash']));}
p16_no(fn()=>bois_p16_apply($pdo,$config,$at,str_repeat('0',64)));
$bad=$config;$bad['retention']['policy_reference']='changed-policy';p16_no(fn()=>bois_p16_apply($pdo,$bad,$at,$plan['plan_hash']));
$bad=$config;$bad['mode']='unknown';p16_no(fn()=>bois_p16_dry_run($pdo,$bad,$at));
$bad=$config;$bad['retention']['rules']['sales_events_days']='30';p16_no(fn()=>bois_p16_dry_run($pdo,$bad,$at));
$bad=$config;$bad['retention']['rules']['orders_days']=1;p16_no(fn()=>bois_p16_dry_run($pdo,$bad,$at));
p16_no(fn()=>bois_p16_dry_run($pdo,$config,$at->modify('+1 day')));
$bad=$config;$bad['retention']['batch_size']=1;$small=bois_p16_dry_run($pdo,$bad,$at);p16_ok($small['would_delete']['sales_events']===1,'Batch bound ignored.');
// Eligibility changing after review must refuse before deleting anything.
$pdo->prepare('UPDATE bois_sales_sessions SET last_seen_at=? WHERE session_id=?')->execute([$new,$ids['empty']]);
$changed=bois_p13_snapshot($pdo);p16_no(fn()=>bois_p16_apply($pdo,$config,$at,$plan['plan_hash']));bois_p13_equal($changed,bois_p13_snapshot($pdo));
$pdo->prepare('UPDATE bois_sales_sessions SET last_seen_at=? WHERE session_id=?')->execute([$old,$ids['empty']]);
// Fail after the first DELETE to prove the entire batch rolls back atomically.
$pdo->exec("CREATE TRIGGER p16_refuse BEFORE DELETE ON bois_consent_choices FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic failure'");
p16_no(fn()=>bois_p16_apply($pdo,$config,$at,$plan['plan_hash']));bois_p13_equal($before,bois_p13_snapshot($pdo));$pdo->exec('DROP TRIGGER p16_refuse');
$applied=bois_p16_apply($pdo,$config,$at,$plan['plan_hash']);p16_ok($applied['deleted']===$plan['would_delete'],'Applied count mismatch.');
$after=bois_p13_snapshot($pdo);$eligible=['bois_sales_events','bois_sales_sessions','bois_sales_order_links','bois_consent_choices'];
foreach(array_diff(bois_p12_tables(),$eligible) as $table)p16_ok($after['tables'][$table]===$before['tables'][$table],'Protected commerce changed: '.$table);
p16_ok($after['ledger']===$before['ledger']&&$after['foreign_keys_checked']===$before['foreign_keys_checked'],'Ledger/FKs changed.');
p16_no(fn()=>bois_p16_apply($pdo,$config,$at,$plan['plan_hash']));
p16_ok(array_sum(bois_p16_dry_run($pdo,$config,$at)['would_delete'])===0,'Idempotent dry-run failed.');
$_COOKIE[BOIS_CONSENT_COOKIE]='expired';p16_ok(bois_consent_choice($pdo)['statistics']===false,'Deleted consent reactivated.');
p16_no(fn()=>bois_consent_days(['mode'=>'production']));p16_no(fn()=>bois_consent_cookie_path(['mode'=>'production']));
p16_ok(bois_consent_days(['mode'=>'production','consent_validity_days'=>42])===42&&bois_consent_cookie_path(['mode'=>'production','consent_cookie_path'=>'/bois-shop-production/'])==='/bois-shop-production/','Production consent config ignored.');
// Real CLI: defaults to dry-run, emits counts only, validates private paths.
$dir=sys_get_temp_dir().'/bois-p16-'.bin2hex(random_bytes(6));mkdir($dir,0700);mkdir($dir.'/private',0700);mkdir($dir.'/public',0755);mkdir($dir.'/private/logs',0700);
$config['observability']=['enabled'=>true,'log_dir'=>$dir.'/private/logs','public_root'=>$dir.'/public'];
$config['retention']['apply_enabled']=false;$config['db']['password']='root';$path=$dir.'/private/config.php';file_put_contents($path,'<?php return '.var_export($config,true).';');chmod($path,0600);putenv('BOIS_PUBLIC_ROOT='.$dir.'/public');
$run=function(array $args,int $expected)use($path):string{$proc=proc_open(array_merge([PHP_BINARY,dirname(__DIR__).'/ops/p16-retention.php',$path],$args),[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);$output=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach([1,2] as $i)fclose($pipes[$i]);p16_ok(proc_close($proc)===$expected,'CLI guard failed.');p16_ok(!str_contains($output.$err,'NEVER_PRINT')&&!str_contains($output,'token_hash'),'CLI exposed identifiers.');return $output;};
p16_ok(json_decode($run([],0),true)['operation']==='dry_run','CLI default not dry-run.');$run(['--apply='.str_repeat('0',64)],1);chmod($path,0644);$run([],1);chmod($path,0600);
echo "P16_FIELDS_SELECT_ONLY_DRYRUN_GUARDS_BOUNDARIES_ROLLBACK_APPLY_FKS_COMMERCE_CLI: pass\nREAL_PRODUCTION_DELETION_STRIPE_MAIL: no\n";
