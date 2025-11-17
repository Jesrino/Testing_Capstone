<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$id = intval($_GET['id']);
$stmt = $conn->prepare("SELECT * FROM services WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$service = $stmt->get_result()->fetch_assoc();

if (!$service) {
    die("Service not found.");
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $price = floatval($_POST["price"]);

    if ($name && $price > 0) {

        $update = $conn->prepare("UPDATE services SET name = ?, price = ? WHERE id = ?");
        $update->bind_param("sdi", $name, $price, $id);

        if ($update->execute()) {
            header("Location: services.php");
            exit;
        } else {
            $error = "Error updating service.";
        }

    } else {
        $error = "All fields are required.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Service</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-4">

    <h3>Edit Service</h3>

    <div class="card mt-3">
        <div class="card-body">

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">

                <div class="mb-3">
                    <label class="form-label">Service Name</label>
                    <input type="text" name="name" value="<?= $service['name'] ?>" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Price (₱)</label>
                    <input type="number" name="price" value="<?= $service['price'] ?>" class="form-control" required step="0.01">
                </div>

                <button class="btn btn-primary">Update</button>
                <a href="services.php" class="btn btn-secondary">Cancel</a>

            </form>

        </div>
    </div>

</div>

</body>
</html>
