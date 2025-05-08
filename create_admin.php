<?php
require_once 'config.php';

// Get database connection
$conn = getDBConnection();

// Set your desired admin username and password
$username = 'admin';
$password = 'admin123'; // Change this to your preferred password

// Hash the password
$hash = password_hash($password, PASSWORD_DEFAULT);

// Check if admin already exists
$result = $conn->query("SELECT * FROM users WHERE username = '$username'");
if ($result->num_rows > 0) {
    echo "Admin user already exists.<br>";
} else {
    $conn->query("INSERT INTO users (username, password, user_type) VALUES ('$username', '$hash', 'admin')");
    echo "Admin user created!<br>";
}
echo "Username: $username<br>Password: $password<br>";
echo "<a href='login.php'>Go to Login</a>";

$conn->close();
?> 