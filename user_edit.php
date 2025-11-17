<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) { die("User not found."); }

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST["username"]);
    $role = $_POST["role"];
    $new_password = trim($_POST["password"]);

    if ($username && $role) {

        if ($new_password != "") {
            // Update username + role + password
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET username = ?, role = ?, password = ? WHERE id = ?");
            $update->bind_param("sssi", $username, $role, $hashed, $id);

        } else {
            // Update username + role only
            $update = $conn->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
            $update->bind_param("ssi", $username, $role, $id);
        }

        if ($update->execute()) {
            header("Location: users.php");
            exit;
        } else {
            $error = "Error updating user.";
        }

    } else {
        $error = "Username and role are required.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-4">

    <h3>Edit User</h3>

    <div class="card mt-3">
        <div class="card-body">

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">

                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control"
                           value="<?= $user['username'] ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-control">
                        <option value="admin" <?= $user['role']=="admin"?"selected":"" ?>>Admin</option>
                        <option value="staff" <?= $user['role']=="staff"?"selected":"" ?>>Staff</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">New Password (leave blank to keep current)</label>
                    <input type="password" name="password" class="form-control">
                </div>

                <button class="btn btn-primary">Update</button>
                <a href="users.php" class="btn btn-secondary">Cancel</a>

            </form>

        </div>
    </div>

</div>

</body>
</html>
