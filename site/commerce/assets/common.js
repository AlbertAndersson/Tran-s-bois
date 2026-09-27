(() => {
  'use strict';

  const cfg = window.BOIS_COMMERCE_CONFIG || { apiBase: null, environmentLabel: 'P3 DEMO', paymentEnabled: false };

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
    setEnvironmentLabel() {
      document.querySelectorAll('[data-env]').forEach(el => {
        el.textContent = cfg.environmentLabel || (cfg.apiBase ? 'P3 STAGING' : 'P3 DEMO');
      });
    }
  };

  window.BOIS_COMMERCE.setEnvironmentLabel();
})();