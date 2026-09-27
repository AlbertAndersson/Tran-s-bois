(() => {
  'use strict';
  const c=window.BOIS_COMMERCE,$=id=>document.getElementById(id);
  let token=sessionStorage.getItem('boisP3Admin')||'';

  function auth(){return {Authorization:'Bearer '+token};}
  async function api(action){
    if(!c.cfg.apiBase) return demo(action);
    return c.api(action,{headers:auth()});
  }
  function demo(action){
    if(action==='admin_catalog') return Promise.resolve({products:[
      {name:'Medlemskap Tranås BoIS',category:'membership',fulfillment_type:'DIGITAL_MEMBERSHIP',is_public:true,is_orderable:true},
      {name:'Nordic Wellness gymkort',category:'member_benefit',fulfillment_type:'MEMBER_BENEFIT',is_public:true,is_orderable:true},
      {name:'Matchställ',category:'match_kit',fulfillment_type:'BATCH_SUPPLIER',is_public:true,is_orderable:true},
      {name:'BoIS 1941 Hoodie',category:'supporter',fulfillment_type:'DIRECT_SUPPLIER',is_public:false,is_orderable:false}
    ],stats:{bois_products:10,bois_orders:0,bois_memberships:0,bois_email_outbox:0}});
    return Promise.resolve({orders:[]});
  }
  function esc(v){return String(v??'').replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));}
  async function load(){
    const [ordersBody,catalogBody]=await Promise.all([api('admin_orders'),api('admin_catalog')]);
    const orders=ordersBody.orders||[],products=catalogBody.products||[],stats=catalogBody.stats||{};
    $('ordersKpi').textContent=stats.bois_orders??orders.length;$('productsKpi').textContent=stats.bois_products??products.length;$('membershipsKpi').textContent=stats.bois_memberships??0;$('outboxKpi').textContent=stats.bois_email_outbox??0;
    $('orders').innerHTML=orders.length?orders.map(o=>'<tr><td><b>'+esc(o.public_id)+'</b><div class="small">'+esc(o.created_at)+'</div></td><td>'+esc(o.customer_name)+'<div class="small">'+esc(o.customer_email)+'</div></td><td><div class="chips">'+(o.items||[]).map(i=>'<span class="chip">'+esc(i.sku)+'</span>').join('')+'</div></td><td>'+c.money(o.total_ore)+'</td><td>'+esc(o.payment_status)+'</td><td>'+esc(o.fulfillment_status)+'</td></tr>').join(''):'<tr><td colspan="6">Inga P3-order ännu.</td></tr>';
    $('catalog').innerHTML=products.map(p=>'<tr><td><b>'+esc(p.name)+'</b></td><td>'+esc(p.category)+'</td><td><span class="chip">'+esc(p.fulfillment_type)+'</span></td><td>'+(p.is_public?'Ja':'Nej')+'</td><td>'+(p.is_orderable?'Ja':'Nej')+'</td></tr>').join('');
    $('login').hidden=true;$('dashboard').hidden=false;
  }
  $('loginForm').addEventListener('submit',async e=>{e.preventDefault();token=$('token').value.trim();sessionStorage.setItem('boisP3Admin',token);$('loginError').hidden=true;try{await load();}catch(err){$('loginError').textContent=err.message;$('loginError').hidden=false;}});
  $('logout').addEventListener('click',()=>{token='';sessionStorage.removeItem('boisP3Admin');$('dashboard').hidden=true;$('login').hidden=false;});
  if(!c.cfg.apiBase){load();} else if(token){load().catch(()=>{});}
})();