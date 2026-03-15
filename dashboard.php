<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin('staff');
$active_page = 'dashboard';
include __DIR__ . '/../includes/sidebar.php';

$db    = getDB();
$today = date('Y-m-d');

// KPI counts
$checkins_today = $db->prepare("SELECT COUNT(*) FROM v_booking_details WHERE check_in_date=? AND booking_status='confirmed'");
$checkins_today->execute([$today]); $checkins_today = (int)$checkins_today->fetchColumn();

$checkouts_today = $db->prepare("SELECT COUNT(*) FROM v_booking_details WHERE check_out_date=? AND booking_status='checked_in'");
$checkouts_today->execute([$today]); $checkouts_today = (int)$checkouts_today->fetchColumn();

$active_guests   = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE status='checked_in'")->fetchColumn();
$pending_confirm = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn();

$today_rev = $db->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM payments WHERE DATE(payment_date)=? AND payment_status='completed'");
$today_rev->execute([$today]); $today_rev = (float)$today_rev->fetchColumn();

// Arriving today
$arrivals = $db->prepare("SELECT * FROM v_booking_details WHERE check_in_date=? AND booking_status='confirmed' ORDER BY room_number");
$arrivals->execute([$today]); $arrivals = $arrivals->fetchAll();

// Departing today
$departures = $db->prepare("SELECT * FROM v_booking_details WHERE check_out_date=? AND booking_status='checked_in' ORDER BY room_number");
$departures->execute([$today]); $departures = $departures->fetchAll();

// Active stays
$active_stays = $db->query("SELECT * FROM v_booking_details WHERE booking_status='checked_in' ORDER BY check_out_date")->fetchAll();

// Upcoming reservations next 7 days
$week_end = date('Y-m-d', strtotime('+7 days'));
$upcoming = $db->prepare("SELECT * FROM v_booking_details WHERE check_in_date > ? AND check_in_date <= ? AND booking_status IN ('confirmed','pending') ORDER BY check_in_date LIMIT 15");
$upcoming->execute([$today, $week_end]); $upcoming = $upcoming->fetchAll();

// Pending bookings needing confirmation
$pending_list = $db->query("SELECT * FROM v_booking_details WHERE booking_status='pending' ORDER BY check_in_date LIMIT 10")->fetchAll();
?>

<div class="topbar">
  <div class="topbar-title">
    <h1>Staff Dashboard</h1>
    <p>Today's operations — <?= date('l, F d, Y') ?></p>
  </div>
  <div style="display:flex;align-items:center;gap:8px;background:#f4f9f5;border-radius:10px;padding:8px 14px;font-size:0.82rem;color:var(--text-mid)">
    <i class="bi bi-clock"></i> <?= date('h:i A') ?> PHT
  </div>
</div>

<div class="page-content">

<!-- KPI CARDS -->
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:20px">

  <div class="stat-card primary" style="margin:0">
    <div class="stat-top">
      <span class="stat-label">Check-ins Today</span>
      <div class="stat-icon" style="background:rgba(255,255,255,0.15);color:#fff"><i class="bi bi-box-arrow-in-right"></i></div>
    </div>
    <div class="stat-value"><?= $checkins_today ?></div>
    <div class="stat-change" style="color:rgba(255,255,255,0.7)">Awaiting arrival</div>
  </div>

  <div class="stat-card" style="margin:0">
    <div class="stat-top">
      <span class="stat-label">Check-outs Today</span>
      <div class="stat-icon" style="background:#fef3c7;color:#d97706"><i class="bi bi-box-arrow-right"></i></div>
    </div>
    <div class="stat-value"><?= $checkouts_today ?></div>
    <div class="stat-change" style="color:var(--text-light)">Departing today</div>
  </div>

  <div class="stat-card" style="margin:0">
    <div class="stat-top">
      <span class="stat-label">Active Guests</span>
      <div class="stat-icon" style="background:#dbeafe;color:#2563eb"><i class="bi bi-person-fill"></i></div>
    </div>
    <div class="stat-value"><?= $active_guests ?></div>
    <div class="stat-change" style="color:var(--text-light)">Currently checked in</div>
  </div>

  <div class="stat-card" style="margin:0">
    <div class="stat-top">
      <span class="stat-label">Today's Revenue</span>
      <div class="stat-icon" style="background:#dcfce7;color:#16a34a"><i class="bi bi-cash-coin"></i></div>
    </div>
    <div class="stat-value" style="font-size:1.2rem">₱<?= number_format($today_rev,0) ?></div>
    <div class="stat-change" style="color:var(--text-light)">Collected today</div>
  </div>

  <div class="stat-card" style="margin:0">
    <div class="stat-top">
      <span class="stat-label">Pending Confirm</span>
      <div class="stat-icon" style="background:#fef9c3;color:#ca8a04"><i class="bi bi-hourglass-split"></i></div>
    </div>
    <div class="stat-value"><?= $pending_confirm ?></div>
    <div class="stat-change" style="color:var(--text-light)">Needs confirmation</div>
  </div>

</div>

<!-- ARRIVALS + DEPARTURES -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px">

  <div class="panel" style="margin:0">
    <div class="panel-header">
      <div>
        <div class="panel-title"><i class="bi bi-box-arrow-in-right me-1" style="color:#16a34a"></i>Arriving Today</div>
        <div class="panel-subtitle"><?= count($arrivals) ?> guest(s) expected</div>
      </div>
      <a href="checkins.php" class="btn-gs btn-gs-sm">Manage</a>
    </div>
    <?php if (empty($arrivals)): ?>
    <div class="text-center py-4" style="color:var(--text-light)">
      <i class="bi bi-check-circle" style="font-size:1.8rem;display:block;color:#16a34a"></i>
      <p class="mt-2 mb-0" style="font-size:0.85rem">No arrivals today</p>
    </div>
    <?php else: ?>
    <table class="gs-table">
      <thead><tr><th>Guest</th><th>Room</th><th>Balance</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($arrivals as $b): ?>
      <tr>
        <td>
          <div style="font-weight:600;font-size:0.85rem"><?= htmlspecialchars($b['first_name'].' '.$b['last_name']) ?></div>
          <div style="font-size:0.72rem;color:var(--text-light)"><?= $b['booking_ref'] ?></div>
        </td>
        <td><strong><?= $b['room_number'] ?></strong><br><small style="color:var(--text-light)"><?= $b['type_name'] ?></small></td>
        <td style="color:#dc2626;font-weight:600;font-size:0.82rem">₱<?= number_format($b['balance_due']??$b['total_amount'],2) ?></td>
        <td><a href="checkins.php" class="btn-gs btn-gs-primary btn-gs-sm"><i class="bi bi-box-arrow-in-right"></i></a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <div class="panel" style="margin:0">
    <div class="panel-header">
      <div>
        <div class="panel-title"><i class="bi bi-box-arrow-right me-1" style="color:#d97706"></i>Departing Today</div>
        <div class="panel-subtitle"><?= count($departures) ?> guest(s) departing</div>
      </div>
      <a href="checkouts.php" class="btn-gs btn-gs-sm">Manage</a>
    </div>
    <?php if (empty($departures)): ?>
    <div class="text-center py-4" style="color:var(--text-light)">
      <i class="bi bi-door-closed" style="font-size:1.8rem;display:block"></i>
      <p class="mt-2 mb-0" style="font-size:0.85rem">No departures today</p>
    </div>
    <?php else: ?>
    <table class="gs-table">
      <thead><tr><th>Guest</th><th>Room</th><th>Balance</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($departures as $b): ?>
      <tr>
        <td>
          <div style="font-weight:600;font-size:0.85rem"><?= htmlspecialchars($b['first_name'].' '.$b['last_name']) ?></div>
          <div style="font-size:0.72rem;color:var(--text-light)"><?= $b['booking_ref'] ?></div>
        </td>
        <td><strong><?= $b['room_number'] ?></strong><br><small style="color:var(--text-light)"><?= $b['type_name'] ?></small></td>
        <td style="color:<?= ($b['balance_due']??1)>0?'#dc2626':'#16a34a' ?>;font-weight:600;font-size:0.82rem">₱<?= number_format($b['balance_due']??0,2) ?></td>
        <td><a href="checkouts.php" class="btn-gs btn-gs-sm" style="background:#d97706;color:#fff"><i class="bi bi-box-arrow-right"></i></a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

</div>

<!-- PENDING CONFIRMATION + UPCOMING RESERVATIONS -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px">

  <div class="panel" style="margin:0;<?= !empty($pending_list)?'border:1.5px solid #fde68a':'' ?>">
    <div class="panel-header" style="<?= !empty($pending_list)?'background:#fffbeb':'' ?>">
      <div>
        <div class="panel-title" style="<?= !empty($pending_list)?'color:#92400e':'' ?>">
          <i class="bi bi-clock me-1"></i>Needs Confirmation (<?= count($pending_list) ?>)
        </div>
        <div class="panel-subtitle">Bookings waiting for staff approval</div>
      </div>
      <a href="checkins.php" class="btn-gs btn-gs-sm">Go to Check-ins</a>
    </div>
    <?php if (empty($pending_list)): ?>
    <div class="text-center py-4" style="color:var(--text-light)">
      <i class="bi bi-check-all" style="font-size:1.8rem;display:block;color:#16a34a"></i>
      <p class="mt-2 mb-0" style="font-size:0.85rem">All bookings confirmed</p>
    </div>
    <?php else: ?>
    <table class="gs-table">
      <thead><tr><th>Guest</th><th>Room</th><th>Check-in</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($pending_list as $b): ?>
      <tr>
        <td>
          <div style="font-weight:600;font-size:0.85rem"><?= htmlspecialchars($b['first_name'].' '.$b['last_name']) ?></div>
          <div style="font-size:0.72rem;color:var(--text-light)"><?= $b['booking_ref'] ?></div>
        </td>
        <td><strong><?= $b['room_number'] ?></strong></td>
        <td style="font-size:0.82rem;font-weight:600;color:<?= $b['check_in_date']===$today?'#16a34a':'var(--text-dark)' ?>">
          <?= date('M d, Y', strtotime($b['check_in_date'])) ?>
        </td>
        <td>
          <form method="POST" action="checkins.php">
            <input type="hidden" name="action" value="confirm_booking">
            <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
            <button type="submit" class="btn-gs btn-gs-sm" style="background:#16a34a;color:#fff;font-size:0.75rem">
              <i class="bi bi-check-circle me-1"></i>Confirm
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <div class="panel" style="margin:0">
    <div class="panel-header">
      <div>
        <div class="panel-title"><i class="bi bi-calendar2-week me-1"></i>Upcoming Reservations</div>
        <div class="panel-subtitle">Next 7 days — <?= count($upcoming) ?> booking(s)</div>
      </div>
      <a href="bookings.php" class="btn-gs btn-gs-sm">View All</a>
    </div>
    <?php if (empty($upcoming)): ?>
    <div class="text-center py-4" style="color:var(--text-light)">
      <i class="bi bi-calendar-x" style="font-size:1.8rem;display:block"></i>
      <p class="mt-2 mb-0" style="font-size:0.85rem">No upcoming reservations</p>
    </div>
    <?php else: ?>
    <table class="gs-table">
      <thead><tr><th>Guest</th><th>Room</th><th>Check-in</th><th>Nights</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($upcoming as $b): ?>
      <tr>
        <td>
          <div style="font-weight:600;font-size:0.85rem"><?= htmlspecialchars($b['first_name'].' '.$b['last_name']) ?></div>
          <div style="font-size:0.72rem;color:var(--text-light)"><?= $b['booking_ref'] ?></div>
        </td>
        <td><strong><?= $b['room_number'] ?></strong><br><small style="color:var(--text-light)"><?= $b['type_name'] ?></small></td>
        <td style="font-weight:600;font-size:0.83rem"><?= date('M d, Y', strtotime($b['check_in_date'])) ?></td>
        <td style="text-align:center"><?= $b['total_nights'] ?></td>
        <td><span class="badge-gs badge-<?= $b['booking_status'] ?>"><?= ucfirst($b['booking_status']) ?></span></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

</div>

<!-- ACTIVE STAYS -->
<div class="panel" style="margin:0">
  <div class="panel-header">
    <div>
      <div class="panel-title"><i class="bi bi-house-check me-1"></i>Active Stays</div>
      <div class="panel-subtitle"><?= count($active_stays) ?> guest(s) currently checked in</div>
    </div>
  </div>
  <?php if (empty($active_stays)): ?>
  <div class="text-center py-4" style="color:var(--text-light)">
    <i class="bi bi-building" style="font-size:1.8rem;display:block"></i>
    <p class="mt-2 mb-0" style="font-size:0.85rem">No active guests</p>
  </div>
  <?php else: ?>
  <div style="overflow-x:auto">
  <table class="gs-table">
    <thead>
      <tr><th>Guest</th><th>Room</th><th>Check-in</th><th>Check-out</th><th>Nights Left</th><th>Total</th><th>Paid</th><th>Balance</th></tr>
    </thead>
    <tbody>
    <?php foreach ($active_stays as $b):
      $nights_left = max(0, (new DateTime($b['check_out_date']))->diff(new DateTime($today))->days);
      $is_overdue  = $b['check_out_date'] < $today;
    ?>
    <tr style="<?= $is_overdue?'background:#fff5f5':'' ?>">
      <td>
        <div style="font-weight:600"><?= htmlspecialchars($b['first_name'].' '.$b['last_name']) ?></div>
        <div style="font-size:0.74rem;color:var(--text-light)"><?= htmlspecialchars($b['email']) ?></div>
      </td>
      <td><strong><?= $b['room_number'] ?></strong><br><small style="color:var(--text-light)"><?= $b['type_name'] ?></small></td>
      <td style="font-size:0.83rem"><?= date('M d, Y', strtotime($b['check_in_date'])) ?></td>
      <td style="font-size:0.83rem;color:<?= $is_overdue?'#dc2626':'var(--text-dark)' ?>;font-weight:<?= $is_overdue?'700':'400' ?>">
        <?= date('M d, Y', strtotime($b['check_out_date'])) ?>
        <?php if ($is_overdue): ?><span class="badge-gs badge-cancelled" style="font-size:0.65rem;margin-left:4px">Overdue</span><?php endif; ?>
      </td>
      <td style="text-align:center;font-weight:600;color:<?= $nights_left<=1?'#dc2626':($nights_left<=2?'#d97706':'var(--text-dark)') ?>">
        <?= $is_overdue ? '—' : $nights_left.' night(s)' ?>
      </td>
      <td style="font-weight:600">₱<?= number_format($b['total_amount'],2) ?></td>
      <td style="color:#16a34a;font-weight:600">₱<?= number_format($b['total_paid']??0,2) ?></td>
      <td style="color:<?= ($b['balance_due']??1)>0?'#dc2626':'#16a34a' ?>;font-weight:700">₱<?= number_format($b['balance_due']??0,2) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
