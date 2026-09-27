(() => {
  'use strict';
  const cfg = window.BOIS_CONFIG || { apiBase: null, environmentLabel: 'DEMO' };
  const $ = id => document.getElementById(id);
  const statusNames = {
    received: 'Ny',
    checked: 'Kontrollerad',
    ready_for_supplier: 'Klar för leverantör',
    ordered: 'Beställd',
    cancelled: 'Avbruten'
  };
  const demoOrders = [
    {order_id:'BOIS-260927-A1B2C',player_name:'Albin Karlsson',parent_name:'Sara Karlsson',email:'sara@example.se',phone:'070-111 11 11',team:'P9',shirt_size:'140',shorts_size:'140',number:'10',name_print:true,total_ore:99800,status:'received',created_at:'2026-09-27T08:12:00+02:00'},
    {order_id:'BOIS-260927-D3E4F',player_name:'Hugo Andersson',parent_name:'Johan Andersson',email:'johan@example.se',phone:'070-222 22 22',team:'P9',shirt_size:'152',shorts_size:'152',number:'17',name_print:true,total_ore:99800,status:'checked',created_at:'2026-09-27T08:18:00+02:00'},
    {order_id:'BOIS-260927-G5H6J',player_name:'Leo Svensson',parent_name:'Maria Svensson',email:'maria@example.se',phone:'070-333 33 33',team:'P9',shirt_size:'140',shorts_size:'140',number:'8',name_print:true,total_ore:99800,status:'ready_for_supplier',created_at:'2026-09-27T08:24:00+02:00'},
    {order_id:'BOIS-260927-K7L8M',player_name:'Viggo Johansson',parent_name:'Anna Johansson',email:'anna@example.se',phone:'070-444 44 44',team:'F9',shirt_size:'128',shorts_size:'140',number:'22',name_print:true,total_ore:99800,status:'ordered',created_at:'2026-09-27T08:31:00+02:00'},
    {order_id:'BOIS-260927-N9P0Q',player_name:'Noah Larsson',parent_name:'Erik Larsson',email:'erik@example.se',phone:'070-555 55 55',team:'P13',shirt_size:'152',shorts_size:'152',number:'5',name_print:false,total_ore:89800,status:'received',created_at:'2026-09-27T08:36:00+02:00'}
  ];
  let token = sessionStorage.getItem('boisAdminToken') || '';
  let orders = [];
  const money = ore => new Intl.NumberFormat('sv-SE', {style:'currency',currency:'SEK',maximumFractionDigits:0}).format((ore || 0) / 100);
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));

  function authHeaders(extra = {}) {
    return token ? {...extra, Authorization: `Bearer ${token}`} : extra;
  }
  async function api(action, options = {}) {
    const query = options.query || '';
    const response = await fetch(`${cfg.apiBase}?action=${encodeURIComponent(action)}${query}`, {
      ...options,
      headers: {Accept:'application/json', ...authHeaders(options.headers || {})}
    });
    if (response.status === 401) {
      token = '';
      sessionStorage.removeItem('boisAdminToken');
      showLogin('Adminnyckeln är fel eller har gått ut.');
      throw new Error('Ej behörig');
    }
    const body = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(body.error || 'API-fel');
    return body;
  }
  function showLogin(message = '') {
    $('loginPanel').hidden = false;
    $('dashboard').hidden = true;
    $('loginError').hidden = !message;
    $('loginError').textContent = message;
  }
  function showDashboard() {
    $('loginPanel').hidden = true;
    $('dashboard').hidden = false;
    $('logoutBtn').hidden = !cfg.apiBase;
  }
  function filteredOrders() {
    const team = $('filterTeam').value;
    const status = $('filterStatus').value;
    const q = $('filterSearch').value.trim().toLowerCase();
    return orders.filter(o =>
      (!team || o.team === team) &&
      (!status || o.status === status) &&
      (!q || [o.order_id,o.player_name,o.parent_name,o.number,o.email].join(' ').toLowerCase().includes(q))
    );
  }
  function statusControl(order) {
    if (!cfg.apiBase) return `<span class="status-${esc(order.status)}">${esc(statusNames[order.status] || order.status)}</span>`;
    const options = Object.entries(statusNames).map(([value,label]) =>
      `<option value="${value}" ${order.status === value ? 'selected' : ''}>${label}</option>`
    ).join('');
    return `<select class="status-select" data-order="${esc(order.order_id)}">${options}</select>`;
  }
  function populateTeams() {
    const current = $('filterTeam').value;
    const teams = [...new Set(orders.map(o => o.team).filter(Boolean))].sort((a,b) => a.localeCompare(b,'sv'));
    $('filterTeam').innerHTML = '<option value="">Alla lag</option>' + teams.map(t => `<option>${esc(t)}</option>`).join('');
    if (teams.includes(current)) $('filterTeam').value = current;
  }
  function render() {
    const rows = filteredOrders();
    $('ordersBody').innerHTML = rows.map(o => `<tr>
      <td><b>${esc(o.order_id)}</b><div class="contact-small">${esc((o.created_at || '').replace('T',' ').slice(0,16))}</div></td>
      <td><b>${esc(o.player_name)}</b>${o.name_print ? '<div class="contact-small">Namntryck</div>' : ''}</td>
      <td>${esc(o.team)}</td><td>${esc(o.shirt_size)}</td><td>${esc(o.shorts_size)}</td><td>${esc(o.number)}</td>
      <td>${esc(o.parent_name)}<div class="contact-small">${esc(o.email)}<br>${esc(o.phone)}</div></td>
      <td><b>${money(o.total_ore)}</b></td><td>${statusControl(o)}</td></tr>`).join('');
    $('emptyState').hidden = rows.length !== 0;
    $('kpiOrders').textContent = orders.length;
    $('kpiNew').textContent = orders.filter(o => o.status === 'received').length;
    $('kpiReady').textContent = orders.filter(o => o.status === 'ready_for_supplier').length;
    $('kpiValue').textContent = money(orders.filter(o => o.status !== 'cancelled').reduce((sum,o) => sum + (o.total_ore || 0), 0));
    $('tableFoot').textContent = cfg.apiBase ? `${rows.length} av ${orders.length} testbeställningar visas.` : 'Demodata – inga riktiga beställningar visas på GitHub Pages.';
    document.querySelectorAll('select[data-order]').forEach(el => el.addEventListener('change', updateStatus));
  }
  async function updateStatus(event) {
    const select = event.target;
    const id = select.dataset.order;
    const row = orders.find(o => o.order_id === id);
    const previous = row ? row.status : 'received';
    select.disabled = true;
    try {
      const result = await api('admin_order', {
        method:'PATCH', query:`&id=${encodeURIComponent(id)}`,
        headers:{'Content-Type':'application/json'}, body:JSON.stringify({status:select.value})
      });
      if (row) Object.assign(row, result.order);
      render();
    } catch (error) {
      select.value = previous;
      alert(error.message);
    } finally {
      select.disabled = false;
    }
  }
  async function loadOrders() {
    orders = cfg.apiBase ? (await api('admin_orders')).orders : demoOrders.slice();
    populateTeams();
    render();
    showDashboard();
  }
  async function exportCsv() {
    if (!cfg.apiBase) {
      const rows = [['Order','Spelare','Lag','Tröja','Byxa','Nummer','Förälder','E-post','Telefon','Status'],
        ...filteredOrders().map(o => [o.order_id,o.player_name,o.team,o.shirt_size,o.shorts_size,o.number,o.parent_name,o.email,o.phone,statusNames[o.status] || o.status])];
      const csv = rows.map(r => r.map(v => `"${String(v ?? '').replace(/"/g,'""')}"`).join(';')).join('\n');
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob(['\ufeff' + csv], {type:'text/csv;charset=utf-8'}));
      a.download = 'bois-demo.csv'; a.click(); URL.revokeObjectURL(a.href); return;
    }
    const response = await fetch(`${cfg.apiBase}?action=admin_export`, {headers:authHeaders()});
    if (response.status === 401) return showLogin('Adminnyckeln är fel eller har gått ut.');
    if (!response.ok) return alert('Exporten misslyckades.');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(await response.blob());
    a.download = 'tranas-bois-bestallningar.csv'; a.click(); URL.revokeObjectURL(a.href);
  }

  $('environmentLabel').textContent = cfg.environmentLabel || (cfg.apiBase ? 'P1 STAGING' : 'DEMO');
  $('loginForm').addEventListener('submit', async event => {
    event.preventDefault(); token = $('adminToken').value.trim(); sessionStorage.setItem('boisAdminToken', token);
    try { await loadOrders(); } catch (_) {}
  });
  $('logoutBtn').addEventListener('click', () => { token=''; sessionStorage.removeItem('boisAdminToken'); $('adminToken').value=''; showLogin(); });
  $('csvBtn').addEventListener('click', exportCsv);
  $('refreshBtn').addEventListener('click', () => loadOrders().catch(error => alert(error.message)));
  ['filterTeam','filterStatus'].forEach(id => $(id).addEventListener('change', render));
  $('filterSearch').addEventListener('input', render);
  if (cfg.apiBase) {
    $('dashboardSubtitle').textContent = 'Riktiga testordrar från P1-staging. Ingen betalning är aktiverad.';
    token ? loadOrders().catch(() => {}) : showLogin();
  } else {
    loadOrders();
  }
})();
