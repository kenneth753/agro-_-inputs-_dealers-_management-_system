<?php
require 'config.php';
if (current_user()) { header('Location: index.php'); exit; }
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name     = trim($_POST['full_name'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $phone    = trim($_POST['phone'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    if (mb_strlen($name) < 2)                       $errors[] = 'Enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if ($phone === '')                               $errors[] = 'Enter a phone number.';
    if ($address === '')                             $errors[] = 'Enter a delivery address.';
    if (strlen($password) < 8)                      $errors[] = 'Password must be at least 8 characters.';
    if (!$errors) {
        try {
            $stmt = $pdo->prepare("INSERT INTO users(full_name,email,phone,address,password_hash) VALUES(?,?,?,?,?)");
            $stmt->execute([$name,$email,$phone,$address,password_hash($password,PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'        => (int)$pdo->lastInsertId(),
                'full_name' => $name,
                'email'     => $email,
                'role'      => 'customer',
                'phone'     => $phone,
                'address'   => $address,
            ];
            flash('success', 'Your account has been created. Welcome to Agro Input Dealers!');
            header('Location: index.php'); exit;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') $errors[] = 'That email address is already registered.';
            else $errors[] = 'Could not create your account. Please try again.';
        }
    }
}
$pageTitle = 'Create Account'; require 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
  <a href="index.php">Home</a>
  <span class="sep">›</span>
  <span>Create Account</span>
</div>

<div style="display:grid; grid-template-columns:1fr 380px; gap:22px; align-items:start; max-width:900px; margin:10px auto 40px;">

  <!-- Form -->
  <div class="panel">
    <div style="margin-bottom:22px;">
      <h1 style="font:700 1.4rem Inter,sans-serif; margin-bottom:6px;">Create Your Account</h1>
      <p class="muted">Register to place orders and track your agricultural supplies.</p>
    </div>

    <?php foreach ($errors as $err): ?><div class="alert error"><?= e($err) ?></div><?php endforeach; ?>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

      <label for="full_name">Full Name *</label>
      <input id="full_name" name="full_name" required maxlength="120"
             placeholder="e.g. John Mukasa"
             value="<?= e($_POST['full_name'] ?? '') ?>">

      <label for="email">Email Address *</label>
      <input type="email" id="email" name="email" required maxlength="190"
             placeholder="your@email.com"
             value="<?= e($_POST['email'] ?? '') ?>">

      <label for="phone">Phone Number *</label>
      <input id="phone" name="phone" required maxlength="30"
             placeholder="e.g. 0772 123 456"
             value="<?= e($_POST['phone'] ?? '') ?>">

      <label for="address">Delivery Address *</label>
      <textarea id="address" name="address" required rows="3"
                placeholder="Street, area, town, district…"><?= e($_POST['address'] ?? '') ?></textarea>

      <label for="password">Password * <span style="font-weight:400; color:#aaa;">(minimum 8 characters)</span></label>
      <input type="password" id="password" name="password" required minlength="8"
             autocomplete="new-password" placeholder="Create a strong password">

      <div style="margin-top:20px;">
        <button class="btn" type="submit" style="width:100%; padding:13px; font-size:1rem;">
          🌱 Create My Account
        </button>
      </div>
    </form>

    <div style="text-align:center; margin-top:16px; padding-top:14px; border-top:1px solid var(--line);">
      <p style="font-size:.88rem; color:#666;">
        Already registered? <a href="login.php" style="font-weight:700;">Log in →</a>
      </p>
    </div>
  </div>

  <!-- Benefits sidebar -->
  <div>
    <div class="panel" style="background:linear-gradient(135deg,#1a3a1a,#2d6a2d); border:none; color:#fff;">
      <h3 style="color:#fff; font:700 1rem Inter,sans-serif; margin-bottom:16px;">Why Create an Account?</h3>
      <div style="display:flex; flex-direction:column; gap:14px;">
        <div style="display:flex; gap:10px; align-items:flex-start;">
          <span style="font-size:1.3rem;">🛒</span>
          <div><strong style="font-size:.88rem;">Easy Ordering</strong><br><span style="font-size:.78rem; color:rgba(255,255,255,.7);">Place and track your farm input orders online.</span></div>
        </div>
        <div style="display:flex; gap:10px; align-items:flex-start;">
          <span style="font-size:1.3rem;">📦</span>
          <div><strong style="font-size:.88rem;">Order History</strong><br><span style="font-size:.78rem; color:rgba(255,255,255,.7);">View all your past and current orders in one place.</span></div>
        </div>
        <div style="display:flex; gap:10px; align-items:flex-start;">
          <span style="font-size:1.3rem;">📱</span>
          <div><strong style="font-size:.88rem;">SMS Updates</strong><br><span style="font-size:.78rem; color:rgba(255,255,255,.7);">Get notified when your order status changes.</span></div>
        </div>
        <div style="display:flex; gap:10px; align-items:flex-start;">
          <span style="font-size:1.3rem;">🔒</span>
          <div><strong style="font-size:.88rem;">Secure &amp; Private</strong><br><span style="font-size:.78rem; color:rgba(255,255,255,.7);">Your data is protected and never shared.</span></div>
        </div>
      </div>
    </div>
  </div>

</div>

<?php require 'includes/footer.php'; ?>
