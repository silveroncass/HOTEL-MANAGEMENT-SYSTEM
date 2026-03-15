<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin('staff');
$active_page = 'login_logs';
// Staff sees only their own logs
$db = getDB();
$logs = $db->prepare("SELECT l.*, u.first_name, u.last_name, r.role_name FROM login_logs l LEFT JOIN users u ON l.user_id=u.user_id LEFT JOIN roles r ON u.role_id=r.role_id WHERE l.user_id=? ORDER BY l.logged_at DESC LIMIT 100");
$logs->execute([$_SESSION['user_id']]);
$logs = $logs->fetchAll();
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="topbar">
  <div class="topbar-title"><h1>My Login History</h1><p>Your recent login activity</p></div>
</div>
<div class="page-content">
  <div class="panel">
    <div style="overflow-x:auto">
    <table class="gs-table">
      <thead><tr><th>Date/Time</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($logs as $l): ?>
      <tr>
        <td><?= date('M d, Y H:i:s', strtotime($l['logged_at'])) ?></td>
        <td><span class="badge-gs <?= $l['status']==='success' ? 'badge-confirmed' : 'badge-cancelled' ?>"><?= ucfirst($l['status']) ?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($logs)): ?><tr><td colspan="2" class="text-center py-4" style="color:var(--text-light)">No logs yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
