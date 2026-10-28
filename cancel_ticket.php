<?php
require_once __DIR__ . '/config/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $record_id = (int)($_POST['record_id'] ?? 0);
    $reason = trim($_POST['cancel_reason'] ?? 'Customer requested cancellation');

    // டிக்கெட் இன்னும் 'Parked' நிலையில் உள்ளதா எனப் பார்த்தல்
    $st = $pdo->prepare("SELECT ticket_number, advance_paid FROM parking_records WHERE record_id = ? AND status = 'Parked'");
    $st->execute([$record_id]);
    $rec = $st->fetch();

    if (!$rec) {
        flash('danger', 'Ticket cannot be cancelled (already settled or cancelled).');
        redirect('dashboard.php');
    }

    // டிக்கெட்டை Cancelled என மாற்றி நேரத்தைப் பதிவு செய்தல்
    $update = $pdo->prepare("UPDATE parking_records SET status = 'Cancelled', out_time = NOW() WHERE record_id = ?");
    $update->execute([$record_id]);

    flash('warning', "Ticket " . htmlspecialchars($rec['ticket_number']) . " has been successfully cancelled.");
    redirect('dashboard.php');
} else {
    redirect('dashboard.php');
}