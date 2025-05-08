<?php
require_once 'config.php';

// Get database connection
$conn = getDBConnection();

// Admin credentials
$username = 'admin';
$password = 'admin123';
$user_type = 'admin';

// Hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Check if admin user already exists
$check_sql = "SELECT id FROM users WHERE username = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param('s', $username);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    echo "Admin user already exists!<br>";
    echo "Username: " . $username . "<br>";
    echo "Password: " . $password . "<br>";
} else {
    // Insert admin user
    $sql = "INSERT INTO users (username, password, user_type) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $username, $hashed_password, $user_type);
    
    if ($stmt->execute()) {
        echo "Admin user created successfully!<br>";
        echo "Username: " . $username . "<br>";
        echo "Password: " . $password . "<br>";
    } else {
        echo "Error creating admin user: " . $stmt->error;
    }
    $stmt->close();
}

$check_stmt->close();
$conn->close();
?> 