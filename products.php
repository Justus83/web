<?php
require_once __DIR__ . '/config/helpers.php';

// ── Handle delete ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int) $_POST['delete_id'];
    $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
    header('Location: products.php?deleted=1');
    exit;
}

// ── Fetch all products ────────────────────────────────────────────────
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

// Attach relations
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
  <title>Products — Gadget Genesis</title>
  <link rel="stylesheet" href="style.css"/>
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>
<body>

<!-- ══ HEADER ════════════════════════════════════════════════════════════ -->
<header class="header">
  <div class="hdr-inner">
    <a href="index.php" class="logo"><img src="logo.jpg" alt="Gadget Genesis" style="height:44px;width:auto;"/></a>
    <?php require_once __DIR__ . '/config/nav.php'; ?>
    <div class="hdr-actions">
      <a href="cart.php" class="ibtn cart">
        <i data-lucide="shopping-cart"></i><span class="cbadge">0</span>
      </a>
    </div>
  </div>
</header>

<!-- ══ PAGE HEADER ═══════════════════════════════════════════════════════ -->
<div class="page-header">
  <div>
    <h1>Products <span class="count-badge">(<?= $total ?>)</span></h1>
  </div>
  <a href="add-product.php" class="btn-primary">+ Add Product</a>
</div>

<!-- ══ TOOLBAR ═══════════════════════════════════════════════════════════ -->
<div class="prod-toolbar">
  <form method="GET" class="prod-search-form">
    <div class="prod-search-wrap">
      <i data-lucide="search" class="prod-search-ico"></i>
      <input type="text" name="search" placeholder="Search products…"
             value="<?= e($search) ?>" class="prod-search-input"/>
    </div>
    <?php if ($search): ?>
      <a href="products.php" class="prod-clear-search">✕ Clear</a>
    <?php endif; ?>
  </form>

  <div class="prod-filter-tabs">
    <?php
    $filterTabs = array_merge(['All'], COLLECTIONS);
    foreach ($filterTabs as $tab): ?>
      <a href="products.php?collection=<?= urlencode($tab) ?>"
         class="filter-tab <?= $filter === $tab ? 'active' : '' ?>">
        <?= e($tab) ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- ══ NOTIFICATIONS ═════════════════════════════════════════════════════ -->
<?php if (isset($_GET['deleted'])): ?>
  <div class="alert alert-success" style="max-width:1200px;margin:0 auto 16px;padding:0 24px">
    <i data-lucide="circle-check"></i> Product deleted successfully.
  </div>
<?php endif; ?>

<!-- ══ PRODUCTS TABLE ════════════════════════════════════════════════════ -->
<div class="prod-list-wrap">

  <?php if (empty($products)): ?>
    <div class="prod-empty">
      <i data-lucide="package-open" class="prod-empty-ico"></i>
      <h3>No products found</h3>
      <p><?= $search ? 'No results for "' . e($search) . '"' : 'No products in this collection yet.' ?></p>
      <a href="add-product.php" class="btn-primary">Add First Product</a>
    </div>
  <?php else: ?>

    <div class="prod-table-wrap">
      <table class="prod-table">
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
          <?php foreach ($products as $p):
            $lowestP = lowestPrice($p['storages']);
          ?>
            <tr>
              <!-- Product -->
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
                      <small><?= e(mb_substr($p['description'], 0, 60)) ?>…</small>
                    <?php endif; ?>
                  </div>
                </div>
              </td>

              <!-- Collections -->
              <td>
                <div class="prod-row-colls">
                  <?php foreach ($p['collections'] as $col): ?>
                    <span class="prod-coll-chip"><?= e($col) ?></span>
                  <?php endforeach; ?>
                </div>
              </td>

              <!-- Storage / Price -->
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
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>

              <!-- Colors -->
              <td>
                <div class="prod-row-colors">
                  <?php foreach ($p['colors'] as $c): ?>
                    <div class="prod-color-dot" title="<?= e($c['name']) ?>">
                      <?php if ($c['image']): ?>
                        <img src="<?= $c['image'] ?>" alt="<?= e($c['name']) ?>"/>
                      <?php else: ?>
                        <span><?= e(substr($c['name'], 0, 1)) ?></span>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              </td>

              <!-- Date -->
              <td class="text-muted">
                <?= date('M j, Y', strtotime($p['created_at'])) ?>
              </td>

              <!-- Actions -->
              <td>
                <div class="prod-row-actions">
                  <a href="product.php?id=<?= $p['id'] ?>" class="row-btn view-btn" title="View">
                    <i data-lucide="eye"></i>
                  </a>
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
    </div>

  <?php endif; ?>
</div>

<!-- ══ EDIT PRICE MODAL ══════════════════════════════════════════════════ -->
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
      <button class="btn-submit" onclick="savePrice()">Save Price</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script src="cart.js"></script>
<script>
/* ── Edit price modal ── */
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
    const res  = await fetch('api/update-price.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ storage_id: editingStorageId, price })
    });
    const data = await res.json();
    if (data.success) {
      showToast('Price updated!');
      closePriceModal();
      setTimeout(() => location.reload(), 800);
    } else {
      showToast(data.error || 'Failed to update price.');
    }
  } catch {
    showToast('Network error.');
  }
}

// Close modal on overlay click
document.getElementById('priceModal').addEventListener('click', function (e) {
  if (e.target === this) closePriceModal();
});

// Enter key on price input
document.getElementById('modalPriceInput').addEventListener('keydown', e => {
  if (e.key === 'Enter') savePrice();
});

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3000);
}

document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();
});
</script>
<?php require_once __DIR__ . '/config/footer.php'; ?>
</body>
</html>






