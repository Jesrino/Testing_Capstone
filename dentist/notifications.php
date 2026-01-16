<?php
require_once "../includes/guards.php";
if (role() !== 'dentist' && role() !== 'dentist_pending') { header('Location: /public/login.php'); exit; }
require_once "../models/Notifications.php";

$dentistId = $_SESSION['user_id'];

$sampleNotifications = [
    [
        'id' => 1,
        'message' => 'Your dentist account has been successfully created. You can now manage appointments, view patient records, and provide quality dental care through the system',
        'type' => 'appointment_booked',
        'isRead' => false,
        'createdAt' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
        'isSample' => true
    ]
];

$realNotifications = getUserNotifications($dentistId);
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

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $notificationId = $_POST['notification_id'];
    global $pdo;
    $stmt = $pdo->prepare("UPDATE Notifications SET isRead = TRUE WHERE id = ? AND userId = ?");
    $stmt->execute([$notificationId, $dentistId]);
    if (!$hasReal && $notificationId == 1) {
        if (!isset($_SESSION['sample_read_ids'])) $_SESSION['sample_read_ids'] = [];
        $_SESSION['sample_read_ids'][] = $notificationId;
    }
    header("Location: notifications.php");
    exit;
}

// Handle mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE Notifications SET isRead = TRUE WHERE userId = ? AND isRead = FALSE");
    $stmt->execute([$dentistId]);
    if (!$hasReal) {
        $_SESSION['sample_read_ids'] = [1];
    }
    header("Location: notifications.php");
    exit;
}

include("../includes/header.php");

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

// Group notifications by type for better organization
$unreadByType = [];
$readByType = [];

foreach ($unreadNotifications as $notification) {
    $type = $notification['type'];
    if (!isset($unreadByType[$type])) {
        $unreadByType[$type] = [];
    }
    $unreadByType[$type][] = $notification;
}

foreach ($readNotifications as $notification) {
    $type = $notification['type'];
    if (!isset($readByType[$type])) {
        $readByType[$type] = [];
    }
    $readByType[$type][] = $notification;
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

            <?php foreach ($unreadByType as $type => $typeNotifications): ?>
                <div class="notification-group">
                    <h3><?php echo ucwords(str_replace('_', ' ', $type)); ?> (<?php echo count($typeNotifications); ?>)</h3>
                    <div class="notifications-list">
                        <?php foreach ($typeNotifications as $notification): ?>
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
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Read Notifications -->
    <?php if (count($readNotifications) > 0): ?>
        <div class="notifications-section">
            <h2>Notification History</h2>

            <?php foreach ($readByType as $type => $typeNotifications): ?>
                <div class="notification-group">
                    <h3><?php echo ucwords(str_replace('_', ' ', $type)); ?> (<?php echo count($typeNotifications); ?>)</h3>
                    <div class="notifications-list">
                        <?php foreach ($typeNotifications as $notification): ?>
                            <div class="notification-card read">
                                <div class="notification-content">
                                    <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                    <small><?php echo date('M j, Y g:i A', strtotime($notification['createdAt'])); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
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
    background: #10b981;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
}

.btn-primary:hover {
    background: #059669;
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

.notification-group {
    margin-bottom: 30px;
}

.notification-group h3 {
    color: #374151;
    margin-bottom: 15px;
    font-size: 1rem;
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
    border-left: 4px solid #10b981;
    background: linear-gradient(90deg, #ecfdf5 0%, #ffffff 100%);
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

<?php include("../includes/footer.php"); ?>
