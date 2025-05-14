<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Redirect employers to dashboard
if (isset($_SESSION['user_id']) && strtolower($_SESSION['user_type']) === 'employer') {
    header('Location: dashboard.php');
    exit();
}

// Database connection
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'jpost';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

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

// Handle job application
if (isset($_POST['apply_job']) && isset($_SESSION['user_id'])) {
    $job_id = (int)$_POST['job_id'];
    $user_id = $_SESSION['user_id'];
    
    // Initialize statements
    $stmt = null;
    $user_stmt = null;
    $applicant_stmt = null;
    
    // Check if already applied
    $check_sql = "SELECT id FROM job_applications WHERE job_id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $job_id, $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert into job_applications
            $sql = "INSERT INTO job_applications (job_id, user_id, status) VALUES (?, ?, 'Pending')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $job_id, $user_id);
            $stmt->execute();
            
            // Get user details
            $user_sql = "SELECT username FROM users WHERE id = ?";
            $user_stmt = $conn->prepare($user_sql);
            $user_stmt->bind_param("i", $user_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            $user_data = $user_result->fetch_assoc();
            
            // Insert into applicants table
            $applicant_sql = "INSERT INTO applicants (name, job_id, status1, user_id) VALUES (?, ?, 'In Review', ?)";
            $applicant_stmt = $conn->prepare($applicant_sql);
            $applicant_stmt->bind_param("sii", 
                $user_data['username'],
                $job_id,
                $user_id
            );
            $applicant_stmt->execute();
            
            // Commit transaction
            $conn->commit();
            $success_message = "Application submitted successfully! You can view your application status in your account.";
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = "Error submitting application: " . $e->getMessage();
        } finally {
            // Close all statements if they exist
            if ($stmt !== null) $stmt->close();
            if ($user_stmt !== null) $user_stmt->close();
            if ($applicant_stmt !== null) $applicant_stmt->close();
        }
    } else {
        $error_message = "You have already applied for this job.";
    }
    $check_stmt->close();
}

// Add search functionality
$search_query = '';
$job_type = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $search_query = htmlspecialchars($_GET['search']);
}

if (isset($_GET['type']) && !empty($_GET['type'])) {
    $job_type = $conn->real_escape_string($_GET['type']);
}

// Add advanced search parameters
$salary_min = isset($_GET['salary_min']) ? (int)$_GET['salary_min'] : '';
$salary_max = isset($_GET['salary_max']) ? (int)$_GET['salary_max'] : '';
$location = isset($_GET['location']) ? $conn->real_escape_string($_GET['location']) : '';
$experience = isset($_GET['experience']) ? $conn->real_escape_string($_GET['experience']) : '';

// Modify the jobs query to include applicant count and handle all filters
$jobs_query = "SELECT j.*, 
               (SELECT COUNT(*) FROM applicants a WHERE a.job_id = j.id AND a.status1 IN ('Interview', 'On Demand')) as active_applicants
               FROM jobs j 
               WHERE 1=1 
               AND NOT EXISTS (
                   SELECT 1 FROM applicants a 
                   WHERE a.job_id = j.id 
                   AND a.status1 IN ('Accepted', 'Cancelled')
               )";

// Add search conditions
if (!empty($search_query)) {
    $jobs_query .= " AND (j.job LIKE '%$search%' OR j.company LIKE '%$search%' OR j.requirements LIKE '%$search%' OR j.salary LIKE '%$search%')";
}

// Add job type filter
if (!empty($job_type)) {
    $jobs_query .= " AND j.job_type = '$job_type'";
}

// Add location filter
if (!empty($location)) {
    $jobs_query .= " AND j.address LIKE '%$location%'";
}

// Add experience level filter
if (!empty($experience)) {
    $jobs_query .= " AND j.requirements LIKE '%$experience%'";
}

// Add salary range filters
if (!empty($salary_min)) {
    $jobs_query .= " AND CAST(REPLACE(REPLACE(j.salary, '₱', ''), ',', '') AS DECIMAL) >= $salary_min";
}
if (!empty($salary_max)) {
    $jobs_query .= " AND CAST(REPLACE(REPLACE(j.salary, '₱', ''), ',', '') AS DECIMAL) <= $salary_max";
}

$jobs_query .= " ORDER BY j.created_at DESC";
$jobs = $conn->query($jobs_query);

// Fetch all jobs
echo '<div style="color:yellow;background:#222;padding:8px;">Jobs found: ' . ($jobs ? $jobs->num_rows : 0) . '</div>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore - JPOST</title>
    <style>
        body {
            background: linear-gradient(135deg, #181818 60%, #232a34 100%);
            color: #fff;
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            background: #181818cc;
            border-bottom: 2px solid #333;
            height: 60px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
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
            position: relative;
        }
        .navbar nav a:hover, .navbar nav a.active {
            color: #4fc3f7;
        }
        .navbar .search {
            display: flex;
            align-items: center;
            background: #fff;
            border-radius: 20px;
            padding: 4px 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
            width: 300px;
        }
        .navbar .search input {
            background: transparent;
            border: none;
            color: #222;
            outline: none;
            padding: 6px 8px;
            font-size: 1em;
            width: 100%;
        }
        .navbar .search button {
            background: none;
            border: none;
            color: #222;
            cursor: pointer;
            font-size: 1.2em;
            padding: 4px 8px;
        }
        .navbar .settings {
            margin-left: 18px;
            font-size: 1.7em;
            color: #4fc3f7;
            cursor: pointer;
        }
        .explore-container {
            margin: 48px auto 0 auto;
            width: 98%;
            max-width: 1400px;
            min-width: 320px;
            background: #232a34ee;
            border-radius: 20px;
            border: 2px solid #fff;
            padding: 36px 0 0 0;
            min-height: 480px;
            position: relative;
            box-shadow: 0 8px 32px rgba(0,0,0,0.18);
        }
        .explore-title {
            text-align: center;
            font-size: 2em;
            font-weight: bold;
            letter-spacing: 1px;
            margin-bottom: 18px;
            color: #4fc3f7;
            text-shadow: 0 2px 8px #0002;
        }
        .explore-content {
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            gap: 32px;
            padding: 0 32px 32px 32px;
        }
        .explore-left {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 18px;
            margin-top: 18px;
        }
        .explore-left img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #fff;
            object-fit: cover;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
        }
        .explore-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .explore-search-bar {
            background: #fff;
            color: #222;
            border-radius: 16px;
            padding: 14px 28px;
            margin-bottom: 32px;
            margin-top: 18px;
            font-size: 1.2em;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 360px;
            max-width: 90vw;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
        }
        .explore-search-bar .search-icon {
            font-size: 1.3em;
            margin-left: 12px;
        }
        .explore-bubbles {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            justify-content: center;
            margin-bottom: 32px;
        }
        .bubble {
            padding: 12px 32px;
            border-radius: 24px;
            color: #fff;
            font-size: 1.15em;
            font-weight: 500;
            margin: 0 6px 12px 0;
            display: inline-block;
            cursor: pointer;
            transition: transform 0.13s, box-shadow 0.13s, background 0.13s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
            border: 2px solid transparent;
        }
        .bubble:hover {
            transform: scale(1.09) translateY(-2px);
            box-shadow: 0 6px 24px rgba(0,0,0,0.18);
            border: 2px solid #fff;
            background: #222 !important;
            color: #4fc3f7 !important;
        }
        .bubble1 { background: #c2185b; }
        .bubble2 { background: #7b1fa2; }
        .bubble3 { background: #00bcd4; color: #222; }
        .bubble4 { background: #8d6e63; }
        .bubble5 { background: #d84315; }
        .bubble6 { background: #fbc02d; color: #222; }
        .bubble7 { background: #1976d2; }
        .bubble8 { background: #388e3c; }
        .bubble9 { background: #512da8; }
        .bubble10 { background: #388e3c; }
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
            .explore-content {
                flex-direction: column;
                gap: 0;
                padding: 0 8px 32px 8px;
            }
            .explore-main {
                align-items: center;
            }
            .explore-search-bar {
                width: 98vw;
                max-width: 98vw;
            }
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: #232a34;
            padding: 24px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            text-align: center;
            border: 2px solid #4fc3f7;
        }
        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-top: 20px;
        }
        .modal-button {
            padding: 8px 24px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .confirm-button {
            background: #4fc3f7;
            color: #222;
        }
        .cancel-button {
            background: #666;
            color: #fff;
        }
        .modal-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .message {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            border-radius: 8px;
            color: #fff;
            z-index: 1001;
            animation: slideIn 0.5s ease-out;
        }
        .success-message {
            background: rgba(76, 175, 80, 0.9);
        }
        .error-message {
            background: rgba(244, 67, 54, 0.9);
        }
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        .bubble.active {
            border: 2px solid #fff;
            background: #222 !important;
            color: #4fc3f7 !important;
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
            <a href="explore.php" class="active">Explore</a>
            <a href="account.php">Account</a>
        </nav>
        <div style="display:flex; align-items:center;">
            <form action="explore.php" method="GET" class="search">
                <input type="text" name="search" placeholder="Search jobs..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit">&#128269;</button>
            </form>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="logout.php" style="color:#fff; text-decoration:none; margin-left:18px; background:#f44336; padding:8px 16px; border-radius:4px;">Logout</a>
            <?php else: ?>
                <a href="login.php" style="color:#fff; text-decoration:none; margin-left:18px;">Login</a>
                <a href="signup.php" style="background:#4fc3f7; color:#222; padding:8px 16px; border-radius:16px; text-decoration:none; font-weight:bold;">Sign Up</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="explore-container">
        <div class="explore-title">Explore Opportunities</div>
        <div class="explore-content">
            <div class="explore-left">
                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/4/4a/Logo_of_the_Department_of_Labor_and_Employment_%28DOLE%29.svg/1200px-Logo_of_the_Department_of_Labor_and_Employment_%28DOLE%29.svg.png" alt="DOLE Logo" style="width:60px; height:60px;">
                <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" alt="People Icon" style="width:60px; height:60px;">
            </div>
            <div class="explore-main">
                <div class="explore-search-bar" style="margin-bottom: 12px;">
                    <form action="explore.php" method="GET" style="display:flex;width:100%;align-items:center;gap:12px;">
                        <input type="text" name="search" placeholder="Search jobs, locations, or skills..." value="<?php echo $search_query; ?>" style="border:none;outline:none;background:transparent;width:80%;font-size:1.1em;color:#222;">
                        <button type="submit" style="background:none;border:none;cursor:pointer;font-size:1.3em;color:#222;">&#128269;</button>
                    </form>
                </div>

                <div class="advanced-search" style="background:#fff;border-radius:12px;padding:16px;margin:0 32px 24px 32px;">
                    <form action="explore.php" method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
                        <?php if (!empty($search_query)): ?>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                        <?php endif; ?>
                        <?php if (!empty($job_type)): ?>
                            <input type="hidden" name="type" value="<?php echo htmlspecialchars($job_type); ?>">
                        <?php endif; ?>
                        
                        <div>
                            <label style="display:block;color:#222;margin-bottom:4px;font-weight:500;">Salary Range (₱)</label>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input type="number" name="salary_min" placeholder="Min" value="<?php echo $salary_min; ?>" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
                                <span style="color:#666;">to</span>
                                <input type="number" name="salary_max" placeholder="Max" value="<?php echo $salary_max; ?>" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
                            </div>
                        </div>
                        
                        <div>
                            <label style="display:block;color:#222;margin-bottom:4px;font-weight:500;">Location</label>
                            <input type="text" name="location" placeholder="Enter location" value="<?php echo htmlspecialchars($location); ?>" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
                        </div>
                        
                        <div>
                            <label style="display:block;color:#222;margin-bottom:4px;font-weight:500;">Experience Level</label>
                            <select name="experience" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
                                <option value="">Any Experience</option>
                                <option value="Entry Level" <?php echo $experience === 'Entry Level' ? 'selected' : ''; ?>>Entry Level</option>
                                <option value="Mid Level" <?php echo $experience === 'Mid Level' ? 'selected' : ''; ?>>Mid Level</option>
                                <option value="Senior Level" <?php echo $experience === 'Senior Level' ? 'selected' : ''; ?>>Senior Level</option>
                            </select>
                        </div>
                        
                        <div style="display:flex;align-items:flex-end;gap:8px;">
                            <button type="submit" style="background:#4fc3f7;color:#222;border:none;padding:8px 24px;border-radius:6px;font-weight:bold;cursor:pointer;width:100%;">Apply Filters</button>
                            <a href="explore.php<?php echo !empty($search_query) ? '?search=' . urlencode($search_query) : ''; ?>" style="background:#666;color:#fff;border:none;padding:8px 24px;border-radius:6px;font-weight:bold;cursor:pointer;text-decoration:none;text-align:center;">Clear Filters</a>
                        </div>
                    </form>
                </div>

                <div class="explore-bubbles">
                    <a href="explore.php<?php echo !empty($search_query) ? '?search=' . urlencode($search_query) : ''; ?>" class="bubble bubble1 <?php echo empty($job_type) ? 'active' : ''; ?>">All Jobs</a>
                    <a href="explore.php?type=Full Time<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" class="bubble bubble2 <?php echo $job_type === 'Full Time' ? 'active' : ''; ?>">Full Time</a>
                    <a href="explore.php?type=Part Time<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" class="bubble bubble3 <?php echo $job_type === 'Part Time' ? 'active' : ''; ?>">Part Time</a>
                    <a href="explore.php?type=Remote<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" class="bubble bubble4 <?php echo $job_type === 'Remote' ? 'active' : ''; ?>">Remote</a>
                    <a href="explore.php?type=Internship<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" class="bubble bubble5 <?php echo $job_type === 'Internship' ? 'active' : ''; ?>">Internship</a>
                </div>
                
                <!-- Jobs List -->
                <div style="width:100%;display:flex;flex-wrap:wrap;gap:18px;padding:0 18px;">
                    <?php if ($jobs && $jobs->num_rows > 0): ?>
                        <?php while($row = $jobs->fetch_assoc()): ?>
                            <div style="background:#fff;color:#222;border-radius:8px;box-shadow:0 2px 8px #0002;padding:18px 22px;min-width:220px;max-width:320px;flex:1;position:relative;">
                                <?php if ($row['active_applicants'] > 0): ?>
                                    <div style="position:absolute;top:12px;right:12px;background:#ff9800;color:#fff;padding:4px 8px;border-radius:4px;font-size:0.8em;">
                                        <?php echo $row['active_applicants']; ?> Active Applicant<?php echo $row['active_applicants'] > 1 ? 's' : ''; ?>
                                    </div>
                                <?php endif; ?>
                                <div style="font-size:1.2em;font-weight:bold;margin-bottom:8px;color:#4fc3f7;">
                                    <?php echo htmlspecialchars($row['job']); ?>
                                </div>
                                <div><b>Company:</b> <?php echo htmlspecialchars($row['company']); ?></div>
                                <div><b>Requirements:</b> <?php echo nl2br(htmlspecialchars($row['requirements'])); ?></div>
                                <div><b>Salary:</b> <?php echo htmlspecialchars($row['salary']); ?></div>
                                <div><b>Job Type:</b> <?php echo htmlspecialchars($row['job_type']); ?></div>
                                <?php if (!empty($row['hours_of_duty'])): ?>
                                    <div><b>Hours of Duty:</b> <?php echo htmlspecialchars($row['hours_of_duty']); ?></div>
                                <?php endif; ?>
                                <div style="font-size:0.9em;color:#888;margin-top:8px;">Posted: <?php echo date('M d, Y', strtotime($row['created_at'])); ?></div>
                                <div style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end;">
                                    <button onclick="applyForJob(<?php echo $row['id']; ?>)" style="background:#4fc3f7;color:#fff;border:none;padding:7px 14px;border-radius:6px;cursor:pointer;font-weight:bold;transition:background 0.2s;">Apply Now</button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="text-align:center;color:#ccc;padding:32px;background:#222;border-radius:12px;width:100%;">
                            <h3 style="margin:0 0 12px 0;color:#4fc3f7;">No Jobs Available</h3>
                            <p style="margin:0;">Check back later for new job opportunities.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="footer">
            <a href="#">Security & Privacy</a>
            <a href="#">Terms and Condition</a>
            <a href="#">About</a>
            <a href="#">Report</a>
        </div>
    </div>

    <!-- Application Modal -->
    <div id="applicationModal" class="modal">
        <div class="modal-content">
            <h3 style="margin: 0 0 16px 0; color: #4fc3f7;">Confirm Application</h3>
            <p>Are you sure you want to apply for this position?</p>
            <form method="POST" id="applicationForm">
                <input type="hidden" name="job_id" id="jobIdInput">
                <input type="hidden" name="apply_job" value="1">
                <div class="modal-buttons">
                    <button type="submit" class="modal-button confirm-button">Yes, Apply</button>
                    <button type="button" class="modal-button cancel-button" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="message success-message"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if (isset($error_message)): ?>
        <div class="message error-message"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div style="color:yellow;background:#222;padding:8px;margin:8px 32px;">
        Search Results: 
        <?php if (!empty($search_query) || !empty($job_type) || !empty($location) || !empty($experience) || !empty($salary_min) || !empty($salary_max)): ?>
            Found <?php echo $jobs->num_rows; ?> jobs
            <?php if (!empty($search_query)): ?>
                matching "<?php echo $search_query; ?>"
            <?php endif; ?>
            <?php if (!empty($job_type)): ?>
                in <?php echo $job_type; ?>
            <?php endif; ?>
            <?php if (!empty($location)): ?>
                near <?php echo htmlspecialchars($location); ?>
            <?php endif; ?>
            <?php if (!empty($experience)): ?>
                requiring <?php echo htmlspecialchars($experience); ?> experience
            <?php endif; ?>
            <?php if (!empty($salary_min) || !empty($salary_max)): ?>
                with salary range 
                <?php 
                    if (!empty($salary_min) && !empty($salary_max)) {
                        echo "₱" . number_format($salary_min) . " - ₱" . number_format($salary_max);
                    } elseif (!empty($salary_min)) {
                        echo "from ₱" . number_format($salary_min);
                    } elseif (!empty($salary_max)) {
                        echo "up to ₱" . number_format($salary_max);
                    }
                ?>
            <?php endif; ?>
        <?php else: ?>
            Showing all jobs
        <?php endif; ?>
    </div>

    <script>
    function applyForJob(jobId) {
        <?php if (!isset($_SESSION['user_id'])): ?>
            alert('Please login to apply for jobs');
            window.location.href = 'login.php';
        <?php else: ?>
            document.getElementById('jobIdInput').value = jobId;
            document.getElementById('applicationModal').style.display = 'flex';
        <?php endif; ?>
    }

    function closeModal() {
        document.getElementById('applicationModal').style.display = 'none';
    }

    // Close message after 3 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const messages = document.querySelectorAll('.message');
        messages.forEach(message => {
            setTimeout(() => {
                message.style.opacity = '0';
                setTimeout(() => message.remove(), 500);
            }, 3000);
        });
    });

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('applicationModal');
        if (event.target == modal) {
            closeModal();
        }
    }

    // Add this to ensure the modal is properly displayed
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('applicationModal');
        if (modal) {
            modal.style.display = 'none';
        }
    });
    </script>
</body>
</html> 