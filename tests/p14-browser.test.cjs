const {chromium}=require('playwright');
const {mkdtempSync,cpSync,mkdirSync,rmSync}=require('node:fs');
const {tmpdir}=require('node:os');
const {join,resolve}=require('node:path');
const {spawn,execFileSync}=require('node:child_process');
const {createHmac}=require('node:crypto');
const assert=require('node:assert/strict');
function otp(){const b=Buffer.alloc(8);b.writeBigUInt64BE(BigInt(Math.floor(Date.now()/30000)));const h=createHmac('sha1','12345678901234567890').update(b).digest(),o=h[19]&15;return String((h.readUInt32BE(o)&0x7fffffff)%1000000).padStart(6,'0');}
(async()=>{
  const dir=mkdtempSync(join(tmpdir(),'bois-p14-browser-'));mkdirSync(join(dir,'public'));
  cpSync(resolve('site/commerce'),join(dir,'public'),{recursive:true});
  execFileSync('php',['tests/p14-browser-fixture.php',dir],{stdio:'inherit',env:{...process.env,BOIS_P14_DISPOSABLE:'YES'}});
  const server=spawn('php',['-S','localhost:8765','-t',join(dir,'public')],{stdio:'ignore'});
  let browser;
  try{
    for(let n=0;n<50;n++){try{await fetch('http://localhost:8765/admin.html');break;}catch{await new Promise(r=>setTimeout(r,100));}}
    browser=await chromium.launch();
    for(const [id,role] of [['tech','superadmin'],['club','club_admin'],['reader','operator']]){
      const context=await browser.newContext(),page=await context.newPage(),errors=[];
      page.on('pageerror',e=>errors.push(e.message));
      await page.goto('http://localhost:8765/admin.html');
      await page.locator('#personalFields').waitFor({state:'visible'});
      await page.locator('#username').fill(id);await page.locator('#password').fill('fixture-'+id);await page.locator('#otp').fill(otp());
      await page.getByRole('button',{name:'Öppna admin',exact:true}).click();
      await page.locator('#dashboard').waitFor({state:'visible'});
      assert.match(await page.locator('#personalIdentity').innerText(),new RegExp(role));
      assert.equal(await page.locator('#runWorker').isVisible(),id==='tech');
      assert.equal(await page.locator('#nordicExport').isVisible(),id!=='reader');
      const cookie=(await context.cookies()).find(c=>c.name==='__Host-BoISAdmin');
      assert(cookie?.secure&&cookie?.httpOnly&&cookie?.sameSite==='Strict');
      assert.equal(await page.evaluate(()=>sessionStorage.getItem('boisP3Admin')),null);
      // Reload resumes the server-side session, without passwords or OTP storage.
      await page.reload();await page.locator('#dashboard').waitFor({state:'visible'});
      await page.getByRole('button',{name:'Logga ut',exact:true}).click();
      await page.locator('#login').waitFor({state:'visible'});
      assert.equal((await context.cookies()).some(c=>c.name==='__Host-BoISAdmin'),false);
      assert.deepEqual(errors,[]);await context.close();
    }
    console.log('P14_BROWSER_PASSWORD_TOTP_ROLES_RELOAD_LOGOUT: pass');
  }finally{await browser?.close();server.kill();rmSync(dir,{recursive:true,force:true});}
})().catch(e=>{console.error(e.message);process.exitCode=1;});
