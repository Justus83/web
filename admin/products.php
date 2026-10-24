<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/auth.php';
adminGuard();

$activePage = 'products';

// ── Handle delete ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int) $_POST['delete_id'];
    $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
    header('Location: products.php?deleted=1');
    exit;
}

// ── Handle featured toggle ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_featured_id'])) {
    $id = (int) $_POST['toggle_featured_id'];
    $pdo->prepare('UPDATE products SET is_featured = 1 - is_featured WHERE id = ?')->execute([$id]);
    header('Location: products.php?featured=1');
    exit;
}

// ── Fetch products ────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$filter = trim($_GET['collection'] ?? 'All');

if ($search !== '') {
    $stmt = $pdo->prepare(
        'SELECT id, model, image, description, created_at FROM products
         WHERE model LIKE :q ORDER BY created_at DESC'
    );
    $stmt->execute([':q' => '%' . $search . '%']);
    $products = $stmt->fetchAll();
} else {
    $products = getProductsByCollection($pdo, $filter, 'newest', 200);
}

foreach ($products as &$p) {
    if (!isset($p['collections'])) $p['collections'] = getCollections($pdo, $p['id']);
    if (!isset($p['storages']))    $p['storages']    = getStorages($pdo, $p['id']);
    if (!isset($p['colors']))      $p['colors']      = getColors($pdo, $p['id']);
}
unset($p);

$total = count($products);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Products — Admin — Gadget Genesis</title>
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
    .adm-topbar { display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px; }
    .adm-topbar h1 { font-size:22px;font-weight:800;color:var(--navy); }

    .btn-add-product {
      display:inline-flex;align-items:center;gap:8px;
      padding:10px 20px;background:var(--navy);color:#fff;
      border-radius:9px;font-size:13px;font-weight:700;
      text-decoration:none;transition:opacity .15s;
    }
    .btn-add-product:hover { opacity:.88; }
    .btn-add-product [data-lucide] { width:15px;height:15px; }

    /* ── Filters ── */
    .prod-toolbar {
      display:flex;gap:12px;flex-wrap:wrap;align-items:center;
      margin-bottom:20px;
    }
    .prod-search-wrap {
      display:flex;align-items:center;gap:8px;
      background:#fff;border:1.5px solid var(--border);border-radius:9px;
      padding:0 12px;
    }
    .prod-search-wrap [data-lucide] { width:15px;height:15px;color:var(--gray); }
    .prod-search-wrap input {
      border:none;outline:none;padding:9px 0;font-size:13px;
      font-family:inherit;color:var(--navy);min-width:200px;background:transparent;
    }
    .prod-clear { font-size:12px;color:var(--gray);text-decoration:none;white-space:nowrap; }
    .prod-clear:hover { color:var(--navy); }

    .filter-tabs { display:flex;gap:6px;flex-wrap:wrap; }
    .filter-tab {
      padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;
      color:var(--gray);text-decoration:none;background:#fff;
      border:1.5px solid var(--border);transition:all .15s;white-space:nowrap;
    }
    .filter-tab:hover,.filter-tab.active { background:var(--navy);color:#fff;border-color:var(--navy); }

    /* ── Table ── */
    .adm-card { background:#fff;border:1px solid var(--border);border-radius:14px;overflow:hidden; }
    .adm-table { width:100%;border-collapse:collapse; }
    .adm-table th { text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--gray);padding:12px 16px;border-bottom:1px solid var(--border);background:#f8f9fc; }
    .adm-table td { padding:13px 16px;font-size:13px;color:var(--navy);border-bottom:1px solid #f1f3f8;vertical-align:middle; }
    .adm-table tr:last-child td { border-bottom:none; }
    .adm-table tbody tr:hover td { background:#f8f9fc; }

    .prod-row-model { display:flex;align-items:center;gap:12px; }
    .prod-row-img   { width:48px;height:48px;object-fit:cover;border-radius:8px;border:1px solid var(--border);flex-shrink:0; }
    .prod-row-img-placeholder { width:48px;height:48px;background:#f3f4f6;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
    .prod-row-img-placeholder [data-lucide] { width:20px;height:20px;color:#9ca3af; }
    .prod-row-model strong { display:block;font-size:13px;font-weight:700; }
    .prod-row-model small  { display:block;font-size:11px;color:var(--gray);margin-top:2px; }

    .prod-coll-chip { display:inline-block;padding:2px 8px;background:#e0e7ff;color:#3730a3;border-radius:20px;font-size:11px;font-weight:600;margin:2px 2px 0 0; }

    .prod-row-storages { display:flex;flex-direction:column;gap:4px; }
    .prod-row-storage  { display:flex;align-items:center;gap:6px;font-size:12px; }
    .prod-row-storage span   { color:var(--gray); }
    .prod-row-storage strong { color:var(--navy); }
    .edit-price-btn { background:none;border:none;cursor:pointer;padding:2px;color:var(--gray);display:inline-flex; }
    .edit-price-btn:hover { color:var(--navy); }
    .edit-price-btn [data-lucide] { width:12px;height:12px; }

    .prod-row-colors { display:flex;gap:4px;flex-wrap:wrap; }
    .prod-color-dot  { width:28px;height:28px;border-radius:50%;overflow:hidden;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;background:#f3f4f6; }
    .prod-color-dot img { width:100%;height:100%;object-fit:cover; }

    .row-actions { display:flex;gap:6px;align-items:center; }
    .row-btn { display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:7px;border:none;cursor:pointer;transition:background .15s; }
    .row-btn [data-lucide] { width:14px;height:14px; }
    .edit-btn   { background:#e0e7ff;color:#3730a3; }
    .edit-btn:hover { background:#c7d2fe; }
    .delete-btn { background:#fee2e2;color:#dc2626; }
    .delete-btn:hover { background:#fecaca; }

    .prod-empty { text-align:center;padding:56px 20px;color:var(--gray); }
    .prod-empty [data-lucide] { width:48px;height:48px;opacity:.2;margin-bottom:12px; }
    .prod-empty h3 { font-size:16px;font-weight:700;color:var(--navy);margin-bottom:6px; }

    .alert-success { background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px; }

    /* ── Price modal ── */
    .modal-overlay { position:fixed;inset:0;background:rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;z-index:9999; }
    .modal-box { background:#fff;border-radius:14px;padding:28px;width:320px;box-shadow:0 20px 60px rgba(0,0,0,.2); }
    .modal-head { display:flex;justify-content:space-between;align-items:center;margin-bottom:18px; }
    .modal-head h3 { font-size:16px;font-weight:800;color:var(--navy); }
    .modal-close { background:none;border:none;font-size:18px;cursor:pointer;color:var(--gray);line-height:1; }
    .modal-body label { font-size:13px;font-weight:600;color:var(--navy);display:block;margin-bottom:6px; }
    .modal-body input { width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;font-family:inherit;outline:none;box-sizing:border-box; }
    .modal-body input:focus { border-color:var(--navy); }
    .modal-foot { display:flex;justify-content:flex-end;gap:8px;margin-top:18px; }
    .modal-foot .btn-cancel { padding:9px 18px;background:#fff;color:var(--navy);border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit; }
    .modal-foot .btn-submit { padding:9px 18px;background:var(--navy);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit; }

    .result-count { font-size:13px;color:var(--gray);margin-bottom:12px; }
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
      <a href="products.php" class="active"><i data-lucide="box"></i> Products</a>
      <a href="add-product.php"><i data-lucide="plus-circle"></i> Add Product</a>
      <a href="discounts.php"><i data-lucide="percent"></i> Discounts</a>
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
      <h1>Products <span style="font-size:16px;font-weight:600;color:var(--gray);">(<?= $total ?>)</span></h1>
      <a href="add-product.php" class="btn-add-product">
        <i data-lucide="plus"></i> Add Product
      </a>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
      <div class="alert-success">Product deleted successfully.</div>
    <?php endif; ?>
    <?php if (isset($_GET['saved'])): ?>
      <div class="alert-success">Product saved successfully.</div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="prod-toolbar">
      <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <div class="prod-search-wrap">
          <i data-lucide="search"></i>
          <input type="text" name="search" placeholder="Search products…" value="<?= e($search) ?>"/>
        </div>
        <?php if ($search): ?>
          <a href="products.php" class="prod-clear">✕ Clear</a>
        <?php endif; ?>
      </form>
      <div class="filter-tabs">
        <?php foreach (array_merge(['All'], COLLECTIONS) as $tab): ?>
          <a href="products.php?collection=<?= urlencode($tab) ?>"
             class="filter-tab <?= $filter === $tab ? 'active' : '' ?>">
            <?= e($tab) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="result-count"><?= $total ?> product<?= $total !== 1 ? 's' : '' ?></div>

    <div class="adm-card">
      <?php if (empty($products)): ?>
        <div class="prod-empty">
          <i data-lucide="package-open"></i>
          <h3>No products found</h3>
          <p><?= $search ? 'No results for "' . e($search) . '"' : 'No products yet.' ?></p>
        </div>
      <?php else: ?>
        <table class="adm-table">
          <thead>
            <tr>
              <th>Product</th>
              <th>Collections</th>
              <th>Storage / Price</th>
              <th>Colors</th>
              <th>Added</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($products as $p): ?>
              <tr>
                <td>
                  <div class="prod-row-model">
                    <?php if ($p['image']): ?>
                      <img src="<?= $p['image'] ?>" alt="<?= e($p['model']) ?>" class="prod-row-img"/>
                    <?php else: ?>
                      <div class="prod-row-img-placeholder"><i data-lucide="image"></i></div>
                    <?php endif; ?>
                    <div>
                      <strong><?= e($p['model']) ?></strong>
                      <?php if ($p['description']): ?>
                        <small><?= e(mb_substr($p['description'], 0, 55)) ?>…</small>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>
                <td>
                  <?php foreach ($p['collections'] as $col): ?>
                    <span class="prod-coll-chip"><?= e($col) ?></span>
                  <?php endforeach; ?>
                </td>
                <td>
                  <?php if (!empty($p['storages'])): ?>
                    <div class="prod-row-storages">
                      <?php foreach ($p['storages'] as $s): ?>
                        <div class="prod-row-storage">
                          <span><?= e($s['label']) ?></span>
                          <strong><?= formatPrice($s['price']) ?></strong>
                          <button class="edit-price-btn"
                                  onclick="editPrice(<?= $s['id'] ?>, <?= $s['price'] ?>, '<?= e($s['label']) ?>')"
                                  title="Edit price">
                            <i data-lucide="pencil"></i>
                          </button>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <span style="color:var(--gray)">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="prod-row-colors">
                    <?php foreach ($p['colors'] as $c): ?>
                      <div class="prod-color-dot" title="<?= e($c['name']) ?>">
                        <?php if ($c['image']): ?>
                          <img src="<?= $c['image'] ?>" alt="<?= e($c['name']) ?>"/>
                        <?php else: ?>
                          <?= e(mb_substr($c['name'], 0, 1)) ?>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </td>
                <td style="color:var(--gray);font-size:12px;">
                  <?= date('d M Y', strtotime($p['created_at'])) ?>
                </td>
                <td>
                  <div class="row-actions">
                    <form method="POST" style="display:inline" title="<?= ($p['is_featured'] ?? 0) ? 'Unfeature' : 'Feature on homepage' ?>">
                      <input type="hidden" name="toggle_featured_id" value="<?= $p['id'] ?>"/>
                      <button type="submit" class="row-btn" style="background:<?= ($p['is_featured'] ?? 0) ? '#fef3c7' : '#f3f4f6' ?>;color:<?= ($p['is_featured'] ?? 0) ? '#f59e0b' : '#9ca3af' ?>;">
                        <i data-lucide="star"></i>
                      </button>
                    </form>
                    <a href="edit-product.php?id=<?= $p['id'] ?>" class="row-btn edit-btn" title="Edit">
                      <i data-lucide="pencil"></i>
                    </a>
                    <form method="POST" onsubmit="return confirm('Delete <?= e(addslashes($p['model'])) ?>?')">
                      <input type="hidden" name="delete_id" value="<?= $p['id'] ?>"/>
                      <button type="submit" class="row-btn delete-btn" title="Delete">
                        <i data-lucide="trash-2"></i>
                      </button>
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

<!-- Price edit modal -->
<div class="modal-overlay" id="priceModal" style="display:none">
  <div class="modal-box">
    <div class="modal-head">
      <h3>Edit Price — <span id="modalStorageLabel"></span></h3>
      <button class="modal-close" onclick="closePriceModal()">✕</button>
    </div>
    <div class="modal-body">
      <label>New Price (UGX)</label>
      <input type="number" id="modalPriceInput" min="0" step="1" placeholder="0"/>
    </div>
    <div class="modal-foot">
      <button class="btn-cancel" onclick="closePriceModal()">Cancel</button>
      <button class="btn-submit" onclick="savePrice()">Save</button>
    </div>
  </div>
</div>

<div class="toast" id="toast" style="position:fixed;bottom:24px;right:24px;background:var(--navy);color:#fff;padding:12px 20px;border-radius:10px;font-size:13px;font-weight:600;display:none;z-index:9999;"></div>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>
let editingStorageId = null;

function editPrice(id, currentPrice, label) {
  editingStorageId = id;
  document.getElementById('modalStorageLabel').textContent = label;
  document.getElementById('modalPriceInput').value = currentPrice;
  document.getElementById('priceModal').style.display = 'flex';
  document.getElementById('modalPriceInput').focus();
}
function closePriceModal() {
  document.getElementById('priceModal').style.display = 'none';
  editingStorageId = null;
}
async function savePrice() {
  const price = parseFloat(document.getElementById('modalPriceInput').value);
  if (isNaN(price) || price < 0) { showToast('Enter a valid price.'); return; }
  try {
    const res  = await fetch('../api/update-price.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ storage_id: editingStorageId, price })
    });
    const data = await res.json();
    if (data.success) { showToast('Price updated!'); closePriceModal(); setTimeout(() => location.reload(), 700); }
    else showToast(data.error || 'Failed.');
  } catch { showToast('Network error.'); }
}
document.getElementById('priceModal').addEventListener('click', function(e) { if (e.target === this) closePriceModal(); });
document.getElementById('modalPriceInput').addEventListener('keydown', e => { if (e.key === 'Enter') savePrice(); });

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg; t.style.display = 'block';
  setTimeout(() => t.style.display = 'none', 3000);
}
document.addEventListener('DOMContentLoaded', () => { if (window.lucide) lucide.createIcons(); });
</script>
</body>
</html>
