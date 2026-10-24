<?php
/* ═══════════════════════════════════════════════════════════════════════
   admin/sidebar.php — Shared admin sidebar
   $activePage should be set before including this file:
   e.g. $activePage = 'orders';
   Possible values: dashboard, orders, products, add-product, coupons,
                    customers, change-password
   ═══════════════════════════════════════════════════════════════════════ */
$activePage = $activePage ?? '';
function sideNav(string $page, string $active): string {
    return $page === $active ? 'class="active"' : '';
}
?>
<aside class="adm-sidebar">
  <div class="adm-logo">
    <img src="<?= str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 2) ?>logo.jpg" alt="Gadget Genesis"/>
    <span>Admin Panel</span>
  </div>
  <nav class="adm-nav">
    <div class="nav-section">Overview</div>
    <a href="index.php" <?= sideNav('dashboard', $activePage) ?>>
      <i data-lucide="layout-dashboard"></i> Dashboard
    </a>
    <div class="nav-section">Store</div>
    <a href="orders.php" <?= sideNav('orders', $activePage) ?>>
      <i data-lucide="package"></i> Orders
    </a>
    <a href="products.php" <?= sideNav('products', $activePage) ?>>
      <i data-lucide="box"></i> Products
    </a>
    <a href="add-product.php" <?= sideNav('add-product', $activePage) ?>>
      <i data-lucide="plus-circle"></i> Add Product
    </a>
    <a href="coupons.php" <?= sideNav('coupons', $activePage) ?>>
      <i data-lucide="tag"></i> Coupons
    </a>
    <a href="customers.php" <?= sideNav('customers', $activePage) ?>>
      <i data-lucide="users"></i> Customers
    </a>
    <a href="reviews.php" <?= sideNav('reviews', $activePage) ?>>
      <i data-lucide="star"></i> Reviews
    </a>
    <div class="nav-section">Account</div>
    <a href="change-password.php" <?= sideNav('change-password', $activePage) ?>>
      <i data-lucide="key-round"></i> Change Password
    </a>
  </nav>
  <div class="adm-footer">
    <span style="font-size:12px;color:rgba(255,255,255,.4);display:flex;align-items:center;gap:6px;">
      <i data-lucide="user" style="width:12px;height:12px;"></i>
      <?= htmlspecialchars(adminUsername(), ENT_QUOTES, 'UTF-8') ?>
    </span>
    <a href="logout.php"><i data-lucide="log-out"></i> Sign Out</a>
  </div>
</aside>
