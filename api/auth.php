<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'POST':
        $action = $_GET['action'] ?? '';

        switch ($action) {
            case 'login':
                $required = ['email', 'password'];
                foreach ($required as $field) {
                    if (!isset($input[$field])) {
                        http_response_code(400);
                        echo json_encode(['error' => "Missing required field: $field"]);
                        exit();
                    }
                }

                $success = loginUser($input['email'], $input['password']);
                if ($success) {
                    echo json_encode([
                        'message' => 'Login successful',
                        'user' => [
                            'id' => $_SESSION['user_id'],
                            'name' => $_SESSION['name'],
                            'role' => $_SESSION['role']
                        ]
                    ]);
                } else {
                    http_response_code(401);
                    echo json_encode(['error' => 'Invalid credentials']);
                }
                break;

            case 'register':
                $required = ['name', 'email', 'password', 'role'];
                foreach ($required as $field) {
                    if (!isset($input[$field])) {
                        http_response_code(400);
                        echo json_encode(['error' => "Missing required field: $field"]);
                        exit();
                    }
                }

                $result = registerUser($input['name'], $input['email'], $input['password'], $input['role']);
                if ($result['ok']) {
                    http_response_code(201);
                    echo json_encode(['message' => 'Registration successful']);
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => $result['msg']]);
                }
                break;

            case 'logout':
                logoutUser();
                echo json_encode(['message' => 'Logout successful']);
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
                break;
        }
        break;

    case 'GET':
        // Check if user is logged in
        if (isset($_SESSION['user_id'])) {
            echo json_encode([
                'authenticated' => true,
                'user' => [
                    'id' => $_SESSION['user_id'],
                    'name' => $_SESSION['name'],
                    'role' => $_SESSION['role']
                ]
            ]);
        } else {
            echo json_encode(['authenticated' => false]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
?>
