<?php
require 'config.php';
$pageTitle = 'Home';
$featured = $pdo->query("SELECT * FROM products WHERE active=1 ORDER BY created_at DESC LIMIT 10")->fetchAll();
$categories = [
    'Improved Seeds'      => ['icon'=>'🌽', 'desc'=>'High-yield varieties'],
    'Fertilizers'         => ['icon'=>'🌿', 'desc'=>'Soil nutrients & growth'],
    'Pesticides'          => ['icon'=>'🛡️', 'desc'=>'Crop protection'],
    'Animal Feeds'        => ['icon'=>'🐄', 'desc'=>'Livestock nutrition'],
    'Veterinary Supplies' => ['icon'=>'🐾', 'desc'=>'Animal health care'],
    'Farm Tools'          => ['icon'=>'🧰', 'desc'=>'Equipment & tools'],
];
$catIcons = ['Improved Seeds'=>'🌽','Fertilizers'=>'🌿','Pesticides'=>'🛡️','Animal Feeds'=>'🐄','Veterinary Supplies'=>'🐾','Farm Tools'=>'🧰'];
require 'includes/header.php';
?>

<!-- ── Hero Banner ── -->
<div class="hero-banner">
  <div class="hero-copy">
    <div class="hero-eyebrow">🌾 Fort Portal's #1 Agro Input Store</div>
    <h1>Quality Farm Inputs,<br>Delivered to You</h1>
    <p>Shop seeds, fertilizers, crop protection, animal feeds, veterinary supplies and farm tools — all in one place. Serving farmers across Fort Portal and the surrounding region.</p>
    <div class="hero-actions">
      <a class="btn lg" href="products.php">🛒 Shop Now</a>
      <a class="btn secondary lg" href="register.php" style="color:#fff; background:rgba(255,255,255,.15); border-color:rgba(255,255,255,.4);">Create Free Account</a>
    </div>
  </div>
  <div class="hero-art">🌱🌾</div>
</div>

<!-- ── Trust strip ── -->
<div class="trust-strip" style="margin-top:0; margin-bottom:24px;">
  <div class="trust-item"><span class="t-icon">🚚</span><span>Fast Local Delivery</span></div>
  <div class="trust-item"><span class="t-icon">✅</span><span>100% Genuine Products</span></div>
  <div class="trust-item"><span class="t-icon">💳</span><span>Flexible Payment Options</span></div>
  <div class="trust-item"><span class="t-icon">🔒</span><span>Secure Online Ordering</span></div>
  <div class="trust-item"><span class="t-icon">📞</span><span>Expert Agro Support</span></div>
</div>

<!-- ── Categories ── -->
<div class="section-head">
  <h2>Shop by Category</h2>
  <a class="view-all" href="products.php">View all →</a>
</div>
<div class="category-grid">
  <?php foreach ($categories as $name => $cat): ?>
  <a class="category-tile" href="products.php?category=<?= urlencode($name) ?>">
    <span class="cat-icon"><?= $cat['icon'] ?></span>
    <strong><?= e($name) ?></strong>
    <small><?= e($cat['desc']) ?></small>
  </a>
  <?php endforeach; ?>
</div>

<!-- ── Flash sale banner ── -->
<div style="background:linear-gradient(90deg,#0d47a1,#1565c0); border-radius:6px; padding:18px 28px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px; margin-bottom:28px;">
  <div>
    <div style="color:#bbdefb; font:700 .75rem Inter,sans-serif; letter-spacing:.08em; text-transform:uppercase; margin-bottom:4px;">⚡ Deals of the Season</div>
    <div style="color:#fff; font:700 1.3rem Inter,sans-serif;">Special Prices on Selected Inputs</div>
    <div style="color:rgba(255,255,255,.8); font-size:.88rem; margin-top:4px;">Limited stock — order while supplies last!</div>
  </div>
  <a class="btn" href="products.php" style="background:#fff; color:#1565c0; font-weight:800;">Shop Deals →</a>
</div>

<!-- ── Featured Products ── -->
<div class="section-head">
  <h2>Featured Products</h2>
  <a class="view-all" href="products.php">View all products →</a>
</div>
<div class="grid">
<?php foreach ($featured as $i => $p):
  $emoji = $catIcons[$p['category']] ?? '🌱';
  $badges = [];
  if ($i < 2) $badges[] = '<span class="badge new">NEW</span>';
  if ($i === 2) $badges[] = '<span class="badge hot">HOT</span>';
  if ($i === 3) $badges[] = '<span class="badge deal">DEAL</span>';
  // fake star rating for display
  $stars = 4 + ($p['id'] % 2 == 0 ? 0.5 : 0);
  $fullStars = floor($stars); $halfStar = ($stars - $fullStars) >= 0.5;
  $reviews = 12 + ($p['id'] * 7 % 88);
?>
<article class="product-card">
  <?php if ($badges): ?><div class="badge-wrap"><?= implode('', $badges) ?></div><?php endif; ?>
  <button class="wishlist-btn" title="Add to wishlist" type="button">♡</button>

  <div class="product-image" style="height:190px; overflow:hidden; background:#f5f7f5;">
    <?php if (!empty($p['image_url'])): ?>
      <img src="<?= e($p['image_url']) ?>"
           alt="<?= e($p['name']) ?>"
           loading="lazy"
           style="width:100%; height:100%; object-fit:cover; transition:transform .3s;"
           onmouseover="this.style.transform='scale(1.05)'"
           onmouseout="this.style.transform='scale(1)'"
           onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
      <div style="display:none; width:100%; height:100%; align-items:center; justify-content:center; font-size:4rem;"><?= $emoji ?></div>
    <?php else: ?>
      <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; font-size:4rem;"><?= $emoji ?></div>
    <?php endif; ?>
  </div>

  <div class="product-body">
    <div class="p-category"><?= e($p['category']) ?></div>
    <h3><?= e($p['name']) ?></h3>

    <div class="stars">
      <?php for ($s = 1; $s <= 5; $s++): ?>
        <span class="star-icon"><?= $s <= $fullStars ? '★' : ($halfStar && $s == $fullStars+1 ? '⯨' : '☆') ?></span>
      <?php endfor; ?>
      <span class="rating-count">(<?= $reviews ?>)</span>
    </div>

    <div class="price-wrap">
      <span class="price"><?= money($p['price']) ?></span>
    </div>

    <div class="stock">
      <?php if ((int)$p['stock_quantity'] > 0): ?>
        <span class="in-stock">✔ In Stock</span> · <?= (int)$p['stock_quantity'] ?> available
      <?php else: ?>
        <span class="out-stock">✘ Out of Stock</span>
      <?php endif; ?>
    </div>

    <?php if ((int)$p['stock_quantity'] > 0): ?>
    <form method="post" action="cart.php" class="add-form">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
      <input class="qty" type="number" name="quantity" min="1" max="<?= (int)$p['stock_quantity'] ?>" value="1" aria-label="Quantity">
      <button class="btn" type="submit">🛒 Add to Cart</button>
    </form>
    <?php else: ?>
    <button class="btn secondary" disabled>Out of Stock</button>
    <?php endif; ?>

    <span class="free-delivery">🚚 Delivery available</span>
  </div>
</article>
<?php endforeach; ?>
</div>

<!-- ── Bottom promo banner ── -->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin:32px 0 10px;">
  <div style="background:linear-gradient(110deg,#1a3a1a,#2d6a2d); border-radius:6px; padding:28px 26px; position:relative; overflow:hidden;">
    <div style="color:#8ddb8d; font:700 .75rem Inter,sans-serif; letter-spacing:.08em; text-transform:uppercase; margin-bottom:6px;">SEASON ESSENTIALS</div>
    <div style="color:#fff; font:700 1.25rem Inter,sans-serif; margin-bottom:8px;">Seeds &amp; Fertilizers</div>
    <p style="color:rgba(255,255,255,.7); font-size:.85rem; margin-bottom:16px;">Get the best yields with our quality improved seeds and balanced fertilizers.</p>
    <a href="products.php?category=Improved+Seeds" class="btn sm" style="background:#8ddb8d; color:#1a3a1a;">Shop Seeds</a>
    <div style="position:absolute; right:20px; bottom:10px; font-size:4rem; opacity:.3;">🌽</div>
  </div>
  <div style="background:linear-gradient(110deg,#1a2a4a,#1e3a6e); border-radius:6px; padding:28px 26px; position:relative; overflow:hidden;">
    <div style="color:#a0c4ff; font:700 .75rem Inter,sans-serif; letter-spacing:.08em; text-transform:uppercase; margin-bottom:6px;">ANIMAL CARE</div>
    <div style="color:#fff; font:700 1.25rem Inter,sans-serif; margin-bottom:8px;">Feeds &amp; Veterinary</div>
    <p style="color:rgba(255,255,255,.7); font-size:.85rem; margin-bottom:16px;">Keep your livestock healthy and productive with our range of quality feeds and supplies.</p>
    <a href="products.php?category=Animal+Feeds" class="btn sm" style="background:#a0c4ff; color:#1a2a4a;">Shop Now</a>
    <div style="position:absolute; right:20px; bottom:10px; font-size:4rem; opacity:.3;">🐄</div>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
