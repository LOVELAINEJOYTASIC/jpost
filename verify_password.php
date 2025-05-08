<?php
require_once 'config.php';

// Get database connection
$conn = getDBConnection();

// Admin credentials
$username = 'admin';
$password = 'admin123';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Update admin password
$sql = "UPDATE users SET password = ? WHERE username = 'admin'";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $hashed_password);

if ($stmt->execute()) {
    echo "Admin password has been reset successfully!<br>";
    echo "Username: admin<br>";
    echo "Password: admin123<br>";
    echo "<br>You can now log in at <a href='login.php'>login.php</a>";
} else {
    echo "Error updating password: " . $stmt->error;
}

$stmt->close();
$conn->close();
?> 