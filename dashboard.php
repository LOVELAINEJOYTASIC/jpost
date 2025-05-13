<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Check if user is logged in and is an employer
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['user_type']) !== 'employer') {
    header('Location: login.php');
    exit();
}

require_once 'config.php';
$conn = getDBConnection();

// Handle new job posting
if (isset($_POST['post_new_job'])) {
    $job_title = $conn->real_escape_string($_POST['job_title']);
    $company = $conn->real_escape_string($_POST['company']);
    $requirements = $conn->real_escape_string($_POST['requirements']);
    $salary = $conn->real_escape_string($_POST['salary']);
    $address = $conn->real_escape_string($_POST['address']);
    
    $insert_sql = "INSERT INTO jobs (job, company, requirements, salary, address) VALUES (?, ?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("sssss", $job_title, $company, $requirements, $salary, $address);
    
    if ($insert_stmt->execute()) {
        header('Location: dashboard.php?success=posted');
        exit();
    } else {
        $error = "Error posting job: " . $insert_stmt->error;
    }
    $insert_stmt->close();
}

// Handle job deletion
if (isset($_POST['delete_job'])) {
    $job_id = (int)$_POST['delete_job'];
    $delete_sql = "DELETE FROM jobs WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $job_id);
    if ($delete_stmt->execute()) {
        header('Location: dashboard.php?success=deleted');
        exit();
    }
    $delete_stmt->close();
}

// Handle job editing
if (isset($_POST['edit_job'])) {
    $job_id = (int)$_POST['edit_job'];
    $job_title = $conn->real_escape_string($_POST['job_title']);
    $company = $conn->real_escape_string($_POST['company']);
    $requirements = $conn->real_escape_string($_POST['requirements']);
    $salary = $conn->real_escape_string($_POST['salary']);
    $address = $conn->real_escape_string($_POST['address']);
    
    $update_sql = "UPDATE jobs SET job = ?, company = ?, requirements = ?, salary = ?, address = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sssssi", $job_title, $company, $requirements, $salary, $address, $job_id);
    if ($update_stmt->execute()) {
        header('Location: dashboard.php?success=updated');
        exit();
    }
    $update_stmt->close();
}

// Handle interview status update
if (isset($_POST['update_interview_status'])) {
    $applicant_id = (int)$_POST['applicant_id'];
    $interview_status = $conn->real_escape_string($_POST['interview_status']);
    
    $update_sql = "UPDATE applicants SET status2 = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $interview_status, $applicant_id);
    
    if ($update_stmt->execute()) {
        header('Location: dashboard.php?success=interview_updated');
        exit();
    }
    $update_stmt->close();
}

// Fetch employer's posted jobs
$jobs_sql = "SELECT * FROM jobs ORDER BY created_at DESC";
$jobs_stmt = $conn->prepare($jobs_sql);
$jobs_stmt->execute();
$jobs_result = $jobs_stmt->get_result();

// Fetch all applicants for employer's jobs
$applicants_sql = "SELECT a.*, j.job as job_title, j.company 
                  FROM applicants a 
                  JOIN jobs j ON a.job_id = j.id 
                  WHERE a.status1 IN ('In Review', 'In Process', 'Interview', 'On Demand', 'Accepted', 'Cancelled', 'In Waiting')
                  ORDER BY a.created_at DESC";
$applicants_stmt = $conn->prepare($applicants_sql);
$applicants_stmt->execute();
$applicants_result = $applicants_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Dashboard - JPOST</title>
    <style>
        body {
            background: #181a1b;
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
        .navbar .logout-btn {
            background: #f44336;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .navbar .logout-btn:hover {
            background: #d32f2f;
        }
        .dashboard-container {
            margin: 48px auto 0 auto;
            width: 95%;
            max-width: 1000px;
            min-width: 320px;
            background: transparent;
            border-radius: 16px;
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
            background: #4fc3f7;
            color: #222;
            font-weight: bold;
            border: none;
            border-radius: 8px;
            padding: 12px 0;
            font-size: 1.1em;
            margin: 10px 0 0 0;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-block;
            text-decoration: none;
        }
        .job-card .post-btn:hover {
            background: #0288d1;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
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
            text-align: center;
            display: block;
        }
        .dashboard-actions .candidate-list { background: #7ed957; color: #222; }
        .dashboard-actions .resume { background: #ffb366; color: #222; }
        .dashboard-actions .interview { background: #f7f7b6; color: #222; }
        .dashboard-actions .recruit { background: #008080; color: #fff; }
        .dashboard-actions button:hover { filter: brightness(0.95); }
        .section-title {
            color: #4fc3f7;
            font-size: 1.3em;
            font-weight: bold;
            margin: 32px 0 12px 0;
        }
        .posted-jobs {
            width: 95%;
            max-width: 1100px;
            margin: 0 auto 32px auto;
        }
        .jobs-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
        }
        .job-tile {
            background: #fff;
            color: #222;
            border-radius: 8px;
            box-shadow: 0 2px 8px #0002;
            padding: 18px 22px;
            min-width: 220px;
            max-width: 320px;
            flex: 1;
            position: relative;
            margin-bottom: 12px;
        }
        .job-tile .job-title {
            color: #1ca7ec;
            font-weight: bold;
            font-size: 1.1em;
            margin-bottom: 6px;
        }
        .job-tile .job-info {
            font-size: 0.98em;
            margin-bottom: 8px;
        }
        .job-tile .job-info b {
            font-weight: 600;
        }
        .job-tile .job-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }
        .job-tile .edit-btn {
            background: #4fc3f7;
            color: #222;
            border: none;
            border-radius: 6px;
            padding: 6px 14px;
            cursor: pointer;
            font-weight: bold;
            flex: 1;
        }
        .job-tile .edit-btn:hover {
            background: #0288d1;
            color: #fff;
        }
        .job-tile .delete-btn {
            background: #f44336;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 6px 14px;
            cursor: pointer;
            font-weight: bold;
            flex: 1;
        }
        .job-tile .delete-btn:hover {
            background: #b71c1c;
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
            .posted-jobs {
                width: 98vw;
            }
        }
        
        /* Add styles for the post job modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
        }
        .modal-content {
            background: #232a34;
            padding: 24px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            margin: 48px auto;
            color: #fff;
        }
        .modal-content h2 {
            margin-top: 0;
            color: #4fc3f7;
        }
        .modal-content input,
        .modal-content textarea {
            width: 100%;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #444;
            background: #2a323d;
            color: #fff;
            margin-bottom: 16px;
        }
        .modal-content textarea {
            min-height: 100px;
        }
        .modal-content label {
            display: block;
            margin-bottom: 8px;
        }
        .modal-buttons {
            display: flex;
            gap: 12px;
        }
        .modal-buttons button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .success-message {
            background: #4caf50;
            color: white;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }

        /* Candidate List Styles */
        .candidates-list {
            max-height: 600px;
            overflow-y: auto;
            margin: 20px 0;
        }
        .candidate-card {
            background: #2a323d;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .candidate-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .candidate-header h3 {
            margin: 0;
            color: #4fc3f7;
        }
        .candidate-status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .candidate-status.pending {
            background: #ffd700;
            color: #000;
        }
        .candidate-status.interviewed {
            background: #4fc3f7;
            color: #000;
        }
        .candidate-status.hired {
            background: #4caf50;
            color: #fff;
        }
        .candidate-status.rejected {
            background: #f44336;
            color: #fff;
        }
        .candidate-details {
            margin-bottom: 16px;
        }
        .candidate-details p {
            margin: 8px 0;
            color: #fff;
        }
        .candidate-actions {
            display: flex;
            gap: 12px;
        }
        .view-resume-btn, .update-status-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            text-align: center;
            flex: 1;
        }
        .view-resume-btn {
            background: #4fc3f7;
            color: #222;
        }
        .update-status-btn {
            background: #666;
            color: #fff;
        }
        .view-resume-btn:hover {
            background: #0288d1;
            color: #fff;
        }
        .update-status-btn:hover {
            background: #444;
        }
        .no-candidates {
            text-align: center;
            padding: 32px;
            color: #666;
            font-size: 1.1em;
        }

        /* Updated Candidate List Styles */
        .status-container {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .candidate-status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .candidate-status.in-review {
            background: #ffd700;
            color: #000;
        }
        .candidate-status.in-process {
            background: #4fc3f7;
            color: #000;
        }
        .candidate-status.interview {
            background: #9c27b0;
            color: #fff;
        }
        .candidate-status.on-demand {
            background: #ff9800;
            color: #000;
        }
        .candidate-status.accepted {
            background: #4caf50;
            color: #fff;
        }
        .candidate-status.cancelled {
            background: #f44336;
            color: #fff;
        }
        .candidate-status.in-waiting {
            background: #607d8b;
            color: #fff;
        }
        .candidate-status.secondary {
            background: #666;
            color: #fff;
            font-size: 0.8em;
        }

        /* Interview Management Styles */
        .interview-list {
            max-height: 600px;
            overflow-y: auto;
            margin: 20px 0;
        }
        .interview-card {
            background: #2a323d;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .interview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .interview-header h3 {
            margin: 0;
            color: #4fc3f7;
        }
        .interview-status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .interview-status.pending {
            background: #ffd700;
            color: #000;
        }
        .interview-status.interviewed {
            background: #4caf50;
            color: #fff;
        }
        .interview-details {
            margin-bottom: 16px;
        }
        .interview-details p {
            margin: 8px 0;
            color: #fff;
        }
        .interview-actions {
            display: flex;
            gap: 12px;
        }
        .status-form {
            display: flex;
            gap: 12px;
            width: 100%;
        }
        .status-select {
            flex: 1;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #444;
            background: #2a323d;
            color: #fff;
        }
        .update-interview-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            background: #4fc3f7;
            color: #222;
            cursor: pointer;
            font-weight: bold;
        }
        .update-interview-btn:hover {
            background: #0288d1;
            color: #fff;
        }
        .no-interviews {
            text-align: center;
            padding: 32px;
            color: #666;
            font-size: 1.1em;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="logo">
            <span style="font-size:1.2em; margin-right:4px;">&#128188;</span> JPOST
        </div>
        <nav>
            <a href="#">Home</a>
            <a href="#">Explore</a>
            <a href="#">Account</a>
            <a href="#" class="active">Dashboard</a>
        </nav>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
    <div class="dashboard-container">
        <div class="job-card">
            <img src="https://i.ibb.co/6bQ6Q0k/professional-woman.png" alt="Professional Woman" class="profile-image">
            <h2>Post a Job</h2>
            <div class="job-details">
                <div>Fill in the details below to post a new job opening.</div>
            </div>
            <button type="button" class="post-btn" onclick="openPostModal()">Post New Job</button>
        </div>
        <div class="dashboard-actions">
            <button type="button" class="candidate-list" onclick="openCandidateModal()">Candidate List</button>
            <button type="button" class="resume">Resume</button>
            <button type="button" class="interview" onclick="openInterviewModal()">Interview</button>
            <button type="button" class="recruit">Recruit</button>
        </div>
    </div>
    <div class="posted-jobs">
        <div class="section-title">Your Posted Jobs</div>
        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                <?php 
                if ($_GET['success'] === 'updated') echo 'Job updated successfully!';
                if ($_GET['success'] === 'deleted') echo 'Job deleted successfully!';
                if ($_GET['success'] === 'posted') echo 'Job posted successfully!';
                if ($_GET['success'] === 'interview_updated') echo 'Interview status updated successfully!';
                ?>
            </div>
        <?php endif; ?>
        <div class="jobs-grid">
            <?php if ($jobs_result->num_rows > 0): ?>
                <?php while ($job = $jobs_result->fetch_assoc()): ?>
                    <div class="job-tile">
                        <div class="job-title"><?php echo htmlspecialchars($job['job']); ?></div>
                        <div class="job-info"><b>Company:</b> <?php echo htmlspecialchars($job['company']); ?></div>
                        <div class="job-info"><b>Requirements:</b> <?php echo htmlspecialchars($job['requirements']); ?></div>
                        <div class="job-info"><b>Salary:</b> <?php echo htmlspecialchars($job['salary']); ?></div>
                        <div class="job-info"><b>Address:</b> <?php echo htmlspecialchars($job['address']); ?></div>
                        <div class="job-info"><b>Posted:</b> <?php echo date('Y-m-d H:i:s', strtotime($job['created_at'])); ?></div>
                        <div class="job-actions">
                            <button class="edit-btn" onclick="openEditModal(<?php echo $job['id']; ?>, '<?php echo htmlspecialchars($job['job']); ?>', '<?php echo htmlspecialchars($job['company']); ?>', '<?php echo htmlspecialchars($job['requirements']); ?>', '<?php echo htmlspecialchars($job['salary']); ?>', '<?php echo htmlspecialchars($job['address']); ?>')">Edit</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this job posting?');">
                                <input type="hidden" name="delete_job" value="<?php echo $job['id']; ?>">
                                <button type="submit" class="delete-btn">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align: center; width: 100%; padding: 20px; color: #666;">
                    No jobs posted yet. Click "Post New Job" to create your first job posting.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Post New Job Modal -->
    <div id="postModal" class="modal">
        <div class="modal-content">
            <h2>Post New Job</h2>
            <form method="POST" id="postForm">
                <div>
                    <label for="job_title">Job Title</label>
                    <input type="text" id="job_title" name="job_title" required>
                </div>
                <div>
                    <label for="company">Company</label>
                    <input type="text" id="company" name="company" required>
                </div>
                <div>
                    <label for="requirements">Requirements</label>
                    <textarea id="requirements" name="requirements" required></textarea>
                </div>
                <div>
                    <label for="salary">Salary</label>
                    <input type="text" id="salary" name="salary" required>
                </div>
                <div>
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" required>
                </div>
                <div class="modal-buttons">
                    <button type="submit" name="post_new_job" style="background: #4fc3f7; color: #222;">Post Job</button>
                    <button type="button" onclick="closePostModal()" style="background: #666; color: #fff;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Job Modal -->
    <div id="editModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000;">
        <div class="modal-content" style="background: #232a34; padding: 24px; border-radius: 12px; width: 90%; max-width: 500px; margin: 48px auto; color: #fff;">
            <h2 style="margin-top: 0; color: #4fc3f7;">Edit Job</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="edit_job" id="edit_job_id">
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px;">Job Title</label>
                    <input type="text" name="job_title" id="edit_job_title" required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #444; background: #2a323d; color: #fff;">
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px;">Company</label>
                    <input type="text" name="company" id="edit_company" required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #444; background: #2a323d; color: #fff;">
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px;">Requirements</label>
                    <textarea name="requirements" id="edit_requirements" required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #444; background: #2a323d; color: #fff; min-height: 100px;"></textarea>
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px;">Salary</label>
                    <input type="text" name="salary" id="edit_salary" required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #444; background: #2a323d; color: #fff;">
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px;">Address</label>
                    <input type="text" name="address" id="edit_address" required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #444; background: #2a323d; color: #fff;">
                </div>
                <div style="display: flex; gap: 12px;">
                    <button type="submit" style="flex: 1; padding: 12px; background: #4fc3f7; color: #222; border: none; border-radius: 4px; cursor: pointer;">Save Changes</button>
                    <button type="button" onclick="closeEditModal()" style="flex: 1; padding: 12px; background: #666; color: #fff; border: none; border-radius: 4px; cursor: pointer;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Candidate List Modal -->
    <div id="candidateModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <h2>Candidate List</h2>
            <div class="candidates-list">
                <?php if ($applicants_result->num_rows > 0): ?>
                    <?php while ($applicant = $applicants_result->fetch_assoc()): ?>
                        <div class="candidate-card">
                            <div class="candidate-header">
                                <h3><?php echo htmlspecialchars($applicant['name']); ?></h3>
                                <div class="status-container">
                                    <span class="candidate-status <?php echo strtolower(str_replace(' ', '-', $applicant['status1'])); ?>">
                                        <?php echo htmlspecialchars($applicant['status1']); ?>
                                    </span>
                                    <?php if (!empty($applicant['status2'])): ?>
                                        <span class="candidate-status secondary">
                                            <?php echo htmlspecialchars($applicant['status2']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($applicant['status3'])): ?>
                                        <span class="candidate-status secondary">
                                            <?php echo htmlspecialchars($applicant['status3']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="candidate-details">
                                <p><strong>Applied for:</strong> <?php echo htmlspecialchars($applicant['job_title']); ?></p>
                                <p><strong>Company:</strong> <?php echo htmlspecialchars($applicant['company']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($applicant['email']); ?></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($applicant['phone']); ?></p>
                                <p><strong>Applied on:</strong> <?php echo date('Y-m-d H:i:s', strtotime($applicant['created_at'])); ?></p>
                            </div>
                            <div class="candidate-actions">
                                <?php if (!empty($applicant['resume_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($applicant['resume_url']); ?>" target="_blank" class="view-resume-btn">View Resume</a>
                                <?php endif; ?>
                                <button onclick="updateStatus(<?php echo $applicant['id']; ?>, 'status1')" class="update-status-btn">Update Status</button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-candidates">
                        No candidates have applied yet.
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-buttons">
                <button type="button" onclick="closeCandidateModal()" style="background: #666; color: #fff;">Close</button>
            </div>
        </div>
    </div>

    <!-- Interview Management Modal -->
    <div id="interviewModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <h2>Interview Management</h2>
            <div class="interview-list">
                <?php 
                // Reset the result pointer
                $applicants_result->data_seek(0);
                if ($applicants_result->num_rows > 0): 
                ?>
                    <?php while ($applicant = $applicants_result->fetch_assoc()): ?>
                        <div class="interview-card">
                            <div class="interview-header">
                                <h3><?php echo htmlspecialchars($applicant['name']); ?></h3>
                                <div class="status-container">
                                    <span class="interview-status <?php echo strtolower(str_replace(' ', '-', $applicant['status2'] ?? 'pending')); ?>">
                                        <?php echo htmlspecialchars($applicant['status2'] ?? 'Pending'); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="interview-details">
                                <p><strong>Position:</strong> <?php echo htmlspecialchars($applicant['job_title']); ?></p>
                                <p><strong>Company:</strong> <?php echo htmlspecialchars($applicant['company']); ?></p>
                                <p><strong>Contact:</strong> <?php echo htmlspecialchars($applicant['email']); ?> | <?php echo htmlspecialchars($applicant['phone']); ?></p>
                            </div>
                            <div class="interview-actions">
                                <form method="POST" class="status-form">
                                    <input type="hidden" name="applicant_id" value="<?php echo $applicant['id']; ?>">
                                    <select name="interview_status" class="status-select">
                                        <option value="Pending" <?php echo ($applicant['status2'] ?? '') === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="Interviewed" <?php echo ($applicant['status2'] ?? '') === 'Interviewed' ? 'selected' : ''; ?>>Interviewed</option>
                                    </select>
                                    <button type="submit" name="update_interview_status" class="update-interview-btn">Update Status</button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-interviews">
                        No candidates available for interview.
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-buttons">
                <button type="button" onclick="closeInterviewModal()" style="background: #666; color: #fff;">Close</button>
            </div>
        </div>
    </div>

    <script>
        function openEditModal(id, title, company, requirements, salary, address) {
            document.getElementById('edit_job_id').value = id;
            document.getElementById('edit_job_title').value = title;
            document.getElementById('edit_company').value = company;
            document.getElementById('edit_requirements').value = requirements;
            document.getElementById('edit_salary').value = salary;
            document.getElementById('edit_address').value = address;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function openPostModal() {
            document.getElementById('postModal').style.display = 'block';
        }

        function closePostModal() {
            document.getElementById('postModal').style.display = 'none';
        }

        function openCandidateModal() {
            document.getElementById('candidateModal').style.display = 'block';
        }

        function closeCandidateModal() {
            document.getElementById('candidateModal').style.display = 'none';
        }

        function openInterviewModal() {
            document.getElementById('interviewModal').style.display = 'block';
        }

        function closeInterviewModal() {
            document.getElementById('interviewModal').style.display = 'none';
        }

        function updateStatus(applicantId, statusField) {
            const statuses = [
                'In Review',
                'In Process',
                'Interview',
                'On Demand',
                'Accepted',
                'Cancelled',
                'In Waiting'
            ];
            
            // Here you would typically make an AJAX call to update the status in the database
            // For now, we'll just show an alert
            alert(`Update status for applicant ID: ${applicantId}\nAvailable statuses: ${statuses.join(', ')}`);
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target == document.getElementById('postModal')) {
                closePostModal();
            }
            if (event.target == document.getElementById('editModal')) {
                closeEditModal();
            }
            if (event.target == document.getElementById('candidateModal')) {
                closeCandidateModal();
            }
            if (event.target == document.getElementById('interviewModal')) {
                closeInterviewModal();
            }
        }
    </script>
</body>
</html> 