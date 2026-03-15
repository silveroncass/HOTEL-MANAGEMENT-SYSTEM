<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin('admin');
$active_page = 'payments';
$db = getDB();
$msg = $err = '';

// ── ADD MANUAL PAYMENT ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='add_payment') {
    $bid    = (int)$_POST['booking_id'];
    $amt    = (float)$_POST['amount_paid'];
    $method = $_POST['payment_method'] ?? 'cash';
    $ref    = trim($_POST['reference_no'] ?? '');
    $notes  = trim($_POST['notes'] ?? '');
    try {
        $db->prepare("INSERT INTO payments (booking_id,amount_paid,payment_method,reference_no,notes,received_by,payment_date) VALUES (?,?,?,?,?,?,NOW())")
           ->execute([$bid,$amt,$method,$ref?:null,$notes?:null,$_SESSION['user_id']]);
        // Update booking payment_status
        $bal = $db->prepare("SELECT balance_due FROM v_booking_details WHERE booking_id=?");
        $bal->execute([$bid]); $bal = (float)$bal->fetchColumn();
        if ($bal <= 0) {
            $db->prepare("UPDATE bookings SET payment_status='paid' WHERE booking_id=?")->execute([$bid]);
        }
        $msg = "Payment of ₱".number_format($amt,2)." recorded successfully.";
    } catch(Exception $e) { $err = $e->getMessage(); }
}

// ── FILTERS ───────────────────────────────────────────────────
$filter_method = $_GET['method'] ?? '';
$filter_date   = $_GET['date']   ?? '';
$filter_status = $_GET['status'] ?? '';

$sql = "SELECT p.*, b.booking_ref, b.total_amount,
    CONCAT(u.first_name,' ',u.last_name) AS guest_name,
    r.room_number, rt.type_name,
    CONCAT(s.first_name,' ',s.last_name) AS received_by_name
FROM payments p
JOIN bookings b ON p.booking_id = b.booking_id
JOIN users u    ON b.user_id    = u.user_id
JOIN rooms r    ON b.room_id    = r.room_id
JOIN room_types rt ON r.type_id = rt.type_id
LEFT JOIN users s ON p.received_by = s.user_id
WHERE 1=1";
$params = [];
if ($filter_method) { $sql .= " AND p.payment_method=?"; $params[] = $filter_method; }
if ($filter_date)   { $sql .= " AND DATE(p.payment_date)=?"; $params[] = $filter_date; }
if ($filter_status) { $sql .= " AND p.payment_status=?"; $params[] = $filter_status; }
$sql .= " ORDER BY p.payment_id DESC";

$stmt = $db->prepare($sql); $stmt->execute($params);
$payments = $stmt->fetchAll();

// ── STATS ─────────────────────────────────────────────────────
$today     = date('Y-m-d');
$total_rev = $db->query("SELECT COALESCE(SUM(amount_paid),0) FROM payments WHERE payment_status='completed'")->fetchColumn();
$today_rev = $db->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM payments WHERE payment_status='completed' AND DATE(payment_date)=?");
$today_rev->execute([$today]); $today_rev = $today_rev->fetchColumn();
$month_rev = $db->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM payments WHERE payment_status='completed' AND MONTH(payment_date)=MONTH(NOW()) AND YEAR(payment_date)=YEAR(NOW())");
$month_rev->execute([]); $month_rev = $month_rev->fetchColumn();
$unpaid_cnt = $db->query("SELECT COUNT(*) FROM bookings WHERE payment_status='unpaid' AND status NOT IN ('cancelled')")->fetchColumn();

// Bookings with balance due
$payable = $db->query("SELECT * FROM v_booking_details WHERE booking_status IN ('confirmed','checked_in','checked_out') AND balance_due > 0 ORDER BY check_in_date DESC")->fetchAll();

include __DIR__ . '/../includes/sidebar.php';
?>
<div class="topbar">
  <div class="topbar-title"><h1>Payments</h1><p>Payment records and revenue overview</p></div>
</div>
<div class="page-content">

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
<div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- STATS -->
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="stat-card primary">
      <div class="stat-top"><span class="stat-label">Total Revenue</span><div class="stat-icon" style="background:rgba(255,255,255,0.15);color:#fff"><i class="bi bi-cash-stack"></i></div></div>
      <div class="stat-value" style="font-size:1.4rem">₱<?= number_format($total_rev,2) ?></div>
      <div class="stat-change" style="color:rgba(255,255,255,0.7)">All time collected</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat-card">
      <div class="stat-top"><span class="stat-label">Today's Revenue</span><div class="stat-icon" style="background:#dcfce7;color:#16a34a"><i class="bi bi-calendar-check"></i></div></div>
      <div class="stat-value" style="font-size:1.4rem">₱<?= number_format($today_rev,2) ?></div>
      <div class="stat-change" style="color:var(--text-light)"><?= date('F d, Y') ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat-card">
      <div class="stat-top"><span class="stat-label">This Month</span><div class="stat-icon" style="background:#e0f2fe;color:#0284c7"><i class="bi bi-graph-up-arrow"></i></div></div>
      <div class="stat-value" style="font-size:1.4rem">₱<?= number_format($month_rev,2) ?></div>
      <div class="stat-change" style="color:var(--text-light)"><?= date('F Y') ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat-card">
      <div class="stat-top"><span class="stat-label">Unpaid Bookings</span><div class="stat-icon" style="background:#fef9c3;color:#ca8a04"><i class="bi bi-exclamation-circle"></i></div></div>
      <div class="stat-value" style="font-size:1.4rem"><?= $unpaid_cnt ?></div>
      <div class="stat-change" style="color:var(--text-light)">Need payment</div>
    </div>
  </div>
</div>

<!-- FILTERS -->
<div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap">
  <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <select name="method" class="form-select form-select-sm" style="width:150px" onchange="this.form.submit()">
      <option value="">All Methods</option>
      <option value="cash" <?= $filter_method==='cash'?'selected':'' ?>>Cash</option>
      <option value="gcash" <?= $filter_method==='gcash'?'selected':'' ?>>GCash</option>
      <option value="credit_card" <?= $filter_method==='credit_card'?'selected':'' ?>>Credit Card</option>
      <option value="debit_card" <?= $filter_method==='debit_card'?'selected':'' ?>>Debit Card</option>
      <option value="paymaya" <?= $filter_method==='paymaya'?'selected':'' ?>>PayMaya</option>
      <option value="bank_transfer" <?= $filter_method==='bank_transfer'?'selected':'' ?>>Bank Transfer</option>
    </select>
    <input type="date" name="date" class="form-control form-control-sm" style="width:150px" value="<?= htmlspecialchars($filter_date) ?>" onchange="this.form.submit()">
    <select name="status" class="form-select form-select-sm" style="width:140px" onchange="this.form.submit()">
      <option value="">All Status</option>
      <option value="completed" <?= $filter_status==='completed'?'selected':'' ?>>Completed</option>
      <option value="pending" <?= $filter_status==='pending'?'selected':'' ?>>Pending</option>
      <option value="refunded" <?= $filter_status==='refunded'?'selected':'' ?>>Refunded</option>
    </select>
    <?php if ($filter_method||$filter_date||$filter_status): ?>
    <a href="payments.php" class="btn btn-sm btn-outline-secondary">Clear</a>
    <?php endif; ?>
  </form>
</div>

<!-- PAYMENTS TABLE -->
<div class="panel">
  <div class="panel-header">
    <div class="panel-title">Payment Records <span style="font-weight:400;font-size:0.82rem;color:var(--text-light)">(<?= count($payments) ?> records)</span></div>
  </div>
  <div style="overflow-x:auto">
  <table class="gs-table">
    <thead>
      <tr><th>#</th><th>Guest</th><th>Booking Ref</th><th>Room</th><th>Amount</th><th>Method</th><th>Reference</th><th>Date</th><th>Status</th><th>Received By</th><th>Receipt</th></tr>
    </thead>
    <tbody>
    <?php if (empty($payments)): ?>
    <tr><td colspan="11" class="text-center py-4" style="color:var(--text-light)"><i class="bi bi-receipt" style="font-size:1.8rem;display:block"></i><p class="mt-2 mb-0">No payment records found</p></td></tr>
    <?php else: ?>
    <?php foreach ($payments as $p):
      $badge = $p['payment_status']==='completed' ? 'badge-confirmed' : ($p['payment_status']==='refunded'?'badge-cancelled':'badge-pending');
    ?>
    <tr>
      <td style="color:var(--text-light);font-size:0.78rem"><?= $p['payment_id'] ?></td>
      <td style="font-weight:600"><?= htmlspecialchars($p['guest_name']) ?></td>
      <td><code style="font-size:0.76rem"><?= $p['booking_ref'] ?></code></td>
      <td><?= $p['room_number'] ?> <small style="color:var(--text-light)"><?= $p['type_name'] ?></small></td>
      <td style="font-weight:700;color:var(--green-main)">₱<?= number_format($p['amount_paid'],2) ?></td>
      <td><?= ucfirst(str_replace('_',' ',$p['payment_method'])) ?></td>
      <td style="font-size:0.78rem;color:var(--text-mid)"><?= htmlspecialchars($p['reference_no'] ?? '—') ?></td>
      <td style="font-size:0.8rem"><?= $p['payment_date'] ? date('M d, Y H:i',strtotime($p['payment_date'])) : '—' ?></td>
      <td><span class="badge-gs <?= $badge ?>"><?= ucfirst($p['payment_status']) ?></span></td>
      <td style="font-size:0.8rem"><?= htmlspecialchars($p['received_by_name'] ?? 'System') ?></td>
      <td><a href="receipt.php?pid=<?= $p['payment_id'] ?>" target="_blank" class="btn-gs btn-gs-sm" style="padding:4px 10px"><i class="bi bi-printer"></i></a></td>
    </tr>
    <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

</div><!-- /page-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
