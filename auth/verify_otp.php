<?php
require_once dirname(__DIR__) . '/config/config.php';
if (empty($_SESSION['pending_admin'])) redirect('auth/login.php');
$st = $pdo->prepare('SELECT * FROM admins WHERE admin_id=?'); $st->execute([$_SESSION['pending_admin']]); $a = $st->fetch();
if (!$a) { unset($_SESSION['pending_admin']); redirect('auth/login.php'); }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'resend') {
        [$ok, $msg, $otp] = issue_otp($pdo, $a, 'login', false);
        if ($msg === 'cooldown') flash('warning', 'Please wait a minute before requesting another code.');
        else {
            if (DEV_MODE) { $_SESSION['dev_otp'] = $otp; $_SESSION['dev_note'] = $ok ? '' : $msg; }
            flash($ok ? 'success' : 'warning', $ok ? 'A new code was sent.' : 'The email could not be sent.');
        }
        redirect('auth/verify_otp.php');
    }
    $res = verify_otp_code($pdo, $a['admin_id'], preg_replace('/\D/', '', $_POST['otp'] ?? ''), 'login');
    if ($res === 'ok') {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $a['admin_id']; $_SESSION['admin_name'] = $a['name'];
        unset($_SESSION['pending_admin'], $_SESSION['dev_otp'], $_SESSION['dev_note'], $_SESSION['captcha_code']);
        redirect('dashboard.php');
    }
    $error = ['wrong' => 'That code is not correct.', 'expired' => 'That code has expired. Request a new one.', 'locked' => 'Too many wrong tries. Request a new code.'][$res];
}
$noNav = true; $pageTitle = 'Verify code';
require APP_ROOT . '/includes/header.php';
if (DEV_MODE && !empty($_SESSION['dev_otp'])): ?>
  <div class="alert alert-info alert-dismissible fade show" role="alert"><b>Development mode.</b> Your sign-in code is <b><?= e($_SESSION['dev_otp']) ?></b>.
    <?php if (!empty($_SESSION['dev_note'])): ?><div class="small mt-1">Email not sent: <?= e($_SESSION['dev_note']) ?></div><?php endif; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
<?php endif; ?>
<div class="auth-card">
  <div class="auth-logo"><i class="bi bi-shield-lock"></i></div>
  <h1>Check your email</h1>
  <p class="sub">Enter the 6-digit code sent to <?= e(preg_replace('/(^.).*(@.*$)/', '$1•••$2', $a['email'])) ?>.</p>
  <?php if ($error): ?><div class="alert alert-danger py-2" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post"><?= csrf_field() ?>
    <input name="otp" class="form-control otp-input mb-4" maxlength="6" inputmode="numeric" autocomplete="one-time-code" pattern="\d{6}" placeholder="••••••" required autofocus>
    <button class="btn btn-glow w-100">Verify and sign in</button>
  </form>
  <form method="post" class="d-flex justify-content-between mt-3"><?= csrf_field() ?><input type="hidden" name="action" value="resend">
    <button class="btn btn-link auth-link p-0">Send a new code</button><a class="auth-link" href="<?= url('auth/login.php') ?>">Start over</a></form>
</div>
<?php require APP_ROOT . '/includes/auth_footer.php';
