<?php
require_once "../includes/guards.php";
require_once "../includes/db.php";
require_once "../models/Notifications.php";

$clientId = $_SESSION['user_id'];

$sampleNotifications = [
    [
        'id' => 1,
        'message' => 'Your account has been successfully created. We’re excited to have you on board! You can now log in, explore features, and start making the most out of your experience. If you need any help, feel free to reach out to our support team.',
        'type' => 'appointment_booked',
        'isRead' => false,
        'createdAt' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        'isSample' => true
    ],
    [
        'id' => 2,
        'message' => 'New clinic hours: Open until 8 PM on weekdays',
        'type' => 'appointment_booked',
        'isRead' => true,
        'createdAt' => date('Y-m-d H:i:s', strtotime('-2 days')),
        'isSample' => true
    ]
];

$realNotifications = getUserNotifications($clientId);
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

// Handle mark as read - BEFORE header.php include
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $notificationId = $_POST['notification_id'];
    global $pdo;
    $stmt = $pdo->prepare("UPDATE Notifications SET isRead = TRUE WHERE id = ? AND userId = ?");
    $stmt->execute([$notificationId, $clientId]);
    if (!$hasReal && in_array($notificationId, [1,2])) {
        if (!isset($_SESSION['sample_read_ids'])) $_SESSION['sample_read_ids'] = [];
        $_SESSION['sample_read_ids'][] = $notificationId;
    }
    header("Location: notifications.php");
    exit;
}

// Handle mark all as read - BEFORE header.php include
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE Notifications SET isRead = TRUE WHERE userId = ? AND isRead = FALSE");
    $stmt->execute([$clientId]);
    if (!$hasReal) {
        $_SESSION['sample_read_ids'] = [1,2];
    }
    header("Location: notifications.php");
    exit;
}

include("../includes/header.php");
requireRole('client');

// Separate unread and read notifications
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

<div class="container">
    <h1>My Notifications</h1>

    <div class="notifications-summary">
        <div class="summary-card">
            <h3>Unread</h3>
            <p class="summary-number"><?php echo count($unreadNotifications); ?></p>
        </div>
        <div class="summary-card">
            <h3>Total</h3>
            <p class="summary-number"><?php echo count($notifications); ?></p>
        </div>
    </div>

    <?php if (count($unreadNotifications) > 0): ?>
        <div class="actions-bar">
            <form method="POST" style="display: inline;">
                <button type="submit" name="mark_all_read" class="btn-primary">Mark All as Read</button>
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
                            <small><?php echo date('M j, Y g:i A', strtotime($notification['createdAt'])); ?> • <?php echo ucfirst(str_replace('_', ' ', $notification['type'])); ?></small>
                        </div>
                        <div class="notification-actions">
                            <?php if (!isset($notification['isSample'])): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                <button type="submit" name="mark_read" class="btn-secondary">Mark as Read</button>
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
                        <div class="notification-content">
                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                            <small><?php echo date('M j, Y g:i A', strtotime($notification['createdAt'])); ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (count($notifications) === 0): ?>
        <div class="no-notifications">
            <p>You have no notifications yet.</p>
        </div>
    <?php endif; ?>
</div>

<style>
.notifications-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.summary-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e5e7eb;
    text-align: center;
}

.summary-card h3 {
    margin: 0 0 10px 0;
    color: #6b7280;
    font-size: 0.875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.summary-number {
    font-size: 2rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0;
}

.actions-bar {
    margin-bottom: 20px;
}

.btn-primary {
    background: #3b82f6;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
}

.btn-primary:hover {
    background: #2563eb;
}

.btn-secondary {
    background: #6b7280;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
}

.btn-secondary:hover {
    background: #4b5563;
}

.notifications-section {
    margin-bottom: 40px;
}

.notifications-section h2 {
    color: #1f2937;
    margin-bottom: 20px;
    font-size: 1.25rem;
}

.notifications-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.notification-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e5e7eb;
    padding: 20px;
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
    border-left: 4px solid #3b82f6;
    background: linear-gradient(90deg, #eff6ff 0%, #ffffff 100%);
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

.no-notifications {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.no-notifications p {
    color: #6b7280;
    font-size: 1.125rem;
    margin: 0;
}
</style>


