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
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) jfail('Invalid request.', 419);
$rid = (int)($_POST['record_id'] ?? 0);
$pay = $_POST['razorpay_payment_id'] ?? '';
$ord = $_POST['razorpay_order_id'] ?? '';
$sig = $_POST['razorpay_signature'] ?? '';
$f = $_SESSION['rzp'][$rid] ?? null;
if (!$f || !hash_equals($f['order_id'], $ord)) jfail('Payment does not match an open order.');
$c = rzp_config();
if (!$c['ready'] || !hash_equals(hash_hmac('sha256', $ord . '|' . $pay, $c['secret']), $sig)) jfail('Payment signature check failed. Do not release the vehicle; contact support with ID ' . $pay);
$st = $pdo->prepare("SELECT r.*, t.name type_name, t.daily_rate FROM parking_records r JOIN vehicle_types t USING(type_id) WHERE r.record_id=? AND r.status='Parked'");
$st->execute([$rid]);
$r = $st->fetch();
if (!$r) jfail('This vehicle was already checked out.');
$b = calc_parking_bill($r['in_time'], $f['out'], $r['daily_rate'], $r['advance_paid']);
if (abs($b['pending'] - $f['amount']) > 0.001) jfail('Amount changed. Payment ' . $pay . ' needs manual review.');
try {
    $ok = settle_record($pdo, $r, $f['out'], $b, 'UPI_Online', $pay);
} catch (PDOException $e) {
    jfail('This payment ID was already used.');
}
if (!$ok) jfail('Already settled.');
unset($_SESSION['rzp'][$rid]);
echo json_encode(['ok' => true, 'redirect' => url('ticket.php?id=' . $rid)]);
