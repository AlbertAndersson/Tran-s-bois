(() => {
  'use strict';
  const c=window.BOIS_COMMERCE,$=id=>document.getElementById(id),q=new URLSearchParams(location.search);
  const publicId=q.get('id')||'',publicToken=q.get('token')||'';
  const provider=c.cfg.paymentProvider||'mock';
  const methods=Array.isArray(c.cfg.paymentMethods)&&c.cfg.paymentMethods.length?c.cfg.paymentMethods:['card','swish'];
  const retryable=['NOT_ENABLED','PENDING','FAILED','CANCELLED'];
  let session=null,order=null,busy=false;
  $('methodStep').hidden=true;$('providerStep').hidden=true;
  $('orderLink').href='order.html?id='+encodeURIComponent(publicId)+'&token='+encodeURIComponent(publicToken);
  $('swishBtn').hidden=!methods.includes('swish');$('cardBtn').hidden=!methods.includes('card');
  if(provider==='stripe'){
    $('paymentIntro').textContent='Betalningen genomförs hos Stripe. Betalningsuppgifterna hanteras där.';
    $('paymentNotice').textContent='Ordern räknas som betald först när servern har bekräftat betalningen – inte enbart när du återkommer från Stripe.';
  }else{
    $('swishBtn').textContent='Test-Swish';$('cardBtn').textContent='Test-kort';
    $('paymentIntro').textContent='Demo · syntetiska data. Ingen riktig betalning genomförs.';
    $('paymentNotice').textContent='Välj testmetod och därefter ett testutfall. Vid avbrott kan du försöka igen på samma order.';
  }
  const refresh=document.createElement('button');refresh.id='refreshPayment';refresh.type='button';refresh.className='btn ghost block';refresh.textContent='Kontrollera betalstatus';
  $('orderLink').after(refresh);
  function message(text,isError=false){const el=$(isError?'error':'success');el.textContent=text;el.hidden=false;}
  function clear(){$('error').hidden=true;$('success').hidden=true;}
  function setBusy(value){busy=value;['swishBtn','cardBtn','payBtn','failBtn','cancelBtn'].forEach(id=>$(id).disabled=value);refresh.disabled=value;$('orderSummary').setAttribute('aria-busy',String(value));}
  function render(o){
    $('orderSummary').innerHTML='<div class="line"><span>Order</span><b>'+c.esc(o.public_id)+'</b></div><div class="line"><span>Betalstatus</span><b title="'+c.esc(o.payment_status)+'">'+c.esc(c.status(o.payment_status))+'</b></div><div class="line total"><span>Ordersumma</span><span>'+c.money(o.total_ore)+'</span></div>';
    $('paymentStatus').textContent=c.status(o.payment_status);
    $('orderLink').hidden=false;
    if(!retryable.includes(o.payment_status)){
      session=null;$('methodStep').hidden=true;$('providerStep').hidden=true;
      message(o.payment_status==='PAID'?'Betalningen är bekräftad av servern. Se medlemskap, gymkort och leverans i orderstatus.':'Ordern är redan behandlad: '+c.status(o.payment_status)+'. Se orderstatus; starta inte ett nytt köp för att lösa detta.');
    }else if(!session){
      $('methodStep').hidden=false;$('providerStep').hidden=true;
      if(o.payment_status==='FAILED')message('Testbetalningen misslyckades. Ordern finns kvar. Välj en betalmetod för att försöka igen.',true);
      if(o.payment_status==='CANCELLED')message('Testbetalningen avbröts. Ordern finns kvar. Välj en betalmetod för att försöka igen.');
    }
  }
  async function loadOrder(){
    if(!publicId||!publicToken)throw new Error('Orderlänken är ofullständig. Öppna betalningen från din orderbekräftelse.');
    const body=await c.api('order',{query:'&id='+encodeURIComponent(publicId)+'&token='+encodeURIComponent(publicToken)});
    if(!body.order?.public_id)throw new Error('Ordern kunde inte hämtas. Försök kontrollera status igen.');
    order=body.order;render(order);return order;
  }
  async function start(method){
    if(busy||!order||!retryable.includes(order.payment_status))return;
    clear();setBusy(true);
    try{
      // Re-read before a retry: a previously lost reply must not cause a second payment.
      await loadOrder();if(!retryable.includes(order.payment_status))return;
      const body=await c.api('checkout',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({public_id:publicId,public_token:publicToken,method})});
      const next=body.checkout;
      if(!next?.session_ref||!next?.session_token)throw new Error('Betalningssessionen är ofullständig. Kontrollera orderstatus.');
      session=next;
      if(session.redirect_url){
        const url=new URL(session.redirect_url);
        if(session.provider!=='stripe'||url.protocol!=='https:'||url.hostname!=='checkout.stripe.com')throw new Error('Betalningsadressen kunde inte verifieras.');
        location.assign(url.href);return;
      }
      if(session.provider!=='mock')throw new Error('Betalmetoden kan inte öppnas i testvyn.');
      $('methodStep').hidden=true;$('providerStep').hidden=false;
      $('methodLabel').textContent=method==='swish'?'Test-Swish':'Test-kort';$('sessionLabel').textContent=session.session_ref;$('paymentStatus').textContent='Väntar på testutfall';
      c.trackSales('checkout_started').catch(()=>{});
    }catch(error){session=null;$('providerStep').hidden=true;$('methodStep').hidden=!order||!retryable.includes(order.payment_status);message(error.message,true);}
    finally{setBusy(false);}
  }
  async function outcome(value){
    if(busy||!session||session.provider!=='mock')return;
    clear();setBusy(true);
    try{
      await c.api('mock_payment_event',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({session_ref:session.session_ref,session_token:session.session_token,outcome:value})});
      session=null;await loadOrder();
    }catch(error){
      // The write may have succeeded even when its response was lost.
      try{await loadOrder();if(order.payment_status!=='PAID')message(error.message+' Kontrollera status innan du fortsätter.',true);}
      catch{message('Betalstatus kunde inte bekräftas. Ingen ny order behövs. Använd Kontrollera betalstatus.',true);}
    }finally{setBusy(false);}
  }
  refresh.addEventListener('click',async()=>{if(busy)return;clear();setBusy(true);try{session=null;await loadOrder();}catch(error){message(error.message,true);}finally{setBusy(false);}});
  $('swishBtn').addEventListener('click',()=>start('swish'));$('cardBtn').addEventListener('click',()=>start('card'));
  $('payBtn').addEventListener('click',()=>outcome('paid'));$('failBtn').addEventListener('click',()=>outcome('failed'));$('cancelBtn').addEventListener('click',()=>outcome('cancelled'));
  setBusy(true);loadOrder().then(()=>{if(q.get('checkout')==='cancelled'&&retryable.includes(order.payment_status))message('Du återvände utan bekräftad betalning. Kontrollera status eller välj betalmetod igen.');}).catch(error=>message(error.message,true)).finally(()=>setBusy(false));
})();
