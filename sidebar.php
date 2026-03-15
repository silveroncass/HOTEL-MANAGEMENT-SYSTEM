<?php
// includes/sidebar.php
// Usage: include this file, set $active_page before including
// $active_page = 'dashboard' | 'rooms' | 'employees' | 'bookings' | 'login_logs'
$role = $_SESSION['role'] ?? 'staff';
$name = ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '');
$initials = strtoupper(substr($_SESSION['first_name'] ?? 'U', 0, 1) . substr($_SESSION['last_name'] ?? '', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GrandStay — <?= ucfirst($active_page ?? 'Dashboard') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" rel="prefetch">
<style>
:root {
  --green-dark:  #1a3028;
  --green-mid:   #2d5016;
  --green-main:  #2e6b3e;
  --green-light: #4a9960;
  --green-pale:  #e8f5e9;
  --green-ghost: #f4f9f5;
  --gold:        #c9a84c;
  --sidebar-w:   220px;
  --text-dark:   #1a2e1e;
  --text-mid:    #4a5e4f;
  --text-light:  #8aab90;
  --border:      #e2ede4;
  --white:       #ffffff;
  --danger:      #dc2626;
  --warning:     #d97706;
  --info:        #0891b2;
}
* { box-sizing: border-box; }
body { font-family: 'DM Sans', sans-serif; background: var(--green-ghost); color: var(--text-dark); margin: 0; }

/* ---- SIDEBAR ---- */
.sidebar {
  position: fixed; top: 0; left: 0; bottom: 0;
  width: var(--sidebar-w);
  background: var(--green-dark);
  display: flex; flex-direction: column;
  z-index: 100;
  overflow-y: auto;
}
.sidebar-brand {
  padding: 24px 20px 20px;
  border-bottom: 1px solid rgba(255,255,255,0.08);
}
.sidebar-brand-icon {
  width: 36px; height: 36px;
  background: rgba(255,255,255,0.12);
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 8px;
}
.sidebar-brand-icon i { color: #fff; font-size: 1rem; }
.sidebar-brand-name { font-family: 'Playfair Display', serif; font-size: 1.15rem; color: #fff; }
.sidebar-brand-sub { font-size: 0.68rem; color: rgba(255,255,255,0.4); letter-spacing: 0.1em; text-transform: uppercase; }

.sidebar-section-label {
  padding: 18px 20px 6px;
  font-size: 0.62rem;
  font-weight: 700;
  color: rgba(255,255,255,0.3);
  letter-spacing: 0.12em;
  text-transform: uppercase;
}
.sidebar-nav a {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 20px;
  color: rgba(255,255,255,0.6);
  text-decoration: none;
  font-size: 0.875rem;
  font-weight: 500;
  border-radius: 0;
  transition: all 0.15s;
  position: relative;
}
.sidebar-nav a:hover { color: #fff; background: rgba(255,255,255,0.06); }
.sidebar-nav a.active {
  color: #fff;
  background: rgba(255,255,255,0.1);
}
.sidebar-nav a.active::before {
  content: '';
  position: absolute;
  left: 0; top: 6px; bottom: 6px;
  width: 3px;
  background: var(--gold);
  border-radius: 0 3px 3px 0;
}
.sidebar-nav .badge-count {
  margin-left: auto;
  background: var(--green-light);
  color: #fff;
  font-size: 0.65rem;
  padding: 2px 7px;
  border-radius: 20px;
  font-weight: 700;
}

.sidebar-footer {
  margin-top: auto;
  padding: 16px 20px;
  border-top: 1px solid rgba(255,255,255,0.08);
  display: flex; align-items: center; gap: 10px;
}
.sidebar-avatar {
  width: 34px; height: 34px;
  background: var(--green-light);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.8rem;
  font-weight: 700;
  color: #fff;
  flex-shrink: 0;
}
.sidebar-user-name { font-size: 0.8rem; color: #fff; font-weight: 500; }
.sidebar-user-role { font-size: 0.68rem; color: rgba(255,255,255,0.4); }
.sidebar-logout {
  margin-left: auto;
  color: rgba(255,255,255,0.4);
  cursor: pointer;
  font-size: 1rem;
  transition: color 0.15s;
}
.sidebar-logout:hover { color: #fff; }

/* ---- MAIN CONTENT ---- */
.main-wrap {
  margin-left: var(--sidebar-w);
  min-height: 100vh;
  display: flex; flex-direction: column;
}
.topbar {
  background: #fff;
  border-bottom: 1px solid var(--border);
  padding: 0 32px;
  height: 64px;
  display: flex; align-items: center; justify-content: space-between;
  position: sticky; top: 0; z-index: 50;
}
.topbar-title h1 { font-family: 'Playfair Display', serif; font-size: 1.4rem; color: var(--text-dark); margin: 0; }
.topbar-title p { font-size: 0.8rem; color: var(--text-light); margin: 0; }
.topbar-actions { display: flex; align-items: center; gap: 16px; }
.topbar-search {
  display: flex; align-items: center;
  background: var(--green-ghost);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 8px 14px;
  gap: 8px;
  font-size: 0.85rem;
  color: var(--text-light);
  min-width: 220px;
}
.topbar-search input {
  border: none; background: transparent; outline: none;
  font-family: 'DM Sans', sans-serif;
  font-size: 0.85rem; color: var(--text-dark); width: 100%;
}
.topbar-notif {
  position: relative;
  width: 38px; height: 38px;
  background: var(--green-ghost);
  border: 1px solid var(--border);
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  color: var(--text-mid);
  font-size: 1.1rem;
}
.topbar-notif .badge-dot {
  position: absolute; top: 6px; right: 6px;
  width: 8px; height: 8px;
  background: var(--danger);
  border-radius: 50%;
  border: 2px solid #fff;
}
.page-content { padding: 32px; flex: 1; }

/* ---- STAT CARDS ---- */
.stat-card {
  background: #fff;
  border-radius: 16px;
  border: 1px solid var(--border);
  padding: 24px;
  display: flex; flex-direction: column;
  gap: 12px;
  transition: box-shadow 0.2s;
}
.stat-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
.stat-card.primary { background: var(--green-dark); border-color: transparent; }
.stat-card.primary .stat-label,
.stat-card.primary .stat-change { color: rgba(255,255,255,0.6); }
.stat-card.primary .stat-value { color: #fff; }
.stat-top { display: flex; justify-content: space-between; align-items: flex-start; }
.stat-icon {
  width: 40px; height: 40px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem;
}
.stat-label { font-size: 0.8rem; color: var(--text-light); font-weight: 500; }
.stat-value { font-family: 'Playfair Display', serif; font-size: 2.2rem; font-weight: 700; color: var(--text-dark); line-height: 1; }
.stat-change { font-size: 0.78rem; display: flex; align-items: center; gap: 4px; }
.stat-change.up { color: var(--green-main); }
.stat-change.down { color: var(--danger); }

/* ---- PANELS ---- */
.panel {
  background: #fff;
  border-radius: 16px;
  border: 1px solid var(--border);
  overflow: hidden;
}
.panel-header {
  padding: 20px 24px;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
}
.panel-title { font-family: 'Playfair Display', serif; font-size: 1.1rem; color: var(--text-dark); margin: 0; }
.panel-subtitle { font-size: 0.78rem; color: var(--text-light); margin: 2px 0 0; }
.panel-body { padding: 24px; }

/* ---- BUTTONS ---- */
.btn-gs {
  font-family: 'DM Sans', sans-serif;
  font-weight: 600; font-size: 0.85rem;
  padding: 9px 18px;
  border-radius: 10px;
  border: none; cursor: pointer;
  display: inline-flex; align-items: center; gap: 6px;
  transition: all 0.2s;
  text-decoration: none;
}
.btn-gs-primary { background: var(--green-dark); color: #fff; }
.btn-gs-primary:hover { background: var(--green-main); color: #fff; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(46,107,62,0.25); }
.btn-gs-outline { background: transparent; color: var(--text-mid); border: 1.5px solid var(--border); }
.btn-gs-outline:hover { background: var(--green-ghost); color: var(--text-dark); }
.btn-gs-danger { background: #fef2f2; color: var(--danger); border: 1.5px solid #fecaca; }
.btn-gs-danger:hover { background: var(--danger); color: #fff; }
.btn-gs-gold { background: var(--gold); color: #fff; }
.btn-gs-sm { padding: 6px 12px; font-size: 0.78rem; }

/* ---- TABLE ---- */
.gs-table { width: 100%; border-collapse: collapse; }
.gs-table th {
  font-size: 0.72rem; font-weight: 700;
  letter-spacing: 0.08em; text-transform: uppercase;
  color: var(--text-light); padding: 10px 16px;
  border-bottom: 1px solid var(--border);
  background: var(--green-ghost);
}
.gs-table td { padding: 14px 16px; font-size: 0.875rem; border-bottom: 1px solid var(--border); vertical-align: middle; }
.gs-table tr:last-child td { border-bottom: none; }
.gs-table tr:hover td { background: var(--green-ghost); }

/* ---- BADGES ---- */
.badge-gs {
  padding: 4px 10px; border-radius: 20px;
  font-size: 0.72rem; font-weight: 600; display: inline-block;
}
.badge-available   { background: #dcfce7; color: #16a34a; }
.badge-occupied    { background: #fef3c7; color: #d97706; }
.badge-maintenance { background: #fef2f2; color: #dc2626; }
.badge-reserved    { background: #ede9fe; color: #7c3aed; }
.badge-confirmed   { background: #dcfce7; color: #16a34a; }
.badge-pending     { background: #fef3c7; color: #d97706; }
.badge-cancelled   { background: #fef2f2; color: #dc2626; }
.badge-checked_in  { background: #dbeafe; color: #2563eb; }
.badge-checked_out { background: #f1f5f9; color: #475569; }
.badge-admin  { background: #ffe4e6; color: #be123c; }
.badge-staff  { background: #dbeafe; color: #1d4ed8; }
.badge-active { background: #dcfce7; color: #16a34a; }
.badge-inactive { background: #f1f5f9; color: #475569; }

/* ---- MODAL ---- */
.modal-content { border-radius: 16px; border: none; }
.modal-header { background: var(--green-dark); border-radius: 16px 16px 0 0; padding: 20px 24px; }
.modal-header .modal-title { color: #fff; font-family: 'Playfair Display', serif; font-size: 1.1rem; }
.modal-header .btn-close { filter: invert(1); }
.modal-body { padding: 24px; }
.modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); }
.form-gs label { font-size: 0.78rem; font-weight: 600; color: var(--text-mid); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; }
.form-gs .form-control, .form-gs .form-select {
  border: 1.5px solid var(--border);
  border-radius: 10px;
  padding: 10px 14px;
  font-family: 'DM Sans', sans-serif;
  font-size: 0.9rem;
  background: var(--green-ghost);
  color: var(--text-dark);
  transition: all 0.2s;
}
.form-gs .form-control:focus, .form-gs .form-select:focus {
  border-color: var(--green-main);
  background: #fff;
  box-shadow: 0 0 0 4px rgba(46,107,62,0.08);
}
</style>

<div class="sidebar">
  <div class="sidebar-brand">
    <div class="sidebar-brand-icon"><i class="bi bi-building"></i></div>
    <div class="sidebar-brand-name">GrandStay</div>
    <div class="sidebar-brand-sub"><?= ucfirst($role) ?> Panel</div>
  </div>

  <div class="sidebar-section-label">Overview</div>
  <nav class="sidebar-nav">
    <a href="dashboard.php" class="<?= ($active_page ?? '') === 'dashboard' ? 'active' : '' ?>">
      <i class="bi bi-grid-1x2"></i> Dashboard
    </a>
  </nav>

  <?php if ($role === 'admin'): ?>
  <div class="sidebar-section-label">Management</div>
  <nav class="sidebar-nav">
    <a href="employees.php" class="<?= ($active_page ?? '') === 'employees' ? 'active' : '' ?>">
      <i class="bi bi-people"></i> Employees
      <span class="badge-count" id="sb-staff-count">—</span>
    </a>
    <a href="rooms.php" class="<?= ($active_page ?? '') === 'rooms' ? 'active' : '' ?>">
      <i class="bi bi-door-open"></i> Rooms
      <span class="badge-count" id="sb-room-count">—</span>
    </a>
    <a href="bookings.php" class="<?= ($active_page ?? '') === 'bookings' ? 'active' : '' ?>">
      <i class="bi bi-calendar-check"></i> Bookings
    </a>
    <a href="payments.php" class="<?= ($active_page ?? '') === 'payments' ? 'active' : '' ?>">
      <i class="bi bi-cash-stack"></i> Payments
    </a>
  </nav>
  <?php endif; ?>

  <?php if ($role === 'staff'): ?>
  <div class="sidebar-section-label">Operations</div>
  <nav class="sidebar-nav">
    <a href="checkins.php" class="<?= ($active_page ?? '') === 'checkins' ? 'active' : '' ?>">
      <i class="bi bi-box-arrow-in-right"></i> Check-ins
    </a>
    <a href="checkouts.php" class="<?= ($active_page ?? '') === 'checkouts' ? 'active' : '' ?>">
      <i class="bi bi-box-arrow-right"></i> Check-outs
    </a>
    <a href="bookings.php" class="<?= ($active_page ?? '') === 'bookings' ? 'active' : '' ?>">
      <i class="bi bi-calendar-check"></i> Bookings
    </a>
  </nav>
  <?php endif; ?>

  <div class="sidebar-section-label">Monitoring</div>
  <nav class="sidebar-nav">
    <a href="login_logs.php" class="<?= ($active_page ?? '') === 'login_logs' ? 'active' : '' ?>">
      <i class="bi bi-shield-check"></i> Login Logs
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-avatar"><?= $initials ?></div>
    <div>
      <div class="sidebar-user-name"><?= htmlspecialchars($name) ?></div>
      <div class="sidebar-user-role"><?= ucfirst($role) ?></div>
    </div>
    <a href="../logout.php" class="sidebar-logout" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
  </div>
</div>

<div class="main-wrap">
