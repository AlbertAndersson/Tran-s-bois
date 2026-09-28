'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const {randomUUID}=require('node:crypto');
const source=fs.readFileSync(path.join(__dirname,'../site/commerce/assets/common.js'),'utf8');
const sleep=ms=>new Promise(resolve=>setTimeout(resolve,ms));

function browser(options={}){
  const requests=[],storageAccess=[],store=new Map();
  const state={health:options.health??{ok:true,mode:'staging',sales_tracking_enabled:false},consent:options.consent??false};
  const context={
    window:{BOIS_COMMERCE_CONFIG:{apiBase:options.demo?null:'./commerce-api.php',environmentLabel:'TEST'}},
    document:{body:{dataset:{salesIgnore:options.admin?'true':'false'}},querySelectorAll:()=>[]},
    location:{pathname:'/shop/index.html',search:'?utm_source=fixture&utm_campaign=gate-test&ref=synthetic'},
    crypto:{randomUUID},URLSearchParams,AbortController,Intl,console,
    // Keep timeout tests fast, with no external requests.
    setTimeout:(fn,ms)=>setTimeout(fn,Math.min(ms,15)),clearTimeout,
    sessionStorage:{
      getItem(key){storageAccess.push(['get',key]);if(options.storageBlocked)throw new Error('Storage denied');return store.get(key)??null;},
      setItem(key,value){storageAccess.push(['set',key]);if(options.storageBlocked)throw new Error('Storage denied');store.set(key,value);},
      removeItem(key){storageAccess.push(['remove',key]);store.delete(key);}
    },
    fetch:async(url,init={})=>{
      const action=new URL(url,'https://example.test').searchParams.get('action');
      requests.push({action,body:init.body?JSON.parse(init.body):null});
      if(action==='consent')return {ok:true,json:async()=>({ok:true,choice:{decided:true,statistics:state.consent}})};
      if(action==='health'){
        if(options.failure==='network')throw new Error('offline');
        if(options.failure==='timeout')return new Promise(()=>{});
        return {ok:options.failure!=='http',json:async()=>{
          if(options.failure==='json')throw new Error('invalid JSON');
          return state.health;
        }};
      }
      if(action==='orders')return {ok:true,json:async()=>({order:{public_id:'TEST-ORDER',total_ore:35000}})};
      if(action==='sales_event')return {ok:true,json:async()=>({ok:true,sales:{accepted:true}})};
      throw new Error('Unexpected request: '+action);
    }
  };
  vm.createContext(context);
  vm.runInContext(source,context,{filename:'common.js'});
  return {app:context.window.BOIS_COMMERCE,requests,storageAccess,store,state};
}
const payload=()=>({
  customer:{name:'Synthetic Test',email:'synthetic@example.invalid'},
  items:[{sku:'MEM-ADULT',quantity:1}],
  sales_session_id:'caller-forged-session',sales_attribution:{campaign:'caller-forged'},
});
const orderRequest=b=>b.requests.filter(r=>r.action==='orders').at(-1);

for(const [name,health] of [
  ['false',{ok:true,mode:'staging',sales_tracking_enabled:false}],
  ['missing flag',{ok:true,mode:'staging'}],
  ['missing environment',{ok:true,sales_tracking_enabled:true}],
  ['production even with true flag',{ok:true,mode:'production',sales_tracking_enabled:true}],
  ['unknown environment',{ok:true,mode:'invalid',sales_tracking_enabled:true}],
  ['string true',{ok:true,mode:'staging',sales_tracking_enabled:'true'}],
  ['numeric true',{ok:true,mode:'staging',sales_tracking_enabled:1}],
  ['unsuccessful health',{ok:false,mode:'staging',sales_tracking_enabled:true}],
]){
  test('Tracking denied: '+name,async()=>{
    const b=browser({health});
    await sleep(30);
    await b.app.trackSales('checkout_started');
    assert.equal(await b.app.salesSessionId(),'');
    assert.equal(JSON.stringify(await b.app.salesAttribution()),'{}');
    const original=payload();
    const result=await b.app.createOrder(original);
    assert.equal(result.public_id,'TEST-ORDER');
    assert.equal(original.sales_session_id,'caller-forged-session','caller data must not be mutated');
    assert.equal(b.storageAccess.length,0,'no sales storage reads or writes');
    assert.equal(b.requests.filter(r=>r.action==='sales_event').length,0);
    assert.equal('sales_session_id' in orderRequest(b).body,false);
    assert.equal('sales_attribution' in orderRequest(b).body,false);
  });
}
for(const failure of ['network','http','json','timeout']){
  test('Health '+failure+' fails closed, order still works',async()=>{
    const b=browser({failure});
    await sleep(30);
    const result=await b.app.createOrder(payload());
    assert.equal(result.public_id,'TEST-ORDER');
    assert.equal(b.storageAccess.length,0);
    assert.equal(b.requests.filter(r=>r.action==='sales_event').length,0);
    assert.equal('sales_session_id' in orderRequest(b).body,false);
  });
}

test('Explicit synthetic staging consent preserves attribution',async()=>{
  const b=browser({consent:true,health:{ok:true,mode:'staging',sales_tracking_enabled:true}});
  await sleep(30);
  await b.app.createOrder(payload());
  const sent=orderRequest(b).body;
  assert.match(sent.sales_session_id,/^[a-f0-9-]{36}$/);
  assert.equal(sent.sales_attribution.source,'fixture');
  assert.equal(sent.sales_attribution.campaign,'gate-test');
  assert.ok(b.requests.some(r=>r.action==='sales_event'));
  assert.equal(b.store.has('boisSalesSession'),true);
  assert.equal(b.store.has('boisSalesAttribution'),true);
});

test('Next interaction rechecks server after switch changes to off',async()=>{
  const b=browser({consent:true,health:{ok:true,mode:'staging',sales_tracking_enabled:true}});
  await sleep(30);
  assert.ok(b.storageAccess.length>0);
  b.state.health={ok:true,mode:'staging',sales_tracking_enabled:false};
  b.requests.length=0;b.storageAccess.length=0;
  await b.app.trackSales('checkout_started');
  await b.app.createOrder(payload());
  assert.equal(b.storageAccess.length,0);
  assert.equal(b.requests.filter(r=>r.action==='sales_event').length,0);
  assert.equal('sales_attribution' in orderRequest(b).body,false);
});

test('Restricted browser storage never breaks the order',async()=>{
  const b=browser({consent:true,storageBlocked:true,health:{ok:true,mode:'staging',sales_tracking_enabled:true}});
  await sleep(30);
  assert.equal((await b.app.createOrder(payload())).public_id,'TEST-ORDER');
  assert.equal('sales_session_id' in orderRequest(b).body,false);
  assert.equal(b.requests.filter(r=>r.action==='sales_event').length,0);
});

test('Admin and disconnected demo never initialise sales tracking',async()=>{
  for(const option of [{admin:true},{demo:true}]){
    const b=browser({...option,consent:true,health:{ok:true,mode:'staging',sales_tracking_enabled:true}});
    await sleep(30);
    await b.app.trackSales('page_view');
    await b.app.createOrder(payload());
    assert.equal(b.storageAccess.length,0);
    assert.equal(b.requests.filter(r=>r.action==='sales_event'||r.action==='health').length,0);
  }
});

test('No statistics decision leaves optional storage and attribution empty',async()=>{
  const b=browser({health:{ok:true,mode:'staging',sales_tracking_enabled:true}});
  await sleep(30);
  await b.app.trackSales('page_view');
  await b.app.createOrder(payload());
  assert.equal(b.storageAccess.length,0);
  assert.equal(b.requests.filter(r=>r.action==='sales_event').length,0);
  assert.equal('sales_session_id' in orderRequest(b).body,false);
});

test('Withdrawal clears keys and stops later attribution',async()=>{
  const b=browser({consent:true,health:{ok:true,mode:'staging',sales_tracking_enabled:true}});
  await sleep(30);
  assert.equal(b.store.has('boisSalesSession'),true);
  b.state.consent=false;
  b.app.clearOptionalSales();
  await b.app.trackSales('checkout_started');
  await b.app.createOrder(payload());
  assert.equal(b.store.has('boisSalesSession'),false);
  assert.equal(b.store.has('boisSalesAttribution'),false);
  assert.equal('sales_session_id' in orderRequest(b).body,false);
});
