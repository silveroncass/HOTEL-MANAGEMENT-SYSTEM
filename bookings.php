<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin('staff');
$active_page = 'bookings';
$db = getDB();

$filter_status = $_GET['status'] ?? '';
$filter_date   = $_GET['date']   ?? '';
$sql    = "SELECT * FROM v_booking_details WHERE 1=1";
$params = [];
if ($filter_status) { $sql .= " AND booking_status=?"; $params[] = $filter_status; }
if ($filter_date)   { $sql .= " AND check_in_date=?";  $params[] = $filter_date; }
$sql .= " ORDER BY created_at DESC";
$stmt = $db->prepare($sql); $stmt->execute($params);
$bookings = $stmt->fetchAll();

include __DIR__ . '/../includes/sidebar.php';
?>
<div class="topbar">
  <div class="topbar-title"><h1>Bookings</h1><p>View all reservations</p></div>
</div>
<div class="page-content">

<!-- Filters -->
<div class="panel mb-3">
  <div class="panel-body py-3">
    <form method="GET" class="d-flex gap-3 flex-wrap align-items-end">
      <div>
        <label style="font-size:0.75rem;font-weight:600;color:var(--text-light);display:block;margin-bottom:4px">STATUS</label>
        <select name="status" class="form-select form-select-sm" style="width:160px">
          <option value="">All Statuses</option>
          <?php foreach(['pending','confirmed','checked_in','checked_out','cancelled'] as $s): ?>
          <option <?= $filter_status===$s?'selected':'' ?> value="<?= $s ?>"><?= ucfirst(str_replace('_',' ',$s)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label style="font-size:0.75rem;font-weight:600;color:var(--text-light);display:block;margin-bottom:4px">CHECK-IN DATE</label>
        <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>" class="form-control form-control-sm" style="width:180px">
      </div>
      <button type="submit" class="btn-gs btn-gs-primary btn-gs-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
      <a href="bookings.php" class="btn-gs btn-gs-outline btn-gs-sm">Clear</a>
    </form>
  </div>
</div>

<!-- Table -->
<div class="panel">
  <div class="panel-header">
    <div><div class="panel-title">All Bookings</div><div class="panel-subtitle"><?= count($bookings) ?> records found</div></div>
  </div>
  <div style="overflow-x:auto">
  <table class="gs-table">
    <thead>
      <tr><th>Ref</th><th>Guest</th><th>Room</th><th>Check-in</th><th>Check-out</th><th>Nights</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Receipt</th></tr>
    </thead>
    <tbody>
    <?php if (empty($bookings)): ?>
    <tr><td colspan="11" class="text-center py-5" style="color:var(--text-light)">No bookings found.</td></tr>
    <?php else: ?>
    <?php foreach ($bookings as $b): ?>
    <tr>
      <td><span style="font-family:monospace;font-size:0.76rem;color:var(--text-mid)"><?= $b['booking_ref'] ?></span></td>
      <td>
        <div style="font-weight:600;font-size:0.87rem"><?= htmlspecialchars($b['first_name'].' '.$b['last_name']) ?></div>
        <div style="font-size:0.74rem;color:var(--text-light)"><?= htmlspecialchars($b['email']) ?></div>
      </td>
      <td><strong><?= $b['room_number'] ?></strong> <span style="font-size:0.76rem;color:var(--text-light)"><?= $b['type_name'] ?></span></td>
      <td style="font-size:0.83rem;white-space:nowrap"><?= date('M d, Y',strtotime($b['check_in_date'])) ?></td>
      <td style="font-size:0.83rem;white-space:nowrap"><?= date('M d, Y',strtotime($b['check_out_date'])) ?></td>
      <td style="text-align:center"><?= $b['total_nights'] ?></td>
      <td style="font-weight:600">₱<?= number_format($b['total_amount'],2) ?></td>
      <td style="color:#16a34a;font-weight:600">₱<?= number_format($b['total_paid']??0,2) ?></td>
      <td style="color:<?= ($b['balance_due']??1)>0?'#dc2626':'#16a34a' ?>;font-weight:600">₱<?= number_format($b['balance_due']??$b['total_amount'],2) ?></td>
      <td><span class="badge-gs badge-<?= $b['booking_status'] ?>"><?= ucfirst(str_replace('_',' ',$b['booking_status'])) ?></span></td>
      <td><a href="receipt.php?bid=<?= $b['booking_id'] ?>" target="_blank" class="btn-gs btn-gs-sm" style="padding:5px 10px" title="Receipt"><i class="bi bi-printer"></i></a></td>
    </tr>
    <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
