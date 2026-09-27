(() => {
  'use strict';

  const c=window.BOIS_COMMERCE,$=id=>document.getElementById(id);
  let token=sessionStorage.getItem('boisP3Admin')||'';
  let orders=[];

  function auth(){return {Authorization:'Bearer '+token};}

  async function api(action, options={}) {
    if(!c.cfg.apiBase) return demo(action,options);
    return c.api(action,{...options,headers:{...(options.headers||{}),...auth()}});
  }

  function demo(action) {
    if(action==='admin_catalog') return Promise.resolve({
      products:[
        {name:'Medlemskap Tranås BoIS',category:'membership',fulfillment_type:'DIGITAL_MEMBERSHIP',is_public:true,is_orderable:true},
        {name:'Nordic Wellness gymkort',category:'member_benefit',fulfillment_type:'MEMBER_BENEFIT',is_public:true,is_orderable:true},
        {name:'Matchställ',category:'match_kit',fulfillment_type:'BATCH_SUPPLIER',is_public:true,is_orderable:true},
        {name:'BoIS 1941 Hoodie',category:'supporter',fulfillment_type:'DIRECT_SUPPLIER',is_public:false,is_orderable:false}
      ],
      stats:{bois_products:10,bois_orders:2,bois_memberships:1,bois_email_outbox:1},
      batch_waiting:{waiting_order_count:2,waiting_item_count:2,threshold_qty:8,max_wait_hours:168,oldest_wait_hours:12,threshold_remaining:6}
    });
    if(action==='admin_batches') return Promise.resolve({
      waiting:{waiting_order_count:2,waiting_item_count:2,threshold_qty:8,max_wait_hours:168,oldest_wait_hours:12,threshold_remaining:6},
      batches:[{public_id:'BATCH-DEMO-001',trigger_reason:'MANUAL',order_count:2,item_count:2,status:'QUEUED',outbox_status:'PENDING',attempts:0,created_at:'2026-09-27 14:30:00',to_email:'supplier@example.invalid',cc_email:'erik@example.invalid'}]
    });
    if(action==='admin_p4') return Promise.resolve({
      stats:{active_members:1,pending_member_verification:1,eligible_gym:1,sent_to_nordic:0,ready_for_pickup:0,activated_gym:0},
      members:[{id:1,member_name:'Demo Medlem',membership_type:'adult',status:'ACTIVE',valid_from:'2026-09-27',valid_to:'2027-09-27',source:'ORDER',email:'member@example.invalid'}],
      entitlements:[
        {id:1,order_public_id:'BOIS-DEMO-MEM1',customer_name:'Demo Medlem',customer_email:'member@example.invalid',member_name:'Demo Medlem',member_status:'ACTIVE',status:'ELIGIBLE',partner_ref:null},
        {id:2,order_public_id:'BOIS-DEMO-GYM2',customer_name:'Befintlig Medlem',customer_email:'existing@example.invalid',member_name:null,member_status:null,status:'PENDING_MEMBER_VERIFICATION',partner_ref:null}
      ]
    });
    if(action==='admin_orders') return Promise.resolve({orders:[
      {public_id:'BOIS-DEMO-KIT1',customer_name:'Test Förälder',customer_email:'test@example.invalid',total_ore:99800,payment_status:'NOT_ENABLED',fulfillment_status:'ON_HOLD',created_at:'2026-09-27 14:00:00',items:[{sku:'MATCHKIT-STAGING',fulfillment_type:'BATCH_SUPPLIER'}]},
      {public_id:'BOIS-DEMO-MEM1',customer_name:'Test Medlem',customer_email:'member@example.invalid',total_ore:300000,payment_status:'NOT_ENABLED',fulfillment_status:'ON_HOLD',created_at:'2026-09-27 14:05:00',items:[{sku:'MEM-ADULT',fulfillment_type:'DIGITAL_MEMBERSHIP'},{sku:'NW-GYM-ANNUAL',fulfillment_type:'MEMBER_BENEFIT'}]}
    ]});
    if(action==='admin_payments') return Promise.resolve({payments:[],events:[],outbox:[]});
    if(action==='admin_p7') return Promise.resolve({assortment:[]});
    if(action==='admin_batch_now') return Promise.resolve({batches:[],waiting:{waiting_order_count:2,waiting_item_count:2,threshold_qty:8,max_wait_hours:168,oldest_wait_hours:12,threshold_remaining:6}});
    if(action==='admin_run_worker') return Promise.resolve({batches:[],mail:{transport:'disabled',processed:0,sent:0,failed:0},waiting:{waiting_order_count:2,waiting_item_count:2,threshold_qty:8,max_wait_hours:168,oldest_wait_hours:12,threshold_remaining:6}});
    return Promise.resolve({ok:true});
  }

  function esc(v){return String(v??'').replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));}

  function matchKitOrder(order) {
    return (order.items||[]).some(i=>i.fulfillment_type==='BATCH_SUPPLIER');
  }

  function renderWaiting(waiting) {
    waiting=waiting||{};
    $('waitingKpi').textContent=waiting.waiting_item_count??0;
    $('waitingOrders').textContent=waiting.waiting_order_count??0;
    $('waitingItems').textContent=waiting.waiting_item_count??0;
    $('remainingItems').textContent=waiting.threshold_remaining??0;
    $('oldestWait').textContent=waiting.oldest_wait_hours==null?'–':waiting.oldest_wait_hours+' h';
    $('thresholdText').textContent=waiting.threshold_qty??8;
    $('waitText').textContent=Math.round((waiting.max_wait_hours??168)/24)+' dagar';
  }

  function renderBatches(batches) {
    $('batchesKpi').textContent=batches.length;
    $('batches').innerHTML=batches.length ? batches.map(b=>{
      const retry=(b.outbox_status==='RETRY'||b.outbox_status==='FAILED') && b.outbox_id
        ? '<button class="btn ghost p5-retry" data-outbox="'+Number(b.outbox_id)+'">Retry</button>'
        : '';
      return '<tr>'+
        '<td><b>'+esc(b.public_id)+'</b><div class="small">'+esc(b.supplier_name||'')+'</div></td>'+
        '<td><span class="chip">'+esc(b.trigger_reason||'')+'</span></td>'+
        '<td>'+esc(b.order_count)+' / <b>'+esc(b.item_count)+'</b></td>'+
        '<td>'+esc(b.status)+'</td>'+
        '<td>'+esc(b.outbox_status||'–')+'<div class="small">försök: '+esc(b.attempts??0)+'</div>'+retry+'</td>'+
        '<td>'+esc(b.created_at||'')+'</td>'+
        '<td><button class="btn ghost p5-csv" data-batch="'+esc(b.public_id)+'">CSV</button></td>'+
      '</tr>';
    }).join('') : '<tr><td colspan="7">Inga batcher ännu.</td></tr>';

    document.querySelectorAll('.p5-csv').forEach(btn=>btn.addEventListener('click',()=>downloadCsv(btn.dataset.batch)));
    document.querySelectorAll('.p5-retry').forEach(btn=>btn.addEventListener('click',()=>retryOutbox(Number(btn.dataset.outbox))));
  }

  function renderOrders() {
    $('orders').innerHTML=orders.length ? orders.map(o=>{
      const canSim=c.cfg.apiBase && ['NOT_ENABLED','PENDING','FAILED','CANCELLED'].includes(o.payment_status);
      return '<tr>'+
        '<td><b>'+esc(o.public_id)+'</b><div class="small">'+esc(o.created_at)+'</div></td>'+
        '<td>'+esc(o.customer_name)+'<div class="small">'+esc(o.customer_email)+'</div></td>'+
        '<td><div class="chips">'+(o.items||[]).map(i=>'<span class="chip">'+esc(i.sku)+'</span>').join('')+'</div></td>'+
        '<td>'+c.money(o.total_ore)+'</td>'+
        '<td>'+esc(o.payment_status)+'</td>'+
        '<td>'+esc(o.fulfillment_status)+'</td>'+
        '<td>'+(canSim?'<button class="btn ghost p5-paid" data-order="'+esc(o.public_id)+'">Simulera betald</button>':'–')+'</td>'+
      '</tr>';
    }).join('') : '<tr><td colspan="7">Inga order ännu.</td></tr>';

    document.querySelectorAll('.p5-paid').forEach(btn=>btn.addEventListener('click',()=>simulatePaid(btn.dataset.order)));
  }

  function renderCatalog(products) {
    $('catalog').innerHTML=products.map(p=>
      '<tr><td><b>'+esc(p.name)+'</b></td><td>'+esc(p.category)+'</td><td><span class="chip">'+esc(p.fulfillment_type)+'</span></td><td>'+(p.is_public?'Ja':'Nej')+'</td><td>'+(p.is_orderable?'Ja':'Nej')+'</td></tr>'
    ).join('');
  }

  function renderPayments(body){
    const payments=body.payments||[],events=body.events||[];
    $('payments').innerHTML=payments.length?payments.map(p=>
      '<tr><td>'+esc(p.public_id)+'</td><td>'+esc(p.provider_ref||'–')+'</td><td>'+esc(p.status)+'</td><td>'+c.money(p.amount_ore)+'</td><td>'+esc(p.effects_status)+'</td></tr>'
    ).join(''):'<tr><td colspan="5">Inga betalningar.</td></tr>';
    $('paymentEvents').innerHTML=events.length?events.map(e=>
      '<tr><td>'+esc(e.event_id)+'</td><td>'+esc(e.event_type)+'</td><td>'+esc(e.status)+'</td><td>'+esc(e.attempts)+'</td><td>'+esc(e.last_error||'–')+'</td></tr>'
    ).join(''):'<tr><td colspan="5">Inga betalhändelser.</td></tr>';
  }

  function renderP7(items){
    $('p7Assortment').innerHTML=items.length?items.map(p=>{
      const variants=(p.variants||[]).map(v=>esc(v.size)+' / '+esc(v.color)+' <small>(SKU '+esc(v.supplier_sku||'TBD')+')</small>').join('<br>');
      const money=v=>v==null?'TBD':c.money(v);
      const launch=p.approved?'Godkänd från '+esc(p.launch_date):'Blockerad';
      return '<tr><td><b>'+esc(p.name)+'</b><br>'+variants+'</td>'+
        '<td>'+esc(p.supplier_candidate||'TBD')+' (kandidat)<br>SKU '+esc(p.supplier_sku||'TBD')+'</td>'+
        '<td>'+esc(p.verification_status)+'<br>Pris: '+esc(p.price_status)+'</td>'+
        '<td>'+money(p.purchase_price_ore)+'</td><td>'+money(p.sale_price_ore)+'</td>'+
        '<td>'+(p.margin_ore==null?'TBD':c.money(p.margin_ore)+' / '+esc(p.margin_pct)+' %')+'</td>'+
        '<td>'+esc(p.fulfillment_type)+'<br>'+esc(p.stock_strategy)+'</td><td>'+launch+'</td>'+
        '<td>'+esc((p.blockers||[]).join('; '))+'</td></tr>';
    }).join(''):'<tr><td colspan="9">P7-underlag saknas.</td></tr>';
  }


  function p4Status(status){
    return '<span class="p4-status '+esc(status||'')+'">'+esc(status||'–')+'</span>';
  }

  function renderP4(body){
    const stats=body.stats||{},members=body.members||[],entitlements=body.entitlements||[];
    $('activeMembersKpi').textContent=stats.active_members??0;
    $('pendingVerifyKpi').textContent=stats.pending_member_verification??0;
    $('eligibleGymKpi').textContent=stats.eligible_gym??0;
    $('activatedGymKpi').textContent=stats.activated_gym??0;

    $('members').innerHTML=members.length?members.map(m=>
      '<tr><td><b>'+esc(m.member_name)+'</b></td><td>'+esc(m.membership_type)+'</td><td>'+p4Status(m.status)+'</td>'+
      '<td>'+esc(m.valid_from||'–')+' → '+esc(m.valid_to||'–')+'</td><td>'+esc(m.source)+'</td><td>'+esc(m.email||'')+'</td></tr>'
    ).join(''):'<tr><td colspan="6">Inga aktiva medlemsrader ännu.</td></tr>';

    $('entitlements').innerHTML=entitlements.length?entitlements.map(e=>{
      let action='–';
      if(e.status==='PENDING_MEMBER_VERIFICATION'){
        action='<button class="btn ghost p4-action p4-verify" data-order="'+esc(e.order_public_id)+'" data-name="'+esc(e.customer_name||'')+'">Verifiera medlem</button>';
      }else if(e.status==='ELIGIBLE'){
        action='<button class="btn ghost p4-action p4-transition" data-id="'+Number(e.id)+'" data-status="SENT_TO_PARTNER">Skickad till Nordic</button>';
      }else if(e.status==='SENT_TO_PARTNER'){
        action='<button class="btn ghost p4-action p4-transition" data-id="'+Number(e.id)+'" data-status="READY_FOR_PICKUP">Klar att hämta</button>';
      }else if(e.status==='READY_FOR_PICKUP'){
        action='<button class="btn ghost p4-action p4-transition" data-id="'+Number(e.id)+'" data-status="ACTIVATED">Aktiverad</button>';
      }
      return '<tr>'+
        '<td><b>'+esc(e.order_public_id)+'</b></td>'+
        '<td>'+esc(e.customer_name)+'<div class="small">'+esc(e.customer_email)+'</div></td>'+
        '<td>'+esc(e.member_name||'Ej verifierad')+'<div class="small">'+esc(e.member_status||'')+'</div></td>'+
        '<td>'+p4Status(e.status)+'</td>'+
        '<td>'+esc(e.partner_ref||'–')+'</td>'+
        '<td>'+action+'</td>'+
      '</tr>';
    }).join(''):'<tr><td colspan="6">Inga Nordic-ärenden ännu.</td></tr>';

    document.querySelectorAll('.p4-verify').forEach(btn=>btn.addEventListener('click',()=>verifyExistingMember(btn.dataset.order,btn.dataset.name)));
    document.querySelectorAll('.p4-transition').forEach(btn=>btn.addEventListener('click',()=>transitionBenefit(Number(btn.dataset.id),btn.dataset.status)));
  }

  async function verifyExistingMember(publicId,defaultName){
    clearP4Messages();
    const memberName=prompt('Medlemmens namn:',defaultName||'');
    if(!memberName) return;
    const membershipType=prompt('Medlemstyp: adult, youth eller senior','adult')||'adult';
    const externalRef=prompt('Eventuellt medlemsnummer/referens (kan lämnas tomt):','')||'';
    try{
      await post('admin_verify_existing_member',{public_id:publicId,member_name:memberName,membership_type:membershipType,external_member_ref:externalRef});
      showP4Message('Medlemskapet verifierades och Nordic-förmånen är nu eligible.');
      await load();
    }catch(error){showP4Error(error.message);}
  }

  async function transitionBenefit(id,status){
    clearP4Messages();
    let partnerRef='';
    if(status==='SENT_TO_PARTNER') partnerRef=prompt('Nordic-referens om sådan finns (valfritt):','')||'';
    try{
      await post('admin_benefit_status',{entitlement_id:id,status,partner_ref:partnerRef});
      showP4Message('Nordic-status uppdaterad till '+status+'.');
      await load();
    }catch(error){showP4Error(error.message);}
  }

  async function downloadNordicCsv(){
    if(!c.cfg.apiBase){alert('CSV hämtas från riktig staging.');return;}
    const response=await fetch(c.cfg.apiBase+'?action=admin_nordic_export',{headers:auth()});
    if(!response.ok){alert('Nordic-exporten kunde inte hämtas.');return;}
    const a=document.createElement('a');
    a.href=URL.createObjectURL(await response.blob());
    a.download='tranas-bois-nordic-wellness.csv';
    a.click();URL.revokeObjectURL(a.href);
  }

  function clearP4Messages(){$('p4Message').hidden=true;$('p4Error').hidden=true;}
  function showP4Message(message){$('p4Message').textContent=message;$('p4Message').hidden=false;}
  function showP4Error(message){$('p4Error').textContent=message;$('p4Error').hidden=false;}

  async function load() {
    const [ordersBody,catalogBody,batchesBody,p4Body,paymentBody,p7Body]=await Promise.all([
      api('admin_orders'),api('admin_catalog'),api('admin_batches'),api('admin_p4'),api('admin_payments'),api('admin_p7')
    ]);
    orders=ordersBody.orders||[];
    const products=catalogBody.products||[],stats=catalogBody.stats||{};
    $('ordersKpi').textContent=stats.bois_orders??orders.length;
    $('outboxKpi').textContent=stats.bois_email_outbox??0;
    renderWaiting(batchesBody.waiting||catalogBody.batch_waiting);
    renderBatches(batchesBody.batches||[]);
    renderOrders();
    renderCatalog(products);
    renderP4(p4Body);
    renderPayments(paymentBody);
    renderP7(p7Body.assortment||[]);
    $('login').hidden=true;$('dashboard').hidden=false;
  }

  async function post(action,payload={}) {
    return api(action,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
  }

  async function simulatePaid(publicId) {
    if(!confirm('Simulera verifierad mockbetalning för denna TESTORDER?')) return;
    clearMessages();
    try {
      const result=await post('admin_simulate_paid',{public_id:publicId});
      const effects=result.result?.result?.effects||{};
      const p4Text=(effects.p4?.members?.length||effects.p4?.benefits?.length)?' Medlems-/förmånsstatus uppdaterades också.':'';
      showMessage('Testordern markerades betald via signerad mockhändelse. '+((effects.p5?.auto_batches||[]).length?'En automatisk batch skapades.':'Ingen ny matchställsbatch skapades.')+p4Text);
      await load();
    } catch(error) { showError(error.message); }
  }

  async function createBatchNow() {
    if(!confirm('Skapa batch av alla betalda matchställ som väntar? I staging skickas inget externt mejl.')) return;
    clearMessages();
    try {
      const result=await post('admin_batch_now',{});
      if((result.batches||[]).length) showMessage('Batch skapad och leverantörsmejl lades i outbox. Ingen extern sändning sker i staging.');
      else showMessage('Det finns inga betalda matchställ som väntar på batch.');
      await load();
    } catch(error){showError(error.message);}
  }

  async function runWorker() {
    clearMessages();
    try {
      const result=await post('admin_run_worker',{});
      const created=(result.batches||[]).length;
      showMessage('Automatikkontroll klar. Nya batcher: '+created+'. Mailtransport: '+(result.mail?.transport||'disabled')+'.');
      await load();
    } catch(error){showError(error.message);}
  }

  async function downloadCsv(batchId) {
    if(!c.cfg.apiBase){alert('CSV hämtas från riktig staging.');return;}
    const response=await fetch(c.cfg.apiBase+'?action=admin_batch_csv&id='+encodeURIComponent(batchId),{headers:auth()});
    if(!response.ok){alert('CSV kunde inte hämtas.');return;}
    const a=document.createElement('a');
    a.href=URL.createObjectURL(await response.blob());
    a.download='tranas-bois-'+batchId.toLowerCase()+'.csv';
    a.click();URL.revokeObjectURL(a.href);
  }

  async function retryOutbox(id) {
    clearMessages();
    try { await post('admin_retry_outbox',{outbox_id:id});showMessage('Outbox-posten återställd för nytt försök.');await load(); }
    catch(error){showError(error.message);}
  }

  function clearMessages(){$('batchMessage').hidden=true;$('batchError').hidden=true;}
  function showMessage(message){$('batchMessage').textContent=message;$('batchMessage').hidden=false;}
  function showError(message){$('batchError').textContent=message;$('batchError').hidden=false;}

  $('loginForm').addEventListener('submit',async e=>{
    e.preventDefault();token=$('token').value.trim();sessionStorage.setItem('boisP3Admin',token);$('loginError').hidden=true;
    try{await load();}catch(err){$('loginError').textContent=err.message;$('loginError').hidden=false;}
  });
  $('logout').addEventListener('click',()=>{token='';sessionStorage.removeItem('boisP3Admin');$('dashboard').hidden=true;$('login').hidden=false;});
  $('batchNow').addEventListener('click',createBatchNow);
  $('runWorker').addEventListener('click',runWorker);
  $('nordicExport').addEventListener('click',downloadNordicCsv);

  if(!c.cfg.apiBase){load();} else if(token){load().catch(()=>{});}
})();
