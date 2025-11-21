<?php
session_start();
require 'config.php';

// If user is already logged in → redirect to admin dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: admin.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    // Validate user
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $db_username, $db_password);
        $stmt->fetch();

        if (password_verify($password, $db_password)) {
            // Login OK → Set session
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $db_username;

            header("Location: admin.php");
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "User not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Booking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-4">

                <div class="card shadow">
                    <div class="card-header text-center bg-primary text-white">
                        <h4>Admin Login</h4>
                    </div>

                    <div class="card-body">

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>

                            <button class="btn btn-primary w-100">Login</button>
                        </form>

                    </div>

                </div>

            </div>
        </div>
    </div>
</body>
</html>
