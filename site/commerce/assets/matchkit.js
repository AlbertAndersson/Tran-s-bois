(() => {
  'use strict';
  const c=window.BOIS_COMMERCE;
  const $=id=>document.getElementById(id);

  $('kitForm').addEventListener('submit',async event=>{
    event.preventDefault();
    $('error').hidden=true;$('success').hidden=true;
    if(!event.currentTarget.reportValidity()) return;

    const number=Number($('number').value.trim());
    if(!Number.isInteger(number)||number<1||number>99){
      $('error').textContent='Ange ett tröjnummer mellan 1 och 99.';$('error').hidden=false;return;
    }

    const button=$('submitBtn');button.disabled=true;const original=button.textContent;button.textContent='Sparar…';
    try{
      const order=await c.createOrder({
        customer:{name:$('buyerName').value.trim(),email:$('email').value.trim(),phone:$('phone').value.trim()},
        items:[{
          sku:'MATCHKIT-STAGING',quantity:1,
          metadata:{
            team:$('team').value,
            player_name:$('player').value.trim(),
            number:String(number),
            shirt_size:$('shirt').value,
            shorts_size:$('shorts').value,
            name_print:$('namePrint').checked,
            number_print:$('numberPrint').checked
          }
        }],
        consent:$('consent').checked,
        website:'',
        idempotency_key:c.uuid()
      });
      $('success').innerHTML='<b>Matchställsorder skapad.</b><div class="order-id" style="margin-top:10px">'+order.public_id+'</div><div class="small" style="margin-top:8px">Ordern går inte till batch förrän en signerad testbetalning har verifierats.</div>'+(c.cfg.apiBase?'<a class="btn secondary block" style="margin-top:12px" href="payment.html?id='+encodeURIComponent(order.public_id)+'&token='+encodeURIComponent(order.public_token)+'">Gå till testbetalning →</a><a class="btn ghost block" style="margin-top:8px" href="order.html?id='+encodeURIComponent(order.public_id)+'&token='+encodeURIComponent(order.public_token)+'">Visa orderstatus</a>':'');
      $('success').hidden=false;
    }catch(error){$('error').textContent=error.message;$('error').hidden=false;}
    finally{button.disabled=false;button.textContent=original;}
  });
})();