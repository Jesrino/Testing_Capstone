<?php
require_once "../includes/db.php";
require_once "../includes/auth.php";
require_once "../includes/guards.php";
require_once "../models/users.php";

header('Content-Type: application/json');

if (!isLoggedIn() || !in_array($_SESSION['role'], ['admin', 'dentist', 'dentist_pending'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_patients':
        getPatients();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}

function getPatients() {
    global $pdo;

    // Get registered patients with their last appointment date
    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.name,
            u.email,
            u.phone,
            u.createdAt as registration_date,
            (SELECT MAX(a.date) FROM Appointments a WHERE a.clientId = u.id) as last_visit,
            'registered' as type
        FROM Users u
        WHERE u.role = 'client'
        ORDER BY u.createdAt DESC
    ");
    $stmt->execute();
    $registeredPatients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get walk-in patients with their appointment dates
    $stmt = $pdo->prepare("
        SELECT
            CONCAT('walkin_', MIN(id)) as id,
            walk_in_name as name,
            walk_in_phone as phone,
            MAX(date) as last_visit,
            MIN(date) as registration_date,
            'walkin' as type,
            NULL as email
        FROM Appointments
        WHERE clientId IS NULL AND walk_in_name IS NOT NULL
        GROUP BY walk_in_name, walk_in_phone
        ORDER BY MAX(date) DESC
    ");
    $stmt->execute();
    $walkinPatients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $allPatients = array_merge($registeredPatients, $walkinPatients);

    // Group by month/year if requested
    $groupBy = $_GET['group_by'] ?? null;
    if ($groupBy === 'month') {
        $grouped = [];
        foreach ($allPatients as $patient) {
            $date = $patient['last_visit'] ?? $patient['registration_date'];
            if ($date) {
                $monthYear = date('F Y', strtotime($date));
            } else {
                $monthYear = 'No Date';
            }
            if (!isset($grouped[$monthYear])) {
                $grouped[$monthYear] = [];
            }
            $grouped[$monthYear][] = $patient;
        }
        echo json_encode(['success' => true, 'patients' => $grouped]);
    } else {
        echo json_encode(['success' => true, 'patients' => $allPatients]);
    }
}
?>
