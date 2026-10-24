<?php
/* ═══════════════════════════════════════════════════════════════════════
   admin/coupons.php — Manage discount coupons
   ═══════════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/auth.php';
adminGuard();

$success = '';
$error   = '';

/* ── Handle POST actions ───────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $code     = strtoupper(trim($_POST['code']         ?? ''));
        $pct      = (int)   ($_POST['discount_pct']        ?? 0);
        $maxUses  = $_POST['max_uses'] !== '' ? (int) $_POST['max_uses'] : null;
        $expires  = trim($_POST['expires_at'] ?? '') ?: null;
        $active   = isset($_POST['active']) ? 1 : 0;

        if ($code === '')     { $error = 'Coupon code is required.'; }
        elseif ($pct < 1 || $pct > 100) { $error = 'Discount must be between 1 and 100%.'; }
        else {
            try {
                $pdo->prepare("INSERT INTO coupons (code, discount_pct, max_uses, expires_at, active)
                                VALUES (:code, :pct, :max, :exp, :active)")
                    ->execute([':code' => $code, ':pct' => $pct, ':max' => $maxUses, ':exp' => $expires, ':active' => $active]);
                $success = "Coupon $code created.";
            } catch (PDOException $e) {
                $error = 'That coupon code already exists.';
            }
        }
    }

    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE coupons SET active = 1 - active WHERE id = ?")->execute([$id]);
        $success = 'Coupon updated.';
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM coupons WHERE id = ?")->execute([$id]);
        $success = 'Coupon deleted.';
    }

    if ($action === 'edit') {
        $id       = (int) ($_POST['id'] ?? 0);
        $pct      = (int)   ($_POST['discount_pct'] ?? 0);
        $maxUses  = $_POST['max_uses'] !== '' ? (int) $_POST['max_uses'] : null;
        $expires  = trim($_POST['expires_at'] ?? '') ?: null;
        $active   = isset($_POST['active']) ? 1 : 0;

        if ($pct < 1 || $pct > 100) { $error = 'Discount must be between 1 and 100%.'; }
        else {
            $pdo->prepare("UPDATE coupons SET discount_pct=:pct, max_uses=:max, expires_at=:exp, active=:active WHERE id=:id")
                ->execute([':pct' => $pct, ':max' => $maxUses, ':exp' => $expires, ':active' => $active, ':id' => $id]);
            $success = 'Coupon updated.';
        }
    }
}

// Load coupon for editing if ?edit=X
$editCoupon = null;
if (!empty($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE id = ?");
    $stmt->execute([(int) $_GET['edit']]);
    $editCoupon = $stmt->fetch();
}

$coupons = $pdo->query("SELECT * FROM coupons ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Coupons — Admin — Gadget Genesis</title>
  <link rel="stylesheet" href="../style.css"/>
  <style>
    body { background: #f1f3f8; }
    .adm-wrap { display: flex; min-height: 100vh; }
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

    .adm-card { background:#fff;border:1px solid var(--border);border-radius:14px;padding:24px;margin-bottom:20px; }
    .adm-card h2 { font-size:16px;font-weight:800;color:var(--navy);margin-bottom:18px;display:flex;align-items:center;gap:8px; }

    .form-row { display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;margin-bottom:16px; }
    .form-field { display:flex;flex-direction:column;gap:5px; }
    .form-field label { font-size:12px;font-weight:700;color:var(--navy); }
    .form-field input, .form-field select { padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;color:var(--navy);outline:none; }
    .form-field input:focus { border-color:var(--navy); }

    .btn-create { padding:10px 22px;background:var(--navy);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit; }
    .btn-create:hover { opacity:.88; }

    .alert { padding:12px 16px;border-radius:9px;font-size:13px;margin-bottom:16px; }
    .alert-success { background:#f0fdf4;border:1px solid #bbf7d0;color:#166534; }
    .alert-error   { background:#fef2f2;border:1px solid #fecaca;color:#b91c1c; }

    .adm-table { width:100%;border-collapse:collapse; }
    .adm-table th { text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--gray);padding:10px 14px;border-bottom:1px solid var(--border); }
    .adm-table td { padding:12px 14px;font-size:13px;color:var(--navy);border-bottom:1px solid #f1f3f8;vertical-align:middle; }
    .adm-table tr:last-child td { border-bottom:none; }

    .pill-active   { background:#dcfce7;color:#166534;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700; }
    .pill-inactive { background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700; }

    .btn-sm { padding:5px 12px;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;border:none; }
    .btn-toggle { background:#e0e7ff;color:var(--navy); }
    .btn-delete { background:#fee2e2;color:#dc2626; }
    .btn-sm:hover { opacity:.8; }
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
      <a href="coupons.php" class="active"><i data-lucide="tag"></i> Coupons</a>
      <a href="customers.php"><i data-lucide="users"></i> Customers</a>
      <a href="reviews.php"><i data-lucide="star"></i> Reviews</a>
    </nav>
    <div class="adm-footer">
      <span style="font-size:12px;color:rgba(255,255,255,.4);padding-bottom:2px;display:flex;align-items:center;gap:6px;"><i data-lucide="user" style="width:12px;height:12px;"></i> <?= htmlspecialchars(adminUsername(), ENT_QUOTES, 'UTF-8') ?></span>
      <a href="change-password.php"><i data-lucide="key-round"></i> Change Password</a>
      <a href="logout.php"><i data-lucide="log-out"></i> Sign Out</a>
    </div>
  </aside>

  <main class="adm-main">
    <div class="adm-topbar"><h1>Coupons</h1></div>

    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <!-- Create / Edit coupon -->
    <div class="adm-card">
      <h2>
        <i data-lucide="<?= $editCoupon ? 'edit' : 'plus-circle' ?>"></i>
        <?= $editCoupon ? 'Edit Coupon — ' . e($editCoupon['code']) : 'Create Coupon' ?>
      </h2>
      <form method="POST">
        <input type="hidden" name="action" value="<?= $editCoupon ? 'edit' : 'create' ?>"/>
        <?php if ($editCoupon): ?>
          <input type="hidden" name="id" value="<?= (int)$editCoupon['id'] ?>"/>
        <?php endif; ?>
        <div class="form-row">
          <div class="form-field">
            <label>Code <?= $editCoupon ? '' : '*' ?></label>
            <?php if ($editCoupon): ?>
              <input type="text" value="<?= e($editCoupon['code']) ?>" disabled style="background:#f3f4f6;opacity:.7"/>
            <?php else: ?>
              <input type="text" name="code" placeholder="e.g. SAVE10" required style="text-transform:uppercase"/>
            <?php endif; ?>
          </div>
          <div class="form-field">
            <label>Discount % *</label>
            <input type="number" name="discount_pct" min="1" max="100" placeholder="e.g. 10" required
                   value="<?= $editCoupon ? (int)$editCoupon['discount_pct'] : '' ?>"/>
          </div>
          <div class="form-field">
            <label>Max Uses (blank = unlimited)</label>
            <input type="number" name="max_uses" min="1" placeholder="e.g. 100"
                   value="<?= $editCoupon && $editCoupon['max_uses'] !== null ? (int)$editCoupon['max_uses'] : '' ?>"/>
          </div>
          <div class="form-field">
            <label>Expires (blank = never)</label>
            <input type="datetime-local" name="expires_at"
                   value="<?= $editCoupon && $editCoupon['expires_at'] ? date('Y-m-d\TH:i', strtotime($editCoupon['expires_at'])) : '' ?>"/>
          </div>
          <div class="form-field" style="justify-content:flex-end;padding-bottom:2px;">
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
              <input type="checkbox" name="active" value="1" <?= (!$editCoupon || $editCoupon['active']) ? 'checked' : '' ?>/> Active
            </label>
          </div>
        </div>
        <div style="display:flex;gap:10px;align-items:center;">
          <button type="submit" class="btn-create"><?= $editCoupon ? 'Save Changes' : 'Create Coupon' ?></button>
          <?php if ($editCoupon): ?>
            <a href="coupons.php" class="btn-cancel-link" style="padding:10px 18px;background:#fff;color:var(--navy);border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">Cancel</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Existing coupons -->
    <div class="adm-card">
      <h2><i data-lucide="tag"></i> All Coupons (<?= count($coupons) ?>)</h2>
      <?php if (empty($coupons)): ?>
        <p style="color:var(--gray);font-size:14px;">No coupons yet.</p>
      <?php else: ?>
        <table class="adm-table">
          <thead><tr><th>Code</th><th>Discount</th><th>Used</th><th>Max Uses</th><th>Expires</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($coupons as $c): ?>
              <tr>
                <td><strong><?= e($c['code']) ?></strong></td>
                <td><?= (int)$c['discount_pct'] ?>%</td>
                <td><?= (int)$c['used_count'] ?></td>
                <td><?= $c['max_uses'] ?? '∞' ?></td>
                <td><?= $c['expires_at'] ? date('d M Y', strtotime($c['expires_at'])) : 'Never' ?></td>
                <td><span class="<?= $c['active'] ? 'pill-active' : 'pill-inactive' ?>"><?= $c['active'] ? 'Active' : 'Inactive' ?></span></td>
                <td style="display:flex;gap:6px;">
                  <a href="coupons.php?edit=<?= (int)$c['id'] ?>" class="btn-sm" style="background:#e0e7ff;color:var(--navy);text-decoration:none;display:inline-flex;align-items:center;padding:5px 12px;border-radius:6px;font-size:12px;font-weight:700;">Edit</a>
                  <form method="POST" style="display:inline">
                    <input type="hidden" name="action" value="toggle"/>
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>"/>
                    <button type="submit" class="btn-sm btn-toggle"><?= $c['active'] ? 'Disable' : 'Enable' ?></button>
                  </form>
                  <form method="POST" style="display:inline" onsubmit="return confirm('Delete coupon <?= e($c['code']) ?>?')">
                    <input type="hidden" name="action" value="delete"/>
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>"/>
                    <button type="submit" class="btn-sm btn-delete">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </main>
</div>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => { if (window.lucide) lucide.createIcons(); });</script>
</body>
</html>
