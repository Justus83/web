<?php
// Determine active page for nav highlighting
$currentPage = basename($_SERVER['PHP_SELF']);
$currentCol  = $_GET['collection'] ?? '';

// Auth state (auth.php may already be loaded; only start session if not)
if (session_status() === PHP_SESSION_NONE) session_start();
$_navCustomerName = $_SESSION['customer_name'] ?? null;

function isActive(string $page, string $current): string {
    return $current === $page ? 'active' : '';
}
?>
<nav class="nav">

  <!-- Home -->
  <a href="index.php" class="nl <?= isActive('index.php', $currentPage) ?>">Home</a>

  <!-- Shop dropdown -->
  <div class="nav-item">
    <a href="shop.php" class="nl <?= $currentPage === 'shop.php' ? 'active' : '' ?>">
      Shop <i data-lucide="chevron-down" class="nc"></i>
    </a>
    <div class="dropdown">
      <a href="shop.php">
        <span class="dd-icon"><i data-lucide="layout-grid"></i></span>
        All Products
      </a>
      <a href="shop.php?collection=Trending">
        <span class="dd-icon"><i data-lucide="trending-up"></i></span>
        Trending Now
      </a>
      <a href="shop.php?collection=New+Arrivals">
        <span class="dd-icon"><i data-lucide="sparkles"></i></span>
        New Arrivals
      </a>
      <a href="shop.php?collection=Premium+Tech">
        <span class="dd-icon"><i data-lucide="star"></i></span>
        Premium Tech
      </a>
      <div class="dropdown-divider"></div>
      <a href="shop.php?collection=Deals" class="dd-footer">
        <i data-lucide="tag"></i> View All Deals
      </a>
    </div>
  </div>

  <!-- Categories dropdown -->
  <div class="nav-item">
    <a href="shop.php" class="nl">
      Categories <i data-lucide="chevron-down" class="nc"></i>
    </a>
    <div class="dropdown">
      <a href="shop.php?collection=Smartphones">
        <span class="dd-icon"><i data-lucide="smartphone"></i></span>
        Smartphones
      </a>
      <a href="shop.php?collection=Audio">
        <span class="dd-icon"><i data-lucide="headphones"></i></span>
        Audio
      </a>
      <a href="shop.php?collection=Smartwatches">
        <span class="dd-icon"><i data-lucide="watch"></i></span>
        Smartwatches
      </a>
      <a href="shop.php?collection=Laptops">
        <span class="dd-icon"><i data-lucide="laptop"></i></span>
        Laptops
      </a>
      <a href="shop.php?collection=Accessories">
        <span class="dd-icon"><i data-lucide="plug"></i></span>
        Accessories
      </a>
    </div>
  </div>

  <!-- Deals -->
  <a href="shop.php?collection=Deals" class="nl <?= $currentCol === 'Deals' ? 'active' : '' ?>">Deals</a>

  <!-- Orders -->
  <a href="orders.php" class="nl <?= isActive('orders.php', $currentPage) ?>">My Orders</a>

  <!-- Account (logged-in state) -->
  <?php if ($_navCustomerName): ?>
  <div class="nav-item">
    <a href="account.php" class="nl <?= isActive('account.php', $currentPage) ?>">
      <i data-lucide="user" style="width:14px;height:14px;vertical-align:-2px;"></i>
      <?= htmlspecialchars(explode(' ', $_navCustomerName)[0], ENT_QUOTES, 'UTF-8') ?>
      <i data-lucide="chevron-down" class="nc"></i>
    </a>
    <div class="dropdown">
      <a href="account.php">
        <span class="dd-icon"><i data-lucide="user"></i></span>
        My Account
      </a>
      <a href="orders.php">
        <span class="dd-icon"><i data-lucide="package"></i></span>
        My Orders
      </a>
      <div class="dropdown-divider"></div>
      <a href="api/logout.php" class="dd-footer" style="color:#dc2626;">
        <i data-lucide="log-out"></i> Sign Out
      </a>
    </div>
  </div>
  <?php endif; ?>

</nav>
