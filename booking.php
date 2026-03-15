<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDB();
$logged_in = isLoggedIn() && $_SESSION['role'] === 'user';
$user_id   = $_SESSION['user_id'] ?? null;
$msg = $err = '';

// Read flash message from session (set after successful booking redirect)
if (!empty($_SESSION['booking_msg'])) {
    $msg = $_SESSION['booking_msg'];
    unset($_SESSION['booking_msg']);
}

// ---- REGISTRATION ----
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='register') {
    $first=trim($_POST['first_name']); $last=trim($_POST['last_name']);
    $email=trim($_POST['email']);      $uname=trim($_POST['username']);
    $pass=trim($_POST['password']);    $phone=trim($_POST['phone']??'');
    $chk=$db->prepare("SELECT COUNT(*) FROM users WHERE username=? OR email=?");
    $chk->execute([$uname,$email]);
    if ($chk->fetchColumn()>0) { $err="Username or email already exists."; }
    elseif (strlen($pass)<6) { $err="Password must be at least 6 characters."; }
    else {
        $role_id=$db->query("SELECT role_id FROM roles WHERE role_name='user'")->fetchColumn();
        $st=$db->prepare("INSERT INTO users (role_id,first_name,last_name,email,username,password,phone) VALUES (?,?,?,?,?,?,?)");
        $st->execute([$role_id,$first,$last,$email,$uname,hashPassword($pass),$phone]);
        $_SESSION['user_id']=$db->lastInsertId(); $_SESSION['username']=$uname;
        $_SESSION['role']='user'; $_SESSION['first_name']=$first; $_SESSION['last_name']=$last; $_SESSION['email']=$email;
        $logged_in=true; $user_id=$_SESSION['user_id'];
        $msg="Welcome, $first! You can now book a room.";
    }
}

// ---- LOGIN ----
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='login_guest') {
    $uname=trim($_POST['username']); $pass=trim($_POST['password']);
    $st=$db->prepare("SELECT u.*,r.role_name FROM users u JOIN roles r ON u.role_id=r.role_id WHERE u.username=?");
    $st->execute([$uname]); $u=$st->fetch();
    if ($u && $u['role_name']==='user' && verifyPassword($pass,$u['password'])) {
        $_SESSION['user_id']=$u['user_id']; $_SESSION['username']=$u['username'];
        $_SESSION['role']='user'; $_SESSION['first_name']=$u['first_name']; $_SESSION['last_name']=$u['last_name']; $_SESSION['email']=$u['email'];
        $logged_in=true; $user_id=$u['user_id'];
        $msg="Welcome back, ".$u['first_name']."!";
    } else { $err="Invalid username or password."; }
}

// ---- BOOKING (with ₱500 deposit) ----
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='book' && $logged_in) {
    $room_id     = (int)$_POST['room_id'];
    $check_in    = $_POST['check_in'];
    $check_out   = $_POST['check_out'];
    $num_guests  = (int)($_POST['num_guests']??1);
    $special     = trim($_POST['special_request']??'');
    $dep_method  = $_POST['deposit_method'] ?? 'cash';
    $dep_ref     = trim($_POST['deposit_ref'] ?? '');
    $DEPOSIT     = 500.00;

    $ri=$db->prepare("SELECT rt.max_occupancy,rt.base_price,rt.type_name,r.room_number FROM rooms r JOIN room_types rt ON r.type_id=rt.type_id WHERE r.room_id=?");
    $ri->execute([$room_id]); $room_info=$ri->fetch();
    $max=(int)($room_info['max_occupancy']??2);

    $room_busy=$db->prepare("SELECT COUNT(*) FROM bookings WHERE room_id=? AND status NOT IN ('cancelled','checked_out') AND check_in_date < ? AND check_out_date > ?");
    $room_busy->execute([$room_id,$check_out,$check_in]);

    // Allow multiple bookings per user

    if ($check_out <= $check_in) {
        $err="Check-out date must be after check-in date.";
    } elseif ($num_guests<1 || $num_guests>$max) {
        $err="This room fits a maximum of {$max} guest(s).";
    } elseif ($room_busy->fetchColumn()>0) {
        $err="Room {$room_info['room_number']} is already booked for those dates. Please choose different dates or another room.";
    } else {
        try {
            $db->beginTransaction();
            $db->prepare("CALL sp_create_booking(?,?,?,?,?,?,@ref)")->execute([$user_id,$room_id,$check_in,$check_out,$num_guests,$special]);
            $ref=$db->query("SELECT @ref")->fetchColumn();
            $new_bid=$db->query("SELECT booking_id FROM bookings WHERE booking_ref='$ref'")->fetchColumn();
            // Record ₱500 deposit
            $db->prepare("INSERT INTO payments (booking_id,amount_paid,payment_method,reference_no,notes,received_by,payment_date) VALUES (?,?,?,?,?,NULL,NOW())")
               ->execute([$new_bid,$DEPOSIT,$dep_method,$dep_ref?:null,'Deposit at booking']);
            $nights=(new DateTime($check_in))->diff(new DateTime($check_out))->days;
            $total=$nights*(float)$room_info['base_price'];
            $balance=$total-$DEPOSIT;
            $_SESSION['booking_msg'] = "🎉 Booking confirmed! Reference: <strong>$ref</strong><br>
                  <small style='opacity:0.85'>
                  {$room_info['type_name']} · Room {$room_info['room_number']} &nbsp;|&nbsp;
                  ".date('M d',strtotime($check_in))." → ".date('M d, Y',strtotime($check_out))." &nbsp;|&nbsp;
                  {$nights} night(s)<br>
                  Total: <strong>₱".number_format($total,2)."</strong> &nbsp;·&nbsp;
                  Deposit paid: <strong>₱".number_format($DEPOSIT,2)."</strong> &nbsp;·&nbsp;
                  Balance at check-in: <strong>₱".number_format($balance,2)."</strong>
                  </small>";
            $db->commit();
            session_write_close();
            header("Location: booking.php?ci=".$_POST['check_in']."&co=".$_POST['check_out']);
            exit;
        } catch (PDOException $e) {
            $db->rollBack();
            $err=$e->getMessage();
            if (strpos($err,'SQLSTATE')!==false) { preg_match('/SET MESSAGE_TEXT = \'([^\']+)\'|: (.+)$/',$err,$m); $err=$m[1]??$m[2]??"Booking failed. Please try again."; }
        }
    }
}

// ---- FETCH ROOMS ----
$today            = date('Y-m-d');
$tomorrow         = date('Y-m-d',strtotime('+1 day'));
// If GET dates are in the past (stale/cached), reset them to today
$check_in_search  = (!empty($_GET['check_in'])  && $_GET['check_in']  >= $today) ? $_GET['check_in']  : $today;
$check_out_search = (!empty($_GET['check_out']) && $_GET['check_out'] > $check_in_search) ? $_GET['check_out'] : $tomorrow;
$type_filter      = $_GET['type_filter'] ?? '';

if ($check_out_search <= $check_in_search)
    $check_out_search = date('Y-m-d',strtotime($check_in_search.' +1 day'));

$sql = "SELECT r.*, rt.type_name, rt.base_price, rt.max_occupancy, rt.amenities, rt.description,
    CASE WHEN r.status = 'maintenance' THEN 0
         WHEN EXISTS (
           SELECT 1 FROM bookings b WHERE b.room_id=r.room_id AND b.status NOT IN ('cancelled','checked_out')
           AND b.check_in_date < ? AND b.check_out_date > ?
         ) THEN 0
         ELSE 1 END AS is_available
FROM rooms r JOIN room_types rt ON r.type_id=rt.type_id
WHERE r.status != 'maintenance'";
$params = [$check_out_search,$check_in_search];
// type filter removed
$sql .= " ORDER BY rt.base_price";
$stmt=$db->prepare($sql); $stmt->execute($params); $rooms=$stmt->fetchAll();

$room_types=[];

$my_bookings=[];
if ($logged_in) {
    $mb=$db->prepare("SELECT * FROM v_booking_details WHERE user_id=? ORDER BY created_at DESC");
    $mb->execute([$user_id]); $my_bookings=$mb->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GrandStay — Book a Room</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--green-dark:#1a3028;--green-main:#2e6b3e;--green-light:#4a9960;--green-pale:#e8f5e9;--gold:#c9a84c;--text-dark:#1a2e1e;--text-mid:#4a5e4f;--text-light:#8aab90;--border:#e2ede4}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:#f4f9f5;color:var(--text-dark)}
.nav-bar{background:var(--green-dark);padding:0 40px;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.nav-brand{display:flex;align-items:center;gap:10px;text-decoration:none}
.nav-logo{width:36px;height:36px;background:rgba(255,255,255,0.12);border-radius:8px;display:flex;align-items:center;justify-content:center}
.nav-logo i{color:#fff;font-size:1rem}
.nav-name{font-family:'Playfair Display',serif;font-size:1.2rem;color:#fff;font-weight:600}
.nav-actions{display:flex;align-items:center;gap:12px}
.btn-nav{padding:8px 18px;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:0.85rem;font-weight:600;cursor:pointer;transition:all 0.2s;border:none;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.btn-nav-outline{background:transparent;color:rgba(255,255,255,0.8);border:1.5px solid rgba(255,255,255,0.3)}
.btn-nav-outline:hover{background:rgba(255,255,255,0.1);color:#fff}
.btn-nav-solid{background:#fff;color:var(--green-dark)}
.btn-nav-solid:hover{background:#f0f0f0}
.hero{background:linear-gradient(160deg,#1a3028,#2d5016);padding:56px 40px 36px;text-align:center}
.hero h1{font-family:'Playfair Display',serif;font-size:2.6rem;color:#fff;line-height:1.2;margin-bottom:10px}
.hero h1 span{color:var(--gold)}
.hero p{color:rgba(255,255,255,0.6);font-size:1rem}
.search-bar-wrap{background:var(--green-dark);padding:0 40px 36px}
.search-bar{background:#fff;border-radius:16px;padding:22px 26px;max-width:800px;margin:0 auto;box-shadow:0 4px 24px rgba(0,0,0,0.15)}
.search-bar h3{font-size:0.95rem;font-weight:600;color:var(--text-dark);margin-bottom:14px}
.search-grid{display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:end}
.sf label{display:block;font-size:0.72rem;font-weight:700;color:var(--text-mid);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:5px}
.sf input,.sf select{width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.88rem;color:var(--text-dark);background:#fff;outline:none}
.sf input:focus,.sf select:focus{border-color:var(--green-main)}
.btn-search{padding:10px 20px;background:var(--green-dark);color:#fff;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.88rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;white-space:nowrap;height:42px}
.btn-search:hover{background:var(--green-main)}
.main-container{max-width:1100px;margin:0 auto;padding:30px 24px}
.section-title{font-family:'Playfair Display',serif;font-size:1.5rem;color:var(--text-dark);margin-bottom:4px}
.section-sub{font-size:0.85rem;color:var(--text-light);margin-bottom:22px}
.room-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px}
.room-card{background:#fff;border-radius:16px;overflow:hidden;border:1px solid var(--border);transition:all 0.25s}
.room-card:hover{transform:translateY(-3px);box-shadow:0 8px 32px rgba(0,0,0,0.1)}
.room-img{width:100%;height:195px;object-fit:cover;display:block}
.room-badge{position:absolute;top:12px;right:12px;padding:4px 10px;border-radius:20px;font-size:0.72rem;font-weight:700}
.badge-avail{background:#dcfce7;color:#16a34a}
.badge-unavail{background:#fee2e2;color:#dc2626}
.room-img-wrap{position:relative}
.room-body{padding:16px 18px}
.room-type-label{font-size:0.7rem;font-weight:700;color:var(--text-light);letter-spacing:0.1em;text-transform:uppercase;margin-bottom:3px}
.room-name{font-family:'Playfair Display',serif;font-size:1.15rem;color:var(--text-dark);margin-bottom:5px}
.room-desc{font-size:0.82rem;color:var(--text-mid);margin-bottom:6px;line-height:1.5}
.room-capacity{font-size:0.78rem;color:var(--text-mid);margin-bottom:10px}
.amenity-tags{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:14px}
.amenity-tag{padding:3px 9px;background:var(--green-pale);border-radius:20px;font-size:0.7rem;color:var(--green-main);font-weight:500}
.room-footer{display:flex;align-items:center;justify-content:space-between}
.room-price{font-family:'Playfair Display',serif;font-size:1.2rem;font-weight:700;color:var(--text-dark)}
.room-price span{font-family:'DM Sans',sans-serif;font-size:0.75rem;font-weight:400;color:var(--text-light)}
.btn-book{padding:9px 16px;background:var(--green-dark);color:#fff;border:none;border-radius:10px;font-size:0.83rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;transition:all 0.2s}
.btn-book:hover{background:var(--green-main)}
.btn-book.unavail{background:#d1d5db;color:#6b7280;cursor:not-allowed}
/* table */
.my-bookings-section{margin-top:40px}
.booking-table-wrap{overflow-x:auto;background:#fff;border-radius:16px;border:1px solid var(--border)}
table.bk-table{width:100%;border-collapse:collapse}
table.bk-table th{padding:11px 14px;font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-light);border-bottom:1px solid var(--border);background:#fafcfb}
table.bk-table td{padding:11px 14px;font-size:0.83rem;border-bottom:1px solid #f0f5f1}
table.bk-table tr:last-child td{border-bottom:none}
.sbadge{padding:3px 10px;border-radius:20px;font-size:0.7rem;font-weight:700;text-transform:capitalize}
.sbadge-pending{background:#fef9c3;color:#854d0e}
.sbadge-confirmed{background:#dcfce7;color:#16a34a}
.sbadge-checked_in{background:#dbeafe;color:#1d4ed8}
.sbadge-checked_out{background:#f1f5f9;color:#64748b}
.sbadge-cancelled{background:#fee2e2;color:#dc2626}
.sbadge-paid{background:#dcfce7;color:#16a34a}
.sbadge-unpaid{background:#fef9c3;color:#854d0e}
.sbadge-refunded{background:#fee2e2;color:#dc2626}
/* modal */
.modal-hdr{background:var(--green-dark);color:#fff;padding:18px 22px;display:flex;align-items:center;justify-content:space-between}
.modal-hdr h5{font-family:'Playfair Display',serif;font-size:1.1rem;margin:0;color:#fff}
.modal-hdr .btn-close{filter:invert(1);opacity:0.8}
.modal-content{border-radius:16px!important;border:none!important;overflow:hidden}
.fgs label{font-size:0.73rem;font-weight:700;color:var(--text-mid);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;display:block}
.fgs input,.fgs select,.fgs textarea{width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.88rem;color:var(--text-dark);background:#fff;outline:none;margin-bottom:12px}
.fgs input:focus,.fgs select:focus,.fgs textarea:focus{border-color:var(--green-main);box-shadow:0 0 0 3px rgba(46,107,62,0.08)}
.fgs textarea{resize:vertical;min-height:60px}
.btn-submit-gs{width:100%;padding:12px;background:var(--green-dark);color:#fff;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.95rem;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all 0.2s}
.btn-submit-gs:hover{background:var(--green-main)}
.sumbox{background:#f4f9f5;border-radius:12px;padding:13px 15px;margin-bottom:12px;font-size:0.83rem}
.sumrow{display:flex;justify-content:space-between;padding:3px 0;color:var(--text-mid)}
.sumrow strong{color:var(--text-dark)}
.sumtotal{border-top:1px solid #b8dfc1;margin-top:7px;padding-top:7px;font-weight:700;font-size:0.9rem}
.dep-box{background:#fff8e1;border:1px solid #f5d87a;border-radius:10px;padding:11px 14px;font-size:0.82rem;color:#856404;margin-bottom:12px}
.dep-box strong{display:block;margin-bottom:2px}
.notice-box{background:#e8f5e9;border:1px solid #a7d7b0;border-radius:10px;padding:10px 14px;font-size:0.81rem;color:#1a6b2e;margin-bottom:12px;display:flex;gap:8px;align-items:flex-start}
.alert-err{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:12px 15px;border-radius:10px;font-size:0.84rem;margin-bottom:16px;display:flex;gap:8px;align-items:flex-start}
.alert-ok{background:#dcfce7;border:1px solid #a7d7b0;color:#166534;padding:12px 15px;border-radius:10px;font-size:0.84rem;margin-bottom:16px;display:flex;gap:8px;align-items:flex-start}
.tabs-auth{display:flex;border-bottom:2px solid var(--border);margin-bottom:18px}
.tab-auth{flex:1;padding:9px;text-align:center;font-weight:600;font-size:0.86rem;cursor:pointer;color:var(--text-light);border-bottom:2px solid transparent;margin-bottom:-2px;transition:all 0.2s}
.tab-auth.active{color:var(--green-main);border-bottom-color:var(--green-main)}
.ref-field{display:none;margin-top:-6px}
@media(max-width:650px){.search-grid{grid-template-columns:1fr 1fr}.search-grid .btn-search{grid-column:1/-1}}
</style>
</head>
<body>

<nav class="nav-bar">
  <a href="booking.php" class="nav-brand">
    <div class="nav-logo"><i class="bi bi-building"></i></div>
    <div class="nav-name">GrandStay</div>
  </a>
  <div class="nav-actions">
    <?php if ($logged_in): ?>
    <span style="color:rgba(255,255,255,0.6);font-size:0.85rem">Welcome, <?= htmlspecialchars($_SESSION['first_name']) ?></span>
    <a href="../logout.php" class="btn-nav btn-nav-outline"><i class="bi bi-box-arrow-right"></i>Sign Out</a>
    <?php else: ?>
    <button class="btn-nav btn-nav-outline" onclick="openAuthModal('login')"><i class="bi bi-person"></i>Sign In</button>
    <button class="btn-nav btn-nav-solid" onclick="openAuthModal('register')"><i class="bi bi-person-plus"></i>Register</button>
    <?php endif; ?>
  </div>
</nav>

<div class="hero">
  <h1>Your Perfect Stay<br>Awaits at <span>GrandStay</span></h1>
  <p>Choose your dates, pick your room, and book in minutes</p>
</div>

<div class="search-bar-wrap">
<div class="search-bar">
  <h3><i class="bi bi-search me-2" style="color:var(--green-main)"></i>Find Your Room</h3>
  <form method="GET" action="booking.php" id="searchForm">
    <div class="search-grid">
      <div class="sf">
        <label>Check-in Date</label>
        <input type="date" name="check_in" id="s_ci"
               value="<?= htmlspecialchars($check_in_search) ?>"
               min="<?= $today ?>" required
               onchange="syncSearchOut()">
      </div>
      <div class="sf">
        <label>Check-out Date</label>
        <input type="date" name="check_out" id="s_co"
               value="<?= htmlspecialchars($check_out_search) ?>"
               min="<?= $check_out_search ?>" required>
      </div>
<button type="submit" class="btn-search"><i class="bi bi-search"></i>Search</button>
    </div>
  </form>
</div>
</div>

<div class="main-container">

  <?php if ($msg): ?>
  <div class="alert-ok"><i class="bi bi-check-circle-fill" style="flex-shrink:0;margin-top:1px"></i><span><?= $msg ?></span></div>
  <?php endif; ?>
  <?php if ($err): ?>
  <div class="alert-err"><i class="bi bi-exclamation-circle-fill" style="flex-shrink:0;margin-top:1px"></i><span><?= htmlspecialchars($err) ?></span></div>
  <?php endif; ?>

  <div class="section-title">Available Rooms</div>
  <div class="section-sub">
    <?= date('M d', strtotime($check_in_search)) ?> → <?= date('M d, Y', strtotime($check_out_search)) ?>
    · <?= count(array_filter($rooms, fn($r)=>$r['is_available'])) ?> available

  </div>

  <div class="room-grid">
  <?php
  $photos=['Single'=>'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=600&q=80',
           'Twin'  =>'https://images.unsplash.com/photo-1595576508898-0ad5c879a061?w=600&q=80',
           'Double'=>'https://images.unsplash.com/photo-1618773928121-c32242e63f39?w=600&q=80',
           'Deluxe'=>'https://images.unsplash.com/photo-1611892440504-42a792e24d32?w=600&q=80',
           'Family'=>'https://images.unsplash.com/photo-1555854877-bab0e564b8d5?w=600&q=80',
           'Suite' =>'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=600&q=80'];
  foreach ($rooms as $r):
    $ams=$r['amenities']?array_slice(explode(',',$r['amenities']),0,4):[];
    $extra=$r['amenities']?max(0,count(explode(',',$r['amenities']))-4):0;
    $photo=$photos[$r['type_name']]??$photos['Single'];
  ?>
  <div class="room-card">
    <div class="room-img-wrap">
      <img src="<?= $photo ?>" class="room-img" alt="<?= htmlspecialchars($r['type_name']) ?>" loading="lazy">
      <span class="room-badge <?= $r['is_available']?'badge-avail':'badge-unavail' ?>"><?= $r['is_available']?'Available':'Booked' ?></span>
    </div>
    <div class="room-body">
      <div class="room-type-label"><?= htmlspecialchars($r['type_name']) ?> Room · <?= $r['room_number'] ?></div>
      <div class="room-name">The <?= htmlspecialchars($r['type_name']) ?></div>
      <div class="room-desc"><?= htmlspecialchars($r['description']??'A comfortable room for your stay.') ?></div>
      <div class="room-capacity"><i class="bi bi-people me-1"></i>Up to <strong><?= (int)$r['max_occupancy'] ?></strong> guest<?= $r['max_occupancy']>1?'s':'' ?></div>
      <div class="amenity-tags">
        <?php foreach ($ams as $a): ?><span class="amenity-tag"><i class="bi bi-check me-1"></i><?= htmlspecialchars(trim($a)) ?></span><?php endforeach; ?>
        <?php if ($extra>0): ?><span class="amenity-tag">+<?= $extra ?> more</span><?php endif; ?>
      </div>
      <div class="room-footer">
        <div class="room-price">₱<?= number_format($r['base_price'],0) ?> <span>/ night</span></div>
        <?php if ($r['is_available']): ?>
        <button class="btn-book" onclick="openBooking(<?= htmlspecialchars(json_encode($r),ENT_QUOTES) ?>,'<?= $check_in_search ?>','<?= $check_out_search ?>')">
          <?php if ($logged_in): ?>Book Now <i class="bi bi-arrow-right"></i><?php else: ?><i class="bi bi-lock"></i> Login to Book<?php endif; ?>
        </button>
        <?php else: ?>
        <button class="btn-book unavail" disabled>Not Available</button>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  </div>

  <?php if ($logged_in && !empty($my_bookings)): ?>
  <div class="my-bookings-section">
    <div class="section-title">My Reservations</div>
    <div class="section-sub">Your booking history and payment status</div>
    <div class="booking-table-wrap">
      <table class="bk-table">
        <thead>
          <tr>
            <th>Reference</th><th>Room</th><th>Check-in</th><th>Check-out</th>
            <th>Nights</th><th>Total</th><th>Paid</th><th>Balance</th><th>Payment</th><th>Status</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($my_bookings as $b):
          $paid    = (float)($b['total_paid']   ?? 0);
          $balance = (float)($b['balance_due']  ?? $b['total_amount']);
          $ps      = $b['payment_status'] ?? 'unpaid';
        ?>
        <tr>
          <td style="font-family:monospace;font-size:0.75rem"><?= htmlspecialchars($b['booking_ref']) ?></td>
          <td><?= htmlspecialchars($b['room_number'].' — '.$b['type_name']) ?></td>
          <td><?= date('M d, Y',strtotime($b['check_in_date'])) ?></td>
          <td><?= date('M d, Y',strtotime($b['check_out_date'])) ?></td>
          <td style="text-align:center"><?= $b['total_nights'] ?></td>
          <td>₱<?= number_format($b['total_amount'],2) ?></td>
          <td style="color:#16a34a;font-weight:600">₱<?= number_format($paid,2) ?></td>
          <td style="color:<?= $balance>0?'#dc2626':'#16a34a' ?>;font-weight:600">₱<?= number_format($balance,2) ?></td>
          <td><span class="sbadge sbadge-<?= $ps ?>"><?= ucfirst($ps) ?></span></td>
          <td><span class="sbadge sbadge-<?= $b['booking_status'] ?>"><?= ucfirst(str_replace('_',' ',$b['booking_status'])) ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

</div>

<!-- AUTH MODAL -->
<div class="modal fade" id="authModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-hdr">
        <h5><i class="bi bi-building me-2"></i>GrandStay Guest Portal</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="tabs-auth">
          <div class="tab-auth active" id="tab-login" onclick="switchTab('login')">Sign In</div>
          <div class="tab-auth" id="tab-register" onclick="switchTab('register')">Create Account</div>
        </div>
        <div id="pane-login">
          <form method="POST" action="booking.php?<?= http_build_query($_GET) ?>" class="fgs">
            <input type="hidden" name="action" value="login_guest">
            <label>Username</label>
            <input type="text" name="username" placeholder="Your username" required autocomplete="username">
            <label>Password</label>
            <input type="password" name="password" placeholder="Your password" required>
            <button type="submit" class="btn-submit-gs"><i class="bi bi-box-arrow-in-right"></i> Sign In</button>
          </form>
          <p style="text-align:center;font-size:0.82rem;margin-top:10px;color:var(--text-light)">
            No account? <a href="#" onclick="switchTab('register');return false;" style="color:var(--green-main);font-weight:600">Register here</a>
          </p>
        </div>
        <div id="pane-register" style="display:none">
          <form method="POST" action="booking.php?<?= http_build_query($_GET) ?>" class="fgs">
            <input type="hidden" name="action" value="register">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 12px">
              <div><label>First Name</label><input type="text" name="first_name" placeholder="First name" required></div>
              <div><label>Last Name</label><input type="text" name="last_name" placeholder="Last name" required></div>
            </div>
            <label>Email</label><input type="email" name="email" placeholder="your@email.com" required>
            <label>Username</label><input type="text" name="username" placeholder="Choose a username" required>
            <label>Password</label><input type="password" name="password" placeholder="Min 6 characters" required>
            <label>Phone (optional)</label><input type="text" name="phone" placeholder="+63 9XX XXX XXXX">
            <button type="submit" class="btn-submit-gs"><i class="bi bi-person-plus"></i> Create Account</button>
          </form>
          <p style="text-align:center;font-size:0.82rem;margin-top:10px;color:var(--text-light)">
            Already have an account? <a href="#" onclick="switchTab('login');return false;" style="color:var(--green-main);font-weight:600">Sign in</a>
          </p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- BOOKING MODAL -->
<div class="modal fade" id="bookingModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-hdr">
        <h5><i class="bi bi-calendar-check me-2"></i>Confirm Your Booking</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">

        <div class="notice-box">
          <i class="bi bi-info-circle-fill" style="flex-shrink:0;margin-top:1px"></i>
          <span>A <strong>₱500 deposit</strong> is required to confirm your booking. The remaining balance will be collected at check-in or check-out.</span>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
          <div>
            <div class="sumbox" id="bookSummary" style="margin-bottom:0"></div>
          </div>
          <div>
            <div class="dep-box">
              <strong><i class="bi bi-cash-coin me-1"></i>Deposit Breakdown</strong>
              <div style="display:flex;justify-content:space-between;padding:2px 0"><span>Deposit now</span><strong id="dep_now">₱500.00</strong></div>
              <div style="display:flex;justify-content:space-between;padding:2px 0"><span>Balance at check-in</span><strong id="dep_balance">—</strong></div>
            </div>
          </div>
        </div>

        <hr style="margin:14px 0;border-color:var(--border)">

        <form method="POST" action="booking.php?<?= http_build_query($_GET) ?>" id="bookingForm" class="fgs">
          <input type="hidden" name="action" value="book">
          <input type="hidden" name="room_id" id="bk_room_id">
          <input type="hidden" name="max_occ" id="bk_max_occ">

          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0 12px">
            <div>
              <label>Check-in Date</label>
              <input type="date" name="check_in" id="bk_ci" required onchange="onCiChange()">
            </div>
            <div>
              <label>Check-out Date</label>
              <input type="date" name="check_out" id="bk_co" required onchange="updateSummary()">
            </div>
            <div>
              <label>Guests</label>
              <select name="num_guests" id="bk_guests" onchange="updateSummary()"></select>
            </div>
          </div>

          <label>Special Requests <span style="font-weight:400;text-transform:none;font-size:0.78rem">(optional)</span></label>
          <textarea name="special_request" placeholder="e.g. early check-in, extra pillows, ground floor..."></textarea>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 12px">
            <div>
              <label>Deposit Payment Method</label>
              <select name="deposit_method" id="dep_method" onchange="toggleRef()">
                <option value="cash">💵 Cash</option>
                <option value="gcash">📱 GCash</option>
                <option value="credit_card">💳 Credit Card</option>
                <option value="debit_card">💳 Debit Card</option>
                <option value="paymaya">📱 PayMaya</option>
                <option value="bank_transfer">🏦 Bank Transfer</option>
              </select>
            </div>
            <div id="ref_wrap" class="ref-field">
              <label>Reference / Transaction No.</label>
              <input type="text" name="deposit_ref" id="dep_ref" placeholder="e.g. GCash ref 123456">
            </div>
          </div>

          <button type="submit" class="btn-submit-gs"><i class="bi bi-check-circle"></i> Confirm Booking & Pay ₱500 Deposit</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentRoom = null;
const loggedIn  = <?= $logged_in ? 'true' : 'false' ?>;
const TODAY     = '<?= $today ?>';
const DEPOSIT   = 500;

// ── Search: sync checkout min with checkin ────────────────────
function syncSearchOut() {
  const ci = document.getElementById('s_ci').value;
  const co = document.getElementById('s_co');
  if (!ci) return;
  const next = new Date(ci); next.setDate(next.getDate()+1);
  const ns = next.toISOString().split('T')[0];
  co.min = ns;
  if (co.value <= ci) co.value = ns;
}
syncSearchOut();

// ── Auth ──────────────────────────────────────────────────────
function openAuthModal(tab) { switchTab(tab); new bootstrap.Modal(document.getElementById('authModal')).show(); }
function switchTab(tab) {
  document.getElementById('pane-login').style.display    = tab==='login'    ?'block':'none';
  document.getElementById('pane-register').style.display = tab==='register' ?'block':'none';
  document.getElementById('tab-login').classList.toggle('active',    tab==='login');
  document.getElementById('tab-register').classList.toggle('active', tab==='register');
}

// ── Booking modal ─────────────────────────────────────────────
function openBooking(room, ci, co) {
  if (!loggedIn) { openAuthModal('login'); return; }
  currentRoom = room;
  document.getElementById('bk_room_id').value = room.room_id;
  document.getElementById('bk_max_occ').value = room.max_occupancy;

  // Set dates
  const bkCi = document.getElementById('bk_ci');
  const bkCo = document.getElementById('bk_co');
  bkCi.min   = TODAY;
  bkCi.value = ci || TODAY;
  setCoMin(ci || TODAY, co);

  // Guests dropdown
  buildGuests(parseInt(room.max_occupancy)||2);
  updateSummary();
  new bootstrap.Modal(document.getElementById('bookingModal')).show();
}

function setCoMin(ci, co) {
  const bkCo = document.getElementById('bk_co');
  const next = new Date(ci); next.setDate(next.getDate()+1);
  const ns   = next.toISOString().split('T')[0];
  bkCo.min   = ns;
  bkCo.value = (co > ci) ? co : ns;
}

function onCiChange() {
  const ci = document.getElementById('bk_ci').value;
  const co = document.getElementById('bk_co').value;
  setCoMin(ci, co);
  updateSummary();
}

function buildGuests(max) {
  const sel = document.getElementById('bk_guests'); sel.innerHTML='';
  for (let i=1;i<=max;i++) { const o=document.createElement('option'); o.value=i; o.textContent=i+(i===1?' Guest':' Guests'); sel.appendChild(o); }
}

function updateSummary() {
  if (!currentRoom) return;
  const ci = document.getElementById('bk_ci').value;
  const co = document.getElementById('bk_co').value;
  const g  = document.getElementById('bk_guests')?.value || 1;
  if (!ci || !co || co <= ci) return;
  const nights = Math.round((new Date(co)-new Date(ci))/86400000);
  const total  = nights * parseFloat(currentRoom.base_price);
  const bal    = total - DEPOSIT;
  document.getElementById('bookSummary').innerHTML =
    `<div class="sumrow"><span>Room</span><strong>${currentRoom.room_number} — ${currentRoom.type_name}</strong></div>
     <div class="sumrow"><span>Check-in</span><strong>${ci}</strong></div>
     <div class="sumrow"><span>Check-out</span><strong>${co}</strong></div>
     <div class="sumrow"><span>Nights</span><strong>${nights}</strong></div>
     <div class="sumrow"><span>Guests</span><strong>${g}</strong></div>
     <div class="sumrow"><span>Rate</span><strong>₱${parseFloat(currentRoom.base_price).toLocaleString('en',{minimumFractionDigits:0})}/night</strong></div>
     <div class="sumrow sumtotal"><span>Total</span><strong>₱${total.toLocaleString('en',{minimumFractionDigits:2})}</strong></div>`;
  document.getElementById('dep_balance').textContent = '₱'+bal.toLocaleString('en',{minimumFractionDigits:2});
}

function toggleRef() {
  const m = document.getElementById('dep_method').value;
  const r = document.getElementById('ref_wrap');
  r.style.display = (m==='cash') ? 'none' : 'block';
}
toggleRef();

// ── Form guard ────────────────────────────────────────────────
document.getElementById('bookingForm').addEventListener('submit', function(e) {
  const ci  = document.getElementById('bk_ci').value;
  const co  = document.getElementById('bk_co').value;
  const g   = parseInt(document.getElementById('bk_guests').value);
  const max = parseInt(document.getElementById('bk_max_occ').value);
  if (co <= ci)  { e.preventDefault(); alert('Check-out must be after check-in.'); return; }
  if (g > max)   { e.preventDefault(); alert('Maximum '+max+' guest(s) for this room.'); return; }
  const m = document.getElementById('dep_method').value;
  if (m !== 'cash' && !document.getElementById('dep_ref').value.trim()) {
    e.preventDefault(); alert('Please enter a reference/transaction number for '+m+' payment.'); return;
  }
});

<?php if ($err): ?>
document.addEventListener('DOMContentLoaded',function(){
  openAuthModal('<?= ($_POST['action']??'')==='register'?'register':'login' ?>');
});
<?php endif; ?>
</script>
</body>
</html>
