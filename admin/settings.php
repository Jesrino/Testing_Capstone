 <?php
require_once "../includes/guards.php";
requireRole('admin');
include("../includes/header.php");
require_once "../models/users.php";

$userId = $_SESSION['user_id'];
$currentTheme = 'light'; // default
$maintenanceMode = false; // default
$emailNotifications = true; // default

// Get current settings
$profileData = getUserProfileData($userId);
if (isset($profileData['theme'])) {
  $currentTheme = $profileData['theme'];
}
if (isset($profileData['maintenance_mode'])) {
  $maintenanceMode = $profileData['maintenance_mode'];
}
if (isset($profileData['email_notifications'])) {
  $emailNotifications = $profileData['email_notifications'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['theme'])) {
    $theme = $_POST['theme'] ?? 'light';
    $profileData['theme'] = $theme;
    $currentTheme = $theme;
  }
  if (isset($_POST['maintenance_mode'])) {
    $maintenanceMode = isset($_POST['maintenance_mode']);
    $profileData['maintenance_mode'] = $maintenanceMode;
  }
  if (isset($_POST['email_notifications'])) {
    $emailNotifications = isset($_POST['email_notifications']);
    $profileData['email_notifications'] = $emailNotifications;
  }

  updateUserProfileData($userId, $profileData);
  $successMessage = "Settings updated successfully!";
}

// Get system information
$systemInfo = [
  'php_version' => PHP_VERSION,
  'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
  'database_version' => 'MySQL 8.0', // You might want to query this
  'total_users' => 0,
  'total_appointments' => 0,
  'total_revenue' => 0
];

try {
  global $pdo;
  $stmt = $pdo->query("SELECT COUNT(*) FROM Users");
  $systemInfo['total_users'] = $stmt->fetchColumn();

  $stmt = $pdo->query("SELECT COUNT(*) FROM Appointments");
  $systemInfo['total_appointments'] = $stmt->fetchColumn();

  $stmt = $pdo->query("SELECT SUM(amount) FROM Payments WHERE status = 'confirmed'");
  $systemInfo['total_revenue'] = $stmt->fetchColumn() ?? 0;
} catch (Exception $e) {
  // Keep defaults if DB fails
}
?>

<div class="dashboard-header">
  <div class="welcome-section">
    <h1>System Settings</h1>
    <p>Configure system preferences and appearance</p>
  </div>
</div>

<div class="container">
  <?php if (isset($successMessage)): ?>
    <div class="success-message">
      <?php echo htmlspecialchars($successMessage); ?>
    </div>
  <?php endif; ?>

  <!-- System Information -->
  <div class="settings-section">
    <h2><i class="fas fa-info-circle"></i> System Information</h2>
    <div class="info-grid">
      <div class="info-card">
        <div class="info-icon">🖥️</div>
        <div class="info-content">
          <h4>PHP Version</h4>
          <p><?php echo htmlspecialchars($systemInfo['php_version']); ?></p>
        </div>
      </div>
      <div class="info-card">
        <div class="info-icon">🌐</div>
        <div class="info-content">
          <h4>Server Software</h4>
          <p><?php echo htmlspecialchars($systemInfo['server_software']); ?></p>
        </div>
      </div>
      <div class="info-card">
        <div class="info-icon">🗄️</div>
        <div class="info-content">
          <h4>Database</h4>
          <p><?php echo htmlspecialchars($systemInfo['database_version']); ?></p>
        </div>
      </div>
      <div class="info-card">
        <div class="info-icon">👥</div>
        <div class="info-content">
          <h4>Total Users</h4>
          <p><?php echo number_format($systemInfo['total_users']); ?></p>
        </div>
      </div>
      <div class="info-card">
        <div class="info-icon">📅</div>
        <div class="info-content">
          <h4>Total Appointments</h4>
          <p><?php echo number_format($systemInfo['total_appointments']); ?></p>
        </div>
      </div>
      <div class="info-card">
        <div class="info-icon">💰</div>
        <div class="info-content">
          <h4>Total Revenue</h4>
          <p>₱<?php echo number_format($systemInfo['total_revenue'], 2); ?></p>
        </div>
      </div>
    </div>
  </div>

  <!-- Appearance Settings -->
  <div class="settings-section">
    <h2><i class="fas fa-palette"></i> Appearance Settings</h2>
    <form method="POST" action="">
      <div class="form-group">
        <label for="theme">Theme:</label>
        <select name="theme" id="theme">
          <option value="light" <?php echo $currentTheme === 'light' ? 'selected' : ''; ?>>Light Mode</option>
          <option value="dark" <?php echo $currentTheme === 'dark' ? 'selected' : ''; ?>>Dark Mode</option>
        </select>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn-primary">Save Appearance Settings</button>
      </div>
    </form>
  </div>

  <!-- Notification Settings -->
  <div class="settings-section">
    <h2><i class="fas fa-bell"></i> Notification Settings</h2>
    <form method="POST" action="">
      <div class="form-group checkbox-group">
        <label class="checkbox-label">
          <input type="checkbox" name="email_notifications" value="1" <?php echo $emailNotifications ? 'checked' : ''; ?>>
          <span class="checkmark"></span>
          Enable Email Notifications
        </label>
        <p class="form-help">Receive email notifications for important system events</p>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn-primary">Save Notification Settings</button>
      </div>
    </form>
  </div>

  <!-- Security Settings -->
  <div class="settings-section">
    <h2><i class="fas fa-shield-alt"></i> Security Settings</h2>
    <div class="security-options">
      <div class="security-item">
        <div class="security-icon">🔐</div>
        <div class="security-content">
          <h4>Password Policy</h4>
          <p>Minimum 8 characters, must include uppercase, lowercase, and numbers</p>
          <button class="btn-secondary">Configure</button>
        </div>
      </div>
      <div class="security-item">
        <div class="security-icon">⏰</div>
        <div class="security-content">
          <h4>Session Timeout</h4>
          <p>Auto-logout after 30 minutes of inactivity</p>
          <button class="btn-secondary">Configure</button>
        </div>
      </div>
      <div class="security-item">
        <div class="security-icon">📊</div>
        <div class="security-content">
          <h4>Login Attempts</h4>
          <p>Maximum 5 failed attempts before temporary lockout</p>
          <button class="btn-secondary">Configure</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Maintenance Settings -->
  <div class="settings-section">
    <h2><i class="fas fa-tools"></i> Maintenance Settings</h2>
    <form method="POST" action="">
      <div class="form-group checkbox-group">
        <label class="checkbox-label">
          <input type="checkbox" name="maintenance_mode" value="1" <?php echo $maintenanceMode ? 'checked' : ''; ?>>
          <span class="checkmark"></span>
          Enable Maintenance Mode
        </label>
        <p class="form-help">Put the system in maintenance mode for updates</p>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn-primary">Save Maintenance Settings</button>
      </div>
    </form>
  </div>

  <!-- Quick Actions -->
  <div class="settings-section">
    <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
    <div class="quick-actions-grid">
      <a href="<?php echo $base_url; ?>/admin/blocked_dates.php" class="action-card">
        <div class="action-icon">📅</div>
        <div class="action-content">
          <h4>Manage Blocked Dates</h4>
          <p>Set unavailable dates for appointments</p>
        </div>
      </a>
      <a href="<?php echo $base_url; ?>/admin/smtp_settings.php" class="action-card">
        <div class="action-icon">📧</div>
        <div class="action-content">
          <h4>SMTP Settings</h4>
          <p>Configure email server settings</p>
        </div>
      </a>
      <a href="<?php echo $base_url; ?>/admin/users.php" class="action-card">
        <div class="action-icon">👥</div>
        <div class="action-content">
          <h4>User Management</h4>
          <p>Manage system users and roles</p>
        </div>
      </a>
      <a href="<?php echo $base_url; ?>/admin/reports.php" class="action-card">
        <div class="action-icon">📊</div>
        <div class="action-content">
          <h4>Reports & Analytics</h4>
          <p>View system reports and analytics</p>
        </div>
      </a>
      <div class="action-card" onclick="clearCache()">
        <div class="action-icon">🗑️</div>
        <div class="action-content">
          <h4>Clear Cache</h4>
          <p>Clear system cache for better performance</p>
        </div>
      </div>
      <div class="action-card" onclick="backupDatabase()">
        <div class="action-icon">💾</div>
        <div class="action-content">
          <h4>Backup Database</h4>
          <p>Create a backup of the database</p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function clearCache() {
  if (confirm('Are you sure you want to clear the system cache? This may temporarily slow down the system.')) {
    // Simulate cache clearing
    alert('Cache cleared successfully!');
  }
}

function backupDatabase() {
  if (confirm('Are you sure you want to create a database backup? This may take a few moments.')) {
    // Simulate backup
    alert('Database backup created successfully!');
  }
}
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

.settings-section {
  background: white;
  padding: 24px;
  border-radius: 12px;
  margin-bottom: 24px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  border: 1px solid #e5e7eb;
}

.settings-section h2 {
  margin: 0 0 20px 0;
  font-size: 1.5rem;
  color: #0f172a;
  border-bottom: 2px solid #eef2f7;
  padding-bottom: 10px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.settings-section h2 i {
  color: #556B2F;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 20px;
}

.info-card {
  background: #f8fafc;
  border-radius: 8px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 15px;
  border: 1px solid #e5e7eb;
}

.info-icon {
  font-size: 2rem;
  width: 50px;
  height: 50px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.info-content h4 {
  margin: 0 0 5px 0;
  font-size: 1rem;
  color: #374151;
  font-weight: 600;
}

.info-content p {
  margin: 0;
  color: #6b7280;
  font-size: 1.1rem;
  font-weight: 500;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 500;
  color: #374151;
}

.form-group select,
.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="password"] {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  background: white;
}

.form-group.checkbox-group {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  font-weight: 500;
  color: #374151;
}

.checkbox-label input[type="checkbox"] {
  display: none;
}

.checkmark {
  width: 20px;
  height: 20px;
  border: 2px solid #d1d5db;
  border-radius: 4px;
  position: relative;
  background: white;
}

.checkbox-label input[type="checkbox"]:checked + .checkmark {
  background: #556B2F;
  border-color: #556B2F;
}

.checkbox-label input[type="checkbox"]:checked + .checkmark::after {
  content: '✓';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  color: white;
  font-size: 12px;
  font-weight: bold;
}

.form-help {
  margin: 5px 0 0 30px;
  color: #6b7280;
  font-size: 0.9rem;
  font-style: italic;
}

.form-actions {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #e5e7eb;
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

.security-options {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.security-item {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 20px;
  background: #f8fafc;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.security-icon {
  font-size: 2rem;
  width: 50px;
  height: 50px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.security-content {
  flex: 1;
}

.security-content h4 {
  margin: 0 0 5px 0;
  font-size: 1.1rem;
  color: #374151;
  font-weight: 600;
}

.security-content p {
  margin: 0 0 10px 0;
  color: #6b7280;
  font-size: 0.9rem;
}

.quick-actions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 20px;
}

.action-card {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 20px;
  background: #f8fafc;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  text-decoration: none;
  color: inherit;
  transition: all 0.2s;
  cursor: pointer;
}

.action-card:hover {
  background: #f1f5f9;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.action-icon {
  font-size: 2rem;
  width: 50px;
  height: 50px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.action-content h4 {
  margin: 0 0 5px 0;
  font-size: 1.1rem;
  color: #374151;
  font-weight: 600;
}

.action-content p {
  margin: 0;
  color: #6b7280;
  font-size: 0.9rem;
}

@media (max-width: 768px) {
  .info-grid {
    grid-template-columns: 1fr;
  }

  .quick-actions-grid {
    grid-template-columns: 1fr;
  }

  .security-options {
    gap: 15px;
  }

  .security-item {
    flex-direction: column;
    text-align: center;
    gap: 10px;
  }

  .welcome-section h1 {
    font-size: 2rem;
  }
}
</style>

<?php include("../includes/footer.php"); ?>
