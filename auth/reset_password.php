<?php
require_once dirname(__DIR__) . '/config/config.php';
if (!empty($_SESSION['admin_id'])) redirect('dashboard.php');
if (empty($_SESSION['reset_email'])) redirect('auth/forgot_password.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $new = $_POST['new_password'] ?? ''; $conf = $_POST['confirm_password'] ?? '';
    if ($problem = password_problem($new)) $error = $problem;
    elseif ($new !== $conf) $error = 'The two passwords do not match.';
    else {
        $st = $pdo->prepare('SELECT * FROM admins WHERE email=?'); $st->execute([$_SESSION['reset_email']]); $a = $st->fetch();
        $res = $a ? verify_otp_code($pdo, $a['admin_id'], preg_replace('/\D/', '', $_POST['otp'] ?? ''), 'reset') : 'expired';
        if ($res === 'ok') {
            $pdo->prepare('UPDATE admins SET password=?, login_otp=NULL, otp_expiry=NULL, otp_attempts=0 WHERE admin_id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $a['admin_id']]);
            unset($_SESSION['reset_email'], $_SESSION['dev_reset_otp'], $_SESSION['dev_reset_note']);
            flash('success', 'Password updated. Sign in with your new password.');
            redirect('auth/login.php');
        }
        $error = ['wrong' => 'That code is not correct.', 'expired' => 'That code is invalid or has expired. Request a new one.', 'locked' => 'Too many wrong tries. Request a new code.'][$res];
    }
}
$noNav = true; $pageTitle = 'Set new password';
require APP_ROOT . '/includes/header.php';
if (DEV_MODE && !empty($_SESSION['dev_reset_otp'])): ?>
  <div class="alert alert-info alert-dismissible fade show" role="alert"><b>Development mode.</b> Your reset code is <b><?= e($_SESSION['dev_reset_otp']) ?></b>.
    <?php if (!empty($_SESSION['dev_reset_note'])): ?><div class="small mt-1">Email not sent: <?= e($_SESSION['dev_reset_note']) ?></div><?php endif; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
<?php endif; ?>
<div class="auth-card">
  <div class="auth-logo"><i class="bi bi-shield-check"></i></div>
  <h1>Set a new password</h1>
  <p class="sub">Enter the code from your email and choose a new password.</p>
  <?php if ($error): ?><div class="alert alert-danger py-2" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="off"><?= csrf_field() ?>
    <div class="mb-3"><label class="form-label" for="otp">6-digit code</label>
      <input id="otp" name="otp" class="form-control otp-input" maxlength="6" inputmode="numeric" autocomplete="one-time-code" pattern="\d{6}" placeholder="••••••" required autofocus></div>
    <div class="mb-3"><label class="form-label" for="np">New password</label>
      <div class="pw-wrap"><input type="password" id="np" name="new_password" class="form-control" required autocomplete="new-password">
        <button type="button" class="pw-eye" data-toggle-pass="np" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye"></i></button></div>
      <div class="hint">At least 8 characters with upper and lower case, a number and a symbol.</div></div>
    <div class="mb-4"><label class="form-label" for="cp">Confirm new password</label>
      <div class="pw-wrap"><input type="password" id="cp" name="confirm_password" class="form-control" required autocomplete="new-password">
        <button type="button" class="pw-eye" data-toggle-pass="cp" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye"></i></button></div></div>
    <button class="btn btn-glow w-100">Update password</button>
  </form>
  <div class="d-flex justify-content-between mt-3"><a class="auth-link" href="<?= url('auth/forgot_password.php') ?>">Send a new code</a><a class="auth-link" href="<?= url('auth/login.php') ?>">Back to sign in</a></div>
</div>
<?php require APP_ROOT . '/includes/auth_footer.php';
