<?php
require_once __DIR__ . '/config/config.php';
require_login();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old = $_POST['old_password'] ?? ''; $new = $_POST['new_password'] ?? ''; $conf = $_POST['confirm_password'] ?? '';
    $st = $pdo->prepare('SELECT * FROM admins WHERE admin_id=?'); $st->execute([$_SESSION['admin_id']]); $a = $st->fetch();
    if (($_SESSION['pw_lock'] ?? 0) > time()) $error = 'Too many wrong attempts. Try again in a few minutes.';
    elseif (!$a || !password_verify($old, $a['password'])) {
        $_SESSION['pw_fails'] = ($_SESSION['pw_fails'] ?? 0) + 1;
        if ($_SESSION['pw_fails'] >= 5) { $_SESSION['pw_lock'] = time() + 300; $_SESSION['pw_fails'] = 0; }
        $error = 'Your current password is incorrect.';
    }
    elseif ($new !== $conf) $error = 'The new passwords do not match.';
    elseif ($new === $old) $error = 'Choose a password different from the current one.';
    elseif ($problem = password_problem($new)) $error = $problem;
    else {
        $pdo->prepare('UPDATE admins SET password=? WHERE admin_id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $a['admin_id']]);
        session_regenerate_id(true); unset($_SESSION['pw_fails']);
        flash('success', 'Password changed successfully.');
        redirect('dashboard.php');
    }
}
$pageTitle = 'Change password';
require APP_ROOT . '/includes/header.php';
function pw_field($id, $name, $label, $auto) { ?>
  <div class="mb-3"><label class="form-label" for="<?= $id ?>"><?= $label ?></label>
    <div class="pw-wrap"><input type="password" id="<?= $id ?>" name="<?= $name ?>" class="form-control" required autocomplete="<?= $auto ?>">
      <button type="button" class="pw-eye" data-toggle-pass="<?= $id ?>" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye"></i></button></div></div>
<?php } ?>
<div class="row justify-content-center"><div class="col-md-8 col-lg-5"><div class="glass p-4">
  <h4 class="mb-1">Change password</h4>
  <p class="text-secondary small">Use at least 8 characters with upper and lower case letters, a number and a symbol.</p>
  <?php if ($error): ?><div class="alert alert-danger py-2" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="off"><?= csrf_field() ?>
    <?php pw_field('op', 'old_password', 'Current password', 'current-password'); pw_field('np', 'new_password', 'New password', 'new-password'); pw_field('cp', 'confirm_password', 'Confirm new password', 'new-password'); ?>
    <button class="btn btn-accent w-100 py-2">Update password</button>
  </form>
</div></div></div>
<style>.pw-wrap{position:relative}.pw-wrap .form-control{padding-right:3rem}.pw-eye{position:absolute;right:.3rem;top:50%;transform:translateY(-50%);width:2.3rem;height:2.3rem;border:0;background:transparent;color:inherit;opacity:.65;border-radius:8px}.pw-eye:hover,.pw-eye:focus-visible{opacity:1;outline:none;background:rgba(127,127,127,.18)}</style>
<?php
$extraScripts = '<script src="' . url('assets/js/auth.js') . '"></script>';
require APP_ROOT . '/includes/footer.php';
