<?php
declare(strict_types=1);
require_once __DIR__.'/p16-common.php';
try{
    if(PHP_SAPI!=='cli'||count($argv)<2||count($argv)>4)throw new RuntimeException('CLI arguments required.');
    $path=$argv[1];$root=getenv('BOIS_PUBLIC_ROOT');$public=is_string($root)?realpath($root):false;
    if(!$public||is_link($path)||!is_file($path)||(fileperms($path)&0077)!==0
        ||(fileperms(dirname($path))&0077)!==0||str_starts_with(realpath($path),$public.DIRECTORY_SEPARATOR))throw new RuntimeException('Private config required.');
    $config=require $path;if(!is_array($config))throw new RuntimeException('Invalid config.');
    $mode=$argv[2]??'--dry-run';$clock=$argv[3]??gmdate('Y-m-d\TH:i:s\Z');
    $at=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z',$clock,new DateTimeZone('UTC'));
    if(!$at||$at->format('Y-m-d\TH:i:s\Z')!==$clock)throw new RuntimeException('UTC clock required.');
    if($mode!=='--dry-run'&&!preg_match('/^--apply=[a-f0-9]{64}$/D',$mode))throw new RuntimeException('Unknown operation.');
    bois_p15_begin($config,'ops');$pdo=bois_p3_pdo($config);
    $result=$mode==='--dry-run'?bois_p16_dry_run($pdo,$config,$at):bois_p16_apply($pdo,$config,$at,substr($mode,8));
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable){bois_p15_error('ops_failed');fwrite(STDERR,"P16_RETENTION: refused/failed; no sensitive details emitted\n");exit(1);}
