<?php
require 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$id = $_GET['id'];

$stmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
$stmt->execute([$id]);

header("Location: bookings.php");
exit;
