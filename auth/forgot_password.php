<?php
require_once dirname(__DIR__) . '/config/config.php';
if (!empty($_SESSION['admin_id'])) redirect('dashboard.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $st = $pdo->prepare('SELECT * FROM admins WHERE email=?'); $st->execute([$email]); $a = $st->fetch();
    $_SESSION['reset_email'] = $email; unset($_SESSION['dev_reset_otp'], $_SESSION['dev_reset_note']);
    if ($a) {
        [$ok, $msg, $otp] = issue_otp($pdo, $a, 'reset', false);
        if (DEV_MODE && $msg !== 'cooldown') { $_SESSION['dev_reset_otp'] = $otp; $_SESSION['dev_reset_note'] = $ok ? '' : $msg; }
    }
    // Same reply whether or not the email exists.
    flash('info', 'If that email is registered, a 6-digit reset code has been sent.');
    redirect('auth/reset_password.php');
}
$noNav = true; $pageTitle = 'Forgot password';
require APP_ROOT . '/includes/header.php';
?>
<div class="auth-card">
  <div class="auth-logo"><i class="bi bi-key"></i></div>
  <h1>Reset your password</h1>
  <p class="sub">Enter your registered email. We will send a 6-digit code.</p>
  <form method="post" novalidate><?= csrf_field() ?>
    <div class="mb-4"><label class="form-label" for="email">Email</label>
      <input type="email" id="email" name="email" class="form-control" placeholder="you@gmail.com" required autofocus></div>
    <button class="btn btn-glow w-100">Send reset code</button>
  </form>
  <div class="text-center mt-3"><a class="auth-link" href="<?= url('auth/login.php') ?>">Back to sign in</a></div>
</div>
<?php require APP_ROOT . '/includes/auth_footer.php';
