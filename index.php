<?php
require_once __DIR__ . '/config/helpers.php';

$phone   = '0759 809061';
$address = 'Opposite CPS, Kooki Towers, Buganda Rd, Kampala';
$hours   = 'Mon – Sat: 9am – 6pm';

// ── DB data for homepage ──────────────────────────────────────────────
// Trending: prefer featured products, fall back to Trending collection
$featured = [];
try {
    $fstmt = $pdo->query("SELECT p.id, p.model, p.image, p.created_at FROM products p WHERE p.is_featured = 1 ORDER BY p.created_at DESC LIMIT 8");
    $featured = $fstmt->fetchAll();
    foreach ($featured as &$fp) {
        $fp['storages']    = getStorages($pdo, $fp['id']);
        $fp['collections'] = getCollections($pdo, $fp['id']);
    }
    unset($fp);
} catch (PDOException $e) {}
$trending    = !empty($featured) ? $featured : getProductsByCollection($pdo, 'Trending', 'newest', 8);
$newArrivals = getProductsByCollection($pdo, 'New Arrivals', 'newest', 4);

// Category tiles — get one representative product image per collection
$catCollections = ['Smartphones','Audio','Smartwatches','Laptops','Accessories'];
$catImages = [];
foreach ($catCollections as $col) {
    $stmt = $pdo->prepare(
        'SELECT p.image FROM products p
         JOIN product_collections pc ON pc.product_id = p.id
         WHERE pc.collection = :col AND p.image IS NOT NULL
         LIMIT 1'
    );
    $stmt->execute([':col' => $col]);
    $row = $stmt->fetch();
    $catImages[$col] = $row['image'] ?? null;
}

// Side panel — one image per collection
$sidePanelCols = ['Smartphones','Premium Tech','Audio','Smartwatches','Deals'];
$sidePanelImages = [];
foreach ($sidePanelCols as $col) {
    $stmt = $pdo->prepare(
        'SELECT p.image FROM products p
         JOIN product_collections pc ON pc.product_id = p.id
         WHERE pc.collection = :col AND p.image IS NOT NULL
         LIMIT 1'
    );
    $stmt->execute([':col' => $col]);
    $row = $stmt->fetch();
    $sidePanelImages[$col] = $row['image'] ?? null;
}

// Hero slides — use first product image from key collections
$heroSlides = [
    ['tag'=>'Latest Smartphones', 'title'=>'Upgrade Your<br>Everyday.',       'desc'=>'Discover the latest smartphones with powerful performance and stunning design.', 'btn'=>'SHOP SMARTPHONES', 'col'=>'Smartphones',  'bg'=>'linear-gradient(120deg,#fdf4f4 60%,#f9e5e5 100%)'],
    ['tag'=>'Premium Audio',      'title'=>'Sound That<br>Moves You.',         'desc'=>'Immerse yourself in crystal-clear audio with our premium headphones.',           'btn'=>'SHOP AUDIO',       'col'=>'Audio',         'bg'=>'linear-gradient(120deg,#f4f6fd 60%,#e8edfa 100%)'],
    ['tag'=>'Smartwatches',       'title'=>'Smarter Living<br>Starts Here.',   'desc'=>'Track your health, stay connected and look great all day long.',                 'btn'=>'SHOP WATCHES',     'col'=>'Smartwatches',  'bg'=>'linear-gradient(120deg,#f4fdf6 60%,#e5f5ea 100%)'],
    ['tag'=>'Latest Laptops',     'title'=>'Power In Your<br>Hands.',          'desc'=>'Explore the most powerful laptops built for creators and professionals.',        'btn'=>'SHOP LAPTOPS',     'col'=>'Laptops',       'bg'=>'linear-gradient(120deg,#fdf8f4 60%,#faeee5 100%)'],
];

foreach ($heroSlides as &$slide) {
    $stmt = $pdo->prepare(
        'SELECT p.image FROM products p
         JOIN product_collections pc ON pc.product_id = p.id
         WHERE pc.collection = :col AND p.image IS NOT NULL
         LIMIT 1'
    );
    $stmt->execute([':col' => $slide['col']]);
    $row = $stmt->fetch();
    $slide['image'] = $row['image'] ?? null;
}
unset($slide);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Gadget Genesis</title>
  <link rel="stylesheet" href="style.css"/>
  <?php require_once __DIR__ . '/config/theme-init.php'; ?></head>
<body>

<!-- ══ TOP BAR ══════════════════════════════════════════════════════════ -->
<div class="topbar">
  <div class="topbar-left">
    <span class="topbar-item">
      <i data-lucide="refresh-ccw" class="ti"></i>
      <span>Easy Returns</span>
      <small>30-day return policy</small>
    </span>
  </div>
  <div class="topbar-center">Need help? Call us: <strong><?= $phone ?></strong></div>
  <div class="topbar-right">
    <a href="https://maps.google.com/?q=Kooki+Towers+Buganda+Road+Kampala" target="_blank"><i data-lucide="map-pin" class="ti"></i> <?= $address ?></a>
    <a href="tel:<?= str_replace(' ','',$phone) ?>"><i data-lucide="headphones" class="ti"></i> Help Center</a>
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
        <i data-lucide="shopping-cart"></i>
        <span class="cbadge">0</span>
      </a>
    </div>

  </div>
</header>

<!-- ══ HERO + SIDE ════════════════════════════════════════════════════════ -->
<section class="hero-wrap">
  <div class="hero-inner">

    <!-- Slider -->
    <div class="slider" id="slider">

      <?php foreach ($heroSlides as $i => $slide):
        $sImg = $slide['image']
          ?: 'https://placehold.co/560x400/f3f4f6/aaa?text=' . urlencode($slide['col']);
      ?>
      <div class="slide <?= $i === 0 ? 'active' : '' ?>" style="background:<?= $slide['bg'] ?>">
        <div class="sl-content">
          <span class="sl-tag"><?= e($slide['tag']) ?></span>
          <h1><?= $slide['title'] ?></h1>
          <p><?= e($slide['desc']) ?></p>
          <a href="shop.php?collection=<?= urlencode($slide['col']) ?>" class="sl-btn">
            <?= e($slide['btn']) ?> <i data-lucide="arrow-right"></i>
          </a>
        </div>
        <div class="sl-img">
          <img src="<?= $sImg ?>" alt="<?= e($slide['col']) ?>"/>
        </div>
      </div>
      <?php endforeach; ?>

      <button class="sarr sprev" id="sprev"><i data-lucide="chevron-left"></i></button>
      <button class="sarr snext" id="snext"><i data-lucide="chevron-right"></i></button>

      <div class="sdots" id="sdots">
        <?php foreach ($heroSlides as $i => $_): ?>
          <span class="dot <?= $i === 0 ? 'active' : '' ?>" data-i="<?= $i ?>"></span>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Side panel -->
    <aside class="side-panel">

      <?php
      $sidePanelDefs = [
        ['col'=>'Smartphones',  'sub'=>'Upgrade Your Everyday'],
        ['col'=>'Premium Tech', 'sub'=>'Made for You'],
        ['col'=>'Audio',        'sub'=>'Turn Up Your World'],
        ['col'=>'Smartwatches', 'sub'=>'Smarter Living Starts Here'],
        ['col'=>'Deals',        'sub'=>'Big Tech. Better Deals.'],
      ];
      foreach ($sidePanelDefs as $i => $def):
        $spImg = $sidePanelImages[$def['col']]
          ?: 'https://placehold.co/90x70/f3f4f6/aaa?text=' . urlencode($def['col']);
      ?>
        <a href="shop.php?collection=<?= urlencode($def['col']) ?>"
           class="scat <?= $i === 0 ? 'active' : '' ?>">
          <div class="scat-left">
            <span class="scat-num"><?= $i + 1 ?></span>
            <div class="scat-info">
              <strong><?= e($def['col']) ?></strong>
              <small><?= e($def['sub']) ?></small>
            </div>
          </div>
          <img src="<?= $spImg ?>" alt="<?= e($def['col']) ?>"/>
        </a>
      <?php endforeach; ?>

      <!-- Special Offers -->
      <div class="special-box">
        <div class="sp-text">
          <span class="sp-tag">Special Offers</span>
          <h3>Just For You!</h3>
          <p>Grab the best deals on top tech products.</p>
          <a href="shop.php?collection=Deals" class="sp-btn">VIEW ALL DEALS</a>
        </div>
        <div class="sp-imgs">
          <?php $dealImg = $sidePanelImages['Deals'] ?: 'https://placehold.co/120x80/f3f4f6/aaa?text=Deals'; ?>
          <img src="<?= $dealImg ?>" alt="Deals" class="sp-img1"/>
        </div>
      </div>

    </aside>
  </div>
</section>

<!-- ══ FEATURES ───────────────────────────────────────────────────────── -->
<div class="features">
  <div class="feat-inner">
    <div class="feat">
      <i data-lucide="truck" class="feat-ico"></i>
      <div><strong>Free Shipping</strong><span>On orders over $50</span></div>
    </div>
    <div class="feat-sep"></div>
    <div class="feat">
      <i data-lucide="refresh-ccw" class="feat-ico"></i>
      <div><strong>Easy Returns</strong><span>30-day return policy</span></div>
    </div>
    <div class="feat-sep"></div>
    <div class="feat">
      <i data-lucide="shield-check" class="feat-ico"></i>
      <div><strong>Secure Payment</strong><span>100% secure checkout</span></div>
    </div>
    <div class="feat-sep"></div>
    <div class="feat">
      <i data-lucide="headphones" class="feat-ico"></i>
      <div><strong>24/7 Support</strong><span>We're here to help</span></div>
    </div>
  </div>
</div>

<!-- ══ SHOP BY CATEGORY ──────────────────────────────────────────────── -->
<section class="sec">
  <div class="sec-inner">
    <div class="sec-head">
      <h2>Shop by Category</h2>
      <a href="shop.php" class="viewall">View all categories <i data-lucide="arrow-right" class="vai"></i></a>
    </div>
    <div class="cats-row">
      <?php foreach ($catCollections as $col):
        $img = $catImages[$col]
          ?: 'https://placehold.co/160x160/f3f4f6/aaa?text=' . urlencode($col);
      ?>
        <a href="shop.php?collection=<?= urlencode($col) ?>" class="cat-tile">
          <div class="cat-img"><img src="<?= $img ?>" alt="<?= e($col) ?>"/></div>
          <strong><?= e($col) ?></strong>
          <span>Shop now <i data-lucide="arrow-right" class="sni"></i></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══ TRENDING + NEW ARRIVALS ──────────────────────────────────────── -->
<section class="sec sec-2col">
  <div class="sec-inner two-col">

    <!-- Trending Now -->
    <div>
      <div class="sec-head">
        <h2>Trending Now</h2>
        <a href="shop.php?collection=Trending" class="viewall">View all <i data-lucide="arrow-right" class="vai"></i></a>
      </div>
      <div class="carousel">
        <button class="carr-btn" id="tprev"><i data-lucide="chevron-left"></i></button>
        <div class="carr-wrap">
          <div class="carr-track" id="ttrack">
            <?php if (empty($trending)): ?>
              <p style="padding:20px;color:#999">No trending products yet.</p>
            <?php else: foreach ($trending as $p):
              $pImg   = $p['image'] ?: 'https://placehold.co/200x200/f3f4f6/aaa?text=' . urlencode($p['model']);
              $pPrice = lowestPrice($p['storages']);
            ?>
              <a href="product.php?id=<?= $p['id'] ?>" class="pcard">
                <div class="pcard-img"><img src="<?= $pImg ?>" alt="<?= e($p['model']) ?>"/></div>
                <div class="pcard-info">
                  <p class="pname"><?= e($p['model']) ?></p>
                  <p class="pprice"><?= $pPrice ? formatPrice($pPrice) : '—' ?></p>
                  <div class="prating">
                    <?php for ($s=1;$s<=5;$s++): ?>
                      <i data-lucide="star" class="star on"></i>
                    <?php endfor; ?>
                  </div>
                </div>
              </a>
            <?php endforeach; endif; ?>
          </div>
        </div>
        <button class="carr-btn" id="tnext"><i data-lucide="chevron-right"></i></button>
      </div>
    </div>

    <!-- New Arrivals + Trust -->
    <div>
      <div class="sec-head">
        <h2>New Arrivals</h2>
      </div>
      <div class="arrivals">
        <?php if (empty($newArrivals)): ?>
          <p style="color:#999;font-size:13px;padding:12px 0">No new arrivals yet.</p>
        <?php else: foreach ($newArrivals as $a):
          $aImg   = $a['image'] ?: 'https://placehold.co/200x200/f3f4f6/aaa?text=' . urlencode($a['model']);
          $aPrice = lowestPrice($a['storages']);
        ?>
          <a href="product.php?id=<?= $a['id'] ?>" class="acard">
            <div class="acard-img"><img src="<?= $aImg ?>" alt="<?= e($a['model']) ?>"/></div>
            <p class="pname"><?= e($a['model']) ?></p>
            <p class="pprice"><?= $aPrice ? formatPrice($aPrice) : '—' ?></p>
          </a>
        <?php endforeach; endif; ?>
      </div>

      <!-- Trust -->
      <div class="trust">
        <div class="trust-item">
          <i data-lucide="badge-check" class="trust-ico"></i>
          <div><strong>Genuine Products</strong><small>100% original gadgets</small></div>
        </div>
        <div class="trust-item">
          <i data-lucide="shield" class="trust-ico"></i>
          <div><strong>1-Year Warranty</strong><small>Shop with confidence</small></div>
        </div>
        <div class="trust-item">
          <i data-lucide="store" class="trust-ico"></i>
          <div><strong>Trusted Store</strong><small>Thousands of happy customers</small></div>
        </div>
      </div>
    </div>

  </div>
</section>

<!-- ══ RECENTLY VIEWED ═══════════════════════════════════════════════════ -->
<section class="recent-section" style="display:none;">
  <div class="recent-inner">
    <h2 class="recent-title">Recently Viewed</h2>
    <div class="recent-track" id="recentStrip"></div>
  </div>
</section>

<?php require_once __DIR__ . '/config/footer.php'; ?>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script src="cart.js"></script>
<script src="index.js"></script>
</body>
</html>






