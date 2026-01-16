<?php
$config = require __DIR__ . '/../config/config.php';

try {
    // Connect without specifying database to create it
    $pdo_temp = new PDO(
        "mysql:host={$config['mysql_host']};charset=utf8mb4",
        $config['mysql_user'],
        $config['mysql_password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    // Create the database if it doesn't exist
    $pdo_temp->exec("CREATE DATABASE IF NOT EXISTS {$config['mysql_dbname']}");

    // Close the temporary connection
    $pdo_temp = null;

    // Now connect to the specific database
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

    // Read the schema file
    $schema = file_get_contents(__DIR__ . '/../schema.sql');

    // Split into individual statements
    $statements = array_filter(array_map('trim', explode(';', $schema)));

    // Skip the CREATE DATABASE and USE statements since DB is already created and selected
    $skip_statements = ['CREATE DATABASE IF NOT EXISTS dents_city', 'USE dents_city'];

    foreach ($statements as $statement) {
        $trimmed = trim($statement);
        if (!empty($trimmed) && !in_array($trimmed, $skip_statements) && !str_starts_with($trimmed, '--')) {
            $pdo->exec($statement);
        }
    }

    // Now insert initial users
    $insert_sql = file_get_contents(__DIR__ . '/../sql/insert_users.sql');
    $insert_statements = array_filter(array_map('trim', explode(';', $insert_sql)));

    foreach ($insert_statements as $statement) {
        $trimmed = trim($statement);
        if (!empty($trimmed) && !str_starts_with($trimmed, '--')) {
            $pdo->exec($statement);
        }
    }

    echo "Database schema and initial users created successfully!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
