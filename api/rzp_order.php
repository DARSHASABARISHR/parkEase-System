<?php
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json');
function jfail($m, $c = 400)
{
    http_response_code($c);
    echo json_encode(['ok' => false, 'error' => $m]);
    exit;
}
if (empty($_SESSION['admin_id'])) jfail('Session expired. Sign in again.', 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) jfail('Invalid request. Refresh the page.', 419);
$st = $pdo->prepare("SELECT r.*, t.name type_name, t.daily_rate FROM parking_records r JOIN vehicle_types t USING(type_id) WHERE r.record_id=? AND r.status='Parked'");
$st->execute([(int)($_POST['record_id'] ?? 0)]);
$r = $st->fetch();
if (!$r) jfail('This vehicle is not parked.');
$now = date('Y-m-d H:i:s');
$b = calc_parking_bill($r['in_time'], $now, $r['daily_rate'], $r['advance_paid']);
if ($b['pending'] <= 0) jfail('Nothing is due. Check out normally.');
[$ok, $o] = rzp_api('orders', [
    'amount' => (int)round($b['pending'] * 100),
    'currency' => 'INR',
    'receipt' => $r['ticket_number'],
    'notes' => ['ticket' => $r['ticket_number'], 'vehicle' => $r['vehicle_number']]
]);
if (!$ok) jfail($o);
// Freeze the billing moment so the amount paid always matches the amount settled.
$_SESSION['rzp'][$r['record_id']] = ['order_id' => $o['id'], 'out' => $now, 'amount' => $b['pending']];
$pdo->prepare('UPDATE parking_records SET razorpay_order_id=? WHERE record_id=?')->execute([$o['id'], $r['record_id']]);
$c = rzp_config();
echo json_encode([
    'ok' => true,
    'key' => $c['key'],
    'order_id' => $o['id'],
    'amount' => $o['amount'],
    'name' => setting('company_name', 'ParkEase'),
    'desc' => 'Parking ' . $r['ticket_number'],
    'contact' => $r['driver_mobile'] ?? '',
    'email' => $r['driver_email'] ?? ''
]);
