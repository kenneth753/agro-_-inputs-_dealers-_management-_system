<?php
require 'config.php'; require_login();
$user = current_user();
$errors = []; $success = '';

/* ── Handle photo upload ────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    verify_csrf();
    $file = $_FILES['avatar'];
    $allowedTypes = ['image/jpeg','image/jpg','image/png','image/webp'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Upload failed. Please try again.';
    } elseif (!in_array($file['type'], $allowedTypes, true)) {
        $errors[] = 'Only JPG, PNG or WEBP images are allowed.';
    } elseif ($file['size'] > $maxSize) {
        $errors[] = 'Image must be smaller than 2MB.';
    } else {
        // Delete old avatar if exists
        if ($user['avatar'] && file_exists(__DIR__ . '/' . $user['avatar'])) {
            @unlink(__DIR__ . '/' . $user['avatar']);
        }
        // Save new avatar
        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'uploads/avatar_' . $user['id'] . '_' . time() . '.' . strtolower($ext);
        if (move_uploaded_file($file['tmp_name'], __DIR__ . '/' . $filename)) {
            $pdo->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$filename, $user['id']]);
            // Refresh session
            $_SESSION['user']['avatar'] = $filename;
            $user = current_user();
            $success = 'Profile photo updated successfully!';
        } else {
            $errors[] = 'Could not save the image. Check folder permissions.';
        }
    }
}

/* ── Handle remove photo ────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_avatar') {
    verify_csrf();
    if ($user['avatar'] && file_exists(__DIR__ . '/' . $user['avatar'])) {
        @unlink(__DIR__ . '/' . $user['avatar']);
    }
    $pdo->prepare("UPDATE users SET avatar=NULL WHERE id=?")->execute([$user['id']]);
    $_SESSION['user']['avatar'] = null;
    $user = current_user();
    $success = 'Profile photo removed.';
}

$pageTitle = 'My Profile';
require 'includes/header.php';
?>

<div class="breadcrumb">
  <a href="index.php">Home</a>
  <span class="sep">›</span>
  <a href="dashboard.php">Dashboard</a>
  <span class="sep">›</span>
  <span>My Profile</span>
</div>

<div style="max-width:680px; margin:0 auto;">

  <div class="page-top" style="margin-bottom:22px;">
    <div>
      <h1 class="page-title">My Profile</h1>
      <p class="muted" style="margin-top:8px;">Manage your account photo and information.</p>
    </div>
    <?php if ($user['role'] === 'admin'): ?>
    <span style="background:#e3f2fd; color:#0d47a1; padding:6px 14px; border-radius:20px; font:700 .8rem Inter,sans-serif;">⚙️ Administrator</span>
    <?php endif; ?>
  </div>

  <?php if ($success): ?><div class="alert success"><?= e($success) ?></div><?php endif; ?>
  <?php foreach ($errors as $err): ?><div class="alert error"><?= e($err) ?></div><?php endforeach; ?>

  <!-- ── Profile card ── -->
  <div class="panel" style="margin-bottom:18px;">

    <!-- Avatar display -->
    <div style="display:flex; align-items:center; gap:24px; flex-wrap:wrap; margin-bottom:28px; padding-bottom:24px; border-bottom:1px solid var(--line);">
      <div style="position:relative;">
        <?php if (!empty($user['avatar']) && file_exists(__DIR__ . '/' . $user['avatar'])): ?>
          <img src="<?= e($user['avatar']) ?>?v=<?= time() ?>"
               alt="<?= e($user['full_name']) ?>"
               style="width:110px; height:110px; border-radius:50%; object-fit:cover; border:4px solid #e3f2fd; box-shadow:0 4px 16px rgba(21,101,192,.2);">
        <?php else: ?>
          <div style="width:110px; height:110px; border-radius:50%; background:linear-gradient(135deg,#0d47a1,#42a5f5); display:flex; align-items:center; justify-content:center; font:800 2.8rem Inter,sans-serif; color:#fff; border:4px solid #e3f2fd; box-shadow:0 4px 16px rgba(21,101,192,.2);">
            <?= strtoupper(mb_substr($user['full_name'], 0, 1)) ?>
          </div>
        <?php endif; ?>
        <?php if ($user['role'] === 'admin'): ?>
        <div style="position:absolute; bottom:4px; right:4px; background:#1565c0; border-radius:50%; width:26px; height:26px; display:flex; align-items:center; justify-content:center; font-size:.85rem; border:2px solid #fff;" title="Administrator">⚙️</div>
        <?php endif; ?>
      </div>

      <div>
        <div style="font:800 1.3rem Inter,sans-serif; color:var(--ink);"><?= e($user['full_name']) ?></div>
        <div style="color:#888; font-size:.88rem; margin:4px 0;"><?= e($user['email']) ?></div>
        <div style="color:#888; font-size:.85rem;"><?= e($user['phone']) ?></div>
        <div style="display:flex; gap:8px; margin-top:10px; flex-wrap:wrap;">
          <?php if ($user['role'] === 'admin'): ?>
          <span style="background:#0d47a1; color:#fff; padding:3px 12px; border-radius:20px; font:700 .75rem Inter,sans-serif;">⚙️ System Administrator</span>
          <span style="background:#1a6b3a; color:#fff; padding:3px 12px; border-radius:20px; font:700 .75rem Inter,sans-serif;">💻 Developer</span>
          <?php else: ?>
          <span style="background:#e3f2fd; color:#0d47a1; padding:3px 12px; border-radius:20px; font:700 .75rem Inter,sans-serif;">👤 Customer</span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Upload form -->
    <h3 style="font:700 1rem Inter,sans-serif; margin:0 0 14px;">📷 Update Profile Photo</h3>

    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

      <div style="border:2px dashed #d0d8e8; border-radius:8px; padding:24px; text-align:center; background:#f8faff; margin-bottom:16px; cursor:pointer;" onclick="document.getElementById('avatar-input').click()">
        <div style="font-size:2.5rem; margin-bottom:8px;">📷</div>
        <div style="font:600 .9rem Inter,sans-serif; color:#1565c0;">Click to choose a photo</div>
        <div style="font-size:.78rem; color:#aaa; margin-top:4px;">JPG, PNG or WEBP · Max 2MB</div>
        <input type="file" id="avatar-input" name="avatar" accept="image/jpeg,image/png,image/webp"
               style="display:none;" onchange="previewImage(this)">
      </div>

      <!-- Preview -->
      <div id="preview-wrap" style="display:none; text-align:center; margin-bottom:16px;">
        <img id="preview-img" src="" alt="Preview"
             style="width:100px; height:100px; border-radius:50%; object-fit:cover; border:3px solid #1565c0; box-shadow:0 2px 12px rgba(21,101,192,.2);">
        <div style="font-size:.8rem; color:#888; margin-top:6px;" id="preview-name"></div>
      </div>

      <button class="btn" type="submit" style="width:100%; padding:12px;">
        📤 Upload Photo
      </button>
    </form>

    <?php if (!empty($user['avatar'])): ?>
    <form method="post" style="margin-top:10px;">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="remove_avatar">
      <button type="submit" class="btn secondary" style="width:100%;" onclick="return confirm('Remove your profile photo?')">
        🗑 Remove Photo
      </button>
    </form>
    <?php endif; ?>

  </div>

  <!-- Account details -->
  <div class="panel">
    <h3 style="font:700 1rem Inter,sans-serif; margin:0 0 16px;">👤 Account Details</h3>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
      <div style="background:#fafafa; border:1px solid var(--line); border-radius:6px; padding:14px;">
        <div style="font:600 .72rem Inter,sans-serif; color:#aaa; text-transform:uppercase; letter-spacing:.04em; margin-bottom:5px;">Full Name</div>
        <div style="font-weight:600;"><?= e($user['full_name']) ?></div>
      </div>
      <div style="background:#fafafa; border:1px solid var(--line); border-radius:6px; padding:14px;">
        <div style="font:600 .72rem Inter,sans-serif; color:#aaa; text-transform:uppercase; letter-spacing:.04em; margin-bottom:5px;">Phone</div>
        <div style="font-weight:600;"><?= e($user['phone']) ?></div>
      </div>
      <div style="background:#fafafa; border:1px solid var(--line); border-radius:6px; padding:14px; grid-column:1/-1;">
        <div style="font:600 .72rem Inter,sans-serif; color:#aaa; text-transform:uppercase; letter-spacing:.04em; margin-bottom:5px;">Email</div>
        <div style="font-weight:600;"><?= e($user['email']) ?></div>
      </div>
      <div style="background:#fafafa; border:1px solid var(--line); border-radius:6px; padding:14px; grid-column:1/-1;">
        <div style="font:600 .72rem Inter,sans-serif; color:#aaa; text-transform:uppercase; letter-spacing:.04em; margin-bottom:5px;">Delivery Address</div>
        <div style="font-size:.9rem; color:#555;"><?= e($user['address']) ?></div>
      </div>
      <div style="background:#e3f2fd; border:1px solid #bbdefb; border-radius:6px; padding:14px; grid-column:1/-1;">
        <div style="font:600 .72rem Inter,sans-serif; color:#1565c0; text-transform:uppercase; letter-spacing:.04em; margin-bottom:5px;">Account Role</div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
          <span style="background:#0d47a1; color:#fff; padding:3px 12px; border-radius:20px; font:700 .78rem Inter,sans-serif;">
            <?= $user['role'] === 'admin' ? '⚙️ System Administrator & Developer' : '👤 Customer' ?>
          </span>
        </div>
      </div>
    </div>

    <div style="margin-top:18px; display:flex; gap:10px; flex-wrap:wrap;">
      <a class="btn" href="dashboard.php">← Back to Dashboard</a>
      <?php if ($user['role'] === 'admin'): ?>
      <a class="btn secondary" href="admin.php">⚙️ Admin Panel</a>
      <?php endif; ?>
    </div>
  </div>

</div>

<script>
function previewImage(input) {
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('preview-img').src = e.target.result;
      document.getElementById('preview-name').textContent = input.files[0].name;
      document.getElementById('preview-wrap').style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>

<?php require 'includes/footer.php'; ?>
