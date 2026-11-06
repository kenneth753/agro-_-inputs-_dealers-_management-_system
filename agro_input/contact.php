<?php
require 'config.php';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($message) < 5)
        $errors[] = 'Enter your name, a valid email and a message of at least 5 characters.';
    if (!$errors) {
        $stmt = $pdo->prepare("INSERT INTO feedback(user_id,name,email,message) VALUES(?,?,?,?)");
        $stmt->execute([current_user()['id'] ?? null, $name, $email, $message]);
        flash('success', 'Thank you! Your message has been received. Our team will get back to you shortly.');
        header('Location: contact.php'); exit;
    }
}
$pageTitle = 'Contact Us'; require 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
  <a href="index.php">Home</a>
  <span class="sep">›</span>
  <span>Contact Us</span>
</div>

<div class="page-top" style="margin-bottom:24px;">
  <div>
    <h1 class="page-title">Contact Us</h1>
    <p class="muted" style="margin-top:8px;">We're here to help. Send us a message and our team will respond promptly.</p>
  </div>
</div>

<div style="display:grid; grid-template-columns:1fr 320px; gap:22px; align-items:start;">

  <!-- Contact form -->
  <div class="panel">
    <h2 style="margin-bottom:18px;">Send a Message</h2>

    <?php foreach ($errors as $err): ?><div class="alert error"><?= e($err) ?></div><?php endforeach; ?>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

      <label for="cname">Your Name *</label>
      <input id="cname" name="name" required
             placeholder="e.g. John Mukasa"
             value="<?= e($_POST['name'] ?? current_user()['full_name'] ?? '') ?>">

      <label for="cemail">Email Address *</label>
      <input type="email" id="cemail" name="email" required
             placeholder="your@email.com"
             value="<?= e($_POST['email'] ?? current_user()['email'] ?? '') ?>">

      <label for="cmessage">Your Message *</label>
      <textarea id="cmessage" name="message" rows="6" required
                placeholder="Tell us about your enquiry, product question, or order issue…"><?= e($_POST['message'] ?? '') ?></textarea>

      <div style="margin-top:18px;">
        <button class="btn" type="submit" style="padding:12px 28px;">📨 Send Message</button>
      </div>
    </form>
  </div>

  <!-- Contact info -->
  <div style="display:flex; flex-direction:column; gap:14px;">
    <div class="panel">
      <h3 style="font:700 .95rem Inter,sans-serif; margin-bottom:16px;">📍 Our Location</h3>
      <p style="font-size:.88rem; color:#555; line-height:1.6;">
        Agro Input Dealers<br>
        Fort Portal City<br>
        Western Region, Uganda
      </p>
    </div>

    <div class="panel">
      <h3 style="font:700 .95rem Inter,sans-serif; margin-bottom:16px;">🕒 Business Hours</h3>
      <div style="font-size:.85rem; color:#555; display:flex; flex-direction:column; gap:6px;">
        <div style="display:flex; justify-content:space-between;"><span>Mon – Fri</span><strong>8:00am – 6:00pm</strong></div>
        <div style="display:flex; justify-content:space-between;"><span>Saturday</span><strong>9:00am – 4:00pm</strong></div>
        <div style="display:flex; justify-content:space-between;"><span>Sunday</span><strong style="color:#aaa;">Closed</strong></div>
      </div>
    </div>

    <div class="panel">
      <h3 style="font:700 .95rem Inter,sans-serif; margin-bottom:12px;">💬 Quick Help</h3>
      <div style="font-size:.85rem; color:#555; display:flex; flex-direction:column; gap:10px;">
        <a href="products.php" style="display:flex; align-items:center; gap:8px; color:var(--orange); text-decoration:none; font-weight:600;">🌾 Browse Products →</a>
        <a href="my_orders.php" style="display:flex; align-items:center; gap:8px; color:var(--orange); text-decoration:none; font-weight:600;">📦 Track My Orders →</a>
        <a href="register.php" style="display:flex; align-items:center; gap:8px; color:var(--orange); text-decoration:none; font-weight:600;">✏️ Create Account →</a>
      </div>
    </div>

    <div class="note">
      📢 <strong>Note:</strong> Add the verified business phone number, email address and opening hours before public launch of this system.
    </div>
  </div>

</div>

<?php require 'includes/footer.php'; ?>
