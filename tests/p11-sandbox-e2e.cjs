'use strict';
const {chromium,request}=require('playwright');
const assert=require('node:assert/strict');
const crypto=require('node:crypto');
const fs=require('node:fs');
const path=require('node:path');
const base=process.env.BOIS_BASE_URL;
const key=process.env.STRIPE_TEST_API_KEY;
const secret=process.env.STRIPE_TEST_WEBHOOK_SECRET;
const admin=process.env.BOIS_ADMIN_TOKEN;
const password=process.env.DEMO_PASSWORD;
assert.equal(base,'https://alberiq.se/bois-shop-stripe-sandbox');
assert.match(key,/^(rk|sk)_test_/);assert.match(secret,/^whsec_/);
const output=path.join(process.env.RUNNER_TEMP,'bois-p11-evidence');fs.mkdirSync(output,{recursive:true});
const suffix=crypto.randomBytes(8).toString('hex');
const delay=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{
  const api=await request.newContext({httpCredentials:{username:'bois-demo',password},userAgent:'AlberIQ-BoIS-Stripe-Sandbox-Check/1.0'});
  const callbacks=await request.newContext({userAgent:'AlberIQ-BoIS-Stripe-Sandbox-Check/1.0'});
  const browser=await chromium.launch({headless:true});
  const context=await browser.newContext({viewport:{width:390,height:850},httpCredentials:{username:'bois-demo',password,origin:new URL(base).origin}});
  const page=await context.newPage();
  const json=async(action,data=null,adminOnly=false)=>{
    const options={headers:adminOnly?{'X-Bois-Admin-Token':admin}:{}};
    const response=data===null?await api.get(base+'/commerce-api.php?action='+action,options):await api.post(base+'/commerce-api.php?action='+action,{...options,data});
    assert.ok(response.ok(),'BoIS '+action+' HTTP '+response.status());return response.json();
  };
  const stripe=async(ref)=>{
    assert.match(ref,/^cs_test_/);
    const response=await fetch('https://api.stripe.com/v1/checkout/sessions/'+ref,{headers:{Authorization:'Bearer '+key}});
    assert.equal(response.ok,true,'Stripe sandbox session read HTTP '+response.status());return response.json();
  };
  const create=async(label)=>{
    const data={customer:{name:'P11 Test',email:'p11-'+label+'-'+suffix+'@example.invalid',phone:''},items:[{sku:'MEM-ADULT',quantity:1,metadata:{member_name:'P11 Test'}}],existing_member:false,consent:true,website:'',idempotency_key:'p11-'+label+'-'+suffix};
    const first=(await json('orders',data)).order;
    const again=(await json('orders',data)).order;assert.equal(first.public_id,again.public_id,'Order create idempotency');
    const checkout=(await json('checkout',{public_id:first.public_id,public_token:first.public_token,method:'card'})).checkout;
    const session=await stripe(checkout.session_ref);
    assert.equal(session.livemode,false);assert.equal(session.currency,'sek');assert.equal(session.amount_total,first.total_ore);
    assert.equal(session.client_reference_id,first.public_id);assert.equal(session.metadata.bois_order_public_id,first.public_id);
    assert.match(session.url,/^https:\/\/checkout\.stripe\.com\//);
    return {order:first,checkout,session};
  };
  const state=async(fixture)=>(await json('order&id='+encodeURIComponent(fixture.order.public_id)+'&token='+encodeURIComponent(fixture.order.public_token))).order;
  const waitState=async(fixture,wanted)=>{
    for(let i=0;i<24;i++){const order=await state(fixture);if(order.payment_status===wanted)return order;await delay(5000);}
    throw Error('Signed Stripe delivery did not produce '+wanted+' within 120s');
  };
  const callback=async(event,t=Math.floor(Date.now()/1000),bad=false)=>{
    const raw=JSON.stringify(event);
    const signature=crypto.createHmac('sha256',secret).update(t+'.'+raw).digest('hex');
    return callbacks.post(process.env.BOIS_WEBHOOK_URL,{headers:{'Content-Type':'application/json','Stripe-Signature':'t='+t+',v1='+(bad?'0'.repeat(64):signature)},data:raw});
  };
  const fillCard=async(number)=>{
    await page.locator('#cardNumber').fill(number);
    await page.locator('#cardExpiry').fill('1230');
    await page.locator('#cardCvc').fill('123');
    await page.locator('#billingName').fill('P11 Test');
    const postal=page.locator('#billingPostalCode');if(await postal.isVisible())await postal.fill('12345');
    await page.getByRole('button',{name:/Betala|Pay/}).click();
  };
  try{
    const health=await json('health');assert.equal(health.payment_provider,'stripe');assert.equal(health.payment_mode,'test');assert.equal(health.production_launch_ready,false);assert.equal(health.mail_transport,'disabled');
    const paid=await create('success');
    await page.goto(paid.session.url);await page.locator('#cardNumber').waitFor({timeout:30000});
    await page.screenshot({path:path.join(output,'checkout-test-390.png')});
    await fillCard('4242424242424242');
    const paidOrder=await waitState(paid,'PAID');
    const paidSession=await stripe(paid.checkout.session_ref);assert.equal(paidSession.payment_status,'paid');assert.equal(paidSession.livemode,false);
    const payments=await json('admin_payments',null,true);
    const delivery=payments.events.find(e=>e.provider_ref===paid.checkout.session_ref&&e.event_type==='payment.succeeded'&&e.status==='PROCESSED');
    assert.ok(delivery&&delivery.event_id.startsWith('evt_'),'Real Stripe event recorded before synthetic callback checks');
    console.log('P11_HOSTED_TEST_CARD_SIGNED_STRIPE_WEBHOOK_PAID: pass');
    await page.goto(base+'/order.html?id='+encodeURIComponent(paid.order.public_id)+'&token='+encodeURIComponent(paid.order.public_token));
    await page.locator('#payment').getByText('PAID',{exact:true}).waitFor();
    await page.screenshot({path:path.join(output,'paid-390.png')});
    const event={id:'evt_p11_replay_'+suffix,type:'checkout.session.completed',livemode:false,data:{object:paidSession}};
    assert.equal((await callback(event,Math.floor(Date.now()/1000)-600)).status(),400);
    assert.equal((await callback(event,undefined,true)).status(),400);
    const wrong={...event,id:'evt_p11_amount_'+suffix,data:{object:{...paidSession,amount_total:paidSession.amount_total+1}}};
    assert.equal((await callback(wrong)).status(),400);
    const firstReplay=await callback(event);assert.equal(firstReplay.status(),200);
    const repeated=await callback(event);assert.equal(repeated.status(),200);assert.equal((await repeated.json()).duplicate,true);
    assert.equal(JSON.stringify((await state(paid)).p4),JSON.stringify(paidOrder.p4),'Replay must not duplicate fulfillment');
    console.log('P11_ORDER_METADATA_AMOUNT_CURRENCY_IDEMPOTENCY_SIGNATURE_TIMESTAMP: pass');
    const cancel=await create('cancel');await page.goto(cancel.session.url);await page.locator('#cardNumber').waitFor();
    // Stripe's return link leaves the session unpaid; a return page is not a settlement event.
    const back=page.getByRole('link',{name:/Tillbaka|Back/});await back.click();
    await page.waitForURL(new RegExp('bois-shop-stripe-sandbox/payment.html'));
    assert.equal((await state(cancel)).payment_status,'PENDING');
    console.log('P11_HOSTED_CANCEL_RETURN_NO_FALSE_PAID: pass');
    const decline=await create('decline');await page.goto(decline.session.url);await page.locator('#cardNumber').waitFor();
    await fillCard('4000000000000002');
    await page.getByText(/nekades|declined/i).first().waitFor({timeout:30000});
    assert.equal((await state(decline)).payment_status,'PENDING');
    await page.screenshot({path:path.join(output,'decline-390.png')});
    console.log('P11_HOSTED_TEST_CARD_DECLINE_NO_FALSE_PAID: pass');
    const refund=(await json('admin_stripe_refund',{public_id:paid.order.public_id,amount_ore:paid.order.total_ore},true)).refund;
    assert.match(refund.refund_id,/^re_/);
    const refunded=await waitState(paid,'REFUNDED');assert.equal(refunded.fulfillment_status,'REVIEW_REQUIRED');
    await page.goto(base+'/order.html?id='+encodeURIComponent(paid.order.public_id)+'&token='+encodeURIComponent(paid.order.public_token));
    await page.locator('#payment').getByText('REFUNDED',{exact:true}).waitFor();
    await page.screenshot({path:path.join(output,'refunded-390.png')});
    console.log('P11_REAL_STRIPE_TEST_REFUND_SIGNED_WEBHOOK: pass');
    console.log('P11_SANDBOX_E2E: pass\nLIVE_STRIPE_CALLS: no\nREAL_MONEY: no\nEXTERNAL_MAIL: no\nPRODUCTION_LAUNCH: no\nNEW_EXTERNAL_COST: 0');
  }catch(error){
    await page.screenshot({path:path.join(output,'failure.png')}).catch(()=>{});
    console.error('P11_FAILURE: '+String(error.message).replace(/(sk|rk|whsec)_[A-Za-z0-9_]+/g,'[REDACTED]'));
    process.exitCode=1;
  }finally{await context.close();await browser.close();await api.dispose();await callbacks.dispose();}
})();
