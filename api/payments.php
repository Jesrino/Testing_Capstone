<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/guards.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../models/payments.php';

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Define valid payment methods
define('VALID_PAYMENT_METHODS', ['gcash', 'maya', 'gotyme', 'bank', 'cash']);

switch ($action) {
    case 'get_appointment_bill':
        getAppointmentBill();
        break;
    case 'get_appointment_payments':
        getAppointmentPayments();
        break;
    case 'get_client_payments':
        getClientPayments();
        break;
    case 'create_payment':
        createPayment();
        break;
    case 'update_payment':
        updatePayment();
        break;
    case 'process_payment':
        processPayment();
        break;
    case 'get_payment_methods':
        getPaymentMethods();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

function getAppointmentPayments() {
    global $pdo;
    $appointmentId = $_GET['appointment_id'] ?? null;
    
    if (!$appointmentId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Appointment ID required']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM Payments WHERE appointmentId = ? ORDER BY createdAt DESC");
    $stmt->execute([$appointmentId]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'payments' => $payments]);
}

function getAppointmentBill() {
    global $pdo;
    $appointmentId = $_GET['appointment_id'] ?? null;
    if (!$appointmentId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Appointment ID required']);
        return;
    }

    // Verify ownership for clients
    $clientId = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT clientId FROM Appointments WHERE id = ?");
    $stmt->execute([$appointmentId]);
    $appt = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$appt) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Appointment not found']);
        return;
    }

    if ($appt['clientId'] != $clientId && !in_array($_SESSION['role'] ?? '', ['admin','dentist'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        return;
    }

    require_once __DIR__ . '/../models/payments.php';
    $bill = getBillForAppointment($appointmentId);
    $paid = getPaidAmountForAppointment($appointmentId);
    $outstanding = max(0, $bill['total'] - $paid);

    echo json_encode(['success' => true, 'bill' => $bill, 'paid' => $paid, 'outstanding' => $outstanding]);
}

function getClientPayments() {
    global $pdo;
    $clientId = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("
        SELECT p.*, a.date, a.time, 
               GROUP_CONCAT(t.name SEPARATOR ', ') as treatments
        FROM Payments p
        JOIN Appointments a ON p.appointmentId = a.id
        LEFT JOIN AppointmentTreatments apt ON a.id = apt.appointmentId
        LEFT JOIN Treatments t ON apt.treatmentId = t.id
        WHERE a.clientId = ?
        GROUP BY p.id
        ORDER BY p.createdAt DESC
    ");
    $stmt->execute([$clientId]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'payments' => $payments]);
}

function createPayment() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['appointmentId', 'amount', 'method', 'status'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
            return;
        }
    }

    // Validate payment method
    $method = strtolower($input['method']);
    if (!in_array($method, VALID_PAYMENT_METHODS)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid payment method. Valid methods: ' . implode(', ', VALID_PAYMENT_METHODS)]);
        return;
    }

    // Validate amount
    if ($input['amount'] <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Amount must be greater than 0']);
        return;
    }

    // Verify appointment exists
    $stmt = $pdo->prepare("SELECT id FROM Appointments WHERE id = ?");
    $stmt->execute([$input['appointmentId']]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Appointment not found']);
        return;
    }
    
    $id = logPayment(
        $input['appointmentId'],
        $input['amount'],
        $method,
        $input['status'],
        $input['transactionId'] ?? null
    );
    
    if ($id) {
        http_response_code(201);
        echo json_encode(['success' => true, 'message' => 'Payment created successfully', 'id' => $id]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create payment']);
    }
}

function updatePayment() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    
    $paymentId = $_GET['id'] ?? null;
    if (!$paymentId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Payment ID required']);
        return;
    }
    
    if (!isset($input['status'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Status field required']);
        return;
    }

    // Validate status
    $validStatuses = ['pending', 'confirmed', 'failed'];
    if (!in_array($input['status'], $validStatuses)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid status. Valid statuses: ' . implode(', ', $validStatuses)]);
        return;
    }
    
    $stmt = $pdo->prepare("UPDATE Payments SET status = ? WHERE id = ?");
    $result = $stmt->execute([$input['status'], $paymentId]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Payment updated successfully']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Payment not found']);
    }
}

function processPayment() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['appointmentId', 'amount', 'method'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
            return;
        }
    }

    // Validate payment method
    $method = strtolower($input['method']);
    if (!in_array($method, VALID_PAYMENT_METHODS)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid payment method. Valid methods: ' . implode(', ', VALID_PAYMENT_METHODS)]);
        return;
    }

    // Verify appointment exists and get details
    $stmt = $pdo->prepare("SELECT id, clientId FROM Appointments WHERE id = ?");
    $stmt->execute([$input['appointmentId']]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Appointment not found']);
        return;
    }

    // Verify ownership for clients
    if ($appointment['clientId'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return;
    }

    // Process payment based on method
    $paymentResult = processPaymentByMethod($method, $input);

    if ($paymentResult['success']) {
        // Log payment to database
        $paymentId = logPayment(
            $input['appointmentId'],
            $input['amount'],
            $method,
            'confirmed',
            $paymentResult['transactionId'] ?? null
        );

        echo json_encode([
            'success' => true,
            'message' => 'Payment processed successfully',
            'paymentId' => $paymentId,
            'method' => $method,
            'amount' => $input['amount'],
            'transactionId' => $paymentResult['transactionId'] ?? null,
            'methodDetails' => $paymentResult['details'] ?? null
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $paymentResult['error'],
            'method' => $method
        ]);
    }
}

function processPaymentByMethod($method, $input) {
    $method = strtolower($method);

    switch ($method) {
        case 'gcash':
            return processGCashPayment($input);
        case 'maya':
            return processMayaPayment($input);
        case 'gotyme':
            return processGoTymePayment($input);
        case 'bank':
            return processBankTransfer($input);
        default:
            return ['success' => false, 'error' => 'Unknown payment method'];
    }
}

function processGCashPayment($input) {
    // GCash payment processing
    // In production, integrate with GCash API
    $phoneNumber = $input['phoneNumber'] ?? null;
    $referenceNumber = $input['referenceNumber'] ?? null;

    if (!$phoneNumber) {
        return ['success' => false, 'error' => 'GCash phone number required'];
    }

    // Generate transaction ID
    $transactionId = 'GCASH-' . time() . '-' . uniqid();

    return [
        'success' => true,
        'transactionId' => $transactionId,
        'details' => [
            'method' => 'GCash',
            'phoneNumber' => $phoneNumber,
            'referenceNumber' => $referenceNumber,
            'message' => 'Payment sent to GCash account'
        ]
    ];
}

function processMayaPayment($input) {
    // Maya payment processing
    // In production, integrate with Maya API
    $accountEmail = $input['accountEmail'] ?? null;
    $referenceNumber = $input['referenceNumber'] ?? null;

    if (!$accountEmail) {
        return ['success' => false, 'error' => 'Maya account email required'];
    }

    $transactionId = 'MAYA-' . time() . '-' . uniqid();

    return [
        'success' => true,
        'transactionId' => $transactionId,
        'details' => [
            'method' => 'Maya',
            'accountEmail' => $accountEmail,
            'referenceNumber' => $referenceNumber,
            'message' => 'Payment sent to Maya account'
        ]
    ];
}

function processGoTymePayment($input) {
    // GoTyme payment processing
    // In production, integrate with GoTyme API
    $phoneNumber = $input['phoneNumber'] ?? null;
    $referenceNumber = $input['referenceNumber'] ?? null;

    if (!$phoneNumber) {
        return ['success' => false, 'error' => 'GoTyme phone number required'];
    }

    $transactionId = 'GOTYME-' . time() . '-' . uniqid();

    return [
        'success' => true,
        'transactionId' => $transactionId,
        'details' => [
            'method' => 'GoTyme',
            'phoneNumber' => $phoneNumber,
            'referenceNumber' => $referenceNumber,
            'message' => 'Payment sent to GoTyme account'
        ]
    ];
}

function processBankTransfer($input) {
    // Bank transfer processing
    $bankName = $input['bankName'] ?? null;
    $accountNumber = $input['accountNumber'] ?? null;
    $accountName = $input['accountName'] ?? null;

    if (!$bankName || !$accountNumber) {
        return ['success' => false, 'error' => 'Bank details required (bankName, accountNumber)'];
    }

    // Generate reference number for transfer
    $referenceNumber = 'BANK-' . time();

    return [
        'success' => true,
        'transactionId' => $referenceNumber,
        'details' => [
            'method' => 'Bank Transfer',
            'bankName' => $bankName,
            'accountNumber' => $accountNumber,
            'accountName' => $accountName,
            'referenceNumber' => $referenceNumber,
            'message' => 'Please transfer to the provided bank account with reference: ' . $referenceNumber
        ]
    ];
}

function getPaymentMethods() {
    $methods = [
        [
            'id' => 'gcash',
            'name' => 'GCash',
            'icon' => 'gcash-icon',
            'description' => 'Mobile payment via GCash',
            'fields' => ['phoneNumber', 'referenceNumber'],
            'isActive' => true
        ],
        [
            'id' => 'maya',
            'name' => 'Maya',
            'icon' => 'maya-icon',
            'description' => 'Payment through Maya wallet',
            'fields' => ['accountEmail', 'referenceNumber'],
            'isActive' => true
        ],
        [
            'id' => 'gotyme',
            'name' => 'GoTyme',
            'icon' => 'gotyme-icon',
            'description' => 'GoTyme digital banking payment',
            'fields' => ['phoneNumber', 'referenceNumber'],
            'isActive' => true
        ],
        [
            'id' => 'bank',
            'name' => 'Bank Transfer',
            'icon' => 'bank-icon',
            'description' => 'Direct bank account transfer',
            'fields' => ['bankName', 'accountNumber', 'accountName'],
            'isActive' => true
        ]
    ];

    echo json_encode(['success' => true, 'methods' => $methods]);
}

?>
