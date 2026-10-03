const {mkdtempSync,mkdirSync,readFileSync,readdirSync,chmodSync,symlinkSync,unlinkSync,openSync,closeSync,ftruncateSync,rmSync}=require('node:fs');
const {tmpdir}=require('node:os');
const {join,resolve}=require('node:path');
const {spawn,execFileSync}=require('node:child_process');
const assert=require('node:assert/strict');
const dir=mkdtempSync(join(tmpdir(),'bois-p15-'));
const fixture=mode=>execFileSync('php',['tests/p15-fixture.php',dir,mode],{stdio:'inherit',env:{...process.env,BOIS_P15_DISPOSABLE:'YES'}});
const cli=()=>{try{return {code:0,text:execFileSync('php',['ops/p15-readiness.php',join(dir,'private/config.php')],{stdio:['ignore','pipe','ignore']}).toString()};}catch(e){return {code:e.status,text:e.stdout.toString()};}};
(async()=>{
  fixture('seed');const server=spawn('php',['-d','display_errors=0','-d','disable_functions=mail,curl_exec,curl_init','-S','127.0.0.1:8775','-t',join(dir,'public')],{stdio:'ignore'});
  try{
    const probe=async(action,status,body,options={})=>{
      const r=await fetch('http://127.0.0.1:8775/commerce-api.php?action='+action,options);
      assert.equal(r.status,status);assert.deepEqual(await r.json(),body);
      assert.match(r.headers.get('x-request-id'),/^[a-f0-9]{24}$/);assert.equal(r.headers.get('cache-control'),'no-store');return r.headers.get('x-request-id');
    };
    for(let n=0;n<50;n++){try{await fetch('http://127.0.0.1:8775/commerce-api.php?action=liveness');break;}catch{await new Promise(r=>setTimeout(r,100));}}
    const rid=await probe('readiness',200,{ready:true},{headers:{'X-Request-ID':'NEVER_LOG_THIS_PERSON','Authorization':'Bearer NEVER_LOG_THIS_CREDENTIAL'}});
    assert.equal(cli().code,0);await probe('readiness',405,{error:'Metoden är inte tillåten.'},{method:'POST'});
    await probe('health',503,{error:'Butiken är inte öppen.'});
    fixture('unchanged');
    fixture('queue');await probe('readiness',503,{ready:false});assert.equal(cli().code,1);
    fixture('transport');fixture('failed-queue');await probe('readiness',503,{ready:false});
    fixture('clean');await probe('readiness',200,{ready:true});
    const logs=join(dir,'private/logs'),file=join(logs,readdirSync(logs)[0]);
    let rows=readFileSync(file,'utf8').trim().split('\n').map(JSON.parse);
    assert(rows.some(r=>r.request===rid&&r.event==='request_completed'&&r.status===200));
    assert(rows.some(r=>r.event==='outbox_failed'));assert(rows.some(r=>r.event==='webhook_failed'));
    assert.equal(readFileSync(file,'utf8').includes('NEVER_LOG'),false);
    for(const row of rows)assert.deepEqual(Object.keys(row),['time','request','component','event','status','duration_ms']);
    chmodSync(logs,0755);await probe('readiness',500,{error:'Ett internt fel uppstod.'});chmodSync(logs,0700);
    unlinkSync(file);symlinkSync('/dev/full',file);await probe('checkout',500,{error:'Ett internt fel uppstod.'},{method:'POST'});unlinkSync(file);
    await probe('readiness',200,{ready:true});
    const fd=openSync(file,'r+');ftruncateSync(fd,10*1024*1024);closeSync(fd);
    await probe('checkout',500,{error:'Ett internt fel uppstod.'},{method:'POST'});unlinkSync(file);
    fixture('unchanged');fixture('bad-db');await probe('readiness',503,{ready:false});await probe('liveness',200,{ok:true});assert.equal(cli().code,1);
    rows=readFileSync(file,'utf8').trim().split('\n').map(JSON.parse);assert(rows.some(r=>r.event==='ops_failed'));
    assert.equal(readFileSync(file,'utf8').includes('NEVER_LOG'),false);
    // Pure threshold tests cover both sides of the documented disk alarms.
    execFileSync('php',['-r',`require '${resolve('server/p15_observability.php')}'; if(bois_p15_disk_ok(49*1024*1024,1024*1024*1024,true)||bois_p15_disk_ok(255*1024*1024,1024*1024*1024)||!bois_p15_disk_ok(512*1024*1024,1024*1024*1024)||bois_p15_disk_ok(512*1024*1024,100*1024*1024*1024))exit(1);`]);
    console.log('P15_HTTP_SELECT_ONLY_DB_FAILURE_QUEUE_DISK_LOG_PRIVACY: pass');
  }finally{server.kill();await new Promise(r=>server.once('exit',r));rmSync(dir,{recursive:true,force:true});}
})().catch(e=>{console.error(e.message);process.exitCode=1;});
