<?php
/* ═══════════════════════════════════════════════════════════════════════
   account.php — Register / Login / Customer Dashboard
   ═══════════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/auth.php';

$customer = authCustomerFull($pdo);
$isLoggedIn = $customer !== null;

/* ── Fetch orders for logged-in customer ───────────────────────────── */
$orders = [];
if ($isLoggedIn) {
    $stmt = $pdo->prepare("
        SELECT id, order_number, payment_method, shipping_method,
               total, status, created_at
        FROM orders
        WHERE customer_id = :cid
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute([':cid' => $customer['id']]);
    $orders = $stmt->fetchAll();
}

/* ── Status badge ──────────────────────────────────────────────────── */
function statusBadge(string $status): string {
    $map = [
        'pending'    => ['Pending',    'badge-pending'],
        'processing' => ['Processing', 'badge-processing'],
        'shipped'    => ['Shipped',    'badge-shipped'],
        'delivered'  => ['Delivered',  'badge-delivered'],
        'cancelled'  => ['Cancelled',  'badge-cancelled'],
    ];
    [$label, $cls] = $map[$status] ?? [ucfirst($status), 'badge-pending'];
    return "<span class=\"ord-badge $cls\">$label</span>";
}

/* ── Which tab to open by default ──────────────────────────────────── */
$defaultTab = $_GET['tab'] ?? 'login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>My Account — Gadget Genesis</title>
  <link rel="stylesheet" href="style.css"/>
  <?php require_once __DIR__ . '/config/theme-init.php'; ?>
  <style>
    /* ── Page shell ── */
    .account-wrap {
      max-width: 640px;
      margin: 0 auto;
      padding: 40px 20px 80px;
    }
    .account-page-title {
      font-size: 24px; font-weight: 800; color: var(--navy);
      margin-bottom: 28px;
    }

    /* ── Auth tabs ── */
    .account-tabs {
      display: flex;
      border-bottom: 2px solid var(--border);
      margin-bottom: 28px;
    }
    .account-tab {
      padding: 12px 26px;
      font-size: 14px; font-weight: 600;
      color: var(--gray);
      cursor: pointer;
      border: none; border-bottom: 2px solid transparent;
      margin-bottom: -2px;
      background: none;
      font-family: inherit;
      transition: color .15s, border-color .15s;
    }
    .account-tab:hover  { color: var(--navy); }
    .account-tab.active { color: var(--navy); border-bottom-color: var(--navy); }
    .account-panel      { display: none; }
    .account-panel.active { display: block; }

    /* ── Auth card ── */
    .account-card {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 32px;
    }
    .account-card h2 {
      font-size: 20px; font-weight: 800; color: var(--navy);
      margin-bottom: 4px;
    }
    .account-card > p {
      font-size: 13px; color: var(--gray);
      margin-bottom: 24px; line-height: 1.6;
    }

    /* ── Form fields ── */
    .auth-field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
    .auth-field label { font-size: 13px; font-weight: 600; color: var(--navy); }
    .auth-field input {
      padding: 11px 14px;
      border: 1.5px solid var(--border); border-radius: 9px;
      font-size: 14px; outline: none; font-family: inherit;
      color: var(--navy);
      transition: border-color .15s, box-shadow .15s;
    }
    .auth-field input:focus {
      border-color: var(--navy);
      box-shadow: 0 0 0 3px rgba(10,25,49,.08);
    }
    .auth-field input.error { border-color: #ef4444; }
    .field-hint { font-size: 12px; color: var(--gray); margin-top: 2px; }

    /* ── Buttons ── */
    .btn-auth {
      width: 100%; padding: 13px;
      background: var(--navy); color: #fff;
      border: none; border-radius: 9px;
      font-size: 14px; font-weight: 700;
      cursor: pointer; font-family: inherit;
      transition: opacity .15s;
      margin-top: 4px;
      display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .btn-auth:hover    { opacity: .88; }
    .btn-auth:disabled { opacity: .55; cursor: not-allowed; }

    /* ── Error / success alerts ── */
    .auth-alert {
      padding: 12px 16px;
      border-radius: 9px;
      font-size: 13px;
      margin-bottom: 18px;
      display: none;
      align-items: center;
      gap: 8px;
    }
    .auth-alert.show { display: flex; }
    .auth-alert.error   { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
    .auth-alert.success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }

    /* ══════════════════════════════════════════
       DASHBOARD
    ══════════════════════════════════════════ */
    .dash-header {
      background: linear-gradient(120deg, var(--navy), #1e40af);
      border-radius: 14px;
      padding: 28px 32px;
      color: #fff;
      display: flex;
      align-items: center;
      gap: 20px;
      margin-bottom: 28px;
    }
    .dash-avatar {
      width: 60px; height: 60px;
      background: rgba(255,255,255,.15);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 24px; font-weight: 800;
      flex-shrink: 0;
    }
    .dash-welcome strong { display: block; font-size: 20px; font-weight: 800; }
    .dash-welcome span   { font-size: 13px; opacity: .8; }

    /* ── Dash sections ── */
    .dash-section {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 24px 28px;
      margin-bottom: 20px;
    }
    .dash-section-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
    }
    .dash-section-head h3 {
      font-size: 16px; font-weight: 800; color: var(--navy);
      display: flex; align-items: center; gap: 8px;
    }
    .dash-section-head a {
      font-size: 13px; font-weight: 600; color: var(--navy);
      text-decoration: none;
    }
    .dash-section-head a:hover { text-decoration: underline; }

    /* ── Address form ── */
    .address-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .address-fields .full { grid-column: 1 / -1; }
    .address-save-row { margin-top: 16px; display: flex; justify-content: flex-end; }
    .btn-save-address {
      padding: 10px 24px;
      background: var(--navy); color: #fff;
      border: none; border-radius: 8px;
      font-size: 13px; font-weight: 700;
      cursor: pointer; font-family: inherit;
      transition: opacity .15s;
    }
    .btn-save-address:hover { opacity: .88; }

    /* ── Orders mini list ── */
    .dash-orders { display: flex; flex-direction: column; gap: 12px; }
    .dash-order-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 10px;
      padding: 14px 18px;
      background: #f8f9fc;
      border: 1px solid var(--border);
      border-radius: 10px;
    }
    .dash-order-left  { display: flex; flex-direction: column; gap: 3px; }
    .dash-order-num   { font-size: 14px; font-weight: 700; color: var(--navy); }
    .dash-order-date  { font-size: 12px; color: var(--gray); }
    .dash-order-right { display: flex; align-items: center; gap: 12px; }
    .dash-order-total { font-size: 14px; font-weight: 700; color: var(--navy); }

    .ord-badge {
      display: inline-block; padding: 3px 10px;
      border-radius: 20px; font-size: 11px; font-weight: 700;
    }
    .badge-pending    { background: #fef3c7; color: #92400e; }
    .badge-processing { background: #dbeafe; color: #1e40af; }
    .badge-shipped    { background: #ede9fe; color: #5b21b6; }
    .badge-delivered  { background: #dcfce7; color: #166534; }
    .badge-cancelled  { background: #fee2e2; color: #991b1b; }

    .btn-view-order {
      font-size: 12px; font-weight: 700;
      color: var(--navy); text-decoration: none;
      border: 1px solid var(--navy);
      padding: 5px 12px; border-radius: 6px;
      transition: background .15s, color .15s;
    }
    .btn-view-order:hover { background: var(--navy); color: #fff; }

    .dash-empty {
      text-align: center; padding: 28px 0;
      color: var(--gray); font-size: 14px;
    }
    .dash-empty [data-lucide] { width: 36px; height: 36px; opacity: .25; margin-bottom: 10px; }

    /* ── Logout btn ── */
    .btn-logout {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 10px 20px;
      background: #fff; color: #dc2626;
      border: 1.5px solid #fecaca; border-radius: 9px;
      font-size: 13px; font-weight: 700;
      cursor: pointer; font-family: inherit;
      text-decoration: none;
      transition: background .15s;
    }
    .btn-logout:hover { background: #fef2f2; }

    /* ── Change password ── */
    .change-pw-toggle {
      font-size: 13px; font-weight: 600; color: var(--navy);
      cursor: pointer; background: none; border: none;
      font-family: inherit; padding: 0; text-decoration: underline;
    }
    .change-pw-fields { display: none; margin-top: 16px; }
    .change-pw-fields.open { display: block; }
  </style>
</head>
<body>

<!-- ══ TOP BAR ══════════════════════════════════════════════════════════ -->
<div class="topbar">
  <div class="topbar-left">
    <span class="topbar-item"><i data-lucide="refresh-ccw" class="ti"></i><span>Easy Returns</span><small>30-day return policy</small></span>
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
      <a href="account.php" class="ibtn active"><i data-lucide="user"></i></a>
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
    <span>My Account</span>
  </div>
</div>

<!-- ══ CONTENT ══════════════════════════════════════════════════════════ -->
<div class="account-wrap">

<?php if ($isLoggedIn): ?>
  <!-- ══════════════ DASHBOARD ══════════════ -->

  <!-- Welcome header -->
  <div class="dash-header">
    <div class="dash-avatar"><?= e(mb_strtoupper(mb_substr($customer['name'], 0, 1))) ?></div>
    <div class="dash-welcome">
      <strong>Hi, <?= e(explode(' ', $customer['name'])[0]) ?>!</strong>
      <span>Member since <?= date('F Y', strtotime($customer['created_at'])) ?></span>
    </div>
  </div>

  <!-- ── My Orders ── -->
  <div class="dash-section">
    <div class="dash-section-head">
      <h3><i data-lucide="package"></i> My Orders</h3>
      <a href="orders.php">View all</a>
    </div>

    <?php if (empty($orders)): ?>
      <div class="dash-empty">
        <i data-lucide="package"></i>
        <p>You haven't placed any orders yet.</p>
        <a href="shop.php" style="color:var(--navy);font-weight:700;">Start Shopping →</a>
      </div>
    <?php else: ?>
      <div class="dash-orders">
        <?php foreach ($orders as $order): ?>
          <div class="dash-order-row">
            <div class="dash-order-left">
              <span class="dash-order-num"><?= e($order['order_number']) ?></span>
              <span class="dash-order-date"><?= date('d M Y', strtotime($order['created_at'])) ?></span>
            </div>
            <div class="dash-order-right">
              <?= statusBadge($order['status']) ?>
              <span class="dash-order-total"><?= formatPrice((float)$order['total']) ?></span>
              <a href="order-confirm.php?id=<?= (int)$order['id'] ?>" class="btn-view-order">View</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- ── Saved Address ── -->
  <div class="dash-section">
    <div class="dash-section-head">
      <h3><i data-lucide="map-pin"></i> Saved Address</h3>
    </div>

    <div id="addressAlert" class="auth-alert"></div>

    <form id="addressForm">
      <div class="address-fields">
        <div class="auth-field full">
          <label for="addrAddress">Street Address</label>
          <input type="text" id="addrAddress" name="address"
                 value="<?= e($customer['address'] ?? '') ?>"
                 placeholder="123 Kampala Road"/>
        </div>
        <div class="auth-field">
          <label for="addrCity">City</label>
          <input type="text" id="addrCity" name="city"
                 value="<?= e($customer['city'] ?? '') ?>"
                 placeholder="Kampala"/>
        </div>
        <div class="auth-field">
          <label for="addrDistrict">District</label>
          <input type="text" id="addrDistrict" name="district"
                 value="<?= e($customer['district'] ?? '') ?>"
                 placeholder="Central"/>
        </div>
      </div>
      <div class="address-save-row">
        <button type="submit" class="btn-save-address">
          <i data-lucide="save" style="width:14px;height:14px;"></i>
          Save Address
        </button>
      </div>
    </form>
  </div>

  <!-- ── Account Details ── -->
  <div class="dash-section">
    <div class="dash-section-head">
      <h3><i data-lucide="user"></i> Account Details</h3>
    </div>

    <div id="detailsAlert" class="auth-alert"></div>

    <form id="detailsForm">
      <div class="auth-field">
        <label for="detName">Full Name</label>
        <input type="text" id="detName" name="name"
               value="<?= e($customer['name']) ?>" required/>
      </div>
      <div class="auth-field">
        <label for="detPhone">Phone Number</label>
        <input type="tel" id="detPhone" name="phone"
               value="<?= e($customer['phone']) ?>" required/>
      </div>
      <div class="auth-field">
        <label for="detEmail">Email Address <span style="color:var(--gray);font-weight:400">(optional)</span></label>
        <input type="email" id="detEmail" name="email"
               value="<?= e($customer['email'] ?? '') ?>"
               placeholder="you@email.com"/>
      </div>

      <div style="margin-top:8px;">
        <button type="button" class="change-pw-toggle" id="pwToggle">Change password</button>
      </div>

      <div class="change-pw-fields" id="pwFields">
        <div class="auth-field" style="margin-top:14px;">
          <label for="pwCurrent">Current Password</label>
          <input type="password" id="pwCurrent" name="current_password" placeholder="Current password"/>
        </div>
        <div class="auth-field">
          <label for="pwNew">New Password</label>
          <input type="password" id="pwNew" name="new_password" placeholder="At least 6 characters"/>
        </div>
        <div class="auth-field">
          <label for="pwConfirm">Confirm New Password</label>
          <input type="password" id="pwConfirm" name="new_password_confirm" placeholder="Repeat new password"/>
        </div>
      </div>

      <div class="address-save-row" style="margin-top:16px;">
        <button type="submit" class="btn-save-address">Save Changes</button>
      </div>
    </form>
  </div>

  <!-- ── Logout ── -->
  <div style="text-align:right; margin-top:8px;">
    <a href="api/logout.php" class="btn-logout">
      <i data-lucide="log-out"></i> Sign Out
    </a>
  </div>

<?php else: ?>
  <!-- ══════════════ AUTH FORMS ══════════════ -->
  <h1 class="account-page-title">My Account</h1>

  <div class="account-tabs">
    <button class="account-tab <?= $defaultTab === 'login' ? 'active' : '' ?>" data-panel="login">Sign In</button>
    <button class="account-tab <?= $defaultTab === 'register' ? 'active' : '' ?>" data-panel="register">Create Account</button>
  </div>

  <!-- ── Login panel ── -->
  <div class="account-panel <?= $defaultTab === 'login' ? 'active' : '' ?>" id="panel-login">
    <div class="account-card">
      <h2>Welcome Back</h2>
      <p>Sign in to view your orders and checkout faster.</p>

      <div id="loginAlert" class="auth-alert"></div>

      <div class="auth-field">
        <label for="loginPhone">Phone Number</label>
        <input type="tel" id="loginPhone" placeholder="e.g. 0759 809061" autocomplete="tel"/>
      </div>
      <div class="auth-field">
        <label for="loginPassword">Password</label>
        <input type="password" id="loginPassword" placeholder="Your password" autocomplete="current-password"/>
      </div>

      <button class="btn-auth" id="loginBtn">
        <i data-lucide="log-in"></i> Sign In
      </button>
    </div>
  </div>

  <!-- ── Register panel ── -->
  <div class="account-panel <?= $defaultTab === 'register' ? 'active' : '' ?>" id="panel-register">
    <div class="account-card">
      <h2>Create an Account</h2>
      <p>Join Gadget Genesis for faster checkout and order tracking.</p>

      <div id="registerAlert" class="auth-alert"></div>

      <div class="auth-field">
        <label for="regName">Full Name <span style="color:#ef4444">*</span></label>
        <input type="text" id="regName" placeholder="John Smith" autocomplete="name"/>
      </div>
      <div class="auth-field">
        <label for="regPhone">Phone Number <span style="color:#ef4444">*</span></label>
        <input type="tel" id="regPhone" placeholder="e.g. 0759 809061" autocomplete="tel"/>
      </div>
      <div class="auth-field">
        <label for="regEmail">Email Address <span style="color:var(--gray);font-weight:400">(optional)</span></label>
        <input type="email" id="regEmail" placeholder="you@email.com" autocomplete="email"/>
      </div>
      <div class="auth-field">
        <label for="regPassword">Password <span style="color:#ef4444">*</span></label>
        <input type="password" id="regPassword" placeholder="At least 6 characters" autocomplete="new-password"/>
        <span class="field-hint">Minimum 6 characters</span>
      </div>
      <div class="auth-field">
        <label for="regConfirm">Confirm Password <span style="color:#ef4444">*</span></label>
        <input type="password" id="regConfirm" placeholder="Repeat your password" autocomplete="new-password"/>
      </div>

      <button class="btn-auth" id="registerBtn">
        <i data-lucide="user-plus"></i> Create Account
      </button>
    </div>
  </div>
<?php endif; ?>

</div><!-- /.account-wrap -->

<?php require_once __DIR__ . '/config/footer.php'; ?>

<div class="toast" id="toast"></div>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script src="cart.js"></script>
<script>
/* ── Helpers ── */
function showAlert(id, msg, type) {
  const el = document.getElementById(id);
  if (!el) return;
  el.textContent = msg;
  el.className   = 'auth-alert show ' + type;
}
function hideAlert(id) {
  const el = document.getElementById(id);
  if (el) el.className = 'auth-alert';
}
function setBusy(btn, busy) {
  btn.disabled = busy;
  if (busy) {
    btn.dataset.orig = btn.innerHTML;
    btn.innerHTML = '<i data-lucide="loader-circle" style="animation:spin 1s linear infinite"></i> Please wait…';
    if (window.lucide) lucide.createIcons();
  } else {
    btn.innerHTML = btn.dataset.orig || btn.innerHTML;
    if (window.lucide) lucide.createIcons();
  }
}

/* ── Tab switching ── */
document.querySelectorAll('.account-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.account-tab, .account-panel').forEach(el => el.classList.remove('active'));
    tab.classList.add('active');
    document.getElementById('panel-' + tab.dataset.panel)?.classList.add('active');
  });
});

/* ── Login ── */
document.getElementById('loginBtn')?.addEventListener('click', async () => {
  const btn  = document.getElementById('loginBtn');
  const phone = document.getElementById('loginPhone')?.value.trim();
  const pass  = document.getElementById('loginPassword')?.value;

  hideAlert('loginAlert');
  if (!phone || !pass) { showAlert('loginAlert', 'Please fill in all fields.', 'error'); return; }

  setBusy(btn, true);
  try {
    const res  = await fetch('api/login.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ phone, password: pass }),
    });
    const data = await res.json();
    if (data.success) {
      showAlert('loginAlert', `Welcome back, ${data.customer_name}! Redirecting…`, 'success');
      setTimeout(() => window.location.href = 'account.php', 900);
    } else {
      showAlert('loginAlert', data.error || 'Login failed.', 'error');
      setBusy(btn, false);
    }
  } catch {
    showAlert('loginAlert', 'Network error. Please try again.', 'error');
    setBusy(btn, false);
  }
});

/* ── Register ── */
document.getElementById('registerBtn')?.addEventListener('click', async () => {
  const btn  = document.getElementById('registerBtn');
  const name = document.getElementById('regName')?.value.trim();
  const phone = document.getElementById('regPhone')?.value.trim();
  const email = document.getElementById('regEmail')?.value.trim();
  const pass  = document.getElementById('regPassword')?.value;
  const conf  = document.getElementById('regConfirm')?.value;

  hideAlert('registerAlert');
  if (!name || !phone || !pass || !conf) {
    showAlert('registerAlert', 'Please fill in all required fields.', 'error'); return;
  }

  setBusy(btn, true);
  try {
    const res  = await fetch('api/register.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ name, phone, email, password: pass, password_confirm: conf }),
    });
    const data = await res.json();
    if (data.success) {
      showAlert('registerAlert', `Account created! Welcome, ${data.customer_name}. Redirecting…`, 'success');
      setTimeout(() => window.location.href = 'account.php', 900);
    } else {
      showAlert('registerAlert', data.error || 'Registration failed.', 'error');
      setBusy(btn, false);
    }
  } catch {
    showAlert('registerAlert', 'Network error. Please try again.', 'error');
    setBusy(btn, false);
  }
});

/* ── Allow Enter key on login/register fields ── */
['loginPhone','loginPassword'].forEach(id => {
  document.getElementById(id)?.addEventListener('keydown', e => {
    if (e.key === 'Enter') document.getElementById('loginBtn')?.click();
  });
});
['regName','regPhone','regEmail','regPassword','regConfirm'].forEach(id => {
  document.getElementById(id)?.addEventListener('keydown', e => {
    if (e.key === 'Enter') document.getElementById('registerBtn')?.click();
  });
});

/* ── Save address ── */
document.getElementById('addressForm')?.addEventListener('submit', async e => {
  e.preventDefault();
  const fd  = new FormData(e.target);
  hideAlert('addressAlert');
  try {
    const res  = await fetch('api/update-account.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ action: 'address', address: fd.get('address'), city: fd.get('city'), district: fd.get('district') }),
    });
    const data = await res.json();
    if (data.success) {
      showAlert('addressAlert', 'Address saved.', 'success');
    } else {
      showAlert('addressAlert', data.error || 'Save failed.', 'error');
    }
  } catch {
    showAlert('addressAlert', 'Network error.', 'error');
  }
});

/* ── Save account details ── */
document.getElementById('detailsForm')?.addEventListener('submit', async e => {
  e.preventDefault();
  const fd  = new FormData(e.target);
  hideAlert('detailsAlert');
  try {
    const res  = await fetch('api/update-account.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({
        action:           'details',
        name:             fd.get('name'),
        phone:            fd.get('phone'),
        email:            fd.get('email'),
        current_password: fd.get('current_password'),
        new_password:     fd.get('new_password'),
        new_password_confirm: fd.get('new_password_confirm'),
      }),
    });
    const data = await res.json();
    if (data.success) {
      showAlert('detailsAlert', 'Details updated.', 'success');
      if (data.reload) setTimeout(() => location.reload(), 800);
    } else {
      showAlert('detailsAlert', data.error || 'Update failed.', 'error');
    }
  } catch {
    showAlert('detailsAlert', 'Network error.', 'error');
  }
});

/* ── Password toggle ── */
document.getElementById('pwToggle')?.addEventListener('click', () => {
  const fields = document.getElementById('pwFields');
  fields?.classList.toggle('open');
  document.getElementById('pwToggle').textContent =
    fields?.classList.contains('open') ? 'Cancel password change' : 'Change password';
});

/* ── Spin keyframe for loader icon ── */
const style = document.createElement('style');
style.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
document.head.appendChild(style);

document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();
});
</script>
</body>
</html>
