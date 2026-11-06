<?php
require 'config.php';

// Check 1: What prices are in DB right now?
$products = $pdo->query("SELECT id, name, price, image_url, stock_quantity FROM products ORDER BY id")->fetchAll();

// Check 2: Do image files exist locally?
// Check 3: Can we reach Unsplash?
$canInternet = @file_get_contents('https://httpbin.org/get', false, stream_context_create(['http'=>['timeout'=>4,'ssl'=>['verify_peer'=>false]],'ssl'=>['verify_peer'=>false]]));
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Diagnostics</title>
<style>
  body{font-family:monospace;background:#111;color:#0f0;padding:20px;}
  h2{color:#4af;margin:20px 0 10px;}
  table{border-collapse:collapse;width:100%;margin-bottom:20px;}
  th{background:#222;color:#aaa;padding:8px 12px;text-align:left;font-size:.8rem;}
  td{padding:8px 12px;border-bottom:1px solid #222;font-size:.82rem;}
  .ok{color:#3f3;font-weight:700;}
  .fail{color:#f44;font-weight:700;}
  .warn{color:#fa0;font-weight:700;}
  .big{font-size:1.1rem;padding:14px;background:#222;border-radius:6px;margin:10px 0;}
  a{color:#4af;}
</style>
</head>
<body>

<h2>🔍 DIAGNOSTIC REPORT</h2>

<!-- Internet check -->
<div class="big">
  Internet: <?= $canInternet ? '<span class="ok">✅ CONNECTED — images can be downloaded</span>' : '<span class="fail">❌ NO INTERNET — cannot download from Unsplash</span>' ?>
</div>

<!-- Uploads folder check -->
<?php
$uploadDir = __DIR__ . '/uploads/products/';
$dirExists = is_dir($uploadDir);
$dirWritable = $dirExists && is_writable($uploadDir);
if (!$dirExists) mkdir($uploadDir, 0755, true);
?>
<div class="big">
  uploads/products/ folder: 
  <?= $dirExists ? '<span class="ok">✅ EXISTS</span>' : '<span class="fail">❌ MISSING — creating now...</span>' ?>
  &nbsp;|&nbsp;
  Writable: <?= $dirWritable ? '<span class="ok">✅ YES</span>' : '<span class="fail">❌ NO — permission issue!</span>' ?>
</div>

<!-- Products in DB -->
<h2>📦 Products in Database (<?= count($products) ?> rows)</h2>
<table>
  <tr><th>#</th><th>Name</th><th>Price</th><th>image_url in DB</th><th>File exists locally?</th><th>Preview</th></tr>
  <?php foreach ($products as $p):
    $imgInDb   = !empty($p['image_url']);
    $localPath = $imgInDb ? __DIR__.'/'.$p['image_url'] : null;
    $fileOk    = $localPath && file_exists($localPath) && filesize($localPath) > 1000;
    $isUrl     = $imgInDb && str_starts_with($p['image_url'], 'http');
  ?>
  <tr>
    <td><?= $p['id'] ?></td>
    <td><?= htmlspecialchars($p['name']) ?></td>
    <td class="<?= $p['price'] <= 2000 ? 'ok' : 'warn' ?>">UGX <?= number_format($p['price'],0) ?></td>
    <td>
      <?php if (!$imgInDb): ?>
        <span class="fail">NULL — no image set</span>
      <?php elseif ($isUrl): ?>
        <span class="warn">External URL (not local): <?= htmlspecialchars(substr($p['image_url'],0,50)) ?>...</span>
      <?php else: ?>
        <span class="ok"><?= htmlspecialchars($p['image_url']) ?></span>
      <?php endif; ?>
    </td>
    <td>
      <?php if ($isUrl): ?>
        <span class="warn">N/A (external URL)</span>
      <?php elseif ($fileOk): ?>
        <span class="ok">✅ <?= round(filesize($localPath)/1024) ?> KB</span>
      <?php else: ?>
        <span class="fail">❌ File missing</span>
      <?php endif; ?>
    </td>
    <td>
      <?php if ($imgInDb): ?>
        <img src="<?= htmlspecialchars($p['image_url']) ?>" style="width:60px;height:60px;object-fit:cover;border-radius:4px;border:1px solid #333;"
             onerror="this.outerHTML='<span class=fail>❌ cannot load</span>'">
      <?php else: ?>
        <span style="color:#555;">—</span>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>

<!-- What to do -->
<h2>🔧 FIX ACTIONS</h2>
<?php if ($canInternet): ?>
  <p class="ok">✅ You have internet. Click below to download all images now:</p>
  <p><a href="download_images.php" style="background:#1565c0;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:700;display:inline-block;margin-top:8px;">
    🖼️ RUN: download_images.php →
  </a></p>
<?php else: ?>
  <p class="fail">❌ No internet. Images will show as coloured emoji cards instead.</p>
  <p class="warn">To fix: connect to internet then visit download_images.php</p>
<?php endif; ?>

<p style="margin-top:20px;">
  <a href="products.php" style="color:#4af;">→ View Products Page</a> &nbsp;|&nbsp;
  <a href="fix_all.php" style="color:#4af;">→ Run fix_all.php</a>
</p>

<p style="margin-top:30px;color:#555;font-size:.75rem;">
  Delete check.php when done: <a href="?del=1" style="color:#f44;" onclick="return confirm('Delete check.php?')">Delete this file</a>
</p>

<?php if (isset($_GET['del'])) { @unlink(__FILE__); echo '<script>location.href="products.php"</script>'; } ?>
</body>
</html>
