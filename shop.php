<?php
require_once __DIR__ . '/config/helpers.php';

// ── Collection, sort & page from URL ─────────────────────────────────
$collection = trim($_GET['collection'] ?? 'All');
$sort       = trim($_GET['sort']       ?? 'newest');
$page       = max(1, (int) ($_GET['page'] ?? 1));
$perPage    = 12;

$allowed = array_merge(['All'], COLLECTIONS);
if (!in_array($collection, $allowed)) $collection = 'All';
$validSorts = ['newest','price_asc','price_desc','oldest'];
if (!in_array($sort, $validSorts)) $sort = 'newest';

// ── Load ALL matching products (for accurate count & JS filters) ──────
$allProducts = getProductsByCollection($pdo, $collection, $sort, 500);

// Flatten price onto each product for JS filtering
foreach ($allProducts as &$p) {
    $p['price'] = lowestPrice($p['storages']);
}
unset($p);

// ── Server-side price sort (storages now loaded) ──────────────────────
if ($sort === 'price_asc') {
    usort($allProducts, fn($a, $b) => $a['price'] <=> $b['price']);
} elseif ($sort === 'price_desc') {
    usort($allProducts, fn($a, $b) => $b['price'] <=> $a['price']);
}

// ── Pagination ────────────────────────────────────────────────────────
$totalProducts = count($allProducts);
$totalPages    = max(1, (int) ceil($totalProducts / $perPage));
$page          = min($page, $totalPages);
$offset        = ($page - 1) * $perPage;
$products      = array_slice($allProducts, $offset, $perPage);

$max_price   = $allProducts ? (int) max(array_column($allProducts, 'price')) : 5000000;
$max_price   = $max_price ?: 5000000;
$collections = array_merge(['All'], COLLECTIONS);

// ── Build pagination URL helper ───────────────────────────────────────
function pageUrl(int $p, string $col, string $sort): string {
    $params = ['collection' => $col, 'sort' => $sort, 'page' => $p];
    return 'shop.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title><?= e($collection) ?> — Gadget Genesis</title>
  <link rel="stylesheet" href="style.css"/>
  <?php require_once __DIR__ . '/config/theme-init.php'; ?></head>
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
      <a href="account.php" class="ibtn"><i data-lucide="user"></i></a>
      <a href="#" class="ibtn"><i data-lucide="heart"></i></a>
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
    <a href="shop.php">Shop</a>
    <?php if ($collection !== 'All'): ?>
      <i data-lucide="chevron-right" class="bc-sep"></i>
      <span><?= htmlspecialchars($collection) ?></span>
    <?php endif; ?>
  </div>
</div>

<!-- ══ SHOP LAYOUT ═══════════════════════════════════════════════════════ -->
<div class="shop-wrap">
  <div class="shop-inner">

    <!-- ── SIDEBAR ── -->
    <aside class="shop-sidebar" id="shopSidebar">

      <div class="sidebar-block">
        <h3>Collections</h3>
        <ul class="filter-list">
          <?php foreach ($collections as $col): ?>
            <li>
              <a href="shop.php?collection=<?= urlencode($col) ?>&sort=<?= $sort ?>"
                 class="filter-link <?= $col === $collection ? 'active' : '' ?>">
                <?= htmlspecialchars($col) ?>
                <?php
                if ($col === 'All') {
                    $countStmt = $pdo->query('SELECT COUNT(*) FROM products');
                } else {
                    $countStmt = $pdo->prepare('SELECT COUNT(DISTINCT product_id) FROM product_collections WHERE collection = :col');
                    $countStmt->execute([':col' => $col]);
                }
                $count = (int) $countStmt->fetchColumn();
                ?>
                <span class="filter-count"><?= $count ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="sidebar-block">
        <h3>Price Range</h3>
        <div class="price-range-wrap">
          <input type="range" id="priceRange" class="price-slider"
                 min="0" max="<?= $max_price ?>"
                 value="<?= $max_price ?>" step="50000"/>
          <div class="price-range-labels">
            <span>UGX 0</span>
            <span id="priceRangeVal"><?= formatPrice($max_price) ?></span>
          </div>
        </div>
      </div>

      <div class="sidebar-block">
        <h3>Rating</h3>
        <ul class="filter-list">
          <?php foreach ([5,4,3] as $r): ?>
            <li>
              <label class="rating-filter">
                <input type="radio" name="rating" value="<?= $r ?>" <?= $r===4?'checked':'' ?> class="rating-radio"/>
                <span class="rating-stars">
                  <?php for ($s=1;$s<=5;$s++): ?>
                    <i data-lucide="star" class="star-i <?= $s<=$r?'on':'' ?>"></i>
                  <?php endfor; ?>
                </span>
                <span class="rating-label"><?= $r ?>+ Stars</span>
              </label>
            </li>
          <?php endforeach; ?>
          <li>
            <label class="rating-filter">
              <input type="radio" name="rating" value="0" class="rating-radio"/>
              <span class="rating-label">All Ratings</span>
            </label>
          </li>
        </ul>
      </div>

      <div class="sidebar-block">
        <h3>Availability</h3>
        <ul class="filter-list">
          <li><label class="check-filter"><input type="checkbox" checked class="avail-check" value="instock"/> In Stock</label></li>
          <li><label class="check-filter"><input type="checkbox" class="avail-check" value="sale"/> On Sale</label></li>
        </ul>
      </div>

      <button class="btn-clear-filters" id="clearFilters">Clear all filters</button>

    </aside>

    <!-- ── MAIN ── -->
    <main class="shop-main">

      <!-- Toolbar -->
      <div class="shop-toolbar">
        <div class="shop-toolbar-left">
          <button class="sidebar-toggle" id="sidebarToggle">
            <i data-lucide="sliders-horizontal"></i> Filters
          </button>
          <span class="results-count">
            <strong id="visibleCount"><?= $totalProducts ?></strong> product<?= $totalProducts !== 1 ? 's' : '' ?>
            <?php if ($collection !== 'All'): ?>
              in <strong><?= htmlspecialchars($collection) ?></strong>
            <?php endif; ?>
          </span>
        </div>
        <div class="shop-toolbar-right">
          <div class="sort-wrap">
            <label for="sortSelect"><i data-lucide="arrow-up-down"></i></label>
            <select id="sortSelect" class="sort-select"
                    onchange="window.location.href='shop.php?collection=<?= urlencode($collection) ?>&sort='+this.value">
              <option value="newest"     <?= $sort==='newest'    ?'selected':'' ?>>Newest</option>
              <option value="oldest"     <?= $sort==='oldest'    ?'selected':'' ?>>Oldest</option>
              <option value="price_asc"  <?= $sort==='price_asc' ?'selected':'' ?>>Price: Low to High</option>
              <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>>Price: High to Low</option>
            </select>
          </div>
          <div class="view-toggle">
            <button class="view-btn active" id="gridView" title="Grid view"><i data-lucide="layout-grid"></i></button>
            <button class="view-btn" id="listView" title="List view"><i data-lucide="layout-list"></i></button>
          </div>
        </div>
      </div>

      <!-- Collection banner -->
      <?php if ($collection !== 'All'): ?>
        <div class="coll-banner">
          <div class="coll-banner-text">
            <h1><?= e($collection) ?></h1>
            <p><?= $totalProducts ?> product<?= $totalProducts !== 1 ? 's' : '' ?> available</p>
          </div>
        </div>
      <?php endif; ?>

      <!-- Product Grid -->
      <?php if (empty($products)): ?>
        <div class="shop-empty">
          <i data-lucide="package-open" class="empty-ico"></i>
          <h3>No products found</h3>
          <p>Try a different collection or clear your filters.</p>
          <a href="shop.php" class="btn-primary">Browse all products</a>
        </div>
      <?php else: ?>
        <div class="shop-grid" id="shopGrid">
          <?php foreach ($products as $p):
            $img        = $p['image'] ?: 'https://placehold.co/400x300/f3f4f6/aaa?text=' . urlencode($p['model']);
            $basePrice  = $p['price'];
            $discount   = $p['discount'] ?? null;
            $salePrice  = $discount ? applyDiscount($basePrice, $discount) : $basePrice;
            $hasDiscount = $discount && $salePrice < $basePrice;
            $savePct    = $hasDiscount ? discountPct($salePrice, $basePrice) : 0;
            $avgRating  = $p['avg_rating'] ?? 0;
            $imgData    = 'data-price="' . (int)$salePrice . '" data-rating="' . $avgRating . '" data-collections="' . e(implode(',', $p['collections'])) . '"';
          ?>
            <a href="product.php?id=<?= $p['id'] ?>"
               class="shop-card" <?= $imgData ?>>

              <div class="shop-card-img">
                <img src="<?= $img ?>" alt="<?= e($p['model']) ?>" loading="lazy"/>
                <?php if ($hasDiscount): ?>
                  <span class="shop-sale-badge">-<?= $savePct ?>%</span>
                <?php endif; ?>
                <button class="shop-card-wish" onclick="event.preventDefault();toggleWish(this)" title="Wishlist">
                  <i data-lucide="heart"></i>
                </button>
              </div>

              <div class="shop-card-body">
                <div class="shop-card-colls">
                  <?php foreach ($p['collections'] as $col): ?>
                    <span class="shop-coll-tag"><?= e($col) ?></span>
                  <?php endforeach; ?>
                </div>

                <p class="shop-card-name"><?= e($p['model']) ?></p>

                <div class="shop-card-rating">
                  <?php for ($s=1;$s<=5;$s++): ?>
                    <i data-lucide="star" class="star-i <?= $avgRating && $s <= round($avgRating) ? 'on' : '' ?>"></i>
                  <?php endfor; ?>
                  <?php if ($avgRating): ?>
                    <span style="font-size:12px;color:var(--gray);margin-left:3px;"><?= number_format($avgRating,1) ?> (<?= (int)($p['review_count'] ?? 0) ?>)</span>
                  <?php endif; ?>
                </div>

                <div class="shop-card-price-row">
                  <?php if ($hasDiscount): ?>
                    <span class="shop-card-old"><?= formatPrice($basePrice) ?></span>
                    <span class="shop-card-price sale"><?= formatPrice($salePrice) ?></span>
                  <?php else: ?>
                    <span class="shop-card-price"><?= $basePrice ? formatPrice($basePrice) : '—' ?></span>
                  <?php endif; ?>
                </div>

                <button class="shop-card-btn"
                        onclick="event.preventDefault();addToCart(<?= $p['id'] ?>,'<?= e(addslashes($p['model'])) ?>',<?= (int)$salePrice ?>,'<?= $img ?>')">
                  <i data-lucide="shopping-cart"></i> Add to Cart
                </button>
                <button class="shop-card-qv"
                        onclick="event.preventDefault();openQuickView(<?= $p['id'] ?>)"
                        title="Quick View">
                  <i data-lucide="eye"></i> Quick View
                </button>
                <button class="shop-card-compare"
                        onclick="event.preventDefault();toggleCompare(<?= $p['id'] ?>,'<?= e(addslashes($p['model'])) ?>','<?= $img ?>')"
                        data-product-id="<?= $p['id'] ?>"
                        title="Add to Compare">
                  <i data-lucide="git-compare"></i> Compare
                </button>
              </div>
            </a>
          <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav class="shop-pagination" aria-label="Product pages">
          <?php if ($page > 1): ?>
            <a href="<?= pageUrl($page - 1, $collection, $sort) ?>" class="pag-btn pag-prev">
              <i data-lucide="arrow-left"></i> Prev
            </a>
          <?php endif; ?>

          <?php
          // Show window of page links: always first, last, current ±2
          $shown = [];
          for ($i = 1; $i <= $totalPages; $i++) {
              if ($i === 1 || $i === $totalPages || abs($i - $page) <= 2) {
                  $shown[] = $i;
              }
          }
          $prev = null;
          foreach ($shown as $pNum):
              if ($prev !== null && $pNum - $prev > 1): ?>
                <span class="pag-dots">…</span>
              <?php endif; ?>
              <a href="<?= pageUrl($pNum, $collection, $sort) ?>"
                 class="pag-btn <?= $pNum === $page ? 'active' : '' ?>"
                 <?= $pNum === $page ? 'aria-current="page"' : '' ?>>
                <?= $pNum ?>
              </a>
          <?php $prev = $pNum; endforeach; ?>

          <?php if ($page < $totalPages): ?>
            <a href="<?= pageUrl($page + 1, $collection, $sort) ?>" class="pag-btn pag-next">
              Next <i data-lucide="arrow-right"></i>
            </a>
          <?php endif; ?>
        </nav>
        <?php endif; ?>

      <?php endif; ?>

    </main>
  </div>
</div>

<!-- ══ FOOTER ════════════════════════════════════════════════════════════ -->
<?php require_once __DIR__ . '/config/footer.php'; ?>

<div class="toast" id="toast"></div>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script src="cart.js"></script>
<script src="shop.js"></script>
</body>
</html>







