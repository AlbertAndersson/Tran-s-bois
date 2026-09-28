(() => {
  'use strict';

  const cfg = window.BOIS_COMMERCE_CONFIG || { apiBase: null, environmentLabel: 'P3 DEMO', paymentEnabled: false };

  const SALES_SESSION_KEY='boisSalesSession';
  const SALES_ATTR_KEY='boisSalesAttribution';
  let pendingTrackingCheck=null;
  let consentRevision=0;
  const eventControllers=new Set();
  async function consentChoice(){
    try{
      const response=await fetch(cfg.apiBase+'?action=consent',{headers:{Accept:'application/json'},cache:'no-store',credentials:'same-origin'});
      if(!response.ok)return null;
      const result=await response.json();
      return result.ok===true?result.choice:null;
    }catch{return null;}
  }

  // No analytics storage access before a positive server decision. Share only an
  // in-flight check, never a cached approval; later interactions recheck the gate.
  // Production stays blocked until the separate consent implementation is ready.
  async function salesTrackingEnabled(){
    if(!cfg.apiBase || document.body?.dataset?.salesIgnore==='true') return false;
    if(pendingTrackingCheck) return pendingTrackingCheck;
    pendingTrackingCheck=(async()=>{
      let timer;
      try{
        const revision=consentRevision;
        const choice=await consentChoice();
        if(choice?.statistics!==true || revision!==consentRevision)return false;
        const controller=new AbortController();
        const deadline=new Promise(resolve=>{
          timer=setTimeout(()=>{controller.abort();resolve(null);},1500);
        });
        const request=fetch(cfg.apiBase+'?action=health',{
          headers:{Accept:'application/json'},
          cache:'no-store',
          signal:controller.signal
        }).then(async response=>response.ok?await response.json():null).catch(()=>null);
        const health=await Promise.race([request,deadline]);
        return revision===consentRevision && health?.ok===true
          && ['staging','test'].includes(health.mode)
          && health.sales_tracking_enabled===true;
      }catch{
        return false;
      }finally{
        clearTimeout(timer);
      }
    })();
    try{return await pendingTrackingCheck;}finally{pendingTrackingCheck=null;}
  }

  function safeToken(value,max){
    value=String(value||'').trim().slice(0,max);
    return /^[\p{L}\p{N}._:+\/-]+$/u.test(value)?value:'';
  }

  // Private storage functions: call only after salesTrackingEnabled() succeeds.
  function readSalesSessionId(){
    let id=sessionStorage.getItem(SALES_SESSION_KEY)||'';
    if(!/^[a-f0-9-]{36}$/i.test(id)){
      id=crypto.randomUUID?crypto.randomUUID():('00000000-0000-4000-8000-'+Math.random().toString(16).slice(2,14).padEnd(12,'0')).slice(0,36);
      sessionStorage.setItem(SALES_SESSION_KEY,id);
    }
    return id;
  }

  function readSalesAttribution(){
    let stored={};
    try{stored=JSON.parse(sessionStorage.getItem(SALES_ATTR_KEY)||'{}')||{};}catch{}
    if(!stored.landing_path){
      const q=new URLSearchParams(location.search);
      stored={
        source:safeToken(q.get('utm_source'),80),
        medium:safeToken(q.get('utm_medium'),80),
        campaign:safeToken(q.get('utm_campaign'),120),
        ref:safeToken(q.get('ref'),80),
        landing_path:location.pathname
      };
      sessionStorage.setItem(SALES_ATTR_KEY,JSON.stringify(stored));
    }
    return stored;
  }

  async function salesSessionId(){
    if(!await salesTrackingEnabled()) return '';
    try{return readSalesSessionId();}catch{return '';}
  }

  async function salesAttribution(){
    if(!await salesTrackingEnabled()) return {};
    try{return readSalesAttribution();}catch{return {};}
  }

  function salesEventKey(){
    return 'web-'+(crypto.randomUUID?crypto.randomUUID():Date.now()+'-'+Math.random().toString(16).slice(2));
  }

  async function trackSales(eventType,{productKey='',pagePath=location.pathname}={}){
    if(!await salesTrackingEnabled()) return;
    const revision=consentRevision;
    const controller=new AbortController();
    try{
      const sessionId=readSalesSessionId(),attribution=readSalesAttribution();
      if(revision!==consentRevision)return;
      eventControllers.add(controller);
      await fetch(cfg.apiBase+'?action=sales_event',{
        method:'POST',
        headers:{'Content-Type':'application/json','Accept':'application/json'},credentials:'same-origin',signal:controller.signal,
        body:JSON.stringify({
          session_id:sessionId,
          event_key:salesEventKey(),
          event_type:eventType,
          page_path:pagePath,
          product_key:productKey,
          attribution
        }),
      });
    }catch{}finally{eventControllers.delete(controller);}
  }

  function clearOptionalSales(){
    consentRevision++;
    eventControllers.forEach(controller=>controller.abort());
    eventControllers.clear();
    try{sessionStorage.removeItem(SALES_SESSION_KEY);sessionStorage.removeItem(SALES_ATTR_KEY);}catch{}
  }

  async function saveConsent(statistics){
    // Stop queued tracking before the server receives a withdrawal.
    clearOptionalSales();
    const result=await window.BOIS_COMMERCE.api('consent',{
      method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({statistics})
    });
    return result.choice;
  }

  async function setupConsent(){
    if(!document.createElement || document.body?.dataset?.salesIgnore==='true')return;
    const link=document.createElement('a');
    link.href='#bois-consent';link.textContent='Kakinställningar';link.className='consent-link';
    link.addEventListener('click',event=>{event.preventDefault();show(true);});
    document.body.append(link);
    const info=document.createElement('a');info.href='cookies.html';info.textContent='Om kakor och lagring';info.className='consent-info-link';document.body.append(info);
    const panel=document.createElement('section');panel.id='bois-consent';panel.className='consent-panel';
    panel.setAttribute('role','dialog');
    panel.setAttribute('aria-modal','false');
    panel.setAttribute('aria-labelledby','bois-consent-title');
    panel.innerHTML='<h2 id="bois-consent-title">Kakor och statistik</h2><p>Vi använder nödvändig lagring för ditt val och orderflödet. Valfri besöksstatistik och kampanjattribution är av tills du väljer ja. Du kan handla utan att välja.</p><p><a href="cookies.html">Läs om lagringen</a></p><div class="consent-actions"><button type="button" data-choice="false">Avvisa statistik</button><button type="button" data-choice="true">Acceptera statistik</button></div><details><summary>Inställningar</summary><p>Nödvändig lagring används för ditt val och administration av tjänsten.</p><label><input type="checkbox" id="bois-statistics"> Tillåt besöksstatistik och kampanjattribution</label><button type="button" data-save="true">Spara inställningar</button></details><p role="status" class="consent-status" hidden></p>';
    document.body.append(panel);
    const status=panel.querySelector('.consent-status');
    function show(focus){panel.hidden=false;if(focus)panel.querySelector('[data-choice="false"]').focus();}
    async function choose(value){
      try{
        await saveConsent(value);
        status.hidden=true;panel.hidden=true;link.focus();
      }catch{
        status.textContent='Ditt val kunde inte sparas. Statistik förblir avstängd och du kan fortsätta handla.';
        status.hidden=false;
      }
    }
    panel.querySelectorAll('[data-choice]').forEach(button=>button.addEventListener('click',()=>choose(button.dataset.choice==='true')));
    panel.querySelector('[data-save]').addEventListener('click',()=>choose(panel.querySelector('#bois-statistics').checked));
    const choice=await consentChoice();
    if(choice?.statistics!==true)clearOptionalSales();
    if(!choice?.decided)show(true);
    else panel.hidden=true;
  }

  window.BOIS_COMMERCE = {
    cfg,
    money(ore) {
      return new Intl.NumberFormat('sv-SE', {
        style: 'currency',
        currency: 'SEK',
        maximumFractionDigits: 0
      }).format((Number(ore) || 0) / 100);
    },
    uuid() {
      return crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + '-' + String(Math.random());
    },
    async api(action, options = {}) {
      if (!cfg.apiBase) throw new Error('Demo-läge: ingen databas är ansluten.');
      const query = options.query || '';
      const response = await fetch(cfg.apiBase + '?action=' + encodeURIComponent(action) + query, {
        method: options.method || 'GET',
        headers: {
          Accept: 'application/json',
          ...(options.headers || {})
        },
        body: options.body
      });
      const body = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(body.error || 'Ett fel uppstod.');
      return body;
    },
    async catalog() {
      if (!cfg.apiBase) {
        return [
          {
            product_key:'membership', name:'Medlemskap Tranås BoIS', category:'membership',
            description:'Stöd föreningen och få tillgång till medlemsförmåner.',
            fulfillment_type:'DIGITAL_MEMBERSHIP', is_public:true, is_orderable:true,
            variants:[
              {sku:'MEM-YOUTH',name:'Ungdom',price_ore:20000},
              {sku:'MEM-ADULT',name:'Vuxen',price_ore:35000},
              {sku:'MEM-SENIOR',name:'Pensionär',price_ore:30000}
            ]
          },
          {
            product_key:'nordic-gym', name:'Nordic Wellness gymkort', category:'member_benefit',
            description:'Gymkort för aktiv BoIS-medlem.', price_ore:265000,
            fulfillment_type:'MEMBER_BENEFIT', is_public:true, is_orderable:true,
            variants:[{sku:'NW-GYM-ANNUAL',name:'Gymkort 12 månader',price_ore:265000}]
          },
          {
            product_key:'match-kit', name:'Matchställ', category:'match_kit',
            description:'Matchställ med namn och nummer.', price_ore:99800,
            fulfillment_type:'BATCH_SUPPLIER', is_public:true, is_orderable:true,
            metadata:{staging_price:true,real_price_pending:true},
            variants:[{sku:'MATCHKIT-STAGING',name:'Matchställ – testvariant',price_ore:99800}]
          }
        ];
      }
      return (await this.api('catalog')).products || [];
    },
    async createOrder(payload) {
      // Analytics is optional: strip caller-supplied attribution before deciding.
      payload={...payload};
      delete payload.sales_session_id;
      delete payload.sales_attribution;
      if(await salesTrackingEnabled()){
        try{
          const sessionId=readSalesSessionId();
          const attribution=readSalesAttribution();
          payload.sales_session_id=sessionId;
          payload.sales_attribution=attribution;
        }catch{
          // Storage restrictions must never prevent an ordinary order.
        }
      }
      if (!cfg.apiBase) {
        return {
          public_id:'BOIS-DEMO-0001',
          public_token:'demo',
          status:'PENDING_PAYMENT',
          payment_status:'NOT_ENABLED',
          total_ore:payload.items.reduce((sum, item) => {
            const prices = {'MEM-YOUTH':20000,'MEM-ADULT':35000,'MEM-SENIOR':30000,'NW-GYM-ANNUAL':265000,'MATCHKIT-STAGING':99800};
            return sum + (prices[item.sku] || 0) * (item.quantity || 1);
          },0)
        };
      }
      const body = await this.api('orders', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify(payload)
      });
      return body.order;
    },
    salesTrackingEnabled,
    salesSessionId,
    salesAttribution,
    trackSales,
    saveConsent,
    clearOptionalSales,
    async recommendations(context={}) {
      if(!cfg.apiBase) return [];
      const body=await this.api('sales_recommendations',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify(context)
      });
      return body.recommendations||[];
    },
    setEnvironmentLabel() {
      document.querySelectorAll('[data-env]').forEach(el => {
        el.textContent = cfg.environmentLabel || (cfg.apiBase ? 'P3 STAGING' : 'P3 DEMO');
      });
    }
  };

  window.BOIS_COMMERCE.setEnvironmentLabel();
  setupConsent();

  if(document.body?.dataset?.salesIgnore!=='true'){
    const name=(location.pathname.split('/').pop()||'index.html').toLowerCase();
    if(name==='membership.html') trackSales('product_view',{productKey:'membership'});
    else if(name==='match-kit.html') trackSales('product_view',{productKey:'match-kit'});
    else if(name==='payment.html') trackSales('checkout_view');
    else trackSales('page_view');
  }
})();
