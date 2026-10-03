<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/server/p14_admin.php';
function p14_ok(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
function p14_no(callable $fn): void {try{$fn();}catch(Throwable){return;}throw new RuntimeException('Unsafe action accepted.');}
$tmp=sys_get_temp_dir().'/bois-p14-'.bin2hex(random_bytes(8));mkdir($tmp,0700);mkdir($tmp.'/private',0700);mkdir($tmp.'/public',0755);
$secret='GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
foreach([59=>'94287082',1111111109=>'07081804',1111111111=>'14050471',1234567890=>'89005924',2000000000=>'69279037',20000000000=>'65353130'] as $time=>$expected)
    p14_ok(bois_p14_totp($secret,intdiv($time,30),8)===$expected,'RFC 6238 vector failed.');
$users=[];
foreach(['tech'=>'superadmin','club'=>'club_admin','reader'=>'operator','expired'=>'operator','disabled'=>'operator','locked'=>'operator','revoked'=>'operator','rolechange'=>'superadmin','rotated'=>'operator','absolute'=>'operator','deactivated'=>'operator'] as $id=>$role)
    $users[$id]=['enabled'=>$id!=='disabled','epoch'=>1,'role'=>$role,'password_hash'=>password_hash('synthetic-password-'.$id,PASSWORD_BCRYPT,['cost'=>12]),'totp_secret'=>$secret];
$save=function()use(&$users,$tmp){file_put_contents($tmp.'/private/users.json',json_encode($users,JSON_THROW_ON_ERROR));chmod($tmp.'/private/users.json',0600);};$save();
$cfg=['mode'=>'test','admin_token'=>str_repeat('synthetic-key-',4),'allowed_origins'=>['http://127.0.0.1'],
    '_security_dir'=>$tmp.'/private/rate','personal_admin'=>['enabled'=>true,'users_file'=>$tmp.'/private/users.json','state_dir'=>$tmp.'/private','public_root'=>$tmp.'/public']];
$login=fn(string $id)=>bois_p14_login($cfg,['username'=>$id,'password'=>'synthetic-password-'.$id,'otp'=>bois_p14_totp($secret,intdiv(time(),30))]);
$tech=$login('tech');$club=$login('club');$reader=$login('reader');
p14_no(fn()=>$login('disabled'));p14_no(fn()=>$login('reader'));
p14_no(fn()=>bois_p14_login($cfg,['username'=>'club','password'=>'wrong','otp'=>'000000']));
p14_no(fn()=>bois_p14_authorize($cfg,'admin_payments',$reader['token']));
p14_no(fn()=>bois_p14_authorize($cfg,'admin_run_worker',$club['token']));
p14_no(fn()=>bois_p14_authorize($cfg,'admin_simulate_paid',$tech['token'],$tech['csrf'],true));
p14_no(fn()=>bois_p14_authorize($cfg,'admin_unknown',$tech['token']));
p14_no(fn()=>bois_p14_authorize($cfg,'admin_batch_now',$club['token'],'',true));
p14_ok(bois_p14_authorize($cfg,'admin_batch_now',$club['token'],$club['csrf'],true)['id']==='club','Role/CSRF positive failed.');
$a=bois_p14_authorize($cfg,'admin_revoke',$tech['token'],$tech['csrf'],true);bois_p14_revoke($cfg,'reader');
p14_no(fn()=>bois_p14_authorize($cfg,'admin_orders',$reader['token']));
bois_p14_logout($cfg,$club['token']);p14_no(fn()=>bois_p14_authorize($cfg,'admin_session',$club['token']));
$expired=$login('expired');$statePath=$tmp.'/private/state.json';$state=json_decode(file_get_contents($statePath),true);
$state['sessions'][hash('sha256',$expired['token'])]['last']=time()-901;file_put_contents($statePath,json_encode($state));
p14_no(fn()=>bois_p14_authorize($cfg,'admin_orders',$expired['token']));
$revoked=$login('revoked');$users['revoked']['epoch']++;$save();p14_no(fn()=>bois_p14_authorize($cfg,'admin_orders',$revoked['token']));
$changed=$login('rolechange');$users['rolechange']['role']='operator';$save();p14_no(fn()=>bois_p14_authorize($cfg,'admin_payments',$changed['token']));
$rotated=$login('rotated');$users['rotated']['password_hash']=password_hash('synthetic-new-password',PASSWORD_BCRYPT,['cost'=>12]);$save();p14_no(fn()=>bois_p14_authorize($cfg,'admin_orders',$rotated['token']));
$deactivated=$login('deactivated');$users['deactivated']['enabled']=false;$save();p14_no(fn()=>bois_p14_authorize($cfg,'admin_orders',$deactivated['token']));
$absolute=$login('absolute');$state=json_decode(file_get_contents($statePath),true);$state['sessions'][hash('sha256',$absolute['token'])]['expires']=time()-1;file_put_contents($statePath,json_encode($state));p14_no(fn()=>bois_p14_authorize($cfg,'admin_orders',$absolute['token']));
for($i=0;$i<5;$i++)p14_no(fn()=>bois_p14_login($cfg,['username'=>'locked','password'=>'wrong','otp'=>'000000']));
p14_no(fn()=>$login('locked'));
chmod($tmp.'/private/users.json',0644);p14_no(fn()=>bois_p14_paths($cfg));chmod($tmp.'/private/users.json',0600);
$bad=$cfg;$bad['personal_admin']['state_dir']=$tmp.'/public';chmod($tmp.'/public',0700);p14_no(fn()=>bois_p14_paths($bad));
$audit=file_get_contents($tmp.'/private/audit.jsonl');
p14_ok(!str_contains($audit,$secret)&&!str_contains($audit,'synthetic-password')&&!str_contains($audit,$tech['token'])&&!str_contains($audit,$tech['csrf']),'Sensitive audit leak.');
// Real HTTP login/session/logout/revoke and legacy-token rejection before DB.
$socket=stream_socket_server('tcp://127.0.0.1:0');$address=stream_socket_get_name($socket,false);fclose($socket);
$origin='http://'.$address;$cfg['allowed_origins']=[$origin];
$cfg['db']=['host'=>'127.0.0.1','port'=>1,'database'=>'must_not_connect','user'=>'none','password'=>'none'];
$cfg['mode']='test';$users['http-user']=$users['club'];$users['http-tech']=$users['tech'];$users['http-tech']['password_hash']=password_hash('http-secret',PASSWORD_BCRYPT,['cost'=>12]);$save();
$cp=$tmp.'/private/config.php';file_put_contents($cp,'<?php return '.var_export($cfg,true).';');chmod($cp,0600);
file_put_contents($tmp.'/public/commerce-api.php',"<?php putenv('BOIS_P3_CONFIG_PATH=".$cp."'); require ".var_export(dirname(__DIR__).'/server/commerce-api.php',true).";");
$server=proc_open([PHP_BINARY,'-S',$address,'-t',$tmp.'/public'],[0=>['file','/dev/null','r'],1=>['file',$tmp.'/private/http.log','a'],2=>['file',$tmp.'/private/http.log','a']],$pipes);
$http=function(string $action,string $method='GET',array $body=[],array $extra=[])use($origin):array{
    $headers=array_merge(['Content-Type: application/json','Origin: '.$origin],$extra);
    $ctx=stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),'content'=>$method==='POST'?json_encode($body):'','ignore_errors'=>true,'timeout'=>5]]);
    $raw=@file_get_contents($origin.'/commerce-api.php?action='.$action,false,$ctx);
    preg_match('/\s(\d{3})\s/',$http_response_header[0]??'',$m);return [(int)($m[1]??0),json_decode($raw?:'{}',true),$http_response_header??[]];
};
try{
    for($i=0;$i<30;$i++){if($http('admin_session')[0]!==0)break;usleep(100000);}
    p14_ok($http('admin_orders','GET',[],['X-Bois-Admin-Token: '.$cfg['admin_token']])[0]===401,'Shared key bypassed personal auth.');
    [$status,$body,$headers]=$http('admin_login','POST',['username'=>'http-tech','password'=>'http-secret','otp'=>bois_p14_totp($secret,intdiv(time(),30))]);
    p14_ok($status===200&&!isset($body['session']['token']),'HTTP login failed/leaked session token.');
    $cookie='';foreach($headers as $h)if(str_starts_with(strtolower($h),'set-cookie:'))$cookie=$h;
    p14_ok(str_contains($cookie,'__Host-BoISAdmin=')&&str_contains(strtolower($cookie),'secure')&&str_contains(strtolower($cookie),'httponly')&&str_contains($cookie,'SameSite=Strict'),'Cookie flags missing.');
    preg_match('/__Host-BoISAdmin=([a-f0-9]{64})/',$cookie,$m);$auth=['Cookie: __Host-BoISAdmin='.$m[1],'X-Bois-CSRF: '.$body['session']['csrf']];
    p14_ok($http('admin_session','GET',[],$auth)[0]===200,'HTTP session failed.');
    p14_ok($http('admin_logout','POST',[],[$auth[0]])[0]===401,'Logout without CSRF accepted.');
    p14_ok($http('admin_revoke','POST',['username'=>'http-user'],$auth)[0]===200,'HTTP revoke failed.');
    p14_ok($http('admin_logout','POST',[],$auth)[0]===200,'HTTP logout failed.');
    p14_ok($http('admin_session','GET',[],$auth)[0]===401,'Logged out session remained valid.');
    p14_ok($http('admin_login','POST',['username'=>'http-tech','password'=>'http-secret','otp'=>bois_p14_totp($secret,intdiv(time(),30))])[0]===401,'HTTP OTP replay accepted.');
}finally{if(is_resource($server))proc_terminate($server);}
$events=array_map(fn($line)=>json_decode($line,true),array_filter(explode("\n",file_get_contents($tmp.'/private/audit.jsonl'))));
$intents=array_filter($events,fn($r)=>$r['action']==='admin_revoke'&&$r['outcome']==='intent');
$completed=false;foreach($intents as $intent)foreach($events as $event)if($event['request']===$intent['request']&&$event['outcome']==='success')$completed=true;
p14_ok($completed,'Missing correlated mutation audit completion.');
p14_ok(count(array_filter($events,fn($r)=>($r['target']??'')==='http-user'&&$r['action']==='admin_revoke'))===1,'Revoke target missing from audit.');
rename($tmp.'/private/audit.jsonl',$tmp.'/private/audit-preserved.jsonl');symlink($tmp.'/public/audit.jsonl',$tmp.'/private/audit.jsonl');
p14_no(fn()=>bois_p14_authorize($cfg,'admin_orders',$tech['token']));
unlink($tmp.'/private/audit.jsonl');rename($tmp.'/private/audit-preserved.jsonl',$tmp.'/private/audit.jsonl');
echo "P14_RFC6238_PASSWORD_MFA_ROLES_SESSION_CSRF_REVOKE_AUDIT_HTTP: pass\nREAL_ACCOUNTS_PRODUCTION_EMAIL_PAYMENT: no\n";
