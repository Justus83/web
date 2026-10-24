<?php
// Cart items are stored in localStorage on the client.
// This PHP file renders the shell; cart.js populates it dynamically.
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Your Cart — Gadget Genesis</title>
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
      <a href="cart.php" class="ibtn cart active">
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
    <span>Cart</span>
  </div>
</div>

<!-- ══ CART LAYOUT ═══════════════════════════════════════════════════════ -->
<div class="cart-wrap">
  <div class="cart-inner">

    <!-- ── LEFT: Items ── -->
    <div class="cart-left">

      <div class="cart-header">
        <h1>Shopping Cart</h1>
        <span class="cart-item-count" id="cartItemCount">0 items</span>
      </div>

      <!-- Empty state -->
      <div class="cart-empty" id="cartEmpty">
        <i data-lucide="shopping-cart" class="cart-empty-ico"></i>
        <h3>Your cart is empty</h3>
        <p>Looks like you haven't added anything yet.</p>
        <a href="shop.php" class="btn-primary">Start Shopping</a>
      </div>

      <!-- Cart items list -->
      <div class="cart-items" id="cartItems" style="display:none">

        <!-- Column headers -->
        <div class="cart-col-headers">
          <span>Product</span>
          <span>Price</span>
          <span>Quantity</span>
          <span>Total</span>
          <span></span>
        </div>

        <!-- Items injected by cart.js -->
        <div id="cartItemsList"></div>

        <!-- Cart actions -->
        <div class="cart-actions">
          <a href="shop.php" class="btn-continue">
            <i data-lucide="arrow-left"></i> Continue Shopping
          </a>
          <button class="btn-clear-cart" id="clearCartBtn">
            <i data-lucide="trash-2"></i> Clear Cart
          </button>
        </div>

      </div>

      <!-- You may also like -->
      <div class="cart-suggestions" id="cartSuggestions" style="display:none">
        <h3>You may also like</h3>
        <div class="suggestions-row" id="suggestionsList">
          <!-- Populated by cart.js from DB -->
        </div>
      </div>

    </div>

    <!-- ── RIGHT: Summary ── -->
    <aside class="cart-summary" id="cartSummary" style="display:none">

      <h2>Order Summary</h2>

      <div class="summary-lines">
        <div class="summary-line">
          <span>Subtotal (<span id="summaryCount">0</span> items)</span>
          <span id="summarySubtotal">UGX 0</span>
        </div>
        <div class="summary-line">
          <span>Shipping</span>
          <span id="summaryShipping" class="free-ship">Free</span>
        </div>
        <div class="summary-line" id="discountLine" style="display:none">
          <span>Discount</span>
          <span id="summaryDiscount" class="discount-val">-UGX 0</span>
        </div>
      </div>

      <!-- Coupon -->
      <div class="coupon-wrap">
        <label class="coupon-label">Have a coupon?</label>
        <div class="coupon-row">
          <input type="text" id="couponInput" placeholder="Enter coupon code" class="coupon-input"/>
          <button class="btn-apply-coupon" id="applyCouponBtn">Apply</button>
        </div>
        <p class="coupon-msg" id="couponMsg"></p>
      </div>

      <div class="summary-total-line">
        <span>Total</span>
        <span id="summaryTotal">UGX 0</span>
      </div>

      <div class="summary-note">
        <i data-lucide="shield-check"></i>
        Secure checkout — your information is protected
      </div>

      <a href="checkout.php" class="btn-checkout" id="checkoutBtn">
        Proceed to Checkout <i data-lucide="arrow-right"></i>
      </a>

      <div class="pay-methods">
        <span>We accept</span>
        <div class="pay-icons">
          <span class="pay-chip">Visa</span>
          <span class="pay-chip">Mastercard</span>
          <span class="pay-chip">MTN MoMo</span>
          <span class="pay-chip">Airtel Money</span>
        </div>
      </div>

    </aside>

  </div>
</div>

<?php require_once __DIR__ . '/config/footer.php'; ?>

<div class="toast" id="toast"></div>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script src="cart.js"></script>
</body>
</html>







