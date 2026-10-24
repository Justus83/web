<?php
/* ═══════════════════════════════════════════════════════════════════════
   compare.php — Side-by-side product comparison
   Accepts ?ids=1,2,3 (up to 3 product IDs)
   ═══════════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/config/helpers.php';

$rawIds   = trim($_GET['ids'] ?? '');
$ids      = array_filter(array_unique(array_map('intval', explode(',', $rawIds))));
$ids      = array_values(array_slice($ids, 0, 3));

$products = [];
foreach ($ids as $pid) {
    $p = getProduct($pdo, $pid);
    if ($p) {
        $discount         = $p['discount'];
        $p['lowest_price'] = lowestPrice($p['storages']);
        $p['lowest_sale']  = applyDiscount($p['lowest_price'], $discount);
        $p['has_discount'] = $discount && $p['lowest_sale'] < $p['lowest_price'];
        $stats             = reviewStats($p['reviews']);
        $p['avg_rating']   = $stats['avg'];
        $p['review_count'] = $stats['count'];
        $products[] = $p;
    }
}

/* Collect all spec keys across all products for unified table rows */
$allSpecKeys = [];
foreach ($products as $p) {
    foreach ($p['specs'] as $spec) {
        if (!in_array($spec['spec_key'], $allSpecKeys)) {
            $allSpecKeys[] = $spec['spec_key'];
        }
    }
}
/* Build a lookup: productId → [spec_key => spec_value] */
$specIndex = [];
foreach ($products as $p) {
    $specIndex[$p['id']] = [];
    foreach ($p['specs'] as $spec) {
        $specIndex[$p['id']][$spec['spec_key']] = $spec['spec_value'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Compare Products — Gadget Genesis</title>
  <link rel="stylesheet" href="style.css"/>
  <?php require_once __DIR__ . '/config/theme-init.php'; ?>
  <style>
    .cmp-wrap   { max-width:1200px;margin:0 auto;padding:40px 24px 64px; }
    .cmp-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:32px;flex-wrap:wrap;gap:12px; }
    .cmp-header h1 { font-size:26px;font-weight:800;color:var(--navy); }

    /* ── Product columns ── */
    .cmp-table { width:100%;border-collapse:collapse; }
    .cmp-table th, .cmp-table td {
      padding:14px 18px;
      text-align:center;
      border-bottom:1px solid #f1f3f8;
      font-size:14px;
    }
    .cmp-table th { background:#f8f9fc; }
    .cmp-table tr:last-child td { border-bottom:none; }
    .cmp-table tbody tr:hover td { background:#fafbff; }

    /* ── Row label column ── */
    .cmp-table td:first-child,
    .cmp-table th:first-child {
      text-align:left;
      font-weight:700;
      color:var(--gray);
      font-size:12px;
      text-transform:uppercase;
      letter-spacing:.4px;
      width:160px;
      background:#f8f9fc;
    }
    .cmp-table tbody td:first-child { color:var(--navy); font-size:13px; text-transform:none; letter-spacing:0; }

    /* ── Product header card ── */
    .cmp-product-head { padding:20px 16px; }
    .cmp-product-img  { width:140px;height:140px;object-fit:contain;border-radius:12px;background:#f3f4f6;margin:0 auto 12px;display:block; }
    .cmp-product-name { font-size:15px;font-weight:800;color:var(--navy);margin-bottom:6px; }
    .cmp-product-price { font-size:18px;font-weight:800;color:var(--navy); }
    .cmp-product-price.sale { color:#16a34a; }
    .cmp-product-old  { font-size:12px;color:#9ca3af;text-decoration:line-through;display:block;margin-bottom:2px; }
    .cmp-product-actions { display:flex;flex-direction:column;gap:8px;margin-top:14px; }
    .cmp-btn-cart {
      display:inline-flex;align-items:center;justify-content:center;gap:6px;
      padding:10px 16px;background:var(--navy);color:#fff;
      border:none;border-radius:9px;font-size:13px;font-weight:700;
      cursor:pointer;font-family:inherit;width:100%;
    }
    .cmp-btn-cart:hover { opacity:.88; }
    .cmp-btn-detail {
      display:inline-flex;align-items:center;justify-content:center;gap:6px;
      padding:9px 16px;background:#fff;color:var(--navy);
      border:1.5px solid var(--border);border-radius:9px;font-size:13px;font-weight:600;
      text-decoration:none;width:100%;box-sizing:border-box;
    }
    .cmp-btn-detail:hover { background:#f1f3f8; }
    .cmp-btn-remove {
      background:none;border:none;cursor:pointer;font-size:11px;
      color:var(--gray);margin-top:4px;font-family:inherit;
    }
    .cmp-btn-remove:hover { color:#dc2626; }
    .cmp-btn-cart [data-lucide],
    .cmp-btn-detail [data-lucide] { width:14px;height:14px; }

    /* ── Rating stars ── */
    .cmp-stars { display:inline-flex;gap:2px;vertical-align:middle; }
    .cmp-stars .star-i { width:13px;height:13px; }

    /* ── Empty / add slot ── */
    .cmp-add-slot {
      text-align:center;padding:32px 16px;color:var(--gray);
    }
    .cmp-add-slot a {
      display:inline-flex;align-items:center;gap:6px;
      padding:10px 18px;background:#f1f3f8;color:var(--navy);
      border-radius:9px;font-size:13px;font-weight:700;
      text-decoration:none;margin-top:10px;
    }
    .cmp-add-slot a:hover { background:#e0e7ff; }

    /* ── Spec match highlight ── */
    .spec-match { color:#16a34a;font-weight:700; }
    .spec-diff  { color:#dc2626; }

    /* ── Empty state ── */
    .cmp-empty { text-align:center;padding:80px 24px; }
    .cmp-empty h2 { font-size:22px;font-weight:800;color:var(--navy);margin-bottom:12px; }
    .cmp-empty p { font-size:15px;color:var(--gray);margin-bottom:24px; }

    @media(max-width:700px) {
      .cmp-table th, .cmp-table td { padding:10px 10px;font-size:13px; }
      .cmp-table td:first-child, .cmp-table th:first-child { width:100px; }
      .cmp-product-img { width:100px;height:100px; }
    }
  </style>
</head>
<body>

<!-- ══ TOPBAR ═══════════════════════════════════════════════════════════ -->
<div class="topbar">
  <div class="topbar-left">
    <span class="topbar-item"><i data-lucide="git-compare" class="ti"></i><span>Comparing Products</span></span>
  </div>
  <div class="topbar-center">Need help? Call: <a href="tel:0759809061" style="color:#fff;font-weight:700;">0759 809061</a></div>
  <div class="topbar-right">
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
      <a href="account.php" class="ibtn"><i data-lucide="user"></i></a>
      <a href="cart.php" class="ibtn cart"><i data-lucide="shopping-cart"></i><span class="cbadge">0</span></a>
    </div>
  </div>
</header>

<!-- ══ BREADCRUMB ════════════════════════════════════════════════════════ -->
<div class="breadcrumb-bar">
  <div class="bc-inner">
    <a href="index.php">Home</a>
    <i data-lucide="chevron-right" class="bc-sep"></i>
    <a href="shop.php">Shop</a>
    <i data-lucide="chevron-right" class="bc-sep"></i>
    <span>Compare</span>
  </div>
</div>

<div class="cmp-wrap">

  <div class="cmp-header">
    <h1>Compare Products</h1>
    <a href="shop.php" style="font-size:14px;font-weight:600;color:var(--navy);text-decoration:none;">← Back to Shop</a>
  </div>

  <?php if (empty($products)): ?>
    <div class="cmp-empty">
      <i data-lucide="git-compare" style="width:48px;height:48px;color:#d1d5db;margin-bottom:16px;"></i>
      <h2>No products to compare</h2>
      <p>Add products to your compare list from the shop page.</p>
      <a href="shop.php" style="display:inline-flex;align-items:center;gap:8px;padding:12px 24px;background:var(--navy);color:#fff;border-radius:10px;font-weight:700;text-decoration:none;">
        <i data-lucide="shopping-bag" style="width:16px;height:16px;"></i> Browse Products
      </a>
    </div>

  <?php else: ?>

    <div style="overflow-x:auto;">
      <table class="cmp-table">

        <!-- ── Product header row ── -->
        <thead>
          <tr>
            <th></th>
            <?php foreach ($products as $p): ?>
              <th>
                <div class="cmp-product-head">
                  <?php
                    $img = $p['image']
                        ?: ($p['colors'][0]['image'] ?? null)
                        ?: 'https://placehold.co/140x140/f3f4f6/aaa?text=' . urlencode($p['model']);
                  ?>
                  <img src="<?= e($img) ?>" alt="<?= e($p['model']) ?>" class="cmp-product-img"
                       onerror="this.src='https://placehold.co/140x140/f3f4f6/aaa?text=?'"/>
                  <div class="cmp-product-name"><?= e($p['model']) ?></div>
                  <?php if ($p['has_discount']): ?>
                    <span class="cmp-product-old"><?= formatPrice($p['lowest_price']) ?></span>
                    <div class="cmp-product-price sale"><?= formatPrice($p['lowest_sale']) ?></div>
                  <?php else: ?>
                    <div class="cmp-product-price"><?= $p['lowest_price'] > 0 ? formatPrice($p['lowest_price']) : '—' ?></div>
                  <?php endif; ?>
                  <div class="cmp-product-actions">
                    <button class="cmp-btn-cart"
                            onclick="window.addToCart(<?= $p['id'] ?>,'<?= e(addslashes($p['model'])) ?>',<?= (int)$p['lowest_sale'] ?>,'<?= e($img) ?>')">
                      <i data-lucide="shopping-cart"></i> Add to Cart
                    </button>
                    <a href="product.php?id=<?= $p['id'] ?>" class="cmp-btn-detail">
                      <i data-lucide="arrow-right"></i> Full Details
                    </a>
                    <button class="cmp-btn-remove" onclick="removeFromCompare(<?= $p['id'] ?>)">
                      ✕ Remove
                    </button>
                  </div>
                </div>
              </th>
            <?php endforeach; ?>
            <!-- Empty add slots -->
            <?php for ($i = count($products); $i < 3; $i++): ?>
              <th>
                <div class="cmp-add-slot">
                  <i data-lucide="plus-circle" style="width:32px;height:32px;color:#d1d5db;"></i>
                  <br/><span style="font-size:13px;">Add product</span>
                  <br/><a href="shop.php"><i data-lucide="shopping-bag"></i> Browse</a>
                </div>
              </th>
            <?php endfor; ?>
          </tr>
        </thead>

        <tbody>
          <!-- ── Rating ── -->
          <tr>
            <td>Rating</td>
            <?php foreach ($products as $p): ?>
              <td>
                <?php if ($p['avg_rating']): ?>
                  <div class="cmp-stars">
                    <?php for ($s=1;$s<=5;$s++): ?>
                      <i data-lucide="star" class="star-i <?= $s <= round($p['avg_rating']) ? 'on' : '' ?>"></i>
                    <?php endfor; ?>
                  </div>
                  <div style="font-size:12px;color:var(--gray);margin-top:3px;"><?= number_format($p['avg_rating'],1) ?> (<?= $p['review_count'] ?>)</div>
                <?php else: ?>
                  <span style="color:var(--gray)">No reviews</span>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
            <?php for ($i=count($products);$i<3;$i++): ?><td>—</td><?php endfor; ?>
          </tr>

          <!-- ── Collections ── -->
          <tr>
            <td>Collections</td>
            <?php foreach ($products as $p): ?>
              <td><?= implode(', ', array_map(fn($c) => e($c), $p['collections'])) ?: '—' ?></td>
            <?php endforeach; ?>
            <?php for ($i=count($products);$i<3;$i++): ?><td>—</td><?php endfor; ?>
          </tr>

          <!-- ── Storage options ── -->
          <tr>
            <td>Storage</td>
            <?php foreach ($products as $p): ?>
              <td><?= implode(', ', array_map(fn($s) => e($s['label']), $p['storages'])) ?: '—' ?></td>
            <?php endforeach; ?>
            <?php for ($i=count($products);$i<3;$i++): ?><td>—</td><?php endfor; ?>
          </tr>

          <!-- ── Colors ── -->
          <tr>
            <td>Colors</td>
            <?php foreach ($products as $p): ?>
              <td><?= implode(', ', array_map(fn($c) => e($c['name']), $p['colors'])) ?: '—' ?></td>
            <?php endforeach; ?>
            <?php for ($i=count($products);$i<3;$i++): ?><td>—</td><?php endfor; ?>
          </tr>

          <!-- ── Discount ── -->
          <tr>
            <td>Discount</td>
            <?php foreach ($products as $p): ?>
              <td>
                <?php if ($p['has_discount']): ?>
                  <?php $savePct = $p['lowest_price'] > 0 ? round(($p['lowest_price'] - $p['lowest_sale']) / $p['lowest_price'] * 100) : 0; ?>
                  <span style="background:#dcfce7;color:#166534;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;">-<?= $savePct ?>% OFF</span>
                <?php else: ?>
                  <span style="color:var(--gray);">—</span>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
            <?php for ($i=count($products);$i<3;$i++): ?><td>—</td><?php endfor; ?>
          </tr>

          <!-- ── Dynamic spec rows ── -->
          <?php foreach ($allSpecKeys as $key): ?>
            <?php
              // Collect all values for this spec key to detect matches/diffs
              $vals = array_map(fn($p) => $specIndex[$p['id']][$key] ?? null, $products);
              $defined = array_filter($vals, fn($v) => $v !== null);
              $allSame = count(array_unique($defined)) === 1 && count($defined) === count($products);
            ?>
            <tr>
              <td><?= e($key) ?></td>
              <?php foreach ($products as $p): ?>
                <?php $val = $specIndex[$p['id']][$key] ?? null; ?>
                <td class="<?= $val !== null && count($products) > 1 ? ($allSame ? 'spec-match' : 'spec-diff') : '' ?>">
                  <?= $val !== null ? e($val) : '<span style="color:#d1d5db;">—</span>' ?>
                </td>
              <?php endforeach; ?>
              <?php for ($i=count($products);$i<3;$i++): ?><td>—</td><?php endfor; ?>
            </tr>
          <?php endforeach; ?>

          <?php if (empty($allSpecKeys)): ?>
            <tr>
              <td>Specifications</td>
              <?php foreach ($products as $p): ?>
                <td style="color:var(--gray);font-style:italic;">Not available</td>
              <?php endforeach; ?>
              <?php for ($i=count($products);$i<3;$i++): ?><td>—</td><?php endfor; ?>
            </tr>
          <?php endif; ?>

        </tbody>
      </table>
    </div>

  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/config/footer.php'; ?>

<div class="toast" id="toast"></div>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script src="cart.js"></script>
<script>
function removeFromCompare(id) {
  const list = JSON.parse(localStorage.getItem('gg_compare') || '[]');
  const updated = list.filter(p => p.id !== id);
  localStorage.setItem('gg_compare', JSON.stringify(updated));
  const ids = updated.map(p => p.id).join(',');
  window.location.href = ids ? `compare.php?ids=${ids}` : 'shop.php';
}
document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();
});
</script>
</body>
</html>
