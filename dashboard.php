<?php
require_once __DIR__ . '/config/config.php';
require_login();

$one = function($sql, $params = []) use ($pdo) {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
};

// Date Filter (Default: Today)
$selected_date = isset($_GET['filter_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['filter_date']) ? $_GET['filter_date'] : date('Y-m-d');

// --- 1. Lifetime Totals (ஆரம்பம் முதல் இதுவரை) ---
$total_cash_alltime = (float)$one("
    SELECT (
        COALESCE((SELECT SUM(advance_paid) FROM parking_records WHERE status<>'Cancelled' AND (payment_mode='Cash' OR payment_mode IS NULL OR payment_mode='')), 0) +
        COALESCE((SELECT SUM(balance_paid) FROM parking_records WHERE status='Completed' AND payment_method='Cash'), 0)
    )
");

$total_online_alltime = (float)$one("
    SELECT (
        COALESCE((SELECT SUM(advance_paid) FROM parking_records WHERE status<>'Cancelled' AND payment_mode IN ('GPay','PhonePe','UPI','Razorpay','Online')), 0) +
        COALESCE((SELECT SUM(balance_paid) FROM parking_records WHERE status='Completed' AND (payment_method='UPI_Online' OR payment_method IN ('GPay','PhonePe','UPI','Razorpay'))), 0)
    )
");
$total_rev_alltime = $total_cash_alltime + $total_online_alltime;
$total_vehicles_alltime = (int)$one("SELECT COUNT(*) FROM parking_records WHERE status<>'Cancelled'");

// --- 2. Selected Date Analytics (தேர்ந்தெடுத்த நாளின் வசூல்) ---
$parked_now = (int)$one("SELECT COUNT(*) FROM parking_records WHERE status='Parked'");
$inflow_day = (int)$one("SELECT COUNT(*) FROM parking_records WHERE DATE(in_time)=? AND status<>'Cancelled'", [$selected_date]);

$adv_cash_day = (float)$one("SELECT COALESCE(SUM(advance_paid),0) FROM parking_records WHERE DATE(in_time)=? AND status<>'Cancelled' AND (payment_mode='Cash' OR payment_mode IS NULL OR payment_mode='')", [$selected_date]);
$adv_online_day = (float)$one("SELECT COALESCE(SUM(advance_paid),0) FROM parking_records WHERE DATE(in_time)=? AND status<>'Cancelled' AND payment_mode IN ('GPay','PhonePe','UPI','Razorpay','Online')", [$selected_date]);

$bal_cash_day = (float)$one("SELECT COALESCE(SUM(balance_paid),0) FROM parking_records WHERE status='Completed' AND payment_method='Cash' AND DATE(out_time)=?", [$selected_date]);
$bal_online_day = (float)$one("SELECT COALESCE(SUM(balance_paid),0) FROM parking_records WHERE status='Completed' AND (payment_method='UPI_Online' OR payment_method IN ('GPay','PhonePe','UPI','Razorpay')) AND DATE(out_time)=?", [$selected_date]);

$cash_day = $adv_cash_day + $bal_cash_day;
$online_day = $adv_online_day + $bal_online_day;
$rev_day = $cash_day + $online_day;

// --- 3. 7 Days Revenue Trend ---
$dates_7 = [];
$cash_7 = [];
$online_7 = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days", strtotime($selected_date)));
    $dates_7[] = date('d M', strtotime($d));
    
    $c = (float)$one("
        SELECT (
            COALESCE((SELECT SUM(advance_paid) FROM parking_records WHERE DATE(in_time)=? AND status<>'Cancelled' AND (payment_mode='Cash' OR payment_mode IS NULL OR payment_mode='')), 0) +
            COALESCE((SELECT SUM(balance_paid) FROM parking_records WHERE DATE(out_time)=? AND status='Completed' AND payment_method='Cash'), 0)
        )
    ", [$d, $d]);
    
    $o = (float)$one("
        SELECT (
            COALESCE((SELECT SUM(advance_paid) FROM parking_records WHERE DATE(in_time)=? AND status<>'Cancelled' AND payment_mode IN ('GPay','PhonePe','UPI','Razorpay','Online')), 0) +
            COALESCE((SELECT SUM(balance_paid) FROM parking_records WHERE DATE(out_time)=? AND status='Completed' AND (payment_method='UPI_Online' OR payment_method IN ('GPay','PhonePe','UPI','Razorpay'))), 0)
        )
    ", [$d, $d]);
    
    $cash_7[] = $c;
    $online_7[] = $o;
}

// --- 4. Vehicle Types Breakdown ---
$type_labels = [];
$type_counts = [];
$types_q = $pdo->prepare("
    SELECT t.name, COUNT(r.record_id) cnt 
    FROM vehicle_types t 
    LEFT JOIN parking_records r ON t.type_id=r.type_id AND DATE(r.in_time)=? AND r.status<>'Cancelled'
    WHERE t.is_active=1 
    GROUP BY t.type_id
");
$types_q->execute([$selected_date]);
foreach ($types_q->fetchAll() as $row) {
    $type_labels[] = $row['name'];
    $type_counts[] = (int)$row['cnt'];
}

$live = $pdo->query("SELECT r.*, t.name type_name FROM parking_records r JOIN vehicle_types t USING(type_id) WHERE r.status='Parked' ORDER BY r.in_time DESC")->fetchAll();
$pageTitle = 'Analytics Dashboard';
require APP_ROOT . '/includes/header.php';
?>

<style>
.stat-pro {
  background: rgba(22, 27, 34, 0.75);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
  backdrop-filter: blur(12px);
  padding: 1.25rem;
  transition: all 0.25s ease;
  position: relative;
  overflow: hidden;
}
.stat-pro:hover {
  transform: translateY(-3px);
  border-color: rgba(245, 158, 11, 0.35);
}
.stat-pro::before {
  content: '';
  position: absolute;
  top: 0; left: 0; width: 4px; height: 100%;
  background: var(--bar-color, #f59e0b);
}
.c-amber { --bar-color: #f59e0b; }
.c-blue { --bar-color: #38bdf8; }
.c-green { --bar-color: #10b981; }
.c-purple { --bar-color: #a855f7; }

.chart-pro {
  background: rgba(22, 27, 34, 0.7);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
  backdrop-filter: blur(10px);
  padding: 1.5rem;
}
</style>

<script>window.PE_SERVER_NOW = <?= time() ?>;</script>

<!-- Header & Date Filter -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div>
    <h3 class="fw-bold text-white mb-0">📊 Smart Parking Analytics</h3>
    <small class="text-secondary">Real-time Parking Management & Revenue Overview</small>
  </div>
  <form method="get" class="d-flex align-items-center gap-2 bg-dark p-2 rounded-3 border border-secondary shadow-sm">
    <label class="small text-secondary fw-semibold ps-1">Select Date:</label>
    <input type="date" name="filter_date" value="<?= e($selected_date) ?>" class="form-control form-control-sm bg-black text-white border-0" onchange="this.form.submit()">
    <?php if ($selected_date !== date('Y-m-d')): ?>
      <a href="dashboard.php" class="btn btn-sm btn-outline-warning">Today (இன்று)</a>
    <?php endif; ?>
  </form>
</div>

<!-- Layer 1: Lifetime Overview (மொத்த வசூல்) -->
<div class="row g-3 mb-4">
  <div class="col-md-3 col-sm-6">
    <div class="stat-pro c-amber">
      <span class="text-secondary small text-uppercase fw-bold">All-Time Revenue</span>
      <h3 class="fw-bold text-warning mt-1 mb-0"><?= money($total_rev_alltime) ?></h3>
      <small class="text-muted"><?= number_format($total_vehicles_alltime) ?> Vehicles Parked</small>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="stat-pro c-blue">
      <span class="text-secondary small text-uppercase fw-bold">All-Time Cash</span>
      <h3 class="fw-bold text-info mt-1 mb-0"><?= money($total_cash_alltime) ?></h3>
      <small class="text-muted">ரொக்க வசூல் (Cash)</small>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="stat-pro c-green">
      <span class="text-secondary small text-uppercase fw-bold">All-Time Online / UPI</span>
      <h3 class="fw-bold text-success mt-1 mb-0"><?= money($total_online_alltime) ?></h3>
      <small class="text-muted">GPay, PhonePe, Razorpay</small>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="stat-pro c-purple">
      <span class="text-secondary small text-uppercase fw-bold">Parked Right Now</span>
      <h3 class="fw-bold text-white mt-1 mb-0"><?= $parked_now ?></h3>
      <small class="text-muted">உள்ளே இருக்கும் வாகனங்கள்</small>
    </div>
  </div>
</div>

<!-- Layer 2: Daily Selected Metrics -->
<h6 class="text-secondary text-uppercase small fw-bold mb-2">📌 Daily Summary: <?= date('d M Y', strtotime($selected_date)) ?></h6>
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="stat-pro c-amber bg-black">
      <span class="text-secondary small">Daily Revenue (அன்றைய வருமானம்)</span>
      <h4 class="fw-bold text-white mt-1 mb-0"><?= money($rev_day) ?></h4>
      <small class="text-warning"><?= $inflow_day ?> Vehicles Inflow</small>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-pro c-blue bg-black">
      <span class="text-secondary small">Daily Cash Collected (ரொக்கம்)</span>
      <h4 class="fw-bold text-info mt-1 mb-0"><?= money($cash_day) ?></h4>
      <small class="text-secondary">Cash Mode</small>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-pro c-green bg-black">
      <span class="text-secondary small">Daily Online / UPI (ஆன்லைன்)</span>
      <h4 class="fw-bold text-success mt-1 mb-0"><?= money($online_day) ?></h4>
      <small class="text-secondary">GPay, PhonePe, QR & Cards</small>
    </div>
  </div>
</div>

<!-- Charts Section -->
<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="chart-pro h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold text-white mb-0">📈 7-Day Revenue Trend (Cash vs Online)</h6>
        <span class="badge bg-dark border border-secondary text-warning">Dynamic Analytics</span>
      </div>
      <canvas id="trendChart" height="150"></canvas>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="chart-pro h-100">
      <h6 class="fw-bold text-white mb-3">🍩 Payment Split (Selected Day)</h6>
      <canvas id="donutChart" height="180"></canvas>
      <div class="d-flex justify-content-around mt-3 text-center small">
        <div><span class="text-warning fw-bold">● Cash</span><br><?= money($cash_day) ?></div>
        <div><span class="text-success fw-bold">● Online</span><br><?= money($online_day) ?></div>
      </div>
    </div>
  </div>
</div>

<!-- Vehicle Types & Live Table -->
<div class="row g-3 mb-4">
  <div class="col-lg-5">
    <div class="chart-pro h-100">
      <h6 class="fw-bold text-white mb-3">🚗 Vehicles Inflow by Type</h6>
      <canvas id="typesBarChart" height="160"></canvas>
    </div>
  </div>
  
  <div class="col-lg-7">
    <div class="chart-pro h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold text-white mb-0">🚘 Vehicles Inside Now (<?= $parked_now ?>)</h6>
        <a href="<?= url('entry.php') ?>" class="btn btn-sm btn-accent">+ New Check-in</a>
      </div>
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" style="background: transparent;">
          <thead>
            <tr class="text-secondary small">
              <th>Ticket #</th><th>Vehicle No</th><th>Type</th><th>In Time</th><th>Elapsed</th><th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($live as $r): ?>
              <tr>
                <td class="fw-bold text-warning"><?= e($r['ticket_number']) ?></td>
                <td class="vnum"><?= e($r['vehicle_number']) ?></td>
                <td><span class="badge bg-secondary"><?= e($r['type_name']) ?></span></td>
                <td class="small"><?= e(date('d M, h:i A', strtotime($r['in_time']))) ?></td>
                <td><span class="elapsed" data-in="<?= strtotime($r['in_time']) ?>"></span></td>
                <td><a class="btn btn-sm btn-outline-warning py-0" href="<?= url('exit.php?q=' . urlencode($r['ticket_number'])) ?>">Check out</a></td>
              </tr>
            <?php endforeach; if (!$live): ?>
              <tr><td colspan="6" class="text-center text-secondary py-4">No vehicles parked inside currently.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php
$extraScripts = '
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
// 1. 7-Day Trend Line Chart
const ctxTrend = document.getElementById("trendChart").getContext("2d");
const gradCash = ctxTrend.createLinearGradient(0, 0, 0, 300);
gradCash.addColorStop(0, "rgba(245, 158, 11, 0.45)");
gradCash.addColorStop(1, "rgba(245, 158, 11, 0.0)");

const gradOnline = ctxTrend.createLinearGradient(0, 0, 0, 300);
gradOnline.addColorStop(0, "rgba(16, 185, 129, 0.45)");
gradOnline.addColorStop(1, "rgba(16, 185, 129, 0.0)");

new Chart(ctxTrend, {
  type: "line",
  data: {
    labels: ' . json_encode($dates_7) . ',
    datasets: [
      {
        label: "Cash Revenue (₹)",
        data: ' . json_encode($cash_7) . ',
        borderColor: "#f59e0b",
        backgroundColor: gradCash,
        fill: true,
        tension: 0.35,
        borderWidth: 2,
        pointRadius: 4,
        pointBackgroundColor: "#f59e0b"
      },
      {
        label: "Online / UPI (₹)",
        data: ' . json_encode($online_7) . ',
        borderColor: "#10b981",
        backgroundColor: gradOnline,
        fill: true,
        tension: 0.35,
        borderWidth: 2,
        pointRadius: 4,
        pointBackgroundColor: "#10b981"
      }
    ]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { labels: { color: "#9ca3af", font: { size: 12 } } },
      tooltip: {
        callbacks: {
          label: function(c) { return " " + c.dataset.label + ": ₹" + Number(c.parsed.y).toFixed(2); }
        }
      }
    },
    scales: {
      x: { grid: { color: "rgba(255,255,255,0.05)" }, ticks: { color: "#9ca3af" } },
      y: { grid: { color: "rgba(255,255,255,0.05)" }, ticks: { color: "#9ca3af" }, beginAtZero: true }
    }
  }
});

// 2. Day Payment Split Donut Chart
new Chart(document.getElementById("donutChart"), {
  type: "doughnut",
  data: {
    labels: ["Cash", "Online / UPI"],
    datasets: [{
      data: [' . $cash_day . ', ' . $online_day . '],
      backgroundColor: ["#f59e0b", "#10b981"],
      borderWidth: 0,
      hoverOffset: 4
    }]
  },
  options: {
    cutout: "75%",
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: function(c) { return " " + c.label + ": ₹" + Number(c.parsed).toFixed(2); }
        }
      }
    }
  }
});

// 3. Vehicle Types Horizontal Bar Chart
new Chart(document.getElementById("typesBarChart"), {
  type: "bar",
  data: {
    labels: ' . json_encode($type_labels) . ',
    datasets: [{
      label: "Count",
      data: ' . json_encode($type_counts) . ',
      backgroundColor: "#38bdf8",
      borderRadius: 6
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { color: "rgba(255,255,255,0.05)" }, ticks: { color: "#9ca3af", stepSize: 1 } },
      y: { grid: { color: "rgba(255,255,255,0.05)" }, ticks: { color: "#9ca3af" } }
    }
  }
});
</script>';

require APP_ROOT . '/includes/footer.php';