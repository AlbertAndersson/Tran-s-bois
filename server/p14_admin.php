<?php
declare(strict_types=1);

function bois_p14_personal(array $config): bool {return ($config['mode']??'')==='production'||($config['personal_admin']['enabled']??false)===true;}
function bois_p14_permissions(string $role): array
{
    $read=['admin_orders','admin_catalog','admin_p7','admin_batches'];
    $club=array_merge($read,['admin_p4','admin_verify_existing_member','admin_benefit_status','admin_nordic_export','admin_batch_now','admin_batch_csv']);
    return match($role){
        'operator'=>$read,
        'club_admin'=>$club,
        'superadmin'=>array_merge($club,['admin_sales','admin_p8_readiness','admin_payments','admin_stripe_refund','admin_retry_payment_outbox','admin_run_payment_outbox','admin_retry_outbox','admin_run_worker','admin_revoke']),
        default=>[],
    };
}

function bois_p14_paths(array $config): array
{
    $p=$config['personal_admin']??[];
    if(($p['enabled']??false)!==true) throw new DomainException('Personlig adminåtkomst är inte aktiverad.');
    $root=realpath($p['public_root']??'');$dir=realpath($p['state_dir']??'');$users=realpath($p['users_file']??'');
    if(!$root||!$dir||!$users||is_link($p['state_dir'])||is_link($p['users_file'])) throw new RuntimeException('Private admin paths required.');
    foreach([$dir,dirname($users)] as $path)
        if(($path===$root||str_starts_with($path,$root.DIRECTORY_SEPARATOR))||(fileperms($path)&0077)!==0) throw new RuntimeException('Private admin directory required.');
    if((fileperms($users)&0077)!==0||filesize($users)>65536) throw new RuntimeException('Private bounded user registry required.');
    $registry=json_decode(file_get_contents($users),true,16,JSON_THROW_ON_ERROR);
    if(!is_array($registry)||count($registry)>50) throw new RuntimeException('Invalid registry.');
    $secrets=[];
    foreach($registry as $id=>$u){
        if(!preg_match('/^[a-z0-9][a-z0-9._-]{2,79}$/D',(string)$id)||!is_array($u)||
            !is_bool($u['enabled']??null)||!is_int($u['epoch']??null)||$u['epoch']<1||!bois_p14_permissions($u['role']??'')||
            !is_string($u['password_hash']??null)||!preg_match('/^[A-Z2-7]{32,64}$/D',$u['totp_secret']??'')) throw new RuntimeException('Invalid personal account.');
        $info=password_get_info($u['password_hash']);
        if(!(($info['algoName']==='bcrypt'&&($info['options']['cost']??0)>=12)||
            ($info['algoName']==='argon2id'&&($info['options']['memory_cost']??0)>=65536))) throw new RuntimeException('Strong password hash required.');
        if(($config['mode']??'')==='production'&&isset($secrets[$u['totp_secret']]))throw new RuntimeException('Unique MFA secrets required.');
        $secrets[$u['totp_secret']]=true;
    }
    return [$dir,$registry];
}

// RFC 6238 / HOTP dynamic truncation; secrets use unpadded uppercase Base32.
function bois_p14_totp(string $secret,int $step,int $digits=6): string
{
    if(!preg_match('/^[A-Z2-7]{32,64}$/D',$secret)||$step<0||!in_array($digits,[6,8],true)) throw new InvalidArgumentException('Invalid TOTP parameters.');
    $bits='';$alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    foreach(str_split($secret) as $ch) $bits.=str_pad(decbin(strpos($alphabet,$ch)),5,'0',STR_PAD_LEFT);
    $key='';for($i=0;$i+8<=strlen($bits);$i+=8)$key.=chr(bindec(substr($bits,$i,8)));
    $hash=hash_hmac('sha1',pack('N2',intdiv($step,4294967296),$step%4294967296),$key,true);
    $offset=ord($hash[19])&15;$number=unpack('N',substr($hash,$offset,4))[1]&0x7fffffff;
    return str_pad((string)($number%(10**$digits)),$digits,'0',STR_PAD_LEFT);
}

function bois_p14_locked(array $config,callable $fn): mixed
{
    [$dir,$users]=bois_p14_paths($config);$path=$dir.'/state.json';
    if(is_link($path)) throw new RuntimeException('State symlink refused.');
    $old=umask(0077);$h=fopen($path,'c+');umask($old);
    if(!$h) throw new RuntimeException('Admin state unavailable.');
    try{
        if(!chmod($path,0600)||!flock($h,LOCK_EX))throw new RuntimeException('Admin lock unavailable.');
        $raw=stream_get_contents($h,4194305);if($raw===false||strlen($raw)>4194304)throw new RuntimeException('Admin state too large.');
        $s=$raw===''?['sessions'=>[],'totp'=>[],'attempts'=>[],'epochs'=>[]]:json_decode($raw,true,16,JSON_THROW_ON_ERROR);
        foreach(['sessions','totp','attempts','epochs'] as $key)if(!is_array($s[$key]??null))throw new RuntimeException('Invalid admin state.');
        $now=time();foreach($s['sessions'] as $key=>$row)if($row['expires']<=$now||$row['last']+900<=$now)unset($s['sessions'][$key]);
        foreach($s['attempts'] as $key=>$row)if($row['until']<=$now)unset($s['attempts'][$key]);
        $result=$fn($s,$users,$dir,$now);
        $out=json_encode($s,JSON_THROW_ON_ERROR);if(strlen($out)>4194304)throw new RuntimeException('Admin state full.');
        rewind($h);if(!ftruncate($h,0)||fwrite($h,$out)!==strlen($out)||!fflush($h))throw new RuntimeException('Admin state write failed.');
        return $result;
    }finally{flock($h,LOCK_UN);fclose($h);}
}

function bois_p14_audit(string $dir,string $actor,string $role,string $action,string $outcome,string $request,string $target=''): void
{
    $path=$dir.'/audit.jsonl';if(is_link($path))throw new RuntimeException('Audit symlink refused.');
    // No passwords, OTPs, tokens, customer data, request bodies, IPs or user agents.
    $line=json_encode(['time'=>gmdate('c'),'actor'=>$actor,'role'=>$role,'action'=>$action,'outcome'=>$outcome,'request'=>$request]+($target!==''?['target'=>$target]:[]),JSON_THROW_ON_ERROR)."\n";
    $old=umask(0077);$h=fopen($path,'ab');umask($old);
    if(!$h)throw new RuntimeException('Audit unavailable.');
    try{if(!chmod($path,0600)||!flock($h,LOCK_EX)||fwrite($h,$line)!==strlen($line)||!fflush($h))throw new RuntimeException('Audit write failed.');}
    finally{flock($h,LOCK_UN);fclose($h);}
}

function bois_p14_login(array $config,array $input): array
{
    $id=$input['username']??'';$password=$input['password']??'';$otp=$input['otp']??'';
    if(!is_string($id)||!is_string($password)||strlen($password)>256||!is_string($otp)||!preg_match('/^[0-9]{6}$/D',$otp))throw new DomainException('Inloggningen misslyckades.');
    // Persist both successful OTP consumption and failed attempts under one lock.
    $result=bois_p14_locked($config,function(&$s,$users,$dir,$now)use($id,$password,$otp){
        $account=preg_match('/^[a-z0-9][a-z0-9._-]{2,79}$/D',$id)?($users[$id]??null):null;
        $bucket=hash('sha256',$id);$attempt=$s['attempts'][$bucket]??['until'=>$now+900,'count'=>0];
        if(count($s['attempts'])>=512&&!isset($s['attempts'][$bucket]))return ['denied'=>true];
        if($attempt['count']>=5)return ['denied'=>true];
        $attempt['count']++;$s['attempts'][$bucket]=$attempt;
        static $dummy=null;$dummy??=password_hash(bin2hex(random_bytes(20)),PASSWORD_BCRYPT,['cost'=>12]);
        $passwordOk=password_verify($password,$account['password_hash']??$dummy);
        $matched=null;$step=intdiv($now,30);
        if($account&&$passwordOk&&$account['enabled'])foreach([$step-1,$step,$step+1] as $n)
            if($n>($s['totp'][$id]??-1)&&hash_equals(bois_p14_totp($account['totp_secret'],$n),$otp)){$matched=$n;break;}
        if($matched===null){bois_p14_audit($dir,$account?$id:'unknown','none','admin_login','denied',bois_p15_id());return ['denied'=>true];}
        if(count($s['sessions'])>=256)throw new RuntimeException('Admin session capacity reached.');
        $s['totp'][$id]=$matched;unset($s['attempts'][$bucket]);
        $token=bin2hex(random_bytes(32));$csrf=bin2hex(random_bytes(32));
        $s['sessions'][hash('sha256',$token)]=['id'=>$id,'epoch'=>$account['epoch'],'credential'=>hash('sha256',$account['password_hash'].'|'.$account['totp_secret']),'revocation'=>$s['epochs'][$id]??0,'role'=>$account['role'],'csrf'=>$csrf,'last'=>$now,'expires'=>$now+28800];
        bois_p14_audit($dir,$id,$account['role'],'admin_login','success',bois_p15_id());
        return ['token'=>$token,'csrf'=>$csrf,'id'=>$id,'role'=>$account['role'],'permissions'=>bois_p14_permissions($account['role'])];
    });
    if(isset($result['denied']))throw new DomainException('Inloggningen misslyckades.');return $result;
}

function bois_p14_authorize(array $config,string $action,string $token,string $csrf='',bool $write=false): array
{
    if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new DomainException('Ej behörig.');
    return bois_p14_locked($config,function(&$s,$users,$dir,$now)use($action,$token,$csrf,$write){
        $key=hash('sha256',$token);$row=$s['sessions'][$key]??null;$user=$row?($users[$row['id']]??null):null;
        if(!$row||!$user||!$user['enabled']||$row['epoch']!==$user['epoch']||$row['role']!==$user['role']||
            ($row['credential']??null)!==hash('sha256',$user['password_hash'].'|'.$user['totp_secret'])||$row['revocation']!==($s['epochs'][$row['id']]??0))throw new DomainException('Sessionen har gått ut.');
        if($write&&(!preg_match('/^[a-f0-9]{64}$/D',$csrf)||!hash_equals($row['csrf'],$csrf)))throw new DomainException('Ogiltigt sessionsskydd.');
        if(!in_array($action,['admin_session','admin_logout'],true)&&!in_array($action,bois_p14_permissions($user['role']),true)){
            bois_p14_audit($dir,$row['id'],$user['role'],$action,'denied',bois_p15_id());throw new DomainException('Ej behörig för åtgärden.');
        }
        $s['sessions'][$key]['last']=$now;
        $request=bois_p15_id();bois_p14_audit($dir,$row['id'],$user['role'],$action,$write?'intent':'read',$request);
        return ['id'=>$row['id'],'role'=>$user['role'],'csrf'=>$row['csrf'],'permissions'=>bois_p14_permissions($user['role']),'dir'=>$dir,'request'=>$request,'action'=>$action];
    });
}

function bois_p14_logout(array $config,string $token): void
{
    bois_p14_locked($config,function(&$s)use($token){unset($s['sessions'][hash('sha256',$token)]);});
}
function bois_p14_revoke(array $config,string $id): void
{
    bois_p14_locked($config,function(&$s,$users,$dir)use($id){
        if(!isset($users[$id]))throw new InvalidArgumentException('Kontot finns inte.');
        $actor=$GLOBALS['bois_p14_actor']??null;
        if($actor)bois_p14_audit($dir,$actor['id'],$actor['role'],'admin_revoke','intent',$actor['request'],$id);
        $s['epochs'][$id]=($s['epochs'][$id]??0)+1;
        foreach($s['sessions'] as $key=>$row)if($row['id']===$id)unset($s['sessions'][$key]);
    });
}
function bois_p14_cookie(string $token): void
{
    setcookie('__Host-BoISAdmin',$token,['expires'=>$token===''?1:0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
}
function bois_p14_request_authorize(array $config,string $action): void
{
    if(($config['mode']??'')==='production'&&($_SERVER['HTTPS']??'')!=='on')throw new DomainException('HTTPS krävs.');
    $write=($_SERVER['REQUEST_METHOD']??'GET')!=='GET';
    if($write&&(!is_string($_SERVER['HTTP_ORIGIN']??null)||!in_array($_SERVER['HTTP_ORIGIN'],$config['allowed_origins']??[],true)))throw new DomainException('Otillåtet ursprung.');
    $context=bois_p14_authorize($config,$action,(string)($_COOKIE['__Host-BoISAdmin']??''),(string)($_SERVER['HTTP_X_BOIS_CSRF']??''),$write);
    $GLOBALS['bois_p14_actor']=$context;
}
function bois_p14_finish(int $status): void
{
    $actor=$GLOBALS['bois_p14_actor']??null;unset($GLOBALS['bois_p14_actor']);
    if($actor&&($_SERVER['REQUEST_METHOD']??'GET')!=='GET')bois_p14_audit($actor['dir'],$actor['id'],$actor['role'],$actor['action'],$status<400?'success':'failed',$actor['request']);
}
