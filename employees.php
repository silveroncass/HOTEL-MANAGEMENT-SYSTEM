<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin('admin');
$active_page = 'employees';

$db = getDB();
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_employee') {
        $first  = trim($_POST['first_name']);
        $last   = trim($_POST['last_name']);
        $email  = trim($_POST['email']);
        $uname  = trim($_POST['username']);
        $phone  = trim($_POST['phone']);
        $pass   = trim($_POST['password']);
        $addr   = trim($_POST['address']);

        // Validate unique
        $chk = $db->prepare("SELECT COUNT(*) FROM users WHERE username=? OR email=?");
        $chk->execute([$uname, $email]);
        if ($chk->fetchColumn() > 0) {
            $err = "Username or email already exists.";
        } elseif (strlen($pass) < 6) {
            $err = "Password must be at least 6 characters.";
        } else {
            $hashed = hashPassword($pass);
            $st = $db->prepare("INSERT INTO users (role_id,first_name,last_name,email,username,password,phone,address,created_by) VALUES (2,?,?,?,?,?,?,?,?)");
            $st->execute([$first,$last,$email,$uname,$hashed,$phone,$addr,$_SESSION['user_id']]);
            $msg = "Employee '{$uname}' created successfully. They can now log in.";
        }
    }

    if ($action === 'edit_employee') {
        $uid   = (int)$_POST['user_id'];
        $first = trim($_POST['first_name']);
        $last  = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $addr  = trim($_POST['address']);
        $active = (int)$_POST['is_active'];

        // Check email uniqueness excluding self
        $chk = $db->prepare("SELECT COUNT(*) FROM users WHERE email=? AND user_id!=?");
        $chk->execute([$email,$uid]);
        if ($chk->fetchColumn() > 0) {
            $err = "Email already in use.";
        } else {
            $st = $db->prepare("UPDATE users SET first_name=?,last_name=?,email=?,phone=?,address=?,is_active=? WHERE user_id=?");
            $st->execute([$first,$last,$email,$phone,$addr,$active,$uid]);

            // Optional password reset
            if (!empty($_POST['new_password'])) {
                $np = trim($_POST['new_password']);
                if (strlen($np) < 6) { $err = "New password must be 6+ chars."; }
                else {
                    $db->prepare("UPDATE users SET password=? WHERE user_id=?")->execute([hashPassword($np),$uid]);
                    $msg = "Employee updated with new password.";
                }
            } else {
                $msg = "Employee updated successfully.";
            }
        }
    }

    if ($action === 'toggle_status') {
        $uid = (int)$_POST['user_id'];
        $db->prepare("UPDATE users SET is_active = 1 - is_active WHERE user_id=?")->execute([$uid]);
        $msg = "Employee status updated.";
    }

    if ($action === 'delete_employee') {
        $uid = (int)$_POST['user_id'];
        $db->prepare("UPDATE users SET is_active=0 WHERE user_id=?")->execute([$uid]);
        $msg = "Employee deactivated.";
    }
}

$staff = $db->query("SELECT u.*, r.role_name, c.first_name as cb_first, c.last_name as cb_last FROM users u JOIN roles r ON u.role_id=r.role_id LEFT JOIN users c ON u.created_by=c.user_id WHERE r.role_name='staff' ORDER BY u.created_at DESC")->fetchAll();

include __DIR__ . '/../includes/sidebar.php';
?>

<div class="topbar">
  <div class="topbar-title"><h1>Employees</h1><p>Create and manage staff accounts</p></div>
  <div class="topbar-actions">
    <button class="btn-gs btn-gs-primary" data-bs-toggle="modal" data-bs-target="#addEmpModal">
      <i class="bi bi-person-plus"></i> Add Employee
    </button>
  </div>
</div>

<div class="page-content">

<?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show rounded-3 mb-3"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger alert-dismissible fade show rounded-3 mb-3"><?= $err ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="panel">
  <div class="panel-header">
    <div><div class="panel-title">Staff Members</div><div class="panel-subtitle"><?= count($staff) ?> employees registered</div></div>
  </div>
  <div style="overflow-x:auto">
  <table class="gs-table">
    <thead>
      <tr><th>Employee</th><th>Username</th><th>Phone</th><th>Status</th><th>Created By</th><th>Date Added</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php if (empty($staff)): ?>
      <tr><td colspan="7" class="text-center py-5" style="color:var(--text-light)">
        <i class="bi bi-people" style="font-size:2rem;display:block;margin-bottom:8px"></i>
        No employees yet. Click "Add Employee" to get started.
      </td></tr>
      <?php else: ?>
      <?php foreach ($staff as $e): ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:12px">
            <div style="width:40px;height:40px;border-radius:50%;background:var(--green-pale);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.9rem;color:var(--green-main)">
              <?= strtoupper(substr($e['first_name'],0,1).substr($e['last_name'],0,1)) ?>
            </div>
            <div>
              <div style="font-weight:600"><?= htmlspecialchars($e['first_name'].' '.$e['last_name']) ?></div>
              <div style="font-size:0.75rem;color:var(--text-light)"><?= htmlspecialchars($e['email']) ?></div>
            </div>
          </div>
        </td>
        <td><code style="background:var(--green-ghost);padding:3px 8px;border-radius:6px;font-size:0.82rem"><?= htmlspecialchars($e['username']) ?></code></td>
        <td style="font-size:0.85rem"><?= htmlspecialchars($e['phone'] ?? '—') ?></td>
        <td><span class="badge-gs <?= $e['is_active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $e['is_active'] ? 'Active' : 'Inactive' ?></span></td>
        <td style="font-size:0.82rem"><?= $e['cb_first'] ? htmlspecialchars($e['cb_first'].' '.$e['cb_last']) : 'System' ?></td>
        <td style="font-size:0.78rem;color:var(--text-light)"><?= date('M d, Y', strtotime($e['created_at'])) ?></td>
        <td>
          <div class="d-flex gap-1">
            <button class="btn-gs btn-gs-outline btn-gs-sm" onclick='openEditEmp(<?= json_encode($e) ?>)' title="Edit">
              <i class="bi bi-pencil"></i>
            </button>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="user_id" value="<?= $e['user_id'] ?>">
              <button type="submit" class="btn-gs btn-gs-outline btn-gs-sm" title="Toggle status">
                <i class="bi bi-<?= $e['is_active'] ? 'toggle-on' : 'toggle-off' ?>"></i>
              </button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

</div>
</div>

<!-- Add Employee Modal -->
<div class="modal fade" id="addEmpModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Create New Employee</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" class="form-gs">
        <input type="hidden" name="action" value="create_employee">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-6"><label>First Name *</label><input type="text" name="first_name" class="form-control" required></div>
            <div class="col-6"><label>Last Name *</label><input type="text" name="last_name" class="form-control" required></div>
            <div class="col-6"><label>Email *</label><input type="email" name="email" class="form-control" required></div>
            <div class="col-6"><label>Phone</label><input type="text" name="phone" class="form-control" placeholder="e.g. 09XX-XXX-XXXX"></div>
            <div class="col-12"><label>Address</label><textarea name="address" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><hr style="border-color:var(--border)"><p style="font-size:0.8rem;font-weight:600;color:var(--text-mid);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:12px"><i class="bi bi-lock me-1"></i>Login Credentials</p></div>
            <div class="col-6">
              <label>Username *</label>
              <input type="text" name="username" class="form-control" placeholder="Unique login name" required>
              <div style="font-size:0.75rem;color:var(--text-light);margin-top:4px">Employee uses this to log in</div>
            </div>
            <div class="col-6">
              <label>Password *</label>
              <input type="password" name="password" class="form-control" placeholder="Min. 6 characters" required minlength="6">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-gs btn-gs-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-gs btn-gs-primary"><i class="bi bi-person-check"></i> Create Employee</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Employee Modal -->
<div class="modal fade" id="editEmpModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Employee</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" class="form-gs">
        <input type="hidden" name="action" value="edit_employee">
        <input type="hidden" name="user_id" id="edit_emp_id">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-6"><label>First Name *</label><input type="text" name="first_name" id="ee_first" class="form-control" required></div>
            <div class="col-6"><label>Last Name *</label><input type="text" name="last_name" id="ee_last" class="form-control" required></div>
            <div class="col-6"><label>Email *</label><input type="email" name="email" id="ee_email" class="form-control" required></div>
            <div class="col-6"><label>Phone</label><input type="text" name="phone" id="ee_phone" class="form-control"></div>
            <div class="col-6">
              <label>Status</label>
              <select name="is_active" id="ee_active" class="form-select">
                <option value="1">Active</option>
                <option value="0">Inactive</option>
              </select>
            </div>
            <div class="col-12"><label>Address</label><textarea name="address" id="ee_addr" class="form-control" rows="2"></textarea></div>
            <div class="col-12">
              <hr style="border-color:var(--border)">
              <label>New Password <span style="font-weight:400;text-transform:none;letter-spacing:0">(leave blank to keep current)</span></label>
              <input type="password" name="new_password" class="form-control" placeholder="Enter new password to change">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-gs btn-gs-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-gs btn-gs-primary"><i class="bi bi-check-lg"></i> Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openEditEmp(e) {
  document.getElementById('edit_emp_id').value = e.user_id;
  document.getElementById('ee_first').value    = e.first_name;
  document.getElementById('ee_last').value     = e.last_name;
  document.getElementById('ee_email').value    = e.email;
  document.getElementById('ee_phone').value    = e.phone || '';
  document.getElementById('ee_addr').value     = e.address || '';
  document.getElementById('ee_active').value   = e.is_active;
  new bootstrap.Modal(document.getElementById('editEmpModal')).show();
}
</script>
</body>
</html>
