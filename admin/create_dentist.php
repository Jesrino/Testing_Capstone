<?php
session_start();
require_once "../includes/guards.php";
requireRole('admin');
require_once "../includes/auth.php";

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $confirmPassword = $_POST['confirm_password'] ?? '';

  // Validation
  if (empty($name) || empty($email) || empty($password)) {
    $message = 'All fields are required.';
    $messageType = 'error';
  } elseif ($password !== $confirmPassword) {
    $message = 'Passwords do not match.';
    $messageType = 'error';
  } elseif (strlen($password) < 6) {
    $message = 'Password must be at least 6 characters long.';
    $messageType = 'error';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $message = 'Please enter a valid email address.';
    $messageType = 'error';
  } else {
    $res = registerUser($name, $email, $password, 'dentist');
    if ($res['ok']) {
      $message = 'Dentist account created successfully!';
      $messageType = 'success';
    } else {
      $message = $res['msg'];
      $messageType = 'error';
    }
  }
}

// Redirect back to users page with message
header("Location: users.php?message=" . urlencode($message) . "&type=" . $messageType);
exit;
?>
