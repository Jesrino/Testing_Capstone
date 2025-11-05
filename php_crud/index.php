<?php include 'db.php'; ?>

<!DOCTYPE html>
<html>
<head>
    <title>PHP CRUD Example</title>
</head>
<body>
    <style>
       /* General Styles */
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f4f7f9;
    color: #333;
    margin: 0;
    padding: 20px;
}

/* Container for content */
.container {
    max-width: 1000px;
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
}

/* 'Add New User' Button Styling */
.add-button {
    display: inline-block;
    background-color: #2ecc71; /* Green color for positive action */
    color: white;
    padding: 10px 15px;
    text-decoration: none;
    border-radius: 5px;
    margin-bottom: 20px;
    transition: background-color 0.3s ease;
    font-weight: bold;
}

.add-button:hover {
    background-color: #27ae60;
}

/* Table Styles */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    box-shadow: 0 2px 3px rgba(0, 0, 0, 0.05);
    border-radius: 5px;
    overflow: hidden;
}

th, td {
    padding: 12px 15px;
    text-align: left;
    border: 1px solid #ddd; 
}

th {
    background-color: #3498db;
    color: white;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    font-weight: 700;
}

tr:nth-child(even) {
    background-color: #f9f9f9;
}

tr:hover {
    background-color: #f0f0f0;
}


.action-links a {
    color: #3498db;
    text-decoration: none;
    margin: 0 5px;
    transition: color 0.3s ease;
    font-weight: 500;
}

.action-links a:hover {
    color: #2980b9;
    text-decoration: underline;
}


.action-links a:first-child { 
     color: #f39c12; 
}
.action-links
    </style>
    <h2>User List</h2>
    <button><a href="add.php">Add New User</a></button>
    <br><br>

    <table border="1" cellpadding="10">
        <tr>
            <th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Action</th>
        </tr>

        <?php
        $result = $conn->query("SELECT * FROM users");
        while($row = $result->fetch_assoc()):
        ?>
        <tr>
            <td><?= $row['id']; ?></td>
            <td><?= $row['name']; ?></td>
            <td><?= $row['email']; ?></td>
            <td><?= $row['phone']; ?></td>
            <td>
                <a href="edit.php?id=<?= $row['id']; ?>">Edit</a> |
                <a href="delete.php?id=<?= $row['id']; ?>" onclick="return confirm('Delete this record?');">Delete</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>