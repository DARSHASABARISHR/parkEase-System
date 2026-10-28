<?php
// Central configuration. Change only the values below if your setup differs.
define('BASE_URL', '/parkease');          // single source of truth for every link/redirect
define('APP_ROOT', dirname(__DIR__));
define('DB_HOST', 'localhost');
define('DB_NAME', 'parkease');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DEV_MODE', false);                 // true: show OTP banner when SMTP is unavailable. Set false in production.

// --- Gmail SMTP (used for login OTP, password reset and receipts) ---
// Use a Gmail *App Password* (Google Account > Security > 2-Step Verification > App passwords), not your normal password.
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);                       // 587 = STARTTLS, 465 = SSL
define('SMTP_USER', ''); // gmail id
define('SMTP_PASS', ''); // 16 digit pass key
define('SMTP_FROM_NAME', 'ParkEase');
// Secret used to hash OTPs in the database. Put any long random text here.
define('APP_SECRET', 'f3a9c1e07b4d46b28e5a91d0c7e23f6a8b1d94c5e0a7f2b36d8c4e19a5b7032d');

// --- SMS (Fast2SMS, no longer used for login) --- : can also be set in system_settings.fast2sms_api_key ---
define('FAST2SMS_API_KEY', '');
// --- Razorpay: RZP_MODE 'test' or 'live'. Keys here are fallbacks; values in system_settings override them. ---
define('RZP_MODE', 'test');
define('RZP_TEST_KEY_ID', 'rzp_test_');
define('RZP_TEST_KEY_SECRET', '');
define('RZP_LIVE_KEY_ID', 'rzp_live_XXXXXXXXXXXXXX');
define('RZP_LIVE_KEY_SECRET', 'XXXXXXXXXXXXXXXXXXXXXXXX');

// --- Helpline & Payment QR ---
define('PARKING_HELPLINE', '+91'); // own mobile 
define('GPAY_QR_PATH', 'assets/img/gpay_qr.jpeg');

define('UPI_ID', ''); // own upi GPay/PhonePe UPI ID-ஐ இங்கே இடவும் (எ.கா: darsha@okaxis)
define('UPI_PAYEE_NAME', 'ParkEase');

date_default_timezone_set('Asia/Kolkata');
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
    session_start();
}
require_once __DIR__ . '/db.php';
require_once APP_ROOT . '/includes/functions.php';
