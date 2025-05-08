<?php
require_once 'config.php';

// Get database connection
$conn = getDBConnection();

// Check if admin user exists
$sql = "SELECT id, username, user_type FROM users WHERE username = 'admin'";
$result = $conn->query($sql);

echo "<h2>Admin User Check</h2>";

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "Admin user found:<br>";
    echo "ID: " . $user['id'] . "<br>";
    echo "Username: " . $user['username'] . "<br>";
    echo "User Type: " . $user['user_type'] . "<br>";
    
    if ($user['user_type'] !== 'admin') {
        echo "<br>WARNING: User exists but is not set as admin!<br>";
        echo "Updating user type to admin...<br>";
        
        $update_sql = "UPDATE users SET user_type = 'admin' WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param('i', $user['id']);
        
        if ($stmt->execute()) {
            echo "Successfully updated user to admin!<br>";
        } else {
            echo "Error updating user: " . $stmt->error . "<br>";
        }
    }
} else {
    echo "No admin user found! Creating one...<br>";
    
    $password = 'admin123';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $insert_sql = "INSERT INTO users (username, password, user_type) VALUES ('admin', ?, 'admin')";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param('s', $hashed_password);
    
    if ($stmt->execute()) {
        echo "Admin user created successfully!<br>";
        echo "Username: admin<br>";
        echo "Password: admin123<br>";
    } else {
        echo "Error creating admin user: " . $stmt->error . "<br>";
    }
}

$conn->close();
?> 