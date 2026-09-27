(() => {
  'use strict';

  const cfg = window.BOIS_CONFIG || { apiBase: null, environmentLabel: 'DEMO' };
  const catalog = window.BOIS_CATALOG || {};
  const $ = id => document.getElementById(id);
  const statusNames = {
    received: 'Ny',
    checked: 'Kontrollerad',
    ready_for_supplier: 'Klar för leverantör',
    ordered: 'Beställd hos leverantör',
    cancelled: 'Avbruten'
  };
  const params = new URLSearchParams(location.search);
  const id = params.get('id') || '';
  const token = params.get('token') || '';

  $('environmentLabel').textContent = cfg.environmentLabel || (cfg.apiBase ? 'P2 STAGING' : 'DEMO');

  function money(ore) {
    return new Intl.NumberFormat('sv-SE', {style:'currency',currency:'SEK',maximumFractionDigits:0}).format((ore || 0) / 100);
  }

  function showError(message) {
    $('orderError').textContent = message;
    $('orderError').hidden = false;
    $('orderIntro').textContent = 'Ordern kunde inte visas.';
  }

  function render(order) {
    $('orderIntro').textContent = 'Här ser du den senaste statusen för beställningen.';
    $('orderId').textContent = order.order_id;
    $('orderStatus').textContent = statusNames[order.status] || order.status;
    $('orderPeriod').textContent = order.order_period_label || (catalog.orderPeriod && catalog.orderPeriod.label) || '–';
    $('orderTeam').textContent = order.team;
    $('orderPlayer').textContent = order.player_name;
    $('orderProduct').textContent = order.product_label || 'Matchställ';
    $('orderShirt').textContent = order.shirt_size;
    $('orderShorts').textContent = order.shorts_size;
    $('orderNumberValue').textContent = order.number;
    $('orderTotal').textContent = money(order.total_ore);
    $('orderContent').hidden = false;
    $('cancelBox').hidden = !order.cancelable;
  }

  async function load() {
    if (!cfg.apiBase) {
      render({
        order_id:'BOIS-DEMO-001',
        order_period_label:(catalog.orderPeriod && catalog.orderPeriod.label) || 'Matchställ 2026/27',
        team:'P9',
        player_name:'Demo Spelare',
        product_label:'Matchställ Knatte',
        shirt_size:'140',
        shorts_size:'140',
        number:'10',
        total_ore:99800,
        status:'received',
        cancelable:true
      });
      $('orderIntro').textContent = 'Demovy – ingen riktig order finns.';
      return;
    }

    if (!id || !token) return showError('Orderlänken saknar ordernummer eller säkerhetstoken.');

    const response = await fetch(cfg.apiBase + '?action=order&id=' + encodeURIComponent(id) + '&token=' + encodeURIComponent(token), {
      headers:{Accept:'application/json'}
    });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(body.error || 'Ordern kunde inte hämtas.');
    render(body.order);
  }

  async function cancelOrder() {
    if (!cfg.apiBase) {
      $('orderStatus').textContent = 'Avbruten (demo)';
      $('cancelBox').hidden = true;
      return;
    }

    if (!confirm('Vill du avbryta testbeställningen?')) return;

    $('cancelBtn').disabled = true;
    try {
      const response = await fetch(cfg.apiBase + '?action=cancel_order', {
        method:'POST',
        headers:{'Content-Type':'application/json','Accept':'application/json'},
        body:JSON.stringify({id:id,token:token})
      });
      const body = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(body.error || 'Beställningen kunde inte avbrytas.');
      render(body.order);
    } catch (error) {
      alert(error.message);
    } finally {
      $('cancelBtn').disabled = false;
    }
  }

  $('cancelBtn').addEventListener('click', cancelOrder);
  load().catch(error => showError(error.message));
})();