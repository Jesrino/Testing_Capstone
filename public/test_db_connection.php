<?php
echo "<h1>Database Connection Test</h1>";

$config = require __DIR__ . '/../config/config.php';

echo "<h2>Configuration:</h2>";
echo "Host: " . htmlspecialchars($config['mysql_host']) . "<br>";
echo "Database: " . htmlspecialchars($config['mysql_dbname']) . "<br>";
echo "User: " . htmlspecialchars($config['mysql_user']) . "<br>";
echo "Password: " . (empty($config['mysql_password']) ? "(empty)" : "(set)") . "<br><br>";

try {
    echo "<h2>Testing MySQL Connection (without database):</h2>";

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

    echo "<span style='color: green;'>✓ MySQL connection successful!</span><br><br>";

    // Try to create database
    echo "<h2>Creating Database:</h2>";
    $pdo_temp->exec("CREATE DATABASE IF NOT EXISTS {$config['mysql_dbname']}");
    echo "<span style='color: green;'>✓ Database '{$config['mysql_dbname']}' created or already exists.</span><br><br>";

    // Close temp connection
    $pdo_temp = null;

    // Test connection to specific database
    echo "<h2>Testing Connection to Database:</h2>";
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

    echo "<span style='color: green;'>✓ Database connection successful!</span><br><br>";

    // Test a simple query
    echo "<h2>Testing Simple Query:</h2>";
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    echo "<span style='color: green;'>✓ Query executed successfully: " . $result['test'] . "</span><br><br>";

    echo "<h2 style='color: green;'>All tests passed! Database is ready.</h2>";
    echo "<p><a href='setup_db_simple.php'>Click here to run the database setup</a></p>";
    echo "<p><a href='register.php'>Click here to test registration</a></p>";

} catch (PDOException $e) {
    echo "<span style='color: red;'>✗ Database connection failed: " . htmlspecialchars($e->getMessage()) . "</span><br><br>";

    echo "<h2>Troubleshooting Steps:</h2>";
    echo "<ol>";
    echo "<li>Make sure XAMPP is running (both Apache and MySQL)</li>";
    echo "<li>Check that MySQL service is started in XAMPP control panel</li>";
    echo "<li>Verify the database credentials in config/config.php are correct</li>";
    echo "<li>Try accessing phpMyAdmin at http://localhost/phpmyadmin to verify MySQL is working</li>";
    echo "</ol>";

    if (strpos($e->getMessage(), 'Connection refused') !== false) {
        echo "<p><strong>Most likely cause:</strong> MySQL is not running. Start MySQL in XAMPP.</p>";
    } elseif (strpos($e->getMessage(), 'Access denied') !== false) {
        echo "<p><strong>Most likely cause:</strong> Wrong username/password in config.php.</p>";
    }
}
?>
