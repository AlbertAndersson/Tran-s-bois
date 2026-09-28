(() => {
  'use strict';
  const cfg=window.BOIS_COMMERCE_CONFIG||{apiBase:null,environmentLabel:'DEMO',paymentEnabled:false};
  const storage={
    get(key){try{return sessionStorage.getItem(key)||'';}catch{return ''; }},
    set(key,value){try{sessionStorage.setItem(key,value);return true;}catch{return false;}},
    remove(key){try{sessionStorage.removeItem(key);}catch{}}
  };
  const labels={
    PENDING_PAYMENT:'Väntar på betalning',NOT_ENABLED:'Inte betald',PENDING:'Väntar',
    PAID:'Betald',FAILED:'Misslyckad',CANCELLED:'Avbruten',PARTIALLY_REFUNDED:'Delvis återbetald',
    REFUND_PENDING:'Återbetalning pågår',REFUNDED:'Återbetald',PAYMENT_REVIEW:'Betalning granskas',
    ON_HOLD:'Inväntar nästa steg',WAITING_BATCH:'Väntar på samlad beställning',QUEUED:'I kö',
    BATCHED:'Ingår i samlad beställning',REVIEW_REQUIRED:'Behöver granskas manuellt',
    ACTIVE:'Aktivt',PENDING_MEMBER_VERIFICATION:'Väntar på medlemskontroll',ELIGIBLE:'Godkänd för gymkort',
    SENT_TO_PARTNER:'Skickat till Nordic',READY_FOR_PICKUP:'Klart att hämta',ACTIVATED:'Aktiverat',
    APPLIED:'Genomfört',PROCESSED:'Behandlat',PROCESSING:'Behandlas',ERROR:'Fel – behöver kontrolleras',
    NOT_APPLICABLE:'Inte aktuellt',RETRY:'Nytt försök väntar',SENT:'Skickat',OPEN:'Öppet',
    DIGITAL_MEMBERSHIP:'Digitalt medlemskap',MEMBER_BENEFIT:'Medlemsförmån',BATCH_SUPPLIER:'Samlad leverantörsbeställning',
    DIRECT_SUPPLIER:'Direkt från leverantör',adult:'Vuxen',youth:'Ungdom',senior:'Pensionär'
  };
  function esc(value){return String(value??'').replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));}
  function status(value){return labels[value]||String(value||'–');}
  function uuid(){
    if(crypto.randomUUID)return crypto.randomUUID();
    const a=crypto.getRandomValues(new Uint8Array(16));a[6]=(a[6]&15)|64;a[8]=(a[8]&63)|128;
    const h=Array.from(a,x=>x.toString(16).padStart(2,'0')).join('');
    return [h.slice(0,8),h.slice(8,12),h.slice(12,16),h.slice(16,20),h.slice(20)].join('-');
  }
  async function api(action,options={}){
    if(!cfg.apiBase)throw new Error('Demoläge: ingen databas är ansluten.');
    const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),options.timeoutMs||15000);
    try{
      const response=await fetch(cfg.apiBase+'?action='+encodeURIComponent(action)+(options.query||''),{
        method:options.method||'GET',headers:{Accept:'application/json',...(options.headers||{})},body:options.body,signal:controller.signal
      });
      const body=await response.json().catch(()=>null);
      if(!response.ok){const e=new Error(body?.error||(response.status===401||response.status===403?'Adminnyckeln saknas eller är ogiltig.':'Anropet misslyckades. Försök igen.'));e.status=response.status;throw e;}
      if(!body||body.ok!==true)throw new Error('Servern gav ett ofullständigt svar. Kontrollera status och försök igen.');
      return body;
    }catch(error){
      if(error.name==='AbortError')throw new Error('Svaret dröjer. Beställningen kan ha sparats. Försök igen utan att ändra formuläret, så används samma orderförsök.');
      if(error instanceof TypeError)throw new Error('Kontakten med servern bröts. Kontrollera anslutningen och försök igen.');
      throw error;
    }finally{clearTimeout(timer);}
  }
  let trackingPromise;
  function trackingAllowed(){
    if(!cfg.apiBase||document.body?.dataset.salesIgnore==='true'||location.pathname.endsWith('assortment-preview.html'))return Promise.resolve(false);
    if(!trackingPromise)trackingPromise=api('health',{timeoutMs:3000}).then(h=>h.mode==='staging'&&h.sales_tracking_enabled===true).catch(()=>false);
    return trackingPromise;
  }
  function safeToken(value,max){value=String(value||'').trim().slice(0,max);return /^[\p{L}\p{N}._:+\/-]+$/u.test(value)?value:'';}
  function salesSessionId(){
    let id=storage.get('boisSalesSession');
    if(!/^[a-f0-9-]{36}$/i.test(id)){id=uuid();if(!storage.set('boisSalesSession',id))return '';}
    return id;
  }
  function salesAttribution(){
    let a={};try{a=JSON.parse(storage.get('boisSalesAttribution')||'{}')||{};}catch{}
    if(!a.landing_path){const q=new URLSearchParams(location.search);a={source:safeToken(q.get('utm_source'),80),medium:safeToken(q.get('utm_medium'),80),campaign:safeToken(q.get('utm_campaign'),120),ref:safeToken(q.get('ref'),80),landing_path:location.pathname};storage.set('boisSalesAttribution',JSON.stringify(a));}
    return a;
  }
  async function trackSales(eventType,{productKey='',pagePath=location.pathname}={}){
    try{
      if(!await trackingAllowed())return;
      const id=salesSessionId();if(!id)return;
      await api('sales_event',{method:'POST',timeoutMs:3000,headers:{'Content-Type':'application/json'},body:JSON.stringify({session_id:id,event_key:'web-'+uuid(),event_type:eventType,page_path:pagePath,product_key:productKey,attribution:salesAttribution()})});
    }catch{/* Attribution must never prevent the shopping flow. */}
  }
  // Per-document retry cache: no customer data is written to browser storage.
  // A repeated unchanged form, including a retry after a lost response, reuses its key.
  const orderAttempts=new Map();
  async function createOrder(input){
    const payload={...input};delete payload.idempotency_key;
    const fingerprint=JSON.stringify(payload);
    let attempt=orderAttempts.get(fingerprint);
    if(!attempt){attempt={key:input.idempotency_key||uuid(),promise:null,result:null};orderAttempts.set(fingerprint,attempt);}
    if(attempt.result)return attempt.result;
    if(attempt.promise)return attempt.promise;
    attempt.promise=(async()=>{
      if(!cfg.apiBase){const prices={'MEM-YOUTH':20000,'MEM-ADULT':35000,'MEM-SENIOR':30000,'NW-GYM-ANNUAL':265000,'MATCHKIT-STAGING':99800};return {public_id:'BOIS-DEMO-0001',public_token:'demo',status:'PENDING_PAYMENT',payment_status:'NOT_ENABLED',total_ore:payload.items.reduce((sum,i)=>sum+(prices[i.sku]||0)*(i.quantity||1),0)};}
      if(await trackingAllowed()){const session=salesSessionId();if(session){payload.sales_session_id=session;payload.sales_attribution=salesAttribution();}}
      const body=await api('orders',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({...payload,idempotency_key:attempt.key})});
      if(!body.order?.public_id||!body.order?.public_token)throw new Error('Orderbekräftelsen är ofullständig. Försök igen utan att ändra formuläret.');
      return body.order;
    })();
    try{attempt.result=await attempt.promise;return attempt.result;}finally{attempt.promise=null;}
  }
  window.BOIS_COMMERCE={
    cfg,storage,status,esc,uuid,api,createOrder,salesSessionId,salesAttribution,trackSales,
    money(ore){return new Intl.NumberFormat('sv-SE',{style:'currency',currency:'SEK',maximumFractionDigits:0}).format((Number(ore)||0)/100);},
    async catalog(){
      if(cfg.apiBase)return (await api('catalog')).products||[];
      return [
        {product_key:'membership',name:'Medlemskap Tranås BoIS',category:'membership',description:'Stöd föreningen och få tillgång till medlemsförmåner.',fulfillment_type:'DIGITAL_MEMBERSHIP',is_public:true,is_orderable:true,variants:[{sku:'MEM-YOUTH',name:'Ungdom',price_ore:20000},{sku:'MEM-ADULT',name:'Vuxen',price_ore:35000},{sku:'MEM-SENIOR',name:'Pensionär',price_ore:30000}]},
        {product_key:'nordic-gym',name:'Nordic Wellness gymkort',category:'member_benefit',description:'Gymkort för aktiv BoIS-medlem.',price_ore:265000,fulfillment_type:'MEMBER_BENEFIT',is_public:true,is_orderable:true,variants:[{sku:'NW-GYM-ANNUAL',name:'Gymkort 12 månader',price_ore:265000}]},
        {product_key:'match-kit',name:'Matchställ',category:'match_kit',description:'Matchställ med namn och nummer.',price_ore:99800,fulfillment_type:'BATCH_SUPPLIER',is_public:true,is_orderable:true,metadata:{staging_price:true,real_price_pending:true},variants:[{sku:'MATCHKIT-STAGING',name:'Matchställ – testvariant',price_ore:99800}]}
      ];
    },
    async recommendations(context={}){if(!cfg.apiBase)return [];return (await api('sales_recommendations',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(context)})).recommendations||[];},
    setEnvironmentLabel(){document.querySelectorAll('[data-env]').forEach(el=>{el.textContent=cfg.environmentLabel||(cfg.apiBase?'STAGING':'DEMO');});}
  };
  window.BOIS_COMMERCE.setEnvironmentLabel();
  const nav=document.querySelector('.nav-links');
  if(nav){nav.setAttribute('role','navigation');nav.setAttribute('aria-label','Butik');}
  else if(document.querySelector('.header')){
    const links=document.createElement('nav');links.className='shop-shortcuts';links.setAttribute('aria-label','Butik');
    links.innerHTML='<a href="index.html">Shoppen</a><a href="membership.html">Medlemskap & gym</a><a href="match-kit.html">Matchställ</a>';
    document.querySelector('.header').append(links);
  }
  document.querySelectorAll('.error,.success').forEach(el=>{
    el.setAttribute('role',el.classList.contains('error')?'alert':'status');el.setAttribute('aria-live',el.classList.contains('error')?'assertive':'polite');el.tabIndex=-1;
    new MutationObserver(records=>{if(records.some(r=>r.attributeName==='hidden')&&!el.hidden){el.focus({preventScroll:true});el.scrollIntoView({block:'nearest',behavior:'auto'});}}).observe(el,{attributes:true,attributeFilter:['hidden']});
  });
  document.querySelectorAll('.table-wrap').forEach(el=>{el.tabIndex=0;el.setAttribute('role','region');el.setAttribute('aria-label','Tabell – rulla i sidled för fler kolumner');});
  const name=(location.pathname.split('/').pop()||'index.html').toLowerCase();
  if(name==='membership.html')trackSales('product_view',{productKey:'membership'});
  else if(name==='match-kit.html')trackSales('product_view',{productKey:'match-kit'});
  else if(name==='payment.html')trackSales('checkout_view');
  else trackSales('page_view');
})();
