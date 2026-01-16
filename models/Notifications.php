<?php
require_once __DIR__ . '/../includes/db.php';

function getUserNotifications($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM Notifications WHERE userId = ? ORDER BY createdAt DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function markNotificationAsRead($notificationId) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE Notifications SET isRead = TRUE WHERE id = ?");
    return $stmt->execute([$notificationId]);
}

function createNotification($userId, $type, $message) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO Notifications (userId, type, message, createdAt, isRead) VALUES (?, ?, ?, NOW(), FALSE)");
    return $stmt->execute([$userId, $type, $message]);
}
?>
