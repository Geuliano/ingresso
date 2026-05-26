document.addEventListener('DOMContentLoaded', () => {
  const badge = document.getElementById('nav-cart-badge');
  const cartLink = document.getElementById('nav-cart');
  const ticketsLink = document.getElementById('nav-tickets');
  const nav = document.querySelector('.bottom-nav');
  const navItems = nav ? Array.from(nav.querySelectorAll('.nav-item')) : [];

  const setActiveNav = (link) => {
    if (!navItems.length || !link) return;
    navItems.forEach((item) => {
      item.classList.toggle('is-active', item === link);
    });
  };

  const applyHashActiveState = () => {
    if (location.hash === '#tickets-section' && document.getElementById('tickets-section')) {
      setActiveNav(ticketsLink);
    }
  };

  const parseJson = (key) => {
    try {
      const raw = localStorage.getItem(key);
      if (!raw) return null;
      return JSON.parse(raw);
    } catch (err) {
      return null;
    }
  };

  const countSelection = () => {
    const data = parseJson('ingresso:selection');
    if (!data || typeof data !== 'object') return 0;
    const tickets = data.tickets || {};
    const ticketCount = Object.values(tickets).reduce((total, qty) => total + (Number(qty) || 0), 0);
    const creditCount = data.creditValue && Number(data.creditValue) > 0 ? 1 : 0;
    return ticketCount + creditCount;
  };

  const countCart = () => {
    const data = parseJson('ingresso:cart');
    if (!data || !Array.isArray(data.items)) return 0;
    return data.items.reduce((total, item) => total + (Number(item.qty) || 0), 0);
  };

  const updateBadge = () => {
    if (!badge) return;
    const total = Math.max(countSelection(), countCart());
    if (total <= 0) {
      badge.hidden = true;
      badge.style.display = 'none';
      badge.textContent = '';
      return;
    }
    badge.hidden = false;
    badge.style.display = '';
    badge.textContent = total > 9 ? '9+' : String(total);
  };

  const handleCartClick = (event) => {
    if (!cartLink) return;
    const summary = document.querySelector('#summary');
    if (summary) {
      event.preventDefault();
      summary.scrollIntoView({ behavior: 'smooth', block: 'start' });
      return;
    }
    const fallback = cartLink.getAttribute('data-summary-href');
    if (fallback) {
      event.preventDefault();
      window.location.href = fallback;
    }
  };

  updateBadge();
  window.addEventListener('storage', updateBadge);
  window.addEventListener('hashchange', applyHashActiveState);

  if (cartLink) {
    cartLink.addEventListener('click', handleCartClick);
  }

  if (ticketsLink) {
    ticketsLink.addEventListener('click', () => setActiveNav(ticketsLink));
  }

  applyHashActiveState();
});
