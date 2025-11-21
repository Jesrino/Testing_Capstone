<?php
session_start();
require 'config.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch available services
$services = $conn->query("SELECT * FROM services ORDER BY name ASC");

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $user_id = $_SESSION['user_id']; // logged-in user
    $service_id = $_POST['service_id'];
    $booking_date = $_POST['booking_date'];
    $booking_time = $_POST['booking_time'];
    $status = 'Pending'; // default status

    $stmt = $conn->prepare("INSERT INTO bookings (user_id, service_id, booking_date, booking_time, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $user_id, $service_id, $booking_date, $booking_time, $status);

    if ($stmt->execute()) {
        $success = "Booking successfully created!";
    } else {
        $error = "Failed to create booking.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Book a Service</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">

    <h2>Hello, <?= htmlspecialchars($_SESSION['username']) ?>! Book a Service</h2>

    <?php if(isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <?php if(isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST">

        <div class="mb-3">
            <label class="form-label">Service</label>
            <select name="service_id" class="form-control" required>
                <option value="">Select a service</option>
                <?php while($s = $services->fetch_assoc()): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> - ₱<?= number_format($s['price']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Date</label>
            <input type="date" name="booking_date" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Time</label>
            <input type="time" name="booking_time" class="form-control" required>
        </div>

        <button class="btn btn-primary">Book Now</button>
        <a href="logout.php" class="btn btn-secondary">Logout</a>
    </form>

</div>

</body>
</html>
