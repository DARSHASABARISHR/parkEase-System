<?php
require_once __DIR__ . '/config/config.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'update') {
        $u = $pdo->prepare('UPDATE vehicle_types SET daily_rate=?, is_active=? WHERE type_id=?');
        foreach ($_POST['rate'] ?? [] as $id => $rate) {
            $rate = (float)$rate; if ($rate < 0 || $rate > 100000) continue;
            $u->execute([$rate, isset($_POST['active'][$id]) ? 1 : 0, (int)$id]);
        }
        flash('success', 'Tariffs updated. New rates apply to check-outs from now on.');
    } elseif (($_POST['action'] ?? '') === 'add' && trim($_POST['name'] ?? '') !== '') {
        $pdo->prepare('INSERT INTO vehicle_types (name,icon,daily_rate) VALUES (?,?,?)')->execute([trim($_POST['name']), 'bi-car-front', max(0, (float)$_POST['new_rate'])]);
        flash('success', 'Vehicle category added.');
    }
    redirect('tariffs.php');
}
$types = $pdo->query('SELECT * FROM vehicle_types ORDER BY type_id')->fetchAll();
$pageTitle = 'Tariffs';
require APP_ROOT . '/includes/header.php';
?>
<div class="row g-3"><div class="col-lg-8"><div class="glass p-4">
  <h4>Daily tariffs (per 24-hour cycle)</h4>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update">
  <table class="table align-middle"><thead><tr><th>Category</th><th style="width:160px">Rate per day (₹)</th><th>Active</th></tr></thead><tbody>
  <?php foreach ($types as $t): ?>
    <tr><td><i class="bi <?= e($t['icon']) ?>"></i> <?= e($t['name']) ?></td>
    <td><input type="number" step="0.01" min="0" name="rate[<?= $t['type_id'] ?>]" value="<?= e($t['daily_rate']) ?>" class="form-control"></td>
    <td><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="active[<?= $t['type_id'] ?>]" <?= $t['is_active'] ? 'checked' : '' ?>></div></td></tr>
  <?php endforeach; ?></tbody></table>
  <button class="btn btn-accent">Save tariffs</button></form>
</div></div>
<div class="col-lg-4"><div class="glass p-4"><h5>Add category</h5>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add">
  <input name="name" class="form-control mb-2" placeholder="e.g. Bus" required>
  <input type="number" step="0.01" min="0" name="new_rate" class="form-control mb-3" placeholder="Daily rate (₹)" required>
  <button class="btn btn-outline-warning w-100">Add</button></form></div></div></div>
<?php require APP_ROOT . '/includes/footer.php';
