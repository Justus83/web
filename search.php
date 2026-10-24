<?php
/* ═══════════════════════════════════════════════════════════════════════
   search.php — Full search results page
   ═══════════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/config/helpers.php';

$q      = trim($_GET['q'] ?? '');
$search = '%' . $q . '%';

$results = [];
if ($q !== '') {
    try {
        $stmt = $pdo->prepare("
            SELECT p.id, p.model, p.image, p.description, p.created_at
            FROM products p
            WHERE p.model LIKE :q1 OR p.description LIKE :q2
            ORDER BY
                CASE WHEN p.model LIKE :q3 THEN 0 ELSE 1 END,
                p.created_at DESC
            LIMIT 100
        ");
        $stmt->execute([':q1' => $search, ':q2' => $search, ':q3' => $q . '%']);
        $products = $stmt->fetchAll();

        foreach ($products as &$p) {
            $p['storages']    = getStorages($pdo, $p['id']);
            $p['collections'] = getCollections($pdo, $p['id']);
            $p['discount']    = getDiscount($pdo, $p['id']);
            $p['price']       = lowestPrice($p['storages']);
            $p['avg_rating']  = (float) $pdo->query(
                "SELECT COALESCE(ROUND(AVG(rating),1),0) FROM product_reviews WHERE product_id = {$p['id']} AND approved = 1"
            )->fetchColumn();
        }
        unset($p);
        $results = $products;
    } catch (PDOException $e) {
        $results = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title><?= $q ? 'Search: ' . e($q) : 'Search' ?> — Gadget Genesis</title>
  <link rel="stylesheet" href="style.css"/>
  <?php require_once __DIR__ . '/config/theme-init.php'; ?>
  <style>
    .search-hero {
      background: linear-gradient(135deg, var(--navy), #2563eb);
      padding: 48px 24px 36px;
      text-align: center;
      color: #fff;
    }
    .search-hero h1 { font-size: 28px; font-weight: 800; margin-bottom: 20px; }
    .search-form-wrap {
      display: flex; gap: 0; max-width: 560px; margin: 0 auto;
      background: #fff; border-radius: 12px; overflow: hidden;
      box-shadow: 0 4px 20px rgba(0,0,0,.2);
    }
    .search-form-wrap input {
      flex: 1; padding: 14px 18px; border: none; outline: none;
      font-size: 16px; font-family: inherit; color: var(--navy);
    }
    .search-form-wrap button {
      padding: 14px 22px; background: var(--navy); color: #fff;
      border: none; cursor: pointer; font-size: 15px; font-weight: 700;
      font-family: inherit; display: flex; align-items: center; gap: 8px;
      transition: background .15s;
    }
    .search-form-wrap button:hover { background: #1e3a6e; }
    .search-form-wrap button [data-lucide] { width: 18px; height: 18px; }
    .search-meta {
      padding: 20px 0 8px;
      font-size: 14px; color: var(--gray);
    }
    .search-meta strong { color: var(--navy); }
  </style>
</head>
<body>

<!-- ══ TOP BAR ══════════════════════════════════════════════════════════ -->
<div class="topbar">
  <div class="topbar-left">
    <span class="topbar-item"><i data-lucide="shield-check" class="ti"></i><span>Secure Shopping</span><small>SSL encrypted</small></span>
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
      <a href="cart.php" class="ibtn cart">
        <i data-lucide="shopping-cart"></i><span class="cbadge">0</span>
      </a>
    </div>
  </div>
</header>

<!-- ══ SEARCH HERO ═══════════════════════════════════════════════════════ -->
<div class="search-hero">
  <h1><?= $q ? 'Search Results for "' . e($q) . '"' : 'Search Products' ?></h1>
  <form class="search-form-wrap" action="search.php" method="GET">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search for phones, laptops, audio…" autofocus/>
    <button type="submit"><i data-lucide="search"></i> Search</button>
  </form>
</div>

<!-- ══ RESULTS ═══════════════════════════════════════════════════════════ -->
<div class="shop-wrap" style="padding-top:0">
  <div class="shop-inner" style="grid-template-columns:1fr">
    <main class="shop-main">

      <?php if ($q === ''): ?>
        <div class="shop-empty" style="margin-top:40px">
          <i data-lucide="search" class="empty-ico"></i>
          <h3>Start searching</h3>
          <p>Enter a product name above to find what you're looking for.</p>
        </div>

      <?php elseif (empty($results)): ?>
        <div class="shop-empty" style="margin-top:40px">
          <i data-lucide="package-open" class="empty-ico"></i>
          <h3>No results for "<?= e($q) ?>"</h3>
          <p>Try a different keyword or browse all products.</p>
          <a href="shop.php" class="btn-primary">Browse All Products</a>
        </div>

      <?php else: ?>
        <p class="search-meta">
          Found <strong><?= count($results) ?></strong> result<?= count($results) !== 1 ? 's' : '' ?>
          for <strong>"<?= e($q) ?>"</strong>
        </p>
        <div class="shop-grid" id="shopGrid">
          <?php foreach ($results as $p):
            $img         = $p['image'] ?: 'https://placehold.co/400x300/f3f4f6/aaa?text=' . urlencode($p['model']);
            $basePrice   = $p['price'];
            $discount    = $p['discount'] ?? null;
            $salePrice   = $discount ? applyDiscount($basePrice, $discount) : $basePrice;
            $hasDiscount = $discount && $salePrice < $basePrice;
            $savePct     = $hasDiscount ? discountPct($salePrice, $basePrice) : 0;
            $avgRating   = $p['avg_rating'] ?? 0;
          ?>
            <a href="product.php?id=<?= $p['id'] ?>" class="shop-card"
               data-price="<?= (int)$salePrice ?>" data-rating="<?= $avgRating ?>"
               data-collections="<?= e(implode(',', $p['collections'])) ?>">
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
                    <span style="font-size:12px;color:var(--gray);margin-left:3px;"><?= number_format($avgRating,1) ?></span>
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
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </main>
  </div>
</div>

<?php require_once __DIR__ . '/config/footer.php'; ?>

<div class="toast" id="toast"></div>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script src="cart.js"></script>
<script src="shop.js"></script>
</body>
</html>
