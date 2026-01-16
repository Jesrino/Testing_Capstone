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
    echo "Database '{$config['mysql_dbname']}' created or already exists.<br>";

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

    // Create Users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS Users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            passwordHash VARCHAR(255) NOT NULL,
            role ENUM('client', 'dentist', 'dentist_pending', 'admin') NOT NULL DEFAULT 'client',
            createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            profileData JSON,
            phone VARCHAR(20),
            address TEXT,
            dateOfBirth DATE,
            gender ENUM('male', 'female', 'other'),
            emergencyContact VARCHAR(255),
            emergencyPhone VARCHAR(20),
            medicalHistory TEXT,
            allergies TEXT,
            currentMedications TEXT,
            lastVisit DATE,
            nextAppointment DATE
        ) ENGINE=InnoDB
    ");
    echo "Users table created.<br>";

    // Create Treatments table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS Treatments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            price DECIMAL(10,2),
            createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");
    echo "Treatments table created.<br>";

    // Create Appointments table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS Appointments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            clientId INT NULL,
            dentistId INT NULL,
            treatmentId INT NULL,
            date DATE NOT NULL,
            time TIME NOT NULL,
            status ENUM('pending', 'confirmed', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
            createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            walk_in_name VARCHAR(255),
            walk_in_phone VARCHAR(20),
            FOREIGN KEY (clientId) REFERENCES Users(id) ON DELETE SET NULL,
            FOREIGN KEY (dentistId) REFERENCES Users(id) ON DELETE SET NULL,
            FOREIGN KEY (treatmentId) REFERENCES Treatments(id) ON DELETE SET NULL
        ) ENGINE=InnoDB
    ");
    echo "Appointments table created.<br>";

    // Create other tables...
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS Payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            appointmentId INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            method ENUM('gcash', 'maya', 'gotyme', 'bank') NOT NULL,
            status ENUM('pending', 'confirmed', 'failed') NOT NULL DEFAULT 'pending',
            transactionId VARCHAR(255),
            createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (appointmentId) REFERENCES Appointments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");
    echo "Payments table created.<br>";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS AppointmentTreatments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            appointmentId INT NOT NULL,
            treatmentId INT NOT NULL,
            createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (appointmentId) REFERENCES Appointments(id) ON DELETE CASCADE,
            FOREIGN KEY (treatmentId) REFERENCES Treatments(id) ON DELETE CASCADE,
            UNIQUE KEY unique_appointment_treatment (appointmentId, treatmentId)
        ) ENGINE=InnoDB
    ");
    echo "AppointmentTreatments table created.<br>";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS Notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            userId INT NOT NULL,
            type ENUM('appointment_booked', 'dentist_assigned', 'status_updated', 'appointment_cancelled', 'appointment_missed') NOT NULL,
            message TEXT NOT NULL,
            isRead BOOLEAN NOT NULL DEFAULT FALSE,
            createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (userId) REFERENCES Users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");
    echo "Notifications table created.<br>";

    // Insert sample treatments
    $pdo->exec("
        INSERT IGNORE INTO Treatments (id, name, description, price) VALUES
        (1, 'Dental Cleaning', 'Professional teeth cleaning and polishing', 1500.00),
        (2, 'Dental Filling', 'Restoration of tooth decay with filling material', 2500.00),
        (3, 'Root Canal Treatment', 'Treatment of infected tooth pulp', 8000.00),
        (4, 'Tooth Extraction', 'Removal of damaged or problematic teeth', 2000.00),
        (5, 'Teeth Whitening', 'Professional teeth whitening treatment', 3500.00)
    ");
    echo "Sample treatments inserted.<br>";

    // Insert admin user
    $adminPassword = password_hash('admin123', PASSWORD_BCRYPT);
    $pdo->exec("
        INSERT IGNORE INTO Users (id, name, email, passwordHash, role) VALUES
        (1, 'Admin User', 'admin@dentscity.com', '$adminPassword', 'admin')
    ");
    echo "Admin user created (email: admin@dentscity.com, password: admin123).<br>";

    echo "<br><strong>Database setup completed successfully!</strong><br>";
    echo "You can now register new users at: <a href='register.php'>Register</a><br>";
    echo "Or login with admin account: <a href='login.php'>Login</a>";

} catch (PDOException $e) {
    echo "Database setup failed: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "Make sure MySQL is running and the database credentials in config/config.php are correct.";
}
?>
