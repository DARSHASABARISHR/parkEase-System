<?php
require_once __DIR__ . '/config/config.php';
require_login();
header('Content-Type: application/json');

$amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;

if ($amount <= 0) {
    echo json_encode(['error' => 'Invalid amount']);
    exit;
}

$keyId = defined('RZP_TEST_KEY_ID') ? RZP_TEST_KEY_ID : '';
$keySecret = defined('RZP_TEST_KEY_SECRET') ? RZP_TEST_KEY_SECRET : '';

if (empty($keyId) || empty($keySecret)) {
    echo json_encode(['error' => 'Razorpay API keys are missing in config.php']);
    exit;
}

// Razorpay Order generate panna cURL call
$orderData = [
    'receipt'         => 'rcpt_' . time(),
    'amount'          => round($amount * 100), // Paise (₹40 = 4000 paise)
    'currency'        => 'INR',
    'payment_capture' => 1
];

$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt($ch, CURLOPT_USERPWD, $keyId . ':' . $keySecret);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $res = json_decode($response, true);
    echo json_encode([
        'order_id' => $res['id'],
        'key'      => $keyId,
        'amount'   => $res['amount']
    ]);
} else {
    $err = json_decode($response, true);
    echo json_encode(['error' => $err['error']['description'] ?? 'Razorpay Order Failed']);
}