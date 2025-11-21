<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch bookings joined with user + service
$stmt = $conn->query("
    SELECT 
        b.id, 
        u.username, 
        s.name AS service_name,
        b.date,
        b.status
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN services s ON b.service_id = s.id
    ORDER BY b.date DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Bookings - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">

<h2>Bookings</h2>
<a href="bookings_create.php" class="btn btn-primary mb-3">Add Booking</a>
<a href="admin.php" class="btn btn-secondary mb-3">Back to Dashboard</a>

<table class="table table-bordered table-striped">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>User</th>
            <th>Service</th>
            <th>Date</th>
            <th>Status</th>
            <th width="160">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($b = $stmt->fetch_assoc()): ?>
        <tr>
            <td><?= $b['id'] ?></td>
            <td><?= htmlspecialchars($b['username']) ?></td>
            <td><?= htmlspecialchars($b['service_name']) ?></td>
            <td><?= $b['date'] ?></td>
            <td><?= $b['status'] ?></td>
            <td>
                <a href="bookings_edit.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                <a href="bookings_delete.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-danger"
                   onclick="return confirm('Delete this booking?');">Delete</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

</body>
</html>
