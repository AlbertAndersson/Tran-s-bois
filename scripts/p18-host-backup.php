<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
ini_set('display_errors','0');ini_set('log_errors','1');ini_set('error_log',__DIR__.'/logs/p18-backup-private-error.log');
umask(0077);
$root=__DIR__;$work=null;$lock=null;
function runPrivate(array $args,string $output,string $errors):void{
 $p=proc_open($args,[0=>['file','/dev/null','r'],1=>['file',$output,'a'],2=>['file',$errors,'a']],$pipes,'/var/www/socen.se');
 if(!is_resource($p)||proc_close($p)!==0)throw new RuntimeException('Private backup process refused');
 chmod($output,0600);chmod($errors,0600);
}
try{
 $lock=fopen($root.'/p18-backup.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Concurrent backup refused');
 chmod($root.'/p18-backup.lock',0600);
 $sha=trim(file_get_contents($root.'/current-release'));
 if(!preg_match('/\A[a-f0-9]{40}\z/',$sha))throw new RuntimeException('Exact release required');
 $release=$root.'/releases/'.$sha;
 require $release.'/private/ops/p13-common.php';
 $config=require $root.'/config-p18.php';
 bois_production_require_checks(bois_production_config_checks($config));
 $pdo=bois_p3_pdo($config);
 $before=bois_p13_snapshot($pdo);
 $base=$root.'/daily-backups';$encryptedRoot=$root.'/daily-encrypted';
 foreach([$base,$encryptedRoot] as $d){if(is_link($d))throw new RuntimeException('Symlink refused');if(!is_dir($d))mkdir($d,0700);chmod($d,0700);}
 $total=0;foreach([$base,$encryptedRoot] as $d){$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d,FilesystemIterator::SKIP_DOTS));foreach($it as $f){if($f->isLink())throw new RuntimeException('Backup symlink refused');$total+=$f->getSize();}}
 if($total>2*1024*1024*1024)throw new RuntimeException('Backup storage cap reached; owner review required');
 $name='bois-daily-'.gmdate('Ymd\THis\Z').'-'.substr($sha,0,7);
 $work=$base.'/'.$name;if(file_exists($work))throw new RuntimeException('Unique backup required');mkdir($work,0700);
 runPrivate(['mysqldump','--defaults-extra-file='.$root.'/mysql-backup.cnf','--no-tablespaces','--single-transaction','--routines','--triggers','--events','--result-file='.$work.'/production.sql',(string)$config['db']['database']],$work.'/process-private.txt',$work.'/errors-private.txt');
 $dump=file_get_contents($work.'/production.sql');if(!$dump||!str_contains(substr($dump,-300),'Dump completed'))throw new RuntimeException('Completed SQL required');unset($dump);chmod($work.'/production.sql',0600);
 runPrivate(['crontab','-l'],$work.'/schedule-private.txt',$work.'/errors-private.txt');
 runPrivate(['tar','-cf',$work.'/runtime-release.tar','.bois-production/config-p18.php','.bois-production/mysql-backup.cnf','.bois-production/p18-daily-backup.php','.bois-production/current-release','.bois-production/admin-state','.bois-production/operations','.bois-production/releases/'.$sha,'.bois-production-auth','public_html/bois-shop-production'],$work.'/process-private.txt',$work.'/errors-private.txt');
 runPrivate(['tar','-tf',$work.'/runtime-release.tar'],$work.'/archive-list-private.txt',$work.'/errors-private.txt');
 bois_p13_equal($before,bois_p13_snapshot($pdo));
 $manifest=['format'=>1,'source_revision'=>$sha,'created_at'=>gmdate('c'),'closed'=>true,'files'=>['production.sql'=>hash_file('sha256',$work.'/production.sql'),'runtime-release.tar'=>hash_file('sha256',$work.'/runtime-release.tar'),'schedule-private.txt'=>hash_file('sha256',$work.'/schedule-private.txt')]];
 file_put_contents($work.'/manifest.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");chmod($work.'/manifest.json',0600);
 runPrivate(['tar','-cf',$work.'/private-bundle.tar','-C',$work,'production.sql','runtime-release.tar','schedule-private.txt','manifest.json'],$work.'/process-private.txt',$work.'/errors-private.txt');
 $plain=file_get_contents($work.'/private-bundle.tar');if(!$plain||strlen($plain)>100*1024*1024)throw new RuntimeException('Bounded backup bundle required');
 $key=file_get_contents($root.'/p18-backup-recovery.key');if(strlen($key)!==32)throw new RuntimeException('Private AES key required');
 $header="BOIS_BACKUP_AES256_GCM_V1\n";$nonce=random_bytes(12);$tag='';
 $cipher=openssl_encrypt($plain,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$nonce,$tag,$header,16);
 if(!is_string($cipher)||strlen($tag)!==16||openssl_decrypt($cipher,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$nonce,$tag,$header)!==$plain)throw new RuntimeException('Authenticated encryption refused');
 $path=$encryptedRoot.'/'.$name.'.aesgcm';$out=fopen($path.'.part','x');if(!$out)throw new RuntimeException('Unique encrypted target required');
 $bytes=$header.$nonce.$cipher.$tag;if(fwrite($out,$bytes)!==strlen($bytes)){fclose($out);throw new RuntimeException('Complete cipher write required');}fflush($out);fclose($out);chmod($path.'.part',0600);rename($path.'.part',$path);
 $status=['ok'=>true,'created_at'=>gmdate('c'),'file'=>basename($path),'sha256'=>hash_file('sha256',$path),'source_revision'=>$sha,'offsite_automatic'=>false];
 file_put_contents($root.'/p18-backup-status.next',json_encode($status,JSON_THROW_ON_ERROR)."\n");chmod($root.'/p18-backup-status.next',0600);rename($root.'/p18-backup-status.next',$root.'/p18-backup-status.json');
 echo "P18_SCHEDULED_BACKUP_SQL_RUNTIME_STABLE_HASH_AES_GCM:pass\n";
}catch(Throwable $e){error_log((string)$e);file_put_contents($root.'/p18-backup-last-failure.json',json_encode(['ok'=>false,'at'=>gmdate('c'),'reason'=>'Inspect private error log'],JSON_THROW_ON_ERROR));chmod($root.'/p18-backup-last-failure.json',0600);fwrite(STDERR,"P18_BACKUP:failed; owner review required\n");exit(1);}
finally{if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}}
