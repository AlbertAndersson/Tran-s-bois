(() => {
  'use strict';
  const c=window.BOIS_COMMERCE,$=id=>document.getElementById(id);
  const prices={'MEM-YOUTH':20000,'MEM-ADULT':35000,'MEM-SENIOR':30000,'NW-GYM-ANNUAL':265000};
  let recommendationRun=0,submitting=false;
  // A membership link must not silently add the gym card. The gym CTA is explicit.
  $('addGym').checked=location.hash==='#gym';
  function selectedMembership(){return document.querySelector('input[name="membership"]:checked')?.value||null;}
  async function recommendation(){
    const run=++recommendationRun,existing=$('existingMember').checked,skus=[];
    if(!existing&&selectedMembership())skus.push(selectedMembership());if($('addGym').checked)skus.push('NW-GYM-ANNUAL');
    try{
      const recs=await c.recommendations({skus,existing_member:existing});if(run!==recommendationRun)return;
      const box=$('salesRecommendation');box.hidden=!recs.length;box.innerHTML=recs.length?'<b>'+c.esc(recs[0].title)+'</b><br><span class="small">'+c.esc(recs[0].message)+'</span>':'';
    }catch{if(run===recommendationRun)$('salesRecommendation').hidden=true;}
  }
  function refresh(){
    const existing=$('existingMember').checked;$('membershipOptions').style.display=existing?'none':'block';$('memberName').required=!existing;
    const rows=[];let total=0;
    if(!existing){const sku=selectedMembership();if(sku){total+=prices[sku];const name=sku==='MEM-YOUTH'?'Ungdomsmedlemskap':sku==='MEM-SENIOR'?'Pensionärsmedlemskap':'Vuxenmedlemskap';rows.push('<div class="line"><span>'+name+'</span><b>'+c.money(prices[sku])+'</b></div>');}}
    if($('addGym').checked){total+=prices['NW-GYM-ANNUAL'];rows.push('<div class="line"><span>Nordic Wellness gymkort</span><b>'+c.money(prices['NW-GYM-ANNUAL'])+'</b></div>');}
    $('membershipSummary').innerHTML=rows.join('')||'<div class="small">Välj minst en produkt.</div>';$('total').textContent=c.money(total);recommendation();
  }
  ['existingMember','addGym'].forEach(id=>$(id).addEventListener('change',refresh));document.querySelectorAll('input[name="membership"]').forEach(el=>el.addEventListener('change',refresh));refresh();
  $('membershipForm').addEventListener('input',()=>{if(!submitting){$('submitBtn').disabled=false;$('submitBtn').textContent='Skapa testorder → betalning';}});
  $('membershipForm').addEventListener('submit',async event=>{
    event.preventDefault();if(submitting||!event.currentTarget.reportValidity())return;$('error').hidden=true;$('success').hidden=true;
    const existing=$('existingMember').checked,items=[];
    if(!existing&&selectedMembership())items.push({sku:selectedMembership(),quantity:1,metadata:{member_name:$('memberName').value.trim()}});
    if($('addGym').checked)items.push({sku:'NW-GYM-ANNUAL',quantity:1,metadata:{}});
    if(!items.length){$('error').textContent='Välj medlemskap eller gymkort. Inga produkter har lagts till automatiskt.';$('error').hidden=false;return;}
    submitting=true;$('submitBtn').disabled=true;$('submitBtn').textContent='Sparar…';let saved=false;
    try{
      const order=await c.createOrder({customer:{name:$('buyerName').value.trim(),email:$('email').value.trim(),phone:$('phone').value.trim()},items,existing_member:existing,consent:$('consent').checked,website:'',idempotency_key:c.uuid()});
      const query='id='+encodeURIComponent(order.public_id)+'&token='+encodeURIComponent(order.public_token);
      $('success').innerHTML='<b>Testordern är sparad.</b><div class="order-id">'+c.esc(order.public_id)+'</div><p>Betalning: '+c.esc(c.status(order.payment_status))+'. Ordersumma: '+c.money(order.total_ore)+'.</p>'+(c.cfg.apiBase?'<a class="btn block" href="payment.html?'+query+'">Gå till testbetalning →</a><a class="btn ghost block" href="order.html?'+query+'">Visa orderstatus</a>':'');
      $('success').hidden=false;saved=true;
    }catch(error){$('error').textContent=error.message;$('error').hidden=false;}
    finally{submitting=false;$('submitBtn').disabled=saved;$('submitBtn').textContent=saved?'Order sparad – fortsätt ovan':'Försök spara testordern igen';}
  });
})();
