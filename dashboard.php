<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Only allow access if user is employer
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['user_type'] ?? '') !== 'employer') {
    header('Location: login.php');
    exit();
}

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'jpost';

$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}
$conn->query("CREATE DATABASE IF NOT EXISTS `$db`");
$conn->close();

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

$conn->query("CREATE TABLE IF NOT EXISTS jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company VARCHAR(255) NOT NULL,
    job VARCHAR(255) NOT NULL,
    requirements TEXT NOT NULL,
    salary VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('jobseeker','employer') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Create user_profiles table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS user_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    full_name VARCHAR(255),
    contact VARCHAR(100),
    email VARCHAR(255),
    resume VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// Create applications table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS job_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('Pending', 'Accepted', 'Rejected') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// Create interview_status table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS interview_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    status ENUM('Pending', 'Scheduled', 'Done', 'Cancelled', 'Rescheduled') DEFAULT 'Pending',
    interview_date DATE,
    interview_time TIME,
    interview_type ENUM('Online', 'In-Person', 'Phone') DEFAULT 'In-Person',
    location VARCHAR(255),
    duration INT DEFAULT 60,
    interviewer VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES job_applications(id) ON DELETE CASCADE
)");

// Add interview feedback table
$conn->query("CREATE TABLE IF NOT EXISTS interview_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    interview_id INT NOT NULL,
    technical_rating INT CHECK (technical_rating BETWEEN 1 AND 5),
    communication_rating INT CHECK (communication_rating BETWEEN 1 AND 5),
    experience_rating INT CHECK (experience_rating BETWEEN 1 AND 5),
    overall_rating INT CHECK (overall_rating BETWEEN 1 AND 5),
    strengths TEXT,
    weaknesses TEXT,
    feedback_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (interview_id) REFERENCES interview_status(id) ON DELETE CASCADE
)");

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM jobs WHERE id=$id");
    header('Location: dashboard.php');
    exit();
}

// Handle edit (update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id']) && $_POST['edit_id'] !== '') {
    $id = intval($_POST['edit_id']);
    $company = $conn->real_escape_string($_POST['company']);
    $job = $conn->real_escape_string($_POST['job']);
    $requirements = $conn->real_escape_string($_POST['requirements']);
    $salary = $conn->real_escape_string($_POST['salary']);
    $conn->query("UPDATE jobs SET company='$company', job='$job', requirements='$requirements', salary='$salary' WHERE id=$id");
    header('Location: dashboard.php');
    exit();
}

// Handle new job post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['edit_id']) || $_POST['edit_id'] === '')) {
    if (isset($_POST['company'], $_POST['job'], $_POST['requirements'], $_POST['salary'])) {
        $company = $conn->real_escape_string($_POST['company']);
        $job = $conn->real_escape_string($_POST['job']);
        $requirements = $conn->real_escape_string($_POST['requirements']);
        $salary = $conn->real_escape_string($_POST['salary']);
        $sql = "INSERT INTO jobs (company, job, requirements, salary) VALUES ('$company', '$job', '$requirements', '$salary')";
        if ($conn->query($sql)) {
            header('Location: dashboard.php?success=1');
            exit();
        } else {
            die('Error posting job: ' . $conn->error);
        }
    }
}

// Handle application status update
if (isset($_POST['update_status'])) {
    $application_id = (int)$_POST['application_id'];
    $new_status = $conn->real_escape_string($_POST['status']);
    $sql = "UPDATE job_applications SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $new_status, $application_id);
    if ($stmt->execute()) {
        header('Location: dashboard.php?success=2');
        exit();
    }
    $stmt->close();
}

// Add email notification function
function sendInterviewNotification($to_email, $subject, $message) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: JPOST <noreply@jpost.com>' . "\r\n";
    
    return mail($to_email, $subject, $message, $headers);
}

// Handle interview status update with notifications
if (isset($_POST['update_interview_status'])) {
    $application_id = (int)$_POST['application_id'];
    $new_status = $conn->real_escape_string($_POST['interview_status']);
    $interview_date = $conn->real_escape_string($_POST['interview_date']);
    $interview_time = $conn->real_escape_string($_POST['interview_time']);
    $interview_type = $conn->real_escape_string($_POST['interview_type']);
    $location = $conn->real_escape_string($_POST['interview_location']);
    $duration = (int)$_POST['interview_duration'];
    $interviewer = $conn->real_escape_string($_POST['interviewer']);
    $notes = $conn->real_escape_string($_POST['interview_notes']);

    // Get applicant email
    $email_query = "SELECT u.email, u.username, j.job, j.company 
                   FROM job_applications ja 
                   JOIN users u ON ja.user_id = u.id 
                   JOIN jobs j ON ja.job_id = j.id 
                   WHERE ja.id = ?";
    $email_stmt = $conn->prepare($email_query);
    $email_stmt->bind_param("i", $application_id);
    $email_stmt->execute();
    $email_result = $email_stmt->get_result();
    $applicant_info = $email_result->fetch_assoc();

    // Check if interview status already exists
    $check_stmt = $conn->prepare("SELECT id FROM interview_status WHERE application_id = ?");
    $check_stmt->bind_param("i", $application_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        // Update existing interview status
        $stmt = $conn->prepare("UPDATE interview_status SET 
            status = ?, 
            interview_date = ?, 
            interview_time = ?, 
            interview_type = ?,
            location = ?,
            duration = ?,
            interviewer = ?,
            notes = ? 
            WHERE application_id = ?");
        $stmt->bind_param("sssssisis", $new_status, $interview_date, $interview_time, $interview_type, $location, $duration, $interviewer, $notes, $application_id);
    } else {
        // Insert new interview status
        $stmt = $conn->prepare("INSERT INTO interview_status 
            (application_id, status, interview_date, interview_time, interview_type, location, duration, interviewer, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssiss", $application_id, $new_status, $interview_date, $interview_time, $interview_type, $location, $duration, $interviewer, $notes);
    }

    if ($stmt->execute()) {
        // Send email notification
        if ($applicant_info && $applicant_info['email']) {
            $email_subject = "Interview Update - " . $applicant_info['job'];
            $email_message = "
                <html>
                <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                    <h2>Interview Update</h2>
                    <p>Dear " . htmlspecialchars($applicant_info['username']) . ",</p>
                    <p>Your interview status for the position of <strong>" . htmlspecialchars($applicant_info['job']) . "</strong> at <strong>" . htmlspecialchars($applicant_info['company']) . "</strong> has been updated.</p>
                    <div style='background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 15px 0;'>
                        <p><strong>Status:</strong> " . htmlspecialchars($new_status) . "</p>
                        <p><strong>Date:</strong> " . htmlspecialchars($interview_date) . "</p>
                        <p><strong>Time:</strong> " . htmlspecialchars($interview_time) . "</p>
                        <p><strong>Type:</strong> " . htmlspecialchars($interview_type) . "</p>
                        <p><strong>Duration:</strong> " . htmlspecialchars($duration) . " minutes</p>
                        " . ($location ? "<p><strong>Location/Link:</strong> " . htmlspecialchars($location) . "</p>" : "") . "
                        " . ($interviewer ? "<p><strong>Interviewer:</strong> " . htmlspecialchars($interviewer) . "</p>" : "") . "
                        " . ($notes ? "<p><strong>Notes:</strong><br>" . nl2br(htmlspecialchars($notes)) . "</p>" : "") . "
                    </div>
                    <p>Please make sure to prepare accordingly and arrive on time.</p>
                    <p>Best regards,<br>JPOST Team</p>
                </body>
                </html>";

            sendInterviewNotification($applicant_info['email'], $email_subject, $email_message);
        }
        header('Location: dashboard.php?success=3');
        exit();
    }
    $stmt->close();
}

// Handle interview feedback submission
if (isset($_POST['submit_feedback'])) {
    $interview_id = (int)$_POST['interview_id'];
    $technical_rating = (int)$_POST['technical_rating'];
    $communication_rating = (int)$_POST['communication_rating'];
    $experience_rating = (int)$_POST['experience_rating'];
    $overall_rating = (int)$_POST['overall_rating'];
    $strengths = $conn->real_escape_string($_POST['strengths']);
    $weaknesses = $conn->real_escape_string($_POST['weaknesses']);
    $feedback_notes = $conn->real_escape_string($_POST['feedback_notes']);

    $stmt = $conn->prepare("INSERT INTO interview_feedback 
        (interview_id, technical_rating, communication_rating, experience_rating, overall_rating, strengths, weaknesses, feedback_notes) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiiisss", $interview_id, $technical_rating, $communication_rating, $experience_rating, $overall_rating, $strengths, $weaknesses, $feedback_notes);
    
    if ($stmt->execute()) {
        header('Location: dashboard.php?success=4');
        exit();
    }
    $stmt->close();
}

// Add search functionality
$search_query = '';
$search_type = 'all'; // Default to searching all

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $search_query = htmlspecialchars($_GET['search']);
}

if (isset($_GET['type']) && !empty($_GET['type'])) {
    $search_type = $conn->real_escape_string($_GET['type']);
}

// Modify the jobs query to include search
$jobs_query = "SELECT * FROM jobs WHERE 1=1";
if (!empty($search_query)) {
    if ($search_type === 'all' || $search_type === 'jobs') {
        $jobs_query .= " AND (job LIKE '%$search%' OR company LIKE '%$search%' OR requirements LIKE '%$search%')";
    }
}
$jobs_query .= " ORDER BY created_at DESC";
$jobs = $conn->query($jobs_query);

// Modify the applications query to include search
$applications_sql = "SELECT ja.*, j.job, j.company, u.username 
                    FROM job_applications ja 
                    INNER JOIN jobs j ON ja.job_id = j.id 
                    INNER JOIN users u ON ja.user_id = u.id 
                    WHERE 1=1";
if (!empty($search_query)) {
    if ($search_type === 'all' || $search_type === 'applications') {
        $applications_sql .= " AND (j.job LIKE '%$search%' OR j.company LIKE '%$search%' OR u.username LIKE '%$search%')";
    }
}
$applications_sql .= " ORDER BY ja.created_at DESC";
$applications = $conn->query($applications_sql);

// Handle logout
if (isset($_GET['logout'])) {
    session_start();
    session_destroy();
    header('Location: login.php');
    exit();
}

// After fetching jobs
echo '<div style="color:yellow;background:#222;padding:8px;">Jobs found: ' . ($jobs ? $jobs->num_rows : 0) . '</div>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Dashboard - JPOST</title>
    <style>
        body {
            background: #181818;
            color: #fff;
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            background: #181818;
            border-bottom: 2px solid #333;
            height: 60px;
        }
        .navbar .logo {
            display: flex;
            align-items: center;
            font-size: 1.7em;
            font-weight: bold;
            letter-spacing: 2px;
        }
        .navbar nav {
            display: flex;
            align-items: center;
        }
        .navbar nav a {
            color: #fff;
            text-decoration: none;
            margin: 0 18px;
            font-size: 1.1em;
            transition: color 0.2s;
        }
        .navbar nav a:hover, .navbar nav a.active {
            color: #4fc3f7;
            text-decoration: underline;
        }
        .navbar .search {
            display: flex;
            align-items: center;
            background: #eee;
            border-radius: 20px;
            padding: 4px 12px;
        }
        .navbar .search input {
            background: transparent;
            border: none;
            color: #222;
            outline: none;
            padding: 6px 8px;
            font-size: 1em;
            width: 200px;
        }
        .navbar .search button {
            background: none;
            border: none;
            color: #222;
            cursor: pointer;
            font-size: 1.2em;
        }
        .navbar .settings {
            margin-left: 18px;
            font-size: 1.7em;
            color: #4fc3f7;
            cursor: pointer;
        }
        .dashboard-container {
            margin: 48px auto 0 auto;
            width: 95%;
            max-width: 1000px;
            min-width: 320px;
            background: #181818;
            border-radius: 16px;
            border: 2px solid #fff;
            padding: 32px 0 32px 0;
            min-height: 480px;
            position: relative;
            display: flex;
            flex-direction: row;
            gap: 48px;
            justify-content: center;
        }
        .job-card {
            background: #fff;
            color: #222;
            border-radius: 8px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.18);
            padding: 32px 28px 24px 28px;
            min-width: 300px;
            max-width: 340px;
            margin: auto 0;
            text-align: center;
        }
        .job-card .profile-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 20px auto;
            border: 3px solid #5bbcff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .job-card h2 {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 18px;
        }
        .job-card .job-details {
            text-align: left;
            margin-bottom: 18px;
            font-size: 1.1em;
        }
        .job-card .post-btn {
            width: 70%;
            background: #5bbcff;
            color: #222;
            font-weight: bold;
            border: none;
            border-radius: 8px;
            padding: 12px 0;
            font-size: 1.1em;
            margin: 10px 0 0 0;
            cursor: pointer;
            transition: background 0.2s;
        }
        .job-card .post-btn:hover {
            background: #0288d1;
            color: #fff;
        }
        .dashboard-actions {
            display: flex;
            flex-direction: column;
            gap: 24px;
            margin: auto 0;
        }
        .dashboard-actions button {
            width: 200px;
            padding: 18px 0;
            border: none;
            border-radius: 4px;
            font-size: 1.15em;
            font-weight: bold;
            cursor: pointer;
            margin-bottom: 0;
            transition: filter 0.15s;
        }
        .dashboard-actions .candidate-list { background: #7ed957; color: #222; }
        .dashboard-actions .resume { background: #ffb366; color: #222; }
        .dashboard-actions .interview { background: #f7f7b6; color: #222; }
        .dashboard-actions .recruit { background: #008080; color: #fff; }
        .dashboard-actions button:hover { filter: brightness(0.95); }
        .footer {
            width: 100%;
            background: #181818;
            border-top: 2px solid #333;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 18px 0 10px 0;
            position: static;
            left: 0;
            bottom: 0;
        }
        .footer a {
            color: #fff;
            text-decoration: underline;
            margin: 0 18px;
            font-size: 1em;
        }
        .footer a:hover {
            color: #4fc3f7;
        }
        @media (max-width: 900px) {
            .dashboard-container {
                flex-direction: column;
                gap: 24px;
                padding: 0 8px 32px 8px;
                align-items: center;
            }
            .dashboard-actions button {
                width: 90vw;
                max-width: 300px;
            }
            .job-card {
                min-width: 90vw;
                max-width: 98vw;
            }
        }
       
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #222;
            margin: 8% auto;
            padding: 32px 24px 24px 24px;
            border: 1px solid #888;
            width: 340px;
            border-radius: 12px;
            color: #fff;
            position: relative;
            box-shadow: 0 4px 24px rgba(0,0,0,0.18);
        }
        .close {
            color: #aaa;
            position: absolute;
            top: 10px;
            right: 18px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: #fff;
        }
        .modal-content label {
            display: block;
            margin-bottom: 6px;
            margin-top: 12px;
            font-size: 1em;
        }
        .modal-content input[type="text"],
        .modal-content textarea {
            width: 100%;
            padding: 8px 10px;
            border-radius: 8px;
            border: none;
            margin-bottom: 8px;
            font-size: 1em;
            background: #fff;
            color: #222;
        }
        .modal-content button[type="submit"] {
            width: 100%;
            background: #5bbcff;
            color: #222;
            font-weight: bold;
            border: none;
            border-radius: 8px;
            padding: 12px 0;
            font-size: 1.1em;
            margin: 10px 0 0 0;
            cursor: pointer;
            transition: background 0.2s;
        }
        .modal-content button[type="submit"]:hover {
            background: #0288d1;
            color: #fff;
        }
        .search-container {
            display: flex;
            align-items: center;
            background: #fff;
            border-radius: 20px;
            padding: 4px 12px;
            width: 300px;
        }
        .search-container input {
            background: transparent;
            border: none;
            color: #222;
            outline: none;
            padding: 6px 8px;
            font-size: 1em;
            width: 100%;
        }
        .search-container button {
            background: none;
            border: none;
            color: #222;
            cursor: pointer;
            font-size: 1.2em;
            padding: 0 8px;
        }
        .logout-btn {
            background: #f44336;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 16px;
            font-weight: bold;
        }
        .logout-btn:hover {
            background: #d32f2f;
        }
        .candidate-card {
            background: #fff;
            color: #222;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .candidate-card h3 {
            color: #4fc3f7;
            margin: 0 0 12px 0;
        }
        .candidate-info {
            margin-bottom: 8px;
        }
        .candidate-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }
        .candidate-actions button {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .view-resume {
            background: #4fc3f7;
            color: #fff;
        }
        .view-resume:hover {
            background: #0288d1;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .status-pending { background: #ffd700; color: #000; }
        .status-accepted { background: #4caf50; color: #fff; }
        .status-rejected { background: #f44336; color: #fff; }
        .rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }
        .rating input {
            display: none;
        }
        .rating label {
            cursor: pointer;
            font-size: 24px;
            color: #ddd;
            padding: 0 2px;
        }
        .rating input:checked ~ label,
        .rating label:hover,
        .rating label:hover ~ label {
            color: #ffd700;
        }
        .rating label:hover,
        .rating label:hover ~ label {
            color: #ffd700;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="logo">
            <span style="font-size:1.2em; margin-right:4px;">&#128188;</span> JPOST
        </div>
        <nav>
            <a href="index.php">Home</a>
            <a href="explore.php">Explore</a>
            <a href="account.php">Account</a>
            <a href="dashboard.php" class="active">Dashboard</a>
        </nav>
        <div style="display:flex; align-items:center;">
            <form action="dashboard.php" method="GET" class="search">
                <input type="text" name="search" placeholder="Search jobs or applications..." value="<?php echo htmlspecialchars($search_query); ?>">
                <select name="type" style="background:transparent;border:none;color:#222;outline:none;margin-right:8px;">
                    <option value="all" <?php echo $search_type === 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="jobs" <?php echo $search_type === 'jobs' ? 'selected' : ''; ?>>Jobs</option>
                    <option value="applications" <?php echo $search_type === 'applications' ? 'selected' : ''; ?>>Applications</option>
                </select>
                <button type="submit">&#128269;</button>
            </form>
            <a href="logout.php" style="color:#fff; text-decoration:none; margin-left:18px; background:#f44336; padding:8px 16px; border-radius:4px;">Logout</a>
        </div>
    </div>
    <div class="dashboard-container">
        <div class="job-card">
            <img src="uploads/professional_woman.jpg" alt="Professional Woman" class="profile-image">
            <h2>Post a Job</h2>
            <div class="job-details">
                <div>Fill in the details below to post a new job opening.</div>
            </div>
            <button class="post-btn" id="openModalBtn">Post New Job</button>
        </div>
        <div class="dashboard-actions">
            <button class="candidate-list" onclick="showApplications()">Candidate List</button>
            <button class="resume">Resume</button>
            <button class="interview">Interview</button>
            <button class="recruit">Recruit</button>
        </div>
    </div>
    <!-- Applications List -->
    <div id="applicationsList" style="width:95%;max-width:1000px;margin:32px auto 0 auto;display:none;">
        <h2 style="color:#4fc3f7;text-align:left;margin-bottom:12px;">Job Applications</h2>
        <?php if ($applications && $applications->num_rows > 0): ?>
            <div style="display:flex;flex-wrap:wrap;gap:18px;">
            <?php while($app = $applications->fetch_assoc()): 
                // Get interview status
                $interview_stmt = $conn->prepare("SELECT status, interview_date, interview_time, interview_type, location, duration, interviewer, notes, updated_at FROM interview_status WHERE application_id = ?");
                $interview_stmt->bind_param("i", $app['id']);
                $interview_stmt->execute();
                $interview_result = $interview_stmt->get_result();
                $interview = $interview_result->fetch_assoc();
                $interview_status = $interview ? $interview['status'] : 'Pending';
                
                // Set status color and icon
                $status_color = '#ff9800'; // Default orange for Pending
                $status_icon = '⏳'; // Pending
                if ($interview_status === 'Done') {
                    $status_color = '#4caf50'; // Green
                    $status_icon = '✓';
                } elseif ($interview_status === 'Scheduled') {
                    $status_color = '#2196f3'; // Blue
                    $status_icon = '📅';
                } elseif ($interview_status === 'Cancelled') {
                    $status_color = '#f44336'; // Red
                    $status_icon = '❌';
                } elseif ($interview_status === 'Rescheduled') {
                    $status_color = '#9c27b0'; // Purple
                    $status_icon = '🔄';
                }
            ?>
                <div style="background:#fff;color:#222;border-radius:8px;box-shadow:0 2px 8px #0002;padding:18px 22px;min-width:220px;max-width:320px;flex:1;position:relative;">
                    <div style="font-size:1.2em;font-weight:bold;margin-bottom:8px;color:#4fc3f7;"><?php echo htmlspecialchars($app['job']); ?></div>
                    <div><b>Applicant:</b> <?php echo htmlspecialchars($app['username']); ?></div>
                    <div><b>Company:</b> <?php echo htmlspecialchars($app['company']); ?></div>
                    <div><b>Status:</b> <span style="color: <?php echo $app['status'] === 'Accepted' ? '#4caf50' : ($app['status'] === 'Rejected' ? '#f44336' : '#ff9800'); ?>"><?php echo htmlspecialchars($app['status']); ?></span></div>
                    <div style="margin-top:12px;padding:12px;background:#f5f5f5;border-radius:6px;border-left:4px solid <?php echo $status_color; ?>;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                            <span style="font-size:1.2em;"><?php echo $status_icon; ?></span>
                            <span style="color:<?php echo $status_color; ?>;font-weight:bold;"><?php echo htmlspecialchars($interview_status); ?></span>
                            <?php if ($interview_status === 'Scheduled'): ?>
                                <span style="font-size:0.9em;color:#666;margin-left:auto;">
                                    <?php 
                                        $interview_datetime = strtotime($interview['interview_date'] . ' ' . $interview['interview_time']);
                                        $now = time();
                                        $diff = $interview_datetime - $now;
                                        if ($diff > 0) {
                                            $days = floor($diff / (60 * 60 * 24));
                                            $hours = floor(($diff % (60 * 60 * 24)) / (60 * 60));
                                            echo "in " . ($days > 0 ? $days . "d " : "") . $hours . "h";
                                        }
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($interview): ?>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
                                <?php if ($interview['interview_date']): ?>
                                    <div><b>Date:</b> <?php echo htmlspecialchars($interview['interview_date']); ?></div>
                                <?php endif; ?>
                                <?php if ($interview['interview_time']): ?>
                                    <div><b>Time:</b> <?php echo htmlspecialchars($interview['interview_time']); ?></div>
                                <?php endif; ?>
                                <?php if ($interview['duration']): ?>
                                    <div><b>Duration:</b> <?php echo htmlspecialchars($interview['duration']); ?> minutes</div>
                                <?php endif; ?>
                                <?php if ($interview['interview_type']): ?>
                                    <div><b>Type:</b> <?php echo htmlspecialchars($interview['interview_type']); ?></div>
                                <?php endif; ?>
                                <?php if ($interview['location']): ?>
                                    <div><b>Location:</b> <?php echo htmlspecialchars($interview['location']); ?></div>
                                <?php endif; ?>
                                <?php if ($interview['interviewer']): ?>
                                    <div><b>Interviewer:</b> <?php echo htmlspecialchars($interview['interviewer']); ?></div>
                                <?php endif; ?>
                            </div>
                            <?php if ($interview['notes']): ?>
                                <div style="margin-top:12px;padding:8px;background:#fff;border-radius:4px;"><b>Notes:</b><br><?php echo nl2br(htmlspecialchars($interview['notes'])); ?></div>
                            <?php endif; ?>
                            <?php if ($interview_status === 'Done' && !$feedback): ?>
                                <div style="margin-top:12px;">
                                    <button onclick="openFeedbackModal(<?php echo $interview['id']; ?>)" 
                                            style="background:#4caf50;color:#fff;border:none;padding:6px 14px;border-radius:6px;cursor:pointer;font-weight:bold;">
                                        Add Feedback
                                    </button>
                                </div>
                            <?php endif; ?>
                            <?php if ($feedback): ?>
                                <div style="margin-top:12px;padding:12px;background:#fff;border-radius:4px;border-left:4px solid #4caf50;">
                                    <h4 style="margin:0 0 8px 0;color:#4caf50;">Interview Feedback</h4>
                                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;margin-bottom:8px;">
                                        <div><b>Technical:</b> <?php echo str_repeat('★', $feedback['technical_rating']) . str_repeat('☆', 5 - $feedback['technical_rating']); ?></div>
                                        <div><b>Communication:</b> <?php echo str_repeat('★', $feedback['communication_rating']) . str_repeat('☆', 5 - $feedback['communication_rating']); ?></div>
                                        <div><b>Experience:</b> <?php echo str_repeat('★', $feedback['experience_rating']) . str_repeat('☆', 5 - $feedback['experience_rating']); ?></div>
                                        <div><b>Overall:</b> <?php echo str_repeat('★', $feedback['overall_rating']) . str_repeat('☆', 5 - $feedback['overall_rating']); ?></div>
                                    </div>
                                    <?php if ($feedback['strengths']): ?>
                                        <div><b>Strengths:</b><br><?php echo nl2br(htmlspecialchars($feedback['strengths'])); ?></div>
                                    <?php endif; ?>
                                    <?php if ($feedback['weaknesses']): ?>
                                        <div><b>Areas for Improvement:</b><br><?php echo nl2br(htmlspecialchars($feedback['weaknesses'])); ?></div>
                                    <?php endif; ?>
                                    <?php if ($feedback['feedback_notes']): ?>
                                        <div style="margin-top:8px;"><b>Additional Notes:</b><br><?php echo nl2br(htmlspecialchars($feedback['feedback_notes'])); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div style="font-size:0.8em;color:#666;margin-top:8px;">Last updated: <?php echo htmlspecialchars($interview['updated_at']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:0.9em;color:#888;margin-top:8px;">Applied: <?php echo htmlspecialchars($app['created_at']); ?></div>
                    <?php if ($app['status'] === 'Accepted'): ?>
                        <div style="margin-top:12px;display:flex;gap:8px;">
                            <button onclick="openInterviewModal(<?php echo $app['id']; ?>, '<?php echo htmlspecialchars($interview_status); ?>', '<?php echo htmlspecialchars($interview['interview_date'] ?? ''); ?>', '<?php echo htmlspecialchars($interview['interview_time'] ?? ''); ?>', '<?php echo htmlspecialchars($interview['interview_type'] ?? ''); ?>', '<?php echo htmlspecialchars($interview['location'] ?? ''); ?>', '<?php echo htmlspecialchars($interview['duration'] ?? ''); ?>', '<?php echo htmlspecialchars($interview['interviewer'] ?? ''); ?>', '<?php echo htmlspecialchars($interview['notes'] ?? ''); ?>');" 
                                    style="background:#4fc3f7;color:#222;border:none;padding:6px 14px;border-radius:6px;cursor:pointer;font-weight:bold;">
                                Update Interview
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div style="color:#ccc;text-align:center;padding:32px;background:#222;border-radius:8px;">
                No applications received yet.
            </div>
        <?php endif; ?>
    </div>
    <!-- Posted Jobs List -->
    <div style="width:95%;max-width:1000px;margin:32px auto 0 auto;">
        <h2 style="color:#4fc3f7;text-align:left;margin-bottom:12px;">Your Posted Jobs</h2>
        <?php if ($jobs && $jobs->num_rows > 0): ?>
            <div style="display:flex;flex-wrap:wrap;gap:18px;">
            <?php while($row = $jobs->fetch_assoc()): ?>
                <div style="background:#fff;color:#222;border-radius:8px;box-shadow:0 2px 8px #0002;padding:18px 22px;min-width:220px;max-width:320px;flex:1;position:relative;">
                    <div style="font-size:1.2em;font-weight:bold;margin-bottom:8px;color:#4fc3f7;"><?php echo htmlspecialchars($row['job']); ?></div>
                    <div><b>Company:</b> <?php echo htmlspecialchars($row['company']); ?></div>
                    <div><b>Requirements:</b> <?php echo nl2br(htmlspecialchars($row['requirements'])); ?></div>
                    <div><b>Salary:</b> <?php echo htmlspecialchars($row['salary']); ?></div>
                    <div style="font-size:0.9em;color:#888;margin-top:8px;">Posted: <?php echo htmlspecialchars($row['created_at']); ?></div>
                    <div style="margin-top:12px;display:flex;gap:8px;">
                        <button class="edit-btn" style="background:#4fc3f7;color:#222;border:none;padding:6px 14px;border-radius:6px;cursor:pointer;font-weight:bold;" 
                            onclick="openEditModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['company'])); ?>', '<?php echo htmlspecialchars(addslashes($row['job'])); ?>', '<?php echo htmlspecialchars(addslashes($row['requirements'])); ?>', '<?php echo htmlspecialchars(addslashes($row['salary'])); ?>'); return false;">Edit</button>
                        <a href="?delete=<?php echo $row['id']; ?>" onclick="return confirm('Delete this job post?');" style="background:#f44336;color:#fff;border:none;padding:7px 14px;border-radius:6px;cursor:pointer;font-weight:bold;text-decoration:none;">Delete</a>
                    </div>
                </div>
            <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div style="color:#ccc;text-align:center;padding:32px;background:#222;border-radius:8px;">
                No jobs posted yet. Click "Post New Job" to create your first job posting.
            </div>
        <?php endif; ?>
    </div>
    <div class="footer">
        <a href="#">Terms and Condition</a>
        <a href="#">Security & Privacy</a>
        <a href="#">About</a>
        <a href="#">Report</a>
    </div>
    <!-- Modal for Job Post -->
    <div id="postModal" class="modal">
        <div class="modal-content">
            <span class="close" id="closeModalBtn">&times;</span>
            <h2 id="modalTitle" style="text-align:center; margin-bottom: 18px;">Create Job Post</h2>
            <?php if (isset($_GET['success'])): ?>
                <div style="background: #4caf50; color: white; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                    Job posted successfully!
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div style="background: #f44336; color: white; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                    Error posting job. Please try again.
                </div>
            <?php endif; ?>
            <form id="jobPostForm" method="POST" autocomplete="off">
                <input type="hidden" id="edit_id" name="edit_id" value="">
                <label for="company">Company</label>
                <input type="text" id="company" name="company" required>
                <label for="job">Job Title</label>
                <input type="text" id="job" name="job" required>
                <label for="requirements">Requirements</label>
                <textarea id="requirements" name="requirements" rows="2" required></textarea>
                <label for="salary">Salary</label>
                <input type="text" id="salary" name="salary" required>
                <button type="submit">Save</button>
            </form>
        </div>
    </div>
    <!-- Add Interview Modal -->
    <div id="interviewModal" class="modal">
        <div class="modal-content">
            <span class="close" id="closeInterviewModal">&times;</span>
            <h2 style="text-align:center; margin-bottom: 18px;">Update Interview Status</h2>
            <form method="POST" id="interviewForm">
                <input type="hidden" name="application_id" id="interview_application_id">
                <input type="hidden" name="update_interview_status" value="1">
                
                <label for="interview_status">Interview Status</label>
                <select id="interview_status" name="interview_status" required style="width:100%;padding:8px;border-radius:8px;margin-bottom:12px;" onchange="updateFormFields()">
                    <option value="Pending">⏳ Pending</option>
                    <option value="Scheduled">📅 Scheduled</option>
                    <option value="Done">✓ Done</option>
                    <option value="Cancelled">❌ Cancelled</option>
                    <option value="Rescheduled">🔄 Rescheduled</option>
                </select>
                
                <div id="scheduledFields">
                    <label for="interview_type">Interview Type</label>
                    <select id="interview_type" name="interview_type" required style="width:100%;padding:8px;border-radius:8px;margin-bottom:12px;">
                        <option value="In-Person">👥 In-Person</option>
                        <option value="Online">💻 Online</option>
                        <option value="Phone">📞 Phone</option>
                    </select>
                    
                    <div style="display:flex;gap:12px;margin-bottom:12px;">
                        <div style="flex:1;">
                            <label for="interview_date">Date</label>
                            <input type="date" id="interview_date" name="interview_date" required style="width:100%;padding:8px;border-radius:8px;">
                        </div>
                        <div style="flex:1;">
                            <label for="interview_time">Time</label>
                            <input type="time" id="interview_time" name="interview_time" required style="width:100%;padding:8px;border-radius:8px;">
                        </div>
                    </div>
                    
                    <div style="display:flex;gap:12px;margin-bottom:12px;">
                        <div style="flex:1;">
                            <label for="interview_duration">Duration (minutes)</label>
                            <input type="number" id="interview_duration" name="interview_duration" min="15" step="15" value="60" required style="width:100%;padding:8px;border-radius:8px;">
                        </div>
                        <div style="flex:1;">
                            <label for="interviewer">Interviewer</label>
                            <input type="text" id="interviewer" name="interviewer" placeholder="Interviewer name" style="width:100%;padding:8px;border-radius:8px;">
                        </div>
                    </div>
                    
                    <label for="interview_location">Location/Meeting Link</label>
                    <input type="text" id="interview_location" name="interview_location" placeholder="Enter location or meeting link" style="width:100%;padding:8px;border-radius:8px;margin-bottom:12px;">
                </div>
                
                <label for="interview_notes">Notes</label>
                <textarea id="interview_notes" name="interview_notes" rows="3" placeholder="Add any additional notes about the interview" style="width:100%;padding:8px;border-radius:8px;margin-bottom:12px;"></textarea>
                
                <button type="submit" style="width:100%;background:#4fc3f7;color:#222;border:none;padding:12px;border-radius:8px;cursor:pointer;font-weight:bold;">Save Interview Status</button>
            </form>
        </div>
    </div>
    <!-- Add Feedback Modal -->
    <div id="feedbackModal" class="modal">
        <div class="modal-content">
            <span class="close" id="closeFeedbackModal">&times;</span>
            <h2 style="text-align:center; margin-bottom: 18px;">Interview Feedback</h2>
            <form method="POST" id="feedbackForm">
                <input type="hidden" name="interview_id" id="feedback_interview_id">
                <input type="hidden" name="submit_feedback" value="1">
                
                <div style="margin-bottom:16px;">
                    <label>Technical Skills</label>
                    <div class="rating">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <input type="radio" name="technical_rating" value="<?php echo $i; ?>" id="tech<?php echo $i; ?>" required>
                            <label for="tech<?php echo $i; ?>">★</label>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <div style="margin-bottom:16px;">
                    <label>Communication Skills</label>
                    <div class="rating">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <input type="radio" name="communication_rating" value="<?php echo $i; ?>" id="comm<?php echo $i; ?>" required>
                            <label for="comm<?php echo $i; ?>">★</label>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <div style="margin-bottom:16px;">
                    <label>Experience Level</label>
                    <div class="rating">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <input type="radio" name="experience_rating" value="<?php echo $i; ?>" id="exp<?php echo $i; ?>" required>
                            <label for="exp<?php echo $i; ?>">★</label>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <div style="margin-bottom:16px;">
                    <label>Overall Rating</label>
                    <div class="rating">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <input type="radio" name="overall_rating" value="<?php echo $i; ?>" id="overall<?php echo $i; ?>" required>
                            <label for="overall<?php echo $i; ?>">★</label>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <label for="strengths">Key Strengths</label>
                <textarea id="strengths" name="strengths" rows="2" placeholder="List the candidate's key strengths" style="width:100%;padding:8px;border-radius:8px;margin-bottom:12px;"></textarea>
                
                <label for="weaknesses">Areas for Improvement</label>
                <textarea id="weaknesses" name="weaknesses" rows="2" placeholder="List areas where the candidate can improve" style="width:100%;padding:8px;border-radius:8px;margin-bottom:12px;"></textarea>
                
                <label for="feedback_notes">Additional Notes</label>
                <textarea id="feedback_notes" name="feedback_notes" rows="3" placeholder="Add any additional feedback or observations" style="width:100%;padding:8px;border-radius:8px;margin-bottom:12px;"></textarea>
                
                <button type="submit" style="width:100%;background:#4caf50;color:#fff;border:none;padding:12px;border-radius:8px;cursor:pointer;font-weight:bold;">Submit Feedback</button>
            </form>
        </div>
    </div>
    <script>
    // Modal logic
    document.getElementById('openModalBtn').onclick = function() {
        document.getElementById('postModal').style.display = 'block';
    };
    document.getElementById('closeModalBtn').onclick = function() {
        document.getElementById('postModal').style.display = 'none';
    };
    window.onclick = function(event) {
        var postModal = document.getElementById('postModal');
        var interviewModal = document.getElementById('interviewModal');
        var feedbackModal = document.getElementById('feedbackModal');
        if (event.target == postModal) {
            postModal.style.display = 'none';
        }
        if (event.target == interviewModal) {
            interviewModal.style.display = 'none';
        }
        if (event.target == feedbackModal) {
            feedbackModal.style.display = 'none';
        }
    };
    
    function openEditModal(id, company, job, requirements, salary) {
        document.getElementById('postModal').style.display = 'block';
        document.getElementById('modalTitle').innerText = 'Edit Job Post';
        document.getElementById('edit_id').value = id;
        document.getElementById('company').value = company;
        document.getElementById('job').value = job;
        document.getElementById('requirements').value = requirements;
        document.getElementById('salary').value = salary;
    }
    // Show/hide applications list
    function showApplications() {
        const applicationsList = document.getElementById('applicationsList');
        applicationsList.style.display = applicationsList.style.display === 'none' ? 'block' : 'none';
        
        // Scroll to applications list
        if (applicationsList.style.display === 'block') {
            applicationsList.scrollIntoView({ behavior: 'smooth' });
        }
    }

    // Function to view resume
    function viewResume(resumeUrl) {
        if (resumeUrl) {
            window.open(resumeUrl, '_blank');
        } else {
            alert('No resume available for this candidate.');
        }
    }

    // Function to schedule interview
    function scheduleInterview(applicationId) {
        const date = prompt('Enter interview date (YYYY-MM-DD):');
        const time = prompt('Enter interview time (HH:MM):');
        if (date && time) {
            // Here you would typically make an AJAX call to save the interview details
            alert(`Interview scheduled for ${date} at ${time}`);
        }
    }

    function openInterviewModal(applicationId, status, date, time, type, location, duration, interviewer, notes) {
        document.getElementById('interviewModal').style.display = 'block';
        document.getElementById('interview_application_id').value = applicationId;
        document.getElementById('interview_status').value = status;
        document.getElementById('interview_date').value = date;
        document.getElementById('interview_time').value = time;
        document.getElementById('interview_type').value = type;
        document.getElementById('interview_location').value = location;
        document.getElementById('interview_duration').value = duration || 60;
        document.getElementById('interviewer').value = interviewer;
        document.getElementById('interview_notes').value = notes;
    }

    document.getElementById('closeInterviewModal').onclick = function() {
        document.getElementById('interviewModal').style.display = 'none';
    }

    function updateFormFields() {
        const status = document.getElementById('interview_status').value;
        const scheduledFields = document.getElementById('scheduledFields');
        const requiredFields = scheduledFields.querySelectorAll('[required]');
        
        if (status === 'Scheduled' || status === 'Rescheduled') {
            scheduledFields.style.display = 'block';
            requiredFields.forEach(field => field.required = true);
        } else {
            scheduledFields.style.display = 'none';
            requiredFields.forEach(field => field.required = false);
        }
    }

    // Initialize form fields on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateFormFields();
    });

    function openFeedbackModal(interviewId) {
        document.getElementById('feedbackModal').style.display = 'block';
        document.getElementById('feedback_interview_id').value = interviewId;
    }

    document.getElementById('closeFeedbackModal').onclick = function() {
        document.getElementById('feedbackModal').style.display = 'none';
    }
    </script>
    <!-- Add search results summary -->
    <div style="color:yellow;background:#222;padding:8px;margin:8px 32px;">
        Search Results: 
        <?php if (!empty($search_query)): ?>
            Found <?php echo $jobs->num_rows; ?> jobs and <?php echo $applications->num_rows; ?> applications matching "<?php echo $search_query; ?>"
        <?php else: ?>
            Showing all jobs and applications
        <?php endif; ?>
    </div>
</body>
</html> 