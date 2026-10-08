'use strict';
// Runs only after the protected synthetic staging deployment. No real names or mail.
const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const base=process.env.BOIS_BASE_URL;
const adminToken=process.env.BOIS_ADMIN_TOKEN;
const demoPassword=process.env.DEMO_PASSWORD;
const demoUsername=process.env.DEMO_USERNAME||'bois-demo';
if(!base||!adminToken||!demoPassword)throw Error('Synthetic staging URL and private credentials required.');
const output=process.env.RUNNER_TEMP?path.join(process.env.RUNNER_TEMP,'bois-browser-evidence'):path.join('/tmp','bois-browser-evidence');
fs.mkdirSync(output,{recursive:true});
const gymEnabled=process.env.BOIS_TEST_GYM_ENABLED!=='false';
const key=()=>Math.random().toString(36).slice(2,12);

(async()=>{
  const browser=await chromium.launch({headless:true,...(process.env.BOIS_BROWSER_CHANNEL?{channel:process.env.BOIS_BROWSER_CHANNEL}:{})});
  let existingOrderId='',matchOrderId='',refundOrderId='',refundSession=null;
  try{
    for(const width of [375,390,1280]){
      const context=await browser.newContext({viewport:{width,height:850},deviceScaleFactor:1,httpCredentials:{username:demoUsername,password:demoPassword}});
      const page=await context.newPage();
      await page.goto(base+'/');
      await page.locator('#bois-consent').waitFor({state:'visible'});
      await page.waitForFunction(()=>document.activeElement?.matches('#bois-consent [data-choice="false"]'));
      assert.equal(await page.evaluate(()=>document.activeElement?.textContent),'Avvisa statistik');
      const inventory=await page.evaluate(()=>({
        sessionKeys:Object.keys(sessionStorage),localKeys:Object.keys(localStorage),
        resourceOrigins:[...new Set(performance.getEntriesByType('resource').map(entry=>new URL(entry.name).origin))]
      }));
      const cookieNames=(await context.cookies()).map(cookie=>cookie.name);
      console.log('C1_BEFORE_CHOICE_'+width+': '+JSON.stringify({cookieNames,...inventory}));
      assert.equal(await page.evaluate(()=>sessionStorage.getItem('boisSalesSession')),null);
      assert.equal(await page.evaluate(()=>sessionStorage.getItem('boisSalesAttribution')),null);
      assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),true,'horizontal overflow on shop '+width);
      await page.screenshot({path:path.join(output,'shop-'+width+'.png')});
      if(width!==375){
        await page.getByRole('button',{name:'Avvisa statistik'}).click();
        await page.locator('#bois-consent').waitFor({state:'hidden'});
      }
      await page.goto(base+'/membership.html');
      await page.locator('#membershipForm').waitFor();
      await page.locator('#gymStock').filter({hasNotText:'Kontrollerar'}).waitFor();
      if(!gymEnabled){
        if((await page.locator('#gymStock').innerText()).includes('Slutsåld')){
          assert.equal(await page.locator('#addGym').isDisabled(),true,'sold-out gym choice disabled');
          assert.equal(await page.locator('#addGym').isChecked(),false,'sold-out gym choice unchecked');
          console.log('NORDIC_SOLD_OUT_BROWSER_'+width+': pass');
        }else await page.locator('#addGym').uncheck();
      }
      assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),true,'horizontal overflow on membership '+width);
      await page.screenshot({path:path.join(output,'membership-'+width+'.png')});
      if(width===390){
        if(gymEnabled) await page.locator('#existingMember').check();
        else await page.locator('#memberName').fill('Bo Test');
        await page.locator('#buyerName').fill('Bo Test');
        await page.locator('#email').fill('bo-'+key()+'@example.invalid');
        await page.locator('#consent').check();
        await page.locator('#submitBtn').click();
        existingOrderId=await page.locator('#success .order-id').innerText();
        await page.getByRole('link',{name:/Gå till testbetalning/}).click();
        await page.locator('#orderSummary').getByText('Betalstatus').waitFor();
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
        assert.equal(await page.locator('#retryPayment').isVisible(),false);
        await page.locator('#p4StatusBody').getByText(gymEnabled?'PENDING_MEMBER_VERIFICATION':'ACTIVE',{exact:true}).waitFor();
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
      refundOrderId=await page.locator('#success .order-id').innerText();
      assert.equal(await page.evaluate(()=>sessionStorage.getItem('boisSalesSession')),null);
      await page.getByRole('link',{name:/Gå till testbetalning/}).click();
      await page.locator('#orderSummary').getByText('Betalstatus').waitFor();
      const apiResponses=[];
      let checkoutPayload=null;
      page.on('response',response=>{
        if(!response.url().includes('commerce-api.php'))return;
        const action=new URL(response.url()).searchParams.get('action');
        apiResponses.push({action,method:response.request().method(),status:response.status()});
        if(action==='checkout')response.json().then(body=>{checkoutPayload=body;}).catch(()=>{});
      });
      await page.locator('#cardBtn').click();
      await page.locator('#providerStep').waitFor({state:'visible',timeout:10000}).catch(async error=>{
        console.log('CHECKOUT_DIAGNOSTIC: '+JSON.stringify({
          apiResponses,errorText:await page.locator('#error').textContent()
        }));
        throw error;
      });
      for(let attempt=0;attempt<30&&!checkoutPayload;attempt++)await page.waitForTimeout(100);
      assert.ok(checkoutPayload?.checkout,'checkout response captured: '+JSON.stringify(apiResponses));
      refundSession=checkoutPayload.checkout;
      await page.locator('#payBtn').click();
      await page.getByText('Signerad testwebhook verifierad.').waitFor();
      await page.locator('#orderLink').click();
      await page.locator('#payment').getByText('PAID').waitFor();
      assert.equal(await page.locator('#retryPayment').isVisible(),false,'paid order must not offer another payment');
      await page.screenshot({path:path.join(output,'paid-without-statistics-375.png')});
      await page.getByRole('link',{name:'Kakinställningar'}).first().click();
      await page.getByRole('button',{name:'Acceptera statistik'}).click();
      await page.locator('#bois-consent').waitFor({state:'hidden'});
      await page.goto(base+'/match-kit.html?utm_source=browser&utm_campaign=synthetic');
      await page.locator('#kitForm').waitFor();
      await page.locator('#player').fill('Cia Test');
      await page.locator('#number').fill('17');
      await page.locator('#buyerName').fill('Cia Test');
      await page.locator('#email').fill('cia-'+key()+'@example.invalid');
      await page.locator('#consent').check();
      await page.locator('#submitBtn').click();
      await page.getByRole('link',{name:/Gå till testbetalning/}).waitFor();
      matchOrderId=await page.locator('#success .order-id').innerText();
      assert.ok(await page.evaluate(()=>sessionStorage.getItem('boisSalesSession')));
      await page.getByRole('link',{name:/Gå till testbetalning/}).click();
      await page.locator('#orderSummary').getByText('Betalstatus').waitFor();
      await page.locator('#swishBtn').click();
      await page.locator('#payBtn').click();
      await page.locator('#orderLink').click();
      await page.locator('#payment').getByText('PAID').waitFor();
      await page.getByRole('link',{name:'Kakinställningar'}).first().click();
      await page.getByRole('button',{name:'Avvisa statistik'}).click();
      await page.locator('#bois-consent').waitFor({state:'hidden'});
      assert.equal(await page.evaluate(()=>sessionStorage.getItem('boisSalesSession')),null);
      assert.equal(await page.evaluate(()=>sessionStorage.getItem('boisSalesAttribution')),null);
      await page.screenshot({path:path.join(output,'match-kit-375.png')});
      await context.close();
    }

    const context=await browser.newContext({viewport:{width:1280,height:850},httpCredentials:{username:demoUsername,password:demoPassword}});
    const admin=await context.newPage();
    await admin.goto(base+'/admin.html');
    await admin.locator('#token').fill(adminToken);
    await admin.getByRole('button',{name:'Öppna admin'}).click();
    await admin.locator('#dashboard').waitFor({state:'visible'});
    for(const id of ['admin-orders','admin-membership','admin-batches','admin-payments','admin-sales']){
      assert.equal(await admin.locator('#'+id).count(),1,'admin navigation '+id);
    }
    const pending=admin.locator('#entitlements tr').filter({hasText:existingOrderId});
    const answerMemberPrompt=async dialog=>{
      if(dialog.type()==='prompt')await dialog.accept(dialog.message().startsWith('Medlemmens')?'Bo Test':dialog.message().startsWith('Medlemstyp')?'adult':'');
      else await dialog.dismiss();
    };
    if(gymEnabled){
    admin.on('dialog',answerMemberPrompt);
    await pending.getByRole('button',{name:'Verifiera medlem'}).click();
    await admin.locator('#entitlements tr').filter({hasText:existingOrderId}).getByRole('button',{name:'Skickad till Nordic'}).waitFor();
    admin.off('dialog',answerMemberPrompt);
    }else console.log('NORDIC_MEMBER_VERIFICATION: unavailable annual quota; P3 CI coverage');
    assert.ok((await admin.locator('#orders').innerText()).includes(matchOrderId));
    assert.ok(Number(await admin.locator('#waitingItems').innerText())>=1);
    await admin.locator('#salesCampaigns').getByText('synthetic').waitFor();
    const waitingCount=Number(await admin.locator('#waitingItems').innerText());
    if(waitingCount===1){
      admin.once('dialog',dialog=>dialog.accept());
      await admin.locator('#batchNow').click();
      await admin.locator('#batchMessage').waitFor({state:'visible'});
      assert.match(await admin.locator('#batchMessage').innerText(),/Batch skapad/);
      console.log('ADMIN_SYNTHETIC_BATCH: pass');
    }else{
      assert.notEqual(process.env.BOIS_REQUIRE_BATCH,'true','Full host acceptance requires an isolated batch fixture');
      console.log('ADMIN_SYNTHETIC_BATCH: skipped; other waiting rows exist');
    }
    const refund=await admin.evaluate(async data=>{
      const response=await fetch('commerce-api.php?action=mock_payment_event',{
        method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)
      });
      return {ok:response.ok,status:response.status};
    },{session_ref:refundSession.session_ref,session_token:refundSession.session_token,outcome:'refunded',refund_ore:refundSession.amount_ore});
    assert.equal(refund.ok,true,'synthetic mock refund endpoint HTTP '+refund.status);
    await admin.reload();
    await admin.locator('#dashboard').waitFor({state:'visible'});
    assert.ok((await admin.locator('#payments').innerText()).includes('REFUNDED'));
    await admin.screenshot({path:path.join(output,'admin-desktop.png')});
    await context.close();
    console.log('CHROMIUM_HEADLESS_VIEWPORTS: 375,390,1280');
    console.log('MOBILE_NO_CHOICE_REJECT_PURCHASE_MOCK_PAID: pass');
    console.log(gymEnabled?'EXISTING_MEMBER_FAILED_CANCELLED_RETRY: pass':'MEMBER_ONLY_FAILED_CANCELLED_RETRY: pass');
    console.log('CONSENT_ACCEPT_ATTRIBUTION_WITHDRAWAL: pass');
    console.log('ADMIN_SECTIONS_WITH_PRIVATE_STAGING_TOKEN: pass');
    console.log(gymEnabled?'ADMIN_MEMBER_VERIFICATION_MATCH_QUEUE_MOCK_REFUND: pass':'ADMIN_MATCH_QUEUE_MOCK_REFUND: pass; Nordic member verification covered in isolated CI');
    console.log('DEMO_ORDER_REFS: '+JSON.stringify({existingOrderId,matchOrderId,refundOrderId}));
    console.log('SCREENSHOTS: '+output);
  }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
