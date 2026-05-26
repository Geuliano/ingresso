// Utilitários compartilhados entre páginas. Carregado antes dos demais scripts.
// Expõe namespace `window.IngressoUtils`.

(function () {
  'use strict';

  /**
   * Escapa string para uso seguro em HTML.
   * NUNCA injetar dados do servidor diretamente via innerHTML sem passar por aqui.
   */
  function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  /**
   * Tagged template literal helper: escapa todas as interpolacoes automaticamente.
   *   html`<h3>${userInput}</h3>`  ->  retorna string ja escapada.
   */
  function html(strings) {
    const values = Array.prototype.slice.call(arguments, 1);
    let out = '';
    for (let i = 0; i < strings.length; i++) {
      out += strings[i];
      if (i < values.length) {
        out += escapeHtml(values[i]);
      }
    }
    return out;
  }

  function formatBRL(value) {
    return Number(value || 0).toLocaleString('pt-BR', {
      style: 'currency',
      currency: 'BRL',
    });
  }

  function normalizeCpf(value) {
    return (value || '').replace(/\D+/g, '');
  }

  function isValidCpf(cpf) {
    const digits = normalizeCpf(cpf);
    if (digits.length !== 11 || /^(\d)\1+$/.test(digits)) return false;
    let sum = 0;
    for (let i = 0; i < 9; i++) sum += Number(digits[i]) * (10 - i);
    let first = (sum * 10) % 11;
    if (first === 10) first = 0;
    if (first !== Number(digits[9])) return false;
    sum = 0;
    for (let i = 0; i < 10; i++) sum += Number(digits[i]) * (11 - i);
    let second = (sum * 10) % 11;
    if (second === 10) second = 0;
    return second === Number(digits[10]);
  }

  function maskCpf(value) {
    return (value || '')
      .replace(/\D/g, '')
      .slice(0, 11)
      .replace(/(\d{3})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
  }

  function isValidEmail(value) {
    return /\S+@\S+\.\S+/.test(value || '');
  }

  function normalizePhone(value) {
    return (value || '').replace(/\D/g, '').slice(0, 13);
  }

  function isValidBrPhone(value) {
    const digits = normalizePhone(value);
    if (digits.startsWith('55')) return digits.length === 13;
    return digits.length === 11;
  }

  /**
   * Retorna o caminho base do site (lido do <meta name="base-path">).
   */
  function basePath() {
    const meta = document.querySelector('meta[name="base-path"]');
    return ((meta && meta.content) || '').replace(/\/$/, '');
  }

  function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return (meta && meta.content) || '';
  }

  /**
   * POST application/x-www-form-urlencoded. Inclui csrf_token automaticamente.
   */
  async function postForm(url, data) {
    const body = new URLSearchParams(Object.assign({}, data || {}, { csrf_token: csrfToken() }));
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      credentials: 'same-origin',
      body,
    });
    const json = await response.json().catch(() => ({}));
    if (!response.ok) throw json;
    return json;
  }

  /**
   * Cria um elemento DOM com atributos e filhos. Texto sempre via textContent
   * (sem risco de XSS).
   *
   *   el('div', { className: 'card' }, [
   *     el('h3', {}, ticket.name),
   *     el('span', { className: 'badge' }, badgeText),
   *   ])
   */
  function el(tag, attrs, children) {
    const node = document.createElement(tag);
    if (attrs && typeof attrs === 'object') {
      Object.keys(attrs).forEach((key) => {
        const value = attrs[key];
        if (value === null || value === undefined || value === false) return;
        if (key === 'className') node.className = String(value);
        else if (key === 'dataset' && typeof value === 'object') {
          Object.keys(value).forEach((dk) => {
            node.dataset[dk] = value[dk];
          });
        } else if (key.startsWith('on') && typeof value === 'function') {
          node.addEventListener(key.slice(2).toLowerCase(), value);
        } else {
          node.setAttribute(key, String(value));
        }
      });
    }
    appendChild(node, children);
    return node;
  }

  function appendChild(parent, children) {
    if (children === null || children === undefined || children === false) return;
    if (Array.isArray(children)) {
      children.forEach((c) => appendChild(parent, c));
      return;
    }
    if (children instanceof Node) {
      parent.appendChild(children);
      return;
    }
    parent.appendChild(document.createTextNode(String(children)));
  }

  window.IngressoUtils = {
    escapeHtml,
    html,
    formatBRL,
    normalizeCpf,
    isValidCpf,
    maskCpf,
    isValidEmail,
    normalizePhone,
    isValidBrPhone,
    basePath,
    csrfToken,
    postForm,
    el,
  };
})();
