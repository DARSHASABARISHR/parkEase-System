<?php
require_once __DIR__ . '/config/config.php';
require_login();
const SEL = "SELECT r.*, t.name type_name, t.daily_rate FROM parking_records r JOIN vehicle_types t USING(type_id) WHERE r.status='Parked'";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $st = $pdo->prepare(SEL . ' AND r.record_id=?'); 
    $st->execute([(int)$_POST['record_id']]); 
    $r = $st->fetch();
    
    if (!$r) { 
        flash('danger', 'This vehicle is not parked (it may already be checked out).'); 
        redirect('exit.php'); 
    }
    
    $now = date('Y-m-d H:i:s');
    $b = calc_parking_bill($r['in_time'], $now, $r['daily_rate'], $r['advance_paid']);
    $method = in_array($_POST['method'] ?? '', ['Cash', 'UPI_Online', 'Razorpay']) ? $_POST['method'] : 'Cash';
    
    if ($b['pending'] > 0 && $method === 'Cash' && (float)($_POST['tendered'] ?? 0) < $b['pending']) {
        flash('danger', 'Cash tendered is less than the balance due (' . money($b['pending']) . ').'); 
        redirect('exit.php?q=' . urlencode($r['ticket_number']));
    }
    
    if (!settle_record($pdo, $r, $now, $b, $method)) {
        flash('danger', 'Already settled.');
    }
    redirect('ticket.php?id=' . $r['record_id']);
}

$q = strtoupper(trim($_GET['q'] ?? '')); $r = null; $b = null;
if ($q !== '') {
    $st = $pdo->prepare(SEL . ' AND (r.ticket_number=? OR r.vehicle_number=?) ORDER BY r.in_time DESC LIMIT 1');
    $st->execute([$q, preg_replace('/[^A-Z0-9]/', '', $q)]); 
    $r = $st->fetch();
    if ($r) $b = calc_parking_bill($r['in_time'], date('Y-m-d H:i:s'), $r['daily_rate'], $r['advance_paid']);
    else flash('warning', 'No parked vehicle matches "' . $q . '".');
}
$cfg = rzp_config();
$pageTitle = 'Check-out';
require APP_ROOT . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-7">
<div class="glass p-4 mb-3">
  <h4 class="mb-3">Vehicle check-out</h4>
  <form method="get" class="d-flex gap-2">
    <input name="q" value="<?= e($q) ?>" class="form-control form-control-lg vnum" placeholder="Vehicle or ticket number" required autofocus>
    <button class="btn btn-accent px-4">Find</button>
  </form>
</div>
<?php if ($r): ?>
<div class="glass p-4">
  <div class="d-flex justify-content-between align-items-start">
    <div>
      <div class="vnum fs-4"><?= e($r['vehicle_number']) ?></div>
      <small class="text-secondary"><?= e($r['type_name']) ?> · <?= e($r['ticket_number']) ?></small>
    </div>
    <div class="text-end">
      <small class="text-secondary">Daily rate</small>
      <div><?= money($r['daily_rate']) ?></div>
    </div>
  </div>
  
  <div class="my-3">
  <?php if ($b['overstay']): ?>
    <span class="badge text-bg-danger fs-6">Overstay Surcharge Applicable - Day <?= $b['days'] ?> Triggered</span>
  <?php else: ?>
    <span class="badge text-bg-success fs-6">Within first 24-hour cycle</span> 
    <small class="text-secondary">Day 2 starts after <?= e(date('d M, h:i A', strtotime($b['cycle_end']))) ?></small>
  <?php endif; ?>
  </div>
  
  <div class="row text-center g-3">
    <div class="col-6 col-md-3"><small class="text-secondary">Total hours parked</small><div class="fs-5"><?= number_format($b['hours'], 2) ?> hrs</div><small class="text-secondary"><?= e(fmt_duration($b['minutes'])) ?></small></div>
    <div class="col-6 col-md-3"><small class="text-secondary">Completed cycles</small><div class="fs-5"><?= $b['days'] ?> Day<?= $b['days'] > 1 ? 's' : '' ?></div><small class="text-secondary"><?= $b['days'] ?> × <?= money($b['rate']) ?> = <?= money($b['total']) ?></small></div>
    <div class="col-6 col-md-3"><small class="text-secondary">Initial / paid amount</small><div class="fs-5"><?= money($b['advance']) ?></div></div>
    <div class="col-6 col-md-3"><small class="text-secondary">Pending balance due</small><div class="bill-total fs-3"><?= money($b['pending']) ?></div></div>
  </div>
  <hr>
  
  <?php if ($b['pending'] <= 0): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="record_id" value="<?= $r['record_id'] ?>">
      <p class="text-success">Fully paid in advance. Nothing to collect.</p>
      <button class="btn btn-accent w-100 py-2">Check out vehicle</button>
    </form>
  <?php else: ?>
  <form method="post" id="payForm"><?= csrf_field() ?>
    <input type="hidden" name="record_id" value="<?= $r['record_id'] ?>">
    
    <label class="form-label fw-bold">கட்டண முறை (Payment Method)</label>
    <div class="btn-group w-100 mb-3">
      <input type="radio" class="btn-check" name="method" id="mc" value="Cash" checked>
      <label class="btn btn-outline-warning" for="mc"><i class="bi bi-cash"></i> Cash</label>
      
      <input type="radio" class="btn-check" name="method" id="mqr" value="UPI_Online">
      <label class="btn btn-outline-warning" for="mqr"><i class="bi bi-qr-code-scan"></i> GPay / PhonePe (QR)</label>
      
      <input type="radio" class="btn-check" name="method" id="mu" value="Razorpay">
      <label class="btn btn-outline-warning" for="mu"><i class="bi bi-credit-card"></i> Razorpay</label>
    </div>

    <!-- Cash Box -->
    <div id="cashBox" class="mb-3">
      <label class="form-label">Cash tendered (₹)</label>
      <input type="number" step="0.01" min="0" name="tendered" id="tendered" class="form-control form-control-lg">
      <div class="mt-2">Change to return: <b id="change">₹0.00</b></div>
    </div>

    <!-- Dynamic UPI QR Box -->
    <div id="upiBox" class="mb-3 d-none text-center">
      <div class="p-3 bg-white rounded d-inline-block shadow my-2">
        <div id="exitDynamicQr"></div>
      </div>
      <h4 class="text-warning fw-bold mt-2">செலுத்த வேண்டிய தொகை: <?= money($b['pending']) ?></h4>
      <p class="text-secondary small mb-0">வாடிக்கையாளர் ஸ்கேன் செய்யும்போதே தொகை தானாக வந்துவிடும். UPI PIN மட்டும் செலுத்தவும்.</p>
    </div>

    <!-- Razorpay Box -->
    <div id="rzpBox" class="mb-3 d-none">
      <?php if ($cfg['ready']): ?>
        <div class="small text-secondary mb-2">Opens Razorpay checkout (<?= e($cfg['mode']) ?> mode) with cards, netbanking & UPI.</div>
      <?php else: ?>
        <div class="alert alert-warning py-2">Razorpay keys are not set. Add them in config/config.php.</div>
      <?php endif; ?>
      <button type="button" id="rzpBtn" class="btn btn-accent w-100 py-2" <?= $cfg['ready'] ? '' : 'disabled' ?>>Pay <?= money($b['pending']) ?> with Razorpay</button>
    </div>

    <button type="submit" class="btn btn-accent w-100 py-2" id="payBtn">Confirm cash and check out</button>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>
</div></div>

<?php
$extraScripts = '';
if ($r && $b['pending'] > 0) {
    $extraScripts = '<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>' .
                    '<script src="https://checkout.razorpay.com/v1/checkout.js"></script>' .
                    '<script>
var due = ' . json_encode((float)$b['pending']) . ';
var CSRF = ' . json_encode(csrf_token()) . ';
var RID = ' . (int)$r['record_id'] . ';
var BASE = ' . json_encode(BASE_URL) . ';
var myUPI = ' . json_encode(defined('UPI_ID') ? UPI_ID : '7845156026@upi') . ';
var payeeName = ' . json_encode(defined('UPI_PAYEE_NAME') ? UPI_PAYEE_NAME : 'ParkEase') . ';

var qrGenerated = false;
function renderExitQR() {
  if (qrGenerated) return;
  var container = document.getElementById("exitDynamicQr");
  container.innerHTML = "";
  var upiString = "upi://pay?pa=" + encodeURIComponent(myUPI) + 
                  "&pn=" + encodeURIComponent(payeeName) + 
                  "&am=" + Number(due).toFixed(2) + 
                  "&cu=INR&tn=" + encodeURIComponent("Parking Balance Fee");

  new QRCode(container, {
    text: upiString,
    width: 210,
    height: 210,
    correctLevel: QRCode.CorrectLevel.M
  });
  qrGenerated = true;
}

function sync() {
  var method = document.querySelector("[name=method]:checked").value;
  var isCash = (method === "Cash");
  var isUpi = (method === "UPI_Online");
  var isRzp = (method === "Razorpay");

  document.getElementById("cashBox").classList.toggle("d-none", !isCash);
  document.getElementById("upiBox").classList.toggle("d-none", !isUpi);
  document.getElementById("rzpBox").classList.toggle("d-none", !isRzp);

  var payBtn = document.getElementById("payBtn");
  if (isRzp) {
    payBtn.classList.add("d-none");
  } else {
    payBtn.classList.remove("d-none");
    payBtn.textContent = isUpi ? "Confirm UPI Payment & Check out" : "Confirm cash and check out";
  }

  document.getElementById("tendered").required = isCash;
  if (isUpi) {
    renderExitQR();
  }
}

document.querySelectorAll("[name=method]").forEach(function(x){ x.addEventListener("change", sync); });
sync();

document.getElementById("tendered").addEventListener("input", function(){
  var c = parseFloat(this.value || 0) - due;
  document.getElementById("change").textContent = "₹" + (c > 0 ? c : 0).toFixed(2);
});

function post(path, fd) {
  return fetch(BASE + path, { method: "POST", body: fd, credentials: "same-origin" }).then(function(res){ return res.json(); });
}

document.getElementById("rzpBtn").addEventListener("click", function(){
  var fd = new FormData();
  fd.append("csrf", CSRF);
  fd.append("record_id", RID);
  post("/api/rzp_order.php", fd).then(function(o){
    if (!o.ok) { alert(o.error); return; }
    new Razorpay({
      key: o.key,
      amount: o.amount,
      currency: "INR",
      order_id: o.order_id,
      name: o.name,
      description: o.desc,
      prefill: { contact: o.contact, email: o.email },
      theme: { color: "#d9a441" },
      handler: function(p){
        var f = new FormData();
        f.append("csrf", CSRF);
        f.append("record_id", RID);
        f.append("razorpay_payment_id", p.razorpay_payment_id);
        f.append("razorpay_order_id", p.razorpay_order_id);
        f.append("razorpay_signature", p.razorpay_signature);
        post("/api/rzp_verify.php", f).then(function(v){
          if (v.ok) { location.href = v.redirect; } else { alert(v.error); }
        });
      }
    }).open();
  }).catch(function(){ alert("Could not reach the server. Try again."); });
});
</script>';
}
require APP_ROOT . '/includes/footer.php';