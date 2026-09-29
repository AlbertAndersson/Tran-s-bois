(() => {
  'use strict';
  const c=window.BOIS_COMMERCE, $=id=>document.getElementById(id);
  let token=sessionStorage.getItem('boisP3Admin')||'';
  const esc=value=>String(value??'').replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
  async function load(){
    if(!c.cfg.apiBase) throw new Error('Preview kräver inloggad staging.');
    const body=await c.api('admin_p7',{headers:{'X-Bois-Admin-Token':token}});
    const groups=new Map();
    for(const p of body.assortment||[]){
      const name=p.preview_group||'Övrigt';
      if(!groups.has(name)) groups.set(name,[]);
      groups.get(name).push(p);
    }
    $('previewGroups').innerHTML=[...groups].map(([group,products])=>
      '<section><h2>'+esc(group)+'</h2><div class="preview-grid">'+products.map(p=>
        '<article class="card" style="margin:12px 0;padding:20px">'+
          '<div role="img" aria-label="Produktbild saknas" style="background:#e8edf1;min-height:180px;display:grid;place-items:center;border-radius:12px;color:#526071">Bild inväntas</div>'+
          '<p class="tag">'+esc(p.verification_status)+' · EJ KÖPBAR</p><h3>'+esc(p.name)+'</h3><p>'+esc(p.preview_copy||'Produkttext inväntas.')+'</p>'+
          '<p><b>'+(p.sale_price_ore==null?'Pris TBD':c.money(p.sale_price_ore)+' (förslag)')+'</b></p>'+
          '<p>Varianter: '+esc((p.variants||[]).map(v=>(v.size||'')+' / '+(v.color||'')).join(', ')||'TBD')+'</p>'+
          '<p class="small">Blockerare: '+esc((p.blockers||[]).join('; '))+'</p>'+
        '</article>').join('')+'</div></section>'
    ).join('')||'<p>Inget P7-underlag finns ännu.</p>';
    $('previewLogin').hidden=true;$('previewContent').hidden=false;$('previewError').hidden=true;
  }
  $('previewLogin').addEventListener('submit',event=>{
    event.preventDefault();token=$('previewToken').value;
    load().then(()=>sessionStorage.setItem('boisP3Admin',token)).catch(error=>{
      $('previewError').textContent=error.message;$('previewError').hidden=false;
    });
  });
  if(token) load().catch(()=>{$('previewLogin').hidden=false;});
})();
