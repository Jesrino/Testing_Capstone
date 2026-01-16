<?php
include("../includes/header.php");
require_once "../includes/guards.php";
requireRole('client');
require_once "../models/users.php";
require_once "../models/Appointments.php";
require_once "../models/Notifications.php";

$clientId = $_SESSION['user_id'];
$user = getUserById($clientId);

// Get client's appointment statistics
global $pdo;

// Total appointments
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM Appointments WHERE clientId = ?");
$stmt->execute([$clientId]);
$totalAppointments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Upcoming appointments (next 7 days)
$stmt = $pdo->prepare("
  SELECT COUNT(*) as upcoming
  FROM Appointments
  WHERE clientId = ? AND date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
  AND status IN ('pending', 'confirmed')
");
$stmt->execute([$clientId]);
$upcomingAppointments = $stmt->fetch(PDO::FETCH_ASSOC)['upcoming'];

// Completed appointments
$stmt = $pdo->prepare("SELECT COUNT(*) as completed FROM Appointments WHERE clientId = ? AND status = 'completed'");
$stmt->execute([$clientId]);
$completedAppointments = $stmt->fetch(PDO::FETCH_ASSOC)['completed'];

// Today's appointments
$stmt = $pdo->prepare("SELECT COUNT(*) as today FROM Appointments WHERE clientId = ? AND date = CURDATE()");
$stmt->execute([$clientId]);
$todayAppointments = $stmt->fetch(PDO::FETCH_ASSOC)['today'];

// Get upcoming appointments details
$stmt = $pdo->prepare("
  SELECT a.*, d.name as dentistName
  FROM Appointments a
  LEFT JOIN users d ON a.dentistId = d.id
  WHERE a.clientId = ? AND a.date >= CURDATE() AND a.status IN ('pending', 'confirmed')
  ORDER BY a.date ASC, a.time ASC
  LIMIT 5
");
$stmt->execute([$clientId]);
$upcomingAppts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent appointments
$stmt = $pdo->prepare("
  SELECT a.*, d.name as dentistName
  FROM Appointments a
  LEFT JOIN users d ON a.dentistId = d.id
  WHERE a.clientId = ?
  ORDER BY a.createdAt DESC
  LIMIT 5
");
$stmt->execute([$clientId]);
$recentAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unread notifications count
$stmt = $pdo->prepare("SELECT COUNT(*) as unread FROM Notifications WHERE userId = ? AND isRead = 0");
$stmt->execute([$clientId]);
$unreadNotifications = $stmt->fetch(PDO::FETCH_ASSOC)['unread'];
?>

<!-- Enhanced Dashboard Header -->
<div class="dashboard-header">
  <div class="header-gradient">
    <div class="header-content">
      <div class="welcome-section">
        <h1><img src="<?php echo $base_url; ?>/assets/images/profile.svg" alt="Welcome" class="header-icon"> Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</h1>
        <p>Manage your dental appointments and stay healthy</p>
        <div class="header-stats">
          <div class="header-stat">
            <span class="stat-number"><?php echo $totalAppointments; ?></span>
            <span class="stat-label">Total Visits</span>
          </div>
          <div class="header-stat">
            <span class="stat-number"><?php echo $upcomingAppointments; ?></span>
            <span class="stat-label">Upcoming</span>
          </div>
          <div class="header-stat">
            <span class="stat-number"><?php echo $completedAppointments; ?></span>
            <span class="stat-label">Completed</span>
          </div>
        </div>
      </div>
      <div class="header-actions">
        <div class="current-time">
          <div class="time-display"><?php echo date('H:i'); ?></div>
          <div class="date-display"><?php echo date('l, F j, Y'); ?></div>
        </div>
        <div class="quick-access">
          <a href="chatbot.php" class="quick-btn">
            <img src="<?php echo $base_url; ?>/assets/images/info_icon.svg" alt="AI Assistant">
            AI Assistant
          </a>
          <a href="profile.php" class="quick-btn">
            <img src="<?php echo $base_url; ?>/assets/images/profile.svg" alt="Profile">
            My Profile
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="container">

<!-- Client Stats -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/appointments_icon.svg" alt="Total Appointments">
    </div>
    <div class="stat-content">
      <h3><?php echo $totalAppointments; ?></h3>
      <p>Total Appointments</p>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/appointment_icon.svg" alt="Upcoming">
    </div>
    <div class="stat-content">
      <h3><?php echo $upcomingAppointments; ?></h3>
      <p>Upcoming</p>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/tick_icon.svg" alt="Completed">
    </div>
    <div class="stat-content">
      <h3><?php echo $completedAppointments; ?></h3>
      <p>Completed</p>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon">
      <img src="<?php echo $base_url; ?>/assets/images/home_icon.svg" alt="Today">
    </div>
    <div class="stat-content">
      <h3><?php echo $todayAppointments; ?></h3>
      <p>Today</p>
    </div>
  </div>
</div>

<!-- Quick Actions -->
<div class="quick-actions">
  <h2>Quick Actions</h2>
  <div class="actions-grid">
    <a href="<?php echo $base_url; ?>/client/appointments.php" class="action-card">
      <div class="action-icon">
        <img src="<?php echo $base_url; ?>/assets/images/add_icon.svg" alt="Book Appointment">
      </div>
      <h3>Book Appointment</h3>
      <p>Schedule a new dental appointment</p>
    </a>

    <a href="<?php echo $base_url; ?>/client/appointments.php" class="action-card">
      <div class="action-icon">
        <img src="<?php echo $base_url; ?>/assets/images/appointments_icon.svg" alt="My Appointments">
      </div>
      <h3>My Appointments</h3>
      <p>View and manage your appointments</p>
    </a>

    <a href="<?php echo $base_url; ?>/client/payments.php" class="action-card">
      <div class="action-icon">
        <img src="<?php echo $base_url; ?>/assets/images/payments.svg" alt="Payments">
      </div>
      <h3>Payments</h3>
      <p>View payment history and invoices</p>
    </a>

    <a href="<?php echo $base_url; ?>/client/notifications.php" class="action-card">
      <div class="action-icon">
        <img src="<?php echo $base_url; ?>/assets/images/info_icon.svg" alt="Notifications">
      </div>
      <h3>Notifications</h3>
      <p>Check your messages <?php if ($unreadNotifications > 0): ?><span class="notification-badge"><?php echo $unreadNotifications; ?></span><?php endif; ?></p>
    </a>

    <a href="<?php echo $base_url; ?>/client/profile.php" class="action-card">
      <div class="action-icon">
        <img src="<?php echo $base_url; ?>/assets/images/profile.svg" alt="Profile">
      </div>
      <h3>My Profile</h3>
      <p>Update your personal information</p>
    </a>

    <a href="<?php echo $base_url; ?>/client/chatbot.php" class="action-card">
      <div class="action-icon">
        <img src="<?php echo $base_url; ?>/assets/images/chats_icon.svg" alt="Chatbot">
      </div>
      <h3>AI Assistant</h3>
      <p>Get help with dental questions</p>
    </a>
  </div>
</div>

<!-- Upcoming Appointments -->
<?php if (!empty($upcomingAppts)): ?>
<div class="appointments-section">
  <h2>Upcoming Appointments</h2>
  <div class="appointments-list">
    <?php foreach ($upcomingAppts as $appt): ?>
      <div class="appointment-card">
        <div class="appointment-time">
          <div class="time"><?php echo date('H:i', strtotime($appt['time'])); ?></div>
          <div class="date"><?php echo date('M d, Y', strtotime($appt['date'])); ?></div>
        </div>
        <div class="appointment-details">
          <h4><?php echo htmlspecialchars($appt['dentistName'] ? 'Dr. ' . $appt['dentistName'] : 'Dentist to be assigned'); ?></h4>
          <p><?php echo htmlspecialchars($appt['notes'] ?? 'Regular checkup'); ?></p>
        </div>
        <div class="appointment-status <?php echo $appt['status']; ?>">
          <?php echo ucfirst($appt['status']); ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="view-all">
    <a href="<?php echo $base_url; ?>/client/appointments.php">View All Appointments →</a>
  </div>
</div>
<?php else: ?>
<div class="no-appointments">
  <div class="no-appointments-icon">
    <img src="<?php echo $base_url; ?>/assets/images/appointment_icon.svg" alt="No Appointments">
  </div>
  <h3>No upcoming appointments</h3>
  <p>Book your next dental checkup to stay healthy.</p>
  <a href="<?php echo $base_url; ?>/client/appointments.php" class="btn-primary">Book Appointment</a>
</div>
<?php endif; ?>

<!-- Recent Activity -->
<div class="activity-section">
  <h2>Recent Activity</h2>
  <div class="activity-list">
    <?php if (!empty($recentAppointments)): ?>
      <?php foreach ($recentAppointments as $appt): ?>
        <div class="activity-item">
          <div class="activity-icon">
            <img src="<?php echo $base_url; ?>/assets/images/appointment_icon.svg" alt="Appointment">
          </div>
          <div class="activity-content">
            <p>Appointment with <strong><?php echo htmlspecialchars($appt['dentistName'] ? 'Dr. ' . $appt['dentistName'] : 'Dentist'); ?></strong></p>
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

<style>
/* Enhanced Dashboard Header */
.dashboard-header {
  margin-bottom: 30px;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.header-gradient {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
  background: linear-gradient(90deg, #10b981, #059669);
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
  background: linear-gradient(135deg, #10b981, #059669);
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

.notification-badge {
  background: #ef4444;
  color: white;
  border-radius: 50%;
  padding: 2px 6px;
  font-size: 0.75rem;
  font-weight: bold;
  margin-left: 5px;
}

/* Appointments Sections */
.appointments-section, .activity-section {
  margin: 40px 0;
  background: white;
  border-radius: 16px;
  padding: 30px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e5e7eb;
}

.appointments-section h2, .activity-section h2 {
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

.appointment-time .date {
  font-size: 0.875rem;
  color: #6b7280;
  margin-top: 4px;
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

.btn-primary {
  background: #10b981;
  color: white;
  border: none;
  padding: 12px 24px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 16px;
  font-weight: 500;
  text-decoration: none;
  display: inline-block;
  margin-top: 10px;
  transition: all 0.3s ease;
}

.btn-primary:hover {
  background: #059669;
  text-decoration: none;
  color: white;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
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
  background: linear-gradient(135deg, #10b981, #059669);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
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
  color: #10b981;
  text-decoration: none;
  font-weight: 600;
  font-size: 1rem;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 0.3s ease;
}

.view-all a:hover {
  color: #059669;
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

  .appointments-section,
  .activity-section {
    padding: 20px;
  }

  .appointment-card {
    padding: 20px;
  }
}
</style>

<?php include("../includes/footer.php"); ?>
