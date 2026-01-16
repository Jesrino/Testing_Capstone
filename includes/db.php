<?php
$config = require __DIR__ . '/../config/config.php';

try {
  $pdo = new PDO(
    "mysql:host={$config['mysql_host']};dbname={$config['mysql_dbname']};charset=utf8mb4",
    $config['mysql_user'],
    $config['mysql_password'],
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]
  );
} catch (Throwable $e) {
  http_response_code(500);
  exit('Database connection error.');
}

// Set global $pdo for backward compatibility
global $pdo;
$pdo = $pdo;

return $pdo;
?>
