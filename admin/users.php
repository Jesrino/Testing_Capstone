<?php
include("../includes/header.php");
require_once "../includes/guards.php";
requireRole('admin');
require_once "../models/users.php";

// Get message from URL if redirected from approve
$message = $_GET['message'] ?? '';
$messageType = $_GET['type'] ?? '';

// Get all users
$allUsers = getAllUsers();

// Get pending dentists
$pendingDentists = getPendingDentists();

// Get approved dentists
$approvedDentists = getApprovedDentists();

// Get user statistics
global $pdo;
$stmt = $pdo->query("SELECT role, COUNT(*) as count FROM Users GROUP BY role");
$userStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
$userStatsAssoc = [];
foreach ($userStats as $stat) {
  $userStatsAssoc[$stat['role']] = $stat['count'];
}

// Get recent user activity
$recentActivity = [];

// Recent user registrations
$stmt = $pdo->prepare("SELECT name, email, role, createdAt FROM Users ORDER BY createdAt DESC LIMIT 5");
$stmt->execute();
$recentUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($recentUsers as $user) {
  $recentActivity[] = [
    'icon' => '👤',
    'title' => 'New user registration',
    'description' => htmlspecialchars($user['name']) . ' registered as ' . str_replace('_', ' ', $user['role']),
    'time' => $user['createdAt']
  ];
}

// Recent appointments
$stmt = $pdo->prepare("
  SELECT a.date, a.time, u.name as client_name, d.name as dentist_name, a.status, a.createdAt
  FROM Appointments a
  LEFT JOIN Users u ON a.clientId = u.id
  LEFT JOIN Users d ON a.dentistId = d.id
  ORDER BY a.createdAt DESC LIMIT 5
");
$stmt->execute();
$recentAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($recentAppointments as $appt) {
  $client = $appt['client_name'] ?: 'Walk-in';
  $recentActivity[] = [
    'icon' => '📅',
    'title' => 'Appointment ' . $appt['status'],
    'description' => $client . ' - ' . date('M j, Y g:i A', strtotime($appt['date'] . ' ' . $appt['time'])),
    'time' => $appt['createdAt']
  ];
}

// Sort by time descending and limit to 10
usort($recentActivity, function($a, $b) {
  return strtotime($b['time']) - strtotime($a['time']);
});
$recentActivity = array_slice($recentActivity, 0, 10);

// Function to calculate time ago
function timeAgo($datetime) {
  $now = new DateTime();
  $ago = new DateTime($datetime);
  $diff = $now->diff($ago);

  if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
  if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
  if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
  if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
  if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
  return 'Just now';
}
?>

<!-- Dashboard Header -->
<div class="dashboard-header">
  <div class="welcome-section">
    <h1>Manage Users</h1>
    <p>Comprehensive user management and account administration</p>
  </div>
  <div class="user-avatar">
    <img src="<?php echo $base_url; ?>/assets/images/admin_logo.svg" alt="Admin Icon" style="width: 60px; height: 60px;">
  </div>
</div>

<div class="container">

<!-- User Statistics -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/people_icon.svg" alt="Total Users">
    </div>
    <div class="stat-content">
      <h3><?php echo array_sum($userStatsAssoc); ?></h3>
      <p>Total Users</p>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/patients_icon.svg" alt="Clients">
    </div>
    <div class="stat-content">
      <h3><?php echo $userStatsAssoc['client'] ?? 0; ?></h3>
      <p>Clients</p>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/doctor_icon.svg" alt="Dentists">
    </div>
    <div class="stat-content">
      <h3><?php echo ($userStatsAssoc['dentist'] ?? 0) + ($userStatsAssoc['dentist_pending'] ?? 0); ?></h3>
      <p>Dentists</p>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/admin_logo.svg" alt="Admins">
    </div>
    <div class="stat-content">
      <h3><?php echo $userStatsAssoc['admin'] ?? 0; ?></h3>
      <p>Admins</p>
    </div>
  </div>
</div>

<!-- Message Display -->
<?php if ($message): ?>
  <div class="success-message" style="margin: 20px 0;">
    <?php echo htmlspecialchars($message); ?>
  </div>
<?php endif; ?>

<!-- User Search and Filters -->
<div class="activity-section">
  <h2><i class="fas fa-search"></i> Search & Filter Users</h2>
  <div class="filters-form">
    <div class="filter-row">
      <div class="filter-group">
        <label for="search_name">Search by Name:</label>
        <input type="text" id="search_name" placeholder="Enter user name..." style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
      </div>
      <div class="filter-group">
        <label for="search_email">Search by Email:</label>
        <input type="email" id="search_email" placeholder="Enter email address..." style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
      </div>
      <div class="filter-group">
        <label for="filter_role">Filter by Role:</label>
        <select id="filter_role" style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
          <option value="">All Roles</option>
          <option value="admin">Admin</option>
          <option value="dentist">Dentist</option>
          <option value="dentist_pending">Pending Dentist</option>
          <option value="client">Client</option>
        </select>
      </div>
      <div class="filter-group">
        <label>&nbsp;</label>
        <div style="display: flex; gap: 10px;">
          <button onclick="applyFilters()" class="btn-primary" style="flex: 1;">Apply Filters</button>
          <button onclick="clearFilters()" class="btn-secondary" style="flex: 1;">Clear</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Recent User Activity -->
<div class="activity-section">
  <h2><i class="fas fa-clock"></i> Recent User Activity</h2>
  <div class="activity-list">
    <?php if (empty($recentActivity)): ?>
      <div class="activity-item">
        <div class="activity-icon">📝</div>
        <div class="activity-content">
          <h4>No recent activity</h4>
          <p>User activity will appear here as users register and book appointments.</p>
          <small>System ready</small>
        </div>
      </div>
    <?php else: ?>
      <?php foreach ($recentActivity as $activity): ?>
        <div class="activity-item">
          <div class="activity-icon"><?php echo $activity['icon']; ?></div>
          <div class="activity-content">
            <h4><?php echo htmlspecialchars($activity['title']); ?></h4>
            <p><?php echo htmlspecialchars($activity['description']); ?></p>
            <small><?php echo date('M j, Y g:i A', strtotime($activity['time'])); ?> (<?php echo timeAgo($activity['time']); ?>)</small>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Create Dentist Account -->
<div class="activity-section" style="margin-bottom: 40px;">
  <h2>Create Dentist Account</h2>
  <form method="POST" action="create_dentist.php" style="max-width: 600px; margin-top: 20px;">
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
      <div class="form-group">
        <label for="dentist_name" style="display: block; margin-bottom: 5px; color: #0a0a0a; font-weight: 500;">Full Name</label>
        <input type="text" id="dentist_name" name="name" placeholder="Enter dentist's full name" required style="width: 100%; padding: 12px 15px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem;">
      </div>

      <div class="form-group">
        <label for="dentist_email" style="display: block; margin-bottom: 5px; color: #0a0a0a; font-weight: 500;">Email Address</label>
        <input type="email" id="dentist_email" name="email" placeholder="Enter dentist's email" required style="width: 100%; padding: 12px 15px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem;">
      </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
      <div class="form-group">
        <label for="dentist_password" style="display: block; margin-bottom: 5px; color: #0a0a0a; font-weight: 500;">Password</label>
        <input type="password" id="dentist_password" name="password" placeholder="Create password" required minlength="6" style="width: 100%; padding: 12px 15px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem;">
      </div>

      <div class="form-group">
        <label for="dentist_confirm_password" style="display: block; margin-bottom: 5px; color: #0a0a0a; font-weight: 500;">Confirm Password</label>
        <input type="password" id="dentist_confirm_password" name="confirm_password" placeholder="Confirm password" required style="width: 100%; padding: 12px 15px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem;">
      </div>
    </div>

    <button type="submit" style="background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: white; padding: 12px 24px; border: none; border-radius: 6px; font-size: 1rem; font-weight: 600; cursor: pointer; margin-top: 20px;">Create Dentist Account</button>
  </form>
</div>

<!-- Pending Dentist Approvals -->
<?php if (!empty($pendingDentists)): ?>
<div class="pending-section">
  <h2>Pending Dentist Approvals</h2>
  <div class="pending-list">
    <?php foreach ($pendingDentists as $dentist): ?>
      <div class="pending-card">
        <div class="pending-info">
          <h4><?php echo htmlspecialchars($dentist['name']); ?></h4>
          <p><?php echo htmlspecialchars($dentist['email']); ?></p>
          <small>Applied: <?php echo date('M d, Y', strtotime($dentist['createdAt'])); ?></small>
        </div>
        <div class="pending-actions">
          <form method="POST" action="approve.php" style="display: inline;">
            <input type="hidden" name="userId" value="<?php echo $dentist['id']; ?>">
            <input type="hidden" name="action" value="approve">
            <button type="submit" class="btn-approve">Approve</button>
          </form>
          <form method="POST" action="approve.php" style="display: inline;">
            <input type="hidden" name="userId" value="<?php echo $dentist['id']; ?>">
            <input type="hidden" name="action" value="reject">
            <button type="submit" class="btn-reject">Reject</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- All Users Table -->
<div class="activity-section">
  <h2>All Users</h2>
  <div style="overflow-x: auto;">
    <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
      <thead>
        <tr style="background: #f8fafc; border-bottom: 2px solid #e5e7eb;">
          <th style="padding: 12px; text-align: left; font-weight: 600; color: #0a0a0a;">Name</th>
          <th style="padding: 12px; text-align: left; font-weight: 600; color: #0a0a0a;">Email</th>
          <th style="padding: 12px; text-align: left; font-weight: 600; color: #0a0a0a;">Role</th>
          <th style="padding: 12px; text-align: left; font-weight: 600; color: #0a0a0a;">Joined</th>
          <th style="padding: 12px; text-align: left; font-weight: 600; color: #0a0a0a;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($allUsers as $user): ?>
          <tr style="border-bottom: 1px solid #e5e7eb;">
            <td style="padding: 12px;"><?php echo htmlspecialchars($user['name']); ?></td>
            <td style="padding: 12px;"><?php echo htmlspecialchars($user['email']); ?></td>
            <td style="padding: 12px;">
              <span style="padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase;
                <?php
                if ($user['role'] === 'admin') echo 'background: #dbeafe; color: #1e40af;';
                elseif ($user['role'] === 'dentist') echo 'background: #d1fae5; color: #065f46;';
                elseif ($user['role'] === 'dentist_pending') echo 'background: #fef3c7; color: #92400e;';
                else echo 'background: #f3f4f6; color: #374151;';
                ?>">
                <?php echo htmlspecialchars($user['role']); ?>
              </span>
            </td>
            <td style="padding: 12px;"><?php echo date('M d, Y', strtotime($user['createdAt'])); ?></td>
            <td style="padding: 12px;">
              <?php if ($user['role'] === 'dentist_pending'): ?>
                <form method="POST" action="approve.php" style="display: inline;">
                  <input type="hidden" name="userId" value="<?php echo $user['id']; ?>">
                  <input type="hidden" name="action" value="approve">
                  <button type="submit" style="background: #10b981; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">Approve</button>
                </form>
                <form method="POST" action="approve.php" style="display: inline;">
                  <input type="hidden" name="userId" value="<?php echo $user['id']; ?>">
                  <input type="hidden" name="action" value="reject">
                  <button type="submit" style="background: #ef4444; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">Reject</button>
                </form>
              <?php else: ?>
                <span style="color: #666; font-size: 0.9rem;">No actions</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

</div>

<script>
function applyFilters() {
  const nameFilter = document.getElementById('search_name').value.toLowerCase();
  const emailFilter = document.getElementById('search_email').value.toLowerCase();
  const roleFilter = document.getElementById('filter_role').value;

  const tableRows = document.querySelectorAll('#users-table tbody tr');

  tableRows.forEach(row => {
    const name = row.cells[0].textContent.toLowerCase();
    const email = row.cells[1].textContent.toLowerCase();
    const role = row.cells[2].textContent.toLowerCase().replace(' ', '_');

    const nameMatch = name.includes(nameFilter);
    const emailMatch = email.includes(emailFilter);
    const roleMatch = !roleFilter || role.includes(roleFilter.toLowerCase());

    if (nameMatch && emailMatch && roleMatch) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
}

function clearFilters() {
  document.getElementById('search_name').value = '';
  document.getElementById('search_email').value = '';
  document.getElementById('filter_role').value = '';

  const tableRows = document.querySelectorAll('#users-table tbody tr');
  tableRows.forEach(row => {
    row.style.display = '';
  });
}

// Add table ID for filtering
document.addEventListener('DOMContentLoaded', function() {
  const table = document.querySelector('.activity-section table');
  if (table) {
    table.id = 'users-table';
  }
});
</script>

<style>
.dashboard-header {
  background: linear-gradient(135deg, #556B2F 0%, #0284c7 100%);
  color: white;
  padding: 3rem 1.25rem;
  border-radius: 0 0 1rem 1rem;
  box-shadow: 0 6px 20px rgba(0,0,0,0.08);
  margin-bottom: 24px;
}

.welcome-section h1 {
  margin: 0 0 0.5rem 0;
  font-size: 2.5rem;
  font-weight: 700;
}

.welcome-section p {
  margin: 0;
  opacity: 0.95;
  font-size: 1.1rem;
}

.success-message {
  background: #d1fae5;
  color: #065f46;
  padding: 1rem;
  border-radius: 8px;
  margin-bottom: 2rem;
  border: 1px solid #a7f3d0;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 30px;
}

.stat-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  display: flex;
  align-items: center;
  gap: 15px;
  border: 1px solid #e5e7eb;
}

.stat-icon {
  width: 50px;
  height: 50px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f3f4f6;
  border-radius: 10px;
}

.stat-icon img {
  width: 30px;
  height: 30px;
}

.stat-content h3 {
  margin: 0 0 5px 0;
  font-size: 2rem;
  color: #0f172a;
  font-weight: 700;
}

.stat-content p {
  margin: 0;
  color: #6b7280;
  font-size: 0.9rem;
}

.activity-section {
  background: white;
  padding: 24px;
  border-radius: 12px;
  margin-bottom: 24px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  border: 1px solid #e5e7eb;
}

.activity-section h2 {
  margin: 0 0 20px 0;
  font-size: 1.5rem;
  color: #0f172a;
  border-bottom: 2px solid #eef2f7;
  padding-bottom: 10px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.activity-section h2 i {
  color: #556B2F;
}

.filters-form {
  margin-top: 15px;
}

.filter-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  align-items: end;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.filter-group label {
  font-weight: 500;
  color: #374151;
}

.filter-group input,
.filter-group select {
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  background: white;
}

.btn-primary, .btn-secondary {
  padding: 12px 24px;
  border: none;
  border-radius: 8px;
  font-size: 0.95rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  text-decoration: none;
  display: inline-block;
}

.btn-primary {
  background: linear-gradient(135deg, #556B2F 0%, #0284c7 100%);
  color: white;
}

.btn-primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(85, 107, 47, 0.3);
}

.btn-secondary {
  background: #f3f4f6;
  color: #374151;
  border: 1px solid #d1d5db;
}

.btn-secondary:hover {
  background: #e5e7eb;
  transform: translateY(-1px);
}

.activity-list {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.activity-item {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 15px;
  background: #f8fafc;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.activity-icon {
  font-size: 1.5rem;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.activity-content h4 {
  margin: 0 0 5px 0;
  font-size: 1rem;
  color: #374151;
  font-weight: 600;
}

.activity-content p {
  margin: 0 0 3px 0;
  color: #6b7280;
  font-size: 0.9rem;
}

.activity-content small {
  color: #9ca3af;
  font-size: 0.8rem;
}

.pending-section {
  background: white;
  padding: 24px;
  border-radius: 12px;
  margin-bottom: 24px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  border: 1px solid #e5e7eb;
}

.pending-section h2 {
  margin: 0 0 20px 0;
  font-size: 1.5rem;
  color: #0f172a;
  border-bottom: 2px solid #eef2f7;
  padding-bottom: 10px;
}

.pending-list {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.pending-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px;
  background: #fef3c7;
  border-radius: 8px;
  border: 1px solid #f59e0b;
}

.pending-info h4 {
  margin: 0 0 5px 0;
  color: #92400e;
  font-weight: 600;
}

.pending-info p {
  margin: 0 0 3px 0;
  color: #92400e;
}

.pending-info small {
  color: #a16207;
  font-size: 0.9rem;
}

.pending-actions {
  display: flex;
  gap: 10px;
}

.btn-approve, .btn-reject {
  padding: 8px 16px;
  border: none;
  border-radius: 6px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-approve {
  background: #10b981;
  color: white;
}

.btn-approve:hover {
  background: #059669;
  transform: translateY(-1px);
}

.btn-reject {
  background: #ef4444;
  color: white;
}

.btn-reject:hover {
  background: #dc2626;
  transform: translateY(-1px);
}

#users-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 20px;
}

#users-table th,
#users-table td {
  padding: 12px;
  text-align: left;
  border-bottom: 1px solid #e5e7eb;
}

#users-table th {
  background: #f8fafc;
  font-weight: 600;
  color: #0a0a0a;
}

#users-table tbody tr:hover {
  background: #f9fafb;
}

@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }

  .filter-row {
    grid-template-columns: 1fr;
  }

  .pending-card {
    flex-direction: column;
    gap: 15px;
    text-align: center;
  }

  .welcome-section h1 {
    font-size: 2rem;
  }
}
</style>

<?php include("../includes/footer.php"); ?>
