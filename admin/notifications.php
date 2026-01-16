<?php
require_once "../includes/guards.php";
requireRole('admin');
require_once "../models/Notifications.php";

$sampleNotifications = [
    [
        'id' => 1,
        'message' => 'Your administrator account has been successfully created. You now have full access to manage users, oversee system operations, and monitor platform activity.',
        'type' => 'appointment_booked',
        'isRead' => false,
        'createdAt' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        'isSample' => true
    ]
];

$userId = $_SESSION['user_id'];
$realNotifications = getUserNotifications($userId);
$hasReal = !empty($realNotifications);

if ($hasReal) {
    $notifications = $realNotifications;
} else {
    $notifications = $sampleNotifications;
    if (isset($_SESSION['sample_read_ids'])) {
        foreach ($notifications as &$n) {
            if (in_array($n['id'], $_SESSION['sample_read_ids'])) {
                $n['isRead'] = true;
            }
        }
        unset($_SESSION['sample_read_ids']);
    }
}

// Handle mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE Notifications SET isRead = TRUE WHERE userId = ? AND isRead = FALSE");
    $stmt->execute([$_SESSION['user_id']]);
    if (!$hasReal) {
        $_SESSION['sample_read_ids'] = [1];
    }
    header("Location: notifications.php");
    exit;
}

include("../includes/header.php");
$unreadNotifications = array_filter($notifications, function($n) { return !$n['isRead']; });
$readNotifications = array_filter($notifications, function($n) { return $n['isRead']; });

// Function to get icon based on type
function getNotificationIcon($type) {
    global $base_url;
    $icons = [
        'appointment' => 'appointment_icon.svg',
        'payment' => 'payments.svg',
        'user_registration' => 'people_icon.svg',
        'system' => 'info_icon.svg',
        'default' => 'info_icon.svg'
    ];
    return $base_url . '/assets/images/' . ($icons[$type] ?? $icons['default']);
}
?>

<!-- Dashboard Header -->
<div class="dashboard-header">
  <div class="welcome-section">
    <h1>Notifications</h1>
    <p>Stay updated with system activities</p>
  </div>
  <div class="user-avatar">
    <img src="<?php echo $base_url; ?>/assets/images/admin_logo.svg" alt="Admin Icon" style="width: 60px; height: 60px;">
  </div>
</div>

<div class="container">

<!-- Notifications Summary -->
<div class="notifications-summary">
  <div class="summary-card unread-card">
    <div class="summary-icon">
      <img src="<?php echo $base_url; ?>/assets/images/info_icon.svg" alt="Unread">
    </div>
    <div class="summary-content">
      <h3><?php echo count($unreadNotifications); ?></h3>
      <p>Unread</p>
    </div>
  </div>
  <div class="summary-card total-card">
    <div class="summary-icon">
      <img src="<?php echo $base_url; ?>/assets/images/list_icon.svg" alt="Total">
    </div>
    <div class="summary-content">
      <h3><?php echo count($notifications); ?></h3>
      <p>Total</p>
    </div>
  </div>
</div>

<?php if (count($unreadNotifications) > 0): ?>
  <div class="actions-bar">
    <form method="POST" style="display: inline;">
      <button type="submit" name="mark_all_read" class="btn-mark-all-read">Mark All as Read</button>
    </form>
  </div>
<?php endif; ?>

<!-- Unread Notifications -->
<?php if (count($unreadNotifications) > 0): ?>
  <div class="notifications-section">
    <h2>Unread Notifications</h2>
    <div class="notifications-list">
      <?php foreach ($unreadNotifications as $notification): ?>
        <div class="notification-card unread animate-slide-in">
          <div class="notification-icon">
            <img src="<?php echo getNotificationIcon($notification['type']); ?>" alt="Notification">
          </div>
          <div class="notification-content">
            <p><?php echo htmlspecialchars($notification['message']); ?></p>
            <small><?php echo date('M d, Y \a\t H:i', strtotime($notification['createdAt'])); ?> • <?php echo ucfirst(str_replace('_', ' ', $notification['type'])); ?></small>
          </div>
          <div class="notification-actions">
            <?php if (!isset($notification['isSample'])): ?>
            <form method="POST" action="<?php echo $base_url; ?>/api/notifications.php" style="display: inline;">
              <input type="hidden" name="action" value="mark_read">
              <input type="hidden" name="notificationId" value="<?php echo $notification['id']; ?>">
              <button type="submit" class="btn-mark-read">Mark as Read</button>
            </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<!-- Read Notifications -->
<?php if (count($readNotifications) > 0): ?>
  <div class="notifications-section">
    <h2>Previous Notifications</h2>
    <div class="notifications-list">
      <?php foreach ($readNotifications as $notification): ?>
        <div class="notification-card read">
          <div class="notification-icon">
            <img src="<?php echo getNotificationIcon($notification['type']); ?>" alt="Notification">
          </div>
          <div class="notification-content">
            <p><?php echo htmlspecialchars($notification['message']); ?></p>
            <small><?php echo date('M d, Y \a\t H:i', strtotime($notification['createdAt'])); ?> • <?php echo ucfirst(str_replace('_', ' ', $notification['type'])); ?></small>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

</div>

<style>
.notifications-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
  margin-bottom: 30px;
}

.summary-card {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
  display: flex;
  align-items: center;
  gap: 15px;
  transition: transform 0.2s ease;
}

.summary-card:hover {
  transform: translateY(-2px);
}

.unread-card {
  background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.total-card {
  background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.summary-icon img {
  width: 40px;
  height: 40px;
  filter: brightness(0) invert(1);
}

.summary-content h3 {
  margin: 0;
  font-size: 2rem;
  font-weight: 700;
}

.summary-content p {
  margin: 5px 0 0 0;
  font-size: 0.875rem;
  opacity: 0.9;
}

.actions-bar {
  margin-bottom: 20px;
}

.btn-mark-all-read {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
  padding: 12px 24px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  transition: all 0.2s ease;
  box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.btn-mark-all-read:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
}

.notifications-section {
  margin: 40px 0;
}

.notifications-section h2 {
  color: #1f2937;
  margin-bottom: 20px;
  font-size: 1.25rem;
  font-weight: 600;
}

.notifications-list {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.notification-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  border-left: 4px solid #e5e7eb;
  display: flex;
  align-items: center;
  gap: 15px;
  transition: all 0.2s ease;
  position: relative;
  overflow: hidden;
}

.notification-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.notification-card.unread {
  border-left-color: #3b82f6;
  background: linear-gradient(90deg, #f0f9ff 0%, #ffffff 100%);
}

.notification-card.read {
  opacity: 0.8;
}

.animate-slide-in {
  animation: slideIn 0.5s ease-out;
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateX(-20px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

.notification-icon img {
  width: 32px;
  height: 32px;
  flex-shrink: 0;
}

.notification-content {
  flex: 1;
}

.notification-content p {
  margin: 0 0 8px 0;
  font-size: 1rem;
  color: #1f2937;
  line-height: 1.5;
  font-weight: 500;
}

.notification-content small {
  color: #6b7280;
  font-size: 0.875rem;
  display: block;
}

.notification-actions {
  flex-shrink: 0;
}

.btn-mark-read {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  border: none;
  padding: 10px 18px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.875rem;
  font-weight: 500;
  transition: all 0.2s ease;
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.btn-mark-read:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.5);
}

.no-notifications {
  text-align: center;
  padding: 60px 20px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.no-notifications-icon img {
  width: 48px;
  height: 48px;
  opacity: 0.5;
  margin-bottom: 20px;
}

.no-notifications h3 {
  margin: 0 0 10px 0;
  color: #6b7280;
}

.no-notifications p {
  margin: 0;
  color: #9ca3af;
}
</style>

<?php include("../includes/footer.php"); ?>
