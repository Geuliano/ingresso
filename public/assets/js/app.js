// Pulse Festival — pagina inicial / checkout inicial.
// Refatorado para usar DOM API (sem innerHTML com dados do servidor).

function makeSVG(svgString) {
  const div = document.createElement('div');
  div.innerHTML = svgString.trim();
  return div.firstChild;
}

const EMPTY_CART_SVG = `<svg viewBox="0 0 120 102" width="120" height="102" fill="none" xmlns="http://www.w3.org/2000/svg">
  <ellipse cx="60" cy="66" rx="44" ry="24" fill="rgba(90,255,214,0.04)"/>
  <g transform="rotate(-18 17 52)" opacity="0.75">
    <rect x="6" y="46" width="20" height="13" rx="2.5" fill="rgba(157,122,255,0.07)" stroke="rgba(157,122,255,0.32)" stroke-width="1"/>
    <line x1="9.5" y1="52.5" x2="23" y2="52.5" stroke="rgba(157,122,255,0.28)" stroke-width="0.9" stroke-dasharray="2.5 2"/>
  </g>
  <g transform="rotate(14 103 43)" opacity="0.75">
    <rect x="94" y="36" width="20" height="13" rx="2.5" fill="rgba(90,255,214,0.06)" stroke="rgba(90,255,214,0.28)" stroke-width="1"/>
    <line x1="97.5" y1="42.5" x2="111" y2="42.5" stroke="rgba(90,255,214,0.22)" stroke-width="0.9" stroke-dasharray="2.5 2"/>
  </g>
  <path d="M28 46 L30 86 Q30 90 35 90 L85 90 Q90 90 90 86 L92 46 Z" fill="rgba(13,14,28,0.65)" stroke="rgba(90,255,214,0.42)" stroke-width="1.5" stroke-dasharray="5.5 2.5"/>
  <path d="M41 46 Q41 30 60 30 Q79 30 79 46" fill="none" stroke="rgba(90,255,214,0.58)" stroke-width="2" stroke-linecap="round"/>
  <circle cx="41" cy="46" r="2.8" fill="rgba(90,255,214,0.22)"/>
  <circle cx="79" cy="46" r="2.8" fill="rgba(90,255,214,0.22)"/>
  <circle cx="60" cy="68" r="14" fill="rgba(90,255,214,0.04)" stroke="rgba(90,255,214,0.18)" stroke-width="1" stroke-dasharray="3.5 2.5"/>
  <line x1="55.5" y1="68" x2="64.5" y2="68" stroke="rgba(90,255,214,0.38)" stroke-width="1.8" stroke-linecap="round"/>
  <line x1="60" y1="63.5" x2="60" y2="72.5" stroke="rgba(90,255,214,0.38)" stroke-width="1.8" stroke-linecap="round"/>
  <circle cx="19" cy="24" r="2.2" fill="rgba(90,255,214,0.48)"/>
  <circle cx="101" cy="66" r="1.6" fill="rgba(157,122,255,0.55)"/>
  <circle cx="11" cy="68" r="1.3" fill="rgba(157,122,255,0.38)"/>
  <circle cx="105" cy="26" r="2" fill="rgba(90,255,214,0.32)"/>
  <g opacity="0.55" transform="translate(92 14)">
    <path d="M5 0L6.3 4H10.5L7.1 6.5L8.4 10.5L5 8L1.6 10.5L2.9 6.5L-0.5 4H3.7Z" fill="rgba(90,255,214,0.6)" stroke="rgba(90,255,214,0.7)" stroke-width="0.4"/>
  </g>
  <g opacity="0.4" transform="translate(14 32)">
    <path d="M4 0L5 3H8L5.5 5L6.5 8L4 6.2L1.5 8L2.5 5L0 3H3Z" fill="rgba(157,122,255,0.7)" stroke="rgba(157,122,255,0.8)" stroke-width="0.4"/>
  </g>
</svg>`;

const DEFAULT_BATCHES = [];

let batches = [...DEFAULT_BATCHES];
const BATCHES_API_URL = 'api/ingressos.php';
const STORAGE_KEY_SELECTION = 'ingresso:selection';

const Utils = window.IngressoUtils;
const { formatBRL, el, basePath } = Utils;

const selection = {
  tickets: {},
  creditValue: 0,
};

const parseDate = (value) => {
  if (!value) return null;
  const d = new Date(value);
  return Number.isNaN(d.getTime()) ? null : d;
};

const getAllTickets = () => batches.flatMap((batch) => batch.tickets.map((t) => ({ ...t, batch })));

const findTicketById = (id) => getAllTickets().find((t) => t.id === id);

const pruneSelectionToExistingTickets = () => {
  const validIds = new Set(getAllTickets().map((t) => t.id));
  Object.keys(selection.tickets).forEach((id) => {
    if (!validIds.has(id)) {
      delete selection.tickets[id];
    }
  });
};

const ticketStatus = (ticket) => {
  const now = new Date();
  const start = parseDate(ticket.batch?.start_at);
  const end = parseDate(ticket.batch?.end_at);
  if (end && now > end) return 'esgotado';
  if (start && now < start) return 'em_breve';
  if ((ticket.available || 0) <= 0 || (ticket.progress || 0) >= 100) return 'esgotado';
  return 'ativo';
};

const pruneCartStorage = (ticketIds = []) => {
  if (!Array.isArray(ticketIds) || ticketIds.length === 0) return;
  try {
    const raw = localStorage.getItem('ingresso:cart');
    if (!raw) return;
    const parsed = JSON.parse(raw) || {};
    const items = Array.isArray(parsed.items) ? parsed.items.filter((item) => !ticketIds.includes(item.id)) : [];
    const attendees = parsed.attendees && typeof parsed.attendees === 'object' ? parsed.attendees : {};
    ticketIds.forEach((id) => {
      delete attendees[id];
    });
    localStorage.setItem('ingresso:cart', JSON.stringify({ ...parsed, items, attendees }));
  } catch (err) {
    console.warn('Nao foi possivel limpar titulares do storage', err);
  }
};

const hasSelectedItems = () => {
  const ticketCount = Object.values(selection.tickets || {}).reduce((total, qty) => total + (Number(qty) || 0), 0);
  const creditCount = selection.creditValue > 0 ? 1 : 0;
  return ticketCount + creditCount > 0;
};

const ticketTotalWithFees = (ticket) => Number(ticket.basePrice || 0) + Number(ticket.fee || 0);

function safeLoadSelection() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY_SELECTION);
    if (!raw) return;
    const parsed = JSON.parse(raw);
    if (parsed && typeof parsed === 'object') {
      selection.tickets = parsed.tickets || {};
      selection.creditValue = Number(parsed.creditValue) || 0;
    }
  } catch (err) {
    console.warn('Nao foi possivel carregar selecao do storage', err);
  }
}

function persistSelection() {
  try {
    localStorage.setItem(STORAGE_KEY_SELECTION, JSON.stringify(selection));
  } catch (err) {
    console.warn('Nao foi possivel salvar selecao no storage', err);
  }
}

function buildTicketCard(ticket) {
  const count = selection.tickets[ticket.id] || 0;
  const status = ticketStatus(ticket);
  const isSoldOut = status === 'esgotado';
  const isSoon = status === 'em_breve';
  const progress = Math.min(100, Math.max(0, ticket.progress || 0));
  const totalPrice = ticketTotalWithFees(ticket);
  const highlight = ticket.show_highlight && ticket.highlight ? ticket.highlight : null;

  const statusBadgeClass = isSoldOut ? 'badge badge--danger' : isSoon ? 'badge badge--warning' : 'badge badge--success';
  const statusBadgeText = isSoldOut ? 'Esgotado' : isSoon ? 'Em breve' : 'Disponível';

  const meta = el('div', { className: 'ticket__meta' }, [
    el('span', { className: statusBadgeClass }, statusBadgeText),
    highlight ? el('span', { className: 'badge badge--highlight' }, highlight) : null,
  ]);

  const fee = Number(ticket.fee || 0);
  const available = Number(ticket.available || 0);
  const isLowStock = !isSoldOut && !isSoon && progress >= 80 && available > 0;

  const progressRow = el('div', { className: 'progress-row' }, [
    el('div', { className: 'progress' }, [
      el('div', { className: 'progress__bar', style: `width: ${progress}%` }),
    ]),
    isLowStock
      ? el('small', { className: 'progress__label progress__label--urgent' }, `${available} restantes`)
      : el('small', { className: 'progress__label' }, `${progress}%`),
  ]);

  const info = el('div', { className: 'ticket__info' }, [
    meta,
    el('h3', { className: 'ticket__name' }, ticket.name),
    progressRow,
  ]);

  const counter = el('div', { className: 'counter' + (isSoldOut || isSoon ? ' is-disabled' : '') }, [
    el('button', { 'aria-label': 'Remover', 'data-action': 'dec', 'data-id': ticket.id }, '-'),
    el('span', {}, String(count)),
    el('button', { 'aria-label': 'Adicionar', 'data-action': 'inc', 'data-id': ticket.id }, '+'),
  ]);

  const price = el('div', { className: 'price' }, [
    el('strong', {}, totalPrice ? formatBRL(totalPrice) : 'Em breve'),
    fee > 0 ? el('small', { className: 'price__fee' }, `+ ${formatBRL(fee)} taxa`) : null,
    counter,
  ]);

  return el('div', { className: 'ticket' }, [info, price]);
}

function renderTickets() {
  const container = document.getElementById('tickets');
  container.replaceChildren();

  batches.forEach((batch) => {
    const batchTickets = batch.tickets.map((t) => ({ ...t, batch }));
    if (!batchTickets.length) return;

    const batchSection = el('div', { className: 'ticket-batch' }, [
      el('h2', {}, batch.name),
      ...batchTickets.map(buildTicketCard),
    ]);

    container.appendChild(batchSection);
  });

  if (!container.children.length) {
    const maintenanceSVG = `<svg viewBox="0 0 120 102" width="120" height="102" fill="none" xmlns="http://www.w3.org/2000/svg">
      <ellipse cx="60" cy="66" rx="44" ry="24" fill="rgba(157,122,255,0.04)"/>
      <rect x="28" y="36" width="64" height="46" rx="5" fill="rgba(13,14,28,0.65)" stroke="rgba(157,122,255,0.38)" stroke-width="1.5" stroke-dasharray="5.5 2.5"/>
      <line x1="28" y1="50" x2="92" y2="50" stroke="rgba(157,122,255,0.25)" stroke-width="1" stroke-dasharray="3 2"/>
      <circle cx="38" cy="43" r="3" fill="rgba(157,122,255,0.2)" stroke="rgba(157,122,255,0.4)" stroke-width="1"/>
      <circle cx="48" cy="43" r="3" fill="rgba(157,122,255,0.12)" stroke="rgba(157,122,255,0.3)" stroke-width="1"/>
      <circle cx="58" cy="43" r="3" fill="rgba(157,122,255,0.08)" stroke="rgba(157,122,255,0.2)" stroke-width="1"/>
      <line x1="38" y1="62" x2="72" y2="62" stroke="rgba(157,122,255,0.3)" stroke-width="1.5" stroke-linecap="round"/>
      <line x1="38" y1="70" x2="60" y2="70" stroke="rgba(157,122,255,0.2)" stroke-width="1.5" stroke-linecap="round"/>
      <g transform="translate(72 57)">
        <circle cx="9" cy="9" r="9" fill="rgba(240,192,96,0.1)" stroke="rgba(240,192,96,0.5)" stroke-width="1.5"/>
        <line x1="9" y1="5" x2="9" y2="10" stroke="rgba(240,192,96,0.8)" stroke-width="1.8" stroke-linecap="round"/>
        <circle cx="9" cy="13" r="1.2" fill="rgba(240,192,96,0.8)"/>
      </g>
      <circle cx="19" cy="24" r="2.2" fill="rgba(157,122,255,0.45)"/>
      <circle cx="105" cy="26" r="2" fill="rgba(90,255,214,0.32)"/>
      <circle cx="101" cy="66" r="1.6" fill="rgba(157,122,255,0.45)"/>
    </svg>`;
    const maintenanceDiv = el('div', { className: 'empty-cart' }, [
      el('p', { className: 'empty-cart__title' }, 'Em manutenção'),
      el('p', { className: 'empty-cart__sub' }, 'Ingressos indisponíveis no momento. Volte em breve.'),
    ]);
    maintenanceDiv.insertBefore(makeSVG(maintenanceSVG), maintenanceDiv.firstChild);
    container.appendChild(maintenanceDiv);
  }
}

function renderCredits() {
  const quick = document.getElementById('credit-quick');
  quick.replaceChildren();
  const presetValues = [0, 50, 100, 200, 300, 400, 500];
  presetValues.forEach((value) => {
    const chip = el('button', {
      type: 'button',
      className: 'chip ' + (selection.creditValue === value ? 'is-active' : ''),
      dataset: { value: String(value) },
      onClick: () => {
        selection.creditValue = value;
        syncCreditControls();
        updateSummary();
      },
    }, formatBRL(value));
    quick.appendChild(chip);
  });
  syncCreditControls();
}

function attachTicketHandlers() {
  document.getElementById('tickets').addEventListener('click', (e) => {
    const action = e.target.getAttribute('data-action');
    const id = e.target.getAttribute('data-id');
    if (!action || !id) return;
    const ticket = findTicketById(id);
    if (!ticket) return;
    const status = ticketStatus(ticket);
    if (status !== 'ativo') return;
    const current = selection.tickets[id] || 0;
    const next = action === 'inc' ? current + 1 : Math.max(0, current - 1);
    if (next > ticket.available) return;
    if (next === 0) {
      delete selection.tickets[id];
      pruneCartStorage([id]);
    } else {
      selection.tickets[id] = next;
    }
    updateSummary();
    renderTickets();
  });
}

function buildSummaryItems() {
  const items = [];
  getAllTickets().forEach((ticket) => {
    const qty = selection.tickets[ticket.id] || 0;
    if (qty > 0) {
      const unit = ticketTotalWithFees(ticket);
      const amount = qty * unit;
      items.push({ name: ticket.name, qty, amount, id: ticket.id, type: 'ticket', unitPrice: unit, fee: ticket.fee, base: ticket.basePrice });
    }
  });
  if (selection.creditValue > 0) {
    items.push({ name: 'Crédito de consumo', qty: 1, amount: selection.creditValue, id: 'credit', type: 'credit' });
  }
  return items;
}

function updateSummary() {
  const list = document.getElementById('summary-list');
  list.replaceChildren();

  const items = buildSummaryItems();
  let total = 0;
  updateCartBadge();
  updateCTAState();

  if (!items.length) {
    const emptyDiv = el('div', { className: 'empty-cart' }, [
      el('p', { className: 'empty-cart__title' }, 'Carrinho vazio'),
      el('p', { className: 'empty-cart__sub' }, 'Selecione seus ingressos ao lado'),
    ]);
    emptyDiv.insertBefore(makeSVG(EMPTY_CART_SVG), emptyDiv.firstChild);
    list.appendChild(emptyDiv);
  } else {
    items.forEach((item) => {
      total += item.amount;
      const isCredit = item.type === 'credit';
      const icon = isCredit ? 'account_balance_wallet' : 'confirmation_number';
      const rowClass = 'summary__item' + (isCredit ? ' summary__item--credit' : '');
      const row = el('div', { className: rowClass }, [
        el('span', { className: 'summary__item-icon material-symbols-outlined' }, icon),
        el('span', { className: 'summary__label' }, [
          el('span', {}, item.name),
          el('span', { className: 'summary__unit' }, `${formatBRL(item.unitPrice ?? item.amount)} × ${item.qty}`),
        ]),
        el('span', { className: 'summary__price' }, formatBRL(item.amount)),
        el('button', {
          className: 'icon-btn icon-btn--ghost summary__remove',
          'aria-label': item.qty > 1 ? 'Remover uma unidade' : 'Remover',
          'data-remove-type': item.type,
          'data-remove-id': item.id,
        }, [
          el('span', { className: 'material-symbols-outlined' }, item.qty > 1 ? 'remove' : 'close'),
        ]),
      ]);
      list.appendChild(row);
    });
  }

  document.getElementById('summary-total').textContent = formatBRL(total);
  persistSelection();
}

function updateCartBadge() {
  const badge = document.getElementById('nav-cart-badge');
  if (!badge) return;

  const ticketCount = Object.values(selection.tickets || {}).reduce((total, qty) => total + (Number(qty) || 0), 0);
  const creditCount = selection.creditValue > 0 ? 1 : 0;
  const count = ticketCount + creditCount;

  if (count <= 0) {
    badge.hidden = true;
    badge.textContent = '';
    return;
  }

  badge.hidden = false;
  badge.textContent = count > 9 ? '9+' : String(count);
}

function updateCTAState() {
  const cta = document.getElementById('cta-buy');
  if (!cta) return;
  const disabled = !hasSelectedItems();
  cta.disabled = disabled;
  if (disabled) cta.setAttribute('aria-disabled', 'true');
  else cta.removeAttribute('aria-disabled');
}

function handleRemoveItem(type, id) {
  if (type === 'credit') {
    selection.creditValue = 0;
    syncCreditControls();
  } else if (type === 'ticket') {
    const current = selection.tickets[id] || 0;
    if (current > 1) {
      selection.tickets[id] = current - 1;
    } else {
      delete selection.tickets[id];
      pruneCartStorage([id]);
    }
    renderTickets();
  }
  updateSummary();
}

function attachSummaryHandlers() {
  document.getElementById('summary-list').addEventListener('click', (e) => {
    const btn = e.target.closest('[data-remove-type]');
    if (!btn) return;
    const type = btn.getAttribute('data-remove-type');
    const id = btn.getAttribute('data-remove-id');
    handleRemoveItem(type, id);
  });
}

function attachScrollButton() {
  const btn = document.querySelector('[data-scroll-target]');
  btn?.addEventListener('click', () => {
    const target = document.querySelector(btn.dataset.scrollTarget);
    target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
}

function attachAnchorScroll() {
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener('click', (e) => {
      const href = anchor.getAttribute('href');
      if (!href || href === '#') return;
      const target = document.querySelector(href);
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });
}

function attachCTA() {
  const cta = document.getElementById('cta-buy');
  cta.addEventListener('click', () => {
    window.location.href = `${basePath()}/carrinho`;
  });
  updateCTAState();
}

async function loadBatchesFromApi() {
  try {
    const response = await fetch(BATCHES_API_URL, { credentials: 'same-origin' });
    const data = await response.json();
    if (data && data.success && Array.isArray(data.batches)) {
      batches = data.batches.map((batch) => ({
        ...batch,
        tickets: Array.isArray(batch.tickets)
          ? batch.tickets.map((ticket) => ({ ...ticket, show_highlight: !!ticket.show_highlight }))
          : [],
      }));
      pruneSelectionToExistingTickets();
      renderTickets();
      updateSummary();
    }
  } catch (err) {
    console.warn('Nao foi possivel carregar lotes do servidor', err);
  }
}

function syncCreditControls() {
  const slider = document.getElementById('credit-slider');
  const display = document.getElementById('credit-value');
  slider.value = selection.creditValue;
  display.textContent = formatBRL(selection.creditValue);
  document.querySelectorAll('#credit-quick .chip').forEach((chip) => {
    const value = Number(chip.dataset.value || 0);
    chip.classList.toggle('is-active', value === selection.creditValue);
  });
}

function attachCreditControls() {
  const slider = document.getElementById('credit-slider');
  const counter = document.getElementById('credit-counter');
  slider.addEventListener('input', (e) => {
    selection.creditValue = Number(e.target.value);
    syncCreditControls();
    updateSummary();
  });

  counter.addEventListener('click', (e) => {
    const action = e.target.getAttribute('data-credit-action');
    if (!action) return;
    const delta = action === 'inc' ? 50 : -50;
    const next = Math.min(500, Math.max(0, selection.creditValue + delta));
    selection.creditValue = next;
    syncCreditControls();
    updateSummary();
  });
}

function init() {
  safeLoadSelection();
  renderTickets();
  renderCredits();
  attachTicketHandlers();
  attachSummaryHandlers();
  attachCreditControls();
  attachScrollButton();
  attachAnchorScroll();
  attachCTA();
  updateSummary();
  loadBatchesFromApi();
}

document.addEventListener('DOMContentLoaded', init);

