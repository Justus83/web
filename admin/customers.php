<?php
/* ═══════════════════════════════════════════════════════════════════════
   admin/customers.php — View registered customers
   ═══════════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/auth.php';
adminGuard();

/* ── Customer detail view (?id=X) ────────────────────────────────────── */
$customerId = (int) ($_GET['id'] ?? 0);
$viewCustomer = null;
$customerOrders = [];

if ($customerId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customerId]);
    $viewCustomer = $stmt->fetch();

    if ($viewCustomer) {
        $stmt = $pdo->prepare("
            SELECT o.id, o.order_number, o.total, o.status, o.created_at,
                   o.payment_method, o.shipping_method,
                   (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count
            FROM orders o
            WHERE o.customer_id = ?
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$customerId]);
        $customerOrders = $stmt->fetchAll();
    }
}

$search = trim($_GET['q'] ?? '');
$params = [];
$where  = '';

if ($search !== '') {
    $where          = "WHERE name LIKE :q OR phone LIKE :q OR email LIKE :q";
    $params[':q']   = '%' . $search . '%';
}

$stmt = $pdo->prepare("
    SELECT c.id, c.name, c.phone, c.email, c.city, c.created_at,
           COUNT(o.id) AS order_count,
           COALESCE(SUM(o.total),0) AS total_spent
    FROM customers c
    LEFT JOIN orders o ON o.customer_id = c.id AND o.status != 'cancelled'
    $where
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$stmt->execute($params);
$customers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Customers — Admin — Gadget Genesis</title>
  <link rel="stylesheet" href="../style.css"/>
  <style>
    body { background:#f1f3f8; }
    .adm-wrap { display:flex;min-height:100vh; }
    .adm-sidebar { width:220px;flex-shrink:0;background:var(--navy);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:100; }
    .adm-logo { padding:22px 20px 18px;border-bottom:1px solid rgba(255,255,255,.1); }
    .adm-logo img { height:36px;width:auto; }
    .adm-logo span { display:block;font-size:11px;color:rgba(255,255,255,.5);margin-top:4px;letter-spacing:.5px;text-transform:uppercase; }
    .adm-nav { flex:1;padding:16px 0; }
    .adm-nav a { display:flex;align-items:center;gap:10px;padding:11px 20px;color:rgba(255,255,255,.7);text-decoration:none;font-size:14px;font-weight:600;transition:background .15s,color .15s;border-left:3px solid transparent; }
    .adm-nav a:hover,.adm-nav a.active { background:rgba(255,255,255,.08);color:#fff;border-left-color:#60a5fa; }
    .adm-nav a [data-lucide] { width:16px;height:16px;flex-shrink:0; }
    .adm-nav .nav-section { font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(255,255,255,.3);padding:16px 20px 6px; }
    .adm-footer { padding:16px 20px;border-top:1px solid rgba(255,255,255,.1);display:flex;flex-direction:column;gap:8px; }
    .adm-footer a { display:flex;align-items:center;gap:8px;font-size:13px;color:rgba(255,255,255,.5);text-decoration:none;transition:color .15s; }
    .adm-footer a:hover { color:#fff; }
    .adm-footer [data-lucide] { width:14px;height:14px; }

    .adm-main { margin-left:220px;flex:1;padding:32px; }
    .adm-topbar { display:flex;align-items:center;justify-content:space-between;margin-bottom:24px; }
    .adm-topbar h1 { font-size:22px;font-weight:800;color:var(--navy); }

    .filters-bar { display:flex;gap:10px;background:#fff;border:1px solid var(--border);border-radius:12px;padding:14px 18px;margin-bottom:20px;align-items:center; }
    .filter-input { padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;color:var(--navy);outline:none;min-width:220px; }
    .filter-input:focus { border-color:var(--navy); }
    .btn-filter { padding:9px 18px;background:var(--navy);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:6px; }
    .btn-filter [data-lucide] { width:14px;height:14px; }

    .adm-card { background:#fff;border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:20px; }
    .adm-table { width:100%;border-collapse:collapse; }
    .adm-table th { text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--gray);padding:12px 16px;border-bottom:1px solid var(--border);background:#f8f9fc; }
    .adm-table td { padding:13px 16px;font-size:13px;color:var(--navy);border-bottom:1px solid #f1f3f8;vertical-align:middle; }
    .adm-table tr:last-child td { border-bottom:none; }
    .adm-table tbody tr:hover td { background:#f8f9fc; }
    .adm-table-empty { text-align:center;padding:48px 20px;color:var(--gray);font-size:14px; }
    .result-count { font-size:13px;color:var(--gray);margin-bottom:12px; }
  </style>
</head>
<body>
<div class="adm-wrap">
  <aside class="adm-sidebar">
    <div class="adm-logo"><img src="../logo.jpg" alt="Gadget Genesis"/><span>Admin Panel</span></div>
    <nav class="adm-nav">
      <div class="nav-section">Overview</div>
      <a href="index.php"><i data-lucide="layout-dashboard"></i> Dashboard</a>
      <div class="nav-section">Store</div>
      <a href="orders.php"><i data-lucide="package"></i> Orders</a>
      <a href="products.php"><i data-lucide="box"></i> Products</a>
      <a href="add-product.php"><i data-lucide="plus-circle"></i> Add Product</a>
      <a href="discounts.php"><i data-lucide="percent"></i> Discounts</a>
      <a href="coupons.php"><i data-lucide="tag"></i> Coupons</a>
      <a href="customers.php" class="active"><i data-lucide="users"></i> Customers</a>
      <a href="reviews.php"><i data-lucide="star"></i> Reviews</a>
    </nav>
    <div class="adm-footer">
      <span style="font-size:12px;color:rgba(255,255,255,.4);padding-bottom:2px;display:flex;align-items:center;gap:6px;"><i data-lucide="user" style="width:12px;height:12px;"></i> <?= htmlspecialchars(adminUsername(), ENT_QUOTES, 'UTF-8') ?></span>
      <a href="change-password.php"><i data-lucide="key-round"></i> Change Password</a>
      <a href="logout.php"><i data-lucide="log-out"></i> Sign Out</a>
    </div>
  </aside>

  <main class="adm-main">
    <div class="adm-topbar">
      <div style="display:flex;align-items:center;gap:14px;">
        <?php if ($viewCustomer): ?>
          <a href="customers.php" style="display:flex;align-items:center;gap:6px;color:var(--navy);text-decoration:none;font-size:14px;font-weight:600;"><i data-lucide="arrow-left" style="width:16px;height:16px;"></i> All Customers</a>
          <h1><?= e($viewCustomer['name']) ?></h1>
        <?php else: ?>
          <h1>Customers</h1>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($viewCustomer): ?>
      <!-- ══ CUSTOMER DETAIL ════════════════════════════════════════════ -->

      <!-- Customer info cards -->
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;margin-bottom:24px;">
        <div class="adm-card" style="margin-bottom:0;">
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
            <div style="width:44px;height:44px;background:var(--navy);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:18px;"><?= strtoupper(substr($viewCustomer['name'],0,1)) ?></div>
            <div>
              <p style="font-weight:800;color:var(--navy);font-size:15px;"><?= e($viewCustomer['name']) ?></p>
              <p style="font-size:12px;color:var(--gray);">Customer #<?= (int)$viewCustomer['id'] ?></p>
            </div>
          </div>
        </div>
        <div class="adm-card" style="margin-bottom:0;">
          <p style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--gray);margin-bottom:4px;">Phone</p>
          <p style="font-size:14px;font-weight:600;color:var(--navy);"><a href="tel:<?= e($viewCustomer['phone']) ?>" style="color:var(--navy);text-decoration:none;"><?= e($viewCustomer['phone']) ?></a></p>
          <?php if ($viewCustomer['email']): ?>
            <p style="font-size:13px;color:var(--gray);margin-top:4px;"><?= e($viewCustomer['email']) ?></p>
          <?php endif; ?>
        </div>
        <div class="adm-card" style="margin-bottom:0;">
          <p style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--gray);margin-bottom:4px;">Address</p>
          <p style="font-size:13px;color:var(--navy);line-height:1.5;">
            <?= e($viewCustomer['address'] ?? '—') ?><br>
            <?= e($viewCustomer['city'] ?? '—') ?><?= $viewCustomer['district'] ? ', ' . e($viewCustomer['district']) : '' ?><br>
            <?= e($viewCustomer['country'] ?? 'UG') ?>
          </p>
        </div>
        <div class="adm-card" style="margin-bottom:0;">
          <p style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--gray);margin-bottom:4px;">Joined</p>
          <p style="font-size:14px;font-weight:600;color:var(--navy);"><?= date('d M Y', strtotime($viewCustomer['created_at'])) ?></p>
          <?php
            $totalSpent = array_sum(array_column(array_filter($customerOrders, fn($o) => $o['status'] !== 'cancelled'), 'total'));
            $totalOrders = count($customerOrders);
          ?>
          <p style="font-size:12px;color:var(--gray);margin-top:4px;"><?= $totalOrders ?> order<?= $totalOrders !== 1 ? 's' : '' ?> · <?= formatPrice((float)$totalSpent) ?></p>
        </div>
      </div>

      <!-- WhatsApp contact button -->
      <div style="margin-bottom:24px;">
        <a href="https://wa.me/<?= preg_replace('/\D/', '', $viewCustomer['phone']) ?>" target="_blank"
           style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:#25d366;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;">
          <i data-lucide="message-circle" style="width:16px;height:16px;"></i> Contact on WhatsApp
        </a>
      </div>

      <!-- Order history -->
      <div class="adm-card">
        <h2 style="font-size:16px;font-weight:800;color:var(--navy);margin-bottom:18px;display:flex;align-items:center;gap:8px;">
          <i data-lucide="package" style="width:16px;height:16px;"></i> Order History (<?= count($customerOrders) ?>)
        </h2>
        <?php if (empty($customerOrders)): ?>
          <p style="color:var(--gray);font-size:14px;text-align:center;padding:20px;">This customer has no orders yet.</p>
        <?php else: ?>
          <table class="adm-table">
            <thead><tr><th>Order #</th><th>Date</th><th>Items</th><th>Payment</th><th>Shipping</th><th>Total</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($customerOrders as $o): ?>
                <tr>
                  <td><strong><?= e($o['order_number']) ?></strong></td>
                  <td><?= date('d M Y, g:ia', strtotime($o['created_at'])) ?></td>
                  <td><?= (int)$o['item_count'] ?> item<?= $o['item_count'] != 1 ? 's' : '' ?></td>
                  <td><?= e($o['payment_method']) ?></td>
                  <td><?= e($o['shipping_method']) ?></td>
                  <td><?= formatPrice((float)$o['total']) ?></td>
                  <td><span class="pill-<?= $o['status'] === 'pending' ? 'pending' : ($o['status'] === 'delivered' ? 'active' : 'inactive') ?>" style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;"><?= ucfirst($o['status']) ?></span></td>
                  <td><a href="orders.php?id=<?= (int)$o['id'] ?>" style="font-size:12px;font-weight:700;color:#2563eb;text-decoration:none;">View</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

    <?php else: ?>
      <!-- ══ CUSTOMER LIST ══════════════════════════════════════════════ -->

    <form method="GET">
      <div class="filters-bar">
        <input type="text" name="q" class="filter-input" placeholder="Search name / phone / email…" value="<?= e($search) ?>"/>
        <button type="submit" class="btn-filter"><i data-lucide="search"></i> Search</button>
      </div>
    </form>

    <div class="result-count"><?= count($customers) ?> customer<?= count($customers) !== 1 ? 's' : '' ?></div>

    <div class="adm-card">
      <?php if (empty($customers)): ?>
        <div class="adm-table-empty">No customers found.</div>
      <?php else: ?>
        <table class="adm-table">
          <thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>City</th><th>Orders</th><th>Total Spent</th><th>Joined</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($customers as $c): ?>
              <tr>
                <td><strong><?= e($c['name']) ?></strong></td>
                <td><a href="tel:<?= e($c['phone']) ?>"><?= e($c['phone']) ?></a></td>
                <td><?= e($c['email'] ?? '—') ?></td>
                <td><?= e($c['city'] ?? '—') ?></td>
                <td><?= (int)$c['order_count'] ?></td>
                <td><?= formatPrice((float)$c['total_spent']) ?></td>
                <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                <td><a href="customers.php?id=<?= (int)$c['id'] ?>" style="font-size:12px;font-weight:700;color:#2563eb;text-decoration:none;">View</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <?php endif; ?>
  </main>
</div>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => { if (window.lucide) lucide.createIcons(); });</script>
</body>
</html>
