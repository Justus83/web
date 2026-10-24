<?php
/* ═══════════════════════════════════════════════════════════════════════
   admin/discounts.php — Product-level discount management
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

    /* ── Create / update discount ── */
    if ($action === 'save') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $type      = $_POST['type'] ?? 'pct';
        $value     = (float) ($_POST['value'] ?? 0);
        $expires   = trim($_POST['expires_at'] ?? '') ?: null;
        $active    = isset($_POST['active']) ? 1 : 0;

        if (!$productId) {
            $error = 'Please select a product.';
        } elseif (!in_array($type, ['pct', 'fixed'])) {
            $error = 'Invalid discount type.';
        } elseif ($type === 'pct' && ($value <= 0 || $value > 100)) {
            $error = 'Percentage must be between 1 and 100.';
        } elseif ($type === 'fixed' && $value <= 0) {
            $error = 'Fixed price must be greater than 0.';
        } else {
            try {
                $pdo->prepare("
                    INSERT INTO product_discounts (product_id, type, value, expires_at, active)
                    VALUES (:pid, :type, :val, :exp, :active)
                    ON DUPLICATE KEY UPDATE
                        type       = VALUES(type),
                        value      = VALUES(value),
                        expires_at = VALUES(expires_at),
                        active     = VALUES(active)
                ")->execute([
                    ':pid'    => $productId,
                    ':type'   => $type,
                    ':val'    => $value,
                    ':exp'    => $expires,
                    ':active' => $active,
                ]);
                $success = 'Discount saved.';
            } catch (PDOException $e) {
                $error = 'Failed to save discount.';
            }
        }
    }

    /* ── Toggle active ── */
    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE product_discounts SET active = 1 - active WHERE id = ?")
            ->execute([$id]);
        $success = 'Discount updated.';
    }

    /* ── Delete ── */
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM product_discounts WHERE id = ?")
            ->execute([$id]);
        $success = 'Discount removed.';
    }
}

/* ── Load all products for the select dropdown ─────────────────────── */
$allProducts = $pdo->query("
    SELECT p.id, p.model,
           MIN(ps.price) AS base_price
    FROM products p
    LEFT JOIN product_storages ps ON ps.product_id = p.id
    GROUP BY p.id
    ORDER BY p.model ASC
")->fetchAll();

/* ── Load existing discounts with product info ─────────────────────── */
$discounts = $pdo->query("
    SELECT d.*, p.model,
           MIN(ps.price) AS base_price
    FROM product_discounts d
    JOIN products p ON p.id = d.product_id
    LEFT JOIN product_storages ps ON ps.product_id = p.id
    GROUP BY d.id
    ORDER BY d.created_at DESC
")->fetchAll();

/* ── Pre-fill edit form if ?edit= is set ───────────────────────────── */
$editing = null;
if (!empty($_GET['edit']) && is_numeric($_GET['edit'])) {
    foreach ($discounts as $d) {
        if ((int)$d['id'] === (int)$_GET['edit']) { $editing = $d; break; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Discounts — Admin — Gadget Genesis</title>
  <link rel="stylesheet" href="../style.css"/>
  <style>
    body { background: #f1f3f8; }
    .adm-wrap    { display: flex; min-height: 100vh; }
    .adm-sidebar { width:220px;flex-shrink:0;background:var(--navy);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:100; }
    .adm-logo    { padding:22px 20px 18px;border-bottom:1px solid rgba(255,255,255,.1); }
    .adm-logo img  { height:36px;width:auto; }
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

    .adm-main  { margin-left:220px;flex:1;padding:32px; }
    .adm-topbar { display:flex;align-items:center;justify-content:space-between;margin-bottom:24px; }
    .adm-topbar h1 { font-size:22px;font-weight:800;color:var(--navy); }

    /* ── Form card ── */
    .adm-card { background:#fff;border:1px solid var(--border);border-radius:14px;padding:26px 28px;margin-bottom:22px; }
    .adm-card h2 { font-size:16px;font-weight:800;color:var(--navy);margin-bottom:20px;display:flex;align-items:center;gap:8px; }
    .adm-card h2 [data-lucide] { width:16px;height:16px; }

    .form-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:18px; }
    .form-field { display:flex;flex-direction:column;gap:5px; }
    .form-field label { font-size:12px;font-weight:700;color:var(--navy);text-transform:uppercase;letter-spacing:.4px; }
    .form-field select,
    .form-field input[type=number],
    .form-field input[type=datetime-local] {
      padding:10px 12px;border:1.5px solid var(--border);border-radius:9px;
      font-size:14px;font-family:inherit;color:var(--navy);outline:none;
      transition:border-color .15s;background:#fff;
    }
    .form-field select:focus,
    .form-field input:focus { border-color:var(--navy); }
    .field-hint { font-size:11px;color:var(--gray);margin-top:2px; }

    /* ── Type toggle ── */
    .type-toggle { display:flex;gap:8px; }
    .type-btn {
      flex:1;padding:10px;border:1.5px solid var(--border);border-radius:9px;
      font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;
      background:#fff;color:var(--gray);transition:all .15s;text-align:center;
    }
    .type-btn.active { background:var(--navy);color:#fff;border-color:var(--navy); }

    /* ── Value preview ── */
    .discount-preview {
      background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;
      padding:14px 18px;font-size:13px;color:#166534;
      display:none;align-items:center;gap:10px;margin-bottom:18px;
    }
    .discount-preview.show { display:flex; }
    .discount-preview [data-lucide] { width:16px;height:16px;flex-shrink:0; }
    .dp-prices { display:flex;align-items:center;gap:10px; }
    .dp-original { text-decoration:line-through;color:var(--gray);font-size:13px; }
    .dp-sale     { font-size:16px;font-weight:800;color:#16a34a; }
    .dp-badge    { background:#16a34a;color:#fff;border-radius:20px;padding:2px 10px;font-size:11px;font-weight:700; }

    .form-row-end { display:flex;align-items:center;gap:12px;flex-wrap:wrap; }
    .checkbox-label { display:flex;align-items:center;gap:7px;font-size:13px;font-weight:600;color:var(--navy);cursor:pointer; }
    .checkbox-label input { width:16px;height:16px;cursor:pointer; }

    .btn-save { padding:11px 26px;background:var(--navy);color:#fff;border:none;border-radius:9px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:8px; }
    .btn-save:hover { opacity:.88; }
    .btn-save [data-lucide] { width:15px;height:15px; }

    /* ── Alerts ── */
    .alert { padding:12px 16px;border-radius:9px;font-size:13px;margin-bottom:18px;display:flex;align-items:center;gap:8px; }
    .alert [data-lucide] { width:15px;height:15px;flex-shrink:0; }
    .alert-success { background:#f0fdf4;border:1px solid #bbf7d0;color:#166534; }
    .alert-error   { background:#fef2f2;border:1px solid #fecaca;color:#b91c1c; }

    /* ── Discounts table ── */
    .adm-table { width:100%;border-collapse:collapse; }
    .adm-table th { text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--gray);padding:11px 14px;border-bottom:1px solid var(--border);background:#f8f9fc; }
    .adm-table td { padding:13px 14px;font-size:13px;color:var(--navy);border-bottom:1px solid #f1f3f8;vertical-align:middle; }
    .adm-table tr:last-child td { border-bottom:none; }
    .adm-table tbody tr:hover td { background:#f8f9fc; }

    .disc-type-pct   { background:#dbeafe;color:#1d4ed8;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700; }
    .disc-type-fixed { background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700; }
    .pill-active     { background:#dcfce7;color:#166534;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700; }
    .pill-inactive   { background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700; }

    .btn-sm { padding:5px 12px;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;border:none; }
    .btn-edit   { background:#e0e7ff;color:#3730a3; }
    .btn-toggle { background:#fef3c7;color:#92400e; }
    .btn-delete { background:#fee2e2;color:#dc2626; }
    .btn-sm:hover { opacity:.8; }
    .td-actions { display:flex;gap:6px; }

    .adm-table-empty { text-align:center;padding:40px;color:var(--gray);font-size:14px; }
    .result-count { font-size:13px;color:var(--gray);margin-bottom:10px; }
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
      <a href="discounts.php" class="active"><i data-lucide="percent"></i> Discounts</a>
      <a href="coupons.php"><i data-lucide="tag"></i> Coupons</a>
      <a href="customers.php"><i data-lucide="users"></i> Customers</a>
      <a href="reviews.php"><i data-lucide="star"></i> Reviews</a>
      <div class="nav-section">Account</div>
      <a href="change-password.php"><i data-lucide="key-round"></i> Change Password</a>
    </nav>
    <div class="adm-footer">
      <span style="font-size:12px;color:rgba(255,255,255,.4);display:flex;align-items:center;gap:6px;">
        <i data-lucide="user" style="width:12px;height:12px;"></i>
        <?= htmlspecialchars(adminUsername(), ENT_QUOTES, 'UTF-8') ?>
      </span>
      <a href="logout.php"><i data-lucide="log-out"></i> Sign Out</a>
    </div>
  </aside>

  <!-- Main -->
  <main class="adm-main">
    <div class="adm-topbar">
      <h1>Product Discounts</h1>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success"><i data-lucide="circle-check"></i> <?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error"><i data-lucide="circle-x"></i> <?= e($error) ?></div>
    <?php endif; ?>

    <!-- ── Add / Edit form ── -->
    <div class="adm-card">
      <h2>
        <i data-lucide="<?= $editing ? 'pencil' : 'plus-circle' ?>"></i>
        <?= $editing ? 'Edit Discount — ' . e($editing['model']) : 'Add Discount' ?>
      </h2>

      <form method="POST" action="discounts.php" id="discountForm">
        <input type="hidden" name="action" value="save"/>

        <!-- Product select -->
        <div class="form-field" style="margin-bottom:18px;">
          <label for="product_id">Product</label>
          <select name="product_id" id="product_id" required>
            <option value="">— Select a product —</option>
            <?php foreach ($allProducts as $p): ?>
              <option value="<?= $p['id'] ?>"
                      data-price="<?= (float)($p['base_price'] ?? 0) ?>"
                      <?= ($editing && $editing['product_id'] == $p['id']) ? 'selected' : '' ?>>
                <?= e($p['model']) ?>
                <?php if ($p['base_price']): ?>
                  — <?= formatPrice((float)$p['base_price']) ?>
                <?php endif; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Type toggle -->
        <div class="form-field" style="margin-bottom:18px;">
          <label>Discount Type</label>
          <div class="type-toggle">
            <button type="button" class="type-btn <?= !$editing || $editing['type']==='pct' ? 'active' : '' ?>"
                    data-type="pct" id="btnPct">
              % Percentage Off
            </button>
            <button type="button" class="type-btn <?= $editing && $editing['type']==='fixed' ? 'active' : '' ?>"
                    data-type="fixed" id="btnFixed">
              Fixed Sale Price
            </button>
          </div>
          <input type="hidden" name="type" id="typeInput"
                 value="<?= $editing ? $editing['type'] : 'pct' ?>"/>
        </div>

        <div class="form-grid">
          <!-- Value -->
          <div class="form-field">
            <label id="valueLabel">Discount %</label>
            <input type="number" name="value" id="valueInput"
                   min="1" step="0.01"
                   placeholder="e.g. 15"
                   value="<?= $editing ? (float)$editing['value'] : '' ?>"
                   required/>
            <span class="field-hint" id="valueHint">Enter a value between 1 and 100</span>
          </div>

          <!-- Expiry -->
          <div class="form-field">
            <label>Expires At <span style="font-weight:400;color:var(--gray)">(optional)</span></label>
            <input type="datetime-local" name="expires_at" id="expiresInput"
                   value="<?= $editing && $editing['expires_at'] ? date('Y-m-d\TH:i', strtotime($editing['expires_at'])) : '' ?>"/>
            <span class="field-hint">Leave empty = never expires</span>
          </div>
        </div>

        <!-- Live preview -->
        <div class="discount-preview" id="discountPreview">
          <i data-lucide="tag"></i>
          <div>
            <div style="font-size:12px;font-weight:600;margin-bottom:4px;" id="previewProduct">—</div>
            <div class="dp-prices">
              <span class="dp-original" id="previewOriginal">—</span>
              <span>→</span>
              <span class="dp-sale" id="previewSale">—</span>
              <span class="dp-badge" id="previewBadge">—</span>
            </div>
          </div>
        </div>

        <div class="form-row-end">
          <label class="checkbox-label">
            <input type="checkbox" name="active" value="1"
                   <?= !$editing || $editing['active'] ? 'checked' : '' ?>/>
            Active
          </label>
          <button type="submit" class="btn-save">
            <i data-lucide="save"></i> <?= $editing ? 'Update Discount' : 'Save Discount' ?>
          </button>
          <?php if ($editing): ?>
            <a href="discounts.php" style="font-size:13px;color:var(--gray);text-decoration:none;">Cancel edit</a>
          <?php endif; ?>
        </div>

      </form>
    </div>

    <!-- ── Existing discounts ── -->
    <div class="result-count"><?= count($discounts) ?> discount<?= count($discounts) !== 1 ? 's' : '' ?> configured</div>

    <div class="adm-card" style="padding:0;overflow:hidden;">
      <?php if (empty($discounts)): ?>
        <div class="adm-table-empty">No discounts yet. Add one above.</div>
      <?php else: ?>
        <table class="adm-table">
          <thead>
            <tr>
              <th>Product</th>
              <th>Type</th>
              <th>Value</th>
              <th>Original Price</th>
              <th>Sale Price</th>
              <th>Saving</th>
              <th>Expires</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($discounts as $d):
              $base     = (float)($d['base_price'] ?? 0);
              $salePrice = $d['type'] === 'pct'
                ? round($base * (1 - (float)$d['value'] / 100))
                : max(0, (float)$d['value']);
              $saving   = $base - $salePrice;
              $pct      = $base > 0 ? round(($saving / $base) * 100) : 0;
              $expired  = $d['expires_at'] && strtotime($d['expires_at']) < time();
            ?>
              <tr>
                <td><strong><?= e($d['model']) ?></strong></td>
                <td>
                  <?php if ($d['type'] === 'pct'): ?>
                    <span class="disc-type-pct">% Off</span>
                  <?php else: ?>
                    <span class="disc-type-fixed">Fixed</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?= $d['type'] === 'pct'
                    ? (float)$d['value'] . '%'
                    : formatPrice((float)$d['value']) ?>
                </td>
                <td><?= $base ? formatPrice($base) : '—' ?></td>
                <td><strong style="color:#16a34a;"><?= $base ? formatPrice($salePrice) : '—' ?></strong></td>
                <td><?= $base && $saving > 0 ? '<span style="color:#16a34a;font-weight:700;">−' . $pct . '%</span>' : '—' ?></td>
                <td style="font-size:12px;color:<?= $expired ? '#dc2626' : 'var(--gray)' ?>;">
                  <?= $d['expires_at'] ? date('d M Y, g:ia', strtotime($d['expires_at'])) : 'Never' ?>
                  <?= $expired ? ' <strong>(Expired)</strong>' : '' ?>
                </td>
                <td>
                  <span class="<?= $d['active'] && !$expired ? 'pill-active' : 'pill-inactive' ?>">
                    <?= $d['active'] && !$expired ? 'Active' : 'Inactive' ?>
                  </span>
                </td>
                <td>
                  <div class="td-actions">
                    <a href="discounts.php?edit=<?= $d['id'] ?>" class="btn-sm btn-edit">Edit</a>
                    <form method="POST" style="display:inline">
                      <input type="hidden" name="action" value="toggle"/>
                      <input type="hidden" name="id" value="<?= $d['id'] ?>"/>
                      <button type="submit" class="btn-sm btn-toggle">
                        <?= $d['active'] ? 'Disable' : 'Enable' ?>
                      </button>
                    </form>
                    <form method="POST" style="display:inline"
                          onsubmit="return confirm('Remove discount from <?= e(addslashes($d['model'])) ?>?')">
                      <input type="hidden" name="action" value="delete"/>
                      <input type="hidden" name="id" value="<?= $d['id'] ?>"/>
                      <button type="submit" class="btn-sm btn-delete">Delete</button>
                    </form>
                  </div>
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
<script>
/* ── Type toggle ── */
document.querySelectorAll('.type-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('typeInput').value = btn.dataset.type;
    updateLabels(btn.dataset.type);
    updatePreview();
  });
});

function updateLabels(type) {
  const lbl  = document.getElementById('valueLabel');
  const hint = document.getElementById('valueHint');
  const inp  = document.getElementById('valueInput');
  if (type === 'pct') {
    lbl.textContent  = 'Discount %';
    hint.textContent = 'Enter a value between 1 and 100';
    inp.min          = '1';
    inp.max          = '100';
    inp.placeholder  = 'e.g. 20';
  } else {
    lbl.textContent  = 'Sale Price (UGX)';
    hint.textContent = 'Enter the new flat sale price';
    inp.min          = '1';
    inp.removeAttribute('max');
    inp.placeholder  = 'e.g. 850000';
  }
}

/* ── Live preview ── */
function fmtUGX(n) {
  return 'UGX ' + Math.round(n).toLocaleString();
}

function updatePreview() {
  const sel      = document.getElementById('product_id');
  const opt      = sel.options[sel.selectedIndex];
  const base     = parseFloat(opt?.dataset?.price || 0);
  const type     = document.getElementById('typeInput').value;
  const val      = parseFloat(document.getElementById('valueInput').value || 0);
  const preview  = document.getElementById('discountPreview');

  if (!base || !val || val <= 0) { preview.classList.remove('show'); return; }

  let sale, pct;
  if (type === 'pct') {
    if (val > 100) { preview.classList.remove('show'); return; }
    sale = Math.round(base * (1 - val / 100));
    pct  = Math.round(val) + '% OFF';
  } else {
    sale = val;
    pct  = base > sale ? Math.round((base - sale) / base * 100) + '% OFF' : '';
  }

  document.getElementById('previewProduct').textContent  = opt.text.split(' —')[0];
  document.getElementById('previewOriginal').textContent = fmtUGX(base);
  document.getElementById('previewSale').textContent     = fmtUGX(sale);
  document.getElementById('previewBadge').textContent    = pct;
  preview.classList.add('show');
}

document.getElementById('product_id').addEventListener('change', updatePreview);
document.getElementById('valueInput').addEventListener('input',  updatePreview);

/* ── Init ── */
document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();
  const currentType = document.getElementById('typeInput').value;
  updateLabels(currentType);
  updatePreview();
});
</script>
</body>
</html>
