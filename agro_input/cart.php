<?php
require 'config.php';
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $id  = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        $qty = filter_input(INPUT_POST, 'quantity',   FILTER_VALIDATE_INT);
        $stmt = $pdo->prepare("SELECT id, stock_quantity, active FROM products WHERE id=?");
        $stmt->execute([$id]); $p = $stmt->fetch();
        if (!$p || !(int)$p['active'] || !$qty || $qty < 1 || $qty > (int)$p['stock_quantity']) {
            flash('error', 'That quantity is not available. Please check current stock.');
        } else {
            $_SESSION['cart'][$id] = min((int)$p['stock_quantity'], (int)($_SESSION['cart'][$id] ?? 0) + $qty);
            flash('success', 'Product added to your cart.');
        }
    } elseif ($action === 'update') {
        foreach (($_POST['quantities'] ?? []) as $id => $qty) {
            $id  = filter_var($id,  FILTER_VALIDATE_INT);
            $qty = filter_var($qty, FILTER_VALIDATE_INT);
            if (!$id) continue;
            if (!$qty || $qty < 1) { unset($_SESSION['cart'][$id]); continue; }
            $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id=? AND active=1");
            $stmt->execute([$id]); $p = $stmt->fetch();
            if (!$p) unset($_SESSION['cart'][$id]);
            else $_SESSION['cart'][$id] = min((int)$qty, (int)$p['stock_quantity']);
        }
        flash('success', 'Cart updated.');
    } elseif ($action === 'remove') {
        $id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        unset($_SESSION['cart'][$id]);
        flash('success', 'Item removed from your cart.');
    }
    header('Location: cart.php'); exit;
}

$pageTitle = 'Shopping Cart';
$items = []; $total = 0;
if ($_SESSION['cart']) {
    $ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE active=1 AND id IN ($placeholders)");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $p) {
        $qty = min((int)$_SESSION['cart'][$p['id']], (int)$p['stock_quantity']);
        if ($qty < 1) { unset($_SESSION['cart'][$p['id']]); continue; }
        $_SESSION['cart'][$p['id']] = $qty;
        $p['quantity'] = $qty; $p['line_total'] = $qty * (float)$p['price'];
        $total += $p['line_total']; $items[] = $p;
    }
}

$catIcons = ['Improved Seeds'=>'🌽','Fertilizers'=>'🌿','Pesticides'=>'🛡️','Animal Feeds'=>'🐄','Veterinary Supplies'=>'🐾','Farm Tools'=>'🧰'];
require 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
  <a href="index.php">Home</a>
  <span class="sep">›</span>
  <span>Shopping Cart</span>
</div>

<div class="page-top">
  <h1 class="page-title">Shopping Cart</h1>
  <?php if ($items): ?>
    <span class="muted" style="font-size:.88rem;"><?= cart_count() ?> item<?= cart_count() !== 1 ? 's' : '' ?> in your cart</span>
  <?php endif; ?>
</div>

<?php if (!$items): ?>
<!-- Empty cart -->
<div class="empty">
  <div class="empty-icon">🛒</div>
  <h2>Your Cart is Empty</h2>
  <p>Looks like you haven't added any products yet. Browse our catalogue to find the agricultural inputs you need.</p>
  <a class="btn lg" href="products.php">🌾 Browse Products</a>
</div>

<?php else: ?>
<div class="cart-layout">

  <!-- ── Cart items ── -->
  <section>
    <div class="panel" style="padding:20px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h2 style="margin:0;">Cart Items (<?= count($items) ?>)</h2>
        <a href="products.php" style="font-size:.82rem; color:var(--orange);">← Continue Shopping</a>
      </div>

      <form method="post" id="update-cart">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="update">

        <?php foreach ($items as $item):
          $emoji = $catIcons[$item['category']] ?? '🌱';
        ?>
        <div class="cart-item">
          <div class="cart-item-img">
            <?php if (!empty($item['image_url'])): ?>
              <img src="<?= e($item['image_url']) ?>"
                   alt="<?= e($item['name']) ?>"
                   loading="lazy"
                   style="width:100%;height:100%;object-fit:cover;border-radius:4px;"
                   onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
              <span style="display:none;font-size:2.2rem;align-items:center;justify-content:center;width:100%;height:100%;"><?= $emoji ?></span>
            <?php else: ?>
              <?= $emoji ?>
            <?php endif; ?>
          </div>
          <div class="cart-item-info" style="flex:1;">
            <div class="ci-name"><?= e($item['name']) ?></div>
            <div class="ci-cat"><?= $emoji ?> <?= e($item['category']) ?></div>
            <div class="ci-price"><?= money($item['price']) ?> <span style="color:#aaa; font-weight:400; font-size:.8rem;">per unit</span></div>
            <div class="cart-item-actions">
              <div class="qty-ctrl">
                <input type="number" name="quantities[<?= (int)$item['id'] ?>]"
                       min="0" max="<?= (int)$item['stock_quantity'] ?>"
                       value="<?= (int)$item['quantity'] ?>"
                       aria-label="Quantity for <?= e($item['name']) ?>"
                       style="width:60px;">
              </div>
              <button type="submit" class="btn sm secondary">Update</button>
            </div>
          </div>
          <div style="text-align:right; flex-shrink:0;">
            <div style="font:700 1rem Inter,sans-serif; color:var(--orange);"><?= money($item['line_total']) ?></div>
            <div style="color:#aaa; font-size:.75rem; margin-bottom:10px;">
              <?= (int)$item['quantity'] ?> × <?= money($item['price']) ?>
            </div>
            <form method="post" id="remove-<?= (int)$item['id'] ?>">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="remove">
              <input type="hidden" name="product_id" value="<?= (int)$item['id'] ?>">
            </form>
            <button class="btn sm danger" form="remove-<?= (int)$item['id'] ?>" type="submit">🗑 Remove</button>
          </div>
        </div>
        <?php endforeach; ?>

      </form>

      <div style="padding-top:14px; border-top:1px solid var(--line); margin-top:8px; display:flex; gap:10px; flex-wrap:wrap;">
        <a class="btn secondary" href="products.php">← Continue Shopping</a>
      </div>
    </div>
  </section>

  <!-- ── Order summary ── -->
  <aside>
    <div class="summary">
      <h2>Order Summary</h2>

      <?php foreach ($items as $item): ?>
      <div class="summary-line" style="font-size:.83rem;">
        <span style="color:#555;"><?= e($item['name']) ?> <span style="color:#aaa;">×<?= (int)$item['quantity'] ?></span></span>
        <strong><?= money($item['line_total']) ?></strong>
      </div>
      <?php endforeach; ?>

      <div class="summary-line">
        <span>Subtotal</span>
        <strong><?= money($total) ?></strong>
      </div>
      <div class="summary-line">
        <span>Delivery</span>
        <span style="color:var(--green); font-weight:600;">Calculated at checkout</span>
      </div>
      <div class="summary-line summary-total">
        <span>Estimated Total</span>
        <strong style="color:var(--orange);"><?= money($total) ?></strong>
      </div>

      <a class="btn" href="checkout.php" style="font-size:.95rem; padding:13px;">
        🔒 Proceed to Checkout
      </a>
      <p style="font-size:.75rem; color:#aaa; text-align:center; margin-top:10px;">
        🔐 Secure ordering · SSL encrypted
      </p>
    </div>

    <!-- Delivery note -->
    <div class="checkout-note" style="margin-top:12px;">
      🚚 <strong>Delivery note:</strong> Delivery charges, if any, will be confirmed by the dealer after order placement.
    </div>

    <!-- Payment icons -->
    <div class="panel" style="margin-top:12px; padding:14px;">
      <div style="font:600 .8rem Inter,sans-serif; color:#888; margin-bottom:10px; text-align:center;">ACCEPTED PAYMENT METHODS</div>
      <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap;">
        <span class="footer-badge" style="font-size:.8rem;">💰 Cash on Delivery</span>
        <span class="footer-badge" style="font-size:.8rem;">📱 Mobile Money</span>
        <span class="footer-badge" style="font-size:.8rem;">🏦 Bank Transfer</span>
      </div>
    </div>
  </aside>

</div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
