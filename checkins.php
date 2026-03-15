<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin('staff');
$active_page = 'checkins';
$db = getDB();
$msg = $err = '';
$today = date('Y-m-d');

// ---- CONFIRM BOOKING (staff only) ----
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='confirm_booking') {
    $bid = (int)$_POST['booking_id'];
    $db->prepare("UPDATE bookings SET status='confirmed', processed_by=? WHERE booking_id=? AND status='pending'")
       ->execute([$_SESSION['user_id'], $bid]);
    $msg = "Booking confirmed successfully.";
}

// ---- PROCESS CHECK-IN (only allowed on check-in day) ----
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='do_checkin') {
    $bid = (int)$_POST['booking_id'];
    // Double-check it's today
    $chk = $db->prepare("SELECT check_in_date FROM bookings WHERE booking_id=?");
    $chk->execute([$bid]); $ci_date = $chk->fetchColumn();
    if ($ci_date !== $today) {
        $err = "Check-in is only allowed on the check-in date ({$ci_date}).";
    } else {
        $db->prepare("UPDATE bookings SET status='checked_in', processed_by=? WHERE booking_id=?")->execute([$_SESSION['user_id'],$bid]);
        $db->prepare("UPDATE rooms r JOIN bookings b ON r.room_id=b.room_id SET r.status='occupied' WHERE b.booking_id=?")->execute([$bid]);
        $msg = "Check-in completed successfully.";
    }
}

// ---- WALK-IN ----
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='walkin') {
    $first=trim($_POST['first_name']); $last=trim($_POST['last_name']);
    $email=trim($_POST['email']);      $phone=trim($_POST['phone']??'');
    $room_id=(int)$_POST['room_id']; $check_in=$_POST['check_in']; $check_out=$_POST['check_out'];
    $payment_method=$_POST['payment_method']??'cash'; $amount_paid=(float)$_POST['amount_paid'];
    if (!$first||!$last||!$email||!$room_id||!$check_in||!$check_out) { $err="Please fill all required fields."; }
    elseif ($check_out<=$check_in) { $err="Check-out must be after check-in."; }
    else {
        try {
            $db->beginTransaction();
            $ucheck=$db->prepare("SELECT user_id FROM users WHERE email=?"); $ucheck->execute([$email]);
            $guest_id=$ucheck->fetchColumn();
            if (!$guest_id) {
                $role_id=$db->query("SELECT role_id FROM roles WHERE role_name='user'")->fetchColumn();
                $uname='walkin_'.time();
                $db->prepare("INSERT INTO users (role_id,first_name,last_name,email,username,password,phone) VALUES (?,?,?,?,?,?,?)")
                   ->execute([$role_id,$first,$last,$email,$uname,hashPassword(bin2hex(random_bytes(6))),$phone]);
                $guest_id=$db->lastInsertId();
            }
            $db->prepare("CALL sp_create_booking(?,?,?,?,?,?,@ref)")->execute([$guest_id,$room_id,$check_in,$check_out,1,'Walk-in via staff']);
            $ref=$db->query("SELECT @ref")->fetchColumn();
            $booking_id=$db->query("SELECT booking_id FROM bookings WHERE booking_ref='$ref'")->fetchColumn();
            $db->prepare("UPDATE bookings SET status='checked_in',processed_by=? WHERE booking_id=?")->execute([$_SESSION['user_id'],$booking_id]);
            $db->prepare("UPDATE rooms SET status='occupied' WHERE room_id=?")->execute([$room_id]);
            $db->prepare("INSERT INTO payments (booking_id,amount_paid,payment_method,received_by,payment_date) VALUES (?,?,?,?,NOW())")
               ->execute([$booking_id,$amount_paid,$payment_method,$_SESSION['user_id']]);
            $db->commit();
            $msg="Walk-in booked & checked in! Ref: <strong>$ref</strong> — $first $last";
        } catch (Exception $e) {
            $db->rollBack(); $err=$e->getMessage();
            if (strpos($err,'SQLSTATE')!==false){preg_match('/SET MESSAGE_TEXT = \'([^\']+)\'|: (.+)$/',$err,$m);$err=$m[1]??$m[2]??"Booking failed.";}
        }
    }
}

// Pending bookings (needs staff confirmation)
$pending = $db->query("SELECT * FROM v_booking_details WHERE booking_status='pending' ORDER BY check_in_date")->fetchAll();

// Today's arrivals (confirmed, ready to check in)
$arrivals = $db->prepare("SELECT * FROM v_booking_details WHERE check_in_date=? AND booking_status='confirmed' ORDER BY check_in_date");
$arrivals->execute([$today]); $arrivals=$arrivals->fetchAll();

// Upcoming confirmed (not today)
$upcoming = $db->prepare("SELECT * FROM v_booking_details WHERE check_in_date>? AND booking_status='confirmed' ORDER BY check_in_date LIMIT 10");
$upcoming->execute([$today]); $upcoming=$upcoming->fetchAll();

$avail_rooms=$db->query("SELECT r.*,rt.type_name,rt.base_price FROM rooms r JOIN room_types rt ON r.type_id=rt.type_id WHERE r.status='available' ORDER BY rt.base_price")->fetchAll();

include __DIR__ . '/../includes/sidebar.php';
?>
<div class="topbar">
  <div class="topbar-title"><h1>Check-ins</h1><p>Manage arrivals and confirm bookings</p></div>
  <div>
    <button class="btn-gs btn-gs-primary" data-bs-toggle="modal" data-bs-target="#walkinModal">
      <i class="bi bi-person-plus me-1"></i>Walk-in Booking
    </button>
  </div>
</div>
<div class="page-content">

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
<div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- PENDING BOOKINGS — needs staff confirmation -->
<?php if (!empty($pending)): ?>
<div class="panel mb-3" style="border:1.5px solid #fef08a">
  <div class="panel-header" style="background:#fefce8">
    <div class="panel-title" style="color:#854d0e"><i class="bi bi-clock me-1"></i>Pending Confirmation (<?= count($pending) ?>)</div>
    <div class="panel-subtitle" style="color:#a16207">These bookings need staff confirmation before guests can check in</div>
  </div>
  <div style="overflow-x:auto">
  <table class="gs-table">
    <thead><tr><th>Guest</th><th>Ref</th><th>Room</th><th>Check-in</th><th>Check-out</th><th>Amount</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach ($pending as $b): ?>
    <tr>
      <td><strong><?= htmlspecialchars($b['first_name'].' '.$b['last_name']) ?></strong><br><small style="color:var(--text-light)"><?= $b['email'] ?></small></td>
      <td><code style="font-size:0.78rem"><?= $b['booking_ref'] ?></code></td>
      <td><strong><?= $b['room_number'] ?></strong> <small style="color:var(--text-light)"><?= $b['type_name'] ?></small></td>
      <td style="font-weight:600;color:<?= $b['check_in_date']===$today?'#16a34a':'var(--text-dark)' ?>"><?= date('M d, Y',strtotime($b['check_in_date'])) ?></td>
      <td><?= date('M d, Y',strtotime($b['check_out_date'])) ?></td>
      <td>₱<?= number_format($b['total_amount'],2) ?></td>
      <td>
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="confirm_booking">
          <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
          <button type="submit" class="btn-gs btn-gs-sm" style="background:#16a34a;color:#fff">
            <i class="bi bi-check-circle me-1"></i>Confirm
          </button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endif; ?>

<!-- TODAY'S ARRIVALS -->
<div class="panel mb-3">
  <div class="panel-header">
    <div class="panel-title"><i class="bi bi-calendar-check me-1"></i>Arriving Today — <?= date('F d, Y') ?> (<?= count($arrivals) ?>)</div>
  </div>
  <div style="overflow-x:auto">
  <table class="gs-table">
    <thead><tr><th>Guest</th><th>Ref</th><th>Room</th><th>Check-out</th><th>Total</th><th>Deposit Paid</th><th>Balance</th><th>Action</th></tr></thead>
    <tbody>
    <?php if (empty($arrivals)): ?>
    <tr><td colspan="8" class="text-center py-4" style="color:var(--text-light)">
      <i class="bi bi-check-circle" style="font-size:1.8rem;display:block"></i>
      <p class="mt-2 mb-0">No arrivals today</p>
    </td></tr>
    <?php else: ?>
    <?php foreach ($arrivals as $b): ?>
    <tr>
      <td><strong><?= htmlspecialchars($b['first_name'].' '.$b['last_name']) ?></strong></td>
      <td><code style="font-size:0.78rem"><?= $b['booking_ref'] ?></code></td>
      <td><strong><?= $b['room_number'] ?></strong> <small style="color:var(--text-light)"><?= $b['type_name'] ?></small></td>
      <td><?= date('M d, Y',strtotime($b['check_out_date'])) ?></td>
      <td>₱<?= number_format($b['total_amount'],2) ?></td>
      <td style="color:#16a34a;font-weight:600">₱<?= number_format($b['total_paid']??0,2) ?></td>
      <td style="color:#dc2626;font-weight:600">₱<?= number_format($b['balance_due']??$b['total_amount'],2) ?></td>
      <td>
        <form method="POST">
          <input type="hidden" name="action" value="do_checkin">
          <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
          <button type="submit" class="btn-gs btn-gs-primary btn-gs-sm">
            <i class="bi bi-box-arrow-in-right me-1"></i>Check In
          </button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- UPCOMING (confirmed, future) -->
<?php if (!empty($upcoming)): ?>
<div class="panel">
  <div class="panel-header">
    <div class="panel-title"><i class="bi bi-calendar2-week me-1"></i>Upcoming Confirmed Bookings</div>
    <div class="panel-subtitle">Check-in button only activates on arrival day</div>
  </div>
  <div style="overflow-x:auto">
  <table class="gs-table">
    <thead><tr><th>Guest</th><th>Ref</th><th>Room</th><th>Check-in</th><th>Check-out</th><th>Amount</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach ($upcoming as $b): ?>
    <tr>
      <td><strong><?= htmlspecialchars($b['first_name'].' '.$b['last_name']) ?></strong></td>
      <td><code style="font-size:0.78rem"><?= $b['booking_ref'] ?></code></td>
      <td><strong><?= $b['room_number'] ?></strong> <small style="color:var(--text-light)"><?= $b['type_name'] ?></small></td>
      <td style="font-weight:600"><?= date('M d, Y',strtotime($b['check_in_date'])) ?></td>
      <td><?= date('M d, Y',strtotime($b['check_out_date'])) ?></td>
      <td>₱<?= number_format($b['total_amount'],2) ?></td>
      <td>
        <!-- Disabled — not today -->
        <button class="btn-gs btn-gs-sm" disabled style="background:#e2ede4;color:#8aab90;cursor:not-allowed" title="Check-in only available on <?= date('M d',strtotime($b['check_in_date'])) ?>">
          <i class="bi bi-clock me-1"></i><?= date('M d',strtotime($b['check_in_date'])) ?>
        </button>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endif; ?>

</div>

<!-- WALK-IN MODAL -->
<div class="modal fade" id="walkinModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="border-radius:16px;overflow:hidden">
      <div class="modal-header" style="background:var(--green-dark);color:#fff">
        <h5 class="modal-title" style="font-family:'Playfair Display',serif;color:#fff"><i class="bi bi-person-plus me-2"></i>Walk-in Booking</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <form method="POST">
          <input type="hidden" name="action" value="walkin">
          <h6 style="font-weight:700;margin-bottom:12px;color:var(--green-dark)"><i class="bi bi-person me-1"></i>Guest Information</h6>
          <div class="row g-3 mb-3">
            <div class="col-md-6"><label class="form-label fw-semibold" style="font-size:0.8rem">First Name *</label><input type="text" name="first_name" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label fw-semibold" style="font-size:0.8rem">Last Name *</label><input type="text" name="last_name" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label fw-semibold" style="font-size:0.8rem">Email *</label><input type="email" name="email" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label fw-semibold" style="font-size:0.8rem">Phone</label><input type="text" name="phone" class="form-control" placeholder="+63 9XX XXX XXXX"></div>
          </div>
          <hr>
          <h6 style="font-weight:700;margin-bottom:12px;color:var(--green-dark)"><i class="bi bi-door-open me-1"></i>Room & Dates</h6>
          <div class="row g-3 mb-3">
            <div class="col-12">
              <label class="form-label fw-semibold" style="font-size:0.8rem">Room *</label>
              <select name="room_id" class="form-select" required id="walkin_room" onchange="updateWalkinPrice()">
                <option value="">— Select a room —</option>
                <?php foreach ($avail_rooms as $r): ?>
                <option value="<?= $r['room_id'] ?>" data-price="<?= $r['base_price'] ?>"><?= $r['room_number'] ?> — <?= $r['type_name'] ?> (₱<?= number_format($r['base_price'],0) ?>/night)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6"><label class="form-label fw-semibold" style="font-size:0.8rem">Check-in *</label><input type="date" name="check_in" id="walkin_ci" class="form-control" value="<?= $today ?>" min="<?= $today ?>" required onchange="updateWalkinPrice()"></div>
            <div class="col-md-6"><label class="form-label fw-semibold" style="font-size:0.8rem">Check-out *</label><input type="date" name="check_out" id="walkin_co" class="form-control" value="<?= date('Y-m-d',strtotime('+1 day')) ?>" required onchange="updateWalkinPrice()"></div>
          </div>
          <hr>
          <h6 style="font-weight:700;margin-bottom:12px;color:var(--green-dark)"><i class="bi bi-cash-coin me-1"></i>Payment</h6>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold" style="font-size:0.8rem">Method *</label>
              <select name="payment_method" class="form-select">
                <option value="cash">💵 Cash</option>
                <option value="gcash">📱 GCash</option>
                <option value="credit_card">💳 Credit Card</option>
                <option value="debit_card">💳 Debit Card</option>
                <option value="paymaya">📱 PayMaya</option>
                <option value="bank_transfer">🏦 Bank Transfer</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold" style="font-size:0.8rem">Amount (₱) *</label>
              <input type="number" name="amount_paid" id="walkin_amount" class="form-control" step="0.01" min="0" required>
            </div>
          </div>
          <div id="walkin_summary" style="background:#f4f9f5;border-radius:10px;padding:12px 16px;font-size:0.85rem;display:none;margin-bottom:16px">
            <strong>Total:</strong> <span id="walkin_total">₱0</span> &nbsp;|&nbsp; <span id="walkin_nights">0 nights</span>
          </div>
          <button type="submit" class="btn btn-dark w-100 py-3" style="border-radius:10px;font-weight:700"><i class="bi bi-check-circle me-2"></i>Book & Check In Now</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function updateWalkinPrice() {
  const sel=document.getElementById('walkin_room');
  const ci=document.getElementById('walkin_ci').value;
  const co=document.getElementById('walkin_co').value;
  const opt=sel.options[sel.selectedIndex];
  if (!opt||!opt.value||!ci||!co) return;
  const price=parseFloat(opt.dataset.price)||0;
  const nights=Math.max(0,(new Date(co)-new Date(ci))/86400000);
  const total=price*nights;
  document.getElementById('walkin_total').textContent='₱'+total.toLocaleString('en',{minimumFractionDigits:2});
  document.getElementById('walkin_nights').textContent=nights+' night'+(nights!==1?'s':'');
  document.getElementById('walkin_amount').value=total.toFixed(2);
  document.getElementById('walkin_summary').style.display='block';
  // Sync checkout min
  const next=new Date(ci); next.setDate(next.getDate()+1);
  document.getElementById('walkin_co').min=next.toISOString().split('T')[0];
}
</script>
</body></html>
