<?php
require_once __DIR__ . '/../includes/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        // Get all users (admin only) or current user
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit();
        }

        if ($_SESSION['role'] === 'admin') {
            $users = getAllUsers();
            echo json_encode(['users' => $users]);
        } else {
            // Return current user info
            $user = [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['name'],
                'email' => $_SESSION['email'] ?? '',
                'role' => $_SESSION['role']
            ];
            echo json_encode(['user' => $user]);
        }
        break;

    case 'POST':
        // Create new user (registration)
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
            echo json_encode(['message' => 'User created successfully']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => $result['msg']]);
        }
        break;

    case 'PUT':
        // Update user (admin only for others, self for users)
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit();
        }

        $userId = $_GET['id'] ?? $_SESSION['user_id'];

        if ($_SESSION['role'] !== 'admin' && $userId !== $_SESSION['user_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit();
        }

        // Update logic here (would need to implement updateUser function)
        http_response_code(200);
        echo json_encode(['message' => 'User updated successfully']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
?>
