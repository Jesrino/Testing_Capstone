<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/Appointments.php';
require_once __DIR__ . '/../models/payments.php';

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
        $role = $_SESSION['role'];

        // Allow admins and dentists to view others' appointments
        if ($role !== 'admin' && $role !== 'dentist' && $userId !== $_SESSION['user_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit();
        }

        if ($role === 'client') {
            $appointments = listClientAppointments($userId);
        } else {
            // For dentists and admins, get appointments where they are the dentist
            global $pdo;
            $stmt = $pdo->prepare("SELECT * FROM Appointments WHERE dentistId = ? ORDER BY date DESC, time DESC");
            $stmt->execute([$userId]);
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $appointmentsArray = [];
        foreach ($appointments as $appt) {
            $appointmentsArray[] = [
                'id' => $appt['id'],
                'clientId' => $appt['clientId'],
                'dentistId' => $appt['dentistId'],
                'date' => $appt['date'],
                'time' => $appt['time'],
                'status' => $appt['status'],
                'createdAt' => $appt['createdAt']
            ];
        }

        echo json_encode(['appointments' => $appointmentsArray]);
        break;

    case 'POST':
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit();
        }

        $required = ['clientId', 'dentistId', 'date', 'time'];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Missing required field: $field"]);
                exit();
            }
        }

        $selectedTreatments = $input['treatments'] ?? [];
        $treatmentId = !empty($selectedTreatments) ? $selectedTreatments[0] : null;
        $result = createAppointment($input['clientId'], $input['dentistId'], $input['date'], $input['time'], $treatmentId, null, null);

        if ($result && !empty($selectedTreatments)) {
            // Add additional treatments to junction table
            global $pdo;
            foreach ($selectedTreatments as $treatmentId) {
                $stmt = $pdo->prepare("INSERT INTO AppointmentTreatments (appointmentId, treatmentId) VALUES (?, ?)");
                $stmt->execute([$result, $treatmentId]);
            }

            // Calculate total amount and create payment
            $totalStmt = $pdo->prepare("SELECT SUM(t.price) as total FROM Treatments t JOIN AppointmentTreatments at ON t.id = at.treatmentId WHERE at.appointmentId = ?");
            $totalStmt->execute([$result]);
            $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];

            if ($total > 0) {
                logPayment($result, $total, 'bank', 'pending');
            }
        }
        if ($result) {
            http_response_code(201);
            echo json_encode(['message' => 'Appointment created successfully', 'id' => $result]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create appointment']);
        }
        break;

    case 'PUT':
        // Update appointment status
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit();
        }

        $appointmentId = $_GET['id'] ?? null;
        if (!$appointmentId) {
            http_response_code(400);
            echo json_encode(['error' => 'Appointment ID required']);
            exit();
        }

        if (!isset($input['status'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Status field required']);
            exit();
        }

        global $pdo;
        $stmt = $pdo->prepare("UPDATE Appointments SET status = ? WHERE id = ?");
        $result = $stmt->execute([$input['status'], $appointmentId]);

        if ($result) {
            echo json_encode(['message' => 'Appointment updated successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Appointment not found']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
?>
