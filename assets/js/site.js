document.addEventListener('DOMContentLoaded', function () {
  const navLinks = document.querySelectorAll('.nav-bar a');
  const cards = Array.from(document.querySelectorAll('.product-grid > article'));
  const grid = document.querySelector('.product-grid');

  // remember original order index for 'popularity' sorting
  cards.forEach((c, idx) => c.dataset.origIndex = idx);

  const normalize = (s='') => s.toString().toLowerCase();

  function showAll() {
    cards.forEach(c => c.classList.remove('hidden'));
  }

  function applyFilter(filter) {
    if (!filter) { showAll(); return; }
    if (filter === 'all') { showAll(); return; }
    if (filter === 'promo') {
      cards.forEach(c => {
        const d = parseInt(c.dataset.discount || '0', 10);
        if (d > 0) c.classList.remove('hidden'); else c.classList.add('hidden');
      });
      return;
    }

    // Filter by keywords from data-keywords attribute
    const q = normalize(filter || '');
    cards.forEach(c => {
      const keywords = normalize(c.dataset.keywords || '');
      const categoryRaw = (c.dataset.categoryRaw || '').toLowerCase();
      if (keywords.includes(q) || categoryRaw === q) {
        c.classList.remove('hidden');
      } else {
        c.classList.add('hidden');
      }
    });
  }

  navLinks.forEach(a => {
    a.addEventListener('click', function (ev) {
      const f = a.dataset.filter || '';
      const path = (window.location.pathname || '').toLowerCase();
      const isOnMenu = path.endsWith('menu.php');
      // if not on menu page, navigate to menu.php with filter param
      if (!isOnMenu) {
        const url = 'menu.php' + (f ? '?filter=' + encodeURIComponent(f) : '');
        window.location.href = url;
        return;
      }
      // on menu page: handle client-side filtering as before
      ev.preventDefault();
      const already = a.classList.contains('active');
      navLinks.forEach(x => x.classList.remove('active'));
      if (already) { showAll(); return; }
      a.classList.add('active');
      applyFilter(f);
      if (grid) grid.scrollIntoView({behavior:'smooth', block:'start'});
    });
  });

  // Sorting
  function parsePrice(card) {
    const raw = card.dataset.price || '';
    const digits = raw.replace(/[^0-9\-]/g, '');
    return digits ? parseInt(digits, 10) : 0;
  }

  function applySort(mode) {
    if (!grid) return;
    const all = Array.from(grid.querySelectorAll('article'));
    let sorted = all.slice();
    if (mode === 'price_asc') {
      sorted.sort((a,b) => parsePrice(a) - parsePrice(b));
    } else if (mode === 'price_desc') {
      sorted.sort((a,b) => parsePrice(b) - parsePrice(a));
    } else { // popularity / default -> original order
      sorted.sort((a,b) => (parseInt(a.dataset.origIndex,10) || 0) - (parseInt(b.dataset.origIndex,10)||0));
    }
    // append in new order
    sorted.forEach(el => grid.appendChild(el));
  }

  const sortSelect = document.getElementById('sort');
  if (sortSelect) {
    sortSelect.addEventListener('change', function (ev) {
      applySort(sortSelect.value);
    });
  }

  try {
    const params = new URLSearchParams(window.location.search);
    const f = params.get('filter');
    if (f) {
      const target = Array.from(navLinks).find(a => (a.dataset.filter||'') === f);
      if (target) target.click(); else applyFilter(f);
    }
  } catch (e) { /* ignore */ }

  // Search input handling (debounced) - works with both header and menu search
  const searchInputs = document.querySelectorAll('.search-input, .search-input-menu');
  
  function debounce(fn, ms = 250) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
  }

  function applySearch(query) {
    const q = normalize(query || '');
    if (!q) { showAll(); return; }
    // clear nav active state when searching
    navLinks.forEach(x => x.classList.remove('active'));
    cards.forEach(c => {
      const keywords = normalize(c.dataset.keywords || '');
      const desc = normalize(c.dataset.description || '');
      const name = normalize(c.dataset.name || '');
      if (keywords.includes(q) || desc.includes(q) || name.includes(q)) c.classList.remove('hidden'); else c.classList.add('hidden');
    });
  }

  searchInputs.forEach(searchInput => {
    if (!searchInput) return;
    const h = debounce(e => applySearch(e.target.value), 220);
    searchInput.addEventListener('input', h);
    searchInput.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape') { searchInput.value = ''; applySearch(''); }
    });
  });

  // Account dropdown toggles (header/footer)
  document.querySelectorAll('.account-wrap').forEach(wrap => {
    const toggle = wrap.querySelector('.account-toggle');
    if (!toggle) return;
    toggle.addEventListener('click', function (ev) {
      ev.preventDefault();
      wrap.classList.toggle('open');
    });
    // close when clicking outside
    document.addEventListener('click', function (ev) {
      if (!wrap.contains(ev.target)) wrap.classList.remove('open');
    });
  });
});
