(() => {
  'use strict';

  const cfg = window.BOIS_COMMERCE_CONFIG || { apiBase: null, environmentLabel: 'P3 DEMO', paymentEnabled: false };

  const SALES_SESSION_KEY='boisSalesSession';
  const SALES_ATTR_KEY='boisSalesAttribution';

  function safeToken(value,max){
    value=String(value||'').trim().slice(0,max);
    return /^[\p{L}\p{N}._:+\/-]+$/u.test(value)?value:'';
  }

  function salesSessionId(){
    let id=sessionStorage.getItem(SALES_SESSION_KEY)||'';
    if(!/^[a-f0-9-]{36}$/i.test(id)){
      id=crypto.randomUUID?crypto.randomUUID():('00000000-0000-4000-8000-'+Math.random().toString(16).slice(2,14).padEnd(12,'0')).slice(0,36);
      sessionStorage.setItem(SALES_SESSION_KEY,id);
    }
    return id;
  }

  function salesAttribution(){
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

  function salesEventKey(){
    return 'web-'+(crypto.randomUUID?crypto.randomUUID():Date.now()+'-'+Math.random().toString(16).slice(2));
  }

  async function trackSales(eventType,{productKey='',pagePath=location.pathname}={}){
    if(!cfg.apiBase || document.body?.dataset?.salesIgnore==='true') return;
    try{
      await fetch(cfg.apiBase+'?action=sales_event',{
        method:'POST',
        headers:{'Content-Type':'application/json','Accept':'application/json'},
        body:JSON.stringify({
          session_id:salesSessionId(),
          event_key:salesEventKey(),
          event_type:eventType,
          page_path:pagePath,
          product_key:productKey,
          attribution:salesAttribution()
        }),
        keepalive:true
      });
    }catch{}
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
      payload={
        ...payload,
        sales_session_id:salesSessionId(),
        sales_attribution:salesAttribution()
      };
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
    salesSessionId,
    salesAttribution,
    trackSales,
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

  if(document.body?.dataset?.salesIgnore!=='true'){
    const name=(location.pathname.split('/').pop()||'index.html').toLowerCase();
    if(name==='membership.html') trackSales('product_view',{productKey:'membership'});
    else if(name==='match-kit.html') trackSales('product_view',{productKey:'match-kit'});
    else if(name==='payment.html') trackSales('checkout_view');
    else trackSales('page_view');
  }
})();