<?php
require_once __DIR__ . '/../includes/db.php';

try {
    // Admin user details
    $name = 'admin';
    $email = 'admin@dents-city.com';
    $password = '12345678';
    $role = 'admin';

    // Hash the password
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    // Insert admin user
    $stmt = $pdo->prepare("INSERT INTO Users (name, email, passwordHash, role, createdAt, profileData) VALUES (?, ?, ?, ?, NOW(), ?)");
    $profileData = json_encode([]); // Empty profile data
    $stmt->execute([$name, $email, $passwordHash, $role, $profileData]);

    echo "Admin account created successfully!\n";
    echo "Email: admin@dents-city.com\n";
    echo "Password: 12345678\n";
    echo "Role: admin\n";

} catch (PDOException $e) {
    if ($e->getCode() == 23000) { // Duplicate entry
        echo "Admin account already exists.\n";
        echo "You can login with:\n";
        echo "Email: admin@dents-city.com\n";
        echo "Password: 12345678\n";
    } else {
        echo "Error creating admin account: " . $e->getMessage() . "\n";
    }
}
?>
