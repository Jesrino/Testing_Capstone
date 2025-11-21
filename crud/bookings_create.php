<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// ================= Fetch users =================
$usersResult = $conn->query("SELECT id, username FROM users ORDER BY username ASC");
$users = [];
while ($row = $usersResult->fetch_assoc()) {
    $users[] = $row;
}

// ================= Fetch services =================
$servicesResult = $conn->query("SELECT id, name FROM services ORDER BY name ASC");
$services = [];
while ($row = $servicesResult->fetch_assoc()) {
    $services[] = $row;
}

// ================= Handle POST =================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_POST['user_id'];
    $service_id = $_POST['service_id'];
    $datetime = $_POST['date']; // datetime-local input

    // Split datetime-local into date + time
    $date = date("Y-m-d", strtotime($datetime));
    $time = date("H:i:s", strtotime($datetime));

    $status = $_POST['status'];

    // Insert using mysqli
    $stmt = $conn->prepare("INSERT INTO bookings (user_id, service_id, date, time, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iisss", $user_id, $service_id, $date, $time, $status);
    $stmt->execute();

    header("Location: bookings.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Booking</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">

<h2>Add Booking</h2>

<form method="post">

    <label>User:</label>
    <select name="user_id" class="form-control mb-3" required>
        <option value="">Select User</option>
        <?php foreach ($users as $u): ?>
            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option>
        <?php endforeach; ?>
    </select>

    <label>Service:</label>
    <select name="service_id" class="form-control mb-3" required>
        <option value="">Select Service</option>
        <?php foreach ($services as $s): ?>
            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
        <?php endforeach; ?>
    </select>

    <label>Date & Time:</label>
    <input type="datetime-local" name="date" class="form-control mb-3" required>

    <label>Status:</label>
    <select name="status" class="form-control mb-3">
        <option>Pending</option>
        <option>Confirmed</option>
        <option>Completed</option>
        <option>Cancelled</option>
    </select>

    <button class="btn btn-success">Save</button>
    <a href="bookings.php" class="btn btn-secondary">Cancel</a>
</form>

</body>
</html>
