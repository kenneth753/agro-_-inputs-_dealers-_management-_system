<?php
require 'config.php'; require_admin();

/* ── POST handlers ─────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_product') {
        $name        = trim($_POST['name'] ?? '');
        $category    = $_POST['category'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $price       = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
        $stock       = filter_var($_POST['stock_quantity'] ?? null, FILTER_VALIDATE_INT);
        $image_url   = trim($_POST['image_url'] ?? '');
        $allowed     = ['Improved Seeds','Fertilizers','Pesticides','Animal Feeds','Veterinary Supplies','Farm Tools'];
        if ($name==='' || !in_array($category,$allowed,true) || $price===false || $price<0 || $stock===false || $stock<0) {
            flash('error', 'Please provide valid product details.');
        } else {
            $pdo->prepare("INSERT INTO products(name,category,description,price,stock_quantity,image_url) VALUES(?,?,?,?,?,?)")
                ->execute([$name,$category,$description,$price,$stock,$image_url ?: null]);
            flash('success', 'Product added successfully.');
        }
    } elseif ($action === 'update_product') {
        $id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
        $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
        $stock = filter_var($_POST['stock_quantity'] ?? null, FILTER_VALIDATE_INT);
        $image_url = trim($_POST['image_url'] ?? '');
        if (!$id || $price===false || $price<0 || $stock===false || $stock<0) {
            flash('error', 'Invalid product update.');
        } else {
            $pdo->prepare("UPDATE products SET price=?,stock_quantity=?,image_url=? WHERE id=?")
                ->execute([$price,$stock,$image_url ?: null,$id]);
            flash('success', 'Product updated.');
        }
    } elseif ($action === 'update_order') {
        $id      = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
        $status  = $_POST['order_status'] ?? '';
        $payment = $_POST['payment_status'] ?? '';
        $statuses = ['Pending','Processing','Ready for delivery','Delivered','Cancelled'];
        $payments = ['Pending','Confirmed','Failed','Refunded'];
        if (!$id || !in_array($status,$statuses,true) || !in_array($payment,$payments,true)) {
            flash('error', 'Invalid order update.');
        } else {
            $lookup = $pdo->prepare("SELECT phone,order_status,payment_status FROM orders WHERE id=?");
            $lookup->execute([$id]); $existing = $lookup->fetch();
            if (!$existing) {
                flash('error', 'Order not found.');
            } else {
                $pdo->prepare("UPDATE orders SET order_status=?,payment_status=? WHERE id=?")
                    ->execute([$status,$payment,$id]);
                if ($existing['order_status']!==$status || $existing['payment_status']!==$payment) {
                    require_once 'includes/sms.php';
                    send_sms($existing['phone'], 'Agro Input: Order #'.$id.' update. Order: '.$status.'. Payment: '.$payment.'.');
                }
                flash('success', 'Order status updated.');
            }
        }
    }
    header('Location: admin.php' . ($_GET['tab'] ? '?tab='.urlencode($_GET['tab']) : '')); exit;
}

/* ── Data queries ──────────────────────────────────────────────────── */
$tab = $_GET['tab'] ?? 'overview';

// Core stats
$totalProducts  = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE active=1")->fetchColumn();
$totalOrders    = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pendingOrders  = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status='Pending'")->fetchColumn();
$totalSales     = (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE order_status<>'Cancelled'")->fetchColumn();
$totalCustomers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
$deliveredCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status='Delivered'")->fetchColumn();
$cancelledCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status='Cancelled'")->fetchColumn();
$lowStock       = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE active=1 AND stock_quantity <= 5")->fetchColumn();

// Recent orders (for overview)
$recentOrders = $pdo->query("SELECT o.*, u.full_name FROM orders o LEFT JOIN users u ON o.user_id=u.id ORDER BY o.created_at DESC LIMIT 8")->fetchAll();

// Orders by status
$ordersByStatus = [];
foreach (['Pending','Processing','Ready for delivery','Delivered','Cancelled'] as $s) {
    $ordersByStatus[$s] = (int)$pdo->prepare("SELECT COUNT(*) FROM orders WHERE order_status=?")->execute([$s]) ? (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status='$s'")->fetchColumn() : 0;
}

// Sales by category
$catSales = $pdo->query("
    SELECT p.category, SUM(oi.line_total) AS total
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    GROUP BY p.category ORDER BY total DESC
")->fetchAll();

// All products (for products tab)
$products = $pdo->query("SELECT * FROM products ORDER BY category, name")->fetchAll();

// All orders (for orders tab)
$orders = $pdo->query("SELECT o.*, u.full_name FROM orders o LEFT JOIN users u ON o.user_id=u.id ORDER BY o.created_at DESC LIMIT 100")->fetchAll();

// All customers (for customers tab)
$customers = $pdo->query("
    SELECT u.*, COUNT(o.id) AS order_count, COALESCE(SUM(o.total_amount),0) AS total_spent
    FROM users u
    LEFT JOIN orders o ON u.id=o.user_id
    WHERE u.role='customer'
    GROUP BY u.id ORDER BY u.created_at DESC
")->fetchAll();

$pageTitle = 'Admin Dashboard';
// No standard header — dashboard has its own layout
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Dashboard | Agro Input Dealers</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body style="background:#f0f2f5;">

<!-- Top bar -->
<div style="background:#0d2a4a; color:#fff; padding:0; position:sticky; top:0; z-index:200; box-shadow:0 2px 8px rgba(0,0,0,.2);">
  <div style="display:flex; align-items:center; justify-content:space-between; padding:0 24px; min-height:56px; gap:14px;">
    <div style="display:flex; align-items:center; gap:14px;">
      <a href="index.php" style="color:rgba(255,255,255,.6); font-size:.82rem; text-decoration:none;">← Back to Site</a>
      <span style="color:rgba(255,255,255,.2);">|</span>
      <span style="color:#fff; font:700 1rem Inter,sans-serif;">🌱 Agro Input <span style="color:#42a5f5;">Admin</span></span>
    </div>
    <div style="display:flex; align-items:center; gap:16px;">
      <?php if ($lowStock > 0): ?>
      <span style="background:#f59e0b; color:#fff; padding:3px 10px; border-radius:20px; font:700 .75rem Inter,sans-serif;">
        ⚠️ <?= $lowStock ?> low stock
      </span>
      <?php endif; ?>
      <?php if ($pendingOrders > 0): ?>
      <span style="background:#e02020; color:#fff; padding:3px 10px; border-radius:20px; font:700 .75rem Inter,sans-serif;">
        🔔 <?= $pendingOrders ?> pending
      </span>
      <?php endif; ?>
      <span style="color:rgba(255,255,255,.7); font-size:.82rem; display:flex; align-items:center; gap:8px;">
        <?= user_avatar(current_user()['avatar'] ?? null, current_user()['full_name'], 30) ?>
        <?= e(current_user()['full_name']) ?>
      </span>
      <a href="logout.php" style="color:#f87171; font-size:.82rem; text-decoration:none;">Logout</a>
    </div>
  </div>
</div>

<div style="width:min(1280px,98%); margin:20px auto; padding:0 8px;">

<?php if ($msg = flash('success')): ?><div class="alert success" style="margin-bottom:14px;"><?= e($msg) ?></div><?php endif; ?>
<?php if ($msg = flash('error')):   ?><div class="alert error"   style="margin-bottom:14px;"><?= e($msg) ?></div><?php endif; ?>

<div class="dash-wrap">

  <!-- ══ SIDEBAR ══════════════════════════════════════════════════════ -->
  <aside class="dash-sidebar">
    <div class="dash-sidebar-head">
      <div class="avatar" style="width:56px;height:56px;border-radius:50%;overflow:hidden;padding:0;background:none;margin-bottom:10px;">
        <?php if (!empty(current_user()['avatar']) && file_exists(__DIR__ . '/' . current_user()['avatar'])): ?>
          <img src="<?= e(current_user()['avatar']) ?>?v=<?= time() ?>" alt="<?= e(current_user()['full_name']) ?>" style="width:56px;height:56px;border-radius:50%;object-fit:cover;border:3px solid rgba(66,165,245,.5);">
        <?php else: ?>
          <div style="width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#1565c0,#42a5f5);display:flex;align-items:center;justify-content:center;font:800 1.4rem Inter,sans-serif;color:#fff;border:3px solid rgba(255,255,255,.15);">
            <?= strtoupper(mb_substr(current_user()['full_name'],0,1)) ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="user-name"><?= e(current_user()['full_name']) ?></div>
      <div class="user-role">⚙️ System Administrator · Developer</div>
    </div>
    <nav class="dash-nav">
      <div class="dash-nav-section">Main</div>
      <a href="?tab=overview"  class="<?= $tab==='overview'  ? 'active' : '' ?>"><span class="nav-icon">📊</span> Overview</a>
      <a href="?tab=orders"    class="<?= $tab==='orders'    ? 'active' : '' ?>"><span class="nav-icon">🧾</span> Orders <span style="background:#e02020;color:#fff;font-size:.65rem;padding:1px 6px;border-radius:10px;margin-left:auto;"><?= $pendingOrders ?></span></a>
      <a href="?tab=products"  class="<?= $tab==='products'  ? 'active' : '' ?>"><span class="nav-icon">📦</span> Products</a>
      <a href="?tab=customers" class="<?= $tab==='customers' ? 'active' : '' ?>"><span class="nav-icon">👥</span> Customers</a>
      <a href="reports.php"    style="color:rgba(255,255,255,.7);display:flex;align-items:center;gap:10px;padding:10px 20px;font-size:.86rem;font-weight:500;border-left:3px solid transparent;text-decoration:none;transition:background .15s,color .15s;"><span class="nav-icon">📈</span> Reports</a>
      <div class="dash-nav-section">Manage</div>
      <a href="?tab=add_product" class="<?= $tab==='add_product' ? 'active' : '' ?>"><span class="nav-icon">➕</span> Add Product</a>
      <div class="dash-nav-section">Store</div>
      <a href="index.php"><span class="nav-icon">🏪</span> View Store</a>
      <a href="products.php"><span class="nav-icon">🌾</span> Product Listing</a>
      <a href="contact.php"><span class="nav-icon">📞</span> Contact</a>
      <div class="dash-nav-section">Account</div>
      <a href="profile.php"><span class="nav-icon">📷</span> My Profile & Photo</a>
      <a href="logout.php"><span class="nav-icon">🚪</span> Logout</a>
    </nav>
  </aside>

  <!-- ══ MAIN CONTENT ══════════════════════════════════════════════════ -->
  <main class="dash-main">

    <?php /* ─────────────────── OVERVIEW TAB ─────────────────── */ ?>
    <?php if ($tab === 'overview'): ?>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
      <div>
        <h1 style="font:800 1.4rem Inter,sans-serif; margin:0;">Dashboard Overview</h1>
        <p style="color:#888; font-size:.83rem; margin:4px 0 0;">Welcome back! Here's what's happening today.</p>
      </div>
      <span style="background:#e3f2fd; color:#1565c0; padding:5px 14px; border-radius:20px; font:700 .8rem Inter,sans-serif;">
        <?= date('D, d M Y') ?>
      </span>
    </div>

    <!-- Stats -->
    <div class="dash-stats">
      <div class="dash-stat blue" data-icon="💰">
        <div class="ds-label">Total Sales</div>
        <div class="ds-value" style="font-size:1.3rem;"><?= money($totalSales) ?></div>
        <div class="ds-sub"><?= $deliveredCount ?> orders delivered</div>
      </div>
      <div class="dash-stat green" data-icon="🧾">
        <div class="ds-label">Total Orders</div>
        <div class="ds-value"><?= number_format($totalOrders) ?></div>
        <div class="ds-sub"><?= $pendingOrders ?> pending review</div>
      </div>
      <div class="dash-stat amber" data-icon="📦">
        <div class="ds-label">Active Products</div>
        <div class="ds-value"><?= number_format($totalProducts) ?></div>
        <div class="ds-sub"><?= $lowStock ?> low stock alerts</div>
      </div>
      <div class="dash-stat red" data-icon="👥">
        <div class="ds-label">Customers</div>
        <div class="ds-value"><?= number_format($totalCustomers) ?></div>
        <div class="ds-sub"><?= $cancelledCount ?> cancelled orders</div>
      </div>
    </div>

    <div style="display:grid; grid-template-columns:1.6fr 1fr; gap:18px; margin-bottom:18px;">

      <!-- Recent Orders -->
      <div class="dash-section">
        <div class="dash-section-head">
          <h3>🧾 Recent Orders</h3>
          <a href="?tab=orders" style="font-size:.8rem; color:#1565c0; font-weight:600;">View all →</a>
        </div>
        <div style="padding:0;">
          <table class="mini-table">
            <thead><tr>
              <th>Order</th><th>Customer</th><th>Amount</th><th>Status</th><th>Action</th>
            </tr></thead>
            <tbody>
            <?php foreach ($recentOrders as $o):
              $oc = match(strtolower($o['order_status'])) {
                'pending'=>'status-pending','processing'=>'status-processing',
                'ready for delivery'=>'status-ready','delivered'=>'status-delivered',
                'cancelled'=>'status-cancelled',default=>''
              };
            ?>
            <tr>
              <td><strong style="color:#1565c0;">#<?= (int)$o['id'] ?></strong><br><small style="color:#aaa;"><?= date('d M', strtotime($o['created_at'])) ?></small></td>
              <td><?= e($o['full_name'] ?? $o['customer_name']) ?><br><small style="color:#aaa;"><?= e($o['phone']) ?></small></td>
              <td><strong><?= money($o['total_amount']) ?></strong></td>
              <td><span class="status <?= $oc ?>"><?= e($o['order_status']) ?></span></td>
              <td><a href="?tab=orders" class="btn sm secondary">Update</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$recentOrders): ?><tr><td colspan="5" style="text-align:center; color:#aaa; padding:20px;">No orders yet</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Right column -->
      <div style="display:flex; flex-direction:column; gap:18px;">

        <!-- Order status breakdown -->
        <div class="dash-section">
          <div class="dash-section-head"><h3>📊 Orders by Status</h3></div>
          <div class="dash-section-body" style="padding:16px 20px;">
            <?php
            $statusColors = ['Pending'=>'#f59e0b','Processing'=>'#1565c0','Ready for delivery'=>'#8b5cf6','Delivered'=>'#3cb878','Cancelled'=>'#e02020'];
            $maxVal = max(array_values($ordersByStatus) ?: [1]);
            foreach ($ordersByStatus as $s => $cnt):
              $pct = $maxVal > 0 ? ($cnt/$maxVal*100) : 0;
            ?>
            <div style="margin-bottom:10px;">
              <div style="display:flex; justify-content:space-between; font-size:.8rem; margin-bottom:4px;">
                <span style="font-weight:600;"><?= e($s) ?></span>
                <span style="color:#888;"><?= $cnt ?></span>
              </div>
              <div style="background:#f0f0f0; border-radius:4px; height:7px;">
                <div style="width:<?= $pct ?>%; background:<?= $statusColors[$s] ?? '#1565c0' ?>; height:7px; border-radius:4px; transition:width .4s;"></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Quick actions -->
        <div class="dash-section">
          <div class="dash-section-head"><h3>⚡ Quick Actions</h3></div>
          <div class="dash-section-body">
            <div class="quick-actions">
              <a class="qa-btn" href="?tab=add_product"><span class="qa-icon">➕</span>Add Product</a>
              <a class="qa-btn" href="?tab=orders"><span class="qa-icon">🧾</span>Manage Orders</a>
              <a class="qa-btn" href="?tab=products"><span class="qa-icon">📦</span>View Inventory</a>
              <a class="qa-btn" href="?tab=customers"><span class="qa-icon">👥</span>Customers</a>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Sales by category -->
    <?php if ($catSales): ?>
    <div class="dash-section">
      <div class="dash-section-head"><h3>🌾 Sales by Category</h3></div>
      <div class="dash-section-body">
        <div class="cat-breakdown">
          <?php
          $catIcons = ['Improved Seeds'=>'🌽','Fertilizers'=>'🌿','Pesticides'=>'🛡️','Animal Feeds'=>'🐄','Veterinary Supplies'=>'🐾','Farm Tools'=>'🧰'];
          $maxCat = max(array_column($catSales, 'total') ?: [1]);
          foreach ($catSales as $row):
            $pct = $maxCat > 0 ? ($row['total']/$maxCat*100) : 0;
          ?>
          <div class="cat-row">
            <span class="cat-name"><?= ($catIcons[$row['category']] ?? '🌱') . ' ' . e($row['category']) ?></span>
            <div class="cat-bar-wrap"><div class="cat-bar" style="width:<?= $pct ?>%;"></div></div>
            <span class="cat-count"><?= money($row['total']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Low stock alert -->
    <?php
    $lowStockProducts = $pdo->query("SELECT * FROM products WHERE active=1 AND stock_quantity <= 10 ORDER BY stock_quantity ASC LIMIT 6")->fetchAll();
    if ($lowStockProducts):
    ?>
    <div class="dash-section">
      <div class="dash-section-head">
        <h3>⚠️ Low Stock Alert</h3>
        <a href="?tab=products" style="font-size:.8rem; color:#1565c0; font-weight:600;">Manage →</a>
      </div>
      <div style="padding:0;">
        <table class="mini-table">
          <thead><tr><th>Product</th><th>Category</th><th>Stock</th><th>Action</th></tr></thead>
          <tbody>
          <?php foreach ($lowStockProducts as $p): ?>
          <tr>
            <td><strong><?= e($p['name']) ?></strong></td>
            <td><?= e($p['category']) ?></td>
            <td>
              <span style="background:<?= (int)$p['stock_quantity']===0?'#fdeaea':'#fff3e0' ?>; color:<?= (int)$p['stock_quantity']===0?'#b71c1c':'#b36000' ?>; padding:2px 8px; border-radius:10px; font:700 .78rem Inter,sans-serif;">
                <?= (int)$p['stock_quantity'] === 0 ? 'Out of stock' : (int)$p['stock_quantity'].' left' ?>
              </span>
            </td>
            <td><a href="?tab=products" class="btn sm secondary">Restock</a></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php /* ─────────────────── ORDERS TAB ─────────────────── */ ?>
    <?php elseif ($tab === 'orders'): ?>

    <div style="margin-bottom:20px;">
      <h1 style="font:800 1.3rem Inter,sans-serif; margin:0 0 4px;">Order Management</h1>
      <p style="color:#888; font-size:.83rem; margin:0;"><?= $totalOrders ?> total orders · <?= $pendingOrders ?> pending</p>
    </div>

    <!-- Order status filter pills -->
    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:18px;">
      <?php
      $filterStatus = $_GET['ostatus'] ?? '';
      $statusList = ['','Pending','Processing','Ready for delivery','Delivered','Cancelled'];
      $statusLabels = [''=> 'All ('.$totalOrders.')','Pending'=>'Pending ('.$ordersByStatus['Pending'].')','Processing'=>'Processing ('.$ordersByStatus['Processing'].')','Ready for delivery'=>'Ready ('.$ordersByStatus['Ready for delivery'].')','Delivered'=>'Delivered ('.$ordersByStatus['Delivered'].')','Cancelled'=>'Cancelled ('.$ordersByStatus['Cancelled'].')'];
      foreach ($statusList as $sl):
        $active = $filterStatus === $sl;
      ?>
      <a href="?tab=orders<?= $sl ? '&ostatus='.urlencode($sl) : '' ?>"
         style="padding:5px 14px; border-radius:20px; font:600 .8rem Inter,sans-serif; border:1.5px solid <?= $active ? '#1565c0' : '#ddd' ?>; background:<?= $active ? '#e3f2fd' : '#fff' ?>; color:<?= $active ? '#0d47a1' : '#555' ?>; text-decoration:none;">
        <?= e($statusLabels[$sl]) ?>
      </a>
      <?php endforeach; ?>
    </div>

    <?php
    // filtered orders
    $filtSQL = "SELECT o.*, u.full_name FROM orders o LEFT JOIN users u ON o.user_id=u.id";
    $filtParams = [];
    if ($filterStatus) { $filtSQL .= " WHERE o.order_status=?"; $filtParams[] = $filterStatus; }
    $filtSQL .= " ORDER BY o.created_at DESC LIMIT 100";
    $filtOrders = $pdo->prepare($filtSQL); $filtOrders->execute($filtParams); $filtOrders = $filtOrders->fetchAll();
    ?>

    <div class="dash-section">
      <div class="dash-section-head">
        <h3>🧾 <?= $filterStatus ?: 'All' ?> Orders</h3>
        <span style="color:#888; font-size:.82rem;"><?= count($filtOrders) ?> shown</span>
      </div>
      <div style="padding:0;">
        <table class="mini-table">
          <thead><tr><th>#</th><th>Customer</th><th>Amount</th><th>Payment</th><th>Order Status</th><th>Update</th></tr></thead>
          <tbody>
          <?php foreach ($filtOrders as $o):
            $oc = match(strtolower($o['order_status'])) { 'pending'=>'status-pending','processing'=>'status-processing','ready for delivery'=>'status-ready','delivered'=>'status-delivered','cancelled'=>'status-cancelled',default=>'' };
            $pc = match(strtolower($o['payment_status'])) { 'confirmed'=>'status-confirmed','failed'=>'status-failed','refunded'=>'status-refunded',default=>'status-pending' };
          ?>
          <tr>
            <td>
              <strong style="color:#1565c0;">#<?= (int)$o['id'] ?></strong><br>
              <small style="color:#aaa;"><?= date('d M Y', strtotime($o['created_at'])) ?></small>
            </td>
            <td>
              <strong><?= e($o['full_name'] ?? $o['customer_name']) ?></strong><br>
              <small style="color:#aaa;"><?= e($o['phone']) ?></small><br>
              <small style="color:#aaa;"><?= e(mb_substr($o['delivery_address'],0,35)) ?>…</small>
            </td>
            <td>
              <strong style="color:#1565c0;"><?= money($o['total_amount']) ?></strong><br>
              <small style="color:#aaa; font-size:.72rem;"><?= e($o['payment_method']) ?></small>
            </td>
            <td><span class="status <?= $pc ?>"><?= e($o['payment_status']) ?></span></td>
            <td><span class="status <?= $oc ?>"><?= e($o['order_status']) ?></span></td>
            <td>
              <form method="post" style="display:flex; flex-direction:column; gap:5px; min-width:155px;">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="update_order">
                <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                <select name="order_status" style="font-size:.78rem; padding:5px 8px;">
                  <?php foreach (['Pending','Processing','Ready for delivery','Delivered','Cancelled'] as $s): ?>
                    <option <?= $o['order_status']===$s?'selected':'' ?>><?= e($s) ?></option>
                  <?php endforeach; ?>
                </select>
                <select name="payment_status" style="font-size:.78rem; padding:5px 8px;">
                  <?php foreach (['Pending','Confirmed','Failed','Refunded'] as $s): ?>
                    <option <?= $o['payment_status']===$s?'selected':'' ?>><?= e($s) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn sm" type="submit" style="width:100%;">Save</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$filtOrders): ?><tr><td colspan="6" style="text-align:center; padding:24px; color:#aaa;">No orders found</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php /* ─────────────────── PRODUCTS TAB ─────────────────── */ ?>
    <?php elseif ($tab === 'products'): ?>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
      <div>
        <h1 style="font:800 1.3rem Inter,sans-serif; margin:0 0 4px;">Product & Inventory</h1>
        <p style="color:#888; font-size:.83rem; margin:0;"><?= $totalProducts ?> active products · <?= $lowStock ?> low stock</p>
      </div>
      <a href="?tab=add_product" class="btn sm">➕ Add Product</a>
    </div>

    <div class="dash-section">
      <div class="dash-section-head"><h3>📦 All Products</h3></div>
      <div style="padding:0;">
        <table class="mini-table">
          <thead><tr><th>#</th><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Update Price & Stock</th></tr></thead>
          <tbody>
          <?php foreach ($products as $p): ?>
          <tr>
            <td style="color:#aaa; font-size:.78rem;"><?= (int)$p['id'] ?></td>
            <td>
              <strong><?= e($p['name']) ?></strong>
              <?php if (!(int)$p['active']): ?><span class="status" style="background:#fdeaea;color:#b71c1c;margin-left:4px;font-size:.65rem;">Inactive</span><?php endif; ?>
            </td>
            <td style="font-size:.83rem;"><?= e($p['category']) ?></td>
            <td style="font-weight:700; color:#1565c0;"><?= money($p['price']) ?></td>
            <td>
              <?php $sq = (int)$p['stock_quantity']; ?>
              <span style="background:<?= $sq===0?'#fdeaea':($sq<=5?'#fff3e0':'#e8f7ee') ?>; color:<?= $sq===0?'#b71c1c':($sq<=5?'#b36000':'#1a6b3a') ?>; padding:2px 8px; border-radius:10px; font:700 .75rem Inter,sans-serif;">
                <?= $sq === 0 ? 'Out of stock' : $sq.' in stock' ?>
              </span>
            </td>
            <td>
              <form method="post" class="inline-form" style="gap:6px; flex-wrap:wrap;">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="update_product">
                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                <input type="number" name="price" min="0" step="1" value="<?= e((string)$p['price']) ?>" required style="width:100px; font-size:.82rem; padding:6px 8px;" aria-label="Price">
                <input type="number" name="stock_quantity" min="0" value="<?= $sq ?>" required style="width:70px; font-size:.82rem; padding:6px 8px;" aria-label="Stock">
                <input type="url" name="image_url" value="<?= e($p['image_url'] ?? '') ?>" placeholder="Image URL" style="width:130px; font-size:.78rem; padding:6px 8px;" aria-label="Image URL">
                <button class="btn sm" type="submit">💾</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php /* ─────────────────── ADD PRODUCT TAB ─────────────────── */ ?>
    <?php elseif ($tab === 'add_product'): ?>

    <div style="margin-bottom:20px;">
      <h1 style="font:800 1.3rem Inter,sans-serif; margin:0 0 4px;">Add New Product</h1>
      <p style="color:#888; font-size:.83rem; margin:0;">Fill in the details below to add a product to the catalogue.</p>
    </div>

    <div style="max-width:680px;">
      <div class="dash-section">
        <div class="dash-section-head"><h3>➕ Product Details</h3></div>
        <div class="dash-section-body">
          <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_product">
            <div>
              <label>Product Name *</label>
              <input name="name" required maxlength="160" placeholder="e.g. Maize Seeds — 1 kg">
            </div>
            <div>
              <label>Category *</label>
              <select name="category" required>
                <option value="">— Select category —</option>
                <?php foreach (['Improved Seeds','Fertilizers','Pesticides','Animal Feeds','Veterinary Supplies','Farm Tools'] as $c): ?>
                  <option><?= e($c) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label>Price (UGX) *</label>
              <input type="number" min="0" step="1" name="price" required placeholder="e.g. 6500">
            </div>
            <div>
              <label>Stock Quantity *</label>
              <input type="number" min="0" name="stock_quantity" required placeholder="e.g. 50">
            </div>
            <div class="full">
              <label>Image URL <span style="font-weight:400;color:#aaa;">(optional)</span></label>
              <input type="url" name="image_url" placeholder="https://example.com/image.jpg">
            </div>
            <div class="full">
              <label>Description</label>
              <textarea name="description" rows="3" placeholder="Describe the product, usage, quantity details…"></textarea>
            </div>
            <div class="full" style="display:flex; gap:10px; margin-top:6px;">
              <button class="btn" type="submit" style="padding:11px 28px;">➕ Add Product</button>
              <a class="btn secondary" href="?tab=products">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>

    <?php /* ─────────────────── CUSTOMERS TAB ─────────────────── */ ?>
    <?php elseif ($tab === 'customers'): ?>

    <div style="margin-bottom:20px;">
      <h1 style="font:800 1.3rem Inter,sans-serif; margin:0 0 4px;">Customers</h1>
      <p style="color:#888; font-size:.83rem; margin:0;"><?= $totalCustomers ?> registered customers</p>
    </div>

    <div class="dash-section">
      <div class="dash-section-head"><h3>👥 All Customers</h3></div>
      <div style="padding:0;">
        <table class="mini-table">
          <thead><tr><th>#</th><th>Name</th><th>Contact</th><th>Address</th><th>Orders</th><th>Total Spent</th><th>Joined</th></tr></thead>
          <tbody>
          <?php foreach ($customers as $c): ?>
          <tr>
            <td style="color:#aaa; font-size:.78rem;"><?= (int)$c['id'] ?></td>
            <td>
              <strong><?= e($c['full_name']) ?></strong><br>
              <small style="color:#aaa;"><?= e($c['email']) ?></small>
            </td>
            <td><?= e($c['phone']) ?></td>
            <td><small style="color:#888;"><?= e(mb_substr($c['address'],0,40)) ?>…</small></td>
            <td style="text-align:center; font-weight:700;"><?= (int)$c['order_count'] ?></td>
            <td style="font-weight:700; color:#1565c0;"><?= money($c['total_spent']) ?></td>
            <td style="color:#aaa; font-size:.8rem;"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$customers): ?><tr><td colspan="7" style="text-align:center;padding:24px;color:#aaa;">No customers yet</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php endif; ?>

  </main>
</div><!-- /dash-wrap -->
</div><!-- /container -->

</body>
</html>
