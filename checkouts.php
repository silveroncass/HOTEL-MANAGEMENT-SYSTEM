<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin('staff');
$active_page = 'checkouts';
$db = getDB();
$msg = $err = '';
$today = date('Y-m-d');

// ---- PROCESS CHECK-OUT ----
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='do_checkout') {
    $bid            = (int)$_POST['booking_id'];
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $amount_paid    = (float)$_POST['amount_paid'];
    $reference_no   = trim($_POST['reference_no'] ?? '');
    $notes          = trim($_POST['notes'] ?? '');
    $actual_checkout = $_POST['actual_checkout'] ?? $today;

    try {
        $db->beginTransaction();

        // Get booking info
        $bk = $db->prepare("SELECT * FROM v_booking_details WHERE booking_id=?");
        $bk->execute([$bid]); $bk = $bk->fetch();

        // If early checkout — recalculate total based on actual nights stayed
        $original_checkout = $bk['check_out_date'];
        $check_in          = $bk['check_in_date'];
        $rate              = (float)$bk['base_price'];

        // chk_dates constraint: checkout must be > check_in
        $min_checkout  = date('Y-m-d', strtotime($check_in . ' +1 day'));
        if ($actual_checkout <= $check_in) $actual_checkout = $min_checkout;

        $actual_nights = max(1, (int)((new DateTime($check_in))->diff(new DateTime($actual_checkout))->days));
        $new_total     = $actual_nights * $rate;

        // Update booking: checked_out, update checkout date and total if early
        $db->prepare("UPDATE bookings SET status='checked_out', payment_status='paid', check_out_date=?, total_amount=?, total_nights=?, processed_by=? WHERE booking_id=?")
           ->execute([$actual_checkout, $new_total, $actual_nights, $_SESSION['user_id'], $bid]);

        // Free the room
        $db->prepare("UPDATE rooms r JOIN bookings b ON r.room_id=b.room_id SET r.status='available' WHERE b.booking_id=?")
           ->execute([$bid]);

        // Record payment
        if ($amount_paid > 0) {
            $db->prepare("INSERT INTO payments (booking_id,amount_paid,payment_method,reference_no,notes,received_by,payment_date) VALUES (?,?,?,?,?,?,NOW())")
               ->execute([$bid, $amount_paid, $payment_method, $reference_no ?: null, $notes ?: 'Balance at check-out', $_SESSION['user_id']]);
        }

        $db->commit();
        $is_early = $actual_checkout < $original_checkout;
        $msg = "Check-out completed for <strong>{$bk['first_name']} {$bk['last_name']}</strong>. Room {$bk['room_number']} is now available.";
        if ($is_early) $msg .= " <span style='color:#92400e'>(Early checkout: {$actual_nights} night(s), ₱".number_format($new_total,2)." charged)</span>";
    } catch(Exception $e) {
        $db->rollBack();
        $err = $e->getMessage();
    }
}

// Today's checkouts
$checkouts = $db->prepare("SELECT * FROM v_booking_details WHERE check_out_date=? AND booking_status='checked_in' ORDER BY room_number");
$checkouts->execute([$today]); $checkouts = $checkouts->fetchAll();

// Overdue
$overdue = $db->prepare("SELECT * FROM v_booking_details WHERE check_out_date<? AND booking_status='checked_in' ORDER BY check_out_date");
$overdue->execute([$today]); $overdue = $overdue->fetchAll();

// Early — checked in, future checkout
$early = $db->prepare("SELECT * FROM v_booking_details WHERE check_out_date>? AND booking_status='checked_in' ORDER BY check_out_date");
$early->execute([$today]); $early = $early->fetchAll();

include __DIR__ . '/../includes/sidebar.php';
?>
<div class="topbar">
  <div class="topbar-title"><h1>Check-outs</h1><p>Manage departures and collect final payments</p></div>
</div>
<div class="page-content">

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
<div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if (!empty($overdue)): ?>
<div class="panel mb-3" style="border:1.5px solid #fecaca">
  <div class="panel-header" style="background:#fef2f2">
    <div class="panel-title" style="color:#dc2626"><i class="bi bi-exclamation-triangle me-1"></i>Overdue (<?= count($overdue) ?>) — Past checkout date</div>
  </div>
  <?= renderCheckoutTable($overdue, true) ?>
</div>
<?php endif; ?>

<div class="panel mb-3">
  <div class="panel-header">
    <div class="panel-title"><i class="bi bi-calendar-check me-1"></i>Departing Today — <?= date('F d, Y') ?> (<?= count($checkouts) ?>)</div>
  </div>
  <?= renderCheckoutTable($checkouts, false) ?>
</div>

<?php if (!empty($early)): ?>
<div class="panel">
  <div class="panel-header" style="background:#fffbeb">
    <div class="panel-title" style="color:#92400e"><i class="bi bi-clock me-1"></i>Early Departures Available (<?= count($early) ?>)</div>
    <div class="panel-subtitle" style="color:#a16207">Scheduled for future dates — price adjusted to actual nights stayed</div>
  </div>
  <?= renderCheckoutTable($early, false) ?>
</div>
<?php endif; ?>

</div>

<?php
function renderCheckoutTable($rows, $overdue_style) {
    if (empty($rows)) {
        return '<div class="text-center py-4" style="color:var(--text-light)"><i class="bi bi-door-open" style="font-size:1.8rem;display:block"></i><p class="mt-2 mb-0">None</p></div>';
    }
    ob_start(); ?>
  <div style="overflow-x:auto">
  <table class="gs-table">
    <thead><tr><th>Guest</th><th>Ref</th><th>Room</th><th>Check-in</th><th>Sched. Out</th><th>Total</th><th>Paid</th><th>Balance</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $b): ?>
    <tr <?= $overdue_style?'style="background:#fffafa"':'' ?>>
      <td><strong><?= htmlspecialchars($b['first_name'].' '.$b['last_name']) ?></strong></td>
      <td><code style="font-size:0.78rem"><?= $b['booking_ref'] ?></code></td>
      <td><strong><?= $b['room_number'] ?></strong> <small style="color:var(--text-light)"><?= $b['type_name'] ?></small></td>
      <td><?= date('M d',strtotime($b['check_in_date'])) ?></td>
      <td style="<?= $overdue_style?'color:#dc2626;font-weight:600':'' ?>"><?= date('M d, Y',strtotime($b['check_out_date'])) ?></td>
      <td>₱<?= number_format($b['total_amount'],2) ?></td>
      <td style="color:#16a34a;font-weight:600">₱<?= number_format($b['total_paid']??0,2) ?></td>
      <td style="color:<?= ($b['balance_due']??1)>0?'#dc2626':'#16a34a' ?>;font-weight:600">₱<?= number_format($b['balance_due']??$b['total_amount'],2) ?></td>
      <td>
        <button class="btn-gs btn-gs-primary btn-gs-sm"
          onclick="openCheckout(<?= htmlspecialchars(json_encode($b),ENT_QUOTES) ?>)">
          <i class="bi bi-box-arrow-right me-1"></i>Check Out
        </button>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
    <?php return ob_get_clean();
}
?>

<!-- CHECKOUT MODAL -->
<div class="modal fade" id="checkoutModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="border-radius:16px;overflow:hidden;border:none">
      <div class="modal-header" style="background:var(--green-dark);padding:18px 22px">
        <h5 class="modal-title" style="font-family:'Playfair Display',serif;margin:0;color:#fff">
          <i class="bi bi-box-arrow-right me-2"></i>Process Check-out & Final Payment
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">

        <!-- Booking summary -->
        <div style="background:#f4f9f5;border-radius:12px;padding:14px 16px;margin-bottom:16px;display:grid;grid-template-columns:1fr 1fr;gap:6px 20px;font-size:0.84rem">
          <div><span style="color:var(--text-light)">Guest: </span><strong id="co_guest"></strong></div>
          <div><span style="color:var(--text-light)">Reference: </span><strong id="co_ref" style="font-family:monospace"></strong></div>
          <div><span style="color:var(--text-light)">Room: </span><strong id="co_room"></strong></div>
          <div><span style="color:var(--text-light)">Check-in: </span><strong id="co_checkin_disp"></strong></div>
          <div><span style="color:var(--text-light)">Rate: </span><strong id="co_rate"></strong></div>
          <div><span style="color:var(--text-light)">Already Paid: </span><strong id="co_paid" style="color:#16a34a"></strong></div>
        </div>

        <!-- Early checkout toggle -->
        <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:10px;padding:12px 16px;margin-bottom:14px">
          <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
            <div style="font-size:0.85rem;color:#92400e;font-weight:600"><i class="bi bi-calendar-x me-1"></i>Actual Check-out Date</div>
            <input type="date" id="co_actual_date" style="border:1.5px solid #fcd34d;border-radius:8px;padding:6px 10px;font-size:0.85rem;background:#fff" onchange="recalcTotal()">
          </div>
          <div id="early_note" style="font-size:0.8rem;color:#92400e;margin-top:6px;display:none">
            <i class="bi bi-info-circle me-1"></i><span id="early_note_text"></span>
          </div>
        </div>

        <!-- Computed total -->
        <div style="background:#1a3028;border-radius:10px;padding:14px 18px;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center">
          <div>
            <div style="color:rgba(255,255,255,0.6);font-size:0.78rem">BALANCE DUE FROM GUEST</div>
            <div id="co_balance_label" style="font-family:'Playfair Display',serif;font-size:1.8rem;color:#c9a84c;font-weight:700"></div>
          </div>
          <div style="text-align:right">
            <div style="color:rgba(255,255,255,0.6);font-size:0.78rem">ADJUSTED TOTAL</div>
            <div id="co_total_label" style="color:#fff;font-size:1rem;font-weight:600"></div>
          </div>
        </div>

        <div id="co_fully_paid_box" style="display:none;background:#dcfce7;border:1px solid #a7d7b0;border-radius:10px;padding:12px 16px;margin-bottom:14px">
          <i class="bi bi-check-circle-fill me-2" style="color:#16a34a"></i>
          <span style="font-size:0.88rem;color:#166534;font-weight:600">Fully paid — no payment needed.</span>
        </div>

        <form method="POST" id="checkoutForm">
          <input type="hidden" name="action" value="do_checkout">
          <input type="hidden" name="booking_id" id="co_bid">
          <input type="hidden" name="actual_checkout" id="co_actual_hidden">

          <div id="co_payment_section">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 14px">
              <div style="margin-bottom:12px">
                <label style="font-size:0.73rem;font-weight:700;color:var(--text-mid);text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:5px">Payment Method</label>
                <select name="payment_method" id="co_method" class="form-select" onchange="toggleCoRef()">
                  <option value="cash">💵 Cash</option>
                  <option value="gcash">📱 GCash</option>
                  <option value="credit_card">💳 Credit Card</option>
                  <option value="debit_card">💳 Debit Card</option>
                  <option value="paymaya">📱 PayMaya</option>
                  <option value="bank_transfer">🏦 Bank Transfer</option>
                </select>
              </div>
              <div id="co_ref_wrap" style="display:none;margin-bottom:12px">
                <label style="font-size:0.73rem;font-weight:700;color:var(--text-mid);text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:5px">Reference No.</label>
                <input type="text" name="reference_no" class="form-control" placeholder="Transaction No.">
              </div>
            </div>
            <div style="margin-bottom:12px">
              <label style="font-size:0.73rem;font-weight:700;color:var(--text-mid);text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:5px">Amount Collected (₱)</label>
              <input type="number" name="amount_paid" id="co_amount" class="form-control" step="0.01" min="0">
            </div>
            <div style="margin-bottom:16px">
              <label style="font-size:0.73rem;font-weight:700;color:var(--text-mid);text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:5px">Notes (optional)</label>
              <input type="text" name="notes" class="form-control" placeholder="e.g. Early departure, paid in full...">
            </div>
          </div>

          <button type="submit" class="btn btn-dark w-100 py-3" style="border-radius:10px;font-weight:700;font-size:0.95rem">
            <i class="bi bi-check-circle me-2"></i>Confirm Check-out & Complete Payment
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let coBooking = null;
const TODAY   = '<?= $today ?>';

function openCheckout(b) {
  coBooking = b;
  const paid    = parseFloat(b.total_paid  || 0);
  const rate    = parseFloat(b.base_price  || 0);

  document.getElementById('co_bid').value          = b.booking_id;
  document.getElementById('co_guest').textContent  = b.first_name + ' ' + b.last_name;
  document.getElementById('co_ref').textContent    = b.booking_ref;
  document.getElementById('co_room').textContent   = b.room_number + ' — ' + b.type_name;
  document.getElementById('co_checkin_disp').textContent = b.check_in_date;
  document.getElementById('co_rate').textContent   = '₱' + rate.toLocaleString('en',{minimumFractionDigits:2}) + '/night';
  document.getElementById('co_paid').textContent   = '₱' + paid.toLocaleString('en',{minimumFractionDigits:2});

  // Default actual checkout = today
  const dateInput = document.getElementById('co_actual_date');
  dateInput.min   = b.check_in_date;
  dateInput.value = TODAY;
  document.getElementById('co_actual_hidden').value = TODAY;

  recalcTotal();
  toggleCoRef();
  new bootstrap.Modal(document.getElementById('checkoutModal')).show();
}

function recalcTotal() {
  if (!coBooking) return;
  const actualDate = document.getElementById('co_actual_date').value || TODAY;
  document.getElementById('co_actual_hidden').value = actualDate;

  const rate       = parseFloat(coBooking.base_price || 0);
  const paid       = parseFloat(coBooking.total_paid || 0);
  const ci         = new Date(coBooking.check_in_date);
  const co         = new Date(actualDate);
  const actualNights = Math.max(1, Math.round((co - ci) / 86400000));
  const newTotal   = actualNights * rate;
  const balance    = Math.max(0, newTotal - paid);

  document.getElementById('co_total_label').textContent  = actualNights + ' night(s) × ₱' + rate.toLocaleString() + ' = ₱' + newTotal.toLocaleString('en',{minimumFractionDigits:2});
  document.getElementById('co_balance_label').textContent = '₱' + balance.toLocaleString('en',{minimumFractionDigits:2});
  document.getElementById('co_amount').value              = balance > 0 ? balance.toFixed(2) : '0.00';

  // Early note
  const isEarly = actualDate < coBooking.check_out_date;
  const note    = document.getElementById('early_note');
  if (isEarly) {
    document.getElementById('early_note_text').textContent =
      'Early checkout: ' + actualNights + ' night(s) instead of ' + coBooking.total_nights + '. Total adjusted to ₱' + newTotal.toLocaleString('en',{minimumFractionDigits:2}) + '.';
    note.style.display = 'block';
  } else {
    note.style.display = 'none';
  }

  // Show/hide payment section
  if (balance <= 0) {
    document.getElementById('co_fully_paid_box').style.display = 'block';
    document.getElementById('co_payment_section').style.display = 'none';
  } else {
    document.getElementById('co_fully_paid_box').style.display = 'none';
    document.getElementById('co_payment_section').style.display = 'block';
  }
}

function toggleCoRef() {
  const m = document.getElementById('co_method').value;
  document.getElementById('co_ref_wrap').style.display = (m === 'cash') ? 'none' : 'block';
}
</script>
</body></html>
