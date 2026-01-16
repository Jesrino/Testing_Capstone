<?php
require_once __DIR__ . '/db.php';

function findUserByEmail($email) {
  global $pdo;
  $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
  $stmt->execute([strtolower($email)]);
  return $stmt->fetch();
}

function registerUser($name, $email, $password, $role) {
  global $pdo;
  $email = strtolower(trim($email));
  if (findUserByEmail($email)) return ['ok' => false, 'msg' => 'Email already taken'];

  $stmt = $pdo->prepare("INSERT INTO users (name, email, passwordHash, role, profileData, createdAt) VALUES (?, ?, ?, ?, ?, NOW())");
  $stmt->execute([
    trim($name),
    $email,
    password_hash($password, PASSWORD_BCRYPT),
    $role,
    json_encode([])
  ]);
  return ['ok' => true];
}

function loginUser($email, $password) {
  $user = findUserByEmail($email);
  if (!$user) return false;
  if (!password_verify($password, $user['passwordHash'])) return false;

  $_SESSION['user_id'] = $user['id'];
  $_SESSION['role'] = $user['role'];
  $_SESSION['name'] = $user['name'];
  return true;
}

function logoutUser() {
  session_start();
  session_destroy();
}
