<?php
include("../includes/header.php");
require_once "../includes/guards.php";
if (role() !== 'dentist' && role() !== 'dentist_pending') {
  header('Location: ' . $base_url . '/public/login.php'); exit;
}
require_once "../models/users.php";
require_once "../models/appointments.php";

$userId = $_SESSION['user_id'];
$user = getUserById($userId);

// Get today's appointments
global $pdo;
$stmt = $pdo->prepare("
  SELECT a.*, u.name as clientName, u.phone as clientPhone
  FROM Appointments a
  JOIN Users u ON a.clientId = u.id
  WHERE a.dentistId = ? AND a.date = CURDATE()
  ORDER BY a.time ASC
");
$stmt->execute([$userId]);
$todaysAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming appointments for this week
$stmt = $pdo->prepare("
  SELECT a.*, u.name as clientName
  FROM Appointments a
  JOIN Users u ON a.clientId = u.id
  WHERE a.dentistId = ? AND a.date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
  AND a.status IN ('pending', 'confirmed')
  ORDER BY a.date ASC, a.time ASC
");
$stmt->execute([$userId]);
$upcomingAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total patients
$stmt = $pdo->prepare("
  SELECT COUNT(DISTINCT clientId) as totalPatients
  FROM Appointments
  WHERE dentistId = ?
");
$stmt->execute([$userId]);
$totalPatients = $stmt->fetch(PDO::FETCH_ASSOC)['totalPatients'];

// Get completed appointments count
$stmt = $pdo->prepare("
  SELECT COUNT(*) as completedCount
  FROM Appointments
  WHERE dentistId = ? AND status = 'completed'
");
$stmt->execute([$userId]);
$completedAppointments = $stmt->fetch(PDO::FETCH_ASSOC)['completedCount'];

// Get pending appointments (awaiting confirmation)
$stmt = $pdo->prepare("
  SELECT COUNT(*) as pendingCount
  FROM Appointments
  WHERE dentistId = ? AND status = 'pending'
");
$stmt->execute([$userId]);
$pendingAppointments = $stmt->fetch(PDO::FETCH_ASSOC)['pendingCount'];
?>

<!-- Enhanced Dashboard Header -->
<div class="dashboard-header">
  <div class="header-gradient">
    <div class="header-content">
      <div class="welcome-section">
        <h1><img src="<?php echo $base_url; ?>/assets/images/doctor_icon.svg" alt="Doctor" class="header-icon"> Welcome back, Dr. <?php echo htmlspecialchars($_SESSION['name']); ?>!</h1>
        <p>Manage your patients and appointments</p>
        <?php if (role() === 'dentist_pending'): ?>
          <div class="pending-notice">
            <span class="pending-icon">⚠️</span>
            <span>Your account is pending admin approval. Some features may be limited.</span>
          </div>
        <?php endif; ?>
        <div class="header-stats">
          <div class="header-stat">
            <span class="stat-number"><?php echo count($todaysAppointments); ?></span>
            <span class="stat-label">Today's Appointments</span>
          </div>
          <div class="header-stat">
            <span class="stat-number"><?php echo $totalPatients; ?></span>
            <span class="stat-label">Total Patients</span>
          </div>
          <div class="header-stat">
            <span class="stat-number"><?php echo count($upcomingAppointments); ?></span>
            <span class="stat-label">This Week</span>
          </div>
        </div>
      </div>
      <div class="header-actions">
        <div class="current-time">
          <div class="time-display"><?php echo date('H:i'); ?></div>
          <div class="date-display"><?php echo date('l, F j, Y'); ?></div>
        </div>
        <div class="quick-access">
          <a href="schedule.php" class="quick-btn">
            <img src="<?php echo $base_url; ?>/assets/images/appointment_icon.svg" alt="Schedule">
            My Schedule
          </a>
          <a href="patients.php" class="quick-btn">
            <img src="<?php echo $base_url; ?>/assets/images/patients_icon.svg" alt="Patients">
            My Patients
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Quick Stats -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/appointment_icon.svg" alt="Today's Appointments">
    </div>
    <div class="stat-content">
      <h3><?php echo count($todaysAppointments); ?></h3>
      <p>Today's Appointments</p>
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
      <img src="<?php echo $base_url; ?>/assets/images/appointments_icon.svg" alt="This Week">
    </div>
    <div class="stat-content">
      <h3><?php echo count($upcomingAppointments); ?></h3>
      <p>This Week</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/appointment_icon.svg" alt="Completed">
    </div>
    <div class="stat-content">
      <h3><?php echo $completedAppointments; ?></h3>
      <p>Completed</p>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/appointment_icon.svg" alt="Pending">
    </div>
    <div class="stat-content">
      <h3><?php echo $pendingAppointments; ?></h3>
      <p>Pending Confirmation</p>
    </div>
  </div></div>

<!-- Quick Actions -->
<div class="quick-actions">
  <h2>Quick Actions</h2>
  <div class="actions-grid">
    <a href="<?php echo $base_url; ?>/dentist/schedule.php" class="action-card">
      <div class="action-icon">
        <img src="<?php echo $base_url; ?>/assets/images/appointment_icon.svg" alt="Schedule">
      </div>
      <h3>My Schedule</h3>
      <p>View and manage appointments</p>
    </a>

    <a href="<?php echo $base_url; ?>/dentist/patients.php" class="action-card">
      <div class="action-icon">
        <img src="<?php echo $base_url; ?>/assets/images/patients_icon.svg" alt="Patients">
      </div>
      <h3>My Patients</h3>
      <p>View patient information</p>
    </a>

    <a href="<?php echo $base_url; ?>/dentist/profile.php" class="action-card">
      <div class="action-icon">
        <img src="<?php echo $base_url; ?>/assets/images/people_icon.svg" alt="Profile">
      </div>
      <h3>My Profile</h3>
      <p>Update professional information</p>
    </a>

    <a href="<?php echo $base_url; ?>/dentist/blocked_dates.php" class="action-card">
      <div class="action-icon">
        <img src="<?php echo $base_url; ?>/assets/images/calendar_icon.svg" alt="Availability">
      </div>
      <h3>My Availability</h3>
      <p>Block vacation and personal days</p>

        <a href="<?php echo $base_url; ?>/dentist/reports.php" class="action-card">
          <div class="action-icon">
            <img src="<?php echo $base_url; ?>/assets/images/appointment_icon.svg" alt="Reports">
          </div>
          <h3>Reports</h3>
          <p>View appointment and patient analytics</p>
        </a>
    </a>

    <a href="<?php echo $base_url; ?>/public/contact.php" class="action-card">
      <div class="action-icon">
        <img src="<?php echo $base_url; ?>/assets/images/info_icon.svg" alt="Support">
      </div>
      <h3>Support</h3>
      <p>Contact clinic administration</p>
    </a>
  </div>
</div>

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
  <p>You have a free day! Take some time to prepare for tomorrow's patients.</p>
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
            <span class="patient"><?php echo htmlspecialchars($appt['clientName']); ?></span>
            <span class="status <?php echo $appt['status']; ?>"><?php echo ucfirst($appt['status']); ?></span>
          </div>
    <?php endforeach; ?>
        </div>
      </div>
  </div>
  <div class="view-all">
    <a href="<?php echo $base_url; ?>/dentist/schedule.php">View Full Schedule →</a>
  </div>
</div>
<?php endif; ?>

</div>

</div>

<style>
/* Enhanced Dashboard Header */
.dashboard-header {
  margin-bottom: 30px;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.header-gradient {
  background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
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

.pending-notice {
  display: flex;
  align-items: center;
  gap: 10px;
  background: rgba(245, 158, 11, 0.2);
  border: 1px solid rgba(245, 158, 11, 0.3);
  border-radius: 8px;
  padding: 12px 16px;
  margin: 15px 0;
  color: white;
}

.pending-icon {
  font-size: 1.2rem;
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
  background: linear-gradient(90deg, #3b82f6, #1e40af);
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
  background: linear-gradient(135deg, #3b82f6, #1e40af);
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
  background: linear-gradient(90deg, #3b82f6, #1e40af);
  transform: scaleX(0);
  transition: transform 0.3s ease;
}

.action-card:hover::before {
  transform: scaleX(1);
}

.action-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
  border-color: #3b82f6;
}

.action-icon {
  width: 60px;
  height: 60px;
  background: linear-gradient(135deg, #3b82f6, #1e40af);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 20px;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
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

/* Appointments Sections */
.appointments-section, .upcoming-section {
  margin: 40px 0;
  background: white;
  border-radius: 16px;
  padding: 30px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e5e7eb;
}

.appointments-section h2, .upcoming-section h2 {
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
  margin: 0;
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

/* View All Links */
.view-all {
  text-align: center;
  margin-top: 24px;
}

.view-all a {
  color: #3b82f6;
  text-decoration: none;
  font-weight: 600;
  font-size: 1rem;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 0.3s ease;
}

.view-all a:hover {
  color: #1e40af;
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

  .appointments-section,
  .upcoming-section {
    padding: 20px;
  }

  .appointment-card {
    padding: 20px;
  }
}
</style>

<?php include("../includes/footer.php"); ?>
