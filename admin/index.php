<?php
/* ═══════════════════════════════════════════════════════════════════════
   admin/index.php — Admin dashboard landing page
   ═══════════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/auth.php';
adminGuard();

/* ── Quick stats ───────────────────────────────────────────────────── */
$stats = [];

$stats['orders_total']   = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$stats['orders_pending'] = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
$stats['orders_today']   = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$stats['revenue_total']  = $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status != 'cancelled'")->fetchColumn();
$stats['customers']      = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$stats['products']       = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

/* ── Revenue last 30 days (daily) ──────────────────────────────────── */
$revenueRows = $pdo->query("
    SELECT DATE(created_at) AS day, COALESCE(SUM(total),0) AS rev
    FROM orders
    WHERE status != 'cancelled'
      AND created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
")->fetchAll();

// Fill in all 30 days so gaps show as 0
$revenueMap = [];
foreach ($revenueRows as $r) $revenueMap[$r['day']] = (float)$r['rev'];
$revenueLabels = [];
$revenueData   = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $revenueLabels[] = date('d M', strtotime($d));
    $revenueData[]   = $revenueMap[$d] ?? 0;
}

/* ── Order status breakdown ────────────────────────────────────────── */
$statusRows = $pdo->query("
    SELECT status, COUNT(*) AS cnt
    FROM orders
    GROUP BY status
")->fetchAll();
$statusLabels = [];
$statusData   = [];
$statusColors = ['pending'=>'#f59e0b','processing'=>'#3b82f6','shipped'=>'#8b5cf6','delivered'=>'#10b981','cancelled'=>'#ef4444'];
foreach ($statusRows as $r) {
    $statusLabels[] = ucfirst($r['status']);
    $statusData[]   = (int)$r['cnt'];
}
$statusBgColors = array_map(fn($l) => $statusColors[strtolower($l)] ?? '#9ca3af', $statusLabels);

/* ── Top 5 products by revenue ─────────────────────────────────────── */
$topProducts = $pdo->query("
    SELECT oi.model, COALESCE(SUM(oi.price * oi.qty),0) AS revenue
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE o.status != 'cancelled'
    GROUP BY oi.model
    ORDER BY revenue DESC
    LIMIT 5
")->fetchAll();
$topLabels  = array_column($topProducts, 'model');
$topRevenue = array_map(fn($r) => (float)$r['revenue'], $topProducts);

/* ── Revenue this month vs last month ──────────────────────────────── */
$thisMonth = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status!='cancelled' AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")->fetchColumn();
$lastMonth = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status!='cancelled' AND MONTH(created_at)=MONTH(DATE_SUB(CURDATE(),INTERVAL 1 MONTH)) AND YEAR(created_at)=YEAR(DATE_SUB(CURDATE(),INTERVAL 1 MONTH))")->fetchColumn();
$monthChange = $lastMonth > 0 ? round(($thisMonth - $lastMonth) / $lastMonth * 100) : ($thisMonth > 0 ? 100 : 0);

/* ── Recent 5 orders ───────────────────────────────────────────────── */
$recent = $pdo->query("
    SELECT id, order_number, customer_name, total, status, created_at
    FROM orders ORDER BY created_at DESC LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Admin — Gadget Genesis</title>
  <link rel="stylesheet" href="../style.css"/>
  <style>
    body { background: #f1f3f8; }

    /* ── Admin shell ── */
    .adm-wrap   { display: flex; min-height: 100vh; }
    .adm-sidebar {
      width: 220px; flex-shrink: 0;
      background: var(--navy);
      display: flex; flex-direction: column;
      padding: 0;
      position: fixed; top: 0; left: 0; bottom: 0;
      z-index: 100;
    }
    .adm-logo {
      padding: 22px 20px 18px;
      border-bottom: 1px solid rgba(255,255,255,.1);
    }
    .adm-logo img { height: 36px; width: auto; }
    .adm-logo span {
      display: block; font-size: 11px; color: rgba(255,255,255,.5);
      margin-top: 4px; letter-spacing: .5px; text-transform: uppercase;
    }
    .adm-nav { flex: 1; padding: 16px 0; }
    .adm-nav a {
      display: flex; align-items: center; gap: 10px;
      padding: 11px 20px;
      color: rgba(255,255,255,.7);
      text-decoration: none; font-size: 14px; font-weight: 600;
      transition: background .15s, color .15s;
      border-left: 3px solid transparent;
    }
    .adm-nav a:hover, .adm-nav a.active {
      background: rgba(255,255,255,.08);
      color: #fff;
      border-left-color: #60a5fa;
    }
    .adm-nav a [data-lucide] { width: 16px; height: 16px; flex-shrink: 0; }
    .adm-nav .nav-section {
      font-size: 10px; font-weight: 700; letter-spacing: 1px;
      text-transform: uppercase; color: rgba(255,255,255,.3);
      padding: 16px 20px 6px;
    }
    .adm-footer {
      padding: 16px 20px;
      border-top: 1px solid rgba(255,255,255,.1);
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .adm-footer a {
      display: flex; align-items: center; gap: 8px;
      font-size: 13px; color: rgba(255,255,255,.5);
      text-decoration: none;
      transition: color .15s;
    }
    .adm-footer a:hover { color: #fff; }
    .adm-footer [data-lucide] { width: 14px; height: 14px; }

    /* ── Main ── */
    .adm-main {
      margin-left: 220px;
      flex: 1;
      padding: 32px;
      min-height: 100vh;
    }
    .adm-topbar {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 28px;
    }
    .adm-topbar h1 { font-size: 22px; font-weight: 800; color: var(--navy); }
    .adm-topbar-right { display: flex; align-items: center; gap: 12px; }
    .adm-view-site {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 8px 16px;
      background: #fff; color: var(--navy);
      border: 1.5px solid var(--border); border-radius: 8px;
      font-size: 13px; font-weight: 600; text-decoration: none;
      transition: background .15s;
    }
    .adm-view-site:hover { background: #f1f3f8; }
    .adm-view-site [data-lucide] { width: 14px; height: 14px; }

    /* ── Stat cards ── */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 16px;
      margin-bottom: 28px;
    }
    .stat-card {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 20px 22px;
    }
    .stat-card-top {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 10px;
    }
    .stat-card-ico {
      width: 38px; height: 38px;
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
    }
    .stat-card-ico [data-lucide] { width: 18px; height: 18px; }
    .ico-blue   { background: #dbeafe; color: #1d4ed8; }
    .ico-yellow { background: #fef3c7; color: #d97706; }
    .ico-green  { background: #dcfce7; color: #16a34a; }
    .ico-purple { background: #ede9fe; color: #7c3aed; }
    .ico-navy   { background: #e0e7ff; color: var(--navy); }
    .ico-red    { background: #fee2e2; color: #dc2626; }

    .stat-card-val {
      font-size: 24px; font-weight: 800; color: var(--navy);
      line-height: 1;
    }
    .stat-card-lbl {
      font-size: 12px; color: var(--gray); margin-top: 4px;
    }

    /* ── Section card ── */
    .adm-card {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 24px;
      margin-bottom: 20px;
    }
    .adm-card-head {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 18px;
    }
    .adm-card-head h2 {
      font-size: 16px; font-weight: 800; color: var(--navy);
      display: flex; align-items: center; gap: 8px;
    }
    .adm-card-head h2 [data-lucide] { width: 16px; height: 16px; }
    .adm-card-head a {
      font-size: 13px; font-weight: 600; color: var(--navy);
      text-decoration: none;
    }
    .adm-card-head a:hover { text-decoration: underline; }

    /* ── Recent orders table ── */
    .adm-table { width: 100%; border-collapse: collapse; }
    .adm-table th {
      text-align: left; font-size: 11px; font-weight: 700;
      text-transform: uppercase; letter-spacing: .5px;
      color: var(--gray); padding: 0 12px 10px;
      border-bottom: 1px solid var(--border);
    }
    .adm-table td {
      padding: 13px 12px;
      font-size: 13px; color: var(--navy);
      border-bottom: 1px solid #f1f3f8;
      vertical-align: middle;
    }
    .adm-table tr:last-child td { border-bottom: none; }
    .adm-table tr:hover td { background: #f8f9fc; }

    .ord-badge {
      display: inline-block; padding: 3px 10px;
      border-radius: 20px; font-size: 11px; font-weight: 700;
    }
    .badge-pending    { background: #fef3c7; color: #92400e; }
    .badge-processing { background: #dbeafe; color: #1e40af; }
    .badge-shipped    { background: #ede9fe; color: #5b21b6; }
    .badge-delivered  { background: #dcfce7; color: #166534; }
    .badge-cancelled  { background: #fee2e2; color: #991b1b; }

    .adm-link {
      color: var(--navy); font-weight: 600; text-decoration: none;
    }
    .adm-link:hover { text-decoration: underline; }

    /* ── Charts section ── */
    .charts-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-bottom: 20px;
    }
    @media (max-width: 900px) { .charts-grid { grid-template-columns: 1fr; } }
    .chart-card {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 22px 24px;
    }
    .chart-card-full { grid-column: 1 / -1; }
    .chart-title {
      font-size: 15px; font-weight: 800; color: var(--navy);
      margin-bottom: 16px; display: flex; align-items: center; gap: 8px;
    }
    .chart-title [data-lucide] { width: 16px; height: 16px; }
    .chart-canvas-wrap { position: relative; height: 220px; }
    .change-pill {
      display: inline-flex; align-items: center; gap: 4px;
      padding: 2px 9px; border-radius: 20px;
      font-size: 12px; font-weight: 700; margin-left: 8px;
    }
    .change-up   { background: #dcfce7; color: #166534; }
    .change-down { background: #fee2e2; color: #991b1b; }
    .change-flat { background: #f3f4f6; color: #6b7280; }
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
      <a href="index.php" class="active"><i data-lucide="layout-dashboard"></i> Dashboard</a>

      <div class="nav-section">Store</div>
      <a href="orders.php"><i data-lucide="package"></i> Orders</a>
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

    <div class="adm-topbar">
      <h1>Dashboard</h1>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-card-top">
          <div>
            <div class="stat-card-val"><?= number_format($stats['orders_total']) ?></div>
            <div class="stat-card-lbl">Total Orders</div>
          </div>
          <div class="stat-card-ico ico-blue"><i data-lucide="shopping-bag"></i></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-top">
          <div>
            <div class="stat-card-val"><?= number_format($stats['orders_pending']) ?></div>
            <div class="stat-card-lbl">Pending Orders</div>
          </div>
          <div class="stat-card-ico ico-yellow"><i data-lucide="clock"></i></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-top">
          <div>
            <div class="stat-card-val"><?= number_format($stats['orders_today']) ?></div>
            <div class="stat-card-lbl">Orders Today</div>
          </div>
          <div class="stat-card-ico ico-green"><i data-lucide="calendar-check"></i></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-top">
          <div>
            <div class="stat-card-val"><?= 'UGX ' . number_format($stats['revenue_total'] / 1000, 0) ?>k</div>
            <div class="stat-card-lbl">
              Total Revenue
              <?php if ($monthChange !== 0): ?>
                <span class="change-pill <?= $monthChange > 0 ? 'change-up' : 'change-down' ?>">
                  <?= $monthChange > 0 ? '↑' : '↓' ?> <?= abs($monthChange) ?>% vs last month
                </span>
              <?php endif; ?>
            </div>
          </div>
          <div class="stat-card-ico ico-purple"><i data-lucide="banknote"></i></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-top">
          <div>
            <div class="stat-card-val"><?= number_format($stats['customers']) ?></div>
            <div class="stat-card-lbl">Customers</div>
          </div>
          <div class="stat-card-ico ico-navy"><i data-lucide="users"></i></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-top">
          <div>
            <div class="stat-card-val"><?= number_format($stats['products']) ?></div>
            <div class="stat-card-lbl">Products</div>
          </div>
          <div class="stat-card-ico ico-red"><i data-lucide="box"></i></div>
        </div>
      </div>
    </div>

    <!-- Charts -->
    <div class="charts-grid">

      <!-- Revenue — last 30 days (line) -->
      <div class="chart-card chart-card-full">
        <div class="chart-title">
          <i data-lucide="trending-up"></i> Revenue — Last 30 Days
          <span style="font-size:13px;font-weight:400;color:var(--gray);margin-left:auto;">
            This month: <strong style="color:var(--navy);">UGX <?= number_format($thisMonth / 1000, 0) ?>k</strong>
            <?php if ($monthChange !== 0): ?>
              <span class="change-pill <?= $monthChange > 0 ? 'change-up' : 'change-down' ?>">
                <?= $monthChange > 0 ? '↑' : '↓' ?> <?= abs($monthChange) ?>%
              </span>
            <?php endif; ?>
          </span>
        </div>
        <div class="chart-canvas-wrap"><canvas id="revenueChart"></canvas></div>
      </div>

      <!-- Order status breakdown (doughnut) -->
      <div class="chart-card">
        <div class="chart-title"><i data-lucide="pie-chart"></i> Orders by Status</div>
        <div class="chart-canvas-wrap"><canvas id="statusChart"></canvas></div>
      </div>

      <!-- Top 5 products by revenue (bar) -->
      <div class="chart-card">
        <div class="chart-title"><i data-lucide="bar-chart-2"></i> Top 5 Products by Revenue</div>
        <div class="chart-canvas-wrap"><canvas id="topProductsChart"></canvas></div>
      </div>

    </div>

    <!-- Recent orders -->
    <div class="adm-card">
      <div class="adm-card-head">
        <h2><i data-lucide="package"></i> Recent Orders</h2>
        <a href="orders.php">View all →</a>
      </div>
      <?php if (empty($recent)): ?>
        <p style="color:var(--gray);font-size:14px;">No orders yet.</p>
      <?php else: ?>
        <table class="adm-table">
          <thead>
            <tr>
              <th>Order</th>
              <th>Customer</th>
              <th>Total</th>
              <th>Status</th>
              <th>Date</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $o): ?>
              <tr>
                <td><strong><?= e($o['order_number']) ?></strong></td>
                <td><?= e($o['customer_name']) ?></td>
                <td><?= formatPrice((float)$o['total']) ?></td>
                <td>
                  <?php
                    $cls = ['pending'=>'badge-pending','processing'=>'badge-processing','shipped'=>'badge-shipped','delivered'=>'badge-delivered','cancelled'=>'badge-cancelled'];
                    $lbl = ['pending'=>'Pending','processing'=>'Processing','shipped'=>'Shipped','delivered'=>'Delivered','cancelled'=>'Cancelled'];
                  ?>
                  <span class="ord-badge <?= $cls[$o['status']] ?? 'badge-pending' ?>">
                    <?= $lbl[$o['status']] ?? ucfirst($o['status']) ?>
                  </span>
                </td>
                <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                <td><a href="orders.php?id=<?= (int)$o['id'] ?>" class="adm-link">View</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

  </main>
</div>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();

  /* ── Shared chart defaults ── */
  Chart.defaults.font.family = "'Segoe UI', Arial, sans-serif";
  Chart.defaults.font.size   = 12;
  Chart.defaults.color       = '#6b7280';

  /* ── Revenue line chart ── */
  new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
      labels: <?= json_encode($revenueLabels) ?>,
      datasets: [{
        label: 'Revenue (UGX)',
        data: <?= json_encode($revenueData) ?>,
        borderColor: '#2563eb',
        backgroundColor: 'rgba(37,99,235,.08)',
        borderWidth: 2.5,
        pointRadius: 2,
        pointHoverRadius: 5,
        tension: 0.4,
        fill: true,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => 'UGX ' + Number(ctx.parsed.y).toLocaleString()
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          grid: { color: '#f1f3f8' },
          ticks: {
            callback: v => v >= 1000 ? (v/1000).toFixed(0) + 'k' : v
          }
        },
        x: { grid: { display: false } }
      }
    }
  });

  /* ── Status doughnut chart ── */
  const statusLabels = <?= json_encode($statusLabels) ?>;
  new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
      labels: statusLabels,
      datasets: [{
        data: <?= json_encode($statusData) ?>,
        backgroundColor: <?= json_encode($statusBgColors) ?>,
        borderWidth: 2,
        borderColor: '#fff',
        hoverOffset: 6,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '60%',
      plugins: {
        legend: {
          position: 'right',
          labels: { padding: 14, usePointStyle: true, pointStyleWidth: 10 }
        }
      }
    }
  });

  /* ── Top products bar chart ── */
  new Chart(document.getElementById('topProductsChart'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($topLabels) ?>,
      datasets: [{
        label: 'Revenue (UGX)',
        data: <?= json_encode($topRevenue) ?>,
        backgroundColor: [
          'rgba(37,99,235,.8)',
          'rgba(124,58,237,.8)',
          'rgba(16,185,129,.8)',
          'rgba(245,158,11,.8)',
          'rgba(239,68,68,.8)',
        ],
        borderRadius: 6,
        borderWidth: 0,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      indexAxis: 'y',
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => 'UGX ' + Number(ctx.parsed.x).toLocaleString()
          }
        }
      },
      scales: {
        x: {
          beginAtZero: true,
          grid: { color: '#f1f3f8' },
          ticks: { callback: v => v >= 1000 ? (v/1000).toFixed(0)+'k' : v }
        },
        y: { grid: { display: false } }
      }
    }
  });
});
</script>
</body>
</html>
