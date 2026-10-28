<?php
require_once __DIR__ . '/config/config.php';
require_login();
$types = $pdo->query('SELECT * FROM vehicle_types WHERE is_active=1 ORDER BY type_id')->fetchAll();
$old = ['vehicle_number' => '', 'type_id' => '', 'driver_name' => '', 'driver_mobile' => '', 'driver_email' => '', 'payment_mode' => 'Cash'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($old as $k => $_) $old[$k] = trim($_POST[$k] ?? '');
    $vn = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $old['vehicle_number']));
    $payment_mode = in_array($old['payment_mode'], ['Cash', 'GPay', 'PhonePe', 'UPI', 'Razorpay']) ? $old['payment_mode'] : 'Cash';
    
    $err = null; $trow = null;
    $tcheck = $pdo->prepare('SELECT type_id, daily_rate FROM vehicle_types WHERE type_id=? AND is_active=1'); 
    $tcheck->execute([(int)$old['type_id']]);
    
    if (!preg_match('/^[A-Z0-9]{4,15}$/', $vn)) $err = 'Enter a valid vehicle number (4–15 letters/digits).';
    elseif (!($trow = $tcheck->fetch())) $err = 'Choose a vehicle type.';
    elseif ($old['driver_mobile'] !== '' && !preg_match('/^[0-9]{10}$/', $old['driver_mobile'])) $err = 'Mobile number must be 10 digits.';
    elseif ($old['driver_email'] !== '' && !filter_var($old['driver_email'], FILTER_VALIDATE_EMAIL)) $err = 'Enter a valid email address.';
    else {
        $d = $pdo->prepare("SELECT ticket_number FROM parking_records WHERE vehicle_number=? AND status='Parked'"); 
        $d->execute([$vn]);
        if ($t = $d->fetchColumn()) $err = "$vn is already parked under ticket $t.";
    }
    
    if ($err) flash('danger', $err);
    else {
        $advance_collected = isset($_POST['collect_advance']) ? $trow['daily_rate'] : 0;
        
        for ($i = 0; $i < 10; $i++) {
            $ticket = 'PK-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            try {
                $pdo->prepare('INSERT INTO parking_records (ticket_number,vehicle_number,type_id,driver_name,driver_mobile,driver_email,advance_paid,payment_mode,in_time) VALUES (?,?,?,?,?,?,?,?,NOW())')
                    ->execute([$ticket, $vn, (int)$old['type_id'], $old['driver_name'] ?: null, $old['driver_mobile'] ?: null, $old['driver_email'] ?: null, $advance_collected, $payment_mode]);
                $id = $pdo->lastInsertId(); break;
            } catch (PDOException $ex) { 
                if ($ex->getCode() != 23000) throw $ex; 
                $id = null; 
            }
        }
        
        if (!$id) { 
            flash('danger', 'Could not generate a ticket. Try again.'); 
        } else {
            if ($old['driver_email']) {
                $html = '<div style="font-family:Arial;padding:16px"><h3>' . e(setting('company_name')) . ' entry slip</h3><p>Ticket <b>' . e($ticket) . '</b><br>Vehicle <b>' . e($vn) . '</b><br>Payment Mode <b>' . e($payment_mode) . '</b><br>Entry ' . date('d M Y, h:i A') . '</p><p style="font-size:12px;color:#777">Notice: Daily parking rate covers up to 24 hours from entry. Helpline: ' . e(defined('PARKING_HELPLINE') ? PARKING_HELPLINE : '+91 7845156026') . '</p></div>';
                send_mail($old['driver_email'], "Parking ticket $ticket", $html);
            }
            redirect('ticket.php?id=' . $id);
        }
    }
}

$pageTitle = 'Check-in';
require APP_ROOT . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-6"><div class="glass p-4">
  <h4 class="mb-3">Vehicle check-in</h4>
  <form method="post" id="entryForm" autocomplete="off"><?= csrf_field() ?>
    <label class="form-label">Vehicle number</label>
    <input name="vehicle_number" id="vNumber" class="form-control form-control-lg vnum mb-3" value="<?= e($old['vehicle_number']) ?>" placeholder="TN45AB1234" maxlength="15" required autofocus>
    
    <label class="form-label">Vehicle type</label>
    <div class="row g-2 mb-3">
    <?php foreach ($types as $t): ?>
      <div class="col-6"><input type="radio" class="btn-check" name="type_id" id="t<?= $t['type_id'] ?>" value="<?= $t['type_id'] ?>" <?= (string)$old['type_id'] === (string)$t['type_id'] ? 'checked' : '' ?> required>
        <label class="btn btn-outline-warning w-100 py-3" for="t<?= $t['type_id'] ?>" data-rate="<?= e($t['daily_rate']) ?>"><i class="bi <?= e($t['icon']) ?> fs-4 d-block"></i><?= e($t['name']) ?><small class="d-block opacity-75"><?= money($t['daily_rate']) ?>/day</small></label></div>
    <?php endforeach; ?>
    </div>
    
    <div class="row g-3 mb-3">
      <div class="col-md-6"><label class="form-label">Driver mobile (optional)</label><input name="driver_mobile" id="dMobile" class="form-control" maxlength="10" inputmode="numeric" value="<?= e($old['driver_mobile']) ?>"></div>
      <div class="col-md-6"><label class="form-label">Driver email (for e-slip)</label><input type="email" name="driver_email" id="dEmail" class="form-control" value="<?= e($old['driver_email']) ?>"></div>
      <div class="col-12"><label class="form-label">Driver name (optional)</label><input name="driver_name" id="dName" class="form-control" value="<?= e($old['driver_name']) ?>"></div>
    </div>

    <!-- Advance collection & Payment Mode -->
    <div class="card bg-dark border-secondary p-3 mb-3">
      <div class="form-check form-switch mb-2">
        <input class="form-check-input" type="checkbox" name="collect_advance" id="adv" checked>
        <label class="form-check-label fw-bold" for="adv">Collect first 24-hr charge now <b id="advAmt" class="text-warning"></b></label>
      </div>

      <div id="paymentModeContainer" class="mt-2">
        <label class="form-label small text-muted">Payment Mode</label>
        <select name="payment_mode" id="paymentMode" class="form-select bg-dark text-white border-secondary">
          <option value="Cash" <?= $old['payment_mode'] === 'Cash' ? 'selected' : '' ?>>💵 Cash (ரொக்கம்)</option>
          <option value="GPay" <?= $old['payment_mode'] === 'GPay' ? 'selected' : '' ?>>📱 Google Pay (Dynamic QR)</option>
          <option value="PhonePe" <?= $old['payment_mode'] === 'PhonePe' ? 'selected' : '' ?>>🟣 PhonePe (Dynamic QR)</option>
          <option value="UPI" <?= $old['payment_mode'] === 'UPI' ? 'selected' : '' ?>>⚡ Other UPI (Dynamic QR)</option>
          <option value="Razorpay" <?= $old['payment_mode'] === 'Razorpay' ? 'selected' : '' ?>>💳 Online (Razorpay Test Mode)</option>
        </select>
        <input type="hidden" name="razorpay_payment_id" id="rzpPaymentId" value="">
      </div>
    </div>

    <button type="submit" class="btn btn-accent w-100 py-2">Issue ticket</button>
  </form>
</div></div></div>

<!-- Dynamic UPI QR Modal Popup -->
<div class="modal fade" id="qrModal" tabindex="-1" aria-labelledby="qrModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white border-secondary text-center p-3">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title w-100 fw-bold" id="qrModalLabel">📱 Scan & Pay (Auto Amount)</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="p-2 bg-white rounded d-inline-block shadow my-2">
          <div id="dynamicQrContainer"></div>
        </div>
        <h4 class="text-warning fw-bold mt-2 mb-1" id="modalPayAmt">₹0.00</h4>
        <p class="text-secondary small mb-0">வாடிக்கையாளர் ஸ்கேன் செய்யும்போதே தொகை தானாக வந்துவிடும். UPI PIN மட்டும் செலுத்தவும்.</p>
      </div>
      <div class="modal-footer border-0 justify-content-center pt-0">
        <button type="button" class="btn btn-success px-4" data-bs-dismiss="modal">Payment Done (சரி)</button>
      </div>
    </div>
  </div>
</div>

<?php
$extraScripts = '<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>' .
                '<script src="https://checkout.razorpay.com/v1/checkout.js"></script>' .
                '<script>
var myUPI = ' . json_encode(defined('UPI_ID') ? UPI_ID : '7845156026@upi') . ';
var payeeName = ' . json_encode(defined('UPI_PAYEE_NAME') ? UPI_PAYEE_NAME : 'ParkEase') . ';
var currentRate = 0;

function generateDynamicQR(amount) {
  var qrContainer = document.getElementById("dynamicQrContainer");
  qrContainer.innerHTML = "";
  var upiString = "upi://pay?pa=" + encodeURIComponent(myUPI) + 
                  "&pn=" + encodeURIComponent(payeeName) + 
                  "&am=" + Number(amount).toFixed(2) + 
                  "&cu=INR&tn=" + encodeURIComponent("Parking Fee");

  new QRCode(qrContainer, {
    text: upiString,
    width: 220,
    height: 220,
    correctLevel: QRCode.CorrectLevel.M
  });
}

function launchRazorpay(amount) {
  fetch("create_order.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: "amount=" + encodeURIComponent(amount)
  })
  .then(function(res){ return res.json(); })
  .then(function(data){
    if (data.error) {
      alert("Razorpay Error: " + data.error);
      return;
    }
    var options = {
      "key": data.key,
      "amount": data.amount,
      "currency": "INR",
      "name": "ParkEase",
      "description": "Parking Advance Fee (Test Mode)",
      "order_id": data.order_id,
      "handler": function (response) {
        document.getElementById("rzpPaymentId").value = response.razorpay_payment_id;
        alert("Payment Successful! ID: " + response.razorpay_payment_id);
      },
      "prefill": {
        "name": document.getElementById("dName").value || "Customer",
        "email": document.getElementById("dEmail").value || "",
        "contact": document.getElementById("dMobile").value || ""
      },
      "theme": { "color": "#f59e0b" }
    };
    var rzp = new Razorpay(options);
    rzp.open();
  })
  .catch(function(err){
    console.error(err);
    alert("Razorpay payment error. Check create_order.php.");
  });
}

document.querySelectorAll("[name=type_id]").forEach(function(r){
  r.addEventListener("change", function(){
    currentRate = Number(document.querySelector("label[for="+r.id+"]").dataset.rate);
    document.getElementById("advAmt").textContent = "(₹" + currentRate.toFixed(2) + ")";
    document.getElementById("modalPayAmt").textContent = "Amount: ₹" + currentRate.toFixed(2);
  });
});

var c = document.querySelector("[name=type_id]:checked");
if(c) c.dispatchEvent(new Event("change"));

var advCheck = document.getElementById("adv");
var payContainer = document.getElementById("paymentModeContainer");
if(advCheck && payContainer) {
  advCheck.addEventListener("change", function(){
    payContainer.style.display = this.checked ? "block" : "none";
  });
}

var paySelect = document.getElementById("paymentMode");
if(paySelect) {
  paySelect.addEventListener("change", function(){
    if(["GPay", "PhonePe", "UPI"].includes(this.value)) {
      generateDynamicQR(currentRate);
      var modal = new bootstrap.Modal(document.getElementById("qrModal"));
      modal.show();
    } else if (this.value === "Razorpay") {
      launchRazorpay(currentRate);
    }
  });
}
</script>';

require APP_ROOT . '/includes/footer.php';