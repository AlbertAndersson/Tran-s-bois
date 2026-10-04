'use strict';
const {chromium}=require('playwright');
const fs=require('node:fs'),path=require('node:path'),os=require('node:os');
const {spawn,execFileSync}=require('node:child_process');
const assert=require('node:assert/strict');
const {build,verify}=require('../scripts/p18-release.cjs');
const base='http://localhost:8766',env={...process.env,BOIS_P18_DISPOSABLE:'YES'};
const order=key=>({customer:{name:'P18 Synthetic',email:'p18@example.invalid'},items:[{sku:'NW-GYM-ANNUAL',quantity:1,metadata:{}}],existing_member:true,consent:true,website:'',idempotency_key:key});
(async()=>{
  const root=fs.mkdtempSync(path.join(os.tmpdir(),'bois-p18-browser-'));
  const php=(file,...args)=>execFileSync('php',['-d','disable_functions=curl_init,curl_exec,mail',file,root,...args],{stdio:'inherit',env});
  let server,browser;
  try{
    build(process.cwd(),root,process.env.GITHUB_SHA||'f'.repeat(40));verify(root);
    php('tests/p18-fixture.php');
    server=spawn('php',['-d','opcache.enable=0','-d','opcache.enable_cli=0','-d','disable_functions=curl_init,curl_exec,mail','-S','localhost:8766','-t',path.join(root,'public')],{stdio:'ignore',env});
    let ready=false;
    for(let n=0;n<50;n++){try{const r=await fetch(base+'/');if(r.ok){ready=true;break;}}catch{}await new Promise(r=>setTimeout(r,100));}
    assert(ready,'packaged server started');
    const request=async(action,status,body,headers={})=>{
      const r=await fetch(base+'/commerce-api.php?action='+action,{method:body?'POST':'GET',headers:{...(body?{'Content-Type':'application/json',Origin:base}:{}),...headers},...(body?{body:JSON.stringify(body)}:{})});
      const data=await r.json();assert.equal(r.status,status,action+' '+JSON.stringify(data));assert.equal(r.headers.get('cache-control'),'no-store');return data;
    };
    await request('health',200);
    await request('admin_orders',401);
    await request('catalog',403,null,{Origin:'https://denied.example.invalid'});
    await request('orders',422,{});
    await request('unknown_endpoint',404);
    // Exercise the existing full staging suite against the packaged code, including
    // membership, shirt-only kit, retry, mock refund, batches, and consent withdrawal.
    execFileSync(process.execPath,['tests/staging-browser-smoke.cjs'],{stdio:'inherit',env:{...env,BOIS_BASE_URL:base,BOIS_ADMIN_TOKEN:'p18-isolated-synthetic-admin',DEMO_PASSWORD:'synthetic',BOIS_TEST_GYM_ENABLED:'true'}});
    php('tests/p18-fixture.php','fill');
    const conflict=await request('orders',409,order('p18-http-soldout'));
    assert.match(conflict.error,/slutsålda/);assert.doesNotMatch(conflict.error,/behörig|inlogg/i);
    // Wrong customer token is authentication; a sold-out purchase is a conflict.
    const admin=await request('admin_orders',200,null,{'X-Bois-Admin-Token':'p18-isolated-synthetic-admin'});
    assert(admin.orders.length>0);
    await request('order&id='+encodeURIComponent(admin.orders[0].public_id)+'&token=wrong',401);
    browser=await chromium.launch({headless:true});
    for(const width of [375,390,1280]){
      const context=await browser.newContext({viewport:{width,height:850}}),page=await context.newPage();
      const errors=[];page.on('pageerror',e=>errors.push(e.message));
      await page.goto(base+'/membership.html');
      await page.locator('#gymStock').filter({hasText:/Slutsåld/}).waitFor();
      assert.equal(await page.locator('#addGym').isDisabled(),true);
      assert.equal(await page.locator('#addGym').isChecked(),false);
      assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),true);
      // The same public API client used by forms preserves the business message.
      const uiError=await page.evaluate(async input=>{try{await window.BOIS_COMMERCE.api('orders',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(input)});return '';}catch(e){return e.message;}},order('p18-ui-'+width));
      assert.match(uiError,/slutsålda/);assert.doesNotMatch(uiError,/behörig|inlogg/i);
      await page.goto(base+'/supporter-preview.html');
      assert.equal(await page.locator('form').count(),0,'2027 preview cannot submit orders');
      assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),true);
      assert.deepEqual(errors,[]);await context.close();
    }
    const beforeCount=admin.orders.length;
    const original=fs.readFileSync(path.join(root,'private/config.php'));
    php('tests/p18-http-state.php','closed');
    await request('orders',503,order('p18-closed'));
    // A closed gate works even with an unreachable DB. Internal errors stay generic.
    php('tests/p18-http-state.php','broken');
    const internal=await request('catalog',500);assert.deepEqual(internal,{error:'Ett internt fel uppstod.'});
    fs.writeFileSync(path.join(root,'private/config.php'),original);
    const after=await request('admin_orders',200,null,{'X-Bois-Admin-Token':'p18-isolated-synthetic-admin'});
    assert.equal(after.orders.length,beforeCount,'denied requests must not create orders');
    console.log('P18_PACKAGED_BROWSER_375_390_1280_AND_HTTP_401_403_409_422_404_500_503: pass');
  }finally{await browser?.close();server?.kill();fs.rmSync(root,{recursive:true,force:true});}
})().catch(e=>{console.error(e.message);process.exitCode=1;});
