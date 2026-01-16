<?php
include("../includes/header.php");
require_once "../includes/guards.php";
requireRole('admin');
require_once "../models/users.php";

// Get system statistics
global $pdo;

// Total users by role
$stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$userStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
$userStatsAssoc = [];
foreach ($userStats as $stat) {
  $userStatsAssoc[$stat['role']] = $stat['count'];
}

// Pending dentists
$pendingDentists = getPendingDentists();

// Recent appointments
$stmt = $pdo->prepare("
  SELECT a.*, u.name as clientName, d.name as dentistName
  FROM Appointments a
  LEFT JOIN users u ON a.clientId = u.id
  LEFT JOIN users d ON a.dentistId = d.id
  ORDER BY a.createdAt DESC
  LIMIT 5
");
$stmt->execute();
$recentAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Today's appointments count
$stmt = $pdo->query("SELECT COUNT(*) as todayCount FROM Appointments WHERE date = CURDATE()");
$todayAppointments = $stmt->fetch(PDO::FETCH_ASSOC)['todayCount'];

// This week's appointments
$stmt = $pdo->query("SELECT COUNT(*) as weekCount FROM Appointments WHERE date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
$weekAppointments = $stmt->fetch(PDO::FETCH_ASSOC)['weekCount'];

// Get today's appointments with details
$stmt = $pdo->prepare("
  SELECT a.*, u.name as clientName, u.phone as clientPhone, d.name as dentistName
  FROM Appointments a
  LEFT JOIN Users u ON a.clientId = u.id
  LEFT JOIN Users d ON a.dentistId = d.id
  WHERE a.date = CURDATE()
  ORDER BY a.time ASC
");
$stmt->execute();
$todaysAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming appointments for this week
$stmt = $pdo->prepare("
  SELECT a.*, u.name as clientName, d.name as dentistName
  FROM Appointments a
  LEFT JOIN users u ON a.clientId = u.id
  LEFT JOIN users d ON a.dentistId = d.id
  WHERE a.date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
  AND a.status IN ('pending', 'confirmed')
  ORDER BY a.date ASC, a.time ASC
");
$stmt->execute();
$upcomingAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total patients (registered + walk-ins)
$stmt = $pdo->prepare("
  SELECT
    (SELECT COUNT(DISTINCT clientId) FROM Appointments WHERE clientId IS NOT NULL) +
    (SELECT COUNT(*) FROM Appointments WHERE clientId IS NULL) as totalPatients
");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$totalPatients = $result['totalPatients'];

// Get total walk-in customers
$stmt = $pdo->prepare("
  SELECT COUNT(*) as totalWalkins
  FROM Appointments
  WHERE clientId IS NULL
");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$totalWalkins = $result['totalWalkins'];

// Get appointment status distribution for chart
$stmt = $pdo->prepare("
  SELECT status, COUNT(*) as count
  FROM Appointments
  GROUP BY status
  ORDER BY count DESC
");
$stmt->execute();
$statusData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If no appointments exist, create sample data for demonstration
if (empty($statusData)) {
  $statusData = [
    ['status' => 'pending', 'count' => 5],
    ['status' => 'confirmed', 'count' => 3],
    ['status' => 'completed', 'count' => 8],
    ['status' => 'cancelled', 'count' => 2]
  ];
}
?>




<!-- Enhanced Dashboard Header -->
<div class="dashboard-header">
  <div class="header-gradient">
    <div class="header-content">
      <div class="welcome-section">
        <h1><img src="<?php echo $base_url; ?>/assets/images/admin_logo.svg" alt="Admin" class="header-icon"> Admin Dashboard</h1>
        <p>Comprehensive clinic management and oversight</p>
        <div class="header-stats">
          <div class="header-stat">
            <span class="stat-number"><?php echo array_sum($userStatsAssoc); ?></span>
            <span class="stat-label">Active Users</span>
          </div>
          <div class="header-stat">
            <span class="stat-number"><?php echo $todayAppointments; ?></span>
            <span class="stat-label">Today's Appointments</span>
          </div>
          <div class="header-stat">
            <span class="stat-number"><?php echo $totalPatients; ?></span>
            <span class="stat-label">Total Patients</span>
          </div>
        </div>
      </div>
      <div class="header-actions">
        <div class="current-time">
          <div class="time-display" id="current-time">--:--</div>
          <div class="date-display" id="current-date">Loading...</div>
        </div>
        <div class="quick-access">
          <a href="reports.php" class="quick-btn">
            <img src="<?php echo $base_url; ?>/assets/images/list_icon.svg" alt="Reports">
            View Reports
          </a>
          <a href="settings.php" class="quick-btn">
            <img src="<?php echo $base_url; ?>/assets/images/info_icon.svg" alt="Settings">
            Settings
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="container">

<!-- System Overview Stats -->
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
      <img src="<?php echo $base_url; ?>/assets/images/appointments_icon.svg" alt="Today's Appointments">
    </div>
    <div class="stat-content">
      <h3><?php echo $todayAppointments; ?></h3>
      <p>Today</p>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/patients_icon.svg" alt="Total Patients">
    </div>
    <div class="stat-content">
      <h3><?php echo $totalPatients; ?></h3>
      <p>Total Patients</p>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/add_icon.svg" alt="Walk-in Customers">
    </div>
    <div class="stat-content">
      <h3><?php echo $totalWalkins; ?></h3>
      <p>Walk-in Customers</p>
    </div>
  </div>
</div>

<!-- Quick Actions -->
<div class="quick-actions">
  <h2>Quick Actions</h2>
  <div class="actions-grid">
    <a href="<?php echo $base_url; ?>/admin/users.php" class="action-card">
      <div class="action-icon">
        <img src="<?php echo $base_url; ?>/assets/images/people_icon.svg" alt="Users">
      </div>
      <h3>Manage Users</h3>
      <p>Approve dentists and manage accounts</p>
    </a>

    <a href="<?php echo $base_url; ?>/admin/reports.php" class="action-card">
      <div class="action-icon">
        <img src="<?php echo $base_url; ?>/assets/images/list_icon.svg" alt="Reports">
      </div>
      <h3>Reports</h3>
      <p>View system reports and analytics</p>
    </a>

    <a href="<?php echo $base_url; ?>/admin/appointments.php" class="action-card">
      <div class="action-icon">
        <img src="<?php echo $base_url; ?>/assets/images/appointments_icon.svg" alt="Appointments">
      </div>
      <h3>All Appointments</h3>
      <p>View and manage all appointments</p>
    </a>

    <a href="<?php echo $base_url; ?>/admin/patients.php" class="action-card">
      <div class="action-icon">
        <img src="<?php echo $base_url; ?>/assets/images/patients_icon.svg" alt="Patients">
      </div>
      <h3>All Patients</h3>
      <p>View and manage patient information</p>
    </a>

    <a href="<?php echo $base_url; ?>/admin/settings.php" class="action-card">
      <div class="action-icon">
        <img src="<?php echo $base_url; ?>/assets/images/info_icon.svg" alt="Settings">
      </div>
      <h3>System Settings</h3>
      <p>Configure system preferences</p>
    </a>

    <a href="<?php echo $base_url; ?>/admin/add_walkin.php" class="action-card">
      <div class="action-icon">
        <img src="<?php echo $base_url; ?>/assets/images/add_icon.svg" alt="Walk-in">
      </div>
      <h3>Add Walk-in</h3>
      <p>Create appointment for walk-in patients</p>
    </a>
  </div>
</div>

<!-- Status Chart -->
<div class="services-chart-section">
  <h2>Appointment Status Distribution</h2>
  <?php if (!empty($statusData)): ?>
    <div class="chart-container">
      <canvas id="statusChart"></canvas>
    </div>
  <?php else: ?>
    <div class="no-chart-data">
      <div class="no-chart-icon">
        <img src="<?php echo $base_url; ?>/assets/images/appointments_icon.svg" alt="No Data">
      </div>
      <h3>No appointment data available</h3>
      <p>The chart will display once appointments are created in the system.</p>
    </div>
  <?php endif; ?>
</div>

<!-- Pending Approvals -->
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
          <form method="POST" action="<?php echo $base_url; ?>/admin/approve.php" style="display: inline;">
            <input type="hidden" name="userId" value="<?php echo $dentist['id']; ?>">
            <input type="hidden" name="action" value="approve">
            <button type="submit" class="btn-approve">Approve</button>
          </form>
          <form method="POST" action="<?php echo $base_url; ?>/admin/approve.php" style="display: inline;">
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
<!-- Today's Appointments -->
<?php if (!empty($todaysAppointments)): ?>
<div class="appointments-section">
  <h2>Today's Appointments</h2>
  <div class="appointments-list">
    <?php foreach ($todaysAppointments as $appt): ?>
      <div class="appointment-card">
        <div class="appointment-time">
          <div class="time"><?php echo date('H:i', strtotime($appt['time'])); ?></div>
        </div>
        <div class="appointment-details">
          <h4><?php echo htmlspecialchars($appt['clientName']); ?></h4>
          <p><?php echo htmlspecialchars($appt['clientPhone']); ?></p>
          <small>Dr. <?php echo htmlspecialchars($appt['dentistName']); ?></small>
        </div>
        <div class="appointment-status <?php echo $appt['status']; ?>">
          <?php echo ucfirst($appt['status']); ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php else: ?>
<div class="no-appointments">
  <div class="no-appointments-icon">
    <img src="<?php echo $base_url; ?>/assets/images/appointment_icon.svg" alt="No Appointments">
  </div>
  <h3>No appointments today</h3>
  <p>The clinic is free today.</p>
</div>
<?php endif; ?>

<!-- Upcoming Appointments This Week -->
<?php if (!empty($upcomingAppointments)): ?>
<div class="upcoming-section">
  <h2>Upcoming This Week</h2>
  <div class="upcoming-list">
    <?php
    $currentDate = '';
    foreach ($upcomingAppointments as $appt):
      $apptDate = date('l, M d', strtotime($appt['date']));
      if ($apptDate !== $currentDate):
        if ($currentDate !== '') echo '</div>';
        $currentDate = $apptDate;
    ?>
      <div class="date-group">
        <h4><?php echo $apptDate; ?></h4>
        <div class="date-appointments">
    <?php endif; ?>
          <div class="mini-appointment">
            <span class="time"><?php echo date('H:i', strtotime($appt['time'])); ?></span>
            <span class="patient"><?php echo htmlspecialchars($appt['clientName']); ?> (Dr. <?php echo htmlspecialchars($appt['dentistName']); ?>)</span>
            <span class="status <?php echo $appt['status']; ?>"><?php echo ucfirst($appt['status']); ?></span>
          </div>
    <?php endforeach; ?>
        </div>
      </div>
  </div>
  <div class="view-all">
    <a href="<?php echo $base_url; ?>/admin/appointments.php">View All Appointments →</a>
  </div>
</div>
<?php endif; ?>

<!-- Recent Activity -->
<div class="activity-section">
  <h2>Recent Appointments</h2>
  <div class="activity-list">
    <?php if (!empty($recentAppointments)): ?>
      <?php foreach ($recentAppointments as $appt): ?>
        <div class="activity-item">
          <div class="activity-icon">
            <img src="<?php echo $base_url; ?>/assets/images/appointment_icon.svg" alt="Appointment">
          </div>
          <div class="activity-content">
            <p><strong><?php echo htmlspecialchars($appt['clientName']); ?></strong> has an appointment with <strong>Dr. <?php echo htmlspecialchars($appt['dentistName']); ?></strong></p>
            <small><?php echo date('M d, Y \a\t H:i', strtotime($appt['createdAt'])); ?> • <?php echo ucfirst($appt['status']); ?></small>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="no-activity">No recent appointments found.</p>
    <?php endif; ?>
  </div>
</div>

</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Status data for chart
const statusData = <?php echo json_encode($statusData); ?>;

// Generate colors for each status
function generateColors(count) {
  const colors = [
    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
    '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#FF6384'
  ];
  return colors.slice(0, count);
}

// Create status pie chart
function createStatusChart() {
  const ctx = document.getElementById('statusChart').getContext('2d');
  const colors = generateColors(statusData.length);

  new Chart(ctx, {
    type: 'pie',
    data: {
      labels: statusData.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1)),
      datasets: [{
        data: statusData.map(item => item.count),
        backgroundColor: colors,
        borderColor: colors.map(color => color.replace('0.6', '1')),
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            padding: 20,
            usePointStyle: true
          }
        }
      }
    }
  });
}

// Initialize chart when page loads
document.addEventListener('DOMContentLoaded', function() {
  createStatusChart();
  updateTime();
  setInterval(updateTime, 1000); // Update every second
});

// Function to update time display
function updateTime() {
  const now = new Date();
  const timeString = now.toLocaleTimeString('en-US', {
    hour12: false,
    hour: '2-digit',
    minute: '2-digit'
  });
  const dateString = now.toLocaleDateString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });

  document.getElementById('current-time').textContent = timeString;
  document.getElementById('current-date').textContent = dateString;
}
</script>

<style>
/* Enhanced Dashboard Header */
.dashboard-header {
  margin-bottom: 30px;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.header-gradient {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  position: relative;
}

.header-gradient::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="10" cy="50" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="30" r="0.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
  opacity: 0.3;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 40px;
  position: relative;
  z-index: 1;
}

.welcome-section h1 {
  margin: 0 0 8px 0;
  font-size: 2.8rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 15px;
  color: white;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.header-icon {
  width: 48px;
  height: 48px;
  filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
}

.welcome-section p {
  margin: 0;
  font-size: 1.2rem;
  opacity: 0.95;
  font-weight: 400;
  color: white;
}

.header-stats {
  display: flex;
  gap: 25px;
  margin-top: 15px;
}

.header-stat {
  text-align: center;
  padding: 15px 20px;
  background: rgba(255, 255, 255, 0.15);
  border-radius: 12px;
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.stat-number {
  display: block;
  font-size: 2.2rem;
  font-weight: bold;
  color: white;
  margin-bottom: 4px;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.stat-label {
  font-size: 0.9rem;
  opacity: 0.9;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: white;
}

.header-actions {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 20px;
}

.current-time {
  text-align: right;
  color: white;
}

.time-display {
  font-size: 2rem;
  font-weight: bold;
  margin-bottom: 2px;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.date-display {
  font-size: 0.9rem;
  opacity: 0.8;
  font-weight: 500;
}

.quick-access {
  display: flex;
  gap: 12px;
}

.quick-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: rgba(255, 255, 255, 0.2);
  color: white;
  text-decoration: none;
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 500;
  border: 1px solid rgba(255, 255, 255, 0.3);
  transition: all 0.3s ease;
  backdrop-filter: blur(10px);
}

.quick-btn:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.quick-btn img {
  width: 16px;
  height: 16px;
  filter: brightness(0) invert(1);
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 24px;
  margin-bottom: 40px;
}

.stat-card {
  background: white;
  border-radius: 16px;
  padding: 30px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e5e7eb;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.stat-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, #667eea, #764ba2);
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
}

.stat-icon {
  width: 60px;
  height: 60px;
  background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 20px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.stat-icon img {
  width: 32px;
  height: 32px;
  opacity: 0.8;
}

.stat-content h3 {
  margin: 0 0 8px 0;
  font-size: 2.5rem;
  font-weight: 700;
  color: #1f2937;
  background: linear-gradient(135deg, #667eea, #764ba2);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.stat-content p {
  margin: 0;
  color: #6b7280;
  font-size: 1rem;
  font-weight: 500;
}

/* Quick Actions */
.quick-actions {
  margin-bottom: 40px;
}

.quick-actions h2 {
  font-size: 1.8rem;
  font-weight: 700;
  color: #1f2937;
  margin-bottom: 24px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.quick-actions h2::before {
  content: '⚡';
  font-size: 1.5rem;
}

.actions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 20px;
}

.action-card {
  background: white;
  border-radius: 16px;
  padding: 30px;
  text-decoration: none;
  color: inherit;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e5e7eb;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.action-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, #10b981, #059669);
  transform: scaleX(0);
  transition: transform 0.3s ease;
}

.action-card:hover::before {
  transform: scaleX(1);
}

.action-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
  border-color: #10b981;
}

.action-icon {
  width: 60px;
  height: 60px;
  background: linear-gradient(135deg, #10b981, #059669);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 20px;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.action-icon img {
  width: 28px;
  height: 28px;
  filter: brightness(0) invert(1);
}

.action-card h3 {
  margin: 0 0 12px 0;
  font-size: 1.4rem;
  font-weight: 600;
  color: #1f2937;
}

.action-card p {
  margin: 0;
  color: #6b7280;
  font-size: 1rem;
  line-height: 1.5;
}

/* Chart Section */
.services-chart-section {
  margin: 40px 0;
  background: white;
  border-radius: 16px;
  padding: 30px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e5e7eb;
}

.services-chart-section h2 {
  font-size: 1.8rem;
  font-weight: 700;
  color: #1f2937;
  margin-bottom: 24px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.services-chart-section h2::before {
  content: '📊';
  font-size: 1.5rem;
}

.chart-container {
  position: relative;
  height: 400px;
  margin-bottom: 20px;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.no-chart-data {
  text-align: center;
  padding: 60px 20px;
  color: #6b7280;
}

.no-chart-icon {
  width: 80px;
  height: 80px;
  margin: 0 auto 20px;
  opacity: 0.5;
}

.no-chart-data h3 {
  margin: 0 0 12px 0;
  color: #374151;
  font-size: 1.5rem;
}

.no-chart-data p {
  margin: 0;
  font-size: 1rem;
}

/* Pending Approvals */
.pending-section {
  margin: 40px 0;
  background: white;
  border-radius: 16px;
  padding: 30px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e5e7eb;
}

.pending-section h2 {
  font-size: 1.8rem;
  font-weight: 700;
  color: #1f2937;
  margin-bottom: 24px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.pending-section h2::before {
  content: '⏳';
  font-size: 1.5rem;
}

.pending-list {
  display: grid;
  gap: 16px;
}

.pending-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24px;
  background: #fef3c7;
  border: 2px solid #f59e0b;
  border-radius: 12px;
  transition: all 0.3s ease;
}

.pending-card:hover {
  background: #fde68a;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);
}

.pending-info h4 {
  margin: 0 0 8px 0;
  font-size: 1.2rem;
  font-weight: 600;
  color: #92400e;
}

.pending-info p {
  margin: 0 0 4px 0;
  color: #92400e;
  font-weight: 500;
}

.pending-info small {
  color: #92400e;
  opacity: 0.8;
  font-size: 0.9rem;
}

.pending-actions {
  display: flex;
  gap: 12px;
}

.btn-approve, .btn-reject {
  padding: 10px 20px;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  font-size: 0.9rem;
  transition: all 0.3s ease;
}

.btn-approve {
  background: #10b981;
  color: white;
}

.btn-approve:hover {
  background: #059669;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-reject {
  background: #ef4444;
  color: white;
}

.btn-reject:hover {
  background: #dc2626;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

/* Appointments Sections */
.appointments-section, .upcoming-section, .activity-section {
  margin: 40px 0;
  background: white;
  border-radius: 16px;
  padding: 30px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e5e7eb;
}

.appointments-section h2, .upcoming-section h2, .activity-section h2 {
  font-size: 1.8rem;
  font-weight: 700;
  color: #1f2937;
  margin-bottom: 24px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.appointments-section h2::before {
  content: '📅';
  font-size: 1.5rem;
}

.upcoming-section h2::before {
  content: '📋';
  font-size: 1.5rem;
}

.activity-section h2::before {
  content: '📈';
  font-size: 1.5rem;
}

.appointments-list {
  display: grid;
  gap: 16px;
}

.appointment-card {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 24px;
  background: #f8fafc;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  transition: all 0.3s ease;
}

.appointment-card:hover {
  background: #f1f5f9;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.appointment-time {
  text-align: center;
  min-width: 80px;
}

.appointment-time .time {
  font-size: 1.4rem;
  font-weight: bold;
  color: #1f2937;
  display: block;
}

.appointment-details h4 {
  margin: 0 0 8px 0;
  font-size: 1.2rem;
  font-weight: 600;
  color: #1f2937;
}

.appointment-details p {
  margin: 0 0 4px 0;
  color: #6b7280;
  font-size: 0.95rem;
}

.appointment-details small {
  color: #9ca3af;
  font-size: 0.85rem;
}

.appointment-status {
  padding: 6px 16px;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.appointment-status.pending {
  background: #fef3c7;
  color: #d97706;
}

.appointment-status.confirmed {
  background: #dbeafe;
  color: #2563eb;
}

.appointment-status.completed {
  background: #d1fae5;
  color: #065f46;
}

.appointment-status.cancelled {
  background: #fee2e2;
  color: #dc2626;
}

/* No Appointments */
.no-appointments {
  text-align: center;
  padding: 60px 20px;
  color: #6b7280;
}

.no-appointments-icon {
  width: 80px;
  height: 80px;
  margin: 0 auto 20px;
  opacity: 0.5;
}

.no-appointments h3 {
  margin: 0 0 12px 0;
  color: #374151;
  font-size: 1.5rem;
}

.no-appointments p {
  margin: 0 0 20px 0;
  font-size: 1rem;
}

/* Upcoming Appointments */
.upcoming-list {
  display: grid;
  gap: 24px;
}

.date-group h4 {
  font-size: 1.2rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 12px;
  padding-bottom: 8px;
  border-bottom: 2px solid #e5e7eb;
}

.date-appointments {
  display: grid;
  gap: 12px;
}

.mini-appointment {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  background: #f8fafc;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  font-size: 0.9rem;
}

.mini-appointment .time {
  font-weight: 600;
  color: #1f2937;
  min-width: 60px;
}

.mini-appointment .patient {
  flex: 1;
  color: #374151;
}

.mini-appointment .status {
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.mini-appointment .status.pending {
  background: #fef3c7;
  color: #d97706;
}

.mini-appointment .status.confirmed {
  background: #dbeafe;
  color: #2563eb;
}

/* Activity Section */
.activity-list {
  display: grid;
  gap: 16px;
}

.activity-item {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding: 20px;
  background: #f8fafc;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  transition: all 0.3s ease;
}

.activity-item:hover {
  background: #f1f5f9;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.activity-icon {
  width: 48px;
  height: 48px;
  background: linear-gradient(135deg, #667eea, #764ba2);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.activity-icon img {
  width: 24px;
  height: 24px;
  filter: brightness(0) invert(1);
}

.activity-content p {
  margin: 0 0 8px 0;
  color: #374151;
  line-height: 1.5;
}

.activity-content small {
  color: #9ca3af;
  font-size: 0.85rem;
}

.no-activity {
  text-align: center;
  padding: 40px 20px;
  color: #6b7280;
  font-style: italic;
}

/* View All Links */
.view-all {
  text-align: center;
  margin-top: 24px;
}

.view-all a {
  color: #667eea;
  text-decoration: none;
  font-weight: 600;
  font-size: 1rem;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 0.3s ease;
}

.view-all a:hover {
  color: #764ba2;
  transform: translateX(4px);
}

/* Responsive Design */
@media (max-width: 1024px) {
  .header-content {
    flex-direction: column;
    text-align: center;
    gap: 30px;
  }

  .header-stats {
    justify-content: center;
  }

  .header-actions {
    align-items: center;
  }

  .actions-grid {
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  }
}

@media (max-width: 768px) {
  .welcome-section h1 {
    font-size: 2.2rem;
  }

  .header-stats {
    flex-direction: column;
    gap: 15px;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }

  .actions-grid {
    grid-template-columns: 1fr;
  }

  .pending-card {
    flex-direction: column;
    gap: 16px;
    text-align: center;
  }

  .pending-actions {
    justify-content: center;
  }

  .appointment-card {
    flex-direction: column;
    text-align: center;
    gap: 16px;
  }

  .mini-appointment {
    flex-direction: column;
    text-align: center;
    gap: 8px;
  }

  .activity-item {
    flex-direction: column;
    text-align: center;
    gap: 12px;
  }

  .quick-access {
    flex-direction: column;
    gap: 8px;
  }
}

@media (max-width: 480px) {
  .header-content {
    padding: 30px 20px;
  }

  .welcome-section h1 {
    font-size: 1.8rem;
  }

  .stat-number {
    font-size: 1.8rem;
  }

  .services-chart-section,
  .pending-section,
  .appointments-section,
  .upcoming-section,
  .activity-section {
    padding: 20px;
  }

  .chart-container {
    height: 300px;
  }
}
</style>

<?php include("../includes/footer.php"); ?>
