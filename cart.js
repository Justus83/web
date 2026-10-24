/* ═══════════════════════════════════════════════════════════════════════
   cart.js — powers cart.php, checkout.php, and order-confirm.php
   ═══════════════════════════════════════════════════════════════════════ */

'use strict';

/* ── Storage helpers ─────────────────────────────────────────────────── */
const CART_KEY  = 'gg_cart';
const ORDER_KEY = 'gg_last_order';
const WISH_KEY  = 'gg_wishlist';

function getCart()        { return JSON.parse(localStorage.getItem(CART_KEY)  || '[]'); }
function saveCart(cart)   { localStorage.setItem(CART_KEY, JSON.stringify(cart)); }
function getOrder()       { return JSON.parse(sessionStorage.getItem(ORDER_KEY) || 'null'); }
function saveOrder(order) { sessionStorage.setItem(ORDER_KEY, JSON.stringify(order)); }

/* ── Wishlist helpers (shared across all pages) ──────────────────────── */
function getWishlist()          { return JSON.parse(localStorage.getItem(WISH_KEY) || '[]'); }
function saveWishlist(list)     { localStorage.setItem(WISH_KEY, JSON.stringify(list)); }
function isWished(id)           { return getWishlist().some(w => w.id === id); }

function toggleWishItem(id, model, price, image) {
  const list    = getWishlist();
  const idx     = list.findIndex(w => w.id === id);
  const added   = idx === -1;
  if (added) { list.push({ id, model, price, image }); }
  else        { list.splice(idx, 1); }
  saveWishlist(list);
  return added; // true = just added, false = just removed
}

/* ── Formatting ──────────────────────────────────────────────────────── */
function fmt(n) {
  return 'UGX ' + Number(n).toLocaleString();
}

function payLabel(val) {
  return { momo: 'MTN Mobile Money', airtel: 'Airtel Money', card: 'Card', cod: 'Cash on Delivery' }[val] || val;
}

function shipLabel(val) {
  return { standard: 'Standard (3–5 days)', express: 'Express (1–2 days)', sameday: 'Same Day' }[val] || val;
}

function shipCost(val) {
  return { standard: 0, express: 15000, sameday: 25000 }[val] ?? 0;
}

/* ── Toast ───────────────────────────────────────────────────────────── */
function showToast(msg, duration = 2800) {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), duration);
}

/* ── Cart badge (all pages) ──────────────────────────────────────────── */
function updateBadge() {
  const total = getCart().reduce((s, i) => s + i.qty, 0);
  document.querySelectorAll('.cbadge').forEach(b => {
    b.textContent   = total > 0 ? total : '';
    b.style.display = total > 0 ? 'flex' : 'none';
  });
}

/* ══════════════════════════════════════════════════════════════════════
   CART PAGE
══════════════════════════════════════════════════════════════════════ */
function initCart() {
  if (!document.getElementById('cartItemsList')) return;

  const COUPONS = {}; // coupons now validated server-side via api/apply-coupon.php
  let   appliedDiscount = 0;
  let   currentShipping = 0;

  /* ── Render ─────────────────────────────────────────────────────── */
  function render() {
    const cart          = getCart();
    const listEl        = document.getElementById('cartItemsList');
    const emptyEl       = document.getElementById('cartEmpty');
    const itemsEl       = document.getElementById('cartItems');
    const summaryEl     = document.getElementById('cartSummary');
    const suggestionsEl = document.getElementById('cartSuggestions');
    const countEl       = document.getElementById('cartItemCount');

    if (!listEl) return;

    if (cart.length === 0) {
      emptyEl.style.display   = 'block';
      itemsEl.style.display   = 'none';
      summaryEl.style.display = 'none';
      suggestionsEl.style.display = 'block';
      if (countEl) countEl.textContent = '0 items';
      return;
    }

    emptyEl.style.display   = 'none';
    itemsEl.style.display   = 'block';
    summaryEl.style.display = 'block';
    suggestionsEl.style.display = 'block';

    // Load suggestions from DB
    const suggList = document.getElementById('suggestionsList');
    if (suggList && !suggList.children.length) {
      fetch('api/suggestions.php')
        .then(r => r.json())
        .then(data => {
          suggList.innerHTML = data.map(p => {
            const img   = p.image || 'https://placehold.co/200x140/f3f4f6/aaa?text=' + encodeURIComponent(p.model);
            const price = p.price ? fmt(p.price) : '—';
            return `<a href="product.php?id=${p.id}" class="suggestion-card">
              <img src="${img}" alt="${p.model}"/>
              <p>${p.model}</p>
              <span>${price}</span>
            </a>`;
          }).join('');
        })
        .catch(() => {});
    }

    const totalItems = cart.reduce((s, i) => s + i.qty, 0);
    if (countEl) countEl.textContent = `${totalItems} item${totalItems !== 1 ? 's' : ''}`;

    // Build rows
    listEl.innerHTML = cart.map((item, idx) => `
      <div class="cart-item" data-idx="${idx}">
        <div class="cart-item-product">
          <div class="cart-item-img">
            <img src="${item.image || 'https://images.unsplash.com/photo-1695048133142-1a20484d2569?w=150&q=70'}"
                 alt="${item.model}" onerror="this.src='https://placehold.co/72x72/f3f4f6/aaa?text=?'"/>
          </div>
          <div class="cart-item-details">
            <p class="cart-item-name">${item.model}</p>
            <div class="cart-item-meta">
              ${item.storage ? `<span>${item.storage}</span>` : ''}
              ${item.color   ? `<span>${item.color}</span>`   : ''}
            </div>
          </div>
        </div>
        <div class="cart-item-price">${fmt(item.price)}</div>
        <div class="cart-qty">
          <button class="cart-qty-btn" onclick="cartQty(${idx}, -1)" ${item.qty <= 1 ? 'style="opacity:.4"' : ''}>
            <i data-lucide="minus"></i>
          </button>
          <span class="cart-qty-val">${item.qty}</span>
          <button class="cart-qty-btn" onclick="cartQty(${idx}, 1)">
            <i data-lucide="plus"></i>
          </button>
        </div>
        <div class="cart-item-total">${fmt(item.price * item.qty)}</div>
        <button class="cart-item-remove" onclick="cartRemove(${idx})" title="Remove">
          <i data-lucide="x"></i>
        </button>
      </div>
    `).join('');

    if (window.lucide) lucide.createIcons();
    updateSummary();
  }
  function updateSummary() {
    const cart     = getCart();
    const subtotal = cart.reduce((s, i) => s + i.price * i.qty, 0);
    const totalQty = cart.reduce((s, i) => s + i.qty, 0);
    const discount = Math.round(subtotal * appliedDiscount / 100);
    const shipping = subtotal >= 200000 ? 0 : currentShipping;
    const total    = subtotal - discount + shipping;

    const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };

    set('summaryCount',    totalQty);
    set('summarySubtotal', fmt(subtotal));
    set('summaryShipping', shipping === 0 ? 'Free' : fmt(shipping));
    set('summaryTotal',    fmt(total));

    const shipEl = document.getElementById('summaryShipping');
    if (shipEl) shipEl.className = shipping === 0 ? 'free-ship' : '';

    const discLine = document.getElementById('discountLine');
    if (discLine) {
      discLine.style.display = discount > 0 ? 'flex' : 'none';
      set('summaryDiscount', `-${fmt(discount)}`);
    }
  }

  /* ── Qty change ──────────────────────────────────────────────────── */
  window.cartQty = function (idx, delta) {
    const cart = getCart();
    if (!cart[idx]) return;
    cart[idx].qty = Math.max(1, Math.min(99, cart[idx].qty + delta));
    saveCart(cart);
    updateBadge();
    render();
  };

  /* ── Remove item ─────────────────────────────────────────────────── */
  window.cartRemove = function (idx) {
    const cart = getCart();
    const name = cart[idx]?.model || 'Item';
    cart.splice(idx, 1);
    saveCart(cart);
    updateBadge();
    render();
    showToast(`${name} removed from cart.`);
  };

  /* ── Clear cart ──────────────────────────────────────────────────── */
  const clearBtn = document.getElementById('clearCartBtn');
  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      if (!confirm('Remove all items from your cart?')) return;
      saveCart([]);
      updateBadge();
      render();
      showToast('Cart cleared.');
    });
  }

  /* ── Coupon ──────────────────────────────────────────────────────── */
  const applyBtn = document.getElementById('applyCouponBtn');
  if (applyBtn) {
    applyBtn.addEventListener('click', async () => {
      const code  = (document.getElementById('couponInput')?.value || '').trim().toUpperCase();
      const msgEl = document.getElementById('couponMsg');
      if (!code) return;

      applyBtn.disabled = true;
      applyBtn.textContent = '…';

      try {
        const res  = await fetch('api/apply-coupon.php', {
          method:  'POST',
          headers: { 'Content-Type': 'application/json' },
          body:    JSON.stringify({ code }),
        });
        const data = await res.json();

        if (data.success) {
          appliedDiscount = data.discount_pct;
          sessionStorage.setItem('gg_discount',      appliedDiscount);
          sessionStorage.setItem('gg_discount_code', data.code);
          if (msgEl) { msgEl.textContent = `✓ ${appliedDiscount}% discount applied!`; msgEl.className = 'coupon-msg success'; }
          updateSummary();
          showToast(`Coupon applied — ${appliedDiscount}% off!`);
        } else {
          appliedDiscount = 0;
          sessionStorage.removeItem('gg_discount');
          sessionStorage.removeItem('gg_discount_code');
          if (msgEl) { msgEl.textContent = data.error || 'Invalid coupon code.'; msgEl.className = 'coupon-msg error'; }
          updateSummary();
        }
      } catch {
        if (msgEl) { msgEl.textContent = 'Could not check coupon. Try again.'; msgEl.className = 'coupon-msg error'; }
      } finally {
        applyBtn.disabled = false;
        applyBtn.textContent = 'Apply';
      }
    });
  }

  // Allow Enter key on coupon input
  document.getElementById('couponInput')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') document.getElementById('applyCouponBtn')?.click();
  });

  /* ── Checkout btn guard ──────────────────────────────────────────── */
  const checkoutBtn = document.getElementById('checkoutBtn');
  if (checkoutBtn) {
    checkoutBtn.addEventListener('click', e => {
      if (getCart().length === 0) {
        e.preventDefault();
        showToast('Your cart is empty.');
      }
    });
  }

  render();
}

/* ══════════════════════════════════════════════════════════════════════
   CHECKOUT PAGE
══════════════════════════════════════════════════════════════════════ */
function initCheckout() {
  if (!document.getElementById('checkoutForm')) return;

  const cart = getCart();
  if (cart.length === 0) {
    window.location.href = 'cart.php';
    return;
  }

  let shippingCost = 0;

  /* ── Populate order summary ──────────────────────────────────────── */
  function renderSummary() {
    const itemsEl = document.getElementById('coSummaryItems');
    if (itemsEl) {
      itemsEl.innerHTML = cart.map(item => `
        <div class="co-summary-item">
          <div class="co-sum-img">
            <img src="${item.image || 'https://images.unsplash.com/photo-1695048133142-1a20484d2569?w=100&q=70'}"
                 alt="${item.model}" onerror="this.src='https://placehold.co/52x52/f3f4f6/aaa?text=?'"/>
          </div>
          <div class="co-sum-info">
            <p class="co-sum-name">${item.model}</p>
            <p class="co-sum-meta">${[item.storage, item.color, `Qty: ${item.qty}`].filter(Boolean).join(' · ')}</p>
          </div>
          <span class="co-sum-price">${fmt(item.price * item.qty)}</span>
        </div>
      `).join('');
      if (window.lucide) lucide.createIcons();
    }
    updateCoTotals();
  }

  function updateCoTotals() {
    const subtotal = cart.reduce((s, i) => s + i.price * i.qty, 0);
    const discount = +sessionStorage.getItem('gg_discount') || 0;
    const disc     = Math.round(subtotal * discount / 100);
    const shipping = subtotal >= 200000 ? 0 : shippingCost;
    const total    = subtotal - disc + shipping;

    const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
    set('coSubtotal', fmt(subtotal));
    set('coShipping', shipping === 0 ? 'Free' : fmt(shipping));
    set('coTotal',    fmt(total));

    const shipEl = document.getElementById('coShipping');
    if (shipEl) shipEl.className = shipping === 0 ? 'free-ship' : '';

    const discLine = document.getElementById('coDiscountLine');
    if (discLine) {
      discLine.style.display = disc > 0 ? 'flex' : 'none';
      set('coDiscount', `-${fmt(disc)}`);
    }
  }

  /* ── Shipping method switch ──────────────────────────────────────── */
  document.querySelectorAll('input[name="shipping"]').forEach(radio => {
    radio.addEventListener('change', () => {
      document.querySelectorAll('.ship-option').forEach(o => o.classList.remove('active'));
      radio.closest('.ship-option')?.classList.add('active');
      shippingCost = shipCost(radio.value);
      updateCoTotals();
    });
  });

  /* ── Payment method switch ───────────────────────────────────────── */
  document.querySelectorAll('input[name="payment"]').forEach(radio => {
    radio.addEventListener('change', () => {
      document.querySelectorAll('.pay-option').forEach(o => o.classList.remove('active'));
      radio.closest('.pay-option')?.classList.add('active');

      // Show/hide extra fields
      document.getElementById('momoField')?.style.setProperty('display',   radio.value === 'momo'   ? 'block' : 'none');
      document.getElementById('airtelField')?.style.setProperty('display', radio.value === 'airtel' ? 'block' : 'none');
      document.getElementById('cardFields')?.style.setProperty('display',  radio.value === 'card'   ? 'block' : 'none');
    });
  });

  /* ── Card number formatting ──────────────────────────────────────── */
  document.getElementById('cardNumber')?.addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').replace(/(.{4})/g, '$1 ').trim().slice(0, 19);
  });
  document.getElementById('cardExpiry')?.addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').replace(/^(\d{2})(\d)/, '$1/$2').slice(0, 5);
  });

  /* ── Form validation & submit ────────────────────────────────────── */
  document.getElementById('checkoutForm').addEventListener('submit', function (e) {
    e.preventDefault();

    const required = ['firstName', 'lastName', 'email', 'phone', 'address', 'city', 'country'];
    let   valid    = true;

    required.forEach(id => {
      const el = document.getElementById(id);
      if (!el) return;
      if (!el.value.trim()) {
        el.classList.add('error');
        valid = false;
      } else {
        el.classList.remove('error');
      }
    });

    if (!valid) { showToast('Please fill in all required fields.'); return; }

    // Validate payment-specific fields
    const payMethod = document.querySelector('input[name="payment"]:checked')?.value;
    if (payMethod === 'momo' && !document.getElementById('momoNumber')?.value.trim()) {
      showToast('Please enter your MTN MoMo number.'); return;
    }
    if (payMethod === 'airtel' && !document.getElementById('airtelNumber')?.value.trim()) {
      showToast('Please enter your Airtel Money number.'); return;
    }
    if (payMethod === 'card') {
      if (!document.getElementById('cardNumber')?.value.trim() ||
          !document.getElementById('cardExpiry')?.value.trim() ||
          !document.getElementById('cardCVV')?.value.trim()) {
        showToast('Please complete your card details.'); return;
      }
    }

    // Build order
    const subtotal     = cart.reduce((s, i) => s + i.price * i.qty, 0);
    const shipMethod   = document.querySelector('input[name="shipping"]:checked')?.value || 'standard';
    const shipping     = subtotal >= 200000 ? 0 : shipCost(shipMethod);
    const discount     = +sessionStorage.getItem('gg_discount') || 0;
    const disc         = Math.round(subtotal * discount / 100);
    const total        = subtotal - disc + shipping;
    const orderNum     = 'GG-' + Date.now().toString().slice(-6);

    const deliveryDays = { standard: '3–5 business days', express: '1–2 business days', sameday: 'Today' };

    const order = {
      number:    orderNum,
      date:      new Date().toLocaleDateString('en-UG', { day: 'numeric', month: 'long', year: 'numeric' }),
      payment:   payLabel(payMethod),
      shipping:  shipLabel(shipMethod),
      delivery:  deliveryDays[shipMethod] || '3–5 business days',
      total:     total,
      subtotal:  subtotal,
      items:     cart,
      name:      `${document.getElementById('firstName').value} ${document.getElementById('lastName').value}`,
      address:   document.getElementById('address').value,
      city:      document.getElementById('city').value,
      phone:     document.getElementById('phone').value,
    };

    saveOrder(order);

    // Read coupon data BEFORE clearing sessionStorage
    const couponCode    = sessionStorage.getItem('gg_discount_code') || null;
    const discountPct   = +sessionStorage.getItem('gg_discount') || 0;
    const discountAmt   = Math.round(subtotal * discountPct / 100);

    // Clear cart
    saveCart([]);
    updateBadge();
    sessionStorage.removeItem('gg_discount');
    sessionStorage.removeItem('gg_discount_code');

    // Button feedback
    const btn = document.getElementById('placeOrderBtn');
    if (btn) { btn.textContent = 'Processing…'; btn.disabled = true; btn.style.background = '#16a34a'; }

    // Save order to DB then redirect
    fetch('api/save-order.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({
        name:          order.name,
        phone:         document.getElementById('phone')?.value?.trim()    || order.phone,
        email:         document.getElementById('email')?.value?.trim()    || '',
        address:       order.address,
        city:          order.city,
        country:       document.getElementById('country')?.value          || 'UG',
        district:      document.getElementById('district')?.value?.trim() || '',
        notes:         document.getElementById('notes')?.value?.trim()    || '',
        payment:       order.payment,
        shipping:      order.shipping,
        subtotal:      order.subtotal,
        shipping_cost: shipCost(document.querySelector('input[name="shipping"]:checked')?.value || 'standard'),
        discount:      discountAmt,
        total:         order.total,
        items:         order.items,
        customer_id:   document.getElementById('customerId')?.value || null,
        coupon_code:   couponCode,
      }),
    })
    .then(r => r.json())
    .then(data => {
      if (data.order_id) {
        // Update the in-memory order with the real server-assigned number
        order.number = data.order_number;
        saveOrder(order);
        sessionStorage.setItem('gg_last_order_id', data.order_id);
      }
    })
    .catch(() => { /* non-fatal — confirm page falls back to sessionStorage */ })
    .finally(() => {
      window.location.href = 'order-confirm.php';
    });
  });

  renderSummary();
}

/* ══════════════════════════════════════════════════════════════════════
   ORDER CONFIRM PAGE
══════════════════════════════════════════════════════════════════════ */
function initConfirm() {
  if (!document.getElementById('orderNumber')) return;

  // If PHP already rendered the page from DB (via ?id=), don't touch the DOM
  // and don't redirect — the inline <script> on the page handles everything.
  if (typeof PAGE_LOADED_FROM_DB !== 'undefined' && PAGE_LOADED_FROM_DB) return;

  const order = getOrder();

  if (!order) {
    // No order data — redirect to home
    window.location.href = 'index.php';
    return;
  }

  const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };

  set('orderNumber',  order.number);
  set('orderDate',    order.date);
  set('orderPayment', order.payment);
  set('orderDelivery',order.delivery);
  set('orderTotal',   fmt(order.total));
  set('shipName',     order.name);
  set('shipAddress',  order.address);
  set('shipCity',     order.city);
  set('shipPhone',    order.phone);

  // Items
  const itemsEl = document.getElementById('confirmItems');
  if (itemsEl && order.items) {
    itemsEl.innerHTML = order.items.map(item => `
      <div class="confirm-item">
        <div class="confirm-item-img">
          <img src="${item.image || 'https://images.unsplash.com/photo-1695048133142-1a20484d2569?w=100&q=70'}"
               alt="${item.model}" onerror="this.src='https://placehold.co/60x60/f3f4f6/aaa?text=?'"/>
        </div>
        <div class="confirm-item-info">
          <p class="confirm-item-name">${item.model}</p>
          <p class="confirm-item-meta">${[item.storage, item.color, `Qty: ${item.qty}`].filter(Boolean).join(' · ')}</p>
        </div>
        <span class="confirm-item-price">${fmt(item.price * item.qty)}</span>
      </div>
    `).join('');
    if (window.lucide) lucide.createIcons();
  }
}

/* ══════════════════════════════════════════════════════════════════════
   GLOBAL — runs on every page that loads cart.js
══════════════════════════════════════════════════════════════════════ */
window.addToCart = function (id, model, price, image, storage, color) {
  const cart     = getCart();
  const existing = cart.findIndex(c => c.id === id && c.storage === (storage||'') && c.color === (color||''));

  if (existing > -1) {
    cart[existing].qty += 1;
  } else {
    cart.push({ id, model, price, image: image || null, storage: storage || '', color: color || '', qty: 1 });
  }

  saveCart(cart);
  updateBadge();
  showToast(`${model} added to cart!`);
};

/* ── Newsletter signup ───────────────────────────────────────────────── */
(function () {
  const form = document.getElementById('newsletterForm');
  if (!form) return;

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    const email = document.getElementById('newsletterEmail')?.value.trim();
    const msgEl = document.getElementById('newsletterMsg');
    if (!email || !email.includes('@')) {
      if (msgEl) { msgEl.textContent = 'Please enter a valid email.'; msgEl.style.color = '#fca5a5'; msgEl.style.display = 'block'; }
      return;
    }

    const btn = form.querySelector('button[type="submit"]');
    if (btn) { btn.disabled = true; btn.textContent = '…'; }

    try {
      const res  = await fetch('api/newsletter.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email }),
      });
      const data = await res.json();
      if (msgEl) {
        msgEl.textContent = data.message || (data.error || 'Something went wrong.');
        msgEl.style.color = data.success ? '#86efac' : '#fca5a5';
        msgEl.style.display = 'block';
      }
      if (data.success) document.getElementById('newsletterEmail').value = '';
    } catch {
      if (msgEl) { msgEl.textContent = 'Network error. Try again.'; msgEl.style.color = '#fca5a5'; msgEl.style.display = 'block'; }
    } finally {
      if (btn) { btn.disabled = false; btn.textContent = 'Subscribe'; }
    }
  });
})();

/* ── Live Search Overlay ─────────────────────────────────────────────── */
(function () {
  const toggleBtn = document.getElementById('searchToggle');
  if (!toggleBtn) return;

  /* ── Inject overlay HTML once ── */
  const overlay = document.createElement('div');
  overlay.id = 'searchOverlay';
  overlay.innerHTML = `
    <div class="srch-box">
      <div class="srch-input-wrap">
        <i data-lucide="search" class="srch-ico"></i>
        <input type="text" id="srchInput" placeholder="Search products…" autocomplete="off" aria-label="Search products"/>
        <button id="srchClose" aria-label="Close search"><i data-lucide="x"></i></button>
      </div>
      <div class="srch-results" id="srchResults"></div>
    </div>`;

  /* Inject styles */
  const style = document.createElement('style');
  style.textContent = `
    #searchOverlay {
      display: none; position: fixed; inset: 0; z-index: 9998;
      background: rgba(0,0,0,.45); backdrop-filter: blur(3px);
    }
    #searchOverlay.open { display: block; }
    .srch-box {
      position: absolute; top: 72px; left: 50%; transform: translateX(-50%);
      width: min(96vw, 640px);
      background: #fff; border-radius: 14px;
      box-shadow: 0 8px 40px rgba(0,0,0,.18);
      overflow: hidden;
    }
    .srch-input-wrap {
      display: flex; align-items: center; gap: 10px;
      padding: 14px 16px; border-bottom: 1px solid #f1f3f8;
    }
    .srch-ico { width: 20px; height: 20px; color: #9ca3af; flex-shrink: 0; }
    #srchInput {
      flex: 1; border: none; outline: none; font-size: 16px;
      font-family: inherit; color: #1e293b;
    }
    #srchClose {
      background: none; border: none; cursor: pointer; padding: 4px;
      color: #9ca3af; display: flex; align-items: center;
    }
    #srchClose:hover { color: #1e293b; }
    #srchClose [data-lucide] { width: 18px; height: 18px; }
    .srch-results { max-height: 420px; overflow-y: auto; }
    .srch-item {
      display: flex; align-items: center; gap: 12px;
      padding: 10px 16px; text-decoration: none; color: inherit;
      transition: background .12s;
    }
    .srch-item:hover { background: #f8faff; }
    .srch-item-img {
      width: 52px; height: 52px; border-radius: 8px; object-fit: cover;
      background: #f3f4f6; flex-shrink: 0;
    }
    .srch-item-info { flex: 1; min-width: 0; }
    .srch-item-name { font-size: 14px; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .srch-item-coll { font-size: 12px; color: #6b7280; margin-top: 2px; }
    .srch-item-price { font-size: 14px; font-weight: 700; color: #1e293b; white-space: nowrap; }
    .srch-footer {
      padding: 12px 16px; border-top: 1px solid #f1f3f8;
      text-align: center;
    }
    .srch-footer a {
      font-size: 13px; font-weight: 700; color: #2563eb; text-decoration: none;
    }
    .srch-footer a:hover { text-decoration: underline; }
    .srch-empty { padding: 28px 16px; text-align: center; color: #9ca3af; font-size: 14px; }
    .srch-loading { padding: 20px 16px; text-align: center; color: #9ca3af; font-size: 13px; }
  `;
  document.head.appendChild(style);
  document.body.appendChild(overlay);
  if (window.lucide) lucide.createIcons();

  const input    = document.getElementById('srchInput');
  const results  = document.getElementById('srchResults');
  const closeBtn = document.getElementById('srchClose');

  function openOverlay() {
    overlay.classList.add('open');
    input.focus();
    if (window.lucide) lucide.createIcons();
  }
  function closeOverlay() {
    overlay.classList.remove('open');
    input.value = '';
    results.innerHTML = '';
  }

  toggleBtn.addEventListener('click', () => {
    overlay.classList.contains('open') ? closeOverlay() : openOverlay();
  });
  closeBtn.addEventListener('click', closeOverlay);
  overlay.addEventListener('click', e => { if (e.target === overlay) closeOverlay(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeOverlay(); });

  /* ── Submit on Enter → full results page ── */
  input.addEventListener('keydown', e => {
    if (e.key === 'Enter' && input.value.trim()) {
      window.location.href = 'search.php?q=' + encodeURIComponent(input.value.trim());
    }
  });

  /* ── Live search ── */
  let timer;
  input.addEventListener('input', () => {
    clearTimeout(timer);
    const q = input.value.trim();
    if (!q) { results.innerHTML = ''; return; }

    results.innerHTML = '<div class="srch-loading">Searching…</div>';

    timer = setTimeout(async () => {
      try {
        const res  = await fetch('api/search.php?q=' + encodeURIComponent(q) + '&limit=6');
        const data = await res.json();

        if (!Array.isArray(data) || data.length === 0) {
          results.innerHTML = `<div class="srch-empty">No results for "<strong>${q}</strong>"</div>`;
          return;
        }

        const placeholder = 'https://placehold.co/52x52/f3f4f6/aaa?text=?';
        results.innerHTML = data.map(p => `
          <a href="product.php?id=${p.id}" class="srch-item">
            <img class="srch-item-img"
                 src="${p.image || placeholder}"
                 alt="${p.model}"
                 onerror="this.src='${placeholder}'"/>
            <div class="srch-item-info">
              <p class="srch-item-name">${p.model}</p>
              ${p.collection ? `<p class="srch-item-coll">${p.collection}</p>` : ''}
            </div>
            <span class="srch-item-price">${p.price_fmt || '—'}</span>
          </a>
        `).join('') + `
          <div class="srch-footer">
            <a href="search.php?q=${encodeURIComponent(q)}">View all results for "${q}" →</a>
          </div>`;

      } catch {
        results.innerHTML = '<div class="srch-empty">Search unavailable. Please try again.</div>';
      }
    }, 280);
  });
})();

/* ── Dark Mode ───────────────────────────────────────────────────────── */
(function () {
  const DARK_KEY = 'gg_dark_mode';

  function applyTheme(dark) {
    document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    const btn  = document.getElementById('darkToggle');
    const icon = btn?.querySelector('[data-lucide]');
    if (icon) {
      icon.setAttribute('data-lucide', dark ? 'sun' : 'moon');
      if (window.lucide) lucide.createIcons({ nodes: [btn] });
    }
    if (btn) btn.setAttribute('title', dark ? 'Switch to light mode' : 'Switch to dark mode');
  }

  // Apply saved preference immediately (before DOMContentLoaded to avoid flash)
  const saved = localStorage.getItem(DARK_KEY);
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  const isDark = saved !== null ? saved === '1' : prefersDark;
  document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');

  window.addEventListener('DOMContentLoaded', () => {
    applyTheme(isDark);

    document.getElementById('darkToggle')?.addEventListener('click', () => {
      const currentlyDark = document.documentElement.getAttribute('data-theme') === 'dark';
      const newDark = !currentlyDark;
      localStorage.setItem(DARK_KEY, newDark ? '1' : '0');
      applyTheme(newDark);
    });
  });
})();

/* ── Sticky header ───────────────────────────────────────────────────── */
(function () {
  const header = document.querySelector('.header');
  if (!header) return;
  window.addEventListener('scroll', () => {
    header.style.boxShadow = scrollY > 8
      ? '0 2px 14px rgba(0,0,0,.1)'
      : '0 1px 3px rgba(0,0,0,.06)';
  }, { passive: true });
})();

/* ── Recently Viewed Products ─────────────────────────────────────────── */
const RECENT_KEY = 'gg_recently_viewed';
const RECENT_MAX = 8;

function getRecent()        { return JSON.parse(localStorage.getItem(RECENT_KEY) || '[]'); }
function saveRecent(list)   { localStorage.setItem(RECENT_KEY, JSON.stringify(list)); }

function trackRecent(id, model, image, price) {
  if (!id) return;
  let list = getRecent().filter(p => p.id !== id);
  list.unshift({ id, model, image, price });
  list = list.slice(0, RECENT_MAX);
  saveRecent(list);
}

function renderRecent(containerId) {
  const el = document.getElementById(containerId);
  if (!el) return;

  const recent = getRecent();
  // Exclude current product if on a product page
  const currentId = parseInt(new URLSearchParams(window.location.search).get('id'), 10);
  const items = currentId ? recent.filter(p => p.id !== currentId) : recent;

  if (items.length === 0) { el.style.display = 'none'; return; }

  const placeholder = 'https://placehold.co/200x200/f3f4f6/aaa?text=?';
  el.innerHTML = items.map(p => `
    <a href="product.php?id=${p.id}" class="recent-card">
      <div class="recent-img">
        <img src="${p.image || placeholder}" alt="${p.model || ''}"
             onerror="this.src='${placeholder}'" loading="lazy"/>
      </div>
      <p class="recent-name">${p.model || ''}</p>
      ${p.price ? `<span class="recent-price">UGX ${Number(p.price).toLocaleString()}</span>` : ''}
    </a>
  `).join('');
  if (window.lucide) lucide.createIcons();
  el.parentElement.style.display = 'block';
}

/* ── Boot ────────────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
  updateBadge();
  initCart();
  initCheckout();
  initConfirm();
  renderRecent('recentStrip');
  if (window.lucide) lucide.createIcons();
});
