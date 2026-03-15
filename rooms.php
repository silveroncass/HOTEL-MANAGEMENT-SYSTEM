<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin('admin');
$active_page = 'rooms';

$db = getDB();
$msg = '';
$err = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_room') {
        $type_id     = (int)$_POST['type_id'];
        $room_number = trim($_POST['room_number']);
        $floor       = (int)$_POST['floor'];
        $status      = $_POST['status'];
        $notes       = trim($_POST['notes']);
        // Check unique room number
        $chk = $db->prepare("SELECT COUNT(*) FROM rooms WHERE room_number=?");
        $chk->execute([$room_number]);
        if ($chk->fetchColumn() > 0) {
            $err = "Room number '$room_number' already exists.";
        } else {
            $st = $db->prepare("INSERT INTO rooms (type_id,room_number,floor,status,notes) VALUES (?,?,?,?,?)");
            $st->execute([$type_id,$room_number,$floor,$status,$notes]);
            $msg = "Room $room_number added successfully.";
        }
    }

    if ($action === 'edit_room') {
        $room_id     = (int)$_POST['room_id'];
        $type_id     = (int)$_POST['type_id'];
        $room_number = trim($_POST['room_number']);
        $floor       = (int)$_POST['floor'];
        $status      = $_POST['status'];
        $notes       = trim($_POST['notes']);
        $chk = $db->prepare("SELECT COUNT(*) FROM rooms WHERE room_number=? AND room_id!=?");
        $chk->execute([$room_number,$room_id]);
        if ($chk->fetchColumn() > 0) {
            $err = "Room number '$room_number' already exists.";
        } else {
            $st = $db->prepare("UPDATE rooms SET type_id=?,room_number=?,floor=?,status=?,notes=? WHERE room_id=?");
            $st->execute([$type_id,$room_number,$floor,$status,$notes,$room_id]);
            $msg = "Room updated successfully.";
        }
    }

    if ($action === 'delete_room') {
        $room_id = (int)$_POST['room_id'];
        // Block if room has active bookings
        $chk = $db->prepare("SELECT COUNT(*) FROM bookings WHERE room_id=? AND status IN ('pending','confirmed','checked_in')");
        $chk->execute([$room_id]);
        if ($chk->fetchColumn() > 0) {
            $err = "Cannot delete room with active bookings.";
        } else {
            // Delete payments linked to this room's bookings first, then bookings, then room
            $db->prepare("DELETE p FROM payments p JOIN bookings b ON p.booking_id = b.booking_id WHERE b.room_id=?")->execute([$room_id]);
            $db->prepare("DELETE FROM bookings WHERE room_id=?")->execute([$room_id]);
            $db->prepare("DELETE FROM rooms WHERE room_id=?")->execute([$room_id]);
            $msg = "Room deleted.";
        }
    }

    if ($action === 'add_type') {
        $type_name    = trim($_POST['type_name']);
        $base_price   = (float)$_POST['base_price'];
        $max_occ      = (int)$_POST['max_occupancy'];
        $description  = trim($_POST['description']);
        $st = $db->prepare("INSERT INTO room_types (type_name,base_price,max_occupancy,description) VALUES (?,?,?,?)");
        $st->execute([$type_name,$base_price,$max_occ,$description]);
        $msg = "Room type added.";
    }
}

$rooms = $db->query("SELECT r.*, rt.type_name, rt.base_price FROM rooms r JOIN room_types rt ON r.type_id=rt.type_id ORDER BY r.room_number")->fetchAll();
$types = $db->query("SELECT * FROM room_types ORDER BY type_name")->fetchAll();

include __DIR__ . '/../includes/sidebar.php';
?>

<div class="topbar">
  <div class="topbar-title"><h1>Rooms</h1><p>Manage hotel rooms and availability</p></div>
  <div class="topbar-actions">
    <button class="btn-gs btn-gs-primary" data-bs-toggle="modal" data-bs-target="#addRoomModal">
      <i class="bi bi-plus-lg"></i> Add Room
    </button>
  </div>
</div>

<div class="page-content">

<?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show rounded-3 mb-3" role="alert"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger alert-dismissible fade show rounded-3 mb-3" role="alert"><?= $err ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>



<!-- Rooms Table -->
<div class="panel">
  <div class="panel-header">
    <div><div class="panel-title">All Rooms</div><div class="panel-subtitle"><?= count($rooms) ?> rooms total</div></div>
  </div>
  <div style="overflow-x:auto">
  <table class="gs-table" id="roomsTable">
    <thead>
      <tr>
        <th>Room No.</th><th>Type</th><th>Floor</th><th>Price/Night</th><th>Status</th><th>Notes</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rooms as $r): ?>
      <tr data-status="<?= $r['status'] ?>" data-type="<?= $r['type_name'] ?>">
        <td><strong><?= $r['room_number'] ?></strong></td>
        <td><?= $r['type_name'] ?></td>
        <td>Floor <?= $r['floor'] ?></td>
        <td>₱<?= number_format($r['base_price'],2) ?></td>
        <td><span class="badge-gs badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
        <td style="font-size:0.8rem;color:var(--text-light)"><?= htmlspecialchars($r['notes'] ?? '—') ?></td>
        <td>
          <div class="d-flex gap-1">
            <button class="btn-gs btn-gs-outline btn-gs-sm" onclick='openEdit(<?= json_encode($r) ?>)'>
              <i class="bi bi-pencil"></i>
            </button>
            <button class="btn-gs btn-gs-danger btn-gs-sm" onclick="confirmDelete(<?= $r['room_id'] ?>, '<?= $r['room_number'] ?>')">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

</div><!-- /page-content -->
</div><!-- /main-wrap -->

<!-- Add Room Modal -->
<div class="modal fade" id="addRoomModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add New Room</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" class="form-gs">
        <input type="hidden" name="action" value="add_room">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-6">
              <label>Room Number *</label>
              <input type="text" name="room_number" class="form-control" placeholder="e.g. 103" required>
            </div>
            <div class="col-6">
              <label>Floor *</label>
              <input type="number" name="floor" class="form-control" min="1" max="20" value="1" required>
            </div>
            <div class="col-12">
              <label>Room Type *</label>
              <select name="type_id" class="form-select" required>
                <?php foreach ($types as $t): ?><option value="<?= $t['type_id'] ?>"><?= $t['type_name'] ?> — ₱<?= number_format($t['base_price'],2) ?>/night</option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label>Status</label>
              <select name="status" class="form-select">
                <option value="available">Available</option>
                <option value="maintenance">Maintenance</option>
              </select>
            </div>
            <div class="col-12">
              <label>Notes</label>
              <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-gs btn-gs-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-gs btn-gs-primary"><i class="bi bi-plus-lg"></i> Add Room</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Room Modal -->
<div class="modal fade" id="editRoomModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Room</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" class="form-gs">
        <input type="hidden" name="action" value="edit_room">
        <input type="hidden" name="room_id" id="edit_room_id">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-6">
              <label>Room Number *</label>
              <input type="text" name="room_number" id="edit_room_number" class="form-control" required>
            </div>
            <div class="col-6">
              <label>Floor *</label>
              <input type="number" name="floor" id="edit_floor" class="form-control" min="1" required>
            </div>
            <div class="col-12">
              <label>Room Type *</label>
              <select name="type_id" id="edit_type_id" class="form-select">
                <?php foreach ($types as $t): ?><option value="<?= $t['type_id'] ?>"><?= $t['type_name'] ?> — ₱<?= number_format($t['base_price'],2) ?>/night</option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label>Status</label>
              <select name="status" id="edit_status" class="form-select">
                <option value="available">Available</option>
                <option value="occupied">Occupied</option>
                <option value="maintenance">Maintenance</option>
                <option value="reserved">Reserved</option>
              </select>
            </div>
            <div class="col-12">
              <label>Notes</label>
              <textarea name="notes" id="edit_notes" class="form-control" rows="2"></textarea>
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

<!-- Delete Confirm -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header" style="background:#dc2626">
        <h5 class="modal-title">Delete Room</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center py-4">
        <i class="bi bi-exclamation-triangle" style="font-size:2.5rem;color:#dc2626"></i>
        <p class="mt-3 mb-1">Are you sure you want to delete <strong id="del-room-no"></strong>?</p>
        <p style="font-size:0.8rem;color:var(--text-light)">This action cannot be undone.</p>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="delete_room">
        <input type="hidden" name="room_id" id="del-room-id">
        <div class="modal-footer justify-content-center">
          <button type="button" class="btn-gs btn-gs-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-gs btn-gs-danger"><i class="bi bi-trash"></i> Delete</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openEdit(r) {
  document.getElementById('edit_room_id').value     = r.room_id;
  document.getElementById('edit_room_number').value = r.room_number;
  document.getElementById('edit_floor').value       = r.floor;
  document.getElementById('edit_type_id').value     = r.type_id;
  document.getElementById('edit_status').value      = r.status;
  document.getElementById('edit_notes').value       = r.notes || '';
  new bootstrap.Modal(document.getElementById('editRoomModal')).show();
}
function confirmDelete(id, num) {
  document.getElementById('del-room-id').value = id;
  document.getElementById('del-room-no').textContent = 'Room ' + num;
  new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

</script>
</body>
</html>
