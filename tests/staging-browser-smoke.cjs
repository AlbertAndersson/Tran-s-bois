'use strict';
// Runs only after the protected synthetic staging deployment. No real names or mail.
const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const base=process.env.BOIS_BASE_URL;
const adminToken=process.env.BOIS_ADMIN_TOKEN;
if(!base||!adminToken)throw Error('Synthetic staging URL and private admin token required.');
const output=process.env.RUNNER_TEMP?path.join(process.env.RUNNER_TEMP,'bois-browser-evidence'):path.join('/tmp','bois-browser-evidence');
fs.mkdirSync(output,{recursive:true});
const key=()=>Math.random().toString(36).slice(2,12);

(async()=>{
  const browser=await chromium.launch({headless:true});
  try{
    for(const width of [375,390,1280]){
      const context=await browser.newContext({viewport:{width,height:850},deviceScaleFactor:1});
      const page=await context.newPage();
      await page.goto(base+'/');
      await page.locator('#bois-consent').waitFor({state:'visible'});
      assert.equal(await page.evaluate(()=>sessionStorage.getItem('boisSalesSession')),null);
      assert.equal(await page.evaluate(()=>sessionStorage.getItem('boisSalesAttribution')),null);
      assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),true,'horizontal overflow on shop '+width);
      await page.screenshot({path:path.join(output,'shop-'+width+'.png')});
      await page.getByRole('button',{name:'Avvisa statistik'}).click();
      await page.goto(base+'/membership.html');
      await page.locator('#membershipForm').waitFor();
      assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),true,'horizontal overflow on membership '+width);
      await page.screenshot({path:path.join(output,'membership-'+width+'.png')});
      if(width===390){
        await page.locator('#existingMember').check();
        await page.locator('#buyerName').fill('Bo Test');
        await page.locator('#email').fill('bo-'+key()+'@example.invalid');
        await page.locator('#consent').check();
        await page.locator('#submitBtn').click();
        await page.getByRole('link',{name:/Gå till testbetalning/}).click();
        await page.locator('#cardBtn').click();
        await page.locator('#failBtn').click();
        await page.locator('#retryBtn').click();
        await page.locator('#swishBtn').click();
        await page.locator('#cancelBtn').click();
        await page.locator('#retryBtn').click();
        await page.locator('#cardBtn').click();
        await page.locator('#payBtn').click();
        await page.locator('#orderLink').click();
        await page.locator('#payment').getByText('PAID').waitFor();
        await page.locator('#p4StatusBody').getByText('PENDING_MEMBER_VERIFICATION').waitFor();
        await page.screenshot({path:path.join(output,'existing-member-retry-390.png')});
        await context.close();continue;
      }
      if(width===1280){await context.close();continue;}

      await page.locator('#memberName').fill('Ada Test');
      await page.locator('#buyerName').fill('Ada Test');
      await page.locator('#email').fill('ada-'+key()+'@example.invalid');
      await page.locator('#consent').check();
      await page.locator('#submitBtn').click();
      await page.getByRole('link',{name:/Gå till testbetalning/}).waitFor();
      assert.equal(await page.evaluate(()=>sessionStorage.getItem('boisSalesSession')),null);
      await page.getByRole('link',{name:/Gå till testbetalning/}).click();
      await page.locator('#cardBtn').click();
      await page.locator('#payBtn').click();
      await page.getByText('Signerad testwebhook verifierad.').waitFor();
      await page.locator('#orderLink').click();
      await page.locator('#payment').getByText('PAID').waitFor();
      await page.screenshot({path:path.join(output,'paid-without-statistics-375.png')});
      await page.getByRole('link',{name:'Kakinställningar'}).first().click();
      await page.getByRole('button',{name:'Acceptera statistik'}).click();
      await page.goto(base+'/match-kit.html?utm_source=browser&utm_campaign=synthetic');
      await page.locator('#kitForm').waitFor();
      await page.locator('#player').fill('Cia Test');
      await page.locator('#number').fill('17');
      await page.locator('#buyerName').fill('Cia Test');
      await page.locator('#email').fill('cia-'+key()+'@example.invalid');
      await page.locator('#consent').check();
      await page.locator('#submitBtn').click();
      await page.getByRole('link',{name:/Gå till testbetalning/}).waitFor();
      assert.ok(await page.evaluate(()=>sessionStorage.getItem('boisSalesSession')));
      await page.getByRole('link',{name:'Kakinställningar'}).first().click();
      await page.getByRole('button',{name:'Avvisa statistik'}).click();
      assert.equal(await page.evaluate(()=>sessionStorage.getItem('boisSalesSession')),null);
      assert.equal(await page.evaluate(()=>sessionStorage.getItem('boisSalesAttribution')),null);
      await page.screenshot({path:path.join(output,'match-kit-375.png')});
      await context.close();
    }

    const context=await browser.newContext({viewport:{width:1280,height:850}});
    const admin=await context.newPage();
    await admin.goto(base+'/admin.html');
    await admin.locator('#token').fill(adminToken);
    await admin.getByRole('button',{name:'Öppna admin'}).click();
    await admin.locator('#dashboard').waitFor({state:'visible'});
    for(const id of ['admin-orders','admin-membership','admin-batches','admin-payments','admin-sales']){
      assert.equal(await admin.locator('#'+id).count(),1,'admin navigation '+id);
    }
    await admin.screenshot({path:path.join(output,'admin-desktop.png')});
    await context.close();
    console.log('CHROMIUM_HEADLESS_VIEWPORTS: 375,390,1280');
    console.log('MOBILE_NO_CHOICE_REJECT_PURCHASE_MOCK_PAID: pass');
    console.log('EXISTING_MEMBER_FAILED_CANCELLED_RETRY: pass');
    console.log('CONSENT_ACCEPT_ATTRIBUTION_WITHDRAWAL: pass');
    console.log('ADMIN_SECTIONS_WITH_PRIVATE_STAGING_TOKEN: pass');
    console.log('SCREENSHOTS: '+output);
  }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
