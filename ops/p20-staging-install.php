<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
umask(0077);ini_set('display_errors','0');ini_set('zend.exception_ignore_args','1');
$home=getenv('HOME');$sha=$argv[1]??'';
if($home!=='/var/www/alberiq.se'||!preg_match('/\A[a-f0-9]{40}\z/',$sha))exit(1);
$root=$home.'/.bois-p20';$release=$root.'/releases/'.$sha;$public=$home.'/public_html/bois-shop-p3';
$backup=$root.'/backups/'.$sha;$switched=false;
ini_set('log_errors','1');ini_set('error_log',$root.'/private-error.log');
function p20_run(array $args,string $log):void{$p=proc_open($args,[0=>['file','/dev/null','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes);if(!is_resource($p)||proc_close($p)!==0)throw new RuntimeException('Private backup process failed.');}
function p20_snapshot(PDO $pdo):array{
    $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    try{$out=[];foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table){
        if(!preg_match('/\A[A-Za-z0-9_]+\z/',$table))throw new RuntimeException('Invalid table.');
        $rows=$pdo->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);
        $encoded=array_map(fn($r)=>json_encode($r,JSON_THROW_ON_ERROR),$rows);sort($encoded);
        $out[$table]=['count'=>count($rows),'hash'=>hash('sha256',implode("\n",$encoded))];
    }ksort($out);return $out;}finally{$pdo->rollBack();}
}
function p20_copy_public(string $from,string $to):void{
    if(file_exists($to)||is_link($from))throw new RuntimeException('Unique public target required.');
    mkdir($to,0755);chmod($to,0755);
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST) as $f){
        if($f->isLink())throw new RuntimeException('Link refused.');$target=$to.'/'.substr($f->getPathname(),strlen($from)+1);
        if($f->isDir()){mkdir($target,0755);chmod($target,0755);}else{copy($f->getPathname(),$target);chmod($target,0644);}
    }
}
try{
    if(!is_dir($root.'/backups'))mkdir($root.'/backups',0700,true);
    if(file_exists($backup)||is_link($public))throw new RuntimeException('Unique verified backup required.');
    $manifest=json_decode(file_get_contents($release.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
    if($manifest['source_revision']!==$sha||$manifest['activation']!=='closed')throw new RuntimeException('Closed exact manifest required.');
    foreach($manifest['files'] as $f){if(str_contains($f['path'],'..')||!hash_equals($f['sha256'],hash_file('sha256',$release.'/'.$f['path'])))throw new RuntimeException('Manifest mismatch.');}
    require $release.'/private/server/p3_db.php';
    $config=require $home.'/.bois-p3/config.php';
    if(($config['mode']??'')!=='staging'||($config['payment_provider']??'')!=='mock')throw new RuntimeException('Existing synthetic staging required.');
    $pdo=bois_p3_pdo($config);$before=p20_snapshot($pdo);
    mkdir($backup,0700);file_put_contents($backup.'/before.json',json_encode($before,JSON_THROW_ON_ERROR));
    $cnf="[client]\n";foreach(['host','user','password'] as $k)$cnf.=$k.'="'.str_replace(['\\','"'],['\\\\','\\"'],(string)$config['db'][$k])."\"\n";
    file_put_contents($backup.'/mysql.cnf',$cnf);chmod($backup.'/mysql.cnf',0600);
    p20_run(['mysqldump','--defaults-extra-file='.$backup.'/mysql.cnf','--no-tablespaces','--single-transaction','--result-file='.$backup.'/database.sql',$config['db']['database']],$backup.'/process.log');
    $runtimePaths=['.bois-p3','.bois-demo-auth','public_html/bois-shop-p3'];
    if(is_file($root.'/current-release')){
        $previous=trim(file_get_contents($root.'/current-release'));
        if(!preg_match('/\A[a-f0-9]{40}\z/',$previous))throw new RuntimeException('Previous release invalid.');
        $runtimePaths=array_merge($runtimePaths,['.bois-p20/current-release','.bois-p20/config-'.$previous.'.php','.bois-p20/releases/'.$previous]);
    }
    p20_run(array_merge(['tar','-cf',$backup.'/runtime.tar','-C',$home],$runtimePaths),$backup.'/process.log');
    if(!str_contains(substr(file_get_contents($backup.'/database.sql'),-300),'Dump completed'))throw new RuntimeException('Incomplete SQL backup.');
    p20_run(['tar','-tf',$backup.'/runtime.tar'],$backup.'/archive-check.log');
    $hashes=[];foreach(['database.sql','runtime.tar'] as $f){chmod($backup.'/'.$f,0600);$hashes[$f]=hash_file('sha256',$backup.'/'.$f);if(!is_readable($backup.'/'.$f))throw new RuntimeException('Unreadable backup.');}
    file_put_contents($backup.'/verified.json',json_encode($hashes,JSON_THROW_ON_ERROR));
    $config['membership_period']='calendar_year';$config['matchkit_batch']=['mode'=>'monthly','day'=>null];
    $config['mail_transport']='disabled';$config['payment_mail_transport']='disabled';$config['external_analytics']=false;
    $config['public_base_url']='https://alberiq.se/bois-shop-p3';$config['consent_validity_days']=180;
    $configPath=$root.'/config-'.$sha.'.php';file_put_contents($configPath,'<?php return '.var_export($config,true).';');chmod($configPath,0600);
    $ready=$root.'/public-'.$sha;p20_copy_public($release.'/public',$ready);
    $access=file_get_contents($public.'/.htaccess');if(!str_contains($access,'AuthType Basic')||!str_contains($access,'Require valid-user'))throw new RuntimeException('Existing Basic protection required.');
    file_put_contents($ready.'/.htaccess',$access);chmod($ready.'/.htaccess',0644);
    file_put_contents($ready.'/commerce-api.php',"<?php ini_set('display_errors','0');putenv('BOIS_P3_CONFIG_PATH=".$configPath."');require '".$release."/private/server/commerce-api.php';");chmod($ready.'/commerce-api.php',0644);
    file_put_contents($ready.'/config.js',"window.BOIS_COMMERCE_CONFIG=Object.freeze({apiBase:'./commerce-api.php',environmentLabel:'SKYDDAT P20-TEST',paymentEnabled:true,p20Preview:true,adminAuth:'token'});\n");chmod($ready.'/config.js',0644);
    // No reseeding or quota reset: only the approved variant metadata changes.
    $pdo->exec("UPDATE bois_variants SET metadata_json=JSON_SET(COALESCE(metadata_json,JSON_OBJECT()),'$.shirt_sizes',JSON_ARRAY('S','M','L')) WHERE sku='MATCHKIT-STAGING'");
    $after=p20_snapshot($pdo);$expected=$before;unset($expected['bois_variants']);$other=$after;unset($other['bois_variants']);
    if($expected!==$other||$before['bois_variants']['count']!==$after['bois_variants']['count'])throw new RuntimeException('Existing data changed unexpectedly.');
    if(!rename($public,$backup.'/public-before'))throw new RuntimeException('Public save failed.');
    if(!rename($ready,$public)){rename($backup.'/public-before',$public);throw new RuntimeException('Public switch reverted.');}$switched=true;
    file_put_contents($root.'/current-release',$sha."\n");chmod($root.'/current-release',0600);
    file_put_contents($backup.'/after.json',json_encode($after,JSON_THROW_ON_ERROR));
    echo "P20_STAGING_BACKUP_READABLE:pass\nP20_STAGING_EXISTING_ORDERS_QUOTA_PRESERVED:pass\nP20_STAGING_INSTALLED:".$sha."\n";
}catch(Throwable $e){error_log($e->getMessage());fwrite(STDERR,"P20_STAGING_INSTALL:refused; inspect private log and backup\n");exit(1);}
