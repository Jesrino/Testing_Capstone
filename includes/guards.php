<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Calculate base URL early for redirects
if (!isset($base_url)) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $base_path = dirname($_SERVER['PHP_SELF']);
    $base_url = $protocol . "://" . $host . str_replace('\\', '/', dirname($base_path));
}

function requireRole($role) {
  global $base_url;
  if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
    header('Location: ' . $base_url . '/public/login.php');
    exit;
  }
}

function isLoggedIn() {
  return isset($_SESSION['user_id']);
}

function role() {
  return $_SESSION['role'] ?? null;
}
function requireLogin() {
  global $base_url;
  if (!isLoggedIn()) {
    header('Location: ' . $base_url . '/public/login.php');
    exit;
  }
}