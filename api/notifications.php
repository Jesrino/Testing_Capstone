<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/Notifications.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit();
        }

        $userId = $_GET['user_id'] ?? $_SESSION['user_id'];
        $notifications = getUserNotifications($userId);

        echo json_encode(['notifications' => $notifications]);
        break;

    case 'POST':
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit();
        }

        $action = $_POST['action'] ?? null;
        if ($action === 'mark_read') {
            $notificationId = $_POST['notificationId'] ?? null;
            if (!$notificationId) {
                http_response_code(400);
                echo json_encode(['error' => 'Notification ID required']);
                exit();
            }

            // Verify the notification belongs to the current user
            global $pdo;
            $stmt = $pdo->prepare("SELECT userId FROM Notifications WHERE id = ?");
            $stmt->execute([$notificationId]);
            $notification = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$notification || $notification['userId'] != $_SESSION['user_id']) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden']);
                exit();
            }

            $result = markNotificationAsRead($notificationId);

            if ($result) {
                // Redirect back to notifications page based on role
                $role = $_SESSION['role'];
                if ($role === 'admin') {
                    header("Location: ../admin/notifications.php");
                } elseif ($role === 'client') {
                    header("Location: ../client/notifications.php");
                } else {
                    header("Location: ../dentist/notifications.php");
                }
                exit();
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update notification']);
            }
        } elseif ($action === 'mark_all_read') {
            // Mark all notifications as read for the current user
            global $pdo;
            $stmt = $pdo->prepare("UPDATE Notifications SET isRead = TRUE WHERE userId = ? AND isRead = FALSE");
            $result = $stmt->execute([$_SESSION['user_id']]);

            if ($result) {
                // Redirect back to notifications page based on role
                $role = $_SESSION['role'];
                if ($role === 'admin') {
                    header("Location: ../admin/notifications.php");
                } elseif ($role === 'client') {
                    header("Location: ../client/notifications.php");
                } else {
                    header("Location: ../dentist/notifications.php");
                }
                exit();
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update notifications']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
        }
        break;

    case 'PUT':
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit();
        }

        $notificationId = $input['id'] ?? null;
        if (!$notificationId) {
            http_response_code(400);
            echo json_encode(['error' => 'Notification ID required']);
            exit();
        }

        // Verify the notification belongs to the current user
        global $pdo;
        $stmt = $pdo->prepare("SELECT userId FROM Notifications WHERE id = ?");
        $stmt->execute([$notificationId]);
        $notification = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$notification || $notification['userId'] != $_SESSION['user_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit();
        }

        $result = markNotificationAsRead($notificationId);

        if ($result) {
            echo json_encode(['message' => 'Notification marked as read']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update notification']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
?>
