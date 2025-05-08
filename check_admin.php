<?php
require_once 'config.php';

// Get database connection
$conn = getDBConnection();

// Check admin user
$sql = "SELECT id, username, user_type FROM users WHERE username = 'admin'";
$result = $conn->query($sql);

echo "<h2>Admin User Verification</h2>";

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "Admin user details:<br>";
    echo "ID: " . $user['id'] . "<br>";
    echo "Username: " . $user['username'] . "<br>";
    echo "User Type: " . $user['user_type'] . "<br>";
    
    if ($user['user_type'] !== 'admin') {
        echo "<br>Fixing user type...<br>";
        $update_sql = "UPDATE users SET user_type = 'admin' WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param('i', $user['id']);
        
        if ($stmt->execute()) {
            echo "Successfully updated user type to admin!<br>";
        } else {
            echo "Error updating user type: " . $stmt->error . "<br>";
        }
    }
} else {
    echo "No admin user found!<br>";
}

$conn->close();
?> 