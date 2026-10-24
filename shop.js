/* ═══════════════════════════════════════════════════════════════════════
   shop.js — Gadget Genesis shop/collection page interactions
   ═══════════════════════════════════════════════════════════════════════ */

/* ── Toast ───────────────────────────────────────────────────────────── */
function showToast(msg, duration = 2800) {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), duration);
}

/* ── Cart badge sync ─────────────────────────────────────────────────── */
function updateCartBadge() {
  const cart  = JSON.parse(localStorage.getItem('gg_cart') || '[]');
  const total = cart.reduce((sum, item) => sum + item.qty, 0);
  document.querySelectorAll('.cbadge').forEach(b => {
    b.textContent  = total > 0 ? total : '';
    b.style.display = total > 0 ? 'flex' : 'none';
  });
}

/* ── Add to cart (called from inline onclick) ────────────────────────── */
function addToCart(id, model, price) {
  const cart     = JSON.parse(localStorage.getItem('gg_cart') || '[]');
  const existing = cart.findIndex(c => c.id === id);

  if (existing > -1) {
    cart[existing].qty += 1;
  } else {
    cart.push({ id, model, price, qty: 1 });
  }

  localStorage.setItem('gg_cart', JSON.stringify(cart));
  updateCartBadge();
  showToast(`${model} added to cart!`);
}

/* ── Wishlist toggle (called from inline onclick) ────────────────────── */
function toggleWish(btn) {
  // Derive product data from the parent card
  const card   = btn.closest('a.shop-card');
  const href   = card?.getAttribute('href') || '';
  const id     = parseInt(new URLSearchParams(href.split('?')[1] || '').get('id'), 10);
  const model  = card?.querySelector('.shop-card-name')?.textContent.trim() || '';
  const price  = parseInt(card?.dataset.price || '0', 10);
  const img    = card?.querySelector('.shop-card-img img')?.src || null;

  const added = id ? toggleWishItem(id, model, price, img) : !btn.classList.contains('active');

  btn.classList.toggle('active', added);
  const icon = btn.querySelector('[data-lucide]');
  if (icon) {
    icon.style.fill  = added ? 'var(--red)' : 'none';
    icon.style.color = added ? 'var(--red)' : '';
  }
  showToast(added ? 'Added to wishlist' : 'Removed from wishlist');
}

/* ── Restore wishlist state on shop cards ────────────────────────────── */
(function () {
  document.querySelectorAll('a.shop-card').forEach(card => {
    const href = card.getAttribute('href') || '';
    const id   = parseInt(new URLSearchParams(href.split('?')[1] || '').get('id'), 10);
    if (!id || !isWished(id)) return;
    const btn  = card.querySelector('.shop-card-wish');
    const icon = btn?.querySelector('[data-lucide]');
    if (btn)  btn.classList.add('active');
    if (icon) { icon.style.fill = 'var(--red)'; icon.style.color = 'var(--red)'; }
  });
})();

/* ── View toggle (grid / list) ───────────────────────────────────────── */
(function () {
  const grid     = document.getElementById('shopGrid');
  const gridBtn  = document.getElementById('gridView');
  const listBtn  = document.getElementById('listView');
  if (!grid || !gridBtn || !listBtn) return;

  gridBtn.addEventListener('click', () => {
    grid.classList.remove('list-view');
    gridBtn.classList.add('active');
    listBtn.classList.remove('active');
    localStorage.setItem('gg_view', 'grid');
  });

  listBtn.addEventListener('click', () => {
    grid.classList.add('list-view');
    listBtn.classList.add('active');
    gridBtn.classList.remove('active');
    localStorage.setItem('gg_view', 'list');
  });

  // Restore saved preference
  if (localStorage.getItem('gg_view') === 'list') {
    listBtn.click();
  }
})();

/* ── Price range filter ──────────────────────────────────────────────── */
(function () {
  const slider  = document.getElementById('priceRange');
  const valEl   = document.getElementById('priceRangeVal');
  const cards   = document.querySelectorAll('.shop-card');
  if (!slider) return;

  function formatPrice(n) {
    return 'UGX ' + Number(n).toLocaleString();
  }

  function applyFilters() {
    const maxPrice  = +slider.value;
    const minRating = getSelectedRating();
    const onlySale  = document.querySelector('.avail-check[value="sale"]')?.checked;
    let   visible   = 0;

    cards.forEach(card => {
      const price  = +card.dataset.price;
      const rating = +card.dataset.rating;
      const hasSale = card.querySelector('.shop-sale-badge, .shop-card-badge') !== null;

      // price === 0 means no price set — always show, don't filter out
      const priceOk  = price === 0 || price <= maxPrice;
      const ratingOk = rating >= minRating;
      const saleOk   = !onlySale || hasSale;

      const show = priceOk && ratingOk && saleOk;
      card.classList.toggle('hidden', !show);
      if (show) visible++;
    });

    const countEl = document.getElementById('visibleCount');
    if (countEl) countEl.textContent = visible;
  }

  function getSelectedRating() {
    const checked = document.querySelector('.rating-radio:checked');
    return checked ? +checked.value : 0;
  }

  slider.addEventListener('input', () => {
    if (valEl) valEl.textContent = formatPrice(slider.value);
    applyFilters();
  });

  // Rating radios
  document.querySelectorAll('.rating-radio').forEach(radio => {
    radio.addEventListener('change', applyFilters);
  });

  // Availability checkboxes
  document.querySelectorAll('.avail-check').forEach(cb => {
    cb.addEventListener('change', applyFilters);
  });

  // Only run filters on slider interaction, not on page load
  // (all products visible by default)
})();

/* ── Clear all filters ───────────────────────────────────────────────── */
(function () {
  const btn = document.getElementById('clearFilters');
  if (!btn) return;

  btn.addEventListener('click', () => {
    // Reset price slider
    const slider = document.getElementById('priceRange');
    if (slider) {
      slider.value = slider.max;
      const valEl = document.getElementById('priceRangeVal');
      if (valEl) valEl.textContent = 'UGX ' + Number(slider.max).toLocaleString();
    }

    // Reset rating to "All"
    const allRating = document.querySelector('.rating-radio[value="0"]');
    if (allRating) allRating.checked = true;

    // Uncheck availability
    document.querySelectorAll('.avail-check').forEach(cb => {
      cb.checked = cb.value === 'instock';
    });

    // Show all cards
    document.querySelectorAll('.shop-card').forEach(c => c.classList.remove('hidden'));

    // Update count
    const countEl = document.getElementById('visibleCount');
    if (countEl) countEl.textContent = document.querySelectorAll('.shop-card').length;

    showToast('Filters cleared');
  });
})();

/* ── Mobile sidebar toggle ───────────────────────────────────────────── */
(function () {
  const toggle  = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('shopSidebar');
  if (!toggle || !sidebar) return;

  // Create overlay
  const overlay = document.createElement('div');
  overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:299;display:none';
  document.body.appendChild(overlay);

  function open() {
    sidebar.classList.add('open');
    overlay.style.display = 'block';
    document.body.style.overflow = 'hidden';
  }

  function close() {
    sidebar.classList.remove('open');
    overlay.style.display = 'none';
    document.body.style.overflow = '';
  }

  toggle.addEventListener('click', () => {
    sidebar.classList.contains('open') ? close() : open();
  });

  overlay.addEventListener('click', close);
})();

/* ── Sticky header shadow ────────────────────────────────────────────── */
(function () {
  const header = document.querySelector('.header');
  if (!header) return;
  window.addEventListener('scroll', () => {
    header.style.boxShadow = scrollY > 8
      ? '0 2px 14px rgba(0,0,0,.1)'
      : '0 1px 3px rgba(0,0,0,.06)';
  }, { passive: true });
})();

/* ── Init ────────────────────────────────────────────────────────────── */
updateCartBadge();

/* ═══════════════════════════════════════════════════════════════════════
   QUICK VIEW MODAL
═══════════════════════════════════════════════════════════════════════ */
(function () {

  /* ── Inject overlay HTML once ── */
  const overlay = document.createElement('div');
  overlay.id = 'qvOverlay';
  overlay.innerHTML = '<div class="qv-modal" id="qvModal"></div>';
  document.body.appendChild(overlay);

  function closeQV() {
    overlay.classList.remove('open');
    document.getElementById('qvModal').innerHTML = '';
    document.body.style.overflow = '';
  }

  overlay.addEventListener('click', e => { if (e.target === overlay) closeQV(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeQV(); });

  /* ── Render star HTML ── */
  function starsHtml(avg) {
    if (!avg) return '';
    let html = '';
    for (let s = 1; s <= 5; s++) {
      html += `<i data-lucide="star" class="star-i ${s <= Math.round(avg) ? 'on' : ''}"></i>`;
    }
    return html;
  }

  /* ── Format price ── */
  function fmtP(n) { return 'UGX ' + Number(n).toLocaleString(); }

  /* ── Open modal ── */
  window.openQuickView = async function (productId) {
    const modal = document.getElementById('qvModal');
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
    modal.innerHTML = '<div class="qv-spinner"><i data-lucide="loader-circle" style="width:28px;height:28px;animation:spin 1s linear infinite;"></i></div>';
    if (window.lucide) lucide.createIcons();

    try {
      const res  = await fetch(`api/quick-view.php?id=${productId}`);
      const p    = await res.json();
      if (p.error) throw new Error(p.error);

      const placeholder = `https://placehold.co/400x400/f3f4f6/aaa?text=${encodeURIComponent(p.model)}`;
      const mainImg     = p.image || placeholder;

      /* ── Price HTML ── */
      let priceHtml = '';
      if (p.has_discount) {
        const savePct = p.lowest_price > 0 ? Math.round((p.lowest_price - p.lowest_sale) / p.lowest_price * 100) : 0;
        priceHtml = `<span class="qv-price-old">${fmtP(p.lowest_price)}</span>
                     <span class="qv-price sale">${fmtP(p.lowest_sale)}</span>
                     <span class="qv-save-badge">-${savePct}% OFF</span>`;
      } else {
        priceHtml = `<span class="qv-price">${p.lowest_sale > 0 ? fmtP(p.lowest_sale) : '—'}</span>`;
      }

      /* ── Storage options ── */
      const storageHtml = p.storages.length
        ? `<div class="qv-section-label">Storage</div>
           <div class="qv-storage-opts">
             ${p.storages.map((s, i) => `
               <button class="qv-stor-btn ${i === 0 ? 'active' : ''}"
                 data-price="${s.sale_price}"
                 data-label="${s.label}"
                 onclick="qvSelectStorage(this)">
                 ${s.label}
               </button>`).join('')}
           </div>`
        : '';

      /* ── Color options ── */
      const colorHtml = p.colors.length
        ? `<div class="qv-section-label">Color</div>
           <div class="qv-color-opts">
             ${p.colors.map((c, i) => `
               <button class="qv-col-btn ${i === 0 ? 'active' : ''}"
                 data-color="${c.name}"
                 data-img="${c.image || placeholder}"
                 onclick="qvSelectColor(this)">
                 ${c.name}
               </button>`).join('')}
           </div>`
        : '';

      /* ── Rating ── */
      const ratingHtml = p.avg_rating
        ? `<div class="qv-rating">
             ${starsHtml(p.avg_rating)}
             <span style="font-size:13px;color:var(--gray);margin-left:4px;">${p.avg_rating} (${p.review_count} review${p.review_count !== 1 ? 's' : ''})</span>
           </div>`
        : '';

      /* ── Collections ── */
      const collHtml = p.collections.length
        ? `<div class="qv-collections">${p.collections.map(c => `<span class="qv-coll-badge">${c}</span>`).join('')}</div>`
        : '';

      modal.innerHTML = `
        <button class="qv-close" onclick="document.getElementById('qvOverlay').classList.remove('open');document.getElementById('qvModal').innerHTML='';document.body.style.overflow='';">
          <i data-lucide="x"></i>
        </button>
        <div class="qv-image">
          <img id="qvMainImg" src="${mainImg}" alt="${p.model}"
               onerror="this.src='${placeholder}'"/>
        </div>
        <div class="qv-info">
          ${collHtml}
          <h2 class="qv-name">${p.model}</h2>
          ${ratingHtml}
          <div class="qv-price-block">${priceHtml}</div>
          ${p.description ? `<p class="qv-desc">${p.description.substring(0, 200)}${p.description.length > 200 ? '…' : ''}</p>` : ''}
          ${storageHtml}
          ${colorHtml}
          <div class="qv-actions">
            <button class="qv-add-cart" onclick="qvAddToCart(${productId}, '${p.model.replace(/'/g, "\\'")}', '${mainImg}')">
              <i data-lucide="shopping-cart"></i> Add to Cart
            </button>
            <a href="product.php?id=${productId}" class="qv-view-full">
              <i data-lucide="arrow-right"></i> Full Details
            </a>
          </div>
        </div>`;

      if (window.lucide) lucide.createIcons();

    } catch (err) {
      modal.innerHTML = `<div class="qv-spinner" style="color:#dc2626;">Failed to load product. <button onclick="closeQV()" style="margin-left:8px;cursor:pointer;background:none;border:none;font-weight:700;">Close</button></div>`;
    }
  };

  /* ── Storage selection — update displayed price ── */
  window.qvSelectStorage = function (btn) {
    document.querySelectorAll('.qv-stor-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const price = parseFloat(btn.dataset.price);
    const priceEl = document.querySelector('.qv-price');
    if (priceEl && price) priceEl.textContent = fmtP(price);
  };

  /* ── Color selection — swap image ── */
  window.qvSelectColor = function (btn) {
    document.querySelectorAll('.qv-col-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const img = document.getElementById('qvMainImg');
    if (img && btn.dataset.img) img.src = btn.dataset.img;
  };

  /* ── Add to cart from modal ── */
  window.qvAddToCart = function (id, model, image) {
    const activeStorage = document.querySelector('.qv-stor-btn.active');
    const activeColor   = document.querySelector('.qv-col-btn.active');
    const price = activeStorage ? parseFloat(activeStorage.dataset.price) : 0;
    const storage = activeStorage ? activeStorage.dataset.label : '';
    const color   = activeColor   ? activeColor.dataset.color   : '';

    window.addToCart(id, model, price, image, storage, color);

    /* brief button feedback */
    const btn = document.querySelector('.qv-add-cart');
    if (btn) {
      btn.style.background = '#16a34a';
      btn.innerHTML = '<i data-lucide="check"></i> Added!';
      if (window.lucide) lucide.createIcons();
      setTimeout(() => {
        btn.style.background = '';
        btn.innerHTML = '<i data-lucide="shopping-cart"></i> Add to Cart';
        if (window.lucide) lucide.createIcons();
      }, 1800);
    }
  };

})();

/* ── Spin animation for loader ── */
if (!document.getElementById('qv-spin-style')) {
  const s = document.createElement('style');
  s.id = 'qv-spin-style';
  s.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
  document.head.appendChild(s);
}

/* ═══════════════════════════════════════════════════════════════════════
   PRODUCT COMPARISON
═══════════════════════════════════════════════════════════════════════ */
(function () {
  const COMPARE_KEY = 'gg_compare';
  const MAX_COMPARE = 3;

  function getCompare()        { return JSON.parse(localStorage.getItem(COMPARE_KEY) || '[]'); }
  function saveCompare(list)   { localStorage.setItem(COMPARE_KEY, JSON.stringify(list)); }

  /* ── Inject compare bar HTML once ── */
  const bar = document.createElement('div');
  bar.id = 'compareBar';
  bar.innerHTML = `
    <div class="cmp-bar-inner">
      <div class="cmp-bar-left">
        <i data-lucide="git-compare" style="width:18px;height:18px;"></i>
        <span class="cmp-bar-label">Compare (<span id="cmpCount">0</span>/3)</span>
        <div class="cmp-bar-slots" id="cmpSlots"></div>
      </div>
      <div class="cmp-bar-right">
        <button id="cmpGoBtn" class="cmp-bar-go">Compare Now</button>
        <button id="cmpClearBtn" class="cmp-bar-clear">Clear</button>
      </div>
    </div>`;
  document.body.appendChild(bar);

  /* Inject styles */
  const style = document.createElement('style');
  style.textContent = `
    #compareBar {
      display: none;
      position: fixed;
      bottom: 0; left: 0; right: 0;
      z-index: 8000;
      background: var(--navy);
      color: #fff;
      box-shadow: 0 -4px 20px rgba(0,0,0,.2);
    }
    #compareBar.open { display: block; }
    .cmp-bar-inner {
      max-width: 1280px; margin: 0 auto;
      padding: 12px 24px;
      display: flex; align-items: center; justify-content: space-between;
      gap: 16px; flex-wrap: wrap;
    }
    .cmp-bar-left  { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .cmp-bar-label { font-size: 14px; font-weight: 700; }
    .cmp-bar-slots { display: flex; gap: 8px; }
    .cmp-slot {
      display: flex; align-items: center; gap: 6px;
      background: rgba(255,255,255,.12);
      border-radius: 8px; padding: 6px 10px;
      font-size: 13px; font-weight: 600;
    }
    .cmp-slot img { width: 32px; height: 32px; object-fit: cover; border-radius: 5px; background: #fff; }
    .cmp-slot-name { max-width: 110px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cmp-slot-remove {
      background: none; border: none; color: rgba(255,255,255,.6);
      cursor: pointer; font-size: 14px; padding: 0 2px; line-height: 1;
    }
    .cmp-slot-remove:hover { color: #fff; }
    .cmp-bar-right { display: flex; gap: 8px; }
    .cmp-bar-go {
      padding: 9px 20px;
      background: #2563eb; color: #fff;
      border: none; border-radius: 8px;
      font-size: 13px; font-weight: 700; cursor: pointer; font-family: inherit;
    }
    .cmp-bar-go:hover { background: #1d4ed8; }
    .cmp-bar-go:disabled { opacity: .5; cursor: not-allowed; }
    .cmp-bar-clear {
      padding: 9px 16px;
      background: transparent; color: rgba(255,255,255,.7);
      border: 1.5px solid rgba(255,255,255,.3); border-radius: 8px;
      font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit;
    }
    .cmp-bar-clear:hover { color: #fff; border-color: #fff; }

    /* Compare button on shop card */
    .shop-card-compare {
      display: none;
      width: 100%;
      align-items: center;
      justify-content: center;
      gap: 6px;
      padding: 8px 12px;
      background: #f1f3f8;
      color: var(--navy);
      border: none;
      border-radius: 9px;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      font-family: inherit;
      margin-top: 4px;
      transition: background .15s, color .15s;
    }
    .shop-card-compare [data-lucide] { width: 14px; height: 14px; }
    .shop-card-compare:hover { background: #e0e7ff; color: #3730a3; }
    .shop-card:hover .shop-card-compare { display: flex; }
    .shop-card-compare.in-compare { display: flex; background: #e0e7ff; color: #3730a3; }
  `;
  document.head.appendChild(style);

  /* ── Render bar ── */
  function renderBar() {
    const list = getCompare();
    const countEl = document.getElementById('cmpCount');
    const slotsEl = document.getElementById('cmpSlots');
    const goBtn   = document.getElementById('cmpGoBtn');

    if (countEl) countEl.textContent = list.length;
    if (goBtn)   goBtn.disabled = list.length < 2;

    if (slotsEl) {
      slotsEl.innerHTML = list.map(p => `
        <div class="cmp-slot">
          <img src="${p.image || 'https://placehold.co/32x32/f3f4f6/aaa?text=?'}"
               alt="${p.model}" onerror="this.src='https://placehold.co/32x32/f3f4f6/aaa?text=?'"/>
          <span class="cmp-slot-name">${p.model}</span>
          <button class="cmp-slot-remove" onclick="toggleCompare(${p.id},'','')">✕</button>
        </div>`).join('');
    }

    bar.classList.toggle('open', list.length > 0);

    // Update all compare buttons on the page
    document.querySelectorAll('.shop-card-compare').forEach(btn => {
      const pid = parseInt(btn.dataset.productId);
      const inList = list.some(p => p.id === pid);
      btn.classList.toggle('in-compare', inList);
      btn.innerHTML = inList
        ? '<i data-lucide="check"></i> Added'
        : '<i data-lucide="git-compare"></i> Compare';
      if (window.lucide) lucide.createIcons(btn);
    });
  }

  /* ── Toggle product in compare list ── */
  window.toggleCompare = function (id, model, image) {
    let list = getCompare();
    const idx = list.findIndex(p => p.id === id);

    if (idx > -1) {
      list.splice(idx, 1);
      showToast(`${model || 'Product'} removed from compare.`);
    } else {
      if (list.length >= MAX_COMPARE) {
        showToast(`You can compare up to ${MAX_COMPARE} products at a time.`);
        return;
      }
      list.push({ id, model, image: image || null });
      showToast(`${model || 'Product'} added to compare.`);
    }

    saveCompare(list);
    renderBar();
  };

  /* ── Compare Now button ── */
  document.getElementById('cmpGoBtn')?.addEventListener('click', () => {
    const ids = getCompare().map(p => p.id).join(',');
    if (ids) window.location.href = `compare.php?ids=${ids}`;
  });

  /* ── Clear button ── */
  document.getElementById('cmpClearBtn')?.addEventListener('click', () => {
    saveCompare([]);
    renderBar();
  });

  /* ── Init on load ── */
  renderBar();

})();
