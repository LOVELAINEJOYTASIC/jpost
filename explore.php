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

// Redirect admins to admin page
if (isset($_SESSION['user_id']) && strtolower($_SESSION['user_type']) === 'admin') {
    header('Location: admin.php');
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
    
    // Check if already applied
    $check_sql = "SELECT id FROM job_applications WHERE job_id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $job_id, $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        $sql = "INSERT INTO job_applications (job_id, user_id, status) VALUES (?, ?, 'Pending')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $job_id, $user_id);
        
        if ($stmt->execute()) {
            $success_message = "Application submitted successfully! You can view your application status in your account.";
        } else {
            $error_message = "Error submitting application: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error_message = "You have already applied for this job.";
    }
    $check_stmt->close();
}

// Fetch all jobs
$jobs = $conn->query("SELECT * FROM jobs ORDER BY created_at DESC");
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
        .explore-container {
            margin: 48px auto 0 auto;
            width: 95%;
            max-width: 950px;
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
            max-width: 400px;
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
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="logout.php" style="color:#fff; text-decoration:none; margin-right:18px; background:#f44336; padding:8px 16px; border-radius:4px;">Logout</a>
            <?php else: ?>
                <a href="login.php" style="color:#fff; text-decoration:none; margin-right:18px;">Login</a>
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
                <div class="explore-search-bar">
                    <input type="text" placeholder="Search jobs, locations, or skills..." style="border:none;outline:none;background:transparent;width:80%;font-size:1.1em;color:#222;">
                    <span class="search-icon">&#128269;</span>
                </div>
                <div class="explore-bubbles">
                    <div class="bubble bubble1">All Jobs</div>
                    <div class="bubble bubble2">Full Time</div>
                    <div class="bubble bubble3">Part Time</div>
                    <div class="bubble bubble4">Remote</div>
                    <div class="bubble bubble5">Internship</div>
                </div>
                
                <!-- Jobs List -->
                <div style="width:100%;display:flex;flex-wrap:wrap;gap:18px;padding:0 18px;">
                    <?php if ($jobs && $jobs->num_rows > 0): ?>
                        <?php while($row = $jobs->fetch_assoc()): ?>
                            <div style="background:#fff;color:#222;border-radius:8px;box-shadow:0 2px 8px #0002;padding:18px 22px;min-width:220px;max-width:320px;flex:1;position:relative;">
                                <div style="font-size:1.2em;font-weight:bold;margin-bottom:8px;color:#4fc3f7;">
                                    <?php echo htmlspecialchars($row['job']); ?>
                                </div>
                                <div><b>Company:</b> <?php echo htmlspecialchars($row['company']); ?></div>
                                <div><b>Requirements:</b> <?php echo nl2br(htmlspecialchars($row['requirements'])); ?></div>
                                <div><b>Salary:</b> <?php echo htmlspecialchars($row['salary']); ?></div>
                                <div style="font-size:0.9em;color:#888;margin-top:8px;">Posted: <?php echo htmlspecialchars($row['created_at']); ?></div>
                                <div style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end;">
                                    <button onclick="applyForJob(<?php echo $row['id']; ?>)" style="background:#4fc3f7;color:#fff;border:none;padding:7px 14px;border-radius:6px;cursor:pointer;font-weight:bold;transition:background 0.2s;">Apply Now</button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="text-align:center;color:#ccc;padding:32px;background:#222;border-radius:12px;">
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

    <div id="applicationModal" class="modal">
        <div class="modal-content">
            <h3 style="margin: 0 0 16px 0; color: #4fc3f7;">Confirm Application</h3>
            <p>Are you sure you want to apply for this position?</p>
            <div class="modal-buttons">
                <button class="modal-button confirm-button" onclick="submitApplication()">Yes, Apply</button>
                <button class="modal-button cancel-button" onclick="closeModal()">Cancel</button>
            </div>
        </div>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="message success-message"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if (isset($error_message)): ?>
        <div class="message error-message"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <script>
    let currentJobId = null;

    function applyForJob(jobId) {
        <?php if (!isset($_SESSION['username'])): ?>
            alert('Please login to apply for jobs');
            window.location.href = 'login.php';
        <?php else: ?>
            currentJobId = jobId;
            document.getElementById('applicationModal').style.display = 'flex';
        <?php endif; ?>
    }

    function closeModal() {
        document.getElementById('applicationModal').style.display = 'none';
        currentJobId = null;
    }

    function submitApplication() {
        if (currentJobId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const jobIdInput = document.createElement('input');
            jobIdInput.type = 'hidden';
            jobIdInput.name = 'job_id';
            jobIdInput.value = currentJobId;
            
            const applyInput = document.createElement('input');
            applyInput.type = 'hidden';
            applyInput.name = 'apply_job';
            applyInput.value = '1';
            
            form.appendChild(jobIdInput);
            form.appendChild(applyInput);
            document.body.appendChild(form);
            form.submit();
        }
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
    </script>
</body>
</html> 