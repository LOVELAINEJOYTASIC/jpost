<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Create database connection
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'jpost';

// Connect to MySQL server only (no db yet)
$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// Create database if it doesn't exist
$conn->query("CREATE DATABASE IF NOT EXISTS `$db`");
$conn->close();

// Now connect to the actual database
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// Create users table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('jobseeker','employer') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Create a test employer account if it doesn't exist
$test_employer = 'employer';
$test_password = password_hash('employer123', PASSWORD_DEFAULT);
$sql = "INSERT IGNORE INTO users (username, password, user_type) VALUES (?, ?, 'employer')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $test_employer, $test_password);
$stmt->execute();
$stmt->close();

// Create a test jobseeker account if it doesn't exist
$test_jobseeker = 'jobseeker';
$test_password = password_hash('jobseeker123', PASSWORD_DEFAULT);
$sql = "INSERT IGNORE INTO users (username, password, user_type) VALUES (?, ?, 'jobseeker')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $test_jobseeker, $test_password);
$stmt->execute();
$stmt->close();

$conn->close();

echo "Database setup completed successfully!";
?> 