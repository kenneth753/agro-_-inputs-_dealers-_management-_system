<?php
require 'config.php'; require_login();
$id   = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id=? AND user_id=?");
$stmt->execute([$id, current_user()['id']]);
$order = $stmt->fetch();
if (!$order) { http_response_code(404); exit('Order not found.'); }
$pageTitle = 'Order Placed — #' . (int)$order['id'];
require 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
  <a href="index.php">Home</a>
  <span class="sep">›</span>
  <a href="my_orders.php">My Orders</a>
  <span class="sep">›</span>
  <span>Order #<?= (int)$order['id'] ?></span>
</div>

<!-- Steps indicator (completed) -->
<div style="display:flex; align-items:center; gap:0; margin-bottom:28px; background:#fff; border:1px solid var(--line); border-radius:6px; overflow:hidden;">
  <div style="flex:1; padding:14px; text-align:center; border-right:1px solid var(--line);">
    <div style="font:700 .8rem Inter,sans-serif; color:var(--green);">✅ Step 1</div>
    <div style="font-size:.83rem; color:#888;">Shopping Cart</div>
  </div>
  <div style="flex:1; padding:14px; text-align:center; border-right:1px solid var(--line);">
    <div style="font:700 .8rem Inter,sans-serif; color:var(--green);">✅ Step 2</div>
    <div style="font-size:.83rem; color:#888;">Checkout</div>
  </div>
  <div style="flex:1; padding:14px; text-align:center; background:#e8f7ee;">
    <div style="font:700 .8rem Inter,sans-serif; color:var(--green);">✅ Step 3</div>
    <div style="font-size:.83rem; font-weight:600; color:var(--green-dk);">Order Confirmed</div>
  </div>
</div>

<div style="max-width:640px; margin:0 auto;">
  <div class="panel" style="text-align:center; padding:44px 32px;">
    <div style="font-size:4.5rem; margin-bottom:16px;">🎉</div>
    <h1 style="font:700 1.6rem Inter,sans-serif; color:var(--green-dk); margin-bottom:8px;">
      Thank you! Your order has been placed.
    </h1>
    <p class="muted" style="margin-bottom:24px; font-size:.95rem;">
      We've received your order and will process it shortly. You'll be notified via SMS with updates.
    </p>

    <!-- Order details card -->
    <div style="background:#f8f8f8; border:1px solid var(--line); border-radius:6px; padding:20px; text-align:left; margin-bottom:24px;">
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
        <div>
          <div style="font:600 .75rem Inter,sans-serif; color:#aaa; text-transform:uppercase; letter-spacing:.04em; margin-bottom:4px;">Order Number</div>
          <div style="font:700 1.1rem Inter,sans-serif; color:var(--orange);">#<?= (int)$order['id'] ?></div>
        </div>
        <div>
          <div style="font:600 .75rem Inter,sans-serif; color:#aaa; text-transform:uppercase; letter-spacing:.04em; margin-bottom:4px;">Order Total</div>
          <div style="font:700 1.1rem Inter,sans-serif;"><?= money($order['total_amount']) ?></div>
        </div>
        <div>
          <div style="font:600 .75rem Inter,sans-serif; color:#aaa; text-transform:uppercase; letter-spacing:.04em; margin-bottom:4px;">Order Status</div>
          <span class="status status-pending"><?= e($order['order_status']) ?></span>
        </div>
        <div>
          <div style="font:600 .75rem Inter,sans-serif; color:#aaa; text-transform:uppercase; letter-spacing:.04em; margin-bottom:4px;">Payment Status</div>
          <span class="status status-pending"><?= e($order['payment_status']) ?></span>
        </div>
        <div class="full" style="grid-column:1/-1;">
          <div style="font:600 .75rem Inter,sans-serif; color:#aaa; text-transform:uppercase; letter-spacing:.04em; margin-bottom:4px;">Payment Method</div>
          <div style="font-size:.88rem;"><?= e($order['payment_method']) ?></div>
        </div>
      </div>
    </div>

    <div class="note" style="text-align:left; margin-bottom:24px;">
      ℹ️ Our staff will review and confirm your payment. Order status will be updated as it progresses. You will receive an SMS notification at every step.
    </div>

    <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
      <a class="btn lg" href="my_orders.php">📦 View My Orders</a>
      <a class="btn secondary lg" href="products.php">🌾 Continue Shopping</a>
    </div>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
