<?php
require 'config.php';
$pageTitle = 'Products';
$q = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$allowed = ['Improved Seeds','Fertilizers','Pesticides','Animal Feeds','Veterinary Supplies','Farm Tools'];
$sql = "SELECT * FROM products WHERE active=1";
$params = [];
if ($q !== '') {
    $sql .= " AND (name LIKE ? OR description LIKE ?)";
    $params[] = "%$q%"; $params[] = "%$q%";
}
if (in_array($category, $allowed, true)) {
    $sql .= " AND category=?";
    $params[] = $category;
}
$sql .= " ORDER BY category, name";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$products = $stmt->fetchAll();

$catIcons = ['Improved Seeds'=>'🌽','Fertilizers'=>'🌿','Pesticides'=>'🛡️','Animal Feeds'=>'🐄','Veterinary Supplies'=>'🐾','Farm Tools'=>'🧰'];
require 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
  <a href="index.php">Home</a>
  <span class="sep">›</span>
  <?php if ($category): ?>
    <a href="products.php">Products</a>
    <span class="sep">›</span>
    <span><?= e($category) ?></span>
  <?php elseif ($q): ?>
    <a href="products.php">Products</a>
    <span class="sep">›</span>
    <span>Search: "<?= e($q) ?>"</span>
  <?php else: ?>
    <span>All Products</span>
  <?php endif; ?>
</div>

<div class="listing-header">
  <div>
    <h1 class="page-title"><?= $category ? e($category) : 'Agricultural Inputs' ?></h1>
    <p class="muted" style="margin-top:8px;"><?= count($products) ?> product<?= count($products) !== 1 ? 's' : '' ?> found<?= $q ? ' for "'.e($q).'"' : '' ?></p>
  </div>
</div>

<!-- ── Filters ── -->
<div class="filters-bar">
  <form method="get" class="searchbar" role="search">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search products…" aria-label="Search">
    <?php if ($category): ?><input type="hidden" name="category" value="<?= e($category) ?>"><?php endif; ?>
    <button class="btn" type="submit">🔍 Search</button>
  </form>

  <form method="get" id="catForm">
    <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
    <select name="category" onchange="document.getElementById('catForm').submit()" aria-label="Filter by category">
      <option value="">All Categories</option>
      <?php foreach ($allowed as $c): ?>
        <option value="<?= e($c) ?>" <?= $category === $c ? 'selected' : '' ?>>
          <?= $catIcons[$c] ?? '' ?> <?= e($c) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>

  <?php if ($q !== '' || $category !== ''): ?>
    <a class="btn secondary" href="products.php">✕ Clear</a>
  <?php endif; ?>
</div>

<!-- ── Category pills ── -->
<div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:18px;">
  <a href="products.php" style="display:inline-block; padding:5px 14px; border-radius:20px; font-size:.82rem; font-weight:600; border:1.5px solid <?= $category==='' ? '#1565c0' : '#ddd' ?>; background:<?= $category==='' ? '#e3f2fd' : '#fff' ?>; color:<?= $category==='' ? '#0d47a1' : '#444' ?>; text-decoration:none;">All</a>
  <?php foreach ($allowed as $c): ?>
    <a href="products.php?category=<?= urlencode($c) ?><?= $q ? '&q='.urlencode($q) : '' ?>"
       style="display:inline-block; padding:5px 14px; border-radius:20px; font-size:.82rem; font-weight:600; border:1.5px solid <?= $category===$c ? '#1565c0' : '#ddd' ?>; background:<?= $category===$c ? '#e3f2fd' : '#fff' ?>; color:<?= $category===$c ? '#0d47a1' : '#444' ?>; text-decoration:none;">
      <?= $catIcons[$c] ?? '' ?> <?= e($c) ?>
    </a>
  <?php endforeach; ?>
</div>

<!-- ── Product grid ── -->
<?php if (!$products): ?>
<div class="empty">
  <div class="empty-icon">🔍</div>
  <h2>No products found</h2>
  <p>We couldn't find products matching your search. Try different keywords or browse all categories.</p>
  <a class="btn" href="products.php">View All Products</a>
</div>
<?php else: ?>
<div class="grid">
<?php foreach ($products as $i => $p):
  $emoji = $catIcons[$p['category']] ?? '🌱';
  $stars = 4 + ($p['id'] % 2 == 0 ? 0.5 : 0);
  $fullStars = floor($stars); $halfStar = ($stars - $fullStars) >= 0.5;
  $reviews = 10 + ($p['id'] * 9 % 90);
  $isNew = $i < 3;
?>
<article class="product-card">
  <?php if ($isNew): ?><div class="badge-wrap"><span class="badge new">NEW</span></div><?php endif; ?>
  <?php if ((int)$p['stock_quantity'] === 0): ?><div class="badge-wrap"><span class="badge" style="background:#888;">OUT OF STOCK</span></div><?php endif; ?>
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

    <?php if (!empty($p['description'])): ?>
    <p style="font-size:.78rem; color:#888; margin:0 0 6px; line-height:1.4; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;"><?= e($p['description']) ?></p>
    <?php endif; ?>

    <div class="price-wrap">
      <span class="price"><?= money($p['price']) ?></span>
    </div>

    <div class="stock">
      <?php if ((int)$p['stock_quantity'] > 0): ?>
        <span class="in-stock">✔ In Stock</span>
        <span style="color:#aaa;"> · <?= (int)$p['stock_quantity'] ?> available</span>
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
    <button class="btn secondary" disabled style="width:100%; margin-top:auto;">Out of Stock</button>
    <?php endif; ?>

    <span class="free-delivery">🚚 Delivery available</span>
  </div>
</article>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
