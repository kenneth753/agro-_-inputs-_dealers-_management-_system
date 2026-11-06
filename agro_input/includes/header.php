<?php
$pageTitle = $pageTitle ?? 'Agro Input Dealers';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | Agro Input Dealers</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<!-- ══ ADMIN BAR (only for admin users) ══════════════════════════════ -->
<?php if (current_user() && current_user()['role'] === 'admin'): ?>
<div style="background:#0d2a4a; color:#fff; padding:0; z-index:300; position:relative;">
  <div style="width:min(1200px,96%); margin:0 auto; display:flex; align-items:center; justify-content:space-between; min-height:38px; flex-wrap:wrap; gap:4px;">

    <!-- Left: Admin quick links -->
    <div style="display:flex; align-items:center; gap:2px; flex-wrap:wrap;">
      <span style="color:rgba(255,255,255,.4); font-size:.72rem; padding:0 8px; font-weight:600; text-transform:uppercase; letter-spacing:.06em;">Admin:</span>
      <a href="admin.php"            style="color:#42a5f5; padding:6px 10px; font-size:.78rem; font-weight:700; text-decoration:none; border-radius:4px; transition:background .15s;" onmouseover="this.style.background='rgba(255,255,255,.1)'" onmouseout="this.style.background='transparent'">⚙️ Dashboard</a>
      <a href="admin.php?tab=orders" style="color:rgba(255,255,255,.8); padding:6px 10px; font-size:.78rem; text-decoration:none; border-radius:4px; transition:background .15s;" onmouseover="this.style.background='rgba(255,255,255,.1)'" onmouseout="this.style.background='transparent'">🧾 Orders</a>
      <a href="admin.php?tab=products" style="color:rgba(255,255,255,.8); padding:6px 10px; font-size:.78rem; text-decoration:none; border-radius:4px; transition:background .15s;" onmouseover="this.style.background='rgba(255,255,255,.1)'" onmouseout="this.style.background='transparent'">📦 Products</a>
      <a href="admin.php?tab=customers" style="color:rgba(255,255,255,.8); padding:6px 10px; font-size:.78rem; text-decoration:none; border-radius:4px; transition:background .15s;" onmouseover="this.style.background='rgba(255,255,255,.1)'" onmouseout="this.style.background='transparent'">👥 Customers</a>
      <a href="reports.php"          style="color:#ffd54f; padding:6px 10px; font-size:.78rem; font-weight:700; text-decoration:none; border-radius:4px; background:rgba(255,213,79,.1); transition:background .15s;" onmouseover="this.style.background='rgba(255,213,79,.2)'" onmouseout="this.style.background='rgba(255,213,79,.1)'">📈 Reports</a>
      <a href="admin.php?tab=add_product" style="color:rgba(255,255,255,.8); padding:6px 10px; font-size:.78rem; text-decoration:none; border-radius:4px; transition:background .15s;" onmouseover="this.style.background='rgba(255,255,255,.1)'" onmouseout="this.style.background='transparent'">➕ Add Product</a>
    </div>

    <!-- Right: Admin identity -->
    <div style="display:flex; align-items:center; gap:8px; padding:0 4px;">
      <?= user_avatar(current_user()['avatar'] ?? null, current_user()['full_name'], 22) ?>
      <span style="color:rgba(255,255,255,.7); font-size:.75rem;"><?= e(current_user()['full_name']) ?></span>
      <span style="background:#1565c0; color:#fff; padding:1px 8px; border-radius:10px; font:700 .65rem Inter,sans-serif;">ADMIN</span>
      <a href="profile.php" style="color:rgba(255,255,255,.5); font-size:.72rem; text-decoration:none; margin-left:4px;">Profile</a>
      <span style="color:rgba(255,255,255,.2);">|</span>
      <a href="logout.php"  style="color:#f87171; font-size:.72rem; text-decoration:none;">Logout</a>
    </div>

  </div>
</div>
<?php endif; ?>

<!-- ── Announcement strip (non-admin only) ── -->
<?php if (!current_user() || current_user()['role'] !== 'admin'): ?>
<div class="header-top">
  🚚 Free delivery on orders over UGX 200,000 &nbsp;|&nbsp;
  <a href="contact.php">Need help? Contact us →</a>
</div>
<?php endif; ?>

<!-- ── Main header ── -->
<header class="site-header">
  <div class="container">
    <div class="header-main">

      <!-- Hamburger (mobile) -->
      <button class="menu-toggle" type="button" onclick="toggleMobileNav()" aria-label="Toggle menu">☰</button>

      <!-- Brand -->
      <a class="brand" href="index.php">
        <span class="brand-mark">🌱</span>
        <span class="brand-text">
          Agro Input
          <small>Dealers · Fort Portal</small>
        </span>
      </a>

      <!-- Search -->
      <form class="header-search" method="get" action="products.php" role="search">
        <input type="search" name="q"
               value="<?= e($_GET['q'] ?? '') ?>"
               placeholder="Search seeds, fertilizers, farm tools…"
               aria-label="Search products">
        <button type="submit">🔍 Search</button>
      </form>

      <!-- Right actions -->
      <nav class="header-actions" aria-label="Account navigation">
        <?php if (current_user()): ?>
          <a class="hdr-btn" href="dashboard.php">
            <span class="icon">📊</span>
            <span class="label">Dashboard</span>
          </a>
          <?php if (current_user()['role'] === 'admin'): ?>
          <!-- Admin button — bright and always visible -->
          <a href="admin.php"
             style="display:inline-flex;align-items:center;gap:6px;background:#ffd54f;color:#0d2a4a;padding:8px 14px;border-radius:6px;font:700 .82rem Inter,sans-serif;text-decoration:none;white-space:nowrap;transition:background .15s;"
             onmouseover="this.style.background='#ffca28'"
             onmouseout="this.style.background='#ffd54f'">
            ⚙️ <span>Admin Panel</span>
          </a>
          <a href="reports.php"
             style="display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.2);color:#fff;padding:8px 14px;border-radius:6px;font:700 .82rem Inter,sans-serif;text-decoration:none;white-space:nowrap;transition:background .15s;"
             onmouseover="this.style.background='rgba(255,255,255,.3)'"
             onmouseout="this.style.background='rgba(255,255,255,.2)'">
            📈 <span>Reports</span>
          </a>
          <?php endif; ?>
          <a class="hdr-btn" href="logout.php">
            <span class="icon"><?= user_avatar(current_user()['avatar'] ?? null, current_user()['full_name'], 26) ?></span>
            <span class="label">Log out</span>
          </a>
        <?php else: ?>
          <a class="hdr-btn" href="login.php">
            <span class="icon">👤</span>
            <span class="label">Log in</span>
          </a>
          <a class="hdr-btn" href="register.php">
            <span class="icon">✏️</span>
            <span class="label">Register</span>
          </a>
        <?php endif; ?>
        <a class="hdr-btn" href="cart.php" aria-label="Shopping cart, <?= cart_count() ?> items">
          <span class="icon">🛒<span class="cart-count"><?= cart_count() ?></span></span>
          <span class="label">Cart</span>
        </a>
      </nav>
    </div>
  </div>

  <!-- ── Category nav bar ── -->
  <div class="cat-nav">
    <div class="container">
      <div class="cat-nav-inner">
        <?php if (current_user() && current_user()['role'] === 'admin'): ?>
          <!-- Admin category bar shows admin links -->
          <a href="admin.php"              style="color:#ffd54f; font-weight:700;">⚙️ Admin</a>
          <a href="reports.php"            style="color:#ffd54f; font-weight:700;">📈 Reports</a>
          <a href="admin.php?tab=orders"   >🧾 Orders</a>
          <a href="admin.php?tab=products" >📦 Products</a>
          <a href="admin.php?tab=customers">👥 Customers</a>
          <a href="admin.php?tab=add_product">➕ Add Product</a>
          <span style="color:rgba(255,255,255,.2); padding:0 6px;">|</span>
          <a href="products.php">🏪 Store</a>
          <a href="contact.php">📞 Help</a>
        <?php else: ?>
          <!-- Regular category bar -->
          <a href="products.php">🏪 All Products</a>
          <a href="products.php?category=<?= urlencode('Improved Seeds') ?>">🌽 Improved Seeds</a>
          <a href="products.php?category=<?= urlencode('Fertilizers') ?>">🌿 Fertilizers</a>
          <a href="products.php?category=<?= urlencode('Pesticides') ?>">🛡️ Pesticides</a>
          <a href="products.php?category=<?= urlencode('Animal Feeds') ?>">🐄 Animal Feeds</a>
          <a href="products.php?category=<?= urlencode('Veterinary Supplies') ?>">🐾 Veterinary</a>
          <a href="products.php?category=<?= urlencode('Farm Tools') ?>">🧰 Farm Tools</a>
          <a href="contact.php">📞 Help</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── Mobile nav drawer ── -->
  <div id="mobile-nav" style="display:none; background:#1a1a1a; padding:10px 0;">
    <div class="container" style="display:flex; flex-direction:column; gap:2px;">
      <a href="index.php"    style="color:#ddd; padding:8px 12px; font-size:.9rem;">🏠 Home</a>
      <a href="products.php" style="color:#ddd; padding:8px 12px; font-size:.9rem;">🏪 All Products</a>
      <a href="products.php?category=<?= urlencode('Improved Seeds') ?>"      style="color:#ddd; padding:8px 12px; font-size:.9rem;">🌽 Improved Seeds</a>
      <a href="products.php?category=<?= urlencode('Fertilizers') ?>"         style="color:#ddd; padding:8px 12px; font-size:.9rem;">🌿 Fertilizers</a>
      <a href="products.php?category=<?= urlencode('Pesticides') ?>"          style="color:#ddd; padding:8px 12px; font-size:.9rem;">🛡️ Pesticides</a>
      <a href="products.php?category=<?= urlencode('Animal Feeds') ?>"        style="color:#ddd; padding:8px 12px; font-size:.9rem;">🐄 Animal Feeds</a>
      <a href="products.php?category=<?= urlencode('Veterinary Supplies') ?>" style="color:#ddd; padding:8px 12px; font-size:.9rem;">🐾 Veterinary</a>
      <a href="products.php?category=<?= urlencode('Farm Tools') ?>"          style="color:#ddd; padding:8px 12px; font-size:.9rem;">🧰 Farm Tools</a>
      <a href="cart.php" style="color:#bbdefb; padding:8px 12px; font-size:.9rem; font-weight:700;">🛒 Cart (<?= cart_count() ?>)</a>
      <?php if (current_user()): ?>
        <a href="dashboard.php" style="color:#ddd; padding:8px 12px; font-size:.9rem;">📊 My Dashboard</a>
        <a href="my_orders.php" style="color:#ddd; padding:8px 12px; font-size:.9rem;">📦 My Orders</a>
        <?php if (current_user()['role'] === 'admin'): ?>
        <hr style="border:none; border-top:1px solid #333; margin:6px 0;">
        <span style="color:#ffd54f; padding:6px 12px; font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em;">Admin Panel</span>
        <a href="admin.php"               style="color:#ffd54f; padding:8px 12px; font-size:.9rem; font-weight:700;">⚙️ Admin Dashboard</a>
        <a href="reports.php"             style="color:#ffd54f; padding:8px 12px; font-size:.9rem; font-weight:700;">📈 Reports</a>
        <a href="admin.php?tab=orders"    style="color:#ddd; padding:8px 12px; font-size:.9rem;">🧾 Manage Orders</a>
        <a href="admin.php?tab=products"  style="color:#ddd; padding:8px 12px; font-size:.9rem;">📦 Manage Products</a>
        <a href="admin.php?tab=customers" style="color:#ddd; padding:8px 12px; font-size:.9rem;">👥 Customers</a>
        <a href="admin.php?tab=add_product" style="color:#ddd; padding:8px 12px; font-size:.9rem;">➕ Add Product</a>
        <hr style="border:none; border-top:1px solid #333; margin:6px 0;">
        <?php endif; ?>
        <a href="profile.php"  style="color:#ddd; padding:8px 12px; font-size:.9rem;">📷 My Profile</a>
        <a href="logout.php"   style="color:#f87171; padding:8px 12px; font-size:.9rem;">🚪 Log out</a>
      <?php else: ?>
        <a href="login.php"    style="color:#ddd; padding:8px 12px; font-size:.9rem;">👤 Log in</a>
        <a href="register.php" style="color:#bbdefb; padding:8px 12px; font-size:.9rem; font-weight:700;">✏️ Register</a>
      <?php endif; ?>
      <a href="contact.php"    style="color:#ddd; padding:8px 12px; font-size:.9rem;">📞 Contact</a>
    </div>
  </div>
</header>

<main class="main-content">
  <div class="container">

<?php if ($msg = flash('success')): ?><div class="alert success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($msg = flash('error')): ?><div class="alert error"><?= e($msg) ?></div><?php endif; ?>

<script>
function toggleMobileNav() {
  var nav = document.getElementById('mobile-nav');
  nav.style.display = nav.style.display === 'none' ? 'block' : 'none';
}
</script>
