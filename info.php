<?php
/* ═══════════════════════════════════════════════════════════════════════
   info.php — Static info pages: return-policy, warranty, faq
   ═══════════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/config/helpers.php';

$pages = [
    'return-policy' => [
        'title' => 'Return Policy',
        'icon'  => 'refresh-ccw',
    ],
    'warranty' => [
        'title' => 'Warranty',
        'icon'  => 'shield-check',
    ],
    'faq' => [
        'title' => 'Frequently Asked Questions',
        'icon'  => 'circle-help',
    ],
];

$slug = trim($_GET['page'] ?? 'faq');
if (!array_key_exists($slug, $pages)) $slug = 'faq';
$page = $pages[$slug];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title><?= e($page['title']) ?> — Gadget Genesis</title>
  <link rel="stylesheet" href="style.css"/>
  <?php require_once __DIR__ . '/config/theme-init.php'; ?>
  <style>
    .info-wrap   { max-width: 820px; margin: 0 auto; padding: 48px 24px 64px; }
    .info-hero   { display:flex;align-items:center;gap:16px;margin-bottom:36px; }
    .info-hero-ico {
      width:52px;height:52px;background:var(--navy);border-radius:14px;
      display:flex;align-items:center;justify-content:center;flex-shrink:0;
    }
    .info-hero-ico [data-lucide] { width:26px;height:26px;color:#fff; }
    .info-hero h1 { font-size:28px;font-weight:800;color:var(--navy);margin:0; }
    .info-section { margin-bottom:36px; }
    .info-section h2 {
      font-size:18px;font-weight:800;color:var(--navy);
      margin-bottom:12px;padding-bottom:8px;
      border-bottom:2px solid #e8ebf4;
    }
    .info-section p  { font-size:15px;color:#374151;line-height:1.7;margin-bottom:10px; }
    .info-section ul { padding-left:20px;margin-bottom:10px; }
    .info-section ul li { font-size:15px;color:#374151;line-height:1.7;margin-bottom:6px; }
    .info-section strong { color:var(--navy); }
    .info-tabs  { display:flex;gap:8px;margin-bottom:40px;flex-wrap:wrap; }
    .info-tab {
      padding:9px 20px;border-radius:9px;font-size:14px;font-weight:700;
      text-decoration:none;border:1.5px solid var(--border);color:var(--navy);
      transition:background .15s,color .15s;
    }
    .info-tab:hover,.info-tab.active {
      background:var(--navy);color:#fff;border-color:var(--navy);
    }
    .faq-item { border:1px solid var(--border);border-radius:12px;margin-bottom:12px;overflow:hidden; }
    .faq-q {
      width:100%;text-align:left;padding:16px 20px;background:#fff;
      font-size:15px;font-weight:700;color:var(--navy);
      border:none;cursor:pointer;display:flex;justify-content:space-between;align-items:center;
      font-family:inherit;
    }
    .faq-q:hover { background:#f8faff; }
    .faq-q [data-lucide] { width:18px;height:18px;flex-shrink:0;transition:transform .2s; }
    .faq-q.open [data-lucide] { transform:rotate(180deg); }
    .faq-a { display:none;padding:0 20px 16px;font-size:14px;color:#374151;line-height:1.7; }
    .faq-a.open { display:block; }
  </style>
</head>
<body>

<!-- ══ TOP BAR ══════════════════════════════════════════════════════════ -->
<div class="topbar">
  <div class="topbar-left">
    <span class="topbar-item"><i data-lucide="shield-check" class="ti"></i><span>Trusted Store</span><small>Since 2020</small></span>
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

<!-- ══ BREADCRUMB ════════════════════════════════════════════════════════ -->
<div class="breadcrumb-bar">
  <div class="bc-inner">
    <a href="index.php">Home</a>
    <i data-lucide="chevron-right" class="bc-sep"></i>
    <span><?= e($page['title']) ?></span>
  </div>
</div>

<div class="info-wrap">

  <!-- Page tabs -->
  <div class="info-tabs">
    <?php foreach ($pages as $s => $p): ?>
      <a href="info.php?page=<?= $s ?>"
         class="info-tab <?= $s === $slug ? 'active' : '' ?>">
        <?= e($p['title']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Hero -->
  <div class="info-hero">
    <div class="info-hero-ico"><i data-lucide="<?= e($page['icon']) ?>"></i></div>
    <h1><?= e($page['title']) ?></h1>
  </div>

  <!-- ══ RETURN POLICY ══════════════════════════════════════════════════ -->
  <?php if ($slug === 'return-policy'): ?>

    <div class="info-section">
      <h2>30-Day Return Guarantee</h2>
      <p>We want you to be completely satisfied with your purchase. If for any reason you're not happy, you may return most items within <strong>30 days</strong> of delivery for a full refund or exchange.</p>
    </div>

    <div class="info-section">
      <h2>Eligibility</h2>
      <ul>
        <li>Item must be returned within 30 days of the delivery date.</li>
        <li>Item must be in its original, unused condition with all accessories and original packaging.</li>
        <li>A valid proof of purchase (order number or receipt) is required.</li>
        <li>Items that have been physically damaged, water-damaged, or tampered with are not eligible.</li>
      </ul>
    </div>

    <div class="info-section">
      <h2>Non-Returnable Items</h2>
      <ul>
        <li>Items with broken seals or missing original packaging.</li>
        <li>Software, digital products, or SIM cards once activated.</li>
        <li>Items explicitly marked as final sale or clearance.</li>
      </ul>
    </div>

    <div class="info-section">
      <h2>How to Return</h2>
      <p>Contact us via WhatsApp on <strong><a href="https://wa.me/256759809061">0759 809061</a></strong> or visit our store at <strong>Kooki Towers, Buganda Rd, Kampala</strong>. Bring the item and your order number. Returns are typically processed within 2–3 business days.</p>
    </div>

    <div class="info-section">
      <h2>Refund Method</h2>
      <p>Refunds are issued via the original payment method (Mobile Money or cash). Credit card refunds may take 5–7 business days depending on your bank.</p>
    </div>

  <!-- ══ WARRANTY ═══════════════════════════════════════════════════════ -->
  <?php elseif ($slug === 'warranty'): ?>

    <div class="info-section">
      <h2>1-Year Manufacturer Warranty</h2>
      <p>All products sold at Gadget Genesis come with a <strong>minimum 1-year manufacturer warranty</strong> unless otherwise stated on the product page. This covers manufacturing defects and hardware failures under normal use.</p>
    </div>

    <div class="info-section">
      <h2>What's Covered</h2>
      <ul>
        <li>Manufacturing defects present at time of purchase.</li>
        <li>Hardware component failures under normal use (e.g. battery, screen, charging port defects).</li>
        <li>Dead pixels or display issues not caused by physical damage.</li>
      </ul>
    </div>

    <div class="info-section">
      <h2>What's Not Covered</h2>
      <ul>
        <li>Physical damage (cracked screens, dents, drops).</li>
        <li>Water or liquid damage.</li>
        <li>Damage caused by unauthorized repairs or modifications.</li>
        <li>Normal wear and tear (battery degradation over time, cosmetic scratches).</li>
        <li>Damage from using non-approved accessories or chargers.</li>
      </ul>
    </div>

    <div class="info-section">
      <h2>How to Claim Warranty</h2>
      <p>Bring your device and proof of purchase to our store at <strong>Kooki Towers, Buganda Rd, Kampala</strong>, or contact us on <strong><a href="https://wa.me/256759809061">WhatsApp</a></strong>. Our team will assess the device and guide you through the repair or replacement process.</p>
      <p>Warranty claims are typically assessed within <strong>3–5 business days</strong>. Complex repairs may take longer and we will keep you informed throughout.</p>
    </div>

  <!-- ══ FAQ ════════════════════════════════════════════════════════════ -->
  <?php else: ?>

    <?php
    $faqs = [
      ['q' => 'Are all products genuine?',
       'a' => 'Yes. Every product we sell is 100% genuine and sourced directly from authorized distributors and manufacturers. We do not sell replicas or refurbished items unless explicitly stated.'],
      ['q' => 'Do you offer delivery?',
       'a' => 'Yes! We offer Standard delivery (3–5 business days), Express delivery (1–2 business days), and Same-Day delivery within Kampala. Delivery is free on orders over UGX 200,000.'],
      ['q' => 'How do I track my order?',
       'a' => 'Visit the Orders page and enter your phone number to look up all orders placed with that number. You can also view your order status in your account dashboard if you are logged in.'],
      ['q' => 'What payment methods do you accept?',
       'a' => 'We accept MTN Mobile Money, Airtel Money, Visa/Mastercard, and Cash on Delivery. All online payments are processed securely.'],
      ['q' => 'Can I cancel my order?',
       'a' => 'You can cancel an order that is still in "Pending" status. Contact us immediately via WhatsApp on 0759 809061 and we will process the cancellation. Orders that are already shipped cannot be cancelled but can be returned under our return policy.'],
      ['q' => 'Do you offer installment payments?',
       'a' => 'We currently do not offer in-house installment plans. However, some mobile money services and bank cards offer pay-later options. Contact us for more information.'],
      ['q' => 'How do I create an account?',
       'a' => 'Click "Account" in the top navigation and select the Register tab. You need a valid phone number, name, and password. Registration is free and gives you access to order history and saved addresses.'],
      ['q' => 'What if I receive a damaged item?',
       'a' => 'If your item arrives damaged, contact us within 48 hours of delivery via WhatsApp with photos of the damage. We will arrange a replacement or refund as quickly as possible.'],
      ['q' => 'Where is your store located?',
       'a' => 'We are located at Kooki Towers, Buganda Road, Kampala (opposite CPS). We are open Monday to Saturday, 9am to 6pm.'],
      ['q' => 'Do you offer bulk or corporate pricing?',
       'a' => 'Yes! We offer competitive pricing for bulk orders and corporate clients. Contact us via WhatsApp or call 0759 809061 to discuss your requirements.'],
    ];
    ?>

    <div id="faqList">
      <?php foreach ($faqs as $i => $faq): ?>
        <div class="faq-item">
          <button class="faq-q" data-idx="<?= $i ?>" aria-expanded="false">
            <?= e($faq['q']) ?>
            <i data-lucide="chevron-down"></i>
          </button>
          <div class="faq-a" id="faq-a-<?= $i ?>">
            <?= e($faq['a']) ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/config/footer.php'; ?>

<div class="toast" id="toast"></div>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script src="cart.js"></script>
<?php if ($slug === 'faq'): ?>
<script>
document.querySelectorAll('.faq-q').forEach(btn => {
  btn.addEventListener('click', () => {
    const idx    = btn.dataset.idx;
    const answer = document.getElementById('faq-a-' + idx);
    const isOpen = btn.classList.contains('open');
    // Close all
    document.querySelectorAll('.faq-q').forEach(b => b.classList.remove('open'));
    document.querySelectorAll('.faq-a').forEach(a => a.classList.remove('open'));
    // Toggle clicked
    if (!isOpen) {
      btn.classList.add('open');
      answer.classList.add('open');
      btn.setAttribute('aria-expanded', 'true');
    } else {
      btn.setAttribute('aria-expanded', 'false');
    }
    if (window.lucide) lucide.createIcons();
  });
});
</script>
<?php endif; ?>
</body>
</html>
