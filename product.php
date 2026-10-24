<?php
require_once __DIR__ . '/config/helpers.php';

// ── Load product from DB ──────────────────────────────────────────────
$id      = (int) ($_GET['id'] ?? 0);
$product = $id ? getProduct($pdo, $id) : null;

if (!$product) {
    http_response_code(404);
    die('<!DOCTYPE html><html><body><h2>Product not found.</h2><a href="shop.php">Back to shop</a></body></html>');
}

// ── Related products (same first collection, exclude self) ────────────
$firstCollection = $product['collections'][0] ?? 'All';
$stmt = $pdo->prepare(
    'SELECT DISTINCT p.id, p.model, p.image, p.created_at
     FROM products p
     JOIN product_collections pc ON pc.product_id = p.id
     WHERE pc.collection = :col AND p.id != :pid
     ORDER BY p.created_at DESC
     LIMIT 8'
);
$stmt->execute([':col' => $firstCollection, ':pid' => $product['id']]);
$relatedRaw = $stmt->fetchAll();

$related = [];
foreach ($relatedRaw as $r) {
    $r['storages']    = getStorages($pdo, $r['id']);
    $r['collections'] = getCollections($pdo, $r['id']);
    $related[] = $r;
}

$lowestP  = lowestPrice($product['storages']);
$discount = $product['discount'] ?? null;
$lowestSale = applyDiscount($lowestP, $discount);
$hasDiscount = $discount && $lowestSale < $lowestP;
$savePct = $hasDiscount ? discountPct($lowestSale, $lowestP) : 0;

// Review stats computed from real DB data
$reviewStats    = reviewStats($product['reviews']);
$productRating  = $reviewStats['avg'];
$productReviews = $reviewStats['count'];
$reviewBars     = $reviewStats['bars'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title><?= e($product['model']) ?> — Gadget Genesis</title>
  <link rel="stylesheet" href="style.css"/>
  <?php require_once __DIR__ . '/config/theme-init.php'; ?>
  <style>
    /* ── Review write form ── */
    .rev-write-wrap { margin-top: 32px; padding-top: 28px; border-top: 1px solid var(--border); }
    .rev-write-title { font-size: 17px; font-weight: 800; color: var(--navy); margin-bottom: 18px; }
    .rev-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 14px; }
    @media (max-width: 600px) { .rev-form-row { grid-template-columns: 1fr; } }
    .rev-field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
    .rev-field label { font-size: 13px; font-weight: 600; color: var(--navy); }
    .rev-field input[type=text], .rev-field textarea {
      padding: 10px 12px; border: 1.5px solid var(--border); border-radius: 9px;
      font-size: 14px; font-family: inherit; color: var(--navy); outline: none;
      transition: border-color .15s;
    }
    .rev-field input:focus, .rev-field textarea:focus { border-color: var(--navy); }
    .rev-field textarea { resize: vertical; }
    /* Star picker */
    .rev-star-picker { display: flex; gap: 4px; }
    .star-pick-btn { background: none; border: none; cursor: pointer; padding: 2px; line-height: 1; }
    .star-pick-btn [data-lucide] { width: 22px; height: 22px; color: #d1d5db; transition: color .15s; }
    .star-pick-btn.active [data-lucide],
    .star-pick-btn:hover [data-lucide] { color: #f59e0b; fill: #f59e0b; }
    /* Submit button */
    .btn-submit-review {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 12px 24px; background: var(--navy); color: #fff;
      border: none; border-radius: 9px; font-size: 14px; font-weight: 700;
      cursor: pointer; font-family: inherit; transition: opacity .15s;
    }
    .btn-submit-review:hover { opacity: .88; }
    .btn-submit-review [data-lucide] { width: 16px; height: 16px; }
    .btn-submit-review:disabled { opacity: .5; cursor: not-allowed; }
    /* Feedback message */
    .rev-form-msg { margin-top: 10px; font-size: 13px; font-weight: 600; padding: 10px 14px; border-radius: 8px; }
    .rev-form-msg.success { background: #f0fdf4; color: #166534; }
    .rev-form-msg.error   { background: #fef2f2; color: #b91c1c; }
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
    <a href="shop.php?collection=<?= urlencode($firstCollection) ?>"><?= e($firstCollection) ?></a>
    <i data-lucide="chevron-right" class="bc-sep"></i>
    <span><?= e($product['model']) ?></span>
  </div>
</div>

<!-- ══ PRODUCT MAIN ══════════════════════════════════════════════════════ -->
<section class="prod-section">
  <div class="prod-inner">

    <!-- Gallery -->
    <div class="prod-gallery">
      <?php
        // Main image: model image → first extra gallery image → first color image
        $mainImage = $product['image']
          ?: ($product['extra_images'][0]['image'] ?? null)
          ?: ($product['colors'][0]['image'] ?? null);
        $placeholder = 'https://placehold.co/600x600/f3f4f6/aaa?text=' . urlencode($product['model']);

        // Build unified thumb list: extra gallery images + color images
        $allThumbs = [];
        foreach ($product['extra_images'] as $ei) {
            $allThumbs[] = ['src' => $ei['image'], 'label' => $ei['caption'] ?: '', 'type' => 'gallery'];
        }
        foreach ($product['colors'] as $c) {
            if ($c['image']) $allThumbs[] = ['src' => $c['image'], 'label' => $c['name'], 'type' => 'color', 'name' => $c['name']];
        }
      ?>
      <div class="gallery-main" id="galleryMain">
        <img src="<?= $mainImage ?: $placeholder ?>"
             alt="<?= e($product['model']) ?>" id="mainImg"/>
        <button class="gal-wish" id="wishBtn" title="Add to wishlist">
          <i data-lucide="heart" id="wishIcon"></i>
        </button>
        <?php if (!empty($allThumbs)): ?>
        <button class="gal-expand" id="galExpand" title="View full screen">
          <i data-lucide="expand"></i>
        </button>
        <?php endif; ?>
      </div>

      <?php if (!empty($allThumbs)): ?>
      <div class="gallery-thumbs" id="galleryThumbs">
        <!-- Main product image thumb -->
        <?php if ($product['image']): ?>
          <div class="gthumb <?= empty($product['extra_images']) ? 'active' : '' ?>"
               data-img="<?= $product['image'] ?>"
               data-color=""
               data-index="-1">
            <img src="<?= $product['image'] ?>" alt="<?= e($product['model']) ?>"/>
          </div>
        <?php endif; ?>
        <!-- Extra gallery image thumbs -->
        <?php foreach ($product['extra_images'] as $i => $ei): ?>
          <div class="gthumb <?= !$product['image'] && $i === 0 ? 'active' : '' ?>"
               data-img="<?= $ei['image'] ?>"
               data-color=""
               data-index="extra-<?= $i ?>">
            <img src="<?= $ei['image'] ?>" alt="<?= e($ei['caption'] ?: 'Image ' . ($i+1)) ?>"/>
          </div>
        <?php endforeach; ?>
        <!-- Color image thumbs -->
        <?php foreach ($product['colors'] as $i => $color):
          if (!$color['image']) continue;
        ?>
          <div class="gthumb"
               data-img="<?= $color['image'] ?>"
               data-color="<?= e($color['name']) ?>"
               data-index="<?= $i ?>">
            <img src="<?= $color['image'] ?>" alt="<?= e($color['name']) ?>"/>
            <span class="gthumb-label"><?= e($color['name']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <?php elseif (!empty($product['colors'])): ?>
      <!-- Legacy: only color thumbs (no extra images) -->
      <div class="gallery-thumbs" id="galleryThumbs">
        <?php foreach ($product['colors'] as $i => $color):
          $thumbImg = $color['image'] ?: $placeholder;
        ?>
          <div class="gthumb <?= $i === 0 ? 'active' : '' ?>"
               data-img="<?= $thumbImg ?>"
               data-color="<?= e($color['name']) ?>"
               data-index="<?= $i ?>">
            <img src="<?= $thumbImg ?>" alt="<?= e($color['name']) ?>"/>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Info -->
    <div class="prod-info">

      <!-- Collections -->
      <div class="prod-collections">
        <?php foreach ($product['collections'] as $col): ?>
          <span class="prod-coll-badge"><?= e($col) ?></span>
        <?php endforeach; ?>
      </div>

      <h1 class="prod-name"><?= e($product['model']) ?></h1>

      <!-- Rating -->
      <div class="prod-rating">
        <div class="prod-stars">
          <?php for ($s = 1; $s <= 5; $s++): ?>
            <i data-lucide="star" class="star-i <?= $productRating && $s <= round($productRating) ? 'on' : '' ?>"></i>
          <?php endfor; ?>
        </div>
        <?php if ($productRating): ?>
          <span class="prod-score"><?= number_format($productRating, 1) ?></span>
          <span class="prod-sep">·</span>
          <span class="prod-score" style="font-weight:400;color:var(--gray)"><?= $productReviews ?> review<?= $productReviews !== 1 ? 's' : '' ?></span>
          <span class="prod-sep">|</span>
        <?php endif; ?>
        <span class="prod-stock instock">
          <i data-lucide="circle-check"></i> In Stock
        </span>
      </div>

      <!-- Price -->
      <div class="prod-price-block">
        <?php if (!empty($product['storages'])): ?>
          <?php if ($hasDiscount): ?>
            <span class="prod-price-old"><?= formatPrice($lowestP) ?></span>
            <span class="prod-price sale" id="prodPrice"><?= formatPrice($lowestSale) ?></span>
            <span class="prod-sale-badge">-<?= $savePct ?>% OFF</span>
          <?php else: ?>
            <span class="prod-price" id="prodPrice"><?= formatPrice($lowestP) ?></span>
          <?php endif; ?>
        <?php else: ?>
          <span class="prod-price" id="prodPrice">—</span>
        <?php endif; ?>
      </div>

      <?php if ($product['description']): ?>
        <p class="prod-desc"><?= e($product['description']) ?></p>
      <?php endif; ?>

      <div class="prod-divider"></div>

      <!-- Storage selector -->
      <?php if (!empty($product['storages'])): ?>
      <div class="prod-option-group">
        <div class="opt-label">
          Storage: <strong id="selectedStorage"><?= e($product['storages'][0]['label']) ?></strong>
        </div>
        <div class="storage-opts" id="storageOpts">
          <?php foreach ($product['storages'] as $i => $s):
            $saleP = applyDiscount((float)$s['price'], $discount);
            $storHasDisc = $discount && $saleP < (float)$s['price'];
          ?>
            <button class="stor-btn <?= $i === 0 ? 'active' : '' ?>"
                    data-price="<?= $saleP ?>"
                    data-base-price="<?= (float)$s['price'] ?>"
                    data-label="<?= e($s['label']) ?>">
              <?= e($s['label']) ?>
              <?php if ($storHasDisc): ?>
                <span style="text-decoration:line-through;color:#9ca3af;font-size:11px;"><?= formatPrice((float)$s['price']) ?></span>
                <span style="color:#16a34a;font-weight:800;"><?= formatPrice($saleP) ?></span>
              <?php else: ?>
                <span><?= formatPrice((float)$s['price']) ?></span>
              <?php endif; ?>
            </button>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Color selector -->
      <?php if (!empty($product['colors'])): ?>
      <div class="prod-option-group">
        <div class="opt-label">
          Color: <strong id="selectedColor"><?= e($product['colors'][0]['name']) ?></strong>
        </div>
        <div class="color-opts" id="colorOpts">
          <?php foreach ($product['colors'] as $i => $color):
            $cImg = $color['image'] ?: $placeholder;
          ?>
            <div class="col-opt <?= $i === 0 ? 'active' : '' ?>"
                 data-img="<?= $cImg ?>"
                 data-name="<?= e($color['name']) ?>"
                 data-index="<?= $i ?>">
              <div class="col-opt-img">
                <img src="<?= $cImg ?>" alt="<?= e($color['name']) ?>"/>
              </div>
              <span><?= e($color['name']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="prod-divider"></div>

      <!-- Quantity + Cart -->
      <div class="prod-cart-row">
        <div class="qty-wrap">
          <button class="qty-btn" id="qtyMinus"><i data-lucide="minus"></i></button>
          <span class="qty-val" id="qtyVal">1</span>
          <button class="qty-btn" id="qtyPlus"><i data-lucide="plus"></i></button>
        </div>
        <button class="btn-add-cart" id="addCartBtn">
          <i data-lucide="shopping-cart"></i> Add to Cart
        </button>
        <button class="btn-buy-now">Buy Now</button>
      </div>

      <!-- Meta -->
      <div class="prod-meta">
        <div class="meta-item">
          <i data-lucide="truck"></i>
          <span><strong>Free delivery</strong> on orders over $50</span>
        </div>
        <div class="meta-item">
          <i data-lucide="refresh-ccw"></i>
          <span><strong>30-day returns</strong> — no questions asked</span>
        </div>
        <div class="meta-item">
          <i data-lucide="shield-check"></i>
          <span><strong>1-year warranty</strong> included</span>
        </div>
        <div class="meta-item">
          <i data-lucide="badge-check"></i>
          <span><strong>100% genuine</strong> product</span>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ══ PRODUCT TABS (Description / Specs / Reviews) ═════════════════════ -->
<section class="prod-tabs-section">
  <div class="prod-tabs-inner">

    <div class="prod-tabs">
      <button class="ptab active" data-tab="desc">Description</button>
      <button class="ptab" data-tab="specs">Specifications</button>
      <button class="ptab" data-tab="reviews">Reviews (<?= $productReviews ?>)</button>
    </div>

    <div class="ptab-content active" id="tab-desc">
      <p><?= htmlspecialchars($product['description']) ?></p>
      <ul class="spec-list" style="margin-top:14px">
        <li>A17 Pro chip with 6-core GPU</li>
        <li>6.1-inch Super Retina XDR display with ProMotion</li>
        <li>Pro camera system: 48MP Main, 12MP Ultra Wide, 12MP 3x Telephoto</li>
        <li>Up to 23 hours video playback</li>
        <li>Ceramic Shield front, textured matte glass back, titanium design</li>
        <li>USB 3 speeds with USB-C connector</li>
      </ul>
    </div>

    <div class="ptab-content" id="tab-specs">
      <?php if (!empty($product['specs'])): ?>
        <table class="specs-table">
          <?php foreach ($product['specs'] as $spec): ?>
            <tr>
              <th><?= e($spec['spec_key']) ?></th>
              <td><?= e($spec['spec_value']) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php else: ?>
        <p style="color:var(--gray);font-size:14px;">No specifications available for this product yet.</p>
      <?php endif; ?>
    </div>

    <div class="ptab-content" id="tab-reviews">
      <div class="reviews-summary">
        <div class="rev-score-big">
          <span class="rev-num"><?= $productRating ?: '—' ?></span>
          <div class="rev-stars-big">
            <?php for ($s=1;$s<=5;$s++): ?>
              <i data-lucide="star" class="star-i <?= $productRating && $s <= round($productRating) ? 'on' : '' ?>"></i>
            <?php endfor; ?>
          </div>
          <small><?= $productReviews ?> review<?= $productReviews !== 1 ? 's' : '' ?></small>
        </div>
        <div class="rev-bars">
          <?php for ($s = 5; $s >= 1; $s--): $pct = $reviewBars[$s] ?? 0; ?>
            <div class="rev-bar-row">
              <span><?= $s ?> ★</span>
              <div class="rev-bar-track"><div class="rev-bar-fill" style="width:<?= $pct ?>%"></div></div>
              <span><?= $pct ?>%</span>
            </div>
          <?php endfor; ?>
        </div>
      </div>

      <!-- Review list -->
      <div class="review-list" id="reviewList">
        <?php if (empty($product['reviews'])): ?>
          <p style="color:var(--gray);font-size:14px;" id="noReviewsMsg">No reviews yet. Be the first to review this product!</p>
        <?php else: ?>
          <?php foreach ($product['reviews'] as $r): ?>
            <div class="review-item">
              <div class="rev-top">
                <div class="rev-avatar"><?= strtoupper(substr(e($r['name']), 0, 1)) ?></div>
                <div>
                  <strong><?= e($r['name']) ?></strong>
                  <div class="rev-meta-stars">
                    <?php for ($s=1;$s<=5;$s++): ?>
                      <i data-lucide="star" class="star-i <?= $s <= (int)$r['rating'] ? 'on' : '' ?>"></i>
                    <?php endfor; ?>
                    <small><?= e(date('M Y', strtotime($r['created_at']))) ?></small>
                  </div>
                </div>
              </div>
              <p><?= e($r['body']) ?></p>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Write a review form -->
      <div class="rev-write-wrap">
        <h4 class="rev-write-title">Write a Review</h4>
        <form id="reviewForm" novalidate>
          <input type="hidden" id="reviewProductId" value="<?= $product['id'] ?>"/>
          <div class="rev-form-row">
            <div class="rev-field">
              <label for="revName">Your Name</label>
              <input type="text" id="revName" placeholder="e.g. John M."
                     value="<?= e(authCustomer()['name'] ?? '') ?>"/>
            </div>
            <div class="rev-field">
              <label>Your Rating</label>
              <div class="rev-star-picker" id="starPicker">
                <?php for ($s=1;$s<=5;$s++): ?>
                  <button type="button" class="star-pick-btn" data-star="<?= $s ?>">
                    <i data-lucide="star"></i>
                  </button>
                <?php endfor; ?>
              </div>
              <input type="hidden" id="revRating" value="5"/>
            </div>
          </div>
          <div class="rev-field">
            <label for="revBody">Your Review</label>
            <textarea id="revBody" rows="4" placeholder="Share your experience with this product…"></textarea>
          </div>
          <button type="submit" class="btn-submit-review">
            <i data-lucide="send"></i> Submit Review
          </button>
          <p class="rev-form-msg" id="revFormMsg" style="display:none;"></p>
        </form>
      </div>
    </div>

  </div>
</section>

<!-- ══ RELATED PRODUCTS ═══════════════════════════════════════════════════ -->
<section class="related-section">
  <div class="related-inner">
    <div class="sec-head-related">
      <h2>Related Products</h2>
      <a href="#" class="viewall-link">View all <i data-lucide="arrow-right" class="vai-sm"></i></a>
    </div>
    <div class="related-carousel">
      <button class="rel-btn" id="relPrev"><i data-lucide="chevron-left"></i></button>
      <div class="related-track-wrap">
        <div class="related-track" id="relTrack">
          <?php foreach ($related as $rp):
            $rImg  = $rp['image'] ?: 'https://placehold.co/300x200/f3f4f6/aaa?text=' . urlencode($rp['model']);
            $rColl = $rp['collections'][0] ?? '';
            $rPrice = lowestPrice($rp['storages']);
          ?>
            <a href="product.php?id=<?= $rp['id'] ?>" class="rel-card">
              <div class="rel-img">
                <img src="<?= $rImg ?>" alt="<?= e($rp['model']) ?>"/>
              </div>
              <div class="rel-info">
                <?php if ($rColl): ?>
                  <span class="rel-coll"><?= e($rColl) ?></span>
                <?php endif; ?>
                <p class="rel-name"><?= e($rp['model']) ?></p>
                <p class="rel-price"><?= $rPrice ? formatPrice($rPrice) : '—' ?></p>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <button class="rel-btn" id="relNext"><i data-lucide="chevron-right"></i></button>
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

<!-- ══ FOOTER ════════════════════════════════════════════════════════════ -->
<?php require_once __DIR__ . '/config/footer.php'; ?>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script src="cart.js"></script>
<script src="product.js"></script>
<script>
/* ── Track recently viewed product ── */
(function () {
  const pid = parseInt('<?= (int)$product['id'] ?>');
  const model = <?= json_encode($product['model']) ?>;
  const img = document.getElementById('mainImg')?.src || null;
  const price = parseFloat('<?= (float)($lowestSale ?: $lowestP) ?>') || 0;
  if (pid) trackRecent(pid, model, img, price);
})();

/* ── Review star picker ── */
(function () {
  const picker   = document.getElementById('starPicker');
  const ratingIn = document.getElementById('revRating');
  if (!picker) return;

  let selected = 5;

  function setStars(n) {
    picker.querySelectorAll('.star-pick-btn').forEach((btn, i) => {
      btn.classList.toggle('active', i < n);
    });
    selected = n;
    ratingIn.value = n;
  }

  setStars(5); // default

  picker.querySelectorAll('.star-pick-btn').forEach((btn, i) => {
    btn.addEventListener('click',      () => setStars(i + 1));
    btn.addEventListener('mouseenter', () => {
      picker.querySelectorAll('.star-pick-btn').forEach((b, j) => b.classList.toggle('active', j <= i));
    });
  });
  picker.addEventListener('mouseleave', () => setStars(selected));
})();

/* ── Review form submit ── */
document.getElementById('reviewForm')?.addEventListener('submit', async function (e) {
  e.preventDefault();
  const name   = document.getElementById('revName')?.value.trim();
  const rating = parseInt(document.getElementById('revRating')?.value, 10);
  const body   = document.getElementById('revBody')?.value.trim();
  const pid    = parseInt(document.getElementById('reviewProductId')?.value, 10);
  const msgEl  = document.getElementById('revFormMsg');
  const btn    = this.querySelector('.btn-submit-review');

  const show = (msg, type) => {
    msgEl.textContent = msg;
    msgEl.className   = 'rev-form-msg ' + type;
    msgEl.style.display = 'block';
  };

  if (!name)  { show('Please enter your name.', 'error');   return; }
  if (!body)  { show('Please write your review.', 'error'); return; }

  btn.disabled = true; btn.textContent = 'Submitting…';

  try {
    const res  = await fetch('api/submit-review.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ product_id: pid, name, rating, body }),
    });
    const data = await res.json();

    if (data.success) {
      // Append new review to the list without page reload
      const noMsg = document.getElementById('noReviewsMsg');
      if (noMsg) noMsg.remove();

      const list = document.getElementById('reviewList');
      const div  = document.createElement('div');
      div.className = 'review-item';
      div.innerHTML = `
        <div class="rev-top">
          <div class="rev-avatar">${data.review.name.charAt(0).toUpperCase()}</div>
          <div>
            <strong>${data.review.name}</strong>
            <div class="rev-meta-stars">
              ${[1,2,3,4,5].map(s => `<i data-lucide="star" class="star-i ${s <= data.review.rating ? 'on' : ''}"></i>`).join('')}
              <small>${data.review.created_at}</small>
            </div>
          </div>
        </div>
        <p>${data.review.body.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</p>`;
      list.insertAdjacentElement('afterbegin', div);
      if (window.lucide) lucide.createIcons();

      show('Thank you for your review!', 'success');
      document.getElementById('revName').value  = '';
      document.getElementById('revBody').value  = '';
    } else {
      show(data.error || 'Could not submit review.', 'error');
    }
  } catch {
    show('Network error. Please try again.', 'error');
  } finally {
    btn.disabled = false; btn.textContent = '';
    btn.innerHTML = '<i data-lucide="send"></i> Submit Review';
    if (window.lucide) lucide.createIcons();
  }
});
</script>
</body>
</html>







