<?php
require_once __DIR__ . '/config/config.php';
require_login();

$st = $pdo->prepare('SELECT r.*, t.name type_name, t.daily_rate FROM parking_records r JOIN vehicle_types t USING(type_id) WHERE record_id=?');
$st->execute([(int)($_GET['id'] ?? 0)]); 
$r = $st->fetch();

if (!$r) { 
    flash('danger', 'Ticket not found.'); 
    redirect('dashboard.php'); 
}

$done = $r['status'] === 'Completed';
$cancelled = $r['status'] === 'Cancelled';
$pageTitle = $done ? 'Invoice' : ($cancelled ? 'Cancelled Slip' : 'Entry slip');
require APP_ROOT . '/includes/header.php';
?>

<div class="glass slip">
  <div class="d-flex justify-content-between align-items-start">
    <div>
      <h4 class="brand mb-0"><?= e(setting('company_name', 'ParkEase')) ?></h4>
      <small class="text-secondary"><?= $done ? 'Final Invoice / இறுதி ரசீது' : ($cancelled ? 'Cancelled Slip / ரத்து செய்யப்பட்ட சீட்டு' : 'Entry Pass / நுழைவுச் சீட்டு') ?></small>
    </div>
    <?php if ($cancelled): ?>
      <span class="badge bg-danger fs-6 px-3 py-2">CANCELLED (ரத்து செய்யப்பட்டது)</span>
    <?php endif; ?>
  </div>

  <?php if (!$done): ?>
    <?php if (!$cancelled): ?>
      <div id="qr" class="d-flex justify-content-center my-3 bg-white p-2 rounded mx-auto shadow-sm" style="width:fit-content"></div>
    <?php endif; ?>

    <div class="row-kv"><span>Ticket # (டிக்கெட் எண்)</span><b><?= e($r['ticket_number']) ?></b></div>
    <div class="row-kv"><span>Vehicle No (வாகன எண்)</span><b class="vnum"><?= e($r['vehicle_number']) ?></b></div>
    <div class="row-kv"><span>Vehicle Type (வாகன வகை)</span><span><?= e($r['type_name']) ?></span></div>
    <div class="row-kv"><span>Entry Time (நுழைவு நேரம்)</span><span><?= e(date('d M Y, h:i A', strtotime($r['in_time']))) ?></span></div>
    <div class="row-kv"><span>Day 1 Valid Until (செல்லுபடியாகும் நேரம்)</span><b><?= e(date('d M Y, h:i A', strtotime($r['in_time']) + 86400)) ?></b></div>
    <div class="row-kv"><span>Daily Rate (நாள் கட்டணம்)</span><span><?= money($r['daily_rate']) ?></span></div>
    <div class="row-kv"><span>Paid in advance (முன்பணம்)</span><b><?= money($r['advance_paid']) ?></b></div>
    <div class="row-kv"><span>Payment Mode (கட்டண முறை)</span><b><?= e($r['payment_mode'] ?? 'Cash') ?></b></div>
    
    <?php if ($cancelled): ?>
      <div class="alert alert-danger text-center mt-3 py-2">
        <i class="bi bi-x-circle-fill"></i> This ticket is cancelled. Vehicle not allowed to park. <br><small>(இந்த டிக்கெட் ரத்து செய்யப்பட்டுள்ளது. வாகனம் பார்க்கிங்கில் அனுமதிக்கப்படாது.)</small>
      </div>
    <?php else: ?>
      <div class="notice">
        <b>Notice / குறிப்பு:</b> Daily rate covers up to 24 hours from entry. Exceeding by even 1 minute triggers the subsequent day's full tariff. <br>
        <span class="opacity-75">ஒரு நாள் கட்டணம் 24 மணி நேரம் வரை மட்டுமே. 1 நிமிடம் கூடுதல் நேரம் ஆனாலும் அடுத்த நாளுக்கான முழு கட்டணம் பொருந்தும்.</span>
      </div>
    <?php endif; ?>
    
    <div class="text-center small mt-3 pt-2 border-top border-secondary">
      <span class="text-secondary">Help & Support / உதவி & விசாரணைக்கு:</span><br>
      <b class="fs-6">📞 <?= defined('PARKING_HELPLINE') ? e(PARKING_HELPLINE) : '+91 7845156026' ?></b>
    </div>

  <?php else: ?>
    <div class="my-3"></div>
    <?php foreach (receipt_rows($r) as [$l, $v, $b]): ?>
      <div class="row-kv"><span><?= e($l) ?></span><?= $b ? '<b>' : '<span>' ?><?= e($v) ?><?= $b ? '</b>' : '</span>' ?></div>
    <?php endforeach; ?>
    
    <div class="text-center small mt-3 pt-2 border-top border-secondary">
      <span class="text-secondary">Help & Support / உதவிக்கு:</span><br>
      <b>📞 <?= defined('PARKING_HELPLINE') ? e(PARKING_HELPLINE) : '+91 7845156026' ?></b>
    </div>
    <p class="small text-secondary mt-2 mb-0 text-center">Thank you for parking with us. / எங்கள் பார்க்கிங் சேவையைப் பயன்படுத்தியதற்கு நன்றி.</p>
  <?php endif; ?>
</div>

<!-- Buttons Section -->
<div class="text-center mt-3 no-print d-flex justify-content-center gap-2 flex-wrap">
  <button class="btn btn-accent" onclick="window.print()"><i class="bi bi-printer"></i> Print (அச்சிடு)</button>
  <a class="btn btn-outline-secondary" href="<?= url('entry.php') ?>">New Check-in (புதிய நுழைவு)</a>
  <a class="btn btn-outline-secondary" href="<?= url('dashboard.php') ?>">Dashboard (முதன்மை பக்கம்)</a>
  
  <?php if (!$done && !$cancelled): ?>
    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
      <i class="bi bi-x-circle"></i> Cancel Ticket (டிக்கெட் ரத்து)
    </button>
  <?php endif; ?>
</div>

<!-- Cancel Confirmation Modal -->
<?php if (!$done && !$cancelled): ?>
<div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white border-secondary">
      <form method="post" action="<?= url('cancel_ticket.php') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="record_id" value="<?= (int)$r['record_id'] ?>">
        <div class="modal-header border-secondary">
          <h5 class="modal-title text-danger fw-bold"><i class="bi bi-exclamation-triangle"></i> Cancel Ticket / டிக்கெட் ரத்து செய்தல்</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body text-start">
          <p>Are you sure you want to cancel ticket <b class="text-warning"><?= e($r['ticket_number']) ?></b> (Vehicle: <b><?= e($r['vehicle_number']) ?></b>)?<br>
          <span class="small text-secondary">இந்த டிக்கெட்டை நிச்சயமாக ரத்து செய்ய விரும்புகிறீர்களா?</span></p>
          
          <div class="mb-3">
            <label class="form-label small text-secondary">Cancellation Reason / ரத்து செய்வதற்கான காரணம்:</label>
            <input type="text" name="cancel_reason" class="form-control bg-black text-white border-secondary" placeholder="e.g. Wrong vehicle number / தவறுதலாக போடப்பட்ட டிக்கெட்" required>
          </div>
          <small class="text-danger">* Note: Once cancelled, this will be removed from active parking count and revenue collection. <br>(குறிப்பு: ரத்து செய்தால் வாகன எண்ணிக்கை மற்றும் வசூல் தொகை இரண்டிலிருந்தும் உடனடியாக நீக்கப்படும்.)</small>
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close (வேண்டாம்)</button>
          <button type="submit" class="btn btn-danger">Confirm Cancel (ஆம், ரத்து செய்)</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php
$extraScripts = ($done || $cancelled) ? '' : '<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script><script>new QRCode(document.getElementById("qr"),{text:' . json_encode($r['ticket_number']) . ',width:140,height:140});</script>';
require APP_ROOT . '/includes/footer.php';