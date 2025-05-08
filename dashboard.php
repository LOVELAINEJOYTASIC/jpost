<?php
<<<<<<< HEAD
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Create database if it doesn't exist
=======

>>>>>>> 376c003af479d721c1dc08bed472e3e7d386750b
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
<<<<<<< HEAD
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
// Handle delete
=======

>>>>>>> 376c003af479d721c1dc08bed472e3e7d386750b
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
<<<<<<< HEAD
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
=======

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['edit_id']) && isset($_POST['company'], $_POST['job'], $_POST['requirements'], $_POST['salary'])) {
    $company = $conn->real_escape_string($_POST['company']);
    $job = $conn->real_escape_string($_POST['job']);
    $requirements = $conn->real_escape_string($_POST['requirements']);
    $salary = $conn->real_escape_string($_POST['salary']);
    $conn->query("INSERT INTO jobs (company, job, requirements, salary) VALUES ('$company', '$job', '$requirements', '$salary')");
    header('Location: dashboard.php');
    exit();
>>>>>>> 376c003af479d721c1dc08bed472e3e7d386750b
}

$jobs = $conn->query("SELECT * FROM jobs ORDER BY created_at DESC");
// Fetch all applications for the employer's jobs
$applications_sql = "SELECT ja.*, j.job, j.company, u.username, up.full_name, up.contact 
                    FROM job_applications ja 
                    INNER JOIN jobs j ON ja.job_id = j.id 
                    INNER JOIN users u ON ja.user_id = u.id 
                    LEFT JOIN user_profiles up ON u.id = up.user_id 
                    ORDER BY ja.created_at DESC";
$applications = $conn->query($applications_sql);
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
            <div class="search">
                <input type="text" placeholder="Find your dream job at JPost">
                <button>&#128269;</button>
            </div>
            <span class="settings">&#9881;</span>
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
<<<<<<< HEAD
    <!-- Applications List -->
    <div id="applicationsList" style="width:95%;max-width:1000px;margin:32px auto 0 auto;display:none;">
        <h2 style="color:#4fc3f7;text-align:left;margin-bottom:12px;">Job Applications</h2>
        <?php if ($applications && $applications->num_rows > 0): ?>
            <div style="display:flex;flex-wrap:wrap;gap:18px;">
            <?php while($app = $applications->fetch_assoc()): ?>
                <div style="background:#fff;color:#222;border-radius:8px;box-shadow:0 2px 8px #0002;padding:18px 22px;min-width:220px;max-width:320px;flex:1;position:relative;">
                    <div style="font-size:1.2em;font-weight:bold;margin-bottom:8px;color:#4fc3f7;"><?php echo htmlspecialchars($app['job']); ?></div>
                    <div><b>Applicant:</b> <?php echo htmlspecialchars($app['full_name'] ?? $app['username']); ?></div>
                    <div><b>Contact:</b> <?php echo htmlspecialchars($app['contact'] ?? 'Not provided'); ?></div>
                    <div><b>Status:</b> <span style="color: <?php echo $app['status'] === 'Accepted' ? '#4caf50' : ($app['status'] === 'Rejected' ? '#f44336' : '#ff9800'); ?>"><?php echo htmlspecialchars($app['status']); ?></span></div>
                    <div style="font-size:0.9em;color:#888;margin-top:8px;">Applied: <?php echo htmlspecialchars($app['created_at']); ?></div>
                    <?php if ($app['status'] === 'Pending'): ?>
                        <div style="margin-top:12px;display:flex;gap:8px;">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                <input type="hidden" name="status" value="Accepted">
                                <input type="hidden" name="update_status" value="1">
                                <button type="submit" style="background:#4caf50;color:#fff;border:none;padding:6px 14px;border-radius:6px;cursor:pointer;font-weight:bold;">Accept</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                <input type="hidden" name="status" value="Rejected">
                                <input type="hidden" name="update_status" value="1">
                                <button type="submit" style="background:#f44336;color:#fff;border:none;padding:6px 14px;border-radius:6px;cursor:pointer;font-weight:bold;">Reject</button>
                            </form>
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
=======

>>>>>>> 376c003af479d721c1dc08bed472e3e7d386750b
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
    <script>
    // Modal logic
    document.getElementById('openModalBtn').onclick = function() {
        document.getElementById('postModal').style.display = 'block';
    };
    document.getElementById('closeModalBtn').onclick = function() {
        document.getElementById('postModal').style.display = 'none';
    };
    window.onclick = function(event) {
        var modal = document.getElementById('postModal');
        if (event.target == modal) {
            modal.style.display = 'none';
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
    }
    </script>
</body>
</html> 