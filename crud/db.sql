-- Database: booking_system
CREATE DATABASE IF NOT EXISTS booking_system;
USE booking_system;

-- ===========================
-- 1. USERS TABLE
-- ===========================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','customer') DEFAULT 'customer'
);

-- Insert default admin
INSERT INTO users (username, password, role)
VALUES ('admin', '$2y$10$abcdefghijklmnopqrstuv1234567890ABCDEabcdeABCDE12345', 'admin');
-- NOTE: Replace the hashed password later with a real hash.

-- ===========================
-- 2. SERVICES TABLE
-- ===========================
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    price DECIMAL(10,2) DEFAULT 0,
    description TEXT
);

-- Sample services
INSERT INTO services (name, price, description) VALUES
('Massage', 999.00, 'Relaxing body massage'),
('Home Service', 1299.00, 'Home visit massage'),
('Therapy', 1499.00, 'Therapeutic treatment session');

-- ===========================
-- 3. BOOKINGS TABLE
-- ===========================
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    service_id INT NOT NULL,
    date DATE NOT NULL,
    time TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
);
