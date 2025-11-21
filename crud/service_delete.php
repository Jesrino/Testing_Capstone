<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("DELETE FROM services WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: services.php");
exit;
