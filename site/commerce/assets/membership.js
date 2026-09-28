(() => {
  'use strict';
  const c = window.BOIS_COMMERCE;
  const $ = id => document.getElementById(id);
  const prices = {'MEM-YOUTH':20000,'MEM-ADULT':35000,'MEM-SENIOR':30000,'NW-GYM-ANNUAL':265000};

  function selectedMembership() {
    return document.querySelector('input[name="membership"]:checked')?.value || null;
  }

  let recommendationRun=0;

  async function refreshRecommendation(){
    const run=++recommendationRun;
    const existing=$('existingMember').checked;
    const skus=[];
    if(!existing && selectedMembership()) skus.push(selectedMembership());
    if($('addGym').checked) skus.push('NW-GYM-ANNUAL');
    try{
      const recs=await c.recommendations({skus,existing_member:existing});
      if(run!==recommendationRun) return;
      const box=$('salesRecommendation');
      if(!recs.length){box.hidden=true;box.textContent='';return;}
      const rec=recs[0];
      box.innerHTML='<b>'+rec.title+'</b><br><span class="small">'+rec.message+'</span>';
      box.hidden=false;
    }catch{
      $('salesRecommendation').hidden=true;
    }
  }

  function refresh() {
    const existing = $('existingMember').checked;
    $('membershipOptions').style.display = existing ? 'none' : 'block';
    $('memberName').required = !existing;

    const lines = [];
    let total = 0;
    if (!existing) {
      const sku = selectedMembership();
      if (sku) {
        total += prices[sku];
        const label = sku === 'MEM-YOUTH' ? 'Ungdomsmedlemskap' : sku === 'MEM-SENIOR' ? 'Pensionärsmedlemskap' : 'Vuxenmedlemskap';
        lines.push('<div class="line"><span>' + label + '</span><b>' + c.money(prices[sku]) + '</b></div>');
      }
    }
    if ($('addGym').checked) {
      total += prices['NW-GYM-ANNUAL'];
      lines.push('<div class="line"><span>Nordic Wellness gymkort</span><b>' + c.money(prices['NW-GYM-ANNUAL']) + '</b></div>');
    }
    $('membershipSummary').innerHTML = lines.join('') || '<div class="small">Välj minst en produkt.</div>';
    $('total').textContent = c.money(total);
    refreshRecommendation();
  }

  $('existingMember').addEventListener('change', refresh);
  $('addGym').addEventListener('change', refresh);
  document.querySelectorAll('input[name="membership"]').forEach(el => el.addEventListener('change', refresh));
  refresh();

  $('membershipForm').addEventListener('submit', async event => {
    event.preventDefault();
    $('error').hidden = true;
    $('success').hidden = true;
    if (!event.currentTarget.reportValidity()) return;

    const existing = $('existingMember').checked;
    const items = [];
    if (!existing) {
      const sku = selectedMembership();
      if (!sku) return;
      items.push({sku,quantity:1,metadata:{member_name:$('memberName').value.trim()}});
    }
    if ($('addGym').checked) items.push({sku:'NW-GYM-ANNUAL',quantity:1,metadata:{}});
    if (!items.length) {
      $('error').textContent = 'Välj medlemskap och/eller gymkort.';
      $('error').hidden = false;
      return;
    }

    const button = $('submitBtn');
    button.disabled = true;
    const original = button.textContent;
    button.textContent = 'Sparar…';

    try {
      const order = await c.createOrder({
        customer:{name:$('buyerName').value.trim(),email:$('email').value.trim(),phone:$('phone').value.trim()},
        items,
        existing_member:existing,
        consent:$('consent').checked,
        website:'',
        idempotency_key:c.uuid()
      });
      $('success').innerHTML =
        '<b>Testorder skapad.</b><div class="order-id" style="margin-top:10px">' + order.public_id + '</div>' +
        '<div class="small" style="margin-top:8px">Betalning: ' + order.payment_status + '. Total: ' + c.money(order.total_ore) + '</div>' +
        (c.cfg.apiBase ? '<a class="btn block" style="margin-top:12px" href="payment.html?id=' + encodeURIComponent(order.public_id) + '&token=' + encodeURIComponent(order.public_token) + '">Gå till testbetalning →</a><a class="btn ghost block" style="margin-top:8px" href="order.html?id=' + encodeURIComponent(order.public_id) + '&token=' + encodeURIComponent(order.public_token) + '">Visa orderstatus</a>' : '');
      $('success').hidden = false;
    } catch (error) {
      $('error').textContent = error.message;
      $('error').hidden = false;
    } finally {
      button.disabled = false;
      button.textContent = original;
    }
  });
})();