<?php
/* ═══════════════════════════════════════════════════════════════════════
   admin/orders.php — Order management panel
   - List all orders with filters (status, search, date)
   - Click an order to see full detail + items
   - Update status inline via AJAX
   ═══════════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/auth.php';
adminGuard();

/* ── Handle status update (POST AJAX) ──────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'update_status') {
        $id       = (int)  ($_POST['order_id'] ?? 0);
        $status   = trim($_POST['status']   ?? '');
        $tracking = trim($_POST['tracking_number'] ?? '');
        $allowed  = ['pending','processing','shipped','delivered','cancelled'];

        if (!$id || !in_array($status, $allowed)) {
            echo json_encode(['error' => 'Invalid input']); exit;
        }

        if ($tracking !== '') {
            $pdo->prepare("UPDATE orders SET status = ?, tracking_number = ? WHERE id = ?")
                ->execute([$status, $tracking, $id]);
        } else {
            $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")
                ->execute([$status, $id]);
        }

        /* ── Send status update email ── */
        $ord = $pdo->prepare("SELECT customer_email, customer_name, order_number, tracking_number FROM orders WHERE id = ?");
        $ord->execute([$id]);
        $ordRow = $ord->fetch();
        if ($ordRow && !empty($ordRow['customer_email'])) {
            require_once __DIR__ . '/../config/mailer.php';
            sendStatusUpdate(
                $ordRow['customer_email'],
                $ordRow['customer_name'],
                $ordRow['order_number'],
                $status,
                $ordRow['tracking_number'] ?? '',
                $id
            );
        }

        echo json_encode(['success' => true, 'status' => $status]);
    }

    if ($_POST['action'] === 'export_csv') {
        // Handled via GET below
    }
    exit;
}

/* ── CSV Export ─────────────────────────────────────────────────────── */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Read filters directly — this runs before the Filters section below
    $csvStatus = trim($_GET['status'] ?? '');
    $csvSearch = trim($_GET['q']      ?? '');
    $csvDate   = trim($_GET['date']   ?? '');

    $where = ["1=1"]; $params = [];
    if ($csvStatus) { $where[] = 'o.status = :s'; $params[':s'] = $csvStatus; }
    if ($csvSearch) { $where[] = '(o.order_number LIKE :q OR o.customer_name LIKE :q OR o.customer_phone LIKE :q)'; $params[':q'] = "%$csvSearch%"; }
    if ($csvDate)   { $where[] = 'DATE(o.created_at) = :d'; $params[':d'] = $csvDate; }
    $sql = "SELECT o.id, o.order_number, o.customer_name, o.customer_phone, o.customer_email,
                   o.address, o.city, o.payment_method, o.shipping_method,
                   o.subtotal, o.shipping_cost, o.discount, o.total, o.status,
                   o.tracking_number, o.created_at
            FROM orders o WHERE " . implode(' AND ', $where) . " ORDER BY o.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="orders-export-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order #','Date','Customer','Phone','Email','Address','City','Payment','Shipping','Subtotal','Shipping Cost','Discount','Total','Status','Tracking #']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['order_number'], date('d M Y g:ia', strtotime($r['created_at'])),
            $r['customer_name'], $r['customer_phone'], $r['customer_email'] ?? '',
            $r['address'], $r['city'], $r['payment_method'], $r['shipping_method'],
            $r['subtotal'], $r['shipping_cost'], $r['discount'], $r['total'],
            $r['status'], $r['tracking_number'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

/* ── Filters ───────────────────────────────────────────────────────── */
$filterStatus = $_GET['status'] ?? '';
$filterSearch = trim($_GET['q']      ?? '');
$filterDate   = trim($_GET['date']   ?? '');
$viewId       = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;

/* ── Load single order detail ──────────────────────────────────────── */
$detailOrder = null;
$detailItems = [];
if ($viewId) {
    $stmt = $pdo->prepare("
        SELECT o.*, c.email as customer_email
        FROM orders o
        LEFT JOIN customers c ON c.id = o.customer_id
        WHERE o.id = ?
    ");
    $stmt->execute([$viewId]);
    $detailOrder = $stmt->fetch();

    if ($detailOrder) {
        $iStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC");
        $iStmt->execute([$viewId]);
        $detailItems = $iStmt->fetchAll();
    }
}

/* ── Build orders list query ───────────────────────────────────────── */
$where  = [];
$params = [];

if ($filterStatus !== '') {
    $where[]            = 'o.status = :status';
    $params[':status']  = $filterStatus;
}
if ($filterSearch !== '') {
    $where[]            = '(o.order_number LIKE :q OR o.customer_name LIKE :q OR o.customer_phone LIKE :q)';
    $params[':q']       = '%' . $filterSearch . '%';
}
if ($filterDate !== '') {
    $where[]            = 'DATE(o.created_at) = :date';
    $params[':date']    = $filterDate;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT o.id, o.order_number, o.customer_name, o.customer_phone,
           o.payment_method, o.shipping_method, o.total, o.status, o.created_at
    FROM orders o
    $whereSQL
    ORDER BY o.created_at DESC
    LIMIT 200
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

/* ── Status helpers ────────────────────────────────────────────────── */
$statusLabels = ['pending'=>'Pending','processing'=>'Processing','shipped'=>'Shipped','delivered'=>'Delivered','cancelled'=>'Cancelled'];
$statusCls    = ['pending'=>'badge-pending','processing'=>'badge-processing','shipped'=>'badge-shipped','delivered'=>'badge-delivered','cancelled'=>'badge-cancelled'];

function statusOpts(string $current): string {
    $all = ['pending','processing','shipped','delivered','cancelled'];
    $out = '';
    foreach ($all as $s) {
        $sel  = $s === $current ? ' selected' : '';
        $lbl  = ucfirst($s);
        $out .= "<option value=\"$s\"$sel>$lbl</option>";
    }
    return $out;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Orders — Admin — Gadget Genesis</title>
  <link rel="stylesheet" href="../style.css"/>
  <style>
    body { background: #f1f3f8; }

    /* ── Shared sidebar (same as dashboard) ── */
    .adm-wrap    { display: flex; min-height: 100vh; }
    .adm-sidebar {
      width: 220px; flex-shrink: 0;
      background: var(--navy);
      display: flex; flex-direction: column;
      position: fixed; top: 0; left: 0; bottom: 0; z-index: 100;
    }
    .adm-logo { padding: 22px 20px 18px; border-bottom: 1px solid rgba(255,255,255,.1); }
    .adm-logo img { height: 36px; width: auto; }
    .adm-logo span { display: block; font-size: 11px; color: rgba(255,255,255,.5); margin-top: 4px; letter-spacing: .5px; text-transform: uppercase; }
    .adm-nav { flex: 1; padding: 16px 0; }
    .adm-nav a { display: flex; align-items: center; gap: 10px; padding: 11px 20px; color: rgba(255,255,255,.7); text-decoration: none; font-size: 14px; font-weight: 600; transition: background .15s, color .15s; border-left: 3px solid transparent; }
    .adm-nav a:hover, .adm-nav a.active { background: rgba(255,255,255,.08); color: #fff; border-left-color: #60a5fa; }
    .adm-nav a [data-lucide] { width: 16px; height: 16px; flex-shrink: 0; }
    .adm-nav .nav-section { font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(255,255,255,.3); padding: 16px 20px 6px; }
    .adm-footer { padding: 16px 20px; border-top: 1px solid rgba(255,255,255,.1); display: flex; flex-direction: column; gap: 8px; }
    .adm-footer a { display: flex; align-items: center; gap: 8px; font-size: 13px; color: rgba(255,255,255,.5); text-decoration: none; transition: color .15s; }
    .adm-footer a:hover { color: #fff; }
    .adm-footer [data-lucide] { width: 14px; height: 14px; }

    /* ── Main ── */
    .adm-main { margin-left: 220px; flex: 1; padding: 32px; }
    .adm-topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
    .adm-topbar h1 { font-size: 22px; font-weight: 800; color: var(--navy); }

    /* ── Filters bar ── */
    .filters-bar {
      display: flex; gap: 10px; flex-wrap: wrap;
      background: #fff; border: 1px solid var(--border);
      border-radius: 12px; padding: 14px 18px;
      margin-bottom: 20px; align-items: center;
    }
    .filter-input {
      padding: 9px 12px;
      border: 1.5px solid var(--border); border-radius: 8px;
      font-size: 13px; font-family: inherit; color: var(--navy);
      outline: none; transition: border-color .15s;
      min-width: 160px;
    }
    .filter-input:focus { border-color: var(--navy); }
    .filter-select {
      padding: 9px 12px;
      border: 1.5px solid var(--border); border-radius: 8px;
      font-size: 13px; font-family: inherit; color: var(--navy);
      background: #fff; outline: none; cursor: pointer;
    }
    .btn-filter {
      padding: 9px 18px;
      background: var(--navy); color: #fff;
      border: none; border-radius: 8px;
      font-size: 13px; font-weight: 700;
      cursor: pointer; font-family: inherit;
      display: flex; align-items: center; gap: 6px;
    }
    .btn-filter:hover { opacity: .88; }
    .btn-filter [data-lucide] { width: 14px; height: 14px; }
    .btn-clear {
      padding: 9px 14px;
      background: #fff; color: var(--gray);
      border: 1.5px solid var(--border); border-radius: 8px;
      font-size: 13px; cursor: pointer; font-family: inherit;
      text-decoration: none;
    }
    .btn-clear:hover { color: var(--navy); border-color: var(--navy); }

    /* ── Orders table ── */
    .adm-card {
      background: #fff; border: 1px solid var(--border);
      border-radius: 14px; overflow: hidden;
      margin-bottom: 20px;
    }
    .adm-table { width: 100%; border-collapse: collapse; }
    .adm-table th { text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--gray); padding: 12px 16px; border-bottom: 1px solid var(--border); background: #f8f9fc; }
    .adm-table td { padding: 13px 16px; font-size: 13px; color: var(--navy); border-bottom: 1px solid #f1f3f8; vertical-align: middle; }
    .adm-table tr:last-child td { border-bottom: none; }
    .adm-table tbody tr:hover td { background: #f8f9fc; cursor: pointer; }
    .adm-table tbody tr.selected td { background: #eff6ff; }

    .ord-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
    .badge-pending    { background: #fef3c7; color: #92400e; }
    .badge-processing { background: #dbeafe; color: #1e40af; }
    .badge-shipped    { background: #ede9fe; color: #5b21b6; }
    .badge-delivered  { background: #dcfce7; color: #166534; }
    .badge-cancelled  { background: #fee2e2; color: #991b1b; }

    .adm-table-empty { text-align: center; padding: 48px 20px; color: var(--gray); font-size: 14px; }
    .adm-table-empty [data-lucide] { width: 40px; height: 40px; opacity: .2; display: block; margin: 0 auto 12px; }

    /* ── Status quick-change ── */
    .status-select {
      padding: 4px 8px; border: 1.5px solid var(--border);
      border-radius: 6px; font-size: 12px; font-family: inherit;
      background: #fff; cursor: pointer; color: var(--navy);
    }
    .status-select:focus { outline: none; border-color: var(--navy); }

    /* ── Order detail panel ── */
    .detail-panel {
      background: #fff; border: 1px solid var(--border);
      border-radius: 14px; padding: 28px;
    }
    .detail-head {
      display: flex; align-items: flex-start; justify-content: space-between;
      flex-wrap: wrap; gap: 14px; margin-bottom: 24px;
    }
    .detail-head h2 { font-size: 20px; font-weight: 800; color: var(--navy); }
    .detail-head-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .btn-back {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 9px 16px; background: #fff; color: var(--navy);
      border: 1.5px solid var(--border); border-radius: 8px;
      font-size: 13px; font-weight: 600; text-decoration: none;
      transition: background .15s;
    }
    .btn-back:hover { background: #f1f3f8; }
    .btn-back [data-lucide] { width: 14px; height: 14px; }

    .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
    @media (max-width: 640px) { .detail-grid { grid-template-columns: 1fr; } }

    .detail-section { background: #f8f9fc; border-radius: 10px; padding: 18px; }
    .detail-section h3 { font-size: 13px; font-weight: 700; color: var(--navy); margin-bottom: 12px; display: flex; align-items: center; gap: 7px; }
    .detail-section h3 [data-lucide] { width: 14px; height: 14px; }
    .detail-line { display: flex; justify-content: space-between; gap: 10px; margin-bottom: 8px; font-size: 13px; }
    .detail-line span:first-child { color: var(--gray); }
    .detail-line span:last-child  { font-weight: 600; color: var(--navy); text-align: right; }
    .detail-line:last-child { margin-bottom: 0; }

    /* ── Items table in detail ── */
    .detail-items { width: 100%; border-collapse: collapse; margin-top: 6px; }
    .detail-items th { text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--gray); padding: 0 10px 8px; border-bottom: 1px solid var(--border); }
    .detail-items td { padding: 11px 10px; font-size: 13px; color: var(--navy); border-bottom: 1px solid #f1f3f8; vertical-align: middle; }
    .detail-items tr:last-child td { border-bottom: none; }

    .item-img { width: 44px; height: 44px; object-fit: cover; border-radius: 6px; background: #f3f4f6; }

    /* ── Status update form ── */
    .status-update-row {
      display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
      padding: 18px; background: #f0f9ff; border: 1px solid #bae6fd;
      border-radius: 10px; margin-bottom: 20px;
    }
    .status-update-row label { font-size: 13px; font-weight: 700; color: var(--navy); }
    .status-big-select {
      padding: 9px 14px; border: 1.5px solid var(--border); border-radius: 8px;
      font-size: 13px; font-family: inherit; background: #fff; color: var(--navy);
      cursor: pointer;
    }
    .btn-update-status {
      padding: 9px 18px; background: var(--navy); color: #fff;
      border: none; border-radius: 8px; font-size: 13px; font-weight: 700;
      cursor: pointer; font-family: inherit;
      display: flex; align-items: center; gap: 7px;
    }
    .btn-update-status:hover { opacity: .88; }
    .btn-update-status [data-lucide] { width: 14px; height: 14px; }
    #statusMsg { font-size: 13px; font-weight: 600; }

    /* ── Totals summary ── */
    .totals-section { background: #f8f9fc; border-radius: 10px; padding: 16px 18px; }
    .totals-line { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 8px; }
    .totals-line span:first-child { color: var(--gray); }
    .totals-line span:last-child  { font-weight: 600; color: var(--navy); }
    .totals-total { display: flex; justify-content: space-between; font-size: 15px; font-weight: 800; color: var(--navy); border-top: 1px solid var(--border); margin-top: 8px; padding-top: 10px; }

    /* ── Result count ── */
    .result-count { font-size: 13px; color: var(--gray); margin-bottom: 12px; }
  </style>
</head>
<body>
<div class="adm-wrap">

  <!-- ══ SIDEBAR ══ -->
  <aside class="adm-sidebar">
    <div class="adm-logo">
      <img src="../logo.jpg" alt="Gadget Genesis"/>
      <span>Admin Panel</span>
    </div>
    <nav class="adm-nav">
      <div class="nav-section">Overview</div>
      <a href="index.php"><i data-lucide="layout-dashboard"></i> Dashboard</a>
      <div class="nav-section">Store</div>
      <a href="orders.php" class="active"><i data-lucide="package"></i> Orders</a>
      <a href="products.php"><i data-lucide="box"></i> Products</a>
      <a href="discounts.php"><i data-lucide="percent"></i> Discounts</a>
      <a href="coupons.php"><i data-lucide="tag"></i> Coupons</a>
      <a href="customers.php"><i data-lucide="users"></i> Customers</a>
      <a href="reviews.php"><i data-lucide="star"></i> Reviews</a>
    </nav>
    <div class="adm-footer">
      <span style="font-size:12px;color:rgba(255,255,255,.4);padding-bottom:2px;display:flex;align-items:center;gap:6px;"><i data-lucide="user" style="width:12px;height:12px;"></i> <?= htmlspecialchars(adminUsername(), ENT_QUOTES, 'UTF-8') ?></span>
      <a href="change-password.php"><i data-lucide="key-round"></i> Change Password</a>
      <a href="logout.php"><i data-lucide="log-out"></i> Sign Out</a>
    </div>
  </aside>

  <!-- ══ MAIN ══ -->
  <main class="adm-main">

    <?php if ($detailOrder): ?>
    <!-- ══════════════ ORDER DETAIL VIEW ══════════════ -->

    <div class="adm-topbar">
      <h1>Order <?= e($detailOrder['order_number']) ?></h1>
      <a href="orders.php" class="btn-back"><i data-lucide="arrow-left"></i> Back to Orders</a>
    </div>

    <!-- Status update -->
    <div class="status-update-row">
      <label for="statusBig">Update Status:</label>
      <select class="status-big-select" id="statusBig">
        <?= statusOpts($detailOrder['status']) ?>
      </select>
      <label for="trackingInput" style="margin-left:8px;">Tracking #:</label>
      <input type="text" id="trackingInput" placeholder="e.g. DHL123456"
             value="<?= e($detailOrder['tracking_number'] ?? '') ?>"
             style="padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;width:160px;"/>
      <button class="btn-update-status" id="updateStatusBtn" data-id="<?= (int)$detailOrder['id'] ?>">
        <i data-lucide="save"></i> Save
      </button>
      <span id="statusMsg"></span>
    </div>

    <div class="detail-panel">
      <div class="detail-grid">

        <!-- Order info -->
        <div class="detail-section">
          <h3><i data-lucide="receipt-text"></i> Order Details</h3>
          <div class="detail-line"><span>Order Number</span><span><?= e($detailOrder['order_number']) ?></span></div>
          <div class="detail-line"><span>Date</span><span><?= date('d M Y, g:ia', strtotime($detailOrder['created_at'])) ?></span></div>
          <div class="detail-line"><span>Payment</span><span><?= e($detailOrder['payment_method']) ?></span></div>
          <div class="detail-line"><span>Shipping</span><span><?= e($detailOrder['shipping_method']) ?></span></div>
          <div class="detail-line">
            <span>Status</span>
            <span>
              <span class="ord-badge <?= $statusCls[$detailOrder['status']] ?? 'badge-pending' ?>" id="statusBadge">
                <?= $statusLabels[$detailOrder['status']] ?? ucfirst($detailOrder['status']) ?>
              </span>
            </span>
          </div>
        </div>

        <!-- Customer info -->
        <div class="detail-section">
          <h3><i data-lucide="user"></i> Customer</h3>
          <div class="detail-line"><span>Name</span><span><?= e($detailOrder['customer_name']) ?></span></div>
          <div class="detail-line"><span>Phone</span><span><a href="tel:<?= e($detailOrder['customer_phone']) ?>"><?= e($detailOrder['customer_phone']) ?></a></span></div>
          <?php if (!empty($detailOrder['customer_email'])): ?>
          <div class="detail-line"><span>Email</span><span><?= e($detailOrder['customer_email'] ?? '') ?></span></div>
          <?php endif; ?>
          <div class="detail-line"><span>Address</span><span><?= e($detailOrder['address']) ?></span></div>
          <div class="detail-line"><span>City</span><span><?= e($detailOrder['city']) ?></span></div>
          <?php if (!empty($detailOrder['district'])): ?>
          <div class="detail-line"><span>District</span><span><?= e($detailOrder['district']) ?></span></div>
          <?php endif; ?>
          <?php if (!empty($detailOrder['notes'])): ?>
          <div class="detail-line"><span>Notes</span><span><?= e($detailOrder['notes']) ?></span></div>
          <?php endif; ?>
        </div>

      </div>

      <!-- Items -->
      <h3 style="font-size:14px;font-weight:800;color:var(--navy);margin-bottom:12px;display:flex;align-items:center;gap:7px;">
        <i data-lucide="package"></i> Items Ordered
      </h3>
      <table class="detail-items">
        <thead>
          <tr>
            <th style="width:52px"></th>
            <th>Product</th>
            <th>Storage</th>
            <th>Color</th>
            <th>Unit Price</th>
            <th>Qty</th>
            <th>Subtotal</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($detailItems as $item): ?>
            <tr>
              <td>
                <?php $imgSrc = !empty($item['image']) ? $item['image'] : 'https://placehold.co/44x44/f3f4f6/aaa?text=?'; ?>
                <img src="<?= e($imgSrc) ?>" alt="<?= e($item['model']) ?>" class="item-img"
                     onerror="this.src='https://placehold.co/44x44/f3f4f6/aaa?text=?'"/>
              </td>
              <td><strong><?= e($item['model']) ?></strong></td>
              <td><?= e($item['storage'] ?? '—') ?></td>
              <td><?= e($item['color']   ?? '—') ?></td>
              <td><?= formatPrice((float)$item['price']) ?></td>
              <td><?= (int)$item['qty'] ?></td>
              <td><strong><?= formatPrice((float)$item['price'] * (int)$item['qty']) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Totals -->
      <div style="max-width:320px;margin-left:auto;margin-top:20px;">
        <div class="totals-section">
          <div class="totals-line"><span>Subtotal</span><span><?= formatPrice((float)$detailOrder['subtotal']) ?></span></div>
          <div class="totals-line"><span>Shipping</span><span><?= (float)$detailOrder['shipping_cost'] > 0 ? formatPrice((float)$detailOrder['shipping_cost']) : 'Free' ?></span></div>
          <?php if ((float)$detailOrder['discount'] > 0): ?>
          <div class="totals-line"><span>Discount</span><span style="color:#16a34a">−<?= formatPrice((float)$detailOrder['discount']) ?></span></div>
          <?php endif; ?>
          <div class="totals-total"><span>Total</span><span><?= formatPrice((float)$detailOrder['total']) ?></span></div>
        </div>
      </div>

      <!-- WhatsApp quick contact -->
      <div style="margin-top:20px;">
        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $detailOrder['customer_phone']) ?>?text=<?= urlencode('Hi ' . $detailOrder['customer_name'] . ', your Gadget Genesis order ' . $detailOrder['order_number'] . ' has been updated. Please contact us for more details.') ?>"
           target="_blank"
           style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:#25d366;color:#fff;border-radius:9px;font-size:13px;font-weight:700;text-decoration:none;">
          <i data-lucide="message-circle"></i> WhatsApp Customer
        </a>
      </div>

    </div>

    <?php else: ?>
    <!-- ══════════════ ORDERS LIST ══════════════ -->

    <div class="adm-topbar">
      <h1>Orders</h1>
    </div>

    <!-- Filters -->
    <form method="GET" action="orders.php">
      <div class="filters-bar">
        <input type="text" name="q" class="filter-input"
               placeholder="Search order / name / phone…"
               value="<?= e($filterSearch) ?>"/>
        <select name="status" class="filter-select">
          <option value="">All Statuses</option>
          <?php foreach ($statusLabels as $val => $lbl): ?>
            <option value="<?= $val ?>" <?= $filterStatus === $val ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
        <input type="date" name="date" class="filter-input" value="<?= e($filterDate) ?>" title="Filter by date"/>
        <button type="submit" class="btn-filter"><i data-lucide="search"></i> Filter</button>
        <?php if ($filterStatus || $filterSearch || $filterDate): ?>
          <a href="orders.php" class="btn-clear">Clear</a>
        <?php endif; ?>
        <a href="orders.php?export=csv<?= $filterStatus ? '&status=' . urlencode($filterStatus) : '' ?><?= $filterSearch ? '&q=' . urlencode($filterSearch) : '' ?><?= $filterDate ? '&date=' . urlencode($filterDate) : '' ?>"
           class="btn-filter" style="background:#16a34a;text-decoration:none;">
          <i data-lucide="download"></i> Export CSV
        </a>
      </div>
    </form>

    <div class="result-count"><?= count($orders) ?> order<?= count($orders) !== 1 ? 's' : '' ?> found</div>

    <div class="adm-card">
      <?php if (empty($orders)): ?>
        <div class="adm-table-empty">
          <i data-lucide="package"></i>
          No orders found.
        </div>
      <?php else: ?>
        <table class="adm-table">
          <thead>
            <tr>
              <th>Order #</th>
              <th>Customer</th>
              <th>Phone</th>
              <th>Total</th>
              <th>Payment</th>
              <th>Status</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $o): ?>
              <tr data-id="<?= (int)$o['id'] ?>" onclick="window.location='orders.php?id=<?= (int)$o['id'] ?>'">
                <td><strong><?= e($o['order_number']) ?></strong></td>
                <td><?= e($o['customer_name']) ?></td>
                <td><?= e($o['customer_phone']) ?></td>
                <td><?= formatPrice((float)$o['total']) ?></td>
                <td><?= e($o['payment_method']) ?></td>
                <td>
                  <select class="status-select" data-id="<?= (int)$o['id'] ?>"
                          onclick="event.stopPropagation()"
                          onchange="quickStatus(this)">
                    <?= statusOpts($o['status']) ?>
                  </select>
                </td>
                <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                <td onclick="event.stopPropagation()">
                  <a href="orders.php?id=<?= (int)$o['id'] ?>"
                     style="color:var(--navy);font-weight:600;font-size:13px;text-decoration:none;">
                    View
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <?php endif; ?>

  </main>
</div>

<div class="toast" id="toast" style="position:fixed;bottom:24px;right:24px;background:var(--navy);color:#fff;padding:12px 20px;border-radius:10px;font-size:13px;font-weight:600;display:none;z-index:9999;"></div>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>
/* ── Toast ── */
function toast(msg, ok = true) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.style.background = ok ? '#16a34a' : '#dc2626';
  t.style.display = 'block';
  setTimeout(() => t.style.display = 'none', 2800);
}

/* ── Quick status change from list view ── */
async function quickStatus(sel) {
  const id     = sel.dataset.id;
  const status = sel.value;
  try {
    const fd = new FormData();
    fd.append('action',   'update_status');
    fd.append('order_id', id);
    fd.append('status',   status);
    const res  = await fetch('orders.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) toast('Status updated');
    else toast('Update failed', false);
  } catch { toast('Network error', false); }
}

/* ── Status update on detail page ── */
document.getElementById('updateStatusBtn')?.addEventListener('click', async function () {
  const id       = this.dataset.id;
  const status   = document.getElementById('statusBig')?.value;
  const tracking = document.getElementById('trackingInput')?.value || '';
  const msgEl    = document.getElementById('statusMsg');

  try {
    const fd = new FormData();
    fd.append('action',         'update_status');
    fd.append('order_id',       id);
    fd.append('status',         status);
    fd.append('tracking_number', tracking);
    const res  = await fetch('orders.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      msgEl.textContent = '✓ Saved';
      msgEl.style.color = '#16a34a';

      // Update badge label and class
      const badge = document.getElementById('statusBadge');
      if (badge) {
        const labels = { pending:'Pending', processing:'Processing', shipped:'Shipped', delivered:'Delivered', cancelled:'Cancelled' };
        const classes = { pending:'badge-pending', processing:'badge-processing', shipped:'badge-shipped', delivered:'badge-delivered', cancelled:'badge-cancelled' };
        badge.textContent = labels[status] || status;
        badge.className   = 'ord-badge ' + (classes[status] || 'badge-pending');
      }

      setTimeout(() => { msgEl.textContent = ''; }, 2500);
    } else {
      msgEl.textContent = '✗ Failed';
      msgEl.style.color = '#dc2626';
    }
  } catch {
    msgEl.textContent = '✗ Network error';
    msgEl.style.color = '#dc2626';
  }
});

document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();
});
</script>
</body>
</html>
