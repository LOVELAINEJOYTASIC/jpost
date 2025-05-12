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
$create_jobs = "CREATE TABLE IF NOT EXISTS jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employer_id INT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    location VARCHAR(255),
    employment_type VARCHAR(50),
    salary_range VARCHAR(100),
    requirements TEXT,
    status ENUM('active', 'pending', 'closed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employer_id) REFERENCES employers(id)
)";

if ($conn->query($create_jobs)) {
    echo "Successfully created jobs table<br>";
    
    // Insert sample jobs
    $jobs = [
        [
            1, // Tech Solutions Inc.
            'Senior Software Developer',
            'We are looking for an experienced software developer to join our team. The ideal candidate should have strong programming skills and experience with modern web technologies.',
            'Technology',
            'New York, NY',
            'Full-time',
            '$100,000 - $150,000',
            '5+ years of experience in web development\nStrong knowledge of PHP, JavaScript, and MySQL\nExperience with modern frameworks'
        ],
        [
            2, // Global Finance Ltd.
            'Financial Analyst',
            'Join our finance team to analyze market trends and provide strategic insights. The role involves financial modeling and market research.',
            'Finance',
            'London, UK',
            'Full-time',
            '$80,000 - $120,000',
            'Bachelor\'s degree in Finance or related field\n3+ years of financial analysis experience\nStrong analytical skills'
        ],
        [
            3, // Healthcare Plus
            'Registered Nurse',
            'We are seeking a dedicated RN to provide quality patient care in our state-of-the-art facility.',
            'Healthcare',
            'Boston, MA',
            'Full-time',
            '$70,000 - $90,000',
            'Valid RN license\n2+ years of clinical experience\nStrong patient care skills'
        ]
    ];
    
    $insert_job = "INSERT INTO jobs (employer_id, title, description, category, location, employment_type, salary_range, requirements) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_job);
    
    foreach ($jobs as $job) {
        $stmt->bind_param("isssssss", $job[0], $job[1], $job[2], $job[3], $job[4], $job[5], $job[6], $job[7]);
        if ($stmt->execute()) {
            echo "Added job: " . htmlspecialchars($job[1]) . "<br>";
        } else {
            echo "Error adding job: " . $stmt->error . "<br>";
        }
    }
    $stmt->close();
} else {
    echo "Error creating jobs table: " . $conn->error . "<br>";
}

// Create job_applications table
$create_applications = "CREATE TABLE IF NOT EXISTS job_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT,
    applicant_id INT,
    status ENUM('pending', 'reviewed', 'interviewed', 'accepted', 'rejected') DEFAULT 'pending',
    application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id),
    FOREIGN KEY (applicant_id) REFERENCES applicants(id)
)";

if ($conn->query($create_applications)) {
    echo "Successfully created job_applications table<br>";
    
    // Insert sample applications
    $applications = [
        [1, 1, 'pending'],  // John Doe applied for Senior Software Developer
        [1, 2, 'reviewed'], // Jane Smith applied for Senior Software Developer
        [2, 3, 'interviewed'] // Mike Johnson applied for Financial Analyst
    ];
    
    $insert_application = "INSERT INTO job_applications (job_id, applicant_id, status) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($insert_application);
    
    foreach ($applications as $app) {
        $stmt->bind_param("iis", $app[0], $app[1], $app[2]);
        if ($stmt->execute()) {
            echo "Added application for job ID: " . $app[0] . "<br>";
        } else {
            echo "Error adding application: " . $stmt->error . "<br>";
        }
    }
    $stmt->close();
} else {
    echo "Error creating job_applications table: " . $conn->error . "<br>";
}

// Verify the tables
echo "<br>Table structure:<br>";
$tables = ['employers', 'jobs', 'job_applications'];
foreach ($tables as $table) {
    echo "<br>$table table structure:<br>";
    $structure = $conn->query("DESCRIBE $table");
    while ($row = $structure->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "<br>";
    }
}

$conn->close();
?> 