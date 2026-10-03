<?php
declare(strict_types=1);
require_once __DIR__.'/p13-common.php';
try {
    if(PHP_SAPI!=='cli'||count($argv)!==3) throw new RuntimeException('Private config and new snapshot path required.');
    $config=bois_p12_load($argv[1]);
    $parent=realpath(dirname($argv[2]));
    if($parent===false||(fileperms($parent)&0077)!==0||is_link($argv[2])) throw new RuntimeException('Private output directory required.');
    $public=getenv('BOIS_PUBLIC_ROOT');
    if(!is_string($public)||$public===''||realpath($public)===false) throw new RuntimeException('Known public root required.');
    $root=realpath($public);
    if($parent===$root||str_starts_with($parent,$root.DIRECTORY_SEPARATOR)) throw new RuntimeException('Output inside public root refused.');
    $pdo=bois_p3_pdo($config);bois_p12_verify_target($pdo,$config);
    $snapshot=bois_p13_snapshot($pdo);
    $old=umask(0077);$file=fopen($argv[2],'x');umask($old);
    if($file===false) throw new RuntimeException('Output exists or unavailable.');
    $data=json_encode($snapshot,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
    if(fwrite($file,$data)!==strlen($data)) {fclose($file);throw new RuntimeException('Incomplete snapshot.');}
    fclose($file);echo "P13_READONLY_SNAPSHOT: pass\n";
} catch(Throwable $e) {fwrite(STDERR,"P13_READONLY_SNAPSHOT: refused/failed; inspect privately.\n");exit(1);}
