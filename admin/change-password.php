<?php
/* ═══════════════════════════════════════════════════════════════════════
   admin/change-password.php — Change admin password
   ═══════════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';

adminGuard();

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($current === '' || $new === '' || $confirm === '') {
        $error = 'All fields are required.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        $stmt = $pdo->prepare('SELECT password_hash FROM admin_users WHERE id = ?');
        $stmt->execute([adminId()]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($current, $row['password_hash'])) {
            $error = 'Current password is incorrect.';
        } else {
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE admin_users SET password_hash = ?, must_change_pw = 0 WHERE id = ?')
                ->execute([$newHash, adminId()]);
            $success = 'Password changed successfully.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Change Password — Admin — Gadget Genesis</title>
  <link rel="stylesheet" href="../style.css"/>
  <style>
    body { background: #f1f3f8; }
    .adm-wrap { display: flex; min-height: 100vh; }

    /* ── Sidebar (shared) ── */
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
    .adm-admin-info { font-size:12px;color:rgba(255,255,255,.4);padding:0 0 4px; }

    .adm-main { margin-left:220px;flex:1;padding:32px; }
    .adm-topbar { display:flex;align-items:center;justify-content:space-between;margin-bottom:24px; }
    .adm-topbar h1 { font-size:22px;font-weight:800;color:var(--navy); }

    /* ── Forced change banner ── */
    .forced-banner {
      background: #fef3c7;
      border: 1px solid #fde68a;
      border-radius: 12px;
      padding: 14px 18px;
      font-size: 13px;
      color: #92400e;
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .forced-banner [data-lucide] { width: 18px; height: 18px; flex-shrink: 0; }

    /* ── Form card ── */
    .pw-card {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 32px;
      max-width: 460px;
    }
    .pw-card h2 { font-size: 18px; font-weight: 800; color: var(--navy); margin-bottom: 6px; }
    .pw-card > p { font-size: 13px; color: var(--gray); margin-bottom: 26px; }

    .auth-field { display:flex;flex-direction:column;gap:6px;margin-bottom:18px; }
    .auth-field label { font-size:13px;font-weight:600;color:var(--navy); }
    .auth-field input {
      padding:11px 14px;border:1.5px solid var(--border);border-radius:9px;
      font-size:14px;font-family:inherit;color:var(--navy);outline:none;
      transition:border-color .15s,box-shadow .15s;
    }
    .auth-field input:focus { border-color:var(--navy);box-shadow:0 0 0 3px rgba(10,25,49,.08); }
    .field-hint { font-size:12px;color:var(--gray); }

    .btn-save {
      padding:12px 28px;background:var(--navy);color:#fff;
      border:none;border-radius:9px;font-size:14px;font-weight:700;
      cursor:pointer;font-family:inherit;transition:opacity .15s;
      display:inline-flex;align-items:center;gap:8px;
    }
    .btn-save:hover { opacity:.88; }
    .btn-save [data-lucide] { width:16px;height:16px; }

    .alert { padding:13px 16px;border-radius:9px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:8px; }
    .alert-success { background:#f0fdf4;border:1px solid #bbf7d0;color:#166534; }
    .alert-error   { background:#fef2f2;border:1px solid #fecaca;color:#b91c1c; }
    .alert [data-lucide] { width:16px;height:16px;flex-shrink:0; }
  </style>
</head>
<body>
<div class="adm-wrap">

  <!-- Sidebar -->
  <aside class="adm-sidebar">
    <div class="adm-logo">
      <img src="../logo.jpg" alt="Gadget Genesis"/>
      <span>Admin Panel</span>
    </div>
    <nav class="adm-nav">
      <div class="nav-section">Overview</div>
      <a href="index.php"><i data-lucide="layout-dashboard"></i> Dashboard</a>
      <div class="nav-section">Store</div>
      <a href="orders.php"><i data-lucide="package"></i> Orders</a>
      <a href="products.php"><i data-lucide="box"></i> Products</a>
      <a href="add-product.php"><i data-lucide="plus-circle"></i> Add Product</a>
      <a href="discounts.php"><i data-lucide="percent"></i> Discounts</a>
      <a href="coupons.php"><i data-lucide="tag"></i> Coupons</a>
      <a href="customers.php"><i data-lucide="users"></i> Customers</a>
      <a href="reviews.php"><i data-lucide="star"></i> Reviews</a>
      <div class="nav-section">Account</div>
      <a href="change-password.php" class="active"><i data-lucide="key-round"></i> Change Password</a>
    </nav>
    <div class="adm-footer">
      <span class="adm-admin-info"><i data-lucide="user" style="width:12px;height:12px;vertical-align:-1px;"></i> <?= htmlspecialchars(adminUsername(), ENT_QUOTES, 'UTF-8') ?></span>
      <a href="logout.php"><i data-lucide="log-out"></i> Sign Out</a>
    </div>
  </aside>

  <main class="adm-main">
    <div class="adm-topbar">
      <h1>Change Password</h1>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success"><i data-lucide="circle-check"></i> <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error"><i data-lucide="circle-x"></i> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="pw-card">
      <h2>Set New Password</h2>
      <p>Choose a strong password — at least 8 characters.</p>

      <form method="POST" action="change-password.php">
        <div class="auth-field">
          <label for="current_password">Current Password</label>
          <input type="password" id="current_password" name="current_password"
                 placeholder="Your current password" autocomplete="current-password" required/>
        </div>
        <div class="auth-field">
          <label for="new_password">New Password</label>
          <input type="password" id="new_password" name="new_password"
                 placeholder="At least 8 characters" autocomplete="new-password" required/>
          <span class="field-hint">Minimum 8 characters</span>
        </div>
        <div class="auth-field">
          <label for="confirm_password">Confirm New Password</label>
          <input type="password" id="confirm_password" name="confirm_password"
                 placeholder="Repeat new password" autocomplete="new-password" required/>
        </div>
        <button type="submit" class="btn-save">
          <i data-lucide="save"></i> Update Password
        </button>
      </form>
    </div>
  </main>
</div>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => { if (window.lucide) lucide.createIcons(); });</script>
</body>
</html>
