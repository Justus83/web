<?php
/* ═══════════════════════════════════════════════════════════════════════
   admin/reviews.php — Moderate product reviews (approve / reject / delete)
   ═══════════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/auth.php';
adminGuard();

$activePage = 'reviews';
$success = '';
$error   = '';

/* ── Handle POST actions ───────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'approve') {
        $pdo->prepare("UPDATE product_reviews SET approved = 1 WHERE id = ?")->execute([$id]);
        $success = 'Review approved.';
    } elseif ($action === 'reject') {
        $pdo->prepare("UPDATE product_reviews SET approved = 0 WHERE id = ?")->execute([$id]);
        $success = 'Review hidden.';
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM product_reviews WHERE id = ?")->execute([$id]);
        $success = 'Review deleted.';
    }
}

/* ── Filters ────────────────────────────────────────────────────────── */
$filter  = $_GET['filter'] ?? 'all';
$search  = trim($_GET['q']  ?? '');

$where  = [];
$params = [];

if ($filter === 'pending')   { $where[] = 'r.approved = 0'; }
if ($filter === 'approved')   { $where[] = 'r.approved = 1'; }
if ($search !== '') {
    $where[] = '(p.model LIKE :q OR r.name LIKE :q OR r.body LIKE :q)';
    $params[':q'] = '%' . $search . '%';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT r.id, r.name, r.rating, r.body, r.approved, r.created_at,
           p.id AS product_id, p.model AS product_name
    FROM product_reviews r
    JOIN products p ON p.id = r.product_id
    $whereSql
    ORDER BY r.created_at DESC
");
$stmt->execute($params);
$reviews = $stmt->fetchAll();

$pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM product_reviews WHERE approved = 0")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Reviews — Admin — Gadget Genesis</title>
  <link rel="stylesheet" href="../style.css"/>
  <style>
    body { background: #f1f3f8; }
    .adm-wrap { display:flex; min-height:100vh; }
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
    .adm-topbar { display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px; }
    .adm-topbar h1 { font-size:22px;font-weight:800;color:var(--navy); }

    .filters-bar { display:flex;gap:10px;background:#fff;border:1px solid var(--border);border-radius:12px;padding:14px 18px;margin-bottom:20px;align-items:center;flex-wrap:wrap; }
    .filter-input { padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;color:var(--navy);outline:none;min-width:200px; }
    .filter-input:focus { border-color:var(--navy); }
    .btn-filter { padding:9px 18px;background:var(--navy);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:6px; }
    .btn-filter [data-lucide] { width:14px;height:14px; }
    .filter-tabs { display:flex;gap:6px; }
    .filter-tab { padding:7px 16px;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;border:1.5px solid var(--border);color:var(--navy);transition:all .15s; }
    .filter-tab:hover { background:#f8faff; }
    .filter-tab.active { background:var(--navy);color:#fff;border-color:var(--navy); }
    .badge-count { display:inline-flex;align-items:center;justify-content:center;background:#ef4444;color:#fff;border-radius:50%;font-size:10px;font-weight:700;min-width:18px;height:18px;padding:0 4px;margin-left:4px; }

    .alert { padding:12px 16px;border-radius:9px;font-size:13px;margin-bottom:16px; }
    .alert-success { background:#f0fdf4;border:1px solid #bbf7d0;color:#166534; }

    .review-card { background:#fff;border:1px solid var(--border);border-radius:12px;padding:20px 24px;margin-bottom:14px; }
    .review-top { display:flex;justify-content:space-between;align-items:flex-start;gap:16px; }
    .review-info { flex:1; }
    .review-product { font-size:13px;font-weight:700;color:var(--navy);margin-bottom:4px; }
    .review-product a { color:#2563eb;text-decoration:none; }
    .review-meta { display:flex;align-items:center;gap:8px;margin-bottom:8px; }
    .review-author { font-size:14px;font-weight:600;color:var(--navy); }
    .review-date { font-size:12px;color:var(--gray); }
    .review-stars { display:flex;gap:1px; }
    .review-stars [data-lucide] { width:14px;height:14px; }
    .review-body { font-size:14px;color:#374151;line-height:1.6;margin-top:8px; }
    .review-actions { display:flex;gap:6px;flex-shrink:0; }
    .btn-sm { padding:6px 14px;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;border:none; }
    .btn-approve { background:#dcfce7;color:#166534; }
    .btn-reject  { background:#fef3c7;color:#92400e; }
    .btn-delete  { background:#fee2e2;color:#dc2626; }
    .btn-sm:hover { opacity:.85; }
    .pill-approved { background:#dcfce7;color:#166534;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700; }
    .pill-pending  { background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700; }
    .empty-state { text-align:center;padding:48px 20px;color:var(--gray);font-size:14px; }
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
      <a href="coupons.php"><i data-lucide="tag"></i> Coupons</a>
      <a href="customers.php"><i data-lucide="users"></i> Customers</a>
      <a href="reviews.php" class="active"><i data-lucide="star"></i> Reviews<?= $pendingCount > 0 ? '<span class="badge-count">' . $pendingCount . '</span>' : '' ?></a>
      <div class="nav-section">Account</div>
      <a href="change-password.php"><i data-lucide="key-round"></i> Change Password</a>
    </nav>
    <div class="adm-footer">
      <span style="font-size:12px;color:rgba(255,255,255,.4);display:flex;align-items:center;gap:6px;">
        <i data-lucide="user" style="width:12px;height:12px;"></i>
        <?= htmlspecialchars(adminUsername(), ENT_QUOTES, 'UTF-8') ?>
      </span>
      <a href="change-password.php"><i data-lucide="key-round"></i> Change Password</a>
      <a href="logout.php"><i data-lucide="log-out"></i> Sign Out</a>
    </div>
  </aside>

  <main class="adm-main">
    <div class="adm-topbar"><h1>Reviews</h1></div>

    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

    <!-- Filters -->
    <div class="filters-bar">
      <div class="filter-tabs">
        <a href="reviews.php?filter=all<?= $search ? '&q=' . urlencode($search) : '' ?>"     class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">All</a>
        <a href="reviews.php?filter=pending<?= $search ? '&q=' . urlencode($search) : '' ?>"  class="filter-tab <?= $filter === 'pending' ? 'active' : '' ?>">Pending<?= $pendingCount > 0 ? '<span class="badge-count">' . $pendingCount . '</span>' : '' ?></a>
        <a href="reviews.php?filter=approved<?= $search ? '&q=' . urlencode($search) : '' ?>" class="filter-tab <?= $filter === 'approved' ? 'active' : '' ?>">Approved</a>
      </div>
      <form method="GET" style="display:flex;gap:8px;align-items:center;flex:1;justify-content:flex-end;">
        <input type="hidden" name="filter" value="<?= e($filter) ?>"/>
        <input type="text" name="q" class="filter-input" placeholder="Search product, author, text…" value="<?= e($search) ?>"/>
        <button type="submit" class="btn-filter"><i data-lucide="search"></i> Search</button>
      </form>
    </div>

    <!-- Review list -->
    <?php if (empty($reviews)): ?>
      <div class="empty-state">No reviews found.</div>
    <?php else: ?>
      <?php foreach ($reviews as $r): ?>
        <div class="review-card">
          <div class="review-top">
            <div class="review-info">
              <div class="review-product">
                <a href="../product.php?id=<?= (int)$r['product_id'] ?>"><?= e($r['product_name']) ?></a>
                <span class="<?= $r['approved'] ? 'pill-approved' : 'pill-pending' ?>" style="margin-left:8px;"><?= $r['approved'] ? 'Approved' : 'Pending' ?></span>
              </div>
              <div class="review-meta">
                <span class="review-author"><?= e($r['name']) ?></span>
                <span class="review-stars">
                  <?php for ($s=1;$s<=5;$s++): ?>
                    <i data-lucide="star" style="color: <?= $s <= (int)$r['rating'] ? '#f59e0b' : '#d1d5db' ?>; <?= $s <= (int)$r['rating'] ? 'fill:#f59e0b;' : '' ?>"></i>
                  <?php endfor; ?>
                </span>
                <span class="review-date"><?= date('d M Y', strtotime($r['created_at'])) ?></span>
              </div>
              <p class="review-body"><?= e($r['body']) ?></p>
            </div>
            <div class="review-actions">
              <?php if (!$r['approved']): ?>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="action" value="approve"/>
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>"/>
                  <button type="submit" class="btn-sm btn-approve">Approve</button>
                </form>
              <?php else: ?>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="action" value="reject"/>
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>"/>
                  <button type="submit" class="btn-sm btn-reject">Hide</button>
                </form>
              <?php endif; ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete this review permanently?')">
                <input type="hidden" name="action" value="delete"/>
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>"/>
                <button type="submit" class="btn-sm btn-delete">Delete</button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>
</div>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => { if (window.lucide) lucide.createIcons(); });</script>
</body>
</html>
