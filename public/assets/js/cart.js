// Pagina de carrinho / titulares.
// Refatorado para usar DOM API (sem innerHTML com dados do servidor).

const STORAGE_KEY_CART = 'ingresso:cart';
const STORAGE_KEY_SELECTION = 'ingresso:selection';

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

const EMPTY_ATTENDEES_SVG = `<svg viewBox="0 0 108 94" width="108" height="94" fill="none" xmlns="http://www.w3.org/2000/svg">
  <ellipse cx="54" cy="66" rx="36" ry="20" fill="rgba(157,122,255,0.05)"/>
  <circle cx="54" cy="32" r="15" fill="rgba(157,122,255,0.06)" stroke="rgba(157,122,255,0.38)" stroke-width="1.5" stroke-dasharray="4.5 2.5"/>
  <path d="M22 76 Q22 54 54 54 Q86 54 86 76" fill="rgba(157,122,255,0.04)" stroke="rgba(157,122,255,0.32)" stroke-width="1.5" stroke-dasharray="4.5 2.5" stroke-linecap="round"/>
  <text x="54" y="37.5" text-anchor="middle" font-family="system-ui,sans-serif" font-size="16" font-weight="800" fill="rgba(157,122,255,0.65)">?</text>
  <circle cx="14" cy="36" r="2" fill="rgba(157,122,255,0.45)"/>
  <circle cx="94" cy="44" r="1.6" fill="rgba(90,255,214,0.45)"/>
  <circle cx="88" cy="20" r="1.4" fill="rgba(157,122,255,0.38)"/>
  <circle cx="16" cy="62" r="1.8" fill="rgba(90,255,214,0.38)"/>
  <g opacity="0.5" transform="translate(88 16)">
    <path d="M4.5 0L5.7 3.6H9.5L6.4 5.8L7.5 9.5L4.5 7.3L1.5 9.5L2.6 5.8L-0.5 3.6H3.3Z" fill="rgba(157,122,255,0.65)" stroke="rgba(157,122,255,0.75)" stroke-width="0.4"/>
  </g>
</svg>`;
const BATCHES_API_URL = 'api/ingressos.php';

const Utils = window.IngressoUtils;
const { formatBRL, isValidCpf, maskCpf, el } = Utils;

const ticketCatalog = {};
const defaultCartItems = [];
const clone = (value) => JSON.parse(JSON.stringify(value));

let cartItems = clone(defaultCartItems);
let attendees = {};

function readSelection() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY_SELECTION);
    if (raw === null) return { exists: false, data: null };
    return { exists: true, data: JSON.parse(raw) };
  } catch (err) {
    return { exists: true, data: null };
  }
}

function buildItemsFromSelection(selection) {
  if (!selection || typeof selection !== 'object') return [];

  const items = [];
  const tickets = selection.tickets || {};
  Object.entries(tickets).forEach(([id, qty]) => {
    const count = Number(qty) || 0;
    const catalog = ticketCatalog[id];
    if (!catalog || count <= 0) return;
    items.push({ id, name: catalog.name, qty: count, price: catalog.price });
  });

  if (selection.creditValue && Number(selection.creditValue) > 0) {
    items.push({
      id: 'credit',
      name: 'Credito de consumo',
      qty: 1,
      price: Number(selection.creditValue),
    });
  }

  return items;
}

function buildCatalogFromBatches(batches) {
  Object.keys(ticketCatalog).forEach((key) => delete ticketCatalog[key]);
  (batches || []).forEach((batch) => {
    (batch.tickets || []).forEach((ticket) => {
      ticketCatalog[ticket.id] = {
        name: ticket.name,
        price: Number(ticket.basePrice || 0) + Number(ticket.fee || 0),
      };
    });
  });
}

async function loadBatchesFromApi() {
  try {
    const response = await fetch(BATCHES_API_URL, { credentials: 'same-origin' });
    const data = await response.json();
    if (data && data.success && Array.isArray(data.batches)) {
      buildCatalogFromBatches(data.batches);
      return data.batches;
    }
  } catch (err) {
    console.warn('Nao foi possivel carregar lotes do servidor', err);
  }
  return [];
}

function loadCartState() {
  let savedCartItems = [];
  let savedAttendees = {};

  try {
    const raw = localStorage.getItem(STORAGE_KEY_CART);
    if (raw) {
      const parsed = JSON.parse(raw);
      if (Array.isArray(parsed.items)) savedCartItems = parsed.items;
      if (parsed.attendees && typeof parsed.attendees === 'object') savedAttendees = parsed.attendees;
    }
  } catch (err) {
    console.warn('Nao foi possivel carregar o carrinho do storage', err);
  }

  const { exists: hasSelection, data: selectionData } = readSelection();
  const selectionItems = hasSelection ? buildItemsFromSelection(selectionData) : [];
  if (hasSelection) {
    cartItems = selectionItems;
  } else if (savedCartItems.length) {
    cartItems = savedCartItems;
  }

  if (!cartItems.length) {
    attendees = {};
  } else {
    const allowedIds = new Set(cartItems.filter((item) => item.id !== 'credit').map((item) => item.id));
    attendees = Object.fromEntries(
      Object.entries(savedAttendees).filter(([ticketId]) => allowedIds.has(ticketId)),
    );
  }
}

function persistCartState() {
  try {
    localStorage.setItem(STORAGE_KEY_CART, JSON.stringify({ items: cartItems, attendees }));
  } catch (err) {
    console.warn('Nao foi possivel salvar o carrinho no storage', err);
  }
}

function syncAttendeesFromCart() {
  const nextAttendees = {};
  cartItems.forEach((item) => {
    if (item.id === 'credit') return;
    const qty = Number(item.qty) || 0;
    if (qty <= 0) return;
    const existing = attendees[item.id] || [];
    const updated = existing.slice(0, qty);
    while (updated.length < qty) {
      updated.push({ name: '', cpf: '', valid: false });
    }
    nextAttendees[item.id] = updated;
  });
  attendees = nextAttendees;
}

function renderCart() {
  const list = document.getElementById('cart-summary');
  list.replaceChildren();
  const attendeesContainer = document.getElementById('cart-attendees');
  let total = 0;

  if (!cartItems.length) {
    const emptyCartDiv = el('div', { className: 'empty-cart' }, [
      el('p', { className: 'empty-cart__title' }, 'Carrinho vazio'),
      el('p', { className: 'empty-cart__sub' }, 'Selecione seus ingressos acima'),
    ]);
    emptyCartDiv.insertBefore(makeSVG(EMPTY_CART_SVG), emptyCartDiv.firstChild);
    list.appendChild(emptyCartDiv);
    document.getElementById('cart-total').textContent = formatBRL(0);
    if (attendeesContainer) {
      const emptyAttendeesDiv = el('div', { className: 'empty-cart' }, [
        el('p', { className: 'empty-cart__title' }, 'Nenhum titular'),
        el('p', { className: 'empty-cart__sub' }, 'Adicione ingressos para preencher os dados'),
      ]);
      emptyAttendeesDiv.insertBefore(makeSVG(EMPTY_ATTENDEES_SVG), emptyAttendeesDiv.firstChild);
      attendeesContainer.replaceChildren(emptyAttendeesDiv);
    }
    return;
  }

  cartItems.forEach((item) => {
    const amount = item.price * item.qty;
    total += amount;
    const isCredit = item.id === 'credit';
    const icon = isCredit ? 'account_balance_wallet' : 'confirmation_number';
    const rowClass = 'summary__item' + (isCredit ? ' summary__item--credit' : '');
    const row = el('div', { className: rowClass }, [
      el('span', { className: 'summary__item-icon material-symbols-outlined' }, icon),
      el('span', { className: 'summary__label' }, [
        el('span', {}, item.name),
        el('span', { className: 'summary__unit' }, `${formatBRL(item.price)} × ${item.qty}`),
      ]),
      el('span', { className: 'summary__price' }, formatBRL(amount)),
    ]);
    list.appendChild(row);
  });

  document.getElementById('cart-total').textContent = formatBRL(total);
}

function renderAttendees() {
  const container = document.getElementById('cart-attendees');
  container.replaceChildren();
  const titleEl = document.getElementById('attendees-title');
  const subtitleEl = document.getElementById('attendees-subtitle');
  const actionsEl = document.getElementById('attendees-actions');

  const hasTicketItems = cartItems.some((item) => item.id !== 'credit' && (Number(item.qty) || 0) > 0);
  const creditItem = cartItems.find((item) => item.id === 'credit' && (Number(item.qty) || 0) > 0);
  const hasAttendees = Object.values(attendees || {}).some((list) => Array.isArray(list) && list.length > 0);
  const hasContent = Boolean(creditItem) || (hasTicketItems && hasAttendees);

  if (!hasContent) {
    const emptyDiv = el('div', { className: 'empty-cart' }, [
      el('p', { className: 'empty-cart__title' }, 'Nenhum titular'),
      el('p', { className: 'empty-cart__sub' }, 'Adicione ingressos para preencher os dados'),
    ]);
    emptyDiv.insertBefore(makeSVG(EMPTY_ATTENDEES_SVG), emptyDiv.firstChild);
    container.appendChild(emptyDiv);
    if (titleEl) titleEl.hidden = true;
    if (subtitleEl) subtitleEl.hidden = true;
    if (actionsEl) actionsEl.hidden = true;
    return;
  }

  if (titleEl) titleEl.hidden = false;
  if (subtitleEl) subtitleEl.hidden = false;
  if (actionsEl) actionsEl.hidden = false;

  if (creditItem) {
    const card = el('div', { className: 'attendee-card attendee-card--credit is-valid' }, [
      el('div', { className: 'attendee-card__header' }, [
        el('div', { className: 'attendee-card__index' }, [
          el('span', { className: 'material-symbols-outlined' }, 'account_balance_wallet'),
        ]),
        el('div', { className: 'attendee-card__info' }, [
          el('span', { className: 'attendee-card__name' }, 'Crédito de consumo'),
          el('p', { className: 'attendee-card__ticket' }, formatBRL(creditItem.price)),
        ]),
        el('div', { className: 'attendee-card__status is-valid' }, [
          el('span', { className: 'material-symbols-outlined' }, 'verified'),
          el('small', {}, 'Voucher aplicado'),
        ]),
      ]),
      el('div', { className: 'attendee-card__body attendee-card__body--credit' }, [
        el('p', { className: 'attendee-card__credit-note' }, 'Seu saldo voucher poderá ser resgatado no dia do evento em forma de fichas ou créditos de consumação.'),
      ]),
    ]);
    container.appendChild(card);
  }

  Object.entries(attendees).forEach(([ticketId, list]) => {
    const ticketName = (ticketCatalog[ticketId]?.name || ticketId).toString();
    list.forEach((attendee, index) => {
      const nameInput = el('input', {
        type: 'text',
        placeholder: ' ',
        value: attendee.name || '',
        'data-ticket': ticketId,
        'data-index': String(index),
        'data-field': 'name',
      });
      const cpfInput = el('input', {
        type: 'text',
        placeholder: '000.000.000-00',
        value: attendee.cpf || '',
        'data-ticket': ticketId,
        'data-index': String(index),
        'data-field': 'cpf',
      });

      const statusClass = 'attendee-card__status ' + (attendee.valid ? 'is-valid' : 'is-invalid');
      const cardClass = 'attendee-card' + (attendee.valid ? ' is-valid' : '');
      const card = el('div', { className: cardClass }, [
        el('div', { className: 'attendee-card__header' }, [
          el('div', { className: 'attendee-card__index' }, String(index + 1)),
          el('div', { className: 'attendee-card__info' }, [
            el('span', { className: 'attendee-card__name' }, ticketName),
            el('p', { className: 'attendee-card__ticket' }, `Titular ${index + 1}`),
          ]),
          el('div', { className: statusClass }, [
            el('span', { className: 'material-symbols-outlined' }, attendee.valid ? 'verified' : 'error'),
            el('small', {}, attendee.valid ? 'Pronto para emitir' : 'Preencha os dados'),
          ]),
        ]),
        el('div', { className: 'attendee-card__body' }, [
          el('label', { className: 'field field--float attendee-card__field' }, [
            nameInput,
            el('span', {}, 'Nome completo'),
            el('span', { className: 'attendee-card__field-icon material-symbols-outlined', 'aria-hidden': 'true' }, 'person'),
          ]),
          el('label', { className: 'field field--float attendee-card__field' }, [
            cpfInput,
            el('span', {}, 'CPF'),
            el('span', { className: 'attendee-card__field-icon material-symbols-outlined', 'aria-hidden': 'true' }, 'badge'),
          ]),
        ]),
      ]);
      container.appendChild(card);
    });
  });
}

function attachAttendeeHandlers() {
  document.getElementById('cart-attendees').addEventListener('input', (e) => {
    const field = e.target.getAttribute('data-field');
    const ticketId = e.target.getAttribute('data-ticket');
    const index = Number(e.target.getAttribute('data-index'));
    if (!field || !ticketId || Number.isNaN(index)) return;
    const value = field === 'cpf' ? maskCpf(e.target.value) : e.target.value;
    e.target.value = value;

    const attendee = attendees[ticketId][index];
    attendee[field] = value;
    const cpfValid = isValidCpf(attendee.cpf);
    const hasName = Boolean(attendee.name.trim());
    attendee.valid = hasName && cpfValid;

    const card = e.target.closest('.attendee-card');
    if (card) {
      const status = card.querySelector('.attendee-card__status');
      const icon = status?.querySelector('.material-symbols-outlined');
      const label = status?.querySelector('small');
      card.classList.toggle('is-valid', attendee.valid);
      status?.classList.toggle('is-valid', attendee.valid);
      status?.classList.toggle('is-invalid', !attendee.valid);
      if (icon) icon.textContent = attendee.valid ? 'verified' : 'error';
      if (label) {
        if (attendee.valid) label.textContent = 'Pronto para emitir';
        else if (value && !cpfValid) label.textContent = 'CPF inválido';
        else label.textContent = 'Preencha os dados';
      }
    }

    persistCartState();
  });
}

function attachCTA() {
  const btn = document.getElementById('cta-buy');
  if (!btn) return;

  btn.addEventListener('click', async () => {
    // 1. Valida se há itens
    if (!cartItems.length) return;

    // 2. Valida titulares
    const ticketItems = cartItems.filter((i) => i.id !== 'credit');
    for (const item of ticketItems) {
      const list = attendees[item.id] || [];
      const qty  = Number(item.qty) || 0;
      for (let i = 0; i < qty; i++) {
        const a = list[i];
        if (!a || !a.valid) {
          showCartToast('Preencha os dados de todos os titulares antes de continuar.');
          document.getElementById('cart-attendees')?.scrollIntoView({ behavior: 'smooth' });
          return;
        }
      }
    }

    // 3. Monta payload de titulares { ticketUid: [{ name, cpf }] }
    const attendeesPayload = {};
    for (const [ticketId, list] of Object.entries(attendees)) {
      attendeesPayload[ticketId] = list.map((a) => ({ name: a.name, cpf: a.cpf }));
    }

    // 4. CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const basePath  = (document.querySelector('meta[name="base-path"]')?.content ?? '').replace(/\/$/, '');

    // 5. Desabilita botão e mostra loading
    btn.disabled = true;
    btn.textContent = 'Aguarde…';

    try {
      const res = await fetch(`${basePath}/api/pedido/criar.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          csrf_token: csrfToken,
          items:      cartItems,
          attendees:  attendeesPayload,
        }),
      });

      const data = await res.json();

      if (!res.ok || !data.success) {
        showCartToast(data.error || 'Erro ao criar pedido. Tente novamente.');
        btn.disabled = false;
        btn.textContent = 'Ir para pagamento';
        return;
      }

      // 6. Limpa o storage e redireciona para o checkout
      try { localStorage.removeItem(STORAGE_KEY_CART); } catch (_) {}
      try { localStorage.removeItem(STORAGE_KEY_SELECTION); } catch (_) {}

      window.location.href = `${basePath}/checkout?uid=${encodeURIComponent(data.pedido_uid)}`;
    } catch (err) {
      showCartToast('Erro de conexão. Verifique sua internet e tente novamente.');
      btn.disabled = false;
      btn.textContent = 'Ir para pagamento';
    }
  });
}

function showCartToast(msg) {
  // Tenta usar toast do sistema, cai no alert se não existir
  if (window.IngressoUtils?.showToast) {
    window.IngressoUtils.showToast(msg, 'error');
  } else {
    // Cria toast inline simples
    let toast = document.getElementById('cart-toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'cart-toast';
      toast.style.cssText = [
        'position:fixed', 'bottom:80px', 'left:50%', 'transform:translateX(-50%)',
        'background:var(--color-error,#ff4444)', 'color:#fff', 'padding:12px 20px',
        'border-radius:10px', 'font-size:0.9rem', 'z-index:9999',
        'max-width:90vw', 'text-align:center', 'box-shadow:0 4px 16px rgba(0,0,0,.4)',
      ].join(';');
      document.body.appendChild(toast);
    }
    toast.textContent = msg;
    toast.style.display = 'block';
    clearTimeout(toast._timeout);
    toast._timeout = setTimeout(() => { toast.style.display = 'none'; }, 4000);
  }
}

async function init() {
  await loadBatchesFromApi();
  loadCartState();
  syncAttendeesFromCart();
  renderCart();
  renderAttendees();
  attachAttendeeHandlers();
  attachCTA();
  persistCartState();
}

document.addEventListener('DOMContentLoaded', async () => {
  await init();
  attachCartMobileBar();
});
