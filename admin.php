<?php
session_start();
require 'config.php';

// Block access if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// ==================== Fetch Bookings ====================
$bookings = $conn->query("
    SELECT b.id, u.username AS customer_name, s.name AS service_name, s.price,
       b.booking_datetime, b.status
FROM bookings b
JOIN users u ON b.user_id = u.id
JOIN services s ON b.service_id = s.id
ORDER BY b.booking_datetime DESC

");

// ==================== Fetch Services ====================
$services = $conn->query("SELECT * FROM services ORDER BY name ASC");

// ==================== Fetch Users ====================
$users = $conn->query("SELECT id, username, role FROM users ORDER BY username ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Booking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<nav class="navbar navbar-dark bg-primary">
    <div class="container">
        <span class="navbar-brand">Admin Dashboard</span>

        <span class="text-white">
            Logged in as <strong><?= $_SESSION['username'] ?></strong>
            |
            <a href="logout.php" class="text-white text-decoration-underline">Logout</a>
        </span>
    </div>
</nav>

<div class="container mt-4">

    <ul class="nav nav-tabs" id="adminTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#bookings" type="button">
                Bookings
            </button>
        </li>

        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#services" type="button">
                Services
            </button>
        </li>

        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#users" type="button">
                Users
            </button>
        </li>
    </ul>

    <div class="tab-content mt-3">

        <!-- ================= BOOKING TAB ================= -->
        <div class="tab-pane fade show active" id="bookings">

            <div class="d-flex justify-content-between mb-2">
                <h4>All Bookings</h4>
                <a href="bookings_create.php" class="btn btn-primary btn-sm">Add Booking</a>
            </div>

            <div class="card">
                <div class="card-body table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Customer</th>
                                <th>Service</th>
                                <th>Price</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php while ($row = $bookings->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $row['id'] ?></td>
                                    <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                    <td><?= htmlspecialchars($row['service_name']) ?></td>
                                    <td>₱<?= number_format($row['price']) ?></td>
                                    <td><?= $row['booking_date'] ?></td>
                                    <td><?= $row['booking_time'] ?></td>
                                    <td>
                                        <a href="bookings_edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                        <a href="bookings_delete.php?id=<?= $row['id'] ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Delete this booking?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>

                    </table>
                </div>
            </div>

        </div>

        <!-- ================= SERVICES TAB ================= -->
        <div class="tab-pane fade" id="services">
            
            <div class="d-flex justify-content-between mb-2">
                <h4>Services</h4>
                <a href="service_add.php" class="btn btn-primary btn-sm">Add Service</a>
            </div>

            <div class="card">
                <div class="card-body table-responsive">

                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Service Name</th>
                                <th>Price</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php while ($row = $services->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $row['id'] ?></td>
                                    <td><?= htmlspecialchars($row['name']) ?></td>
                                    <td>₱<?= number_format($row['price']) ?></td>
                                    <td>
                                        <a href="service_edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                        <a href="service_delete.php?id=<?= $row['id'] ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Delete this service?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>

                    </table>

                </div>
            </div>

        </div>

        <!-- ================= USERS TAB ================= -->
        <div class="tab-pane fade" id="users">
            
            <div class="d-flex justify-content-between mb-2">
                <h4>User Accounts</h4>
                <a href="user_add.php" class="btn btn-primary btn-sm">Add User</a>
            </div>

            <div class="card">
                <div class="card-body table-responsive">

                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php while ($row = $users->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $row['id'] ?></td>
                                    <td><?= htmlspecialchars($row['username']) ?></td>
                                    <td><?= ucfirst($row['role']) ?></td>
                                    <td>
                                        <a href="user_edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                        <a href="user_delete.php?id=<?= $row['id'] ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Delete this user?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>

                    </table>

                </div>
            </div>

        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
