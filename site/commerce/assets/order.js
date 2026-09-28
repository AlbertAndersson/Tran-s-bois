(() => {
  'use strict';
  const c=window.BOIS_COMMERCE,$=id=>document.getElementById(id);
  const params=new URLSearchParams(location.search),id=params.get('id')||'',token=params.get('token')||'';

  async function load(){
    if(!c.cfg.apiBase){
      $('intro').textContent='Demovy – ingen riktig order hämtas.';
      $('content').hidden=false;$('orderId').textContent='BOIS-DEMO-0001';$('status').textContent='PENDING_PAYMENT';$('payment').textContent='NOT_ENABLED';$('fulfillment').textContent='ON_HOLD';$('total').textContent='3 000 kr';$('items').innerHTML='<tr><td>Medlemskap</td><td>Vuxen</td><td>1</td><td>DIGITAL_MEMBERSHIP</td><td>350 kr</td></tr><tr><td>Nordic Wellness</td><td>Gymkort</td><td>1</td><td>MEMBER_BENEFIT</td><td>2 650 kr</td></tr>';return;
    }
    if(!id||!token) throw new Error('Orderlänken saknar ordernummer eller token.');
    const body=await c.api('order',{query:'&id='+encodeURIComponent(id)+'&token='+encodeURIComponent(token)});
    const o=body.order;
    $('intro').textContent='Här visas status direkt från Commerce Core.';
    $('content').hidden=false;$('orderId').textContent=o.public_id;$('status').textContent=o.status;$('payment').textContent=o.payment_status;$('fulfillment').textContent=o.fulfillment_status;$('total').textContent=c.money(o.total_ore);
    $('retryPayment').hidden=!['PENDING','FAILED','CANCELLED'].includes(o.payment_status);
    if(!$('retryPayment').hidden)$('retryPayment').href='payment.html?id='+encodeURIComponent(id)+'&token='+encodeURIComponent(token);
    if(o.payment?.reference){
      $('paymentReference').textContent='Betalreferens: '+o.payment.reference+(o.payment.method?' · '+o.payment.method:'');
      $('paymentReference').hidden=false;
    }
    $('items').innerHTML=(o.items||[]).map(i=>'<tr><td><b>'+i.product_name+'</b></td><td>'+i.variant_name+'</td><td>'+i.quantity+'</td><td><span class="chip">'+i.fulfillment_type+'</span></td><td>'+c.money(i.line_total_ore)+'</td></tr>').join('');
    const p4=o.p4||{};
    const memberships=p4.memberships||[],benefits=p4.benefits||[];
    if(memberships.length||benefits.length){
      $('p4Status').hidden=false;
      const rows=[];
      memberships.forEach(m=>rows.push('<div class="line"><span>Medlemskap '+(m.membership_type||'')+'</span><b>'+(m.status||'–')+'</b></div>'));
      benefits.forEach(b=>rows.push('<div class="line"><span>Nordic Wellness</span><b>'+(b.status||'–')+'</b></div>'));
      $('p4StatusBody').innerHTML=rows.join('');
    }
  }
  load().catch(error=>{$('intro').textContent='Ordern kunde inte visas.';$('error').textContent=error.message;$('error').hidden=false;});
})();
