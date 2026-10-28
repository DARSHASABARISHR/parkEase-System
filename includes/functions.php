<?php
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function url($p = '') { return BASE_URL . '/' . ltrim($p, '/'); }
function redirect($p) { header('Location: ' . url($p)); exit; }
function money($v) { return '₹' . number_format((float)$v, 2); }

function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
        !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419); exit('Session expired. Go back, refresh and try again.');
    }
}
function flash($type = null, $msg = null) {
    if ($type !== null) { $_SESSION['flash'][] = [$type, $msg]; return; }
    $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f;
}
function require_login() {
    if (empty($_SESSION['admin_id'])) redirect('auth/login.php');
}
function setting($key, $default = '') {
    global $pdo; static $cache = null;
    if ($cache === null) $cache = $pdo->query('SELECT setting_key,setting_value FROM system_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    return $cache[$key] ?? $default;
}

/** 24-hour cycle billing. Days = CEIL(elapsed minutes / 1440), minimum 1. One minute past a cycle starts the next full day. */
function calc_parking_bill($in, $out, $rate, $advance = 0) {
    $mins  = max(0, (int)floor((strtotime($out) - strtotime($in)) / 60));
    $days  = max(1, (int)ceil($mins / 1440));
    $total = round($days * (float)$rate, 2);
    $adv   = min((float)$advance, $total);
    return ['minutes' => $mins, 'hours' => round($mins / 60, 2), 'days' => $days, 'rate' => (float)$rate,
            'total' => $total, 'advance' => $adv, 'pending' => round($total - $adv, 2), 'overstay' => $days > 1,
            'cycle_end' => date('Y-m-d H:i:s', strtotime($in) + $days * 86400)];
}
function fmt_duration($m) { return floor($m / 60) . 'h ' . ($m % 60) . 'm'; }

function load_mailer() {
    if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) return true;
    $auto = APP_ROOT . '/vendor/autoload.php';
    $base = APP_ROOT . '/includes/PHPMailer/src/';
    if (is_file($auto)) require_once $auto;
    elseif (is_file($base . 'PHPMailer.php')) {
        require_once $base . 'Exception.php'; require_once $base . 'PHPMailer.php'; require_once $base . 'SMTP.php';
    }
    return class_exists('\PHPMailer\PHPMailer\PHPMailer');
}
/** Sends through Gmail SMTP using SMTP_* constants from config/config.php. Returns [bool ok, string message]. Never throws. */
function send_mail($to, $subject, $html) {
    if (!load_mailer()) return [false, 'PHPMailer is not installed (see README)'];
    if (SMTP_USER === '' || stripos(SMTP_USER, 'yourgmail') !== false || SMTP_PASS === '' || stripos(SMTP_PASS, 'xxxx') !== false)
        return [false, 'SMTP is not configured in config/config.php'];
    try {
        $m = new \PHPMailer\PHPMailer\PHPMailer(true);
        $m->isSMTP(); $m->Host = SMTP_HOST; $m->SMTPAuth = true;
        $m->Username = SMTP_USER; $m->Password = str_replace(' ', '', SMTP_PASS);
        $m->SMTPSecure = SMTP_PORT == 465 ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $m->Port = (int)SMTP_PORT; $m->Timeout = 12; $m->CharSet = 'UTF-8';
        $m->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $m->addAddress($to); $m->isHTML(true); $m->Subject = $subject; $m->Body = $html; $m->AltBody = strip_tags($html);
        $m->send();
        return [true, 'sent'];
    } catch (\Throwable $ex) { return [false, 'Mail error: ' . $ex->getMessage()]; }
}

// ---------- OTP (login + password reset), hashed in the database, 5 tries, 10 minutes ----------
function otp_hash($code) { return hash_hmac('sha256', (string)$code, APP_SECRET); }
function otp_cols($kind) { return $kind === 'reset' ? ['reset_otp', 'reset_expiry', 'reset_attempts'] : ['login_otp', 'otp_expiry', 'otp_attempts']; }

/** Creates and emails a 6-digit code. Returns [bool sent, string message, string otp|null]. $force=false enforces a 60-second cooldown. */
function issue_otp($pdo, $a, $kind, $force = false) {
    [$oc, $ec, $ac] = otp_cols($kind);
    if (!$force) {
        $q = $pdo->prepare("SELECT 1 FROM admins WHERE admin_id=? AND $ec > DATE_ADD(NOW(), INTERVAL 9 MINUTE)"); $q->execute([$a['admin_id']]);
        if ($q->fetchColumn()) return [false, 'cooldown', null];
    }
    $otp = (string)random_int(100000, 999999);
    $pdo->prepare("UPDATE admins SET $oc=?, $ec=DATE_ADD(NOW(), INTERVAL 10 MINUTE), $ac=0 WHERE admin_id=?")->execute([otp_hash($otp), $a['admin_id']]);
    $title = $kind === 'reset' ? 'Password reset code' : 'Sign-in code';
    $html = '<div style="font-family:Arial,sans-serif;max-width:420px;margin:auto;padding:20px;border:1px solid #ddd;border-radius:10px">'
          . '<h3 style="margin:0 0 8px">' . e(SMTP_FROM_NAME) . ' · ' . $title . '</h3>'
          . '<p style="font-size:32px;letter-spacing:8px;margin:16px 0"><b>' . $otp . '</b></p>'
          . '<p style="color:#666;font-size:13px">Valid for 10 minutes. If you did not request this, ignore this email and consider changing your password.</p></div>';
    [$ok, $msg] = send_mail($a['email'], "Your " . SMTP_FROM_NAME . " " . strtolower($title), $html);
    return [$ok, $msg, $otp];
}
/** Returns 'ok' | 'wrong' | 'expired' | 'locked'. A correct code is consumed. */
function verify_otp_code($pdo, $adminId, $code, $kind) {
    [$oc, $ec, $ac] = otp_cols($kind);
    $st = $pdo->prepare("SELECT $oc o, $ec e, $ac n FROM admins WHERE admin_id=?"); $st->execute([$adminId]); $r = $st->fetch();
    if (!$r || !$r['o'] || strtotime($r['e']) < time()) return 'expired';
    if ((int)$r['n'] >= 5) { $pdo->prepare("UPDATE admins SET $oc=NULL, $ec=NULL WHERE admin_id=?")->execute([$adminId]); return 'locked'; }
    if (strlen($code) !== 6 || !hash_equals($r['o'], otp_hash($code))) {
        $pdo->prepare("UPDATE admins SET $ac=$ac+1 WHERE admin_id=?")->execute([$adminId]); return 'wrong';
    }
    $pdo->prepare("UPDATE admins SET $oc=NULL, $ec=NULL, $ac=0 WHERE admin_id=?")->execute([$adminId]);
    return 'ok';
}
function password_problem($pw) {
    if (strlen($pw) < 8 || !preg_match('/[a-z]/', $pw) || !preg_match('/[A-Z]/', $pw) || !preg_match('/\d/', $pw) || !preg_match('/[^A-Za-z0-9]/', $pw))
        return 'Use at least 8 characters with upper and lower case letters, a number and a symbol.';
    return null;
}

function pay_label($m) { return $m === 'UPI_Online' ? 'Razorpay (UPI / Online)' : ($m ?: '—'); }

/** Rows for the final invoice (used by the printable page and the email). Each: [label, value, bold]. */
function receipt_rows($r) {
    $mins = max(0, (int)floor((strtotime($r['out_time']) - strtotime($r['in_time'])) / 60));
    $total = (float)$r['total_amount']; $adv = min((float)$r['advance_paid'], $total);
    $rows = [['Ticket', $r['ticket_number'], true], ['Vehicle', $r['vehicle_number'] . ' (' . $r['type_name'] . ')', false],
        ['In-time', date('d M Y, h:i A', strtotime($r['in_time'])), false], ['Out-time', date('d M Y, h:i A', strtotime($r['out_time'])), false],
        ['Duration', fmt_duration($mins) . ' (' . number_format($mins / 60, 2) . ' hrs)', false]];
    if ($r['days_charged']) $rows[] = ['Billing cycles', $r['days_charged'] . ' Day' . ($r['days_charged'] > 1 ? 's' : '') . ' @ ' . money($r['rate_applied']) . '/day = ' . money($total), false];
    $rows[] = ['Advance paid at entry', money($adv), false];
    $rows[] = ['Pending settled at exit', money($total - $adv), false];
    $rows[] = ['Grand total', money($total), true];
    $rows[] = ['Payment mode', pay_label($r['payment_method']), false];
    if (!empty($r['razorpay_payment_id'])) $rows[] = ['Transaction ID', $r['razorpay_payment_id'], false];
    return $rows;
}
function receipt_html($r) {
    $h = '<div style="font-family:Arial,sans-serif;max-width:480px;margin:auto;border:1px solid #ddd;border-radius:10px;padding:20px">'
       . '<h2 style="margin:0">' . e(setting('company_name', 'ParkEase')) . '</h2><p style="color:#777;margin:4px 0 16px">Final invoice / receipt</p><table style="width:100%;font-size:14px;line-height:1.8">';
    foreach (receipt_rows($r) as [$l, $v, $b]) $h .= '<tr><td>' . e($l) . '</td><td align="right">' . ($b ? '<b>' : '') . e($v) . ($b ? '</b>' : '') . '</td></tr>';
    return $h . '</table><p style="color:#777;font-size:12px;margin-top:16px">Thank you for parking with us.</p></div>';
}

/** Closes a parked record and emails the receipt. Returns false if it was already settled. */
function settle_record($pdo, $r, $out, $b, $method, $payId = null) {
    $u = $pdo->prepare("UPDATE parking_records SET out_time=?, duration_hours=?, total_amount=?, balance_paid=?, days_charged=?, rate_applied=?, payment_method=?, razorpay_payment_id=?, payment_status='Paid', status='Completed' WHERE record_id=? AND status='Parked'");
    $u->execute([$out, $b['hours'], $b['total'], $b['pending'], $b['days'], $b['rate'], $method, $payId, $r['record_id']]);
    if ($u->rowCount() !== 1) return false;
    $r = array_merge($r, ['out_time' => $out, 'total_amount' => $b['total'], 'days_charged' => $b['days'], 'rate_applied' => $b['rate'], 'payment_method' => $method, 'razorpay_payment_id' => $payId]);
    if (!empty($r['driver_email'])) {
        [$ok] = send_mail($r['driver_email'], 'Parking receipt ' . $r['ticket_number'], receipt_html($r));
        flash($ok ? 'success' : 'warning', $ok ? 'Receipt emailed to the driver.' : 'Payment saved, but the receipt email could not be sent.');
    }
    flash('success', 'Checked out. ' . money($b['pending']) . ' settled.');
    return true;
}

// ---------- Captcha ----------
function new_captcha() {
    $c = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $code = '';
    for ($i = 0; $i < 5; $i++) $code .= $c[random_int(0, strlen($c) - 1)];
    return $_SESSION['captcha_code'] = $code;
}

// ---------- SMS (Fast2SMS) ----------
function send_sms_otp($mobile, $otp) {
    $key = setting('fast2sms_api_key') ?: FAST2SMS_API_KEY;
    if (!$key) return [false, 'SMS API key not set'];
    if (!preg_match('/^[6-9][0-9]{9}$/', (string)$mobile)) return [false, 'no valid mobile number saved for the admin'];
    if (!function_exists('curl_init')) return [false, 'cURL is disabled in php.ini'];
    $ch = curl_init('https://www.fast2sms.com/dev/bulkV2');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => ['authorization: ' . $key, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['route' => 'otp', 'variables_values' => $otp, 'numbers' => $mobile])]);
    $res = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    if ($res === false) return [false, 'SMS network error: ' . $err];
    $j = json_decode($res, true);
    if (!empty($j['return'])) return [true, 'sent'];
    $m = $j['message'] ?? 'rejected'; return [false, 'SMS provider: ' . (is_array($m) ? implode(', ', $m) : $m)];
}

// ---------- Razorpay ----------
function rzp_config() {
    $mode = setting('razorpay_mode', RZP_MODE) === 'live' ? 'live' : 'test';
    $key = setting("rzp_{$mode}_key_id") ?: constant('RZP_' . strtoupper($mode) . '_KEY_ID');
    $sec = setting("rzp_{$mode}_key_secret") ?: constant('RZP_' . strtoupper($mode) . '_KEY_SECRET');
    $ready = $key && $sec && stripos($key, 'XXXX') === false && stripos($sec, 'XXXX') === false;
    return ['mode' => $mode, 'key' => $key, 'secret' => $sec, 'ready' => (bool)$ready];
}
function rzp_api($path, $payload) {
    $c = rzp_config();
    if (!$c['ready']) return [false, 'Razorpay keys are not configured'];
    if (!function_exists('curl_init')) return [false, 'cURL is disabled in php.ini'];
    $ch = curl_init('https://api.razorpay.com/v1/' . $path);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_USERPWD => $c['key'] . ':' . $c['secret'], CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($payload)]);
    $res = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    if ($res === false) return [false, 'Network error: ' . $err];
    $j = json_decode($res, true);
    if (isset($j['error'])) return [false, 'Razorpay: ' . ($j['error']['description'] ?? 'request failed')];
    return [true, $j];
}
