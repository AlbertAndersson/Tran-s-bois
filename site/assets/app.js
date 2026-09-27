(() => {
  'use strict';
  const cfg = window.BOIS_CONFIG || { mode: 'demo', apiBase: null, environmentLabel: 'LIVE DEMO' };
  const $ = (id) => document.getElementById(id);
  const form = $('orderForm');
  const btn = $('orderBtn');
  const namePrint = $('nameprint');
  const numberPrint = $('numberprint');
  const dialog = $('confirmationDialog');
  const prices = { shirt: 449, shorts: 349, name: 100, number: 100 };
  $('environmentLabel').textContent = cfg.environmentLabel || (cfg.apiBase ? 'P1 STAGING' : 'LIVE DEMO');
  if (cfg.apiBase) {
    $('modeNotice').classList.add('staging');
    $('modeNotice').innerHTML = '<b>P1 staging:</b> Beställningar sparas i testmiljön. Använd endast testuppgifter – ingen betalning sker.';
    btn.textContent = 'Skicka testbeställning →';
    $('paymentNote').textContent = 'Ordern sparas, men betalning är avstängd i P1.';
  }
  const refreshPrice = () => {
    const name = namePrint.checked ? prices.name : 0;
    const number = numberPrint.checked ? prices.number : 0;
    $('nameLine').style.display = namePrint.checked ? 'flex' : 'none';
    $('numberLine').style.display = numberPrint.checked ? 'flex' : 'none';
    $('total').textContent = `${prices.shirt + prices.shorts + name + number} kr`;
  };
  namePrint.addEventListener('change', refreshPrice); numberPrint.addEventListener('change', refreshPrice); refreshPrice();
  function payload() { return { team:$('team').value, shirt_size:$('shirt').value, shorts_size:$('shorts').value, player_name:$('player').value.trim(), number:$('number').value.trim(), name_print:namePrint.checked, number_print:numberPrint.checked, parent_name:$('parent').value.trim(), email:$('mail').value.trim(), phone:$('phone').value.trim(), consent:$('consent').checked, website:$('website').value, idempotency_key:(crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`) }; }
  function showError(message){const el=$('formError');el.textContent=message;el.hidden=false;el.scrollIntoView({behavior:'smooth',block:'center'});} function clearError(){$('formError').hidden=true;}
  function showConfirmation(order){$('confirmationTitle').textContent=cfg.apiBase?'Testbeställningen är registrerad':'Så kommer bekräftelsen att se ut';$('confirmationText').textContent=cfg.apiBase?'Ordern är sparad i P1-testmiljön och syns nu i ledarvyn.':'I den skarpa versionen registreras ordern här och blir direkt synlig för lagets ledare.';$('orderNumber').hidden=false;$('orderNumber').textContent=order.order_id||'BOIS-DEMO-001';$('confirmationSmall').textContent=cfg.apiBase?'Ingen betalning har genomförts. Använd ledarvyn för att kontrollera ordern och ändra status.':'Detta är endast en demo. Ingenting har sparats.';dialog.showModal();}
  async function submitReal(data){const response=await fetch(`${cfg.apiBase}?action=orders`,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(data)});const body=await response.json().catch(()=>({}));if(!response.ok)throw new Error(body.error||'Beställningen kunde inte sparas.');return body;}
  form.addEventListener('submit',async(event)=>{event.preventDefault();clearError();if(!form.reportValidity())return;if(!/^\d{1,2}$/.test($('number').value.trim()))return showError('Ange ett tröjnummer med 1–2 siffror.');btn.disabled=true;const original=btn.textContent;btn.textContent=cfg.apiBase?'Sparar…':'Öppnar demo…';try{const result=cfg.apiBase?await submitReal(payload()):{order_id:'BOIS-DEMO-001'};showConfirmation(result);if(cfg.apiBase)form.reset();refreshPrice();}catch(error){showError(error.message||'Ett oväntat fel uppstod.');}finally{btn.disabled=false;btn.textContent=original;}});
  $('closeDialog').addEventListener('click',()=>dialog.close());
})();
