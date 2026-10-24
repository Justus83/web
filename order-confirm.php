<?php
/* ═══════════════════════════════════════════════════════════════════════
   order-confirm.php — Order confirmation page
   - When reached via ?id=X  → loads order from DB (persistent, shareable)
   - When reached fresh from checkout → JS populates from sessionStorage
     then immediately calls api/save-order.php and replaces the URL with ?id=X
   ═══════════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

$dbOrder    = null;
$dbItems    = [];
$loadFromDb = false;

if (!empty($_GET['id']) && is_numeric($_GET['id'])) {
    $orderId = (int) $_GET['id'];

    $stmt = $pdo->prepare("
        SELECT id, order_number, customer_name, customer_phone, customer_email,
               address, city, country, district, notes,
               payment_method, shipping_method,
               subtotal, shipping_cost, discount, total,
               status, tracking_number, created_at
        FROM orders
        WHERE id = :id
    ");
    $stmt->execute([':id' => $orderId]);
    $dbOrder = $stmt->fetch();

    if ($dbOrder) {
        $loadFromDb = true;
        $iStmt = $pdo->prepare("
            SELECT model, storage, color, image, price, qty
            FROM order_items
            WHERE order_id = :order_id
            ORDER BY id ASC
        ");
        $iStmt->execute([':order_id' => $orderId]);
        $dbItems = $iStmt->fetchAll();
    }
}

/* ── Status label helper ───────────────────────────────────────────── */
function statusLabel(string $s): string {
    return match($s) {
        'pending'    => 'Pending',
        'processing' => 'Processing',
        'shipped'    => 'Shipped',
        'delivered'  => 'Delivered',
        'cancelled'  => 'Cancelled',
        default      => ucfirst($s),
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Order Confirmed — Gadget Genesis</title>
  <link rel="stylesheet" href="style.css"/>
  <?php require_once __DIR__ . '/config/theme-init.php'; ?>
  <style>
    /* ── Print styles ── */
    @media print {
      .topbar, .header, .co-steps-bar, .confirm-actions,
      .whats-next, .follow-us, .no-print, footer { display: none !important; }
      body { background: #fff !important; }
      .confirm-wrap { padding: 0 !important; }
      .confirm-banner { background: #f0fdf4 !important; -webkit-print-color-adjust: exact; }
    }

    /* ── Estimated delivery banner ── */
    .delivery-banner {
      background: linear-gradient(120deg, #eef1f8, #e8f0fe);
      border: 1px solid #c7d7f5;
      border-radius: 12px;
      padding: 18px 24px;
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .delivery-banner-ico {
      width: 48px; height: 48px;
      background: var(--navy);
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .delivery-banner-ico [data-lucide] { width: 24px; height: 24px; color: #fff; }
    .delivery-banner-text strong { display: block; font-size: 16px; font-weight: 800; color: var(--navy); margin-bottom: 2px; }
    .delivery-banner-text span   { font-size: 13px; color: #3b5998; }

    /* ── Action buttons row ── */
    .confirm-actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .btn-whatsapp {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 12px 22px;
      background: #25d366; color: #fff;
      border: none; border-radius: 9px;
      font-size: 14px; font-weight: 700;
      cursor: pointer; font-family: inherit;
      transition: background .15s;
      text-decoration: none;
    }
    .btn-whatsapp:hover { background: #1ebe5d; }
    .btn-whatsapp [data-lucide] { width: 18px; height: 18px; }

    .btn-print {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 12px 22px;
      background: #fff; color: var(--navy);
      border: 1.5px solid var(--navy); border-radius: 9px;
      font-size: 14px; font-weight: 700;
      cursor: pointer; font-family: inherit;
      transition: background .15s, color .15s;
    }
    .btn-print:hover { background: var(--navy); color: #fff; }
    .btn-print [data-lucide] { width: 16px; height: 16px; }

    .btn-save-pdf {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 12px 22px;
      background: #fff; color: #16a34a;
      border: 1.5px solid #16a34a; border-radius: 9px;
      font-size: 14px; font-weight: 700;
      cursor: pointer; font-family: inherit;
      transition: background .15s, color .15s;
    }
    .btn-save-pdf:hover { background: #16a34a; color: #fff; }
    .btn-save-pdf [data-lucide] { width: 16px; height: 16px; }

    .btn-my-orders {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 12px 22px;
      background: #fff; color: var(--navy);
      border: 1.5px solid var(--navy); border-radius: 9px;
      font-size: 14px; font-weight: 700;
      text-decoration: none;
      transition: background .15s, color .15s;
    }
    .btn-my-orders:hover { background: var(--navy); color: #fff; }
    .btn-my-orders [data-lucide] { width: 16px; height: 16px; }

    /* ── Status pill ── */
    .status-pill {
      display: inline-block;
      padding: 4px 14px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 700;
    }
    .status-pending    { background: #fef3c7; color: #92400e; }
    .status-processing { background: #dbeafe; color: #1e40af; }
    .status-shipped    { background: #ede9fe; color: #5b21b6; }
    .status-delivered  { background: #dcfce7; color: #166534; }
    .status-cancelled  { background: #fee2e2; color: #991b1b; }

    /* ── Follow us section ── */
    .follow-us {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 28px 32px;
      text-align: center;
    }
    .follow-us h3 {
      font-size: 18px; font-weight: 800; color: var(--navy);
      margin-bottom: 6px;
    }
    .follow-us p {
      font-size: 14px; color: var(--gray);
      margin-bottom: 22px;
    }
    .social-links {
      display: flex;
      justify-content: center;
      gap: 12px;
      flex-wrap: wrap;
    }
    .social-link {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 10px 20px;
      border-radius: 9px;
      font-size: 14px; font-weight: 700;
      text-decoration: none;
      transition: transform .15s, box-shadow .15s;
    }
    .social-link:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.12); }
    .social-link svg { width: 18px; height: 18px; }

    .sl-instagram { background: linear-gradient(135deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888); color: #fff; }
    .sl-facebook  { background: #1877f2; color: #fff; }
    .sl-twitter   { background: #000; color: #fff; }
    .sl-snapchat  { background: #fffc00; color: #000; }
  </style>
</head>
<body>

<!-- ══ TOP BAR ══════════════════════════════════════════════════════════ -->
<div class="topbar no-print">
  <div class="topbar-left">
    <span class="topbar-item"><i data-lucide="shield-check" class="ti"></i><span>Secure Checkout</span><small>SSL encrypted</small></span>
  </div>
  <div class="topbar-center">Need help? Call: <a href="tel:0759809061" style="color:#fff;font-weight:700;">0759 809061</a> &nbsp;|&nbsp; Kooki Towers, Buganda Rd, Kampala</div>
  <div class="topbar-right">
    <a href="https://maps.google.com/?q=Kooki+Towers+Buganda+Road+Kampala" target="_blank"><i data-lucide="map-pin" class="ti"></i> Get Directions</a>
    <a href="https://wa.me/256759809061" target="_blank"><i data-lucide="message-circle" class="ti"></i> WhatsApp</a>
  </div>
</div>

<!-- ══ HEADER ════════════════════════════════════════════════════════════ -->
<header class="header no-print">
  <div class="hdr-inner">
    <a href="index.php" class="logo"><img src="logo.jpg" alt="Gadget Genesis" style="height:44px;width:auto;"/></a>
    <?php require_once __DIR__ . '/config/nav.php'; ?>
    <div class="hdr-actions">
      <button id="darkToggle" class="ibtn" title="Toggle dark mode" aria-label="Toggle dark mode"><i data-lucide="moon"></i></button>
      <button class="ibtn" id="searchToggle" aria-label="Search"><i data-lucide="search"></i></button>
      <a href="account.php" class="ibtn"><i data-lucide="user"></i></a>
      <a href="cart.php" class="ibtn cart">
        <i data-lucide="shopping-cart"></i><span class="cbadge">0</span>
      </a>
    </div>
  </div>
</header>

<!-- ══ STEPS ══════════════════════════════════════════════════════════════ -->
<div class="co-steps-bar no-print">
  <div class="co-steps">
    <div class="co-step done"><span class="co-step-num"><i data-lucide="check"></i></span><span class="co-step-label">Cart</span></div>
    <div class="co-step-line done"></div>
    <div class="co-step done"><span class="co-step-num"><i data-lucide="check"></i></span><span class="co-step-label">Details</span></div>
    <div class="co-step-line done"></div>
    <div class="co-step done"><span class="co-step-num"><i data-lucide="check"></i></span><span class="co-step-label">Payment</span></div>
    <div class="co-step-line done"></div>
    <div class="co-step active"><span class="co-step-num"><i data-lucide="check"></i></span><span class="co-step-label">Confirm</span></div>
  </div>
</div>

<!-- ══ CONFIRM CONTENT ═══════════════════════════════════════════════════ -->
<div class="confirm-wrap">
  <div class="confirm-inner">

    <!-- Success banner -->
    <div class="confirm-banner">
      <div class="confirm-icon"><i data-lucide="circle-check-big"></i></div>
      <div class="confirm-text">
        <h1>Order Placed Successfully!</h1>
        <p>Thank you for shopping with Gadget Genesis. Your order is confirmed and being processed.</p>
      </div>
    </div>

    <!-- Estimated delivery banner -->
    <div class="delivery-banner">
      <div class="delivery-banner-ico"><i data-lucide="truck"></i></div>
      <div class="delivery-banner-text">
        <strong>Estimated Delivery: <span id="estimatedDate">Calculating…</span></strong>
        <span id="deliveryNote">Based on your selected shipping method</span>
      </div>
    </div>

    <!-- Cards grid -->
    <div class="confirm-grid">

      <div class="confirm-card">
        <h3><i data-lucide="receipt-text"></i> Order Details</h3>
        <div class="confirm-lines">
          <div class="confirm-line">
            <span>Order Number</span>
            <strong id="orderNumber">
              <?= $loadFromDb ? e($dbOrder['order_number']) : '#GG-000000' ?>
            </strong>
          </div>
          <div class="confirm-line">
            <span>Order Date</span>
            <strong id="orderDate">
              <?= $loadFromDb ? e(date('d M Y, g:ia', strtotime($dbOrder['created_at']))) : '—' ?>
            </strong>
          </div>
          <div class="confirm-line">
            <span>Payment Method</span>
            <strong id="orderPayment">
              <?= $loadFromDb ? e($dbOrder['payment_method']) : '—' ?>
            </strong>
          </div>
          <div class="confirm-line">
            <span>Shipping Method</span>
            <strong id="orderShipping">
              <?= $loadFromDb ? e($dbOrder['shipping_method']) : '—' ?>
            </strong>
          </div>
          <?php if ($loadFromDb): ?>
          <div class="confirm-line">
            <span>Status</span>
            <strong>
              <span class="status-pill status-<?= e($dbOrder['status']) ?>">
                <?= e(statusLabel($dbOrder['status'])) ?>
              </span>
            </strong>
          </div>
          <?php if (!empty($dbOrder['tracking_number'])): ?>
          <div class="confirm-line">
            <span>Tracking Number</span>
            <strong style="color:#2563eb;"><?= e($dbOrder['tracking_number']) ?></strong>
          </div>
          <?php endif; ?>
          <?php endif; ?>
          <div class="confirm-line confirm-total-line">
            <span>Order Total</span>
            <strong id="orderTotal">
              <?= $loadFromDb ? formatPrice((float)$dbOrder['total']) : 'UGX 0' ?>
            </strong>
          </div>
        </div>
      </div>

      <div class="confirm-card">
        <h3><i data-lucide="map-pin"></i> Shipping To</h3>
        <div class="confirm-address" id="confirmAddress">
          <p id="shipName">
            <?= $loadFromDb ? e($dbOrder['customer_name']) : '—' ?>
          </p>
          <p id="shipAddress">
            <?= $loadFromDb ? e($dbOrder['address']) : '—' ?>
          </p>
          <p id="shipCity">
            <?= $loadFromDb ? e($dbOrder['city']) : '—' ?>
          </p>
          <p id="shipPhone">
            <?= $loadFromDb ? e($dbOrder['customer_phone']) : '—' ?>
          </p>
        </div>
      </div>

      <div class="confirm-card confirm-full">
        <h3><i data-lucide="package"></i> Items Ordered</h3>
        <div class="confirm-items" id="confirmItems">
          <?php if ($loadFromDb && !empty($dbItems)): ?>
            <?php foreach ($dbItems as $item): ?>
              <div class="confirm-item">
                <div class="confirm-item-img">
                  <?php
                    $imgSrc = !empty($item['image'])
                      ? $item['image']
                      : 'https://placehold.co/60x60/f3f4f6/aaa?text=' . urlencode($item['model']);
                  ?>
                  <img src="<?= e($imgSrc) ?>"
                       alt="<?= e($item['model']) ?>"
                       onerror="this.src='https://placehold.co/60x60/f3f4f6/aaa?text=?'"/>
                </div>
                <div class="confirm-item-info">
                  <p class="confirm-item-name"><?= e($item['model']) ?></p>
                  <p class="confirm-item-meta">
                    <?= implode(' · ', array_filter([
                        $item['storage'] ?? '',
                        $item['color']   ?? '',
                        'Qty: ' . (int)$item['qty'],
                    ])) ?>
                  </p>
                </div>
                <span class="confirm-item-price">
                  <?= formatPrice((float)$item['price'] * (int)$item['qty']) ?>
                </span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
          <!-- JS will also populate this for fresh checkouts -->
        </div>
      </div>

    </div>

    <!-- What's next -->
    <div class="whats-next no-print">
      <h3>What happens next?</h3>
      <div class="next-steps">
        <div class="next-step">
          <div class="next-step-ico"><i data-lucide="message-circle"></i></div>
          <div><strong>WhatsApp Confirmation</strong><p>You'll receive an order confirmation on WhatsApp shortly.</p></div>
        </div>
        <div class="next-arrow"><i data-lucide="arrow-right"></i></div>
        <div class="next-step">
          <div class="next-step-ico"><i data-lucide="package"></i></div>
          <div><strong>Order Processing</strong><p>We'll prepare your items for shipment within 24 hours.</p></div>
        </div>
        <div class="next-arrow"><i data-lucide="arrow-right"></i></div>
        <div class="next-step">
          <div class="next-step-ico"><i data-lucide="truck"></i></div>
          <div><strong>Out for Delivery</strong><p>Your order will arrive by <span id="nextStepsDate" style="font-weight:700;color:var(--navy)">—</span>.</p></div>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="confirm-actions no-print">
      <a href="index.php" class="btn-continue-shop">
        <i data-lucide="arrow-left"></i> Continue Shopping
      </a>
      <a href="orders.php" class="btn-my-orders">
        <i data-lucide="package"></i> My Orders
      </a>
      <button class="btn-print" onclick="window.print()">
        <i data-lucide="printer"></i> Print Order
      </button>
      <button class="btn-save-pdf" id="savePdfBtn">
        <i data-lucide="download"></i> Save as PDF
      </button>
      <a href="#" class="btn-whatsapp" id="whatsappBtn" target="_blank">
        <i data-lucide="message-circle"></i> Share on WhatsApp
      </a>
    </div>

    <!-- Follow Us -->
    <div class="follow-us no-print">
      <h3>Follow Gadget Genesis Everywhere</h3>
      <p>Stay updated with the latest deals, launches and tech news from us.</p>
      <div class="social-links">
        <a href="https://instagram.com/gadgetgenesis" target="_blank" class="social-link sl-instagram" aria-label="Instagram">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
          Instagram
        </a>
        <a href="https://facebook.com/gadgetgenesis" target="_blank" class="social-link sl-facebook" aria-label="Facebook">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
          Facebook
        </a>
        <a href="https://snapchat.com/add/gadgetgenesis" target="_blank" class="social-link sl-snapchat" aria-label="Snapchat">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.5 2 6 4.5 6 8v1.5c-.8.2-1.5.5-1.5 1s.3.8.8 1c-.2.5-.5 1-.9 1.3-.2.1-.3.3-.1.5.4.5 1.2.7 2.2.8.3.5.7 1 1.5 1 .5 0 1-.2 1.5-.3.3-.1.5-.1.5-.1s.2 0 .5.1c.5.2 1 .3 1.5.3.8 0 1.2-.5 1.5-1 1-.1 1.8-.3 2.2-.8.2-.2.1-.4-.1-.5-.4-.3-.7-.8-.9-1.3.5-.2.8-.5.8-1s-.7-.8-1.5-1V8c0-3.5-2.5-6-6-6z"/></svg>
          Snapchat
        </a>
        <a href="https://x.com/gadgetgenesis" target="_blank" class="social-link sl-twitter" aria-label="X (Twitter)">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
          X (Twitter)
        </a>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/config/footer.php'; ?>

<div class="toast" id="toast"></div>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script src="cart.js"></script>
<script>
/* ── Config passed from PHP ── */
const PAGE_LOADED_FROM_DB = <?= $loadFromDb ? 'true' : 'false' ?>;
const DB_SHIPPING_METHOD  = <?= $loadFromDb ? json_encode($dbOrder['shipping_method']) : 'null' ?>;

/* ── Estimated delivery date ── */
function calcDelivery(shippingMethod) {
  const now  = new Date();
  let   days = 5;

  if (shippingMethod && shippingMethod.toLowerCase().includes('express'))  days = 2;
  if (shippingMethod && shippingMethod.toLowerCase().includes('same'))     days = 0;
  if (shippingMethod && shippingMethod.toLowerCase().includes('standard')) days = 5;

  if (days === 0) {
    return { date: 'Today', note: 'Your order will arrive today!' };
  }

  let added = 0, d = new Date(now);
  while (added < days) {
    d.setDate(d.getDate() + 1);
    if (d.getDay() !== 0 && d.getDay() !== 6) added++;
  }

  const formatted = d.toLocaleDateString('en-UG', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
  const note = days === 1
    ? 'Express delivery — arrives tomorrow'
    : `Standard delivery — ${days} business days`;

  return { date: formatted, note };
}

/* ── WhatsApp share ── */
function buildWhatsAppMsg(num, name, shipping, total, items) {
  const lines = (items || []).map(i =>
    `  • ${i.model}${i.storage ? ' ' + i.storage : ''}${i.color ? ' ' + i.color : ''} x${i.qty}`
  ).join('\n');
  return `*🛍️ Gadget Genesis — Order Confirmation*\n\n` +
    `Order No: *${num}*\n` +
    `Customer: ${name}\n` +
    `Payment: ${shipping}\n\n` +
    `*Items:*\n${lines}\n\n` +
    `*Total: UGX ${Number(total).toLocaleString()}*\n\n` +
    `Estimated delivery: ${document.getElementById('estimatedDate')?.textContent || '—'}\n\n` +
    `Thank you for shopping with Gadget Genesis! 🙏`;
}

/* ── Save as PDF ── */
document.getElementById('savePdfBtn')?.addEventListener('click', () => {
  const prev = document.title;
  document.title = `Order_${document.getElementById('orderNumber')?.textContent || 'Confirmation'}`;
  window.print();
  document.title = prev;
});

/* ── Init ── */
document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();

  /* If PHP already loaded data from DB, just set delivery date & WhatsApp */
  if (PAGE_LOADED_FROM_DB) {
    const delivery = calcDelivery(DB_SHIPPING_METHOD || 'standard');
    const dateEl   = document.getElementById('estimatedDate');
    const noteEl   = document.getElementById('deliveryNote');
    const nextEl   = document.getElementById('nextStepsDate');
    if (dateEl) dateEl.textContent = delivery.date;
    if (noteEl) noteEl.textContent = delivery.note;
    if (nextEl) nextEl.textContent = delivery.date;

    const orderNum = document.getElementById('orderNumber')?.textContent?.trim() || '';
    const name     = document.getElementById('shipName')?.textContent?.trim()    || '';
    const total    = '<?= $loadFromDb ? (float)$dbOrder['total'] : 0 ?>';
    const items    = <?= $loadFromDb ? json_encode(array_map(fn($i) => [
        'model'   => $i['model'],
        'storage' => $i['storage'],
        'color'   => $i['color'],
        'qty'     => $i['qty'],
        'price'   => $i['price'],
        'image'   => $i['image'] ?? null,
    ], $dbItems)) : '[]' ?>;

    const wa = document.getElementById('whatsappBtn');
    if (wa) {
      const msg = buildWhatsAppMsg(orderNum, name, DB_SHIPPING_METHOD, total, items);
      wa.href = `https://wa.me/256759809061?text=${encodeURIComponent(msg)}`;
    }
    return; /* DB data is already rendered by PHP — nothing more to do */
  }

  /* Fresh checkout: wait for cart.js initConfirm() to populate the page,
     then update delivery banner and WhatsApp link */
  setTimeout(() => {
    const order = JSON.parse(sessionStorage.getItem('gg_last_order') || 'null');
    if (!order) { window.location.href = 'index.php'; return; }

    const delivery = calcDelivery(order.shipping || '');
    const dateEl   = document.getElementById('estimatedDate');
    const noteEl   = document.getElementById('deliveryNote');
    const nextEl   = document.getElementById('nextStepsDate');
    if (dateEl) dateEl.textContent = delivery.date;
    if (noteEl) noteEl.textContent = delivery.note;
    if (nextEl) nextEl.textContent = delivery.date;

    const shipEl = document.getElementById('orderShipping');
    if (shipEl) shipEl.textContent = order.shipping || '—';

    const wa = document.getElementById('whatsappBtn');
    if (wa && order.number) {
      const msg = buildWhatsAppMsg(order.number, order.name, order.shipping, order.total, order.items);
      wa.href = `https://wa.me/256759809061?text=${encodeURIComponent(msg)}`;
    }

    /* Replace URL with permanent ?id= link once cart.js has set order_id */
    const savedId = sessionStorage.getItem('gg_last_order_id');
    if (savedId && !window.location.search.includes('id=')) {
      window.history.replaceState(null, '', `order-confirm.php?id=${savedId}`);
    }
  }, 400);
});
</script>
</body>
</html>
