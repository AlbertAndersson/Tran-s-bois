(() => {
  'use strict';
  const c=window.BOIS_COMMERCE;
  const $=id=>document.getElementById(id);
  const q=new URLSearchParams(location.search);
  const publicId=q.get('id')||'';
  const publicToken=q.get('token')||'';
  let session=null;

  $('orderLink').href='order.html?id='+encodeURIComponent(publicId)+'&token='+encodeURIComponent(publicToken);

  function showError(message){$('error').textContent=message;$('error').hidden=false;}
  function clear(){ $('error').hidden=true; $('success').hidden=true; }
  function setBusy(busy){['swishBtn','cardBtn','payBtn','failBtn','cancelBtn'].forEach(id=>$(id).disabled=busy); }

  async function loadOrder(){
    if(!publicId||!publicToken) throw new Error('Orderreferens saknas.');
    const body=await c.api('order',{query:'&id='+encodeURIComponent(publicId)+'&token='+encodeURIComponent(publicToken)});
    const o=body.order;
    $('orderSummary').innerHTML=
      '<div class="line"><span>Order</span><b>'+o.public_id+'</b></div>'+
      '<div class="line"><span>Betalstatus</span><b>'+o.payment_status+'</b></div>'+
      '<div class="line total"><span>Att betala</span><span>'+c.money(o.total_ore)+'</span></div>';
    if(['PAID','PARTIALLY_REFUNDED','REFUNDED'].includes(o.payment_status)){
      $('methodStep').hidden=true;
      $('success').innerHTML='<b>Ordern är redan behandlad.</b> Betalstatus: '+o.payment_status+'.';
      $('success').hidden=false;
      $('orderLink').hidden=false;
    }
  }

  async function start(method){
    clear();setBusy(true);
    try{
      const body=await c.api('checkout',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({public_id:publicId,public_token:publicToken,method})
      });
      session=body.checkout;
      $('methodStep').hidden=true;$('providerStep').hidden=false;
      $('methodLabel').textContent=method==='swish'?'Test-Swish':'Test-kort';
      $('sessionLabel').textContent=session.session_ref;
      $('paymentStatus').textContent=session.status;
    }catch(error){showError(error.message);}
    finally{setBusy(false);}
  }

  async function outcome(value){
    if(!session) return;
    clear();setBusy(true);
    try{
      const body=await c.api('mock_payment_event',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({session_ref:session.session_ref,session_token:session.session_token,outcome:value})
      });
      const status=body.payment?.result?.status||value.toUpperCase();
      $('paymentStatus').textContent=status;
      $('success').innerHTML=value==='paid'
        ? '<b>Signerad testwebhook verifierad.</b> Ordern har gått genom P6 och vidare till P4/P5.'
        : '<b>Testutfall registrerat.</b> Status: '+status+'.';
      $('success').hidden=false;$('orderLink').hidden=false;
      if(value==='paid') $('providerStep').querySelectorAll('button').forEach(b=>b.disabled=true);
    }catch(error){showError(error.message);}
    finally{if(value!=='paid') setBusy(false);}
  }

  $('swishBtn').addEventListener('click',()=>start('swish'));
  $('cardBtn').addEventListener('click',()=>start('card'));
  $('payBtn').addEventListener('click',()=>outcome('paid'));
  $('failBtn').addEventListener('click',()=>outcome('failed'));
  $('cancelBtn').addEventListener('click',()=>outcome('cancelled'));

  loadOrder().catch(error=>showError(error.message));
})();
