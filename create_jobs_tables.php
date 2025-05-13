<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
$conn = getDBConnection();

// Create employers table
$create_employers = "CREATE TABLE IF NOT EXISTS employers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    company_logo VARCHAR(255),
    industry VARCHAR(100),
    website VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($create_employers)) {
    echo "Successfully created employers table<br>";
    
    // Insert sample employers
    $employers = [
        ['Tech Solutions Inc.', 'logos/tech_solutions.png', 'Technology', 'https://techsolutions.com'],
        ['Global Finance Ltd.', 'logos/global_finance.png', 'Finance', 'https://globalfinance.com'],
        ['Healthcare Plus', 'logos/healthcare_plus.png', 'Healthcare', 'https://healthcareplus.com']
    ];
    
    $insert_employer = "INSERT INTO employers (company_name, company_logo, industry, website) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_employer);
    
    foreach ($employers as $employer) {
        $stmt->bind_param("ssss", $employer[0], $employer[1], $employer[2], $employer[3]);
        if ($stmt->execute()) {
            echo "Added employer: " . htmlspecialchars($employer[0]) . "<br>";
        } else {
            echo "Error adding employer: " . $stmt->error . "<br>";
        }
    }
    $stmt->close();
} else {
    echo "Error creating employers table: " . $conn->error . "<br>";
}

// Create jobs table
$sql = "CREATE TABLE IF NOT EXISTS jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company VARCHAR(255) NOT NULL,
    job VARCHAR(255) NOT NULL,
    requirements TEXT,
    salary VARCHAR(100),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Jobs table created successfully or already exists<br>";
} else {
    echo "Error creating jobs table: " . $conn->error . "<br>";
}

// Create applicants table
$create_applicants = "CREATE TABLE IF NOT EXISTS applicants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    resume_url VARCHAR(255),
    status1 ENUM('In Review','In Process','Interview','On Demand','Accepted','Cancelled','In Waiting'),
    status2 VARCHAR(50),
    status3 VARCHAR(50),
    job_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    user_id INT,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
)";

if ($conn->query($create_applicants)) {
    echo "Successfully created applicants table<br>";
} else {
    echo "Error creating applicants table: " . $conn->error . "<br>";
}

// Create job_applications table
$create_applications = "CREATE TABLE IF NOT EXISTS job_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('Pending','Accepted','Rejected') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($create_applications)) {
    echo "Successfully created job_applications table<br>";
} else {
    echo "Error creating job_applications table: " . $conn->error . "<br>";
}

// Verify the tables
echo "<br>Table structure:<br>";
$tables = ['employers', 'jobs', 'applicants', 'job_applications'];
foreach ($tables as $table) {
    echo "<br>$table table structure:<br>";
    $structure = $conn->query("DESCRIBE $table");
    while ($row = $structure->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "<br>";
    }
}

$conn->close();
?> 