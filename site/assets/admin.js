(() => {
  'use strict';

  const cfg = window.BOIS_CONFIG || { apiBase: null, environmentLabel: 'DEMO' };
  const catalog = window.BOIS_CATALOG || {};
  const $ = id => document.getElementById(id);
  const statusNames = {
    received: 'Ny',
    checked: 'Kontrollerad',
    ready_for_supplier: 'Klar för leverantör',
    ordered: 'Beställd',
    cancelled: 'Avbruten'
  };

  const demoOrders = [
    {order_id:'BOIS-260927-A1B2C',product_id:'match-kit-knatte',product_label:'Matchställ Knatte',order_period_id:'2026-27-matchstall',player_name:'Albin Karlsson',parent_name:'Sara Karlsson',email:'sara@example.se',phone:'070-111 11 11',team:'P9',shirt_size:'140',shorts_size:'140',number:'10',name_print:true,total_ore:99800,status:'received',created_at:'2026-09-27T08:12:00+02:00'},
    {order_id:'BOIS-260927-D3E4F',product_id:'match-kit-knatte',product_label:'Matchställ Knatte',order_period_id:'2026-27-matchstall',player_name:'Hugo Andersson',parent_name:'Johan Andersson',email:'johan@example.se',phone:'070-222 22 22',team:'P9',shirt_size:'152',shorts_size:'152',number:'17',name_print:true,total_ore:99800,status:'checked',created_at:'2026-09-27T08:18:00+02:00'},
    {order_id:'BOIS-260927-G5H6J',product_id:'match-kit-knatte',product_label:'Matchställ Knatte',order_period_id:'2026-27-matchstall',player_name:'Leo Svensson',parent_name:'Maria Svensson',email:'maria@example.se',phone:'070-333 33 33',team:'P9',shirt_size:'140',shorts_size:'140',number:'8',name_print:true,total_ore:99800,status:'ready_for_supplier',created_at:'2026-09-27T08:24:00+02:00'},
    {order_id:'BOIS-260927-K7L8M',product_id:'match-kit-knatte',product_label:'Matchställ Knatte',order_period_id:'2026-27-matchstall',player_name:'Viggo Johansson',parent_name:'Anna Johansson',email:'anna@example.se',phone:'070-444 44 44',team:'F9',shirt_size:'128',shorts_size:'140',number:'22',name_print:true,total_ore:99800,status:'ordered',created_at:'2026-09-27T08:31:00+02:00'},
    {order_id:'BOIS-260927-N9P0Q',product_id:'match-kit-knatte',product_label:'Matchställ Knatte',order_period_id:'2026-27-matchstall',player_name:'Noah Larsson',parent_name:'Erik Larsson',email:'erik@example.se',phone:'070-555 55 55',team:'P13',shirt_size:'152',shorts_size:'152',number:'5',name_print:false,total_ore:89800,status:'received',created_at:'2026-09-27T08:36:00+02:00'}
  ];

  let token = sessionStorage.getItem('boisAdminToken') || '';
  let orders = [];

  const money = ore => new Intl.NumberFormat('sv-SE', {style:'currency',currency:'SEK',maximumFractionDigits:0}).format((ore || 0) / 100);
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));

  function authHeaders(extra) {
    const headers = Object.assign({}, extra || {});
    if (token) headers.Authorization = 'Bearer ' + token;
    return headers;
  }

  async function api(action, options) {
    const opt = options || {};
    const response = await fetch(cfg.apiBase + '?action=' + encodeURIComponent(action) + (opt.query || ''), {
      method: opt.method || 'GET',
      headers: Object.assign({Accept:'application/json'}, authHeaders(opt.headers)),
      body: opt.body
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

  function showLogin(message) {
    $('loginPanel').hidden = false;
    $('dashboard').hidden = true;
    $('loginError').hidden = !message;
    $('loginError').textContent = message || '';
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

    return orders.filter(order =>
      (!team || order.team === team) &&
      (!status || order.status === status) &&
      (!q || [
        order.order_id,
        order.player_name,
        order.parent_name,
        order.number,
        order.email
      ].join(' ').toLowerCase().includes(q))
    );
  }

  function statusControl(order) {
    if (!cfg.apiBase) {
      return '<span class="status-' + esc(order.status) + '">' + esc(statusNames[order.status] || order.status) + '</span>';
    }

    const options = Object.entries(statusNames).map(entry => {
      const value = entry[0];
      const label = entry[1];
      return '<option value="' + value + '" ' + (order.status === value ? 'selected' : '') + '>' + label + '</option>';
    }).join('');

    return '<select class="status-select" data-order="' + esc(order.order_id) + '">' + options + '</select>';
  }

  function populateTeams() {
    const current = $('filterTeam').value;
    const catalogTeams = (catalog.teams || []).filter(team => team.active).map(team => team.label);
    const orderTeams = orders.map(order => order.team).filter(Boolean);
    const teams = Array.from(new Set(catalogTeams.concat(orderTeams))).sort((a,b) => a.localeCompare(b,'sv'));

    $('filterTeam').innerHTML = '<option value="">Alla lag</option>' + teams.map(team => '<option>' + esc(team) + '</option>').join('');
    if (teams.includes(current)) $('filterTeam').value = current;
  }

  function summaryRows(rows) {
    const groups = new Map();

    rows.filter(order => order.status !== 'cancelled').forEach(order => {
      const key = [order.team, order.product_label || 'Matchställ', order.shirt_size, order.shorts_size].join('|');
      if (!groups.has(key)) {
        groups.set(key, {
          team: order.team,
          product: order.product_label || 'Matchställ',
          shirt: order.shirt_size,
          shorts: order.shorts_size,
          count: 0
        });
      }
      groups.get(key).count += 1;
    });

    return Array.from(groups.values()).sort((a,b) =>
      a.team.localeCompare(b.team,'sv') ||
      String(a.shirt).localeCompare(String(b.shirt),'sv') ||
      String(a.shorts).localeCompare(String(b.shorts),'sv')
    );
  }

  function renderSummary(rows) {
    const summary = summaryRows(rows);
    $('summaryBody').innerHTML = summary.length
      ? summary.map(row =>
          '<tr><td>' + esc(row.team) + '</td><td>' + esc(row.product) + '</td><td>' + esc(row.shirt) + '</td><td>' + esc(row.shorts) + '</td><td><b>' + row.count + '</b></td></tr>'
        ).join('')
      : '<tr><td colspan="5" class="p2-empty-summary">Inget att sammanställa.</td></tr>';
  }

  function render() {
    const rows = filteredOrders();

    $('ordersBody').innerHTML = rows.map(order =>
      '<tr>' +
      '<td><b>' + esc(order.order_id) + '</b><div class="contact-small">' + esc((order.created_at || '').replace('T',' ').slice(0,16)) + '</div></td>' +
      '<td><b>' + esc(order.player_name) + '</b>' + (order.name_print ? '<div class="contact-small">Namntryck</div>' : '') + '</td>' +
      '<td>' + esc(order.team) + '</td>' +
      '<td>' + esc(order.shirt_size) + '</td>' +
      '<td>' + esc(order.shorts_size) + '</td>' +
      '<td>' + esc(order.number) + '</td>' +
      '<td>' + esc(order.parent_name) + '<div class="contact-small">' + esc(order.email) + '<br>' + esc(order.phone) + '</div></td>' +
      '<td><b>' + money(order.total_ore) + '</b></td>' +
      '<td>' + statusControl(order) + '</td>' +
      '</tr>'
    ).join('');

    $('emptyState').hidden = rows.length !== 0;
    $('kpiOrders').textContent = orders.length;
    $('kpiNew').textContent = orders.filter(order => order.status === 'received').length;
    $('kpiReady').textContent = orders.filter(order => order.status === 'ready_for_supplier').length;
    $('kpiValue').textContent = money(orders.filter(order => order.status !== 'cancelled').reduce((sum, order) => sum + (order.total_ore || 0), 0));
    $('tableFoot').textContent = cfg.apiBase
      ? rows.length + ' av ' + orders.length + ' testbeställningar visas.'
      : 'Demodata – inga riktiga beställningar visas på GitHub Pages.';

    renderSummary(rows);
    document.querySelectorAll('select[data-order]').forEach(select => select.addEventListener('change', updateStatus));
  }

  async function updateStatus(event) {
    const select = event.target;
    const id = select.dataset.order;
    const row = orders.find(order => order.order_id === id);
    const previous = row ? row.status : 'received';

    select.disabled = true;
    try {
      const result = await api('admin_order', {
        method: 'PATCH',
        query: '&id=' + encodeURIComponent(id),
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({status:select.value})
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

  function downloadDemoCsv(summaryOnly) {
    let rows;

    if (summaryOnly) {
      rows = [['Lag','Produkt','Tröja','Byxa','Antal']].concat(
        summaryRows(filteredOrders()).map(row => [row.team,row.product,row.shirt,row.shorts,row.count])
      );
    } else {
      rows = [['Order','Spelare','Lag','Tröja','Byxa','Nummer','Förälder','E-post','Telefon','Status']].concat(
        filteredOrders().map(order => [
          order.order_id,order.player_name,order.team,order.shirt_size,order.shorts_size,order.number,
          order.parent_name,order.email,order.phone,statusNames[order.status] || order.status
        ])
      );
    }

    const csv = rows.map(row => row.map(value => '"' + String(value ?? '').replace(/"/g,'""') + '"').join(';')).join('\n');
    const link = document.createElement('a');
    link.href = URL.createObjectURL(new Blob(['\ufeff' + csv], {type:'text/csv;charset=utf-8'}));
    link.download = summaryOnly ? 'bois-sammanstallning-demo.csv' : 'bois-detaljer-demo.csv';
    link.click();
    URL.revokeObjectURL(link.href);
  }

  async function exportCsv(summaryOnly) {
    if (!cfg.apiBase) return downloadDemoCsv(summaryOnly);

    const action = summaryOnly ? 'admin_summary_export' : 'admin_export';
    const response = await fetch(cfg.apiBase + '?action=' + action, {headers:authHeaders()});
    if (response.status === 401) return showLogin('Adminnyckeln är fel eller har gått ut.');
    if (!response.ok) return alert('Exporten misslyckades.');

    const link = document.createElement('a');
    link.href = URL.createObjectURL(await response.blob());
    link.download = summaryOnly ? 'tranas-bois-sammanstallning.csv' : 'tranas-bois-bestallningar.csv';
    link.click();
    URL.revokeObjectURL(link.href);
  }

  $('environmentLabel').textContent = cfg.environmentLabel || (cfg.apiBase ? 'P2 STAGING' : 'DEMO');
  $('adminPeriod').textContent = (catalog.orderPeriod && catalog.orderPeriod.label) ? catalog.orderPeriod.label : 'Beställningsperiod';

  $('loginForm').addEventListener('submit', async event => {
    event.preventDefault();
    token = $('adminToken').value.trim();
    sessionStorage.setItem('boisAdminToken', token);
    try { await loadOrders(); } catch (_) {}
  });

  $('logoutBtn').addEventListener('click', () => {
    token = '';
    sessionStorage.removeItem('boisAdminToken');
    $('adminToken').value = '';
    showLogin();
  });

  $('csvBtn').addEventListener('click', () => exportCsv(false));
  $('summaryCsvBtn').addEventListener('click', () => exportCsv(true));
  $('refreshBtn').addEventListener('click', () => loadOrders().catch(error => alert(error.message)));
  ['filterTeam','filterStatus'].forEach(id => $(id).addEventListener('change', render));
  $('filterSearch').addEventListener('input', render);

  if (cfg.apiBase) {
    $('dashboardSubtitle').textContent = 'Riktiga testordrar från P2-staging. Ingen betalning är aktiverad.';
    token ? loadOrders().catch(() => {}) : showLogin();
  } else {
    loadOrders();
  }
})();