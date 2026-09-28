(() => {
  'use strict';
  const c=window.BOIS_COMMERCE,$=id=>document.getElementById(id),q=new URLSearchParams(location.search);
  const id=q.get('id')||'',token=q.get('token')||'';
  const controls=document.createElement('div');controls.className='chips';
  const refresh=document.createElement('button');refresh.type='button';refresh.id='refreshOrder';refresh.className='btn ghost';refresh.textContent='Uppdatera status';
  const retry=document.createElement('a');retry.id='resumePayment';retry.className='btn';retry.textContent='Fortsätt till testbetalning';retry.hidden=true;
  retry.href='payment.html?id='+encodeURIComponent(id)+'&token='+encodeURIComponent(token);controls.append(refresh,retry);
  $('intro').after(controls);
  const guidance=document.createElement('p');guidance.id='orderGuidance';guidance.className='notice blue';guidance.setAttribute('role','status');controls.after(guidance);
  function labelled(el,code){el.textContent=c.status(code);el.title=code||'';}
  function render(o){
    $('intro').textContent='Här följer du din order. Dela inte den privata orderlänken.';
    $('content').hidden=false;$('orderId').textContent=o.public_id;labelled($('status'),o.status);labelled($('payment'),o.payment_status);labelled($('fulfillment'),o.fulfillment_status);$('total').textContent=c.money(o.total_ore);
    const ref=o.payment?.reference;
    $('paymentReference').hidden=!ref;$('paymentReference').textContent=ref?'Betalreferens: '+ref+(o.payment.method?' · '+o.payment.method:''):'';
    $('items').innerHTML=(o.items||[]).map(i=>'<tr><td><b>'+c.esc(i.product_name)+'</b></td><td>'+c.esc(i.variant_name)+'</td><td>'+c.esc(i.quantity)+'</td><td><span class="chip" title="'+c.esc(i.fulfillment_type)+'">'+c.esc(c.status(i.fulfillment_type))+'</span></td><td>'+c.money(i.line_total_ore)+'</td></tr>').join('');
    const memberships=o.p4?.memberships||[],benefits=o.p4?.benefits||[];
    $('p4Status').hidden=!(memberships.length||benefits.length);
    $('p4StatusBody').innerHTML=memberships.map(m=>'<div class="line"><span>Medlemskap '+c.esc(c.status(m.membership_type))+'</span><b title="'+c.esc(m.status)+'">'+c.esc(c.status(m.status))+'</b></div>').join('')+benefits.map(b=>'<div class="line"><span>Nordic Wellness</span><b title="'+c.esc(b.status)+'">'+c.esc(c.status(b.status))+'</b></div>').join('');
    const canRetry=['NOT_ENABLED','PENDING','FAILED','CANCELLED'].includes(o.payment_status);
    retry.hidden=!canRetry||!c.cfg.apiBase||!id||!token;
    retry.textContent=c.cfg.paymentProvider==='stripe'?'Fortsätt till betalning':'Fortsätt till testbetalning';
    if(['REFUND_PENDING','PARTIALLY_REFUNDED','REFUNDED'].includes(o.payment_status)||o.fulfillment_status==='REVIEW_REQUIRED')guidance.textContent='Återbetalning och leverans granskas var för sig. BoIS behöver kontrollera eventuellt medlemskap, gymkort eller redan beställt matchställ. Gör inte ett nytt köp för att lösa detta.';
    else if(canRetry)guidance.textContent='Ordern finns kvar men betalningen är inte bekräftad. Fortsätt på samma order eller uppdatera status om svaret dröjer.';
    else if(benefits.some(b=>b.status==='PENDING_MEMBER_VERIFICATION'))guidance.textContent='Betalningen är bekräftad. BoIS behöver verifiera ditt befintliga medlemskap innan gymkortet kan lämnas vidare till Nordic.';
    else if(benefits.some(b=>b.status==='ELIGIBLE'))guidance.textContent='Betalningen och rätten till gymkort är godkända. Gymkortet är inte aktiverat ännu; nästa steg hanteras av BoIS och Nordic.';
    else if(o.fulfillment_status==='WAITING_BATCH')guidance.textContent='Ditt betalda matchställ väntar på en samlad beställning: åtta betalda ställ eller högst sju dagars väntetid. Det är inte ett löfte om leveransdag.';
    else guidance.textContent='Betalningen är bekräftad. Se respektive medlemskap, gymkort och orderrad för nästa steg.';
  }
  async function load(){
    refresh.disabled=true;$('error').hidden=true;
    try{
      if(!c.cfg.apiBase){render({public_id:'BOIS-DEMO-0001',status:'PENDING_PAYMENT',payment_status:'NOT_ENABLED',fulfillment_status:'ON_HOLD',total_ore:300000,items:[]});$('intro').textContent='Demovy – ingen riktig order hämtas.';return;}
      if(!id||!token)throw new Error('Orderlänken är ofullständig. Använd länken från orderbekräftelsen.');
      const body=await c.api('order',{query:'&id='+encodeURIComponent(id)+'&token='+encodeURIComponent(token)});
      if(!body.order)throw new Error('Ordern kunde inte hämtas.');render(body.order);
    }catch(error){$('intro').textContent='Ordern kunde inte visas just nu.';$('error').textContent=error.message;$('error').hidden=false;retry.hidden=true;}
    finally{refresh.disabled=false;}
  }
  refresh.addEventListener('click',load);load();
})();
