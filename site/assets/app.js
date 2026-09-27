(() => {
  'use strict';

  const cfg = window.BOIS_CONFIG || { mode: 'demo', apiBase: null, environmentLabel: 'LIVE DEMO' };
  const catalog = window.BOIS_CATALOG || {};
  const $ = id => document.getElementById(id);
  const product = Array.isArray(catalog.products) ? catalog.products[0] : null;
  const period = catalog.orderPeriod || { id: 'draft', label: 'Beställningsperiod' };
  const form = $('orderForm');
  const btn = $('orderBtn');
  const namePrint = $('nameprint');
  const numberPrint = $('numberprint');
  const dialog = $('confirmationDialog');

  function money(ore) {
    return new Intl.NumberFormat('sv-SE', { style: 'currency', currency: 'SEK', maximumFractionDigits: 0 }).format((ore || 0) / 100);
  }

  function fillSelect(select, values, placeholder) {
    select.innerHTML = '<option value="">' + placeholder + '</option>' + values.map(value => '<option value="' + String(value).replace(/"/g, '&quot;') + '">' + value + '</option>').join('');
  }

  function initialiseCatalog() {
    if (!product) return;
    $('productTitle').textContent = product.label;
    $('productDescription').textContent = product.description;
    $('periodLabel').textContent = period.label;
    $('periodState').textContent = period.closesAt ? 'Beställ senast ' + period.closesAt : 'Produktionsutkast – slutdatum fastställs av BoIS.';

    const teams = (catalog.teams || []).filter(team => team.active).map(team => team.label);
    fillSelect($('team'), teams, 'Välj lag');
    fillSelect($('shirt'), product.components.shirt.sizes || [], 'Välj');
    fillSelect($('shorts'), product.components.shorts.sizes || [], 'Välj');

    $('shirtLineLabel').textContent = product.components.shirt.label;
    $('shortsLineLabel').textContent = product.components.shorts.label;
    $('shirtLinePrice').textContent = money(product.components.shirt.priceOre);
    $('shortsLinePrice').textContent = money(product.components.shorts.priceOre);
    $('nameLineLabel').textContent = product.personalization.name.label;
    $('numberLineLabel').textContent = product.personalization.number.label;
    $('nameLinePrice').textContent = money(product.personalization.name.priceOre);
    $('numberLinePrice').textContent = money(product.personalization.number.priceOre);
    $('namePrintLabel').textContent = product.personalization.name.label + ' på tröjan';
    $('numberPrintLabel').textContent = product.personalization.number.label;
    namePrint.checked = product.personalization.name.default !== false;
    numberPrint.checked = product.personalization.number.default !== false;
  }

  $('environmentLabel').textContent = cfg.environmentLabel || (cfg.apiBase ? 'P2 STAGING' : 'LIVE DEMO');
  if (cfg.apiBase) {
    $('modeNotice').classList.add('staging');
    $('modeNotice').innerHTML = '<b>P2 staging:</b> Testbeställningar sparas. Använd endast testuppgifter – ingen betalning sker.';
    btn.textContent = 'Skicka testbeställning →';
    $('paymentNote').textContent = 'Ordern sparas, men betalning är fortfarande avstängd.';
  }

  initialiseCatalog();

  function totalOre() {
    if (!product) return 0;
    return Number(product.components.shirt.priceOre || 0)
      + Number(product.components.shorts.priceOre || 0)
      + (namePrint.checked ? Number(product.personalization.name.priceOre || 0) : 0)
      + (numberPrint.checked ? Number(product.personalization.number.priceOre || 0) : 0);
  }

  function refreshPrice() {
    $('nameLine').style.display = namePrint.checked ? 'flex' : 'none';
    $('numberLine').style.display = numberPrint.checked ? 'flex' : 'none';
    $('total').textContent = money(totalOre());
  }

  function payload() {
    return {
      product_id: product ? product.id : 'match-kit-knatte',
      order_period_id: period.id || 'draft',
      team: $('team').value,
      shirt_size: $('shirt').value,
      shorts_size: $('shorts').value,
      player_name: $('player').value.trim(),
      number: $('number').value.trim(),
      name_print: namePrint.checked,
      number_print: numberPrint.checked,
      parent_name: $('parent').value.trim(),
      email: $('mail').value.trim(),
      phone: $('phone').value.trim(),
      consent: $('consent').checked,
      website: $('website').value,
      idempotency_key: crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + '-' + String(Math.random())
    };
  }

  function showError(message) {
    const el = $('formError');
    el.textContent = message;
    el.hidden = false;
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function clearError() {
    $('formError').hidden = true;
  }

  function showConfirmation(order) {
    $('confirmationTitle').textContent = cfg.apiBase ? 'Testbeställningen är registrerad' : 'Så kommer bekräftelsen att se ut';
    $('confirmationText').textContent = cfg.apiBase
      ? 'Ordern är sparad i P2-testmiljön och syns nu i ledarvyn.'
      : 'I den skarpa versionen registreras ordern här och blir direkt synlig för lagets ledare.';
    $('orderNumber').hidden = false;
    $('orderNumber').textContent = order.order_id || 'BOIS-DEMO-001';
    $('confirmationSmall').textContent = cfg.apiBase
      ? 'Ingen betalning har genomförts. Spara ordernumret eller öppna orderstatus.'
      : 'Detta är endast en demo. Ingenting har sparats.';
    const statusLink = $('orderStatusLink');
    if (cfg.apiBase && order.public_token && order.order_id) {
      statusLink.href = 'order.html?id=' + encodeURIComponent(order.order_id) + '&token=' + encodeURIComponent(order.public_token);
      statusLink.hidden = false;
    } else {
      statusLink.hidden = true;
    }
    dialog.showModal();
  }

  async function submitReal(data) {
    const response = await fetch(cfg.apiBase + '?action=orders', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(data)
    });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(body.error || 'Beställningen kunde inte sparas.');
    return body;
  }

  namePrint.addEventListener('change', refreshPrice);
  numberPrint.addEventListener('change', refreshPrice);
  refreshPrice();

  form.addEventListener('submit', async event => {
    event.preventDefault();
    clearError();
    if (!form.reportValidity()) return;

    const number = Number($('number').value.trim());
    if (!Number.isInteger(number) || number < 1 || number > 99) {
      return showError('Ange ett tröjnummer mellan 1 och 99.');
    }

    btn.disabled = true;
    const original = btn.textContent;
    btn.textContent = cfg.apiBase ? 'Sparar…' : 'Öppnar demo…';

    try {
      const result = cfg.apiBase
        ? await submitReal(payload())
        : { order_id: 'BOIS-DEMO-001', public_token: null };
      showConfirmation(result);
      if (cfg.apiBase) form.reset();
      initialiseCatalog();
      refreshPrice();
    } catch (error) {
      showError(error.message || 'Ett oväntat fel uppstod.');
    } finally {
      btn.disabled = false;
      btn.textContent = original;
    }
  });

  $('closeDialog').addEventListener('click', () => dialog.close());
})();