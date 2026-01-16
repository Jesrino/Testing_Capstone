<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database connection
require_once __DIR__ . '/../includes/db.php';

// Get the request method and path
$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];

// Remove query string and decode URL
$path = parse_url($request, PHP_URL_PATH);
$path = str_replace('/api', '', $path); // Remove /api prefix
$path = trim($path, '/');

// Split path into segments
$segments = explode('/', $path);
$endpoint = $segments[0] ?? '';

// Route to appropriate handler
switch ($endpoint) {
    case 'users':
        require_once __DIR__ . '/users.php';
        break;
    case 'appointments':
        require_once __DIR__ . '/appointments.php';
        break;
    case 'payments':
        require_once __DIR__ . '/payments.php';
        break;
    case 'services':
        require_once __DIR__ . '/services.php';
        break;
    case 'auth':
        require_once __DIR__ . '/auth.php';
        break;
    case 'notifications':
        require_once __DIR__ . '/notifications.php';
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}
?>
