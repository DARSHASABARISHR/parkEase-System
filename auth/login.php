<?php
require_once dirname(__DIR__) . '/config/config.php';
if (!empty($_SESSION['admin_id'])) redirect('dashboard.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $expected = $_SESSION['captcha_code'] ?? '';
    new_captcha();                                          // every captcha is single-use
    if (($_SESSION['lock_until'] ?? 0) > time()) {
        $error = 'Too many failed attempts. Wait a few minutes and try again.';
    } elseif ($expected === '' || !hash_equals($expected, strtoupper(trim($_POST['captcha'] ?? '')))) {
        $_SESSION['fails'] = ($_SESSION['fails'] ?? 0) + 1; $error = 'The captcha did not match. A new one is shown below.';
    } else {
        $st = $pdo->prepare('SELECT * FROM admins WHERE email = ?'); $st->execute([strtolower(trim($_POST['email'] ?? ''))]);
        $a = $st->fetch();
        // verify against a dummy hash when the email is unknown, so timing does not reveal registered emails
        $valid = password_verify($_POST['password'] ?? '', $a['password'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi') && $a;
        if ($valid) {
            [$ok, $msg, $otp] = issue_otp($pdo, $a, 'login', true);
            if ($ok || DEV_MODE) {
                session_regenerate_id(true);
                $_SESSION['pending_admin'] = $a['admin_id']; unset($_SESSION['fails']);
                if (DEV_MODE) { $_SESSION['dev_otp'] = $otp; $_SESSION['dev_note'] = $ok ? '' : $msg; } else unset($_SESSION['dev_otp'], $_SESSION['dev_note']);
                if ($ok) flash('success', 'A 6-digit code was sent to ' . preg_replace('/(^.).*(@.*$)/', '$1•••$2', $a['email']) . '.');
                redirect('auth/verify_otp.php');
            }
            $error = 'The code email could not be sent. Check the SMTP settings in config/config.php.';
        } else {
            $_SESSION['fails'] = ($_SESSION['fails'] ?? 0) + 1; usleep(500000); $error = 'Incorrect email or password.';
        }
    }
    if (($_SESSION['fails'] ?? 0) >= 6) { $_SESSION['lock_until'] = time() + 300; $_SESSION['fails'] = 0; }
} else { new_captcha(); }
$gd = function_exists('imagecreatetruecolor');
$noNav = true; $pageTitle = 'Sign in';
require APP_ROOT . '/includes/header.php';
?>
<div class="auth-card">
  <div class="auth-logo"><i class="bi bi-p-lg"></i></div>
  <h1>Welcome back</h1>
  <p class="sub">Sign in to the ParkEase control room.</p>
  <?php if ($error): ?><div class="alert alert-danger py-2" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="off" novalidate><?= csrf_field() ?>
    <div class="mb-3"><label class="form-label" for="email">Email</label>
      <input type="email" id="email" name="email" class="form-control" placeholder="you@gmail.com" required autofocus></div>
    <div class="mb-2"><label class="form-label" for="password">Password</label>
      <div class="pw-wrap"><input type="password" id="password" name="password" class="form-control" placeholder="Your password" required autocomplete="current-password">
        <button type="button" class="pw-eye" data-toggle-pass="password" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye"></i></button></div></div>
    <div class="mb-3 text-end"><a class="auth-link" href="<?= url('auth/forgot_password.php') ?>">Forgot Password?</a></div>
    <label class="form-label" for="captcha">Captcha</label>
    <div class="captcha-row">
      <?php if ($gd): ?>
        <img id="capImg" src="<?= url('auth/captcha.php') ?>" alt="Captcha characters">
        <button type="button" class="btn btn-outline-light" data-captcha-reload="capImg" data-src="<?= url('auth/captcha.php?new=1') ?>" aria-label="Reload captcha"><i class="bi bi-arrow-clockwise"></i></button>
      <?php else: ?><span class="fs-2 fw-bold flex-fill text-center" style="letter-spacing:8px"><?= e($_SESSION['captcha_code']) ?></span><?php endif; ?>
    </div>
    <input id="captcha" name="captcha" class="form-control mb-4 text-uppercase" maxlength="5" placeholder="Type the characters" required autocomplete="off">
    <button class="btn btn-glow w-100">Continue</button>
  </form>
</div>
<?php require APP_ROOT . '/includes/auth_footer.php';
