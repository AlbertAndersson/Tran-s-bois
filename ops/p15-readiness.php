<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/server/p3_db.php';
try{
    $path=$argv[1]??'';
    if(PHP_SAPI!=='cli'||$path===''||is_link($path)||!is_file($path)||(fileperms($path)&0077)!==0)throw new RuntimeException('Private CLI config required.');
    $config=require $path;
    if(!is_array($config))throw new RuntimeException('Invalid config.');
    $public=realpath($config['observability']['public_root']??'');$real=realpath($path);
    if(!$public||$real===$public||str_starts_with($real,$public.DIRECTORY_SEPARATOR))throw new RuntimeException('Private CLI config required.');
    bois_p15_begin($config,'ops');$result=bois_p15_readiness($config);
    $GLOBALS['bois_p15_cli_status']=$result['ready']?200:503;
    echo json_encode($result,JSON_THROW_ON_ERROR)."\n";exit($result['ready']?0:1);
}catch(Throwable){bois_p15_error('ops_failed');fwrite(STDERR,"P15_READINESS: unavailable\n");exit(1);}
