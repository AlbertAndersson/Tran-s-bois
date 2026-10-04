<?php
declare(strict_types=1);
if(getenv('BOIS_P18_DISPOSABLE')!=='YES')throw new RuntimeException('Disposable CI only.');
$path=$argv[1].'/private/config.php';$config=require $path;
if($argv[2]==='closed'){$config['mode']='production';$config['production_launch_enabled']=false;$config['db']['port']=1;}
elseif($argv[2]==='broken'){$config['mode']='test';$config['db']['port']=1;}
else throw new RuntimeException('Unknown fixture state.');
file_put_contents($path,'<?php return '.var_export($config,true).';');chmod($path,0600);
