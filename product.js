/* ═══════════════════════════════════════════════════════════════════════
   product.js — Gadget Genesis product page interactions
   ═══════════════════════════════════════════════════════════════════════ */

/* ── Helpers ─────────────────────────────────────────────────────────── */
function showToast(msg, duration = 2800) {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), duration);
}

function formatPrice(n) {
  return 'UGX ' + Number(n).toLocaleString();
}

/* ── Image Gallery ───────────────────────────────────────────────────── */
(function () {
  const mainImg = document.getElementById('mainImg');
  const thumbs  = document.querySelectorAll('.gthumb');
  if (!mainImg || !thumbs.length) return;

  function setActive(thumb) {
    thumbs.forEach(t => t.classList.remove('active'));
    thumb.classList.add('active');

    // Fade transition
    mainImg.style.opacity = '0';
    setTimeout(() => {
      mainImg.src = thumb.dataset.img;
      mainImg.style.opacity = '1';
    }, 200);
  }

  thumbs.forEach(thumb => {
    thumb.addEventListener('click', () => setActive(thumb));
  });

  mainImg.style.transition = 'opacity .2s';
})();

/* ── Storage Selector ────────────────────────────────────────────────── */
(function () {
  const btns          = document.querySelectorAll('.stor-btn');
  const priceDisplay  = document.getElementById('prodPrice');
  const selectedLabel = document.getElementById('selectedStorage');
  if (!btns.length) return;

  btns.forEach(btn => {
    btn.addEventListener('click', () => {
      btns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      if (priceDisplay)  priceDisplay.textContent  = formatPrice(btn.dataset.price);
      if (selectedLabel) selectedLabel.textContent  = btn.dataset.label;
    });
  });
})();

/* ── Color Selector ──────────────────────────────────────────────────── */
(function () {
  const opts          = document.querySelectorAll('.col-opt');
  const thumbs        = document.querySelectorAll('.gthumb');
  const mainImg       = document.getElementById('mainImg');
  const selectedColor = document.getElementById('selectedColor');
  if (!opts.length) return;

  function syncGallery(index) {
    thumbs.forEach((t, i) => t.classList.toggle('active', i === index));
    if (mainImg && thumbs[index]) {
      mainImg.style.opacity = '0';
      setTimeout(() => {
        mainImg.src = thumbs[index].dataset.img;
        mainImg.style.opacity = '1';
      }, 200);
    }
  }

  opts.forEach(opt => {
    opt.addEventListener('click', () => {
      opts.forEach(o => o.classList.remove('active'));
      opt.classList.add('active');

      if (selectedColor) selectedColor.textContent = opt.dataset.name;
      syncGallery(+opt.dataset.index);
    });
  });
})();

/* ── Quantity ────────────────────────────────────────────────────────── */
(function () {
  const minus  = document.getElementById('qtyMinus');
  const plus   = document.getElementById('qtyPlus');
  const valEl  = document.getElementById('qtyVal');
  if (!minus || !plus || !valEl) return;

  let qty = 1;

  function update() {
    valEl.textContent = qty;
    minus.style.opacity = qty <= 1 ? '.4' : '1';
  }

  minus.addEventListener('click', () => { if (qty > 1) { qty--; update(); } });
  plus.addEventListener('click',  () => { if (qty < 99) { qty++; update(); } });

  update();
})();

/* ── Add to Cart ─────────────────────────────────────────────────────── */
(function () {
  const btn = document.getElementById('addCartBtn');
  if (!btn) return;

  btn.addEventListener('click', () => {
    const storage = document.querySelector('.stor-btn.active');
    const color   = document.querySelector('.col-opt.active');
    const qty     = document.getElementById('qtyVal');
    const name    = document.querySelector('.prod-name');

    const item = {
      model:   name   ? name.textContent : 'Product',
      storage: storage ? storage.dataset.label : '',
      price:   storage ? +storage.dataset.price : 0,
      color:   color   ? color.dataset.name : '',
      qty:     qty     ? +qty.textContent : 1,
    };

    // Save to localStorage cart
    const cart = JSON.parse(localStorage.getItem('gg_cart') || '[]');
    const existing = cart.findIndex(
      c => c.model === item.model && c.storage === item.storage && c.color === item.color
    );

    if (existing > -1) {
      cart[existing].qty += item.qty;
    } else {
      cart.push(item);
    }

    localStorage.setItem('gg_cart', JSON.stringify(cart));

    // Update badge
    updateCartBadge();

    showToast(`${item.model} (${item.storage}, ${item.color}) added to cart!`);

    // Button feedback
    btn.style.background = '#16a34a';
    btn.textContent = '✓ Added to Cart';
    setTimeout(() => {
      btn.style.background = '';
      btn.innerHTML = '<i data-lucide="shopping-cart"></i> Add to Cart';
      if (window.lucide) lucide.createIcons();
    }, 2000);
  });
})();

/* ── Buy Now ─────────────────────────────────────────────────────────── */
(function () {
  const btn = document.querySelector('.btn-buy-now');
  if (!btn) return;

  btn.addEventListener('click', () => {
    // Add item to cart first (same selection as Add to Cart)
    const storage = document.querySelector('.stor-btn.active');
    const color   = document.querySelector('.col-opt.active');
    const name    = document.querySelector('.prod-name');
    const img     = document.getElementById('mainImg');

    const item = {
      model:   name    ? name.textContent.trim() : 'Product',
      storage: storage ? storage.dataset.label   : '',
      price:   storage ? +storage.dataset.price  : 0,
      color:   color   ? color.dataset.name       : '',
      image:   img     ? img.src                  : null,
      qty:     1,
    };

    if (!item.price) { showToast('Please select a storage option first.'); return; }

    const cart     = JSON.parse(localStorage.getItem('gg_cart') || '[]');
    const existing = cart.findIndex(
      c => c.model === item.model && c.storage === item.storage && c.color === item.color
    );
    if (existing > -1) { cart[existing].qty += 1; }
    else               { cart.push(item); }
    localStorage.setItem('gg_cart', JSON.stringify(cart));
    updateCartBadge();

    // Redirect to checkout
    window.location.href = 'checkout.php';
  });
})();

/* ── Wishlist ────────────────────────────────────────────────────────── */
(function () {
  const btn  = document.getElementById('wishBtn');
  const icon = document.getElementById('wishIcon');
  if (!btn) return;

  // Get product data from the page
  const productId    = parseInt(new URLSearchParams(window.location.search).get('id'), 10);
  const productModel = document.querySelector('.prod-name')?.textContent.trim() || '';
  const productPrice = parseFloat(document.getElementById('prodPrice')?.textContent.replace(/[^0-9.]/g, '') || '0');
  const productImage = document.getElementById('mainImg')?.src || null;

  function applyState(wished) {
    btn.classList.toggle('active', wished);
    if (icon) {
      icon.style.fill  = wished ? 'var(--red)' : 'none';
      icon.style.color = wished ? 'var(--red)' : '';
    }
  }

  // Restore persisted state
  if (productId) applyState(isWished(productId));

  btn.addEventListener('click', () => {
    if (!productId) return;
    const added = toggleWishItem(productId, productModel, productPrice, productImage);
    applyState(added);
    showToast(added ? 'Added to wishlist' : 'Removed from wishlist');
  });
})();

/* ── Product Tabs ────────────────────────────────────────────────────── */
(function () {
  const tabs     = document.querySelectorAll('.ptab');
  const contents = document.querySelectorAll('.ptab-content');
  if (!tabs.length) return;

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      contents.forEach(c => c.classList.remove('active'));

      tab.classList.add('active');
      const target = document.getElementById('tab-' + tab.dataset.tab);
      if (target) target.classList.add('active');
    });
  });
})();

/* ── Related Carousel ────────────────────────────────────────────────── */
(function () {
  const track = document.getElementById('relTrack');
  const prev  = document.getElementById('relPrev');
  const next  = document.getElementById('relNext');
  if (!track || !prev || !next) return;

  let offset = 0;

  function cardWidth() {
    const card = track.querySelector('.rel-card');
    return card ? card.offsetWidth + 14 : 214;
  }

  function maxOffset() {
    return Math.max(0, track.scrollWidth - track.parentElement.offsetWidth);
  }

  function update() {
    track.style.transform = `translateX(-${offset}px)`;
    prev.style.opacity = offset <= 0          ? '.4' : '1';
    next.style.opacity = offset >= maxOffset() ? '.4' : '1';
  }

  next.addEventListener('click', () => {
    offset = Math.min(offset + cardWidth() * 2, maxOffset());
    update();
  });

  prev.addEventListener('click', () => {
    offset = Math.max(offset - cardWidth() * 2, 0);
    update();
  });

  // Touch swipe
  let tx = 0;
  track.addEventListener('touchstart', e => { tx = e.touches[0].clientX; }, { passive: true });
  track.addEventListener('touchend', e => {
    const diff = tx - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 30) {
      offset = diff > 0
        ? Math.min(offset + cardWidth(), maxOffset())
        : Math.max(offset - cardWidth(), 0);
      update();
    }
  });

  update();
})();

/* ── Cart Badge Sync ─────────────────────────────────────────────────── */
function updateCartBadge() {
  const cart   = JSON.parse(localStorage.getItem('gg_cart') || '[]');
  const total  = cart.reduce((sum, item) => sum + item.qty, 0);
  const badges = document.querySelectorAll('.cbadge');
  badges.forEach(b => {
    b.textContent = total > 0 ? total : '';
    b.style.display = total > 0 ? 'flex' : 'none';
  });
}

// Run on load
updateCartBadge();

/* ── Sticky header shadow on scroll ──────────────────────────────────── */
(function () {
  const header = document.querySelector('.header');
  if (!header) return;
  window.addEventListener('scroll', () => {
    header.style.boxShadow = scrollY > 8
      ? '0 2px 14px rgba(0,0,0,.1)'
      : '0 1px 3px rgba(0,0,0,.06)';
  }, { passive: true });
})();
