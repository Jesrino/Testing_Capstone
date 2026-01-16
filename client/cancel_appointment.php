<?php
require_once "../includes/guards.php";
require_once "../includes/db.php";
require_once "../models/Appointments.php";
requireRole('client');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'])) {
    $appointmentId = $_POST['appointment_id'];
    $clientId = $_SESSION['user_id'];

    // Get appointment details before cancelling for notification
    $stmt = $pdo->prepare("SELECT date, time FROM Appointments WHERE id = ? AND clientId = ? AND status = 'pending'");
    $stmt->execute([$appointmentId, $clientId]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($appointment) {
        // Cancel the appointment
        $updateStmt = $pdo->prepare("UPDATE Appointments SET status = 'cancelled' WHERE id = ? AND clientId = ? AND status = 'pending'");
        $result = $updateStmt->execute([$appointmentId, $clientId]);

        if ($result) {
            // Notify admin about cancellation
            $adminStmt = $pdo->prepare("SELECT id FROM Users WHERE role = 'admin'");
            $adminStmt->execute();
            $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($admins as $admin) {
                createNotification($admin['id'], 'appointment_cancelled', "Client cancelled appointment for {$appointment['date']} at {$appointment['time']}");
            }

            $success = "Appointment cancelled successfully.";
        } else {
            $error = "Failed to cancel appointment.";
        }
    } else {
        $error = "Appointment not found or cannot be cancelled.";
    }
}

header("Location: appointments.php" . (isset($success) ? "?success=" . urlencode($success) : (isset($error) ? "?error=" . urlencode($error) : "")));
exit();
?>
