<?php
// Order summary is read from localStorage via checkout.js
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

$customer = authCustomerFull($pdo); // null if not logged in
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Checkout — Gadget Genesis</title>
  <link rel="stylesheet" href="style.css"/>
  <?php require_once __DIR__ . '/config/theme-init.php'; ?></head>
<body>

<!-- ══ TOP BAR ══════════════════════════════════════════════════════════ -->
<div class="topbar">
  <div class="topbar-left">
    <span class="topbar-item"><i data-lucide="shield-check" class="ti"></i><span>Secure Checkout</span><small>SSL encrypted</small></span>
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
    <a href="cart.php">Cart</a>
    <i data-lucide="chevron-right" class="bc-sep"></i>
    <span>Checkout</span>
  </div>
</div>

<!-- ══ CHECKOUT STEPS ════════════════════════════════════════════════════ -->
<div class="co-steps-bar">
  <div class="co-steps">
    <div class="co-step done">
      <span class="co-step-num"><i data-lucide="check"></i></span>
      <span class="co-step-label">Cart</span>
    </div>
    <div class="co-step-line done"></div>
    <div class="co-step active">
      <span class="co-step-num">2</span>
      <span class="co-step-label">Details</span>
    </div>
    <div class="co-step-line"></div>
    <div class="co-step">
      <span class="co-step-num">3</span>
      <span class="co-step-label">Payment</span>
    </div>
    <div class="co-step-line"></div>
    <div class="co-step">
      <span class="co-step-num">4</span>
      <span class="co-step-label">Confirm</span>
    </div>
  </div>
</div>

<!-- ══ CHECKOUT LAYOUT ═══════════════════════════════════════════════════ -->
<div class="co-wrap">
  <div class="co-inner">

    <!-- ── LEFT: Form ── -->
    <div class="co-form-col">

      <form id="checkoutForm" novalidate>

        <!-- Contact -->
        <div class="co-card">
          <div class="co-card-head">
            <i data-lucide="user"></i>
            <h2>Contact Information</h2>
          </div>
          <div class="co-fields">
            <div class="co-field">
              <label for="firstName">First Name <span class="req">*</span></label>
              <input type="text" id="firstName" name="firstName" placeholder="John" required
                <?php if ($customer): ?>
                  value="<?= e(explode(' ', $customer['name'])[0]) ?>"
                <?php endif; ?>/>
            </div>
            <div class="co-field">
              <label for="lastName">Last Name <span class="req">*</span></label>
              <input type="text" id="lastName" name="lastName" placeholder="Smith" required
                <?php if ($customer): ?>
                  value="<?= e(implode(' ', array_slice(explode(' ', $customer['name']), 1)) ?: '') ?>"
                <?php endif; ?>/>
            </div>
            <div class="co-field co-full">
              <label for="email">Email Address <span class="req">*</span></label>
              <input type="email" id="email" name="email" placeholder="john@example.com" required
                <?php if ($customer): ?>
                  value="<?= e($customer['email'] ?? '') ?>"
                <?php endif; ?>/>
            </div>
            <div class="co-field co-full">
              <label for="phone">Phone Number <span class="req">*</span></label>
              <input type="tel" id="phone" name="phone" placeholder="e.g. 0759 123 456" required
                <?php if ($customer): ?>
                  value="<?= e($customer['phone']) ?>"
                <?php endif; ?>/>
            </div>
          </div>
        </div>

        <!-- Shipping -->
        <div class="co-card">
          <div class="co-card-head">
            <i data-lucide="map-pin"></i>
            <h2>Shipping Address</h2>
          </div>
          <div class="co-fields">
            <div class="co-field co-full">
              <label for="address">Street Address <span class="req">*</span></label>
              <input type="text" id="address" name="address" placeholder="123 Kampala Road" required
                <?php if ($customer): ?>
                  value="<?= e($customer['address'] ?? '') ?>"
                <?php endif; ?>/>
            </div>
            <div class="co-field">
              <label for="city">City <span class="req">*</span></label>
              <input type="text" id="city" name="city" placeholder="Kampala" required
                <?php if ($customer): ?>
                  value="<?= e($customer['city'] ?? '') ?>"
                <?php endif; ?>/>
            </div>
            <div class="co-field">
              <label for="district">District</label>
              <input type="text" id="district" name="district" placeholder="Central"
                <?php if ($customer): ?>
                  value="<?= e($customer['district'] ?? '') ?>"
                <?php endif; ?>/>
            </div>
            <div class="co-field co-full">
              <label for="country">Country <span class="req">*</span></label>
              <select id="country" name="country" required>
                <option value="UG" selected>Uganda</option>
                <option value="KE">Kenya</option>
                <option value="TZ">Tanzania</option>
                <option value="RW">Rwanda</option>
                <option value="BI">Burundi</option>
                <option value="SS">South Sudan</option>
              </select>
            </div>
            <div class="co-field co-full">
              <label for="notes">Order Notes (optional)</label>
              <textarea id="notes" name="notes" placeholder="Any special instructions for delivery…" rows="3"></textarea>
            </div>
          </div>
        </div>

        <!-- Shipping method -->
        <div class="co-card">
          <div class="co-card-head">
            <i data-lucide="truck"></i>
            <h2>Shipping Method</h2>
          </div>
          <div class="ship-options">
            <label class="ship-option active" id="shipStandard">
              <input type="radio" name="shipping" value="standard" checked/>
              <div class="ship-opt-body">
                <div class="ship-opt-left">
                  <strong>Standard Delivery</strong>
                  <small>3–5 business days</small>
                </div>
                <span class="ship-price free">Free</span>
              </div>
            </label>
            <label class="ship-option" id="shipExpress">
              <input type="radio" name="shipping" value="express"/>
              <div class="ship-opt-body">
                <div class="ship-opt-left">
                  <strong>Express Delivery</strong>
                  <small>1–2 business days</small>
                </div>
                <span class="ship-price">UGX 15,000</span>
              </div>
            </label>
            <label class="ship-option" id="shipSameDay">
              <input type="radio" name="shipping" value="sameday"/>
              <div class="ship-opt-body">
                <div class="ship-opt-left">
                  <strong>Same Day Delivery</strong>
                  <small>Kampala only — order before 12pm</small>
                </div>
                <span class="ship-price">UGX 25,000</span>
              </div>
            </label>
          </div>
        </div>

        <!-- Payment -->
        <div class="co-card">
          <div class="co-card-head">
            <i data-lucide="credit-card"></i>
            <h2>Payment Method</h2>
          </div>
          <div class="pay-options">
            <label class="pay-option active" id="payMomo">
              <input type="radio" name="payment" value="momo" checked/>
              <div class="pay-opt-body">
                <i data-lucide="smartphone"></i>
                <div>
                  <strong>MTN Mobile Money</strong>
                  <small>Pay with MTN MoMo</small>
                </div>
              </div>
            </label>
            <label class="pay-option" id="payAirtel">
              <input type="radio" name="payment" value="airtel"/>
              <div class="pay-opt-body">
                <i data-lucide="smartphone"></i>
                <div>
                  <strong>Airtel Money</strong>
                  <small>Pay with Airtel Money</small>
                </div>
              </div>
            </label>
            <label class="pay-option" id="payCard">
              <input type="radio" name="payment" value="card"/>
              <div class="pay-opt-body">
                <i data-lucide="credit-card"></i>
                <div>
                  <strong>Credit / Debit Card</strong>
                  <small>Visa, Mastercard</small>
                </div>
              </div>
            </label>
            <label class="pay-option" id="payCOD">
              <input type="radio" name="payment" value="cod"/>
              <div class="pay-opt-body">
                <i data-lucide="banknote"></i>
                <div>
                  <strong>Cash on Delivery</strong>
                  <small>Pay when your order arrives</small>
                </div>
              </div>
            </label>
          </div>

          <!-- MoMo number field (shown when MoMo selected) -->
          <div class="pay-extra" id="momoField">
            <label for="momoNumber">MTN MoMo Number <span class="req">*</span></label>
            <input type="tel" id="momoNumber" name="momoNumber" placeholder="e.g. 0771 234 567"/>
          </div>

          <!-- Airtel number field -->
          <div class="pay-extra" id="airtelField" style="display:none">
            <label for="airtelNumber">Airtel Money Number <span class="req">*</span></label>
            <input type="tel" id="airtelNumber" name="airtelNumber" placeholder="e.g. 0751 234 567"/>
          </div>

          <!-- Card fields -->
          <div class="pay-extra" id="cardFields" style="display:none">
            <div class="co-fields">
              <div class="co-field co-full">
                <label for="cardNumber">Card Number <span class="req">*</span></label>
                <input type="text" id="cardNumber" name="cardNumber" placeholder="1234 5678 9012 3456" maxlength="19"/>
              </div>
              <div class="co-field">
                <label for="cardExpiry">Expiry <span class="req">*</span></label>
                <input type="text" id="cardExpiry" name="cardExpiry" placeholder="MM/YY" maxlength="5"/>
              </div>
              <div class="co-field">
                <label for="cardCVV">CVV <span class="req">*</span></label>
                <input type="text" id="cardCVV" name="cardCVV" placeholder="123" maxlength="4"/>
              </div>
            </div>
          </div>
        </div>

        <button type="submit" class="btn-place-order" id="placeOrderBtn">
          <i data-lucide="lock"></i>
          Place Order Securely
        </button>

        <?php if ($customer): ?>
        <input type="hidden" id="customerId" value="<?= (int)$customer['id'] ?>"/>
        <?php endif; ?>

        <p class="co-secure-note">
          <i data-lucide="shield-check"></i>
          Your payment information is encrypted and secure.
        </p>

      </form>
    </div>

    <!-- ── RIGHT: Order summary ── -->
    <aside class="co-summary">

      <h2>Order Summary</h2>

      <div class="co-summary-items" id="coSummaryItems">
        <!-- Populated by cart.js -->
      </div>

      <div class="co-summary-lines">
        <div class="co-sum-line">
          <span>Subtotal</span>
          <span id="coSubtotal">UGX 0</span>
        </div>
        <div class="co-sum-line">
          <span>Shipping</span>
          <span id="coShipping" class="free-ship">Free</span>
        </div>
        <div class="co-sum-line" id="coDiscountLine" style="display:none">
          <span>Discount</span>
          <span id="coDiscount" class="discount-val">-UGX 0</span>
        </div>
      </div>

      <div class="co-summary-total">
        <span>Total</span>
        <span id="coTotal">UGX 0</span>
      </div>

      <div class="co-trust">
        <div class="co-trust-item"><i data-lucide="shield-check"></i><span>Secure Payment</span></div>
        <div class="co-trust-item"><i data-lucide="refresh-ccw"></i><span>Easy Returns</span></div>
        <div class="co-trust-item"><i data-lucide="truck"></i><span>Fast Delivery</span></div>
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







