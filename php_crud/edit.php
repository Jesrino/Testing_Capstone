<?php include 'db.php'; ?>

<?php
$id = $_GET['id'];
$result = $conn->query("SELECT * FROM users WHERE id=$id");
$row = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit User</title>
</head>
<body>
<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f4f7f9;
    color: #333;
    margin: 0;
    padding: 20px;
}

/* Container for content */
.container {
    max-width: 600px; /* Smaller container for forms */
    margin: 0 auto;
    background-color: #fff;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* Heading Style */
h2 {
    color: #2c3e50;
    border-bottom: 2px solid #3498db;
    padding-bottom: 10px;
    margin-top: 0;
    font-weight: 600;
    margin-bottom: 25px;
}

/* Form Styling */
form {
    display: flex;
    flex-direction: column;
}

/* Input field and label wrapping */
.form-group {
    margin-bottom: 15px;
}

/* Label Styling */
label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #555;
}

/* Input Field Styling */
input[type="text"],
input[type="email"],
input[type="tel"] {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-sizing: border-box;
    transition: border-color 0.3s;
}

input[type="text"]:focus,
input[type="email"]:focus,
input[type="tel"]:focus {
    border-color: #3498db; 
    outline: none;
}


input[type="submit"] {
    background-color: #2ecc71; 
    color: white;
    padding: 12px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
    transition: background-color 0.3s ease;
    margin-top: 10px;
}

input[type="submit"]:hover {
    background-color: #27ae60;
}


.back-link {
    display: inline-block;
    margin-top: 20px;
    color: #3498db;
    text-decoration: none;
    font-weight: 500;
}

.back-link:hover {
    text-decoration: underline;
}
</style>
<h2>Edit User</h2>
    <form method="POST">
        Name: <input type="text" name="name" value="<?= $row['name']; ?>" required><br><br>
        Email: <input type="email" name="email" value="<?= $row['email']; ?>" required><br><br>
        Phone: <input type="text" name="phone" value="<?= $row['phone']; ?>" required><br><br>
        <input type="submit" name="update" value="Update">
    </form>
</body>
</html>

<?php
if (isset($_POST['update'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];

    $sql = "UPDATE users SET name='$name', email='$email', phone='$phone' WHERE id=$id";
    if ($conn->query($sql)) {
        header("Location: index.php");
        exit;
    } else {
        echo "Error updating record: " . $conn->error;
    }
}
?>
