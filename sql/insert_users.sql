-- Manual INSERT commands for admin and dentist accounts
-- Run these after creating the database schema

-- Insert admin user
INSERT INTO Users (name, email, passwordHash, role) VALUES
('Admin User', 'admin@dentscity.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Admin: admin@dentscity.com / password
-- dentist@dentscity.com / password

-- Insert dentist user
INSERT INTO Users (name, email, passwordHash, role) VALUES
('Dr. John Doe', 'dentist@dentscity.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dentist');
