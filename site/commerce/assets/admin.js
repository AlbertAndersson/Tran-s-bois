(() => {
  'use strict';
  const c=window.BOIS_COMMERCE,$=id=>document.getElementById(id),esc=c.esc;
  let token=c.storage.get('boisP3Admin'),orders=[],health={},loading=false;
  const dashboard=$('dashboard');
  const warning=document.createElement('div');warning.id='qaAdminWarning';warning.className='error';warning.hidden=true;warning.setAttribute('role','alert');dashboard.prepend(warning);
  const tasks=document.createElement('section');tasks.id='qaTasks';tasks.className='card';tasks.innerHTML='<h2>Att göra nu</h2><p class="small">Status från servern. Återbetalning återkallar inte automatiskt medlemskap, gymkort eller leverans.</p><div class="qa-tasks" id="qaTaskCounts"></div>';
  dashboard.querySelector('.kpis').before(tasks);
  const nav=document.createElement('nav');nav.className='qa-admin-nav';nav.setAttribute('aria-label','Adminavsnitt');nav.innerHTML='<a href="#qaTasks">Att göra</a><a href="#qaMembers">Medlem & Nordic</a><a href="#qaBatches">Matchställ</a><a href="#qaOrders">Order</a><a href="#qaPayments">Betalning & fel</a><a href="#qaSales">Försäljning</a>';
  tasks.before(nav);
  const refresh=document.createElement('button');refresh.id='qaRefreshAdmin';refresh.type='button';refresh.className='btn ghost';refresh.textContent='Uppdatera översikten';nav.append(refresh);
  $('entitlements').closest('.card').id='qaMembers';$('batches').closest('.table-wrap').id='qaBatches';$('orders').closest('.table-wrap').id='qaOrders';$('payments').closest('.table-wrap').id='qaPayments';$('salesFunnel').closest('.card').id='qaSales';
  const filters=document.createElement('div');filters.className='row qa-admin-tools';filters.innerHTML='<div class="field"><label for="qaOrderSearch">Sök order, namn eller e-post</label><input id="qaOrderSearch" type="search" autocomplete="off"></div><div class="field"><label for="qaOrderFilter">Visa order</label><select id="qaOrderFilter"><option value="all">Alla senaste order</option><option value="unpaid">Inte betalda / avbrutna</option><option value="review">Återbetalning / manuell granskning</option><option value="paid">Betalda</option></select></div>';
  $('qaOrders').before(filters);
  const resultNote=document.createElement('p');resultNote.id='qaOrderCount';resultNote.className='small';filters.after(resultNote);
  const reviewNote=document.createElement('p');reviewNote.className='notice blue qa-review-note';reviewNote.textContent='Vid återbetalning: kontrollera betalhändelsen och vad som redan levererats. Ingen återbetalning till Stripe startas från denna översikt.';$('qaPayments').before(reviewNote);
  function badge(code){return '<span class="chip" title="'+esc(code)+'">'+esc(c.status(code))+'</span>';}
  function demo(action){if(action==='health')return {mode:'demo',payment_provider:'disabled'};return {orders:[],products:[],stats:{},batches:[],waiting:{},members:[],entitlements:[],payments:[],events:[],assortment:[],sales:{kpis:{},funnel:{},campaigns:[],products:[]}};}
  async function api(action,options={}){if(!c.cfg.apiBase)return demo(action);return c.api(action,{...options,headers:{...(options.headers||{}),Authorization:'Bearer '+token}});}
  function post(action,payload={}){return api(action,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});}
  function show(text,error=false){const el=$(error?'batchError':'batchMessage');el.textContent=text;el.hidden=false;}
  function renderTasks(p4,batch,payment){
    const counts=[['Medlemskontroll',p4?.stats?.pending_member_verification,'qaMembers'],['Gymkort att lämna till Nordic',p4?.stats?.eligible_gym,'qaMembers'],['Matchställ i kö',batch?.waiting?.waiting_item_count,'qaBatches'],['Betalhändelser med fel',payment?(payment.events||[]).filter(e=>e.status==='ERROR').length:null,'qaPayments'],['Order att granska',orders.filter(o=>['REFUND_PENDING','PARTIALLY_REFUNDED','REFUNDED'].includes(o.payment_status)||o.fulfillment_status==='REVIEW_REQUIRED').length,'qaOrders']];
    $('qaTaskCounts').innerHTML=counts.map(([label,value,anchor])=>'<a class="qa-task" href="#'+anchor+'"><b>'+esc(value??'–')+'</b>'+esc(label)+'</a>').join('');
  }
  function renderOrders(){
    const term=$('qaOrderSearch').value.trim().toLocaleLowerCase('sv'),filter=$('qaOrderFilter').value;
    const rows=orders.filter(o=>{
      if(term&&!([o.public_id,o.customer_name,o.customer_email].join(' ').toLocaleLowerCase('sv').includes(term)))return false;
      if(filter==='unpaid')return ['NOT_ENABLED','PENDING','FAILED','CANCELLED'].includes(o.payment_status);
      if(filter==='paid')return o.payment_status==='PAID';
      if(filter==='review')return o.fulfillment_status==='REVIEW_REQUIRED'||['REFUND_PENDING','PARTIALLY_REFUNDED','REFUNDED'].includes(o.payment_status);
      return true;
    });
    $('qaOrderCount').textContent='Visar '+rows.length+' av '+orders.length+' hämtade order (senaste högst 100).';
    $('orders').innerHTML=rows.length?rows.map(o=>{
      const canSim=['staging','test'].includes(health.mode)&&health.payment_provider==='mock'&&['NOT_ENABLED','PENDING','FAILED','CANCELLED'].includes(o.payment_status);
      return '<tr><td><b>'+esc(o.public_id)+'</b><div class="small">'+esc(o.created_at)+'</div></td><td>'+esc(o.customer_name)+'<div class="small">'+esc(o.customer_email)+'</div></td><td>'+(o.items||[]).map(i=>'<span class="chip">'+esc(i.sku)+'</span>').join(' ')+'</td><td>'+c.money(o.total_ore)+'</td><td>'+badge(o.payment_status)+'</td><td>'+badge(o.fulfillment_status)+'</td><td>'+(canSim?'<button class="btn ghost" data-paid="'+esc(o.public_id)+'">Simulera betald</button>':'–')+'</td></tr>';
    }).join(''):'<tr><td colspan="7">Inga order matchar filtret.</td></tr>';
  }
  function renderBatches(body){
    const w=body.waiting||{},batches=body.batches||[];
    ['waitingKpi','waitingItems'].forEach(id=>$(id).textContent=w.waiting_item_count??0);$('waitingOrders').textContent=w.waiting_order_count??0;$('remainingItems').textContent=w.threshold_remaining??0;$('oldestWait').textContent=w.oldest_wait_hours==null?'–':w.oldest_wait_hours+' h';$('thresholdText').textContent=w.threshold_qty??8;$('waitText').textContent=Math.round((w.max_wait_hours??168)/24)+' dagar';$('batchesKpi').textContent=batches.length;
    $('batches').innerHTML=batches.length?batches.map(b=>'<tr><td><b>'+esc(b.public_id)+'</b><div class="small">'+esc(b.supplier_name)+'</div></td><td>'+esc(({MANUAL:'Manuellt',THRESHOLD:'Åtta betalda ställ',MAX_WAIT:'Max väntetid'})[b.trigger_reason]||b.trigger_reason)+'</td><td>'+esc(b.order_count)+' / '+esc(b.item_count)+'</td><td>'+badge(b.status)+'</td><td>'+badge(b.outbox_status)+'<div class="small">Försök: '+esc(b.attempts??0)+'</div>'+(['RETRY','FAILED'].includes(b.outbox_status)&&b.outbox_id?'<button class="btn ghost" data-retry="'+Number(b.outbox_id)+'">Försök igen</button>':'')+'</td><td>'+esc(b.created_at)+'</td><td><button class="btn ghost" data-csv="'+esc(b.public_id)+'">Hämta CSV</button></td></tr>').join(''):'<tr><td colspan="7">Inga samlade beställningar ännu.</td></tr>';
  }
  function renderP4(body){
    const s=body.stats||{};for(const [id,key] of [['activeMembersKpi','active_members'],['pendingVerifyKpi','pending_member_verification'],['eligibleGymKpi','eligible_gym'],['activatedGymKpi','activated_gym']])$(id).textContent=s[key]??0;
    $('members').innerHTML=(body.members||[]).map(m=>'<tr><td><b>'+esc(m.member_name)+'</b></td><td>'+esc(c.status(m.membership_type))+'</td><td>'+badge(m.status)+'</td><td>'+esc(m.valid_from)+' → '+esc(m.valid_to)+'</td><td>'+esc(m.source)+'</td><td>'+esc(m.email)+'</td></tr>').join('')||'<tr><td colspan="6">Inga medlemsrader ännu.</td></tr>';
    $('entitlements').innerHTML=(body.entitlements||[]).map(e=>{
      let action='–';
      if(e.status==='PENDING_MEMBER_VERIFICATION')action='<button class="btn ghost" data-verify="'+esc(e.order_public_id)+'" data-name="'+esc(e.customer_name)+'">Verifiera medlem</button>';
      else {const next={ELIGIBLE:['SENT_TO_PARTNER','Markera skickat till Nordic'],SENT_TO_PARTNER:['READY_FOR_PICKUP','Markera klart att hämta'],READY_FOR_PICKUP:['ACTIVATED','Markera aktiverat']}[e.status];if(next)action='<button class="btn ghost" data-benefit="'+Number(e.id)+'" data-status="'+next[0]+'">'+next[1]+'</button>';}
      return '<tr><td><b>'+esc(e.order_public_id)+'</b></td><td>'+esc(e.customer_name)+'<div class="small">'+esc(e.customer_email)+'</div></td><td>'+esc(e.member_name||'Ej verifierad')+'</td><td>'+badge(e.status)+'</td><td>'+esc(e.partner_ref||'–')+'</td><td>'+action+'</td></tr>';
    }).join('')||'<tr><td colspan="6">Inga Nordic-ärenden ännu.</td></tr>';
  }
  function renderPayments(body){
    $('payments').innerHTML=(body.payments||[]).map(p=>'<tr><td>'+esc(p.public_id)+'</td><td>'+esc(p.provider_ref||'–')+'</td><td>'+badge(p.status)+'</td><td>'+c.money(p.amount_ore)+'</td><td>'+badge(p.effects_status)+'</td></tr>').join('')||'<tr><td colspan="5">Inga betalningar.</td></tr>';
    $('paymentEvents').innerHTML=(body.events||[]).map(e=>'<tr><td>'+esc(e.event_id)+'</td><td>'+esc(e.event_type)+'</td><td>'+badge(e.status)+'</td><td>'+esc(e.attempts)+'</td><td>'+esc(e.last_error||'–')+'</td></tr>').join('')||'<tr><td colspan="5">Inga betalhändelser.</td></tr>';
  }
  function renderP7(items){
    $('p7Assortment').innerHTML=items.map(p=>'<tr><td><b>'+esc(p.name)+'</b><br>'+(p.variants||[]).map(v=>esc(v.size)+' / '+esc(v.color)+' (SKU '+esc(v.supplier_sku||'saknas')+')').join('<br>')+'</td><td>'+esc(p.supplier_candidate||'Saknas')+' (kandidat)<br>SKU '+esc(p.supplier_sku||'saknas')+'</td><td>'+esc(p.verification_status)+'<br>Pris: '+esc(p.price_status)+'</td><td>'+(p.purchase_price_ore==null?'Saknas':c.money(p.purchase_price_ore))+'</td><td>'+(p.sale_price_ore==null?'Saknas':c.money(p.sale_price_ore))+'</td><td>'+(p.margin_ore==null?'Kan inte beräknas':c.money(p.margin_ore)+' / '+esc(p.margin_pct)+' %')+'</td><td>'+esc(c.status(p.fulfillment_type))+'<br>'+esc(p.stock_strategy)+'</td><td>'+(p.approved?'Godkänt från '+esc(p.launch_date):'Blockerat')+'</td><td>'+esc((p.blockers||[]).join('; '))+'</td></tr>').join('')||'<tr><td colspan="9">Sortimentsunderlag saknas.</td></tr>';
  }
  function renderSales(body){
    const s=body.sales||{},k=s.kpis||{},f=s.funnel||{};$('salesSessionsKpi').textContent=k.sessions??0;$('salesPaidKpi').textContent=k.paid_sessions??0;$('salesConversionKpi').textContent=(k.session_to_paid_pct??0)+' %';$('salesNetKpi').textContent=c.money(k.net_paid_ore??0);
    const labels={page_view:'Sidvisning',product_view:'Produktvisning',order_created:'Order skapad',checkout_view:'Betalningssida',checkout_started:'Betalning startad',paid:'Betald'};
    $('salesFunnel').innerHTML=Object.entries(labels).map(([key,label])=>'<tr><td>'+label+'</td><td>'+esc(f[key]??0)+'</td></tr>').join('');
    $('salesCampaigns').innerHTML=(s.campaigns||[]).map(r=>'<tr><td>'+esc(r.source)+'</td><td>'+esc(r.medium)+'</td><td>'+esc(r.campaign)+'</td><td>'+esc(r.ref_code)+'</td><td>'+esc(r.sessions)+'</td><td>'+esc(r.orders)+'</td><td>'+esc(r.paid_orders)+'</td><td>'+c.money(r.net_paid_ore)+'</td></tr>').join('')||'<tr><td colspan="8">Ingen kampanjdata ännu. Här visas bara attribuerade sessioner och order.</td></tr>';
    $('salesProducts').innerHTML=(s.products||[]).map(r=>'<tr><td>'+esc(r.sku)+'</td><td>'+esc(r.product_name)+'</td><td>'+esc(r.quantity)+'</td><td>'+esc(r.paid_orders)+'</td><td>'+c.money(r.gross_item_ore)+'</td></tr>').join('')||'<tr><td colspan="5">Inga attribuerade betalda order ännu.</td></tr>';
  }
  async function load(){
    if(loading)return;loading=true;refresh.disabled=true;warning.hidden=true;
    try{
      health=await api('health');
      const main=await api('admin_orders');orders=main.orders||[];renderOrders();$('ordersKpi').textContent=orders.length;
      const actions=['admin_catalog','admin_batches','admin_p4','admin_payments','admin_p7','admin_sales'];
      const results=await Promise.allSettled(actions.map(a=>api(a)));const values={};const missing=[];
      results.forEach((result,index)=>{if(result.status==='fulfilled')values[actions[index]]=result.value;else missing.push(actions[index]);});
      if(values.admin_catalog){const b=values.admin_catalog;$('ordersKpi').textContent=b.stats?.bois_orders??orders.length;$('outboxKpi').textContent=b.stats?.bois_email_outbox??0;$('catalog').innerHTML=(b.products||[]).map(p=>'<tr><td>'+esc(p.name)+'</td><td>'+esc(p.category)+'</td><td>'+esc(c.status(p.fulfillment_type))+'</td><td>'+(p.is_public?'Ja':'Nej')+'</td><td>'+(p.is_orderable?'Ja':'Nej')+'</td></tr>').join('');}
      if(values.admin_batches)renderBatches(values.admin_batches);
      if(values.admin_p4)renderP4(values.admin_p4);
      if(values.admin_payments)renderPayments(values.admin_payments);
      if(values.admin_p7)renderP7(values.admin_p7.assortment||[]);
      if(values.admin_sales)renderSales(values.admin_sales);
      renderTasks(values.admin_p4,values.admin_batches,values.admin_payments);
      if(missing.length){warning.textContent='Delar av översikten kunde inte uppdateras ('+missing.map(a=>({admin_catalog:'katalog',admin_batches:'matchställ',admin_p4:'medlemskap/Nordic',admin_payments:'betalningar',admin_p7:'sortiment',admin_sales:'försäljningsöversikt'})[a]).join(', ')+'). Övriga delar fungerar. Tidigare visade siffror kan vara inaktuella. Tryck Uppdatera översikten.';warning.hidden=false;}
      $('login').hidden=true;dashboard.hidden=false;
    }finally{loading=false;refresh.disabled=false;}
  }
  function verifyDialog(name){
    return new Promise(resolve=>{
      const previous=document.activeElement,dialog=document.createElement('dialog');
      dialog.innerHTML='<form><h2>Verifiera befintlig medlem</h2><p>Kontrollera medlemskapet innan du godkänner gymförmånen.</p><div class="field"><label for="qaMemberName">Medlemmens namn</label><input id="qaMemberName" name="member_name" maxlength="160" required></div><div class="field"><label for="qaMemberType">Medlemstyp</label><select id="qaMemberType" name="membership_type"><option value="adult">Vuxen</option><option value="youth">Ungdom</option><option value="senior">Pensionär</option></select></div><div class="field"><label for="qaMemberRef">Medlemsreferens (frivillig)</label><input id="qaMemberRef" name="external_member_ref" maxlength="100"></div><div class="chips"><button type="submit" class="btn">Bekräfta verifiering</button><button type="button" class="btn ghost" data-cancel>Avbryt</button></div></form>';
      document.body.append(dialog);dialog.querySelector('[name=member_name]').value=name||'';
      function close(value){dialog.close();dialog.remove();previous?.focus();resolve(value);}
      dialog.addEventListener('cancel',e=>{e.preventDefault();close(null);});dialog.querySelector('[data-cancel]').onclick=()=>close(null);
      dialog.querySelector('form').onsubmit=e=>{e.preventDefault();close(Object.fromEntries(new FormData(e.target)));};dialog.showModal();
    });
  }
  async function action(button,fn){if(button.disabled)return;button.disabled=true;$('batchError').hidden=true;$('batchMessage').hidden=true;try{await fn();await load();}catch(error){show(error.message,true);}finally{button.disabled=false;}}
  $('orders').addEventListener('click',e=>{const b=e.target.closest('[data-paid]');if(b&&confirm('Simulera signerad mockbetalning för denna TESTORDER?'))action(b,async()=>{await post('admin_simulate_paid',{public_id:b.dataset.paid});show('Testbetalning verifierad. Översikten är uppdaterad.');});});
  $('entitlements').addEventListener('click',async e=>{
    const b=e.target.closest('button');if(!b)return;
    if(b.dataset.verify){const fields=await verifyDialog(b.dataset.name);if(fields)action(b,async()=>{await post('admin_verify_existing_member',{public_id:b.dataset.verify,...fields});show('Medlemskapet är verifierat. Gymkortet är nu godkänt för nästa steg hos Nordic.');});}
    else if(b.dataset.benefit&&confirm('Uppdatera Nordic-ärendet till '+c.status(b.dataset.status)+'? Detta registrerar status; det skickar inte något mejl.'))action(b,async()=>{await post('admin_benefit_status',{entitlement_id:Number(b.dataset.benefit),status:b.dataset.status,partner_ref:''});show('Nordic-status uppdaterad: '+c.status(b.dataset.status)+'.');});
  });
  async function download(actionName,filename,query=''){
    if(!c.cfg.apiBase)throw new Error('Export kräver ansluten staging.');
    const response=await fetch(c.cfg.apiBase+'?action='+actionName+query,{headers:{Authorization:'Bearer '+token}});
    if(!response.ok)throw new Error('Exporten kunde inte hämtas. Kontrollera adminnyckeln och försök igen.');
    const url=URL.createObjectURL(await response.blob()),a=document.createElement('a');a.href=url;a.download=filename;a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);
  }
  $('batches').addEventListener('click',e=>{const b=e.target.closest('button');if(!b)return;if(b.dataset.csv)action(b,()=>download('admin_batch_csv','tranas-bois-'+b.dataset.csv+'.csv','&id='+encodeURIComponent(b.dataset.csv)));if(b.dataset.retry)action(b,async()=>{await post('admin_retry_outbox',{outbox_id:Number(b.dataset.retry)});show('Nytt försök förberett. Utskick är fortfarande avstängda i staging.');});});
  $('batchNow').onclick=()=>{if(confirm('Skapa samlad beställning av betalda matchställ som väntar? I staging skickas inget externt mejl.'))action($('batchNow'),async()=>{const r=await post('admin_batch_now');show(r.batches?.length?'Samlad beställning skapad. Inget externt mejl skickades.':'Inga betalda matchställ väntar.');});};
  $('runWorker').onclick=()=>action($('runWorker'),async()=>{const r=await post('admin_run_worker');show('Automatikkontroll klar. Nya samlade beställningar: '+(r.batches?.length||0)+'. Mejlsändning: '+(r.mail?.transport==='disabled'?'avstängd':r.mail?.transport||'okänd')+'.');});
  $('nordicExport').onclick=()=>action($('nordicExport'),()=>download('admin_nordic_export','tranas-bois-nordic-wellness.csv'));
  $('qaOrderSearch').addEventListener('input',renderOrders);$('qaOrderFilter').addEventListener('change',renderOrders);
  $('buildCampaignLink').onclick=()=>{const url=new URL('index.html',location.href);url.search='';for(const [id,key] of [['campaignSource','utm_source'],['campaignMedium','utm_medium'],['campaignName','utm_campaign'],['campaignRef','ref']]){const value=$(id).value.trim().replace(/[^\p{L}\p{N}._:+\/-]/gu,'').slice(0,120);if(value)url.searchParams.set(key,value);}$('campaignLink').value=url.href;$('campaignLink').focus();$('campaignLink').select();};
  refresh.onclick=()=>load().catch(error=>{warning.textContent=error.message;warning.hidden=false;});
  $('loginForm').addEventListener('submit',async e=>{e.preventDefault();token=$('token').value.trim();$('loginError').hidden=true;try{await load();c.storage.set('boisP3Admin',token);}catch(error){$('loginError').textContent=error.message;$('loginError').hidden=false;}});
  $('logout').onclick=()=>{token='';c.storage.remove('boisP3Admin');$('token').value='';dashboard.hidden=true;$('login').hidden=false;};
  if(!c.cfg.apiBase||token)load().catch(()=>{c.storage.remove('boisP3Admin');});
})();
