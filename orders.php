<?php
/* ═══════════════════════════════════════════════════════════════════════
   orders.php — Customer order history
   Lookup by phone number (no login required)
   ═══════════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

$orders      = [];
$searched    = false;
$searchPhone = '';
$error       = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $searched    = true;
    $searchPhone = trim($_POST['phone'] ?? '');

    if ($searchPhone === '') {
        $error = 'Please enter your phone number.';
    } else {
        /* Normalise: strip spaces/dashes so 0759 809 061 matches 0759809061 */
        $normPhone = preg_replace('/[\s\-]/', '', $searchPhone);

        $stmt = $pdo->prepare("
            SELECT id, order_number, customer_name, payment_method,
                   shipping_method, total, status, created_at
            FROM orders
            WHERE REPLACE(REPLACE(customer_phone,' ',''),'-','') = :phone
            ORDER BY created_at DESC
        ");
        $stmt->execute([':phone' => $normPhone]);
        $orders = $stmt->fetchAll();

        if (empty($orders)) {
            $error = 'No orders found for that phone number.';
        }
    }
}

/* ── Status badge helper ───────────────────────────────────────────── */
function statusBadge(string $status): string {
    $map = [
        'pending'    => ['label' => 'Pending',    'class' => 'badge-pending'],
        'processing' => ['label' => 'Processing', 'class' => 'badge-processing'],
        'shipped'    => ['label' => 'Shipped',    'class' => 'badge-shipped'],
        'delivered'  => ['label' => 'Delivered',  'class' => 'badge-delivered'],
        'cancelled'  => ['label' => 'Cancelled',  'class' => 'badge-cancelled'],
    ];
    $s = $map[$status] ?? ['label' => ucfirst($status), 'class' => 'badge-pending'];
    return '<span class="ord-badge ' . $s['class'] . '">' . $s['label'] . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>My Orders — Gadget Genesis</title>
  <link rel="stylesheet" href="style.css"/>
  <?php require_once __DIR__ . '/config/theme-init.php'; ?>
  <style>
    /* ── Page layout ── */
    .orders-wrap {
      max-width: 860px;
      margin: 0 auto;
      padding: 40px 20px 80px;
    }
    .orders-page-title {
      font-size: 26px;
      font-weight: 800;
      color: var(--navy);
      margin-bottom: 6px;
    }
    .orders-page-sub {
      font-size: 14px;
      color: var(--gray);
      margin-bottom: 32px;
    }

    /* ── Lookup card ── */
    .lookup-card {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 32px;
      margin-bottom: 36px;
    }
    .lookup-card h2 {
      font-size: 17px;
      font-weight: 700;
      color: var(--navy);
      margin-bottom: 18px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .lookup-row {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }
    .lookup-row input[type="tel"] {
      flex: 1;
      min-width: 220px;
      padding: 12px 16px;
      border: 1.5px solid var(--border);
      border-radius: 9px;
      font-size: 15px;
      font-family: inherit;
      color: var(--navy);
      outline: none;
      transition: border-color .15s;
    }
    .lookup-row input[type="tel"]:focus { border-color: var(--navy); }
    .lookup-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 12px 24px;
      background: var(--navy);
      color: #fff;
      border: none;
      border-radius: 9px;
      font-size: 14px;
      font-weight: 700;
      cursor: pointer;
      font-family: inherit;
      transition: opacity .15s;
    }
    .lookup-btn:hover { opacity: .88; }
    .lookup-error {
      margin-top: 14px;
      padding: 12px 16px;
      background: #fef2f2;
      border: 1px solid #fecaca;
      border-radius: 9px;
      font-size: 14px;
      color: #b91c1c;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    /* ── Orders list ── */
    .orders-list { display: flex; flex-direction: column; gap: 16px; }

    .order-card {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 14px;
      overflow: hidden;
      transition: box-shadow .15s;
    }
    .order-card:hover { box-shadow: 0 4px 18px rgba(0,0,0,.08); }

    .order-card-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 10px;
      padding: 18px 24px;
      border-bottom: 1px solid var(--border);
      background: #f8f9fc;
    }
    .order-card-head-left { display: flex; align-items: center; gap: 14px; }
    .order-card-num {
      font-size: 15px;
      font-weight: 800;
      color: var(--navy);
    }
    .order-card-date {
      font-size: 13px;
      color: var(--gray);
    }
    .order-card-head-right {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .order-card-total {
      font-size: 15px;
      font-weight: 800;
      color: var(--navy);
    }

    /* ── Status badges ── */
    .ord-badge {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 700;
      letter-spacing: .3px;
    }
    .badge-pending    { background: #fef3c7; color: #92400e; }
    .badge-processing { background: #dbeafe; color: #1e40af; }
    .badge-shipped    { background: #ede9fe; color: #5b21b6; }
    .badge-delivered  { background: #dcfce7; color: #166534; }
    .badge-cancelled  { background: #fee2e2; color: #991b1b; }

    /* ── Order body ── */
    .order-card-body {
      padding: 18px 24px;
      display: flex;
      gap: 40px;
      flex-wrap: wrap;
    }
    .order-meta-item {
      display: flex;
      flex-direction: column;
      gap: 3px;
    }
    .order-meta-item span:first-child {
      font-size: 12px;
      color: var(--gray);
      text-transform: uppercase;
      letter-spacing: .4px;
    }
    .order-meta-item span:last-child {
      font-size: 14px;
      font-weight: 600;
      color: var(--navy);
    }

    /* ── View details link ── */
    .order-card-foot {
      padding: 14px 24px;
      border-top: 1px solid var(--border);
      display: flex;
      justify-content: flex-end;
      gap: 10px;
    }
    .btn-view-order {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 9px 18px;
      background: var(--navy);
      color: #fff;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 700;
      text-decoration: none;
      transition: opacity .15s;
    }
    .btn-view-order:hover { opacity: .88; }
    .btn-reorder {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 9px 18px;
      background: #fff;
      color: var(--navy);
      border: 1.5px solid var(--navy);
      border-radius: 8px;
      font-size: 13px;
      font-weight: 700;
      text-decoration: none;
      cursor: pointer;
      font-family: inherit;
      transition: background .15s, color .15s;
    }
    .btn-reorder:hover { background: var(--navy); color: #fff; }

    /* ── Empty state ── */
    .orders-empty {
      text-align: center;
      padding: 60px 20px;
      color: var(--gray);
    }
    .orders-empty [data-lucide] { width: 52px; height: 52px; margin-bottom: 16px; opacity: .3; }
    .orders-empty h3 { font-size: 18px; font-weight: 700; color: var(--navy); margin-bottom: 8px; }
    .orders-empty p  { font-size: 14px; }
  </style>
</head>
<body>

<!-- ══ TOP BAR ══════════════════════════════════════════════════════════ -->
<div class="topbar">
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
<header class="header">
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

<!-- ══ BREADCRUMB ════════════════════════════════════════════════════════ -->
<div class="breadcrumb-bar">
  <div class="bc-inner">
    <a href="index.php">Home</a>
    <i data-lucide="chevron-right" class="bc-sep"></i>
    <span>My Orders</span>
  </div>
</div>

<!-- ══ CONTENT ══════════════════════════════════════════════════════════ -->
<div class="orders-wrap">

  <h1 class="orders-page-title">My Orders</h1>
  <p class="orders-page-sub">Enter the phone number you used at checkout to view your order history.</p>

  <!-- Lookup form -->
  <div class="lookup-card">
    <h2><i data-lucide="search"></i> Look Up Your Orders</h2>
    <form method="POST" action="orders.php">
      <div class="lookup-row">
        <input
          type="tel"
          name="phone"
          placeholder="e.g. 0759 809 061"
          value="<?= e($searchPhone) ?>"
          required
          aria-label="Phone number"
        />
        <button type="submit" class="lookup-btn">
          <i data-lucide="search"></i> Find Orders
        </button>
      </div>
      <?php if ($searched && $error): ?>
        <div class="lookup-error" role="alert">
          <i data-lucide="circle-alert"></i>
          <?= e($error) ?>
        </div>
      <?php endif; ?>
    </form>
  </div>

  <?php if ($searched && !empty($orders)): ?>
  <!-- Results -->
  <div class="orders-list">
    <?php foreach ($orders as $order): ?>
      <div class="order-card">

        <div class="order-card-head">
          <div class="order-card-head-left">
            <span class="order-card-num"><?= e($order['order_number']) ?></span>
            <span class="order-card-date">
              <?= date('d M Y, g:ia', strtotime($order['created_at'])) ?>
            </span>
          </div>
          <div class="order-card-head-right">
            <?= statusBadge($order['status']) ?>
            <span class="order-card-total"><?= formatPrice((float)$order['total']) ?></span>
          </div>
        </div>

        <div class="order-card-body">
          <div class="order-meta-item">
            <span>Customer</span>
            <span><?= e($order['customer_name']) ?></span>
          </div>
          <div class="order-meta-item">
            <span>Payment</span>
            <span><?= e($order['payment_method']) ?></span>
          </div>
          <div class="order-meta-item">
            <span>Shipping</span>
            <span><?= e($order['shipping_method']) ?></span>
          </div>
        </div>

        <div class="order-card-foot">
          <a href="order-confirm.php?id=<?= (int)$order['id'] ?>" class="btn-view-order">
            <i data-lucide="eye"></i> View Details
          </a>
        </div>

      </div>
    <?php endforeach; ?>
  </div>

  <?php elseif (!$searched): ?>
  <!-- Initial state -->
  <div class="orders-empty">
    <i data-lucide="package"></i>
    <h3>No orders looked up yet</h3>
    <p>Enter your phone number above to find your orders.</p>
  </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/config/footer.php'; ?>

<div class="toast" id="toast"></div>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script src="cart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();
});
</script>
</body>
</html>
