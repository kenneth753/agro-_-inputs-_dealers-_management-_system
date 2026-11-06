<?php
require 'config.php'; require_login();
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC");
$stmt->execute([current_user()['id']]);
$orders = $stmt->fetchAll();
$pageTitle = 'My Orders';
require 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
  <a href="index.php">Home</a>
  <span class="sep">›</span>
  <span>My Orders</span>
</div>

<div class="page-top">
  <h1 class="page-title">My Orders</h1>
  <?php if ($orders): ?>
    <span class="muted"><?= count($orders) ?> order<?= count($orders) !== 1 ? 's' : '' ?> placed</span>
  <?php endif; ?>
</div>

<?php if (!$orders): ?>
<div class="empty">
  <div class="empty-icon">📦</div>
  <h2>No Orders Yet</h2>
  <p>You haven't placed any orders yet. Start shopping to see your orders here.</p>
  <a class="btn lg" href="products.php">🌾 Browse Products</a>
</div>

<?php else: ?>
<?php foreach ($orders as $o):
  $statusClass = match(strtolower($o['order_status'])) {
    'pending'  => 'status-pending',
    'processing' => 'status-processing',
    'ready for delivery' => 'status-ready',
    'delivered'  => 'status-delivered',
    'cancelled'  => 'status-cancelled',
    default => ''
  };
  $payClass = match(strtolower($o['payment_status'])) {
    'confirmed' => 'status-confirmed',
    'failed'    => 'status-failed',
    'refunded'  => 'status-refunded',
    default => 'status-pending'
  };
  $it = $pdo->prepare("SELECT * FROM order_items WHERE order_id=?");
  $it->execute([$o['id']]);
  $orderItems = $it->fetchAll();
?>
<div class="order-panel">
  <div class="order-panel-head">
    <div>
      <h3>Order <span style="color:var(--orange);">#<?= (int)$o['id'] ?></span></h3>
      <small class="muted"><?= date('D, d M Y · H:i', strtotime($o['created_at'])) ?></small>
    </div>
    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
      <span class="status <?= $statusClass ?>">📦 <?= e($o['order_status']) ?></span>
      <span class="status <?= $payClass ?>">💳 <?= e($o['payment_status']) ?></span>
      <strong style="color:var(--orange);"><?= money($o['total_amount']) ?></strong>
    </div>
  </div>
  <div class="order-panel-body">

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:16px; background:#fafafa; border-radius:5px; padding:12px; border:1px solid var(--line);">
      <div>
        <div style="font:600 .75rem Inter,sans-serif; color:#aaa; text-transform:uppercase; letter-spacing:.04em; margin-bottom:3px;">Delivery Address</div>
        <div style="font-size:.85rem;"><?= e($o['delivery_address']) ?></div>
      </div>
      <div>
        <div style="font:600 .75rem Inter,sans-serif; color:#aaa; text-transform:uppercase; letter-spacing:.04em; margin-bottom:3px;">Payment Method</div>
        <div style="font-size:.85rem;"><?= e($o['payment_method']) ?></div>
      </div>
      <?php if ($o['customer_note']): ?>
      <div style="grid-column:1/-1;">
        <div style="font:600 .75rem Inter,sans-serif; color:#aaa; text-transform:uppercase; letter-spacing:.04em; margin-bottom:3px;">Order Note</div>
        <div style="font-size:.85rem; color:#666;"><?= e($o['customer_note']) ?></div>
      </div>
      <?php endif; ?>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Product</th>
            <th style="text-align:center;">Qty</th>
            <th style="text-align:right;">Unit Price</th>
            <th style="text-align:right;">Subtotal</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orderItems as $line): ?>
          <tr>
            <td><strong><?= e($line['product_name']) ?></strong></td>
            <td style="text-align:center; font-weight:600;"><?= (int)$line['quantity'] ?></td>
            <td style="text-align:right;"><?= money($line['unit_price']) ?></td>
            <td style="text-align:right; color:var(--orange); font-weight:700;"><?= money($line['line_total']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="3" style="text-align:right; font-weight:700; padding-top:10px;">Order Total</td>
            <td style="text-align:right; color:var(--orange); font:700 1rem Inter,sans-serif; padding-top:10px;"><?= money($o['total_amount']) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>

  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
