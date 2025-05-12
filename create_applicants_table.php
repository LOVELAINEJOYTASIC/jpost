<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
$conn = getDBConnection();

// Create applicants table
$create_table_query = "CREATE TABLE IF NOT EXISTS applicants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    resume_url VARCHAR(255),
    status1 ENUM('In Review', 'In Process', 'Interview', 'On Demand', 'Accepted', 'Cancelled', 'In Waiting') DEFAULT 'In Review',
    status2 VARCHAR(50),
    status3 VARCHAR(50),
    job_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($create_table_query)) {
    echo "Successfully created applicants table<br>";
    
    // Insert some sample data
    $sample_data = [
        ['John Doe', 'john@example.com', '123-456-7890', 'In Review', 'Pending', 'New'],
        ['Jane Smith', 'jane@example.com', '098-765-4321', 'In Process', 'Scheduled', 'Active'],
        ['Mike Johnson', 'mike@example.com', '555-123-4567', 'Interview', 'Completed', 'Follow-up']
    ];
    
    $insert_query = "INSERT INTO applicants (name, email, phone, status1, status2, status3) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_query);
    
    foreach ($sample_data as $data) {
        $stmt->bind_param("ssssss", $data[0], $data[1], $data[2], $data[3], $data[4], $data[5]);
        if ($stmt->execute()) {
            echo "Added sample applicant: " . htmlspecialchars($data[0]) . "<br>";
        } else {
            echo "Error adding sample applicant: " . $stmt->error . "<br>";
        }
    }
    
    $stmt->close();
    
    // Verify the table structure
    echo "<br>Table structure:<br>";
    $structure_query = "DESCRIBE applicants";
    $structure_result = $conn->query($structure_query);
    while ($row = $structure_result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "<br>";
    }
    
    // Verify the data
    echo "<br>Sample data:<br>";
    $data_query = "SELECT * FROM applicants";
    $data_result = $conn->query($data_query);
    while ($row = $data_result->fetch_assoc()) {
        echo "Name: " . htmlspecialchars($row['name']) . " - Status: " . htmlspecialchars($row['status1']) . "<br>";
    }
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

$conn->close();

if (strtolower($_SESSION['user_type']) === 'admin') {
    header('Location: admin.php');
}
?> 