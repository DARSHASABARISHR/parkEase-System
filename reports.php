<?php
require_once __DIR__ . '/config/config.php';
require_login();
$from = $_GET['from'] ?? date('Y-m-01'); $to = $_GET['to'] ?? date('Y-m-d');
$type = (int)($_GET['type'] ?? 0); $method = $_GET['method'] ?? ''; $status = $_GET['status'] ?? '';
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : date('Y-m-01');
$to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : date('Y-m-d');
$w = ['DATE(r.in_time) BETWEEN ? AND ?']; $p = [$from, $to];
if ($type) { $w[] = 'r.type_id=?'; $p[] = $type; }
if (in_array($method, ['Cash', 'UPI_Online'], true)) { $w[] = 'r.payment_method=?'; $p[] = $method; }
if (in_array($status, ['Parked', 'Completed', 'Cancelled'], true)) { $w[] = 'r.status=?'; $p[] = $status; }
$st = $pdo->prepare('SELECT r.*, t.name type_name FROM parking_records r JOIN vehicle_types t USING(type_id) WHERE ' . implode(' AND ', $w) . ' ORDER BY r.in_time DESC LIMIT 5000');
$st->execute($p); $rows = $st->fetchAll();

if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="parkease_report_' . $from . '_to_' . $to . '.csv"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['Ticket', 'Vehicle', 'Type', 'In', 'Out', 'Duration (h)', 'Days', 'Advance (INR)', 'Total (INR)', 'Payment', 'Razorpay ID', 'Status']);
    foreach ($rows as $r) fputcsv($o, [$r['ticket_number'], $r['vehicle_number'], $r['type_name'], $r['in_time'], $r['out_time'], $r['duration_hours'], $r['days_charged'], $r['advance_paid'], $r['total_amount'], $r['payment_method'], $r['razorpay_payment_id'], $r['status']]);
    exit;
}
$types = $pdo->query('SELECT type_id,name FROM vehicle_types ORDER BY type_id')->fetchAll();
$sum = array_sum(array_map(fn($r) => $r['status'] === 'Completed' ? $r['total_amount'] : 0, $rows));
$pageTitle = 'Reports';
require APP_ROOT . '/includes/header.php';
$qs = http_build_query(['from' => $from, 'to' => $to, 'type' => $type, 'method' => $method, 'status' => $status, 'export' => 1]);
?>
<div class="glass p-3 mb-3">
<form class="row g-2 align-items-end" method="get">
  <div class="col-6 col-md"><label class="form-label small">From</label><input type="date" name="from" value="<?= e($from) ?>" class="form-control"></div>
  <div class="col-6 col-md"><label class="form-label small">To</label><input type="date" name="to" value="<?= e($to) ?>" class="form-control"></div>
  <div class="col-6 col-md"><label class="form-label small">Vehicle type</label><select name="type" class="form-select"><option value="0">All</option>
    <?php foreach ($types as $t): ?><option value="<?= $t['type_id'] ?>" <?= $type == $t['type_id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md"><label class="form-label small">Payment</label><select name="method" class="form-select"><option value="">All</option>
    <option value="Cash" <?= $method === 'Cash' ? 'selected' : '' ?>>Cash</option><option value="UPI_Online" <?= $method === 'UPI_Online' ? 'selected' : '' ?>>UPI / Online</option></select></div>
  <div class="col-6 col-md"><label class="form-label small">Status</label><select name="status" class="form-select"><option value="">All</option>
    <?php foreach (['Parked', 'Completed', 'Cancelled'] as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
  <div class="col-12 col-md-auto"><button class="btn btn-accent">Apply</button> <a class="btn btn-outline-success" href="?<?= e($qs) ?>"><i class="bi bi-file-earmark-spreadsheet"></i> Export CSV</a></div>
</form></div>
<div class="glass p-3">
  <div class="d-flex justify-content-between mb-2"><span><?= count($rows) ?> records</span><b>Revenue in range: <?= money($sum) ?></b></div>
  <div class="table-responsive"><table class="table table-sm align-middle">
    <thead><tr><th>Ticket</th><th>Vehicle</th><th>Type</th><th>In</th><th>Out</th><th>Duration</th><th class="text-end">Amount</th><th>Payment</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><a href="<?= url('ticket.php?id=' . $r['record_id']) ?>"><?= e($r['ticket_number']) ?></a></td><td class="vnum"><?= e($r['vehicle_number']) ?></td><td><?= e($r['type_name']) ?></td>
      <td><?= e(date('d M h:i A', strtotime($r['in_time']))) ?></td><td><?= $r['out_time'] ? e(date('d M h:i A', strtotime($r['out_time']))) : '—' ?></td>
      <td><?= $r['duration_hours'] !== null ? e(fmt_duration((int)round($r['duration_hours'] * 60))) : '—' ?></td>
      <td class="text-end"><?= money($r['total_amount']) ?></td><td><?= e(str_replace('_', ' ', (string)$r['payment_method'])) ?: '—' ?></td>
      <td><span class="badge text-bg-<?= $r['status'] === 'Completed' ? 'success' : ($r['status'] === 'Parked' ? 'warning' : 'secondary') ?>"><?= e($r['status']) ?></span></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="9" class="text-center text-secondary py-4">No records for these filters. Widen the date range.</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
<?php require APP_ROOT . '/includes/footer.php';
