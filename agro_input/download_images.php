<?php
/**
 * IMAGE DOWNLOADER & LOCAL SAVER
 * Downloads compressed product images and saves them locally.
 * Run: http://localhost/agro_input/agro_input/download_images.php
 * Delete after running.
 */
require 'config.php';

// Make sure folder exists
$dir = __DIR__ . '/uploads/products/';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

/*
 * Product images — using Unsplash source API (direct download links)
 * Each image: 400x400px, optimized JPEG ~30–60KB
 */
$productImages = [
    'maize_seeds.jpg' => [
        'name'  => 'Maize Seeds — 1 kg',
        'url'   => 'https://images.unsplash.com/photo-1601593768799-76e4bb31ddac?w=400&h=400&fit=crop&q=80',
        'fallback' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e8/Corn_Zea_mays_Shucked_Ear_3008px.jpg/400px-Corn_Zea_mays_Shucked_Ear_3008px.jpg',
    ],
    'bean_seeds.jpg' => [
        'name'  => 'Bean Seeds — 1 kg',
        'url'   => 'https://images.unsplash.com/photo-1515543237350-b3eea1ec8082?w=400&h=400&fit=crop&q=80',
        'fallback' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f9/Cubed_beef_in_bean_chili.jpg/400px-Cubed_beef_in_bean_chili.jpg',
    ],
    'npk_fertilizer.jpg' => [
        'name'  => 'NPK Fertilizer — 50 kg',
        'url'   => 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&h=400&fit=crop&q=80',
        'fallback' => 'https://images.unsplash.com/photo-1464226184884-fa280b87c399?w=400&h=400&fit=crop&q=80',
    ],
    'urea_fertilizer.jpg' => [
        'name'  => 'Urea Fertilizer — 50 kg',
        'url'   => 'https://images.unsplash.com/photo-1464226184884-fa280b87c399?w=400&h=400&fit=crop&q=80',
        'fallback' => 'https://images.unsplash.com/photo-1500937386664-56d1dfef3854?w=400&h=400&fit=crop&q=80',
    ],
    'pesticide.jpg' => [
        'name'  => 'Crop Protection Product',
        'url'   => 'https://images.unsplash.com/photo-1586771107445-d3ca888129ff?w=400&h=400&fit=crop&q=80',
        'fallback' => 'https://images.unsplash.com/photo-1530836369250-ef72a3f5cda8?w=400&h=400&fit=crop&q=80',
    ],
    'dairy_meal.jpg' => [
        'name'  => 'Dairy Meal — 50 kg',
        'url'   => 'https://images.unsplash.com/photo-1500595046743-cd271d694d30?w=400&h=400&fit=crop&q=80',
        'fallback' => 'https://images.unsplash.com/photo-1516467508483-a7212febe31a?w=400&h=400&fit=crop&q=80',
    ],
    'vet_supplies.jpg' => [
        'name'  => 'Veterinary Supplies Pack',
        'url'   => 'https://images.unsplash.com/photo-1628771065518-0d82f1938462?w=400&h=400&fit=crop&q=80',
        'fallback' => 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?w=400&h=400&fit=crop&q=80',
    ],
    'hand_hoe.jpg' => [
        'name'  => 'Hand Hoe',
        'url'   => 'https://images.unsplash.com/photo-1592417817098-8fd3d9eb14a5?w=400&h=400&fit=crop&q=80',
        'fallback' => 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&h=400&fit=crop&q=80',
    ],
];

/* ── Download function ─────────────────────────────────────────────── */
function downloadImage(string $url, string $savePath): bool {
    $ctx = stream_context_create([
        'http' => [
            'timeout'         => 15,
            'follow_location' => true,
            'user_agent'      => 'Mozilla/5.0 (compatible; AgroInput/1.0)',
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ],
    ]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data && strlen($data) > 1000) {
        file_put_contents($savePath, $data);
        return true;
    }
    return false;
}

/* ── Process each image ────────────────────────────────────────────── */
$results = [];
foreach ($productImages as $filename => $info) {
    $savePath  = $dir . $filename;
    $localUrl  = 'uploads/products/' . $filename;
    $success   = false;

    // Skip download if file already exists and is valid
    if (file_exists($savePath) && filesize($savePath) > 5000) {
        $success = true;
        $results[] = ['skip', $info['name'], $filename, filesize($savePath)];
    } else {
        // Try primary URL
        $success = downloadImage($info['url'], $savePath);

        // Try fallback if primary failed
        if (!$success && !empty($info['fallback'])) {
            $success = downloadImage($info['fallback'], $savePath);
        }

        if ($success) {
            $results[] = ['ok', $info['name'], $filename, filesize($savePath)];
        } else {
            $results[] = ['fail', $info['name'], $filename, 0];
        }
    }

    // Update database with local path
    if ($success) {
        $pdo->prepare("UPDATE products SET image_url=? WHERE name=?")
            ->execute([$localUrl, $info['name']]);
    }
}

/* ── Also update prices ──────────────────────────────────────────────*/
$prices = [
    ['Maize Seeds — 1 kg',        1500],
    ['Bean Seeds — 1 kg',         1800],
    ['NPK Fertilizer — 50 kg',    2000],
    ['Urea Fertilizer — 50 kg',   1900],
    ['Crop Protection Product',   1200],
    ['Dairy Meal — 50 kg',        1700],
    ['Veterinary Supplies Pack',  1300],
    ['Hand Hoe',                  1000],
];
foreach ($prices as [$name, $price]) {
    $pdo->prepare("UPDATE products SET price=? WHERE name=?")->execute([$price, $name]);
}

/* ── Fix avatar column ───────────────────────────────────────────────*/
try { $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(500) DEFAULT NULL"); } catch(PDOException $e) {}

/* ── Verify/create Kenneth admin account ────────────────────────────*/
$check = $pdo->prepare("SELECT id FROM users WHERE email='kenethmasereka7@gmail.com'");
$check->execute();
if (!$check->fetch()) {
    $pdo->prepare("INSERT INTO users(full_name,email,phone,address,password_hash,role) VALUES(?,?,?,?,?,?)")
        ->execute(['Masereka Kenneth','kenethmasereka7@gmail.com','0774156088','Fort Portal, Uganda',password_hash('077415ken',PASSWORD_DEFAULT),'admin']);
}

/* ── Load products for preview ───────────────────────────────────────*/
$products = $pdo->query("SELECT * FROM products ORDER BY id")->fetchAll();

$ok   = count(array_filter($results, fn($r) => $r[0] === 'ok'));
$skip = count(array_filter($results, fn($r) => $r[0] === 'skip'));
$fail = count(array_filter($results, fn($r) => $r[0] === 'fail'));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Download Images — Agro Input</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:Inter,sans-serif;background:#f0f2f5;padding:24px 12px;color:#222;}
    .wrap{max-width:980px;margin:0 auto;}
    h1{font-size:1.4rem;color:#1565c0;margin-bottom:4px;}
    .sub{color:#888;font-size:.85rem;margin-bottom:22px;}
    .summary{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:22px;}
    .sum-box{background:#fff;border-radius:8px;padding:16px;text-align:center;border:1px solid #e8e8e8;}
    .sum-val{font:800 1.8rem Inter,sans-serif;}
    .sum-lbl{font-size:.78rem;color:#888;margin-top:4px;font-weight:600;text-transform:uppercase;}
    .ok-val{color:#1a6b3a;} .fail-val{color:#b71c1c;} .skip-val{color:#1565c0;}
    .log{background:#fff;border:1px solid #e8e8e8;border-radius:8px;margin-bottom:22px;overflow:hidden;}
    .log-head{padding:12px 16px;background:#fafafa;border-bottom:1px solid #e8e8e8;font:700 .9rem Inter,sans-serif;}
    .log-row{display:flex;align-items:center;gap:12px;padding:9px 16px;border-bottom:1px solid #f5f5f5;font-size:.85rem;}
    .log-row:last-child{border-bottom:none;}
    .log-icon{font-size:1.1rem;width:24px;text-align:center;}
    .log-name{flex:1;font-weight:600;}
    .log-file{color:#aaa;font-size:.78rem;font-family:monospace;}
    .log-size{color:#888;font-size:.78rem;min-width:60px;text-align:right;}
    .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-bottom:22px;}
    .card{background:#fff;border:1px solid #e8e8e8;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06);}
    .card-img{width:100%;height:160px;object-fit:cover;display:block;background:#f5f5f5;}
    .card-no-img{width:100%;height:160px;background:linear-gradient(135deg,#e3f2fd,#f5f5f5);display:flex;align-items:center;justify-content:center;font-size:3.5rem;}
    .card-body{padding:10px 12px;}
    .card-cat{font-size:.68rem;color:#aaa;text-transform:uppercase;letter-spacing:.04em;margin-bottom:3px;}
    .card-name{font:600 .82rem Inter,sans-serif;color:#222;margin-bottom:5px;line-height:1.3;}
    .card-price{color:#1565c0;font:800 .92rem Inter,sans-serif;}
    .stock{font-size:.72rem;color:#aaa;}
    .warn{background:#fff3e0;border-left:4px solid #f59e0b;border-radius:6px;padding:12px 16px;color:#b36000;font-weight:600;font-size:.85rem;margin-bottom:16px;}
    .btns{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px;}
    .btn{display:inline-block;padding:11px 22px;border-radius:6px;font:700 .88rem Inter,sans-serif;text-decoration:none;border:none;cursor:pointer;}
    .blue{background:#1565c0;color:#fff;} .green{background:#3cb878;color:#fff;} .red{background:#e02020;color:#fff;}
    .fail-note{background:#fdeaea;border-left:4px solid #e02020;border-radius:6px;padding:12px 16px;color:#b71c1c;font-size:.85rem;margin-bottom:16px;}
  </style>
</head>
<body>
<div class="wrap">
  <h1>🖼️ Image Download & Setup Complete</h1>
  <p class="sub">Product images downloaded locally, prices updated, database fixed.</p>

  <!-- Summary -->
  <div class="summary">
    <div class="sum-box"><div class="sum-val ok-val"><?= $ok + $skip ?></div><div class="sum-lbl">Images Ready</div></div>
    <div class="sum-box"><div class="sum-val skip-val"><?= $skip ?></div><div class="sum-lbl">Already Existed</div></div>
    <div class="sum-box"><div class="sum-val fail-val"><?= $fail ?></div><div class="sum-lbl">Failed (no internet?)</div></div>
  </div>

  <?php if ($fail > 0): ?>
  <div class="fail-note">
    ⚠️ <?= $fail ?> image(s) could not be downloaded. This means your PC has no internet connection right now.
    The products will show emoji icons as fallback — that's fine. You can re-run this script later when connected.
  </div>
  <?php endif; ?>

  <!-- Download log -->
  <div class="log">
    <div class="log-head">📋 Download Log</div>
    <?php foreach ($results as [$status, $name, $file, $size]): ?>
    <div class="log-row">
      <span class="log-icon"><?= $status==='ok'?'✅':($status==='skip'?'⏭️':'❌') ?></span>
      <span class="log-name"><?= htmlspecialchars($name) ?></span>
      <span class="log-file"><?= htmlspecialchars($file) ?></span>
      <span class="log-size"><?= $size > 0 ? round($size/1024).' KB' : 'Failed' ?></span>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Product grid preview -->
  <h2 style="font-size:1rem;color:#444;margin-bottom:14px;font-weight:700;">
    📦 Products Preview (<?= count($products) ?> products)
  </h2>
  <div class="grid">
    <?php
    $catEmoji = ['Improved Seeds'=>'🌽','Fertilizers'=>'🌿','Pesticides'=>'🛡️','Animal Feeds'=>'🐄','Veterinary Supplies'=>'🐾','Farm Tools'=>'🧰'];
    foreach ($products as $p):
      $hasImg = !empty($p['image_url']) && file_exists(__DIR__.'/'.$p['image_url']);
    ?>
    <div class="card">
      <?php if ($hasImg): ?>
        <img class="card-img"
             src="<?= htmlspecialchars($p['image_url']) ?>?v=<?= time() ?>"
             alt="<?= htmlspecialchars($p['name']) ?>"
             loading="lazy">
      <?php else: ?>
        <div class="card-no-img"><?= $catEmoji[$p['category']] ?? '🌱' ?></div>
      <?php endif; ?>
      <div class="card-body">
        <div class="card-cat"><?= htmlspecialchars($p['category']) ?></div>
        <div class="card-name"><?= htmlspecialchars($p['name']) ?></div>
        <div class="card-price">UGX <?= number_format($p['price'], 0) ?></div>
        <div class="stock"><?= (int)$p['stock_quantity'] ?> in stock</div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="warn">⚠️ Delete this file after confirming everything looks correct.</div>

  <div class="btns">
    <a class="btn blue" href="index.php">🏠 Homepage</a>
    <a class="btn green" href="products.php">🌾 Products Page</a>
    <a class="btn blue" href="admin.php">⚙️ Admin</a>
    <form method="post" style="display:inline;">
      <button class="btn red" name="del" value="1"
              onclick="return confirm('Delete all setup files?')">
        🗑 Delete Setup Files
      </button>
    </form>
  </div>
</div>

<?php
if (isset($_POST['del'])) {
    foreach (['setup_prices.php','setup_images.php','setup_avatar.php','setup_admin.php','fix_all.php',__FILE__] as $f) {
        if (file_exists(__DIR__.'/'.$f)) @unlink(__DIR__.'/'.$f);
    }
    echo '<script>
        document.body.innerHTML="<div style=\'text-align:center;padding:80px;font-family:sans-serif;\'>"
            +"<div style=\'font-size:4rem;margin-bottom:16px;\'>✅</div>"
            +"<h2 style=\'color:#1a6b3a;margin-bottom:10px;\'>All done! Files cleaned up.</h2>"
            +"<p style=\'color:#888;margin-bottom:24px;\'>Your site is fully set up.</p>"
            +"<a href=\'products.php\' style=\'background:#1565c0;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:700;\'>View Products →</a>"
            +"</div>";
    </script>';
}
?>
</body>
</html>
