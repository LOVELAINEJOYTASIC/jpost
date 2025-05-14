<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';
$conn = getDBConnection();

// Authentication: Only allow admin or hr
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['user_type']), ['admin', 'hr'])) {
    header('Location: login.php?error=unauthorized');
    exit();
}

// Handle job deletion
if (isset($_POST['delete_job'])) {
    $job_id = (int)$_POST['delete_job'];
    $delete_sql = "DELETE FROM jobs WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $job_id);
    if ($delete_stmt->execute()) {
        header('Location: job_postings.php?success=deleted');
        exit();
    }
    $delete_stmt->close();
}

// Handle job status update
if (isset($_POST['update_status'])) {
    $job_id = (int)$_POST['job_id'];
    $status = $conn->real_escape_string($_POST['status']);
    $update_sql = "UPDATE jobs SET status = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $status, $job_id);
    if ($update_stmt->execute()) {
        header('Location: job_postings.php?success=updated');
        exit();
    }
    $update_stmt->close();
}

// Add address column to jobs table if it doesn't exist
$check_address = $conn->query("SHOW COLUMNS FROM jobs LIKE 'address'");
if ($check_address->num_rows == 0) {
    $conn->query("ALTER TABLE jobs ADD COLUMN address TEXT AFTER company");
}

// Handle job creation with improved validation
if (isset($_POST['create_job'])) {
    $errors = [];
    
    // Validate and sanitize inputs
    $job = trim($conn->real_escape_string($_POST['job'] ?? ''));
    $company = trim($conn->real_escape_string($_POST['company'] ?? ''));
    $salary = trim($conn->real_escape_string($_POST['salary'] ?? ''));
    $requirements = trim($conn->real_escape_string($_POST['requirements'] ?? ''));
    $job_type = trim($conn->real_escape_string($_POST['job_type'] ?? ''));
    $address = trim($conn->real_escape_string($_POST['address'] ?? ''));
    $user_id = $_SESSION['user_id'];

    // Validation
    if (empty($job)) $errors[] = "Job title is required";
    if (empty($company)) $errors[] = "Company name is required";
    if (empty($salary)) $errors[] = "Salary is required";
    if (empty($requirements)) $errors[] = "Requirements are required";
    if (empty($job_type)) $errors[] = "Job type is required";
    if (empty($address)) $errors[] = "Address is required";

    if (empty($errors)) {
        $insert_sql = "INSERT INTO jobs (job, company, salary, requirements, job_type, address, user_id, status, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', NOW())";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ssssssi", $job, $company, $salary, $requirements, $job_type, $address, $user_id);
        
        if ($insert_stmt->execute()) {
            header('Location: job_postings.php?success=created');
            exit();
        } else {
            $errors[] = "Error creating job: " . $insert_stmt->error;
        }
        $insert_stmt->close();
    }
}

// Add search functionality
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$status_filter = isset($_GET['status_filter']) ? $conn->real_escape_string($_GET['status_filter']) : '';

// Modify the fetch query to include search and filters
$sql = "SELECT j.*, u.username as employer_name 
        FROM jobs j 
        LEFT JOIN users u ON j.user_id = u.id 
        WHERE 1=1";

if (!empty($search)) {
    $sql .= " AND (j.job LIKE '%$search%' OR j.company LIKE '%$search%' OR j.requirements LIKE '%$search%')";
}
if (!empty($status_filter)) {
    $sql .= " AND j.status = '$status_filter'";
}

$sql .= " ORDER BY j.created_at DESC";
$result = $conn->query($sql);
$jobs = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Postings - JPOST</title>
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
        .main-content {
            padding: 40px;
            max-width: 1200px;
            margin: 0 auto;
            min-height: calc(100vh - 60px);
            display: flex;
            flex-direction: column;
        }
        .jobs-card {
            background: #fff;
            color: #222;
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
            padding: 32px;
            margin-bottom: 24px;
            flex: 1;
        }
        .jobs-card:last-child {
            margin-bottom: 0;
        }
        .jobs-card h2 {
            margin-top: 0;
            margin-bottom: 24px;
            color: #222;
            font-size: 1.4em;
        }
        .jobs-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .jobs-table th, .jobs-table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .jobs-table th {
            background: #f7f7f7;
            font-weight: 600;
            color: #222;
        }
        .jobs-table tr:hover {
            background: #f9f9f9;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        .edit-btn, .delete-btn {
            padding: 6px 12px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.2s;
        }
        .edit-btn {
            background: #4fc3f7;
            color: #fff;
        }
        .delete-btn {
            background: #f44336;
            color: #fff;
        }
        .edit-btn:hover, .delete-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: 500;
        }
        .status-active {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .status-inactive {
            background: #ffebee;
            color: #c62828;
        }
        .success-message {
            background: #4caf50;
            color: #fff;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 24px;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        .modal-content {
            background: #fff;
            color: #222;
            width: 90%;
            max-width: 600px;
            margin: 50px auto;
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.2);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .modal-header h2 {
            margin: 0;
            color: #222;
        }
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            color: #666;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #444;
        }
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1em;
        }
        .save-btn {
            background: #4fc3f7;
            color: #fff;
            border: none;
            padding: 8px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1em;
        }
        .save-btn:hover {
            background: #0288d1;
        }
        .search-filter-form {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .search-filter-form input[type="text"] {
            flex: 1;
            min-width: 200px;
        }
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }
            .jobs-card {
                padding: 20px;
            }
            .search-filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            .search-filter-form input[type="text"],
            .search-filter-form select,
            .search-filter-form button {
                width: 100%;
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
            <a href="explore.php">Explore</a>
            <a href="hr.php">HR Dashboard</a>
        </nav>
        <div style="display:flex; align-items:center;">
            <a href="hr.php" style="color:#fff; text-decoration:none; margin-right:18px; background:#2196f3; padding:8px 16px; border-radius:4px;">Back to HR</a>
            <a href="logout.php" style="color:#fff; text-decoration:none; background:#f44336; padding:8px 16px; border-radius:4px;">Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="jobs-card">
            <h2>Job Postings</h2>
            <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="success-message">
                    <?php 
                    if ($_GET['success'] === 'updated') echo 'Job status updated successfully!';
                    if ($_GET['success'] === 'deleted') echo 'Job deleted successfully!';
                    if ($_GET['success'] === 'created') echo 'Job created successfully!';
                    ?>
                </div>
            <?php endif; ?>

            <!-- Add search and filter form -->
            <form method="GET" class="search-filter-form" style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center;">
                <input type="text" name="search" placeholder="Search jobs..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; padding: 8px; border-radius: 6px; border: 1px solid #ddd;">
                <select name="status_filter" style="padding: 8px; border-radius: 6px; border: 1px solid #ddd;">
                    <option value="">All Statuses</option>
                    <option value="Active" <?php echo $status_filter === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo $status_filter === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
                <button type="submit" class="save-btn">Search</button>
                <?php if (!empty($search) || !empty($status_filter)): ?>
                    <a href="job_postings.php" class="save-btn" style="background: #666; text-decoration: none;">Clear</a>
                <?php endif; ?>
            </form>

            <table class="jobs-table">
                <tr>
                    <th>Job Title</th>
                    <th>Company</th>
                    <th>Location</th>
                    <th>Employer</th>
                    <th>Posted Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                <?php if (empty($jobs)): ?>
                    <tr><td colspan="7" style="text-align:center;">No job postings found.</td></tr>
                <?php else: ?>
                    <?php foreach ($jobs as $job): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($job['job'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($job['company'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($job['address'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($job['employer_name'] ?? ''); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($job['created_at'] ?? 'now')); ?></td>
                            <td>
                                <span class="status-badge <?php echo ($job['status'] ?? '') === 'Active' ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo htmlspecialchars($job['status'] ?? 'Inactive'); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="edit-btn" onclick="openEditModal(<?php echo $job['id'] ?? 0; ?>, '<?php echo htmlspecialchars($job['status'] ?? ''); ?>')">Edit</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this job posting?');">
                                        <input type="hidden" name="delete_job" value="<?php echo $job['id'] ?? 0; ?>">
                                        <button type="submit" class="delete-btn">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>
        </div>

        <div class="jobs-card" style="margin-top: 24px; margin-bottom: 0;">
            <h2>Create New Job Posting</h2>
            <form method="POST" style="display: grid; gap: 16px; max-width: 600px; margin-bottom: 0;">
                <div class="form-group">
                    <label for="job">Job Title:</label>
                    <input type="text" id="job" name="job" required style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ddd;" value="<?php echo htmlspecialchars($_POST['job'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="company">Company:</label>
                    <input type="text" id="company" name="company" required style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ddd;" value="<?php echo htmlspecialchars($_POST['company'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="address">Location:</label>
                    <input type="text" id="address" name="address" required style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ddd;" value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="salary">Salary:</label>
                    <input type="text" id="salary" name="salary" required style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ddd;" value="<?php echo htmlspecialchars($_POST['salary'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="requirements">Requirements:</label>
                    <textarea id="requirements" name="requirements" required style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ddd; min-height: 100px;"><?php echo htmlspecialchars($_POST['requirements'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="job_type">Job Type:</label>
                    <select id="job_type" name="job_type" required style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ddd;">
                        <option value="Full Time" <?php echo ($_POST['job_type'] ?? '') === 'Full Time' ? 'selected' : ''; ?>>Full Time</option>
                        <option value="Part Time" <?php echo ($_POST['job_type'] ?? '') === 'Part Time' ? 'selected' : ''; ?>>Part Time</option>
                        <option value="Remote" <?php echo ($_POST['job_type'] ?? '') === 'Remote' ? 'selected' : ''; ?>>Remote</option>
                        <option value="Internship" <?php echo ($_POST['job_type'] ?? '') === 'Internship' ? 'selected' : ''; ?>>Internship</option>
                    </select>
                </div>
                <button type="submit" name="create_job" class="save-btn" style="justify-self: start;">Create Job Posting</button>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Job Status</h2>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="job_id" id="edit_job_id">
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select name="status" id="status" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_status" class="save-btn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openEditModal(id, currentStatus) {
        document.getElementById('editModal').style.display = 'block';
        document.getElementById('edit_job_id').value = id;
        document.getElementById('status').value = currentStatus;
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target == document.getElementById('editModal')) {
            closeEditModal();
        }
    }
    </script>
</body>
</html> 