<?php
require 'config.php'; require_login();
$user = current_user();
$uid  = $user['id'];

/* ── Data ─────────────────────────────────────────────────────────── */
$orders = $pdo->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC");
$orders->execute([$uid]); $orders = $orders->fetchAll();

$totalOrders   = count($orders);
$totalSpent    = array_sum(array_column($orders, 'total_amount'));
$pendingOrders = count(array_filter($orders, fn($o) => $o['order_status'] === 'Pending'));
$delivered     = count(array_filter($orders, fn($o) => $o['order_status'] === 'Delivered'));

// Latest 5 orders for overview
$recentOrders = array_slice($orders, 0, 5);

// Active tab
$tab = $_GET['tab'] ?? 'overview';

$catIcons = ['Improved Seeds'=>'🌽','Fertilizers'=>'🌿','Pesticides'=>'🛡️','Animal Feeds'=>'🐄','Veterinary Supplies'=>'🐾','Farm Tools'=>'🧰'];

$pageTitle = 'My Dashboard';
require 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
  <a href="index.php">Home</a>
  <span class="sep">›</span>
  <span>My Dashboard</span>
</div>

<div class="dash-wrap">

  <!-- ══ SIDEBAR ══════════════════════════════════════════════════════ -->
  <aside class="dash-sidebar">
    <div class="dash-sidebar-head">
      <div style="width:56px;height:56px;border-radius:50%;overflow:hidden;margin-bottom:10px;">
        <?php if (!empty($user['avatar']) && file_exists(__DIR__ . '/' . $user['avatar'])): ?>
          <img src="<?= e($user['avatar']) ?>?v=<?= time() ?>" alt="<?= e($user['full_name']) ?>" style="width:56px;height:56px;border-radius:50%;object-fit:cover;border:3px solid rgba(66,165,245,.5);">
        <?php else: ?>
          <div style="width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#1565c0,#42a5f5);display:flex;align-items:center;justify-content:center;font:800 1.4rem Inter,sans-serif;color:#fff;border:3px solid rgba(255,255,255,.15);">
            <?= strtoupper(mb_substr($user['full_name'],0,1)) ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="user-name"><?= e($user['full_name']) ?></div>
      <div class="user-role">Customer Account</div>
    </div>
    <nav class="dash-nav">
      <div class="dash-nav-section">Account</div>
      <a href="?tab=overview"  class="<?= $tab==='overview'  ?'active':'' ?>"><span class="nav-icon">📊</span> Overview</a>
      <a href="?tab=orders"    class="<?= $tab==='orders'    ?'active':'' ?>"><span class="nav-icon">📦</span> My Orders
        <?php if ($pendingOrders): ?><span style="background:#1565c0;color:#fff;font-size:.65rem;padding:1px 6px;border-radius:10px;margin-left:auto;"><?= $pendingOrders ?></span><?php endif; ?>
      </a>
      <a href="?tab=profile"   class="<?= $tab==='profile'   ?'active':'' ?>"><span class="nav-icon">👤</span> My Profile</a>
      <a href="profile.php"><span class="nav-icon">📷</span> Profile Photo</a>
      <div class="dash-nav-section">Shop</div>
      <a href="products.php"><span class="nav-icon">🌾</span> Browse Products</a>
      <a href="cart.php"><span class="nav-icon">🛒</span> Shopping Cart
        <?php $cc = cart_count(); if ($cc): ?><span style="background:#1565c0;color:#fff;font-size:.65rem;padding:1px 6px;border-radius:10px;margin-left:auto;"><?= $cc ?></span><?php endif; ?>
      </a>
      <a href="contact.php"><span class="nav-icon">📞</span> Contact Support</a>
      <div class="dash-nav-section">Session</div>
      <a href="logout.php"><span class="nav-icon">🚪</span> Log Out</a>
    </nav>
  </aside>

  <!-- ══ MAIN CONTENT ══════════════════════════════════════════════════ -->
  <main class="dash-main">

    <?php /* ─────────────── OVERVIEW TAB ─────────────── */ ?>
    <?php if ($tab === 'overview'): ?>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
      <div>
        <h1 style="font:800 1.3rem Inter,sans-serif; margin:0 0 4px;">
          Welcome back, <?= e(explode(' ', $user['full_name'])[0]) ?>! 👋
        </h1>
        <p style="color:#888; font-size:.83rem; margin:0;">Here's a summary of your account and orders.</p>
      </div>
      <a class="btn" href="products.php">🛒 Shop Now</a>
    </div>

    <!-- Customer stats -->
    <div class="cust-stats">
      <div class="cust-stat">
        <div class="cs-icon" style="background:#e3f2fd;">📦</div>
        <div>
          <div class="cs-label">Total Orders</div>
          <div class="cs-value"><?= $totalOrders ?></div>
        </div>
      </div>
      <div class="cust-stat">
        <div class="cs-icon" style="background:#e8f7ee;">✅</div>
        <div>
          <div class="cs-label">Delivered</div>
          <div class="cs-value"><?= $delivered ?></div>
        </div>
      </div>
      <div class="cust-stat">
        <div class="cs-icon" style="background:#fff3e0;">💰</div>
        <div>
          <div class="cs-label">Total Spent</div>
          <div class="cs-value" style="font-size:1rem;"><?= money($totalSpent) ?></div>
        </div>
      </div>
    </div>

    <?php if ($pendingOrders > 0): ?>
    <div class="note" style="margin-bottom:18px;">
      🔔 You have <strong><?= $pendingOrders ?> pending order<?= $pendingOrders>1?'s':'' ?></strong> awaiting processing.
      <a href="?tab=orders" style="font-weight:700;">View orders →</a>
    </div>
    <?php endif; ?>

    <!-- Recent orders -->
    <div class="dash-section" style="margin-bottom:18px;">
      <div class="dash-section-head">
        <h3>📦 Recent Orders</h3>
        <a href="?tab=orders" style="font-size:.8rem; color:#1565c0; font-weight:600;">View all →</a>
      </div>
      <?php if (!$recentOrders): ?>
        <div class="dash-section-body">
          <div style="text-align:center; padding:30px 20px;">
            <div style="font-size:3rem; margin-bottom:10px;">🛒</div>
            <p style="color:#888; margin-bottom:14px;">You haven't placed any orders yet.</p>
            <a class="btn" href="products.php">Browse Products</a>
          </div>
        </div>
      <?php else: ?>
      <div style="padding:0;">
        <table class="mini-table">
          <thead><tr><th>Order</th><th>Date</th><th>Amount</th><th>Status</th><th>Payment</th></tr></thead>
          <tbody>
          <?php foreach ($recentOrders as $o):
            $oc = match(strtolower($o['order_status'])) {'pending'=>'status-pending','processing'=>'status-processing','ready for delivery'=>'status-ready','delivered'=>'status-delivered','cancelled'=>'status-cancelled',default=>''};
            $pc = match(strtolower($o['payment_status'])) {'confirmed'=>'status-confirmed','failed'=>'status-failed','refunded'=>'status-refunded',default=>'status-pending'};
          ?>
          <tr>
            <td><strong style="color:#1565c0;">#<?= (int)$o['id'] ?></strong></td>
            <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
            <td><strong><?= money($o['total_amount']) ?></strong></td>
            <td><span class="status <?= $oc ?>"><?= e($o['order_status']) ?></span></td>
            <td><span class="status <?= $pc ?>"><?= e($o['payment_status']) ?></span></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- Quick actions -->
    <div class="dash-section">
      <div class="dash-section-head"><h3>⚡ Quick Actions</h3></div>
      <div class="dash-section-body">
        <div class="quick-actions" style="grid-template-columns: repeat(4,1fr);">
          <a class="qa-btn" href="products.php?category=Improved+Seeds"><span class="qa-icon">🌽</span>Seeds</a>
          <a class="qa-btn" href="products.php?category=Fertilizers"><span class="qa-icon">🌿</span>Fertilizers</a>
          <a class="qa-btn" href="products.php?category=Animal+Feeds"><span class="qa-icon">🐄</span>Animal Feeds</a>
          <a class="qa-btn" href="products.php?category=Farm+Tools"><span class="qa-icon">🧰</span>Farm Tools</a>
        </div>
      </div>
    </div>

    <?php /* ─────────────── ORDERS TAB ─────────────── */ ?>
    <?php elseif ($tab === 'orders'): ?>

    <div style="margin-bottom:20px;">
      <h1 style="font:800 1.3rem Inter,sans-serif; margin:0 0 4px;">My Orders</h1>
      <p style="color:#888; font-size:.83rem; margin:0;"><?= $totalOrders ?> order<?= $totalOrders!==1?'s':'' ?> placed</p>
    </div>

    <?php if (!$orders): ?>
      <div class="empty">
        <div class="empty-icon">📦</div>
        <h2>No Orders Yet</h2>
        <p>You haven't placed any orders. Start shopping!</p>
        <a class="btn lg" href="products.php">🌾 Browse Products</a>
      </div>
    <?php else: ?>
    <?php foreach ($orders as $o):
      $oc = match(strtolower($o['order_status'])) {'pending'=>'status-pending','processing'=>'status-processing','ready for delivery'=>'status-ready','delivered'=>'status-delivered','cancelled'=>'status-cancelled',default=>''};
      $pc = match(strtolower($o['payment_status'])) {'confirmed'=>'status-confirmed','failed'=>'status-failed','refunded'=>'status-refunded',default=>'status-pending'};
      // timeline steps
      $steps = ['Pending','Processing','Ready for delivery','Delivered'];
      $cancelled = $o['order_status'] === 'Cancelled';
      $currentStep = array_search($o['order_status'], $steps);
      $oItems = $pdo->prepare("SELECT * FROM order_items WHERE order_id=?");
      $oItems->execute([$o['id']]); $oItems = $oItems->fetchAll();
    ?>
    <div class="order-panel" style="margin-bottom:14px;">
      <div class="order-panel-head">
        <div>
          <h3 style="margin:0 0 3px;">Order <span style="color:#1565c0;">#<?= (int)$o['id'] ?></span></h3>
          <small class="muted"><?= date('D, d M Y · H:i', strtotime($o['created_at'])) ?></small>
        </div>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
          <span class="status <?= $oc ?>"><?= e($o['order_status']) ?></span>
          <span class="status <?= $pc ?>"><?= e($o['payment_status']) ?></span>
          <strong style="color:#1565c0;"><?= money($o['total_amount']) ?></strong>
        </div>
      </div>
      <div class="order-panel-body">

        <!-- Order timeline -->
        <?php if (!$cancelled): ?>
        <div class="order-timeline">
          <?php foreach ($steps as $i => $step):
            $cls = '';
            if ($currentStep !== false) {
              if ($i < $currentStep) $cls = 'done';
              elseif ($i === $currentStep) $cls = 'current';
            }
            $icons = ['Pending'=>'⏳','Processing'=>'⚙️','Ready for delivery'=>'🚚','Delivered'=>'✅'];
          ?>
          <div class="ot-step <?= $cls ?>">
            <div class="ot-icon"><?= $icons[$step] ?></div>
            <?= e($step) ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="background:#fdeaea; border-radius:6px; padding:10px 14px; margin-bottom:14px; color:#b71c1c; font:600 .85rem Inter,sans-serif;">
          ✘ This order was cancelled.
        </div>
        <?php endif; ?>

        <!-- Order items -->
        <div class="table-wrap" style="margin-top:14px;">
          <table>
            <thead><tr><th>Product</th><th style="text-align:center">Qty</th><th style="text-align:right">Unit Price</th><th style="text-align:right">Subtotal</th></tr></thead>
            <tbody>
            <?php foreach ($oItems as $line): ?>
            <tr>
              <td><strong><?= e($line['product_name']) ?></strong></td>
              <td style="text-align:center; font-weight:700;"><?= (int)$line['quantity'] ?></td>
              <td style="text-align:right;"><?= money($line['unit_price']) ?></td>
              <td style="text-align:right; color:#1565c0; font-weight:700;"><?= money($line['line_total']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr><td colspan="3" style="text-align:right; font-weight:700;">Order Total</td>
              <td style="text-align:right; color:#1565c0; font:700 1rem Inter,sans-serif;"><?= money($o['total_amount']) ?></td></tr>
            </tfoot>
          </table>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:14px; background:#fafafa; border-radius:6px; padding:12px; border:1px solid var(--line);">
          <div>
            <div style="font:600 .72rem Inter,sans-serif; color:#aaa; text-transform:uppercase; margin-bottom:3px;">Delivery Address</div>
            <div style="font-size:.85rem;"><?= e($o['delivery_address']) ?></div>
          </div>
          <div>
            <div style="font:600 .72rem Inter,sans-serif; color:#aaa; text-transform:uppercase; margin-bottom:3px;">Payment Method</div>
            <div style="font-size:.85rem;"><?= e($o['payment_method']) ?></div>
          </div>
          <?php if ($o['customer_note']): ?>
          <div style="grid-column:1/-1;">
            <div style="font:600 .72rem Inter,sans-serif; color:#aaa; text-transform:uppercase; margin-bottom:3px;">Note</div>
            <div style="font-size:.85rem; color:#666;"><?= e($o['customer_note']) ?></div>
          </div>
          <?php endif; ?>
        </div>

      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php /* ─────────────── PROFILE TAB ─────────────── */ ?>
    <?php elseif ($tab === 'profile'): ?>

    <div style="margin-bottom:20px;">
      <h1 style="font:800 1.3rem Inter,sans-serif; margin:0 0 4px;">My Profile</h1>
      <p style="color:#888; font-size:.83rem; margin:0;">Your account details and delivery information.</p>
    </div>

    <div style="max-width:620px;">
      <div class="dash-section">
        <div class="dash-section-head"><h3>👤 Account Information</h3></div>
        <div class="dash-section-body">

          <div style="display:flex; align-items:center; gap:16px; margin-bottom:22px; padding:16px; background:#f8f8f8; border-radius:8px; border:1px solid var(--line);">
            <div style="width:60px; height:60px; background:linear-gradient(135deg,#1565c0,#42a5f5); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.6rem; color:#fff; flex-shrink:0;">
              <?= strtoupper(mb_substr($user['full_name'],0,1)) ?>
            </div>
            <div>
              <div style="font:700 1rem Inter,sans-serif;"><?= e($user['full_name']) ?></div>
              <div style="color:#888; font-size:.85rem;"><?= e($user['email']) ?></div>
              <span style="background:#e3f2fd; color:#0d47a1; padding:2px 8px; border-radius:10px; font:600 .72rem Inter,sans-serif; margin-top:4px; display:inline-block;">
                Customer
              </span>
            </div>
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
            <div style="background:#fafafa; border:1px solid var(--line); border-radius:6px; padding:14px;">
              <div style="font:600 .72rem Inter,sans-serif; color:#aaa; text-transform:uppercase; letter-spacing:.04em; margin-bottom:6px;">Full Name</div>
              <div style="font-weight:600;"><?= e($user['full_name']) ?></div>
            </div>
            <div style="background:#fafafa; border:1px solid var(--line); border-radius:6px; padding:14px;">
              <div style="font:600 .72rem Inter,sans-serif; color:#aaa; text-transform:uppercase; letter-spacing:.04em; margin-bottom:6px;">Phone Number</div>
              <div style="font-weight:600;"><?= e($user['phone']) ?></div>
            </div>
            <div style="background:#fafafa; border:1px solid var(--line); border-radius:6px; padding:14px; grid-column:1/-1;">
              <div style="font:600 .72rem Inter,sans-serif; color:#aaa; text-transform:uppercase; letter-spacing:.04em; margin-bottom:6px;">Email Address</div>
              <div style="font-weight:600;"><?= e($user['email']) ?></div>
            </div>
            <div style="background:#fafafa; border:1px solid var(--line); border-radius:6px; padding:14px; grid-column:1/-1;">
              <div style="font:600 .72rem Inter,sans-serif; color:#aaa; text-transform:uppercase; letter-spacing:.04em; margin-bottom:6px;">Default Delivery Address</div>
              <div style="font-size:.9rem; color:#555;"><?= e($user['address']) ?></div>
            </div>
          </div>

          <div style="margin-top:18px; padding-top:14px; border-top:1px solid var(--line);">
            <div class="note">
              ℹ️ To update your profile details, contact our support team or place a new order with updated information.
            </div>
          </div>

        </div>
      </div>

      <!-- Account stats -->
      <div class="dash-section" style="margin-top:14px;">
        <div class="dash-section-head"><h3>📊 Your Statistics</h3></div>
        <div class="dash-section-body">
          <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:12px;">
            <div style="background:#e3f2fd; border-radius:8px; padding:16px; text-align:center;">
              <div style="font-size:1.8rem; font-weight:800; color:#1565c0;"><?= $totalOrders ?></div>
              <div style="font-size:.8rem; color:#1565c0; font-weight:600;">Total Orders</div>
            </div>
            <div style="background:#e8f7ee; border-radius:8px; padding:16px; text-align:center;">
              <div style="font-size:1.8rem; font-weight:800; color:#1a6b3a;"><?= $delivered ?></div>
              <div style="font-size:.8rem; color:#1a6b3a; font-weight:600;">Delivered</div>
            </div>
            <div style="background:#fff3e0; border-radius:8px; padding:16px; text-align:center; grid-column:1/-1;">
              <div style="font-size:1.5rem; font-weight:800; color:#b36000;"><?= money($totalSpent) ?></div>
              <div style="font-size:.8rem; color:#b36000; font-weight:600;">Total Amount Spent</div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <?php endif; ?>

  </main>
</div><!-- /dash-wrap -->

<?php require 'includes/footer.php'; ?>
