<?php
include("../includes/header.php");
require_once "../includes/guards.php";
if (role() !== 'dentist' && role() !== 'dentist_pending') {
  header('Location: ' . $base_url . '/public/login.php'); exit;
}
require_once "../models/BlockedDates.php";

$dentistId = $_SESSION['user_id'];

// Create dentist-specific blocked dates table if needed
global $pdo;
$pdo->exec("CREATE TABLE IF NOT EXISTS DentistBlockedDates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dentistId INT NOT NULL,
    date DATE NOT NULL,
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY dentist_date (dentistId, date),
    FOREIGN KEY (dentistId) REFERENCES Users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Handle add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_block'])) {
    $date = $_POST['date'] ?? null;
    $reason = trim($_POST['reason'] ?? '');
    if (!$date) {
        $error = 'Please choose a date.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO DentistBlockedDates (dentistId, date, reason) VALUES (?, ?, ?)");
        if ($stmt->execute([$dentistId, $date, $reason])) {
            $success = 'Blocked date added.';
        } else {
            $error = 'Failed to add blocked date (maybe already exists).';
        }
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_block'])) {
    $id = intval($_POST['block_id']);
    if ($id) {
        $stmt = $pdo->prepare("DELETE FROM DentistBlockedDates WHERE id = ? AND dentistId = ?");
        if ($stmt->execute([$id, $dentistId])) {
            $success = 'Blocked date removed.';
        } else {
            $error = 'Failed to remove blocked date.';
        }
    }
}

// Get dentist's blocked dates
$stmt = $pdo->prepare("SELECT * FROM DentistBlockedDates WHERE dentistId = ? ORDER BY date DESC");
$stmt->execute([$dentistId]);
$blocked = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="dashboard-header">
  <div class="welcome-section">
    <h1>My Blocked Dates</h1>
    <p>Manage your vacation and unavailable days</p>
  </div>
</div>

<div class="container">
  <?php if (isset($error)): ?>
    <div class="error"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <?php if (isset($success)): ?>
    <div class="success"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <div class="booking-section">
    <h2>Block Your Schedule</h2>
    <p style="color:#6b7280;margin-bottom:12px;">Mark dates when you won't be available (vacation, training, personal time)</p>
    <form method="POST">
      <div class="form-group">
        <label for="date">Date</label>
        <input type="date" name="date" id="date" required min="<?php echo date('Y-m-d'); ?>">
      </div>
      <div class="form-group">
        <label for="reason">Reason (optional)</label>
        <input type="text" name="reason" id="reason" placeholder="e.g. Vacation, Conference, Training">
      </div>
      <button type="submit" name="add_block" class="btn-primary">Block Date</button>
    </form>
  </div>

  <h2>Your Blocked Dates</h2>
  <?php if (empty($blocked)): ?>
    <p style="color:#6b7280;">No blocked dates set. All your available dates are open for booking.</p>
  <?php else: ?>
    <table class="appointments-table" style="max-width:800px;">
      <thead>
        <tr><th>Date</th><th>Reason</th><th>Created</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php foreach ($blocked as $b): ?>
          <tr>
            <td><strong><?php echo htmlspecialchars($b['date']); ?></strong></td>
            <td><?php echo htmlspecialchars($b['reason'] ?? '—'); ?></td>
            <td><?php echo date('M d, Y', strtotime($b['created_at'])); ?></td>
            <td>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="block_id" value="<?php echo $b['id']; ?>">
                <button type="submit" name="delete_block" class="btn-cancel" onclick="return confirm('Remove this blocked date?')">Remove</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

</div>

<style>
.booking-section { background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e5e7eb; max-width:800px; }
.form-group { margin-bottom:12px; }
.form-group label { display:block; margin-bottom:6px; font-weight:600; }
.form-group input { padding:8px; border:1px solid #d1d5db; border-radius:6px; width:100%; max-width:400px; }
.btn-primary { background:#3b82f6; color:#fff; padding:8px 14px; border-radius:6px; border:none; cursor:pointer; }
.btn-cancel { background:#dc2626; color:#fff; padding:6px 10px; border-radius:6px; border:none; cursor:pointer; }
.error { background:#fee2e2; color:#dc2626; padding:12px; border-radius:8px; margin-bottom:20px; border:1px solid #fecaca; }
.success { background:#d1fae5; color:#065f46; padding:12px; border-radius:8px; margin-bottom:20px; border:1px solid #a7f3d0; }
.appointments-table { width:100%; border-collapse:collapse; border-radius:8px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.1); }
.appointments-table th { background:#f3f4f6; padding:12px; font-weight:600; color:#374151; border-bottom:1px solid #e5e7eb; }
.appointments-table td { padding:12px; border-bottom:1px solid #e5e7eb; }
</style>

<?php include("../includes/footer.php"); ?>
