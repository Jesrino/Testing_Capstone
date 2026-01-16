<?php
require_once __DIR__ . '/../includes/db.php';

function approveDentist($userId) {
  global $pdo;
  $stmt = $pdo->prepare("UPDATE Users SET role = 'dentist' WHERE id = ?");
  $stmt->execute([$userId]);
}

function getAllUsers() {
  global $pdo;
  $stmt = $pdo->query("SELECT * FROM Users ORDER BY createdAt DESC");
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserById($userId) {
  global $pdo;
  $stmt = $pdo->prepare("SELECT * FROM Users WHERE id = ?");
  $stmt->execute([$userId]);
  return $stmt->fetch(PDO::FETCH_ASSOC);
}

function updateUserProfile($userId, $data) {
  global $pdo;
  $stmt = $pdo->prepare("UPDATE Users SET
    phone = ?, address = ?, dateOfBirth = ?, gender = ?,
    emergencyContact = ?, emergencyPhone = ?, medicalHistory = ?,
    allergies = ?, currentMedications = ?, lastVisit = ?, nextAppointment = ?
    WHERE id = ?");
  $stmt->execute([
    $data['phone'] ?? null,
    $data['address'] ?? null,
    $data['dateOfBirth'] ?? null,
    $data['gender'] ?? null,
    $data['emergencyContact'] ?? null,
    $data['emergencyPhone'] ?? null,
    $data['medicalHistory'] ?? null,
    $data['allergies'] ?? null,
    $data['currentMedications'] ?? null,
    $data['lastVisit'] ?? null,
    $data['nextAppointment'] ?? null,
    $userId
  ]);
}

function getPendingDentists() {
  global $pdo;
  $stmt = $pdo->query("SELECT * FROM Users WHERE role = 'dentist_pending' ORDER BY createdAt DESC");
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getApprovedDentists() {
  global $pdo;
  $stmt = $pdo->query("SELECT * FROM Users WHERE role = 'dentist' ORDER BY name ASC");
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getUserProfileData($userId) {
  global $pdo;
  $user = getUserById($userId);
  if ($user && !empty($user['profileData'])) {
    return json_decode($user['profileData'], true);
  }
  return [];
}

function updateUserProfileData($userId, $data) {
  global $pdo;
  $jsonData = json_encode($data);
  $stmt = $pdo->prepare("UPDATE Users SET profileData = ? WHERE id = ?");
  $stmt->execute([$jsonData, $userId]);
}