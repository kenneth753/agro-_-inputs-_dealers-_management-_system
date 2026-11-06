<?php
require 'config.php'; require_login();
$cart = $_SESSION['cart'] ?? [];
if (!$cart) {
    flash('error','Your cart is empty.');
    header('Location: cart.php'); exit;
}
$ids = array_keys($cart);
$ph = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM products WHERE active=1 AND id IN ($ph)");
$stmt->execute($ids); $products = $stmt->fetchAll();
$items = []; $total = 0;
foreach ($products as $p) {
    $qty = (int)($cart[$p['id']] ?? 0);
    if ($qty < 1 || $qty > (int)$p['stock_quantity']) continue;
    $p['quantity'] = $qty;
    $p['line_total'] = $qty * (float)$p['price'];
    $items[] = $p; $total += $p['line_total'];
}
if (!$items) {
    $_SESSION['cart'] = [];
    flash('error','Your cart items are no longer available. Please shop again.');
    header('Location: products.php'); exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name    = trim($_POST['customer_name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['delivery_address'] ?? '');
    $method  = $_POST['payment_method'] ?? '';
    $note    = trim($_POST['customer_note'] ?? '');
    $methods = ['Cash on delivery','Mobile Money - manual confirmation','Bank transfer - manual confirmation'];
    if ($name === '')                          $errors[] = 'Enter the recipient name.';
    if ($phone === '')                         $errors[] = 'Enter a contact phone number.';
    if ($address === '')                       $errors[] = 'Enter the delivery address.';
    if (!in_array($method, $methods, true))   $errors[] = 'Select a payment method.';

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $total = 0; $fresh = [];
            foreach ($items as $item) {
                $check = $pdo->prepare("SELECT * FROM products WHERE id=? AND active=1 FOR UPDATE");
                $check->execute([$item['id']]); $p = $check->fetch();
                if (!$p || (int)$p['stock_quantity'] < (int)$item['quantity'])
                    throw new RuntimeException('Stock changed while you were checking out. Please review your cart.');
                $p['quantity']   = (int)$item['quantity'];
                $p['line_total'] = $p['quantity'] * (float)$p['price'];
                $total += $p['line_total']; $fresh[] = $p;
            }
            $ins = $pdo->prepare("INSERT INTO orders(user_id,customer_name,phone,delivery_address,payment_method,total_amount,customer_note) VALUES(?,?,?,?,?,?,?)");
            $ins->execute([current_user()['id'],$name,$phone,$address,$method,$total,$note]);
            $orderId = (int)$pdo->lastInsertId();
            $line  = $pdo->prepare("INSERT INTO order_items(order_id,product_id,product_name,quantity,unit_price,line_total) VALUES(?,?,?,?,?,?)");
            $stock = $pdo->prepare("UPDATE products SET stock_quantity=stock_quantity-? WHERE id=?");
            foreach ($fresh as $p) {
                $line->execute([$orderId,$p['id'],$p['name'],$p['quantity'],$p['price'],$p['line_total']]);
                $stock->execute([$p['quantity'],$p['id']]);
            }
            $pdo->commit();
            require_once 'includes/sms.php';
            $smsMsg = 'Agro Input: Order #'.$orderId.' received. Total UGX '.number_format($total,0).'. Payment: '.$method.'. Status: Pending. We will update you when confirmed.';
            send_sms($phone, $smsMsg);
            $_SESSION['cart'] = [];
            header('Location: order_success.php?id='.$orderId); exit;
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $ex instanceof RuntimeException ? $ex->getMessage() : 'Could not place the order. Please try again.';
        }
    }
}

$catIcons = ['Improved Seeds'=>'🌽','Fertilizers'=>'🌿','Pesticides'=>'🛡️','Animal Feeds'=>'🐄','Veterinary Supplies'=>'🐾','Farm Tools'=>'🧰'];
$pageTitle = 'Checkout'; require 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
  <a href="index.php">Home</a>
  <span class="sep">›</span>
  <a href="cart.php">Cart</a>
  <span class="sep">›</span>
  <span>Checkout</span>
</div>

<!-- Steps indicator -->
<div style="display:flex; align-items:center; gap:0; margin-bottom:24px; background:#fff; border:1px solid var(--line); border-radius:6px; overflow:hidden;">
  <div style="flex:1; padding:14px; text-align:center; background:#e3f2fd; border-right:1px solid var(--line);">
    <div style="font:700 .8rem Inter,sans-serif; color:var(--orange);">✅ Step 1</div>
    <div style="font-size:.83rem; color:#888;">Shopping Cart</div>
  </div>
  <div style="flex:1; padding:14px; text-align:center; background:#e3f2fd; border-right:1px solid var(--line);">
    <div style="font:700 .8rem Inter,sans-serif; color:var(--orange);">▶ Step 2</div>
    <div style="font-size:.83rem; font-weight:600; color:var(--ink);">Checkout Details</div>
  </div>
  <div style="flex:1; padding:14px; text-align:center; background:#fff;">
    <div style="font:700 .8rem Inter,sans-serif; color:#bbb;">Step 3</div>
    <div style="font-size:.83rem; color:#bbb;">Order Confirmed</div>
  </div>
</div>

<?php foreach ($errors as $err): ?>
  <div class="alert error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="cart-layout">

  <!-- ── Checkout form ── -->
  <section>
    <div class="panel">
      <h2>📋 Delivery &amp; Payment Details</h2>

      <form method="post" id="checkout-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <!-- Delivery info -->
        <div style="background:#fafafa; border:1px solid var(--line); border-radius:6px; padding:18px; margin-bottom:18px;">
          <div style="font:700 .9rem Inter,sans-serif; margin-bottom:14px; display:flex; align-items:center; gap:8px;">
            <span>🚚</span> Delivery Information
          </div>
          <div class="form-grid">
            <div class="full">
              <label for="customer_name">Recipient Full Name *</label>
              <input id="customer_name" name="customer_name" required placeholder="e.g. John Mukasa"
                     value="<?= e($_POST['customer_name'] ?? current_user()['full_name']) ?>">
            </div>
            <div>
              <label for="phone">Phone Number *</label>
              <input id="phone" name="phone" required placeholder="e.g. 0772 123456"
                     value="<?= e($_POST['phone'] ?? current_user()['phone']) ?>">
            </div>
            <div class="full">
              <label for="delivery_address">Delivery Address *</label>
              <textarea id="delivery_address" name="delivery_address" rows="3" required
                        placeholder="Street, area, town / village, district…"><?= e($_POST['delivery_address'] ?? current_user()['address']) ?></textarea>
            </div>
          </div>
        </div>

        <!-- Payment method -->
        <div style="background:#fafafa; border:1px solid var(--line); border-radius:6px; padding:18px; margin-bottom:18px;">
          <div style="font:700 .9rem Inter,sans-serif; margin-bottom:14px; display:flex; align-items:center; gap:8px;">
            <span>💳</span> Payment Method
          </div>

          <?php
          $methodList = ['Cash on delivery','Mobile Money - manual confirmation','Bank transfer - manual confirmation'];
          $methodIcons = ['Cash on delivery'=>'💰','Mobile Money - manual confirmation'=>'📱','Bank transfer - manual confirmation'=>'🏦'];
          $methodDesc = [
            'Cash on delivery' => 'Pay when your order is delivered.',
            'Mobile Money - manual confirmation' => 'Pay via MTN or Airtel Mobile Money. Staff will confirm receipt.',
            'Bank transfer - manual confirmation' => 'Transfer to our bank account. Staff will confirm receipt.',
          ];
          foreach ($methodList as $m):
            $checked = (($_POST['payment_method'] ?? '') === $m);
          ?>
          <label style="display:flex; align-items:flex-start; gap:12px; padding:12px; border:2px solid <?= $checked ? 'var(--orange)' : 'var(--line)' ?>; border-radius:6px; margin-bottom:8px; cursor:pointer; background:<?= $checked ? 'var(--orange-lt)' : '#fff' ?>;">
            <input type="radio" name="payment_method" value="<?= e($m) ?>"
                   <?= $checked ? 'checked' : '' ?>
                   style="width:auto; margin-top:3px; accent-color:var(--orange);"
                   required>
            <div>
              <div style="font:600 .88rem Inter,sans-serif;"><?= $methodIcons[$m] ?> <?= e($m) ?></div>
              <div style="font-size:.78rem; color:#888; margin-top:2px;"><?= e($methodDesc[$m]) ?></div>
            </div>
          </label>
          <?php endforeach; ?>

    <div class="checkout-note" style="margin-top:10px;">
      ℹ️ This system records payment as <strong>Pending</strong>. It does not process actual Mobile Money or bank payments — staff confirm payment manually.
    </div>
        </div>

        <!-- Order note -->
        <div style="background:#fafafa; border:1px solid var(--line); border-radius:6px; padding:18px; margin-bottom:20px;">
          <div style="font:700 .9rem Inter,sans-serif; margin-bottom:10px;">📝 Order Note (Optional)</div>
          <textarea name="customer_note" rows="3" placeholder="Any delivery directions, special requests or comments…"><?= e($_POST['customer_note'] ?? '') ?></textarea>
        </div>

        <button class="btn lg" type="submit" style="width:100%; font-size:1rem;">
          🔒 Place Order · <?= money($total) ?>
        </button>
        <p style="text-align:center; font-size:.78rem; color:#aaa; margin-top:8px;">
          By placing this order you agree to our Terms &amp; Conditions.
        </p>
      </form>
    </div>
  </section>

  <!-- ── Order summary ── -->
  <aside>
    <div class="summary">
      <h2>🧾 Order Summary</h2>

      <?php foreach ($items as $item):
        $emoji = $catIcons[$item['category']] ?? '🌱';
      ?>
      <div class="cart-item" style="padding:10px 0; gap:10px;">
        <div class="cart-item-img" style="width:52px; height:52px; font-size:1.6rem; overflow:hidden;">
          <?php if (!empty($item['image_url'])): ?>
            <img src="<?= e($item['image_url']) ?>"
                 alt="<?= e($item['name']) ?>"
                 loading="lazy"
                 style="width:100%;height:100%;object-fit:cover;border-radius:4px;"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
            <span style="display:none;font-size:1.6rem;align-items:center;justify-content:center;width:100%;height:100%;"><?= $emoji ?></span>
          <?php else: ?>
            <?= $emoji ?>
          <?php endif; ?>
        </div>
        <div style="flex:1;">
          <div style="font:600 .83rem Inter,sans-serif; color:var(--ink);"><?= e($item['name']) ?></div>
          <div style="font-size:.75rem; color:#aaa;">Qty: <?= (int)$item['quantity'] ?> × <?= money($item['price']) ?></div>
        </div>
        <strong style="color:var(--orange); font-size:.9rem;"><?= money($item['line_total']) ?></strong>
      </div>
      <?php endforeach; ?>

      <div class="summary-line">
        <span>Subtotal</span>
        <strong><?= money($total) ?></strong>
      </div>
      <div class="summary-line">
        <span>Delivery fee</span>
        <span style="color:var(--green); font-weight:600; font-size:.83rem;">Confirmed by dealer</span>
      </div>
      <div class="summary-line summary-total">
        <span>Total</span>
        <strong style="color:var(--orange); font-size:1.15rem;"><?= money($total) ?></strong>
      </div>

      <button class="btn" form="checkout-form" type="submit" style="width:100%; padding:13px; margin-top:10px;">
        Place Order
      </button>
    </div>

    <div class="checkout-note" style="margin-top:12px;">
      🔒 <strong>Secure order.</strong> Your information is safe with us. Payment is confirmed by our staff after review.
    </div>
  </aside>

</div>

<?php require 'includes/footer.php'; ?>
