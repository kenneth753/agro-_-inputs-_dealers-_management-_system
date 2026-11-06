<?php
require 'config.php';
if (current_user()) { header('Location: index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT id,full_name,email,phone,address,password_hash,role,avatar FROM users WHERE email=?");
    $stmt->execute([$email]); $u = $stmt->fetch();
    if ($u && password_verify($password, $u['password_hash'])) {
        session_regenerate_id(true);
        unset($u['password_hash']); $_SESSION['user'] = $u;
        header('Location: ' . ($u['role'] === 'admin' ? 'admin.php' : 'index.php')); exit;
    }
    $error = 'Email or password is incorrect.';
}
$pageTitle = 'Log In'; require 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
  <a href="index.php">Home</a>
  <span class="sep">›</span>
  <span>Log In</span>
</div>

<div style="max-width:480px; margin:10px auto 40px;">
  <div class="panel">
    <!-- Header -->
    <div style="text-align:center; margin-bottom:26px;">
      <div style="font-size:3rem; margin-bottom:10px;">🌱</div>
      <h1 style="font:700 1.5rem Inter,sans-serif; margin-bottom:6px;">Welcome Back</h1>
      <p class="muted">Log in to manage your orders and shop for farm inputs.</p>
    </div>

    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

      <label for="email">Email Address</label>
      <input type="email" id="email" name="email" required autocomplete="username"
             placeholder="your@email.com"
             value="<?= e($_POST['email'] ?? '') ?>">

      <label for="password">Password</label>
      <input type="password" id="password" name="password" required autocomplete="current-password"
             placeholder="Your password">

      <div style="margin-top:20px;">
        <button class="btn" type="submit" style="width:100%; padding:13px; font-size:1rem;">
          Log In to My Account
        </button>
      </div>
    </form>

    <div style="text-align:center; margin-top:20px; padding-top:16px; border-top:1px solid var(--line);">
      <p style="font-size:.88rem; color:#666;">
        New customer? <a href="register.php" style="font-weight:700;">Create a free account →</a>
      </p>
    </div>
  </div>

  <!-- Trust note -->
  <div style="display:flex; gap:8px; justify-content:center; margin-top:16px; flex-wrap:wrap;">
    <span style="font-size:.78rem; color:#aaa; display:flex; align-items:center; gap:4px;">🔒 Secure login</span>
    <span style="font-size:.78rem; color:#aaa; display:flex; align-items:center; gap:4px;">🌱 Agro Input Dealers, Fort Portal</span>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
