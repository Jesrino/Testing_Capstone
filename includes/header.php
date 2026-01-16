<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$role = $_SESSION['role'] ?? null;
// Determine the base path dynamically
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$base_path = dirname($_SERVER['PHP_SELF']);
$base_url = $protocol . "://" . $host . str_replace('\\', '/', dirname($base_path));
// Prepare unread notifications count for header badge (efficient DB COUNT)
$unreadCount = 0;
if (!empty($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/db.php';
        $countStmt = $pdo->prepare("SELECT COUNT(*) as c FROM Notifications WHERE userId = ? AND isRead = FALSE");
        $countStmt->execute([$_SESSION['user_id']]);
        $row = $countStmt->fetch(PDO::FETCH_ASSOC);
        $unreadCount = (int)($row['c'] ?? 0);
    } catch (Exception $e) {
        // fallback to zero if DB not available
        $unreadCount = 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dents-City</title>
  <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/layout-fix.css" />
  <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/style.css" />
  <script src="<?php echo $base_url; ?>/assets/header.js"></script>
</head>
<body>
<div class="site-root<?php echo $role ? ' has-sidebar' : ''; ?>">
<header class="site-header" id="siteHeader">
  <!-- Left: Logo + Brand Name -->
  <div class="header-left">
    <div class="logo-brand">
      <img src="<?php echo $base_url; ?>/assets/images/logo.svg" alt="Dents-City Logo" class="logo-img" />
      <span class="brand-name">Dents–City</span>
    </div>
  </div>

  <!-- Center: Navigation -->
  <nav class="header-nav main-nav">
    <a href="<?php echo $base_url; ?>/public/index.php" class="nav-link">Home</a>
    <a href="<?php echo $base_url; ?>/public/services.php" class="nav-link">Services</a>
    <a href="<?php echo $base_url; ?>/public/about.php" class="nav-link">About</a>
    <a href="<?php echo $base_url; ?>/public/contact.php" class="nav-link">Contact</a>
  </nav>

  <!-- Right: Actions -->
  <div class="header-right">
    <?php if(basename($_SERVER['REQUEST_URI']) !== 'register.php'): ?>
      <?php if($role):
        $notifPath = $base_url;
        if ($role === 'admin') $notifPath .= '/admin/notifications.php';
        elseif ($role === 'client') $notifPath .= '/client/notifications.php';
        else $notifPath .= '/dentist/notifications.php';
      ?>
        <a href="<?php echo $notifPath; ?>" class="notifications-btn" title="Notifications">
          <span class="notifications-icon">🔔</span>
          <?php if (!empty($unreadCount) && $unreadCount > 0): ?>
            <span class="notification-badge"><?php echo $unreadCount; ?></span>
          <?php endif; ?>
        </a>
      <?php endif; ?>

      <?php if(!$role): ?>
        <a class="btn btn-primary" href="<?php echo $base_url; ?>/public/register.php">Create account</a>
      <?php elseif($role !== 'admin' && $role !== 'client'): ?>
        <div class="profile-menu">
          <button class="profile-btn" onclick="toggleDropdown()" title="Profile">
            <img src="<?php echo $base_url; ?>/assets/images/profile_icon.svg" alt="Profile" class="profile-icon" />
          </button>
          <div id="profile-dropdown" class="profile-dropdown">
            <?php if($role === 'dentist' || $role === 'dentist_pending'): ?>
              <a href="<?php echo $base_url; ?>/dentist/dashboard.php">Dentist Dashboard</a>
              <a href="<?php echo $base_url; ?>/dentist/patients.php">Patients</a>
              <a href="<?php echo $base_url; ?>/dentist/schedule.php">Schedule</a>
              <a href="<?php echo $base_url; ?>/dentist/profile.php">Profile</a>
            <?php endif; ?>
            <a href="<?php echo $base_url; ?>/public/logout.php">Logout</a>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</header>
<?php if($role): ?>
<!-- Mobile sidebar toggle -->
<button class="sidenav-toggle" onclick="toggleSidebar()">
  <img src="<?php echo $base_url; ?>/assets/images/menu_icon.svg" alt="Menu" style="width: 24px; height: 24px;">
</button>
<div class="sidenav-overlay" onclick="closeSidebar()"></div>
<aside class="sidenav<?php echo ($role === 'admin' || $role === 'client') ? ' admin-sidenav' : ''; ?>">
  <?php if($role !== 'admin'): ?>
  <div class="sidenav-header">
    <img src="<?php echo $base_url; ?>/assets/images/logo.svg" alt="Dents-City Logo" class="sidenav-logo" />
  </div>
  <?php endif; ?>
  <nav class="sidenav-nav">
    <button class="sidebar-collapse-btn" onclick="toggleSidebarCollapse()" title="Collapse Sidebar">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
      </svg>
    </button>
    <?php
    // Get current page for active state
    $currentPage = basename($_SERVER['PHP_SELF']);
    $currentDir = basename(dirname($_SERVER['PHP_SELF']));
    ?>
    <?php if($role === 'client'): ?>
      <a href="<?php echo $base_url; ?>/client/dashboard.php" class="sidenav-link <?php echo ($currentPage === 'dashboard.php' && $currentDir === 'client') ? 'active' : ''; ?>">
        <span class="nav-icon">📊</span>
        <span class="nav-text">Dashboard</span>
      </a>
      <a href="<?php echo $base_url; ?>/client/appointments.php" class="sidenav-link <?php echo ($currentPage === 'appointments.php' && $currentDir === 'client') ? 'active' : ''; ?>">
        <span class="nav-icon">📅</span>
        <span class="nav-text">Appointments</span>
      </a>
      <a href="<?php echo $base_url; ?>/client/notifications.php" class="sidenav-link <?php echo ($currentPage === 'notifications.php' && $currentDir === 'client') ? 'active' : ''; ?>">
        <span class="nav-icon">🔔</span>
        <span class="nav-text">Notifications</span>
        <?php if ($unreadCount > 0): ?>
          <span class="nav-badge"><?php echo $unreadCount; ?></span>
        <?php endif; ?>
      </a>
      <a href="<?php echo $base_url; ?>/client/payments.php" class="sidenav-link <?php echo ($currentPage === 'payments.php' && $currentDir === 'client') ? 'active' : ''; ?>">
        <span class="nav-icon">💳</span>
        <span class="nav-text">Payments</span>
      </a>
      <a href="<?php echo $base_url; ?>/client/profile.php" class="sidenav-link <?php echo ($currentPage === 'profile.php' && $currentDir === 'client') ? 'active' : ''; ?>">
        <span class="nav-icon">👤</span>
        <span class="nav-text">Profile</span>
      </a>
    <?php elseif($role === 'dentist' || $role === 'dentist_pending'): ?>
      <a href="<?php echo $base_url; ?>/dentist/dashboard.php" class="sidenav-link <?php echo ($currentPage === 'dashboard.php' && $currentDir === 'dentist') ? 'active' : ''; ?>">
        <span class="nav-icon">📊</span>
        <span class="nav-text">Dashboard</span>
      </a>
      <a href="<?php echo $base_url; ?>/dentist/patients.php" class="sidenav-link <?php echo ($currentPage === 'patients.php' && $currentDir === 'dentist') ? 'active' : ''; ?>">
        <span class="nav-icon">👥</span>
        <span class="nav-text">Patients</span>
      </a>
      <a href="<?php echo $base_url; ?>/dentist/schedule.php" class="sidenav-link <?php echo ($currentPage === 'schedule.php' && $currentDir === 'dentist') ? 'active' : ''; ?>">
        <span class="nav-icon">📅</span>
        <span class="nav-text">Schedule</span>
      </a>
      <a href="<?php echo $base_url; ?>/dentist/notifications.php" class="sidenav-link <?php echo ($currentPage === 'notifications.php' && $currentDir === 'dentist') ? 'active' : ''; ?>">
        <span class="nav-icon">🔔</span>
        <span class="nav-text">Notifications</span>
        <?php if ($unreadCount > 0): ?>
          <span class="nav-badge"><?php echo $unreadCount; ?></span>
        <?php endif; ?>
      </a>
      <a href="<?php echo $base_url; ?>/dentist/profile.php" class="sidenav-link <?php echo ($currentPage === 'profile.php' && $currentDir === 'dentist') ? 'active' : ''; ?>">
        <span class="nav-icon">👤</span>
        <span class="nav-text">Profile</span>
      </a>
      <a href="<?php echo $base_url; ?>/dentist/blocked_dates.php" class="sidenav-link <?php echo ($currentPage === 'blocked_dates.php' && $currentDir === 'dentist') ? 'active' : ''; ?>">
        <span class="nav-icon">⏰</span>
        <span class="nav-text">My Availability</span>
      </a>
      <a href="<?php echo $base_url; ?>/dentist/reports.php" class="sidenav-link <?php echo ($currentPage === 'reports.php' && $currentDir === 'dentist') ? 'active' : ''; ?>">
        <span class="nav-icon">📈</span>
        <span class="nav-text">Reports</span>
      </a>
    <?php elseif($role === 'admin'): ?>
      <a href="<?php echo $base_url; ?>/admin/dashboard.php" class="sidenav-link <?php echo ($currentPage === 'dashboard.php' && $currentDir === 'admin') ? 'active' : ''; ?>">
        <span class="nav-icon">📊</span>
        <span class="nav-text">Dashboard</span>
      </a>
      <a href="<?php echo $base_url; ?>/admin/users.php" class="sidenav-link <?php echo ($currentPage === 'users.php' && $currentDir === 'admin') ? 'active' : ''; ?>">
        <span class="nav-icon">👥</span>
        <span class="nav-text">Users</span>
      </a>
      <a href="<?php echo $base_url; ?>/admin/reports.php" class="sidenav-link <?php echo ($currentPage === 'reports.php' && $currentDir === 'admin') ? 'active' : ''; ?>">
        <span class="nav-icon">📈</span>
        <span class="nav-text">Reports</span>
      </a>
      <a href="<?php echo $base_url; ?>/admin/appointments.php" class="sidenav-link <?php echo ($currentPage === 'appointments.php' && $currentDir === 'admin') ? 'active' : ''; ?>">
        <span class="nav-icon">📅</span>
        <span class="nav-text">Appointments</span>
      </a>
      <a href="<?php echo $base_url; ?>/admin/notifications.php" class="sidenav-link <?php echo ($currentPage === 'notifications.php' && $currentDir === 'admin') ? 'active' : ''; ?>">
        <span class="nav-icon">🔔</span>
        <span class="nav-text">Notifications</span>
        <?php if ($unreadCount > 0): ?>
          <span class="nav-badge"><?php echo $unreadCount; ?></span>
        <?php endif; ?>
      </a>
      <a href="<?php echo $base_url; ?>/admin/payments.php" class="sidenav-link <?php echo ($currentPage === 'payments.php' && $currentDir === 'admin') ? 'active' : ''; ?>">
        <span class="nav-icon">💳</span>
        <span class="nav-text">Payments</span>
      </a>
      <a href="<?php echo $base_url; ?>/admin/blocked_dates.php" class="sidenav-link <?php echo ($currentPage === 'blocked_dates.php' && $currentDir === 'admin') ? 'active' : ''; ?>">
        <span class="nav-icon">🚫</span>
        <span class="nav-text">Blocked Dates</span>
      </a>
      <a href="<?php echo $base_url; ?>/admin/settings.php" class="sidenav-link <?php echo ($currentPage === 'settings.php' && $currentDir === 'admin') ? 'active' : ''; ?>">
        <span class="nav-icon">⚙️</span>
        <span class="nav-text">Settings</span>
      </a>
    <?php endif; ?>
    <div class="sidenav-divider"></div>
    <a href="<?php echo $base_url; ?>/public/logout.php" class="sidenav-link logout-link">
      <span class="nav-icon">🚪</span>
      <span class="nav-text">Logout</span>
    </a>
  </nav>
</aside>
<?php endif; ?>

<style>
/* Notifications Styles */
.user-actions {
  display: flex;
  align-items: center;
  gap: 15px;
}

.notifications-menu {
  position: relative;
}

.notifications-btn {
  background: none;
  border: none;
  cursor: pointer;
  position: relative;
  padding: 8px;
  border-radius: 50%;
  transition: background-color 0.2s;
}

.notifications-btn:hover {
  background-color: rgba(255, 255, 255, 0.1);
}

.notifications-icon {
  width: 24px;
  height: 24px;
  filter: invert(1);
}

.notification-badge {
  position: absolute;
  top: 4px;
  right: 4px;
  background: #ef4444;
  color: white;
  border-radius: 50%;
  width: 18px;
  height: 18px;
  font-size: 11px;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
}

.notifications-dropdown {
  position: absolute;
  top: 100%;
  right: 0;
  width: 350px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  border: 1px solid #e5e7eb;
  display: none;
  z-index: 1000;
  max-height: 400px;
  overflow: hidden;
}

.notifications-header {
  padding: 15px;
  border-bottom: 1px solid #e5e7eb;
  background: #f9fafb;
}

.notifications-header h4 {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
}

.notifications-list {
  max-height: 300px;
  overflow-y: auto;
}

.notification-item {
  padding: 12px 15px;
  border-bottom: 1px solid #f3f4f6;
  cursor: pointer;
  transition: background-color 0.2s;
}

.notification-item:hover {
  background-color: #f9fafb;
}

.notification-item.unread {
  background-color: #fefce8;
  border-left: 3px solid #f59e0b;
}

.notification-item p {
  margin: 0 0 4px 0;
  font-size: 14px;
  color: #374151;
  line-height: 1.4;
}

.notification-item small {
  color: #6b7280;
  font-size: 12px;
}

.no-notifications {
  padding: 20px;
  text-align: center;
  color: #6b7280;
  font-style: italic;
}

/* Enhanced Sidebar Styles */
.sidenav {
  background: linear-gradient(180deg, #1f2937 0%, #374151 100%);
  color: white;
  width: 280px;
  height: 100vh;
  position: fixed;
  top: 0;
  left: 0;
  z-index: 1000;
  display: flex;
  flex-direction: column;
  box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
}

.sidenav.collapsed {
  width: 70px;
}

.sidenav-header {
  padding: 20px;
  text-align: center;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidenav-logo {
  width: 40px;
  height: 40px;
}

.sidenav-nav {
  flex: 1;
  padding: 20px 0;
  display: flex;
  flex-direction: column;
}

.sidebar-collapse-btn {
  background: none;
  border: none;
  color: white;
  padding: 10px;
  margin: 0 15px 20px 15px;
  cursor: pointer;
  border-radius: 8px;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.sidebar-collapse-btn:hover {
  background: rgba(255, 255, 255, 0.1);
}

.sidenav-link {
  display: flex;
  align-items: center;
  padding: 12px 20px;
  color: rgba(255, 255, 255, 0.8);
  text-decoration: none;
  transition: all 0.2s;
  position: relative;
  margin: 2px 10px;
  border-radius: 8px;
  font-weight: 500;
}

.sidenav-link:hover {
  background: rgba(255, 255, 255, 0.1);
  color: white;
  transform: translateX(5px);
}

.sidenav-link.active {
  background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
}

.sidenav-link.active::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  background: #60a5fa;
  border-radius: 0 2px 2px 0;
}

.nav-icon {
  font-size: 18px;
  width: 24px;
  text-align: center;
  margin-right: 12px;
  flex-shrink: 0;
}

.nav-text {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.nav-badge {
  background: #ef4444;
  color: white;
  border-radius: 50%;
  width: 20px;
  height: 20px;
  font-size: 11px;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-left: 8px;
  flex-shrink: 0;
}

.sidenav-divider {
  height: 1px;
  background: rgba(255, 255, 255, 0.1);
  margin: 20px 20px;
}

.logout-link {
  margin-top: auto;
  color: rgba(255, 255, 255, 0.6);
}

.logout-link:hover {
  background: rgba(239, 68, 68, 0.2);
  color: #fca5a5;
}

/* Collapsed sidebar styles */
.sidenav.collapsed .sidenav-header {
  padding: 15px;
}

.sidenav.collapsed .sidenav-logo {
  width: 30px;
  height: 30px;
}

.sidenav.collapsed .sidenav-link {
  padding: 12px;
  justify-content: center;
}

.sidenav.collapsed .nav-icon {
  margin-right: 0;
}

.sidenav.collapsed .nav-text,
.sidenav.collapsed .nav-badge {
  display: none;
}

.sidenav.collapsed .sidebar-collapse-btn {
  margin: 0 10px 20px 10px;
}

/* Mobile sidebar */
.sidenav-toggle {
  display: none;
  position: fixed;
  top: 15px;
  left: 15px;
  z-index: 1001;
  background: #3b82f6;
  border: none;
  border-radius: 6px;
  padding: 8px;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.sidenav-overlay {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  z-index: 999;
}

@media (max-width: 768px) {
  .sidenav {
    transform: translateX(-100%);
    transition: transform 0.3s ease;
  }

  .sidenav.open {
    transform: translateX(0);
  }

  .sidenav-toggle {
    display: block;
  }

  .sidenav-overlay {
    display: block;
  }

  .site-main.with-sidenav {
    margin-left: 0;
  }
}
</style>

<script>
// Notifications dropdown toggle
function toggleNotifications() {
  const dropdown = document.getElementById('notifications-dropdown');
  const profileDropdown = document.getElementById('profile-dropdown');

  // Close profile dropdown if open
  if (profileDropdown && profileDropdown.style.display === 'block') {
    profileDropdown.style.display = 'none';
  }

  // Toggle notifications dropdown
  dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
  const notificationsMenu = event.target.closest('.notifications-menu');
  const profileMenu = event.target.closest('.profile-menu');

  if (!notificationsMenu) {
    const dropdown = document.getElementById('notifications-dropdown');
    if (dropdown) dropdown.style.display = 'none';
  }

  if (!profileMenu) {
    const dropdown = document.getElementById('profile-dropdown');
    if (dropdown) dropdown.style.display = 'none';
  }
});

// Mark notification as read when clicked
document.addEventListener('click', function(event) {
  if (event.target.closest('.notification-item')) {
    const item = event.target.closest('.notification-item');
    const notificationId = item.dataset.id;

    if (notificationId && item.classList.contains('unread')) {
      // Mark as read via AJAX (optional enhancement)
      item.classList.remove('unread');
      item.classList.add('read');

      // Update badge count
      const badge = document.querySelector('.notification-badge');
      if (badge) {
        let count = parseInt(badge.textContent) - 1;
        if (count <= 0) {
          badge.style.display = 'none';
        } else {
          badge.textContent = count;
        }
      }
    }
  }
});

// Sidebar functionality
function toggleSidebar() {
  const sidenav = document.querySelector('.sidenav');
  const overlay = document.querySelector('.sidenav-overlay');
  const toggleBtn = document.querySelector('.sidenav-toggle');
  if (sidenav) {
    sidenav.classList.toggle('open');
    if (toggleBtn) toggleBtn.classList.toggle('open');
    if (overlay) {
      overlay.style.display = overlay.style.display === 'block' ? 'none' : 'block';
    }
  }
}

function closeSidebar() {
  const sidenav = document.querySelector('.sidenav');
  const overlay = document.querySelector('.sidenav-overlay');
  const toggleBtn = document.querySelector('.sidenav-toggle');
  if (sidenav) {
    sidenav.classList.remove('open');
    if (toggleBtn) toggleBtn.classList.remove('open');
    if (overlay) {
      overlay.style.display = 'none';
    }
  }
}

// Initialize sidebar state on page load
document.addEventListener('DOMContentLoaded', function() {
  const savedState = localStorage.getItem('sidebarCollapsed');
  if (savedState === 'true') {
    toggleSidebarCollapse();
  }
});
</script>

<!-- Main content area -->
<main class="site-main<?php echo $role ? ' with-sidenav' : ''; ?><?php echo $role ? ' with-sidebar' : ''; ?>">
