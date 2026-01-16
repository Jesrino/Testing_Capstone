-- MySQL schema for dents_city database

CREATE DATABASE IF NOT EXISTS dents_city;
USE dents_city;

-- Users table
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
) ENGINE=InnoDB;

-- Treatments table
CREATE TABLE IF NOT EXISTS Treatments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  price DECIMAL(10,2),
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Appointments table
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
) ENGINE=InnoDB;

-- Payments table
CREATE TABLE IF NOT EXISTS Payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  appointmentId INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  method ENUM('gcash', 'maya', 'gotyme', 'bank') NOT NULL,
  status ENUM('pending', 'confirmed', 'failed') NOT NULL DEFAULT 'pending',
  transactionId VARCHAR(255),
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (appointmentId) REFERENCES Appointments(id) ON DELETE CASCADE
);

-- Appointment Treatments junction table (for multiple treatments per appointment)
CREATE TABLE IF NOT EXISTS AppointmentTreatments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  appointmentId INT NOT NULL,
  treatmentId INT NOT NULL,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (appointmentId) REFERENCES Appointments(id) ON DELETE CASCADE,
  FOREIGN KEY (treatmentId) REFERENCES Treatments(id) ON DELETE CASCADE,
  UNIQUE KEY unique_appointment_treatment (appointmentId, treatmentId)
) ENGINE=InnoDB;

-- Notifications table
CREATE TABLE IF NOT EXISTS Notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  userId INT NOT NULL,
  type ENUM('appointment_booked', 'dentist_assigned', 'status_updated', 'appointment_cancelled', 'appointment_missed') NOT NULL,
  message TEXT NOT NULL,
  isRead BOOLEAN NOT NULL DEFAULT FALSE,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (userId) REFERENCES Users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
