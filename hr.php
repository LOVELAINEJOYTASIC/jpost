<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';
$conn = getDBConnection();

// Debug information
error_log("HR page accessed - Session contents: " . print_r($_SESSION, true));

// Authentication: Only allow admin or hr
if (!isset($_SESSION['user_id'])) {
    error_log("No user_id in session - Redirecting to login");
    header('Location: login.php?error=not_logged_in');
    exit();
}

if (!isset($_SESSION['user_type'])) {
    error_log("No user_type in session - Redirecting to login");
    header('Location: login.php?error=not_logged_in');
    exit();
}

if (!in_array(strtolower($_SESSION['user_type']), ['admin', 'hr'])) {
    error_log("Unauthorized access attempt - User type: " . $_SESSION['user_type']);
    header('Location: index.php?error=unauthorized');
    exit();
}

// Double check user type in database
$user_id = $_SESSION['user_id'];
$check_user = $conn->prepare("SELECT user_type FROM users WHERE id = ?");
$check_user->bind_param("i", $user_id);
$check_user->execute();
$result = $check_user->get_result();
$user = $result->fetch_assoc();

if (!$user || !in_array(strtolower($user['user_type']), ['admin', 'hr'])) {
    error_log("Access denied - User type is not admin/hr: " . ($user['user_type'] ?? 'not set'));
    header('Location: index.php?error=unauthorized');
    exit();
}

error_log("Access granted - User is admin/hr");
$check_user->close();

// Add delete functionality
if (isset($_POST['delete_applicant'])) {
    $applicant_id = (int)$_POST['delete_applicant'];
    $delete_sql = "DELETE FROM applicants WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $applicant_id);
    if ($delete_stmt->execute()) {
        header('Location: hr.php?success=deleted');
        exit();
    }
    $delete_stmt->close();
}

// Add edit functionality
if (isset($_POST['edit_applicant'])) {
    $applicant_id = (int)$_POST['edit_applicant'];
    $status = $conn->real_escape_string($_POST['status']);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update applicants table
        $update_sql = "UPDATE applicants SET status1 = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $status, $applicant_id);
        $update_stmt->execute();
        
        // Get the user_id and job_id from applicants table
        $get_user_sql = "SELECT user_id, job_id FROM applicants WHERE id = ?";
        $get_user_stmt = $conn->prepare($get_user_sql);
        $get_user_stmt->bind_param("i", $applicant_id);
        $get_user_stmt->execute();
        $user_result = $get_user_stmt->get_result();
        $user_data = $user_result->fetch_assoc();
        
        if ($user_data && $user_data['user_id']) {
            // Update job_applications table for this specific job application
            $update_job_sql = "UPDATE job_applications SET status = ? WHERE user_id = ? AND job_id = ?";
            $update_job_stmt = $conn->prepare($update_job_sql);
            $update_job_stmt->bind_param("sii", $status, $user_data['user_id'], $user_data['job_id']);
            $update_job_stmt->execute();
        }
        
        // Commit transaction
        $conn->commit();
        header('Location: hr.php?success=updated');
        exit();
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        header('Location: hr.php?error=update_failed');
        exit();
    }
}

// Create user_id column in applicants table if it doesn't exist
$check_column = $conn->query("SHOW COLUMNS FROM applicants LIKE 'user_id'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE applicants ADD COLUMN user_id INT, ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL");
}

// Create job_type column in jobs table if it doesn't exist
$check_job_type = $conn->query("SHOW COLUMNS FROM jobs LIKE 'job_type'");
if ($check_job_type->num_rows == 0) {
    $conn->query("ALTER TABLE jobs ADD COLUMN job_type ENUM('Full Time', 'Part Time', 'Remote', 'Internship') DEFAULT 'Full Time'");
}

// Fetch applicants from database with search functionality
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';

$sql = "SELECT a.*, up.full_name, up.birthday, up.address, up.contact, up.application, j.job, j.job_type 
        FROM applicants a 
        LEFT JOIN user_profiles up ON a.user_id = up.user_id 
        LEFT JOIN jobs j ON a.job_id = j.id 
        WHERE 1=1";

if (!empty($search)) {
    $sql .= " AND (a.name LIKE '%$search%' OR a.email LIKE '%$search%' OR a.phone LIKE '%$search%' OR up.full_name LIKE '%$search%' OR j.job LIKE '%$search%')";
}
if (!empty($status_filter)) {
    $sql .= " AND a.status1 = '$status_filter'";
}
$sql .= " ORDER BY a.created_at DESC";

$result = $conn->query($sql);
$applicants = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $applicants[] = $row;
    }
}

// Function to link applicants with user profiles
function linkApplicantsWithUsers($conn) {
    // Get all applicants without user_id
    $sql = "SELECT a.id, a.name FROM applicants a WHERE a.user_id IS NULL";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($applicant = $result->fetch_assoc()) {
            // Find matching user by name
            $name = $conn->real_escape_string($applicant['name']);
            $user_sql = "SELECT id FROM users WHERE username = '$name'";
            $user_result = $conn->query($user_sql);
            
            if ($user_result && $user_result->num_rows > 0) {
                $user = $user_result->fetch_assoc();
                // Update applicant with user_id
                $update_sql = "UPDATE applicants SET user_id = ? WHERE id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("ii", $user['id'], $applicant['id']);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

// Call the function to link applicants
linkApplicantsWithUsers($conn);

// Get candidate statistics
$stats_sql = "SELECT 
    status1,
    COUNT(*) as count,
    (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM applicants)) as percentage
FROM applicants 
GROUP BY status1";

$stats_result = $conn->query($stats_sql);
$candidate_stats = [];
if ($stats_result && $stats_result->num_rows > 0) {
    while ($row = $stats_result->fetch_assoc()) {
        $candidate_stats[$row['status1']] = [
            'count' => $row['count'],
            'percentage' => round($row['percentage'], 1)
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Dashboard - JPOST</title>
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
        }
        .navbar nav a:hover, .navbar nav a.active {
            color: #4fc3f7;
            text-decoration: underline;
        }
        .navbar .search {
            display: flex;
            align-items: center;
            background: #fff;
            border-radius: 24px;
            padding: 4px 16px;
            margin-left: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .navbar .search:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-1px);
        }
        .navbar .search input {
            background: transparent;
            border: none;
            color: #222;
            outline: none;
            padding: 8px;
            font-size: 1em;
            width: 240px;
            transition: width 0.3s ease;
        }
        .navbar .search input:focus {
            width: 280px;
        }
        .navbar .search button {
            background: none;
            border: none;
            color: #222;
            cursor: pointer;
            font-size: 1.2em;
            padding: 4px 8px;
            transition: transform 0.2s ease;
        }
        .navbar .search button:hover {
            transform: scale(1.1);
        }
        .navbar .settings {
            margin-left: 18px;
            font-size: 1.7em;
            color: #4fc3f7;
            cursor: pointer;
            border: 2px solid #4fc3f7;
            border-radius: 8px;
            padding: 2px 6px;
        }
        .main-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin: 40px 40px 0 40px;
        }
        .applicants-card {
            background: #f7f7f7;
            color: #222;
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
            padding: 32px;
            min-width: 400px;
            max-width: 1200px;
            flex-grow: 1;
        }
        .applicants-card h2 {
            margin-top: 0;
            margin-bottom: 18px;
            font-size: 1.4em;
            font-weight: bold;
        }
        .applicants-table {
            margin: 0 auto;
            background: #f7f7f7;
            border-radius: 10px;
            box-shadow: 0 2px 8px #0004;
            min-width: 900px;
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 24px;
        }
        .applicants-table th, .applicants-table td {
            padding: 12px 18px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .applicants-table th {
            background: #4fc3f7;
            font-weight: 600;
            color: #222;
        }
        .applicants-table tr:nth-child(even) {
            background: #fff;
        }
        .applicants-table tr:nth-child(odd) {
            background: #f1f8fb;
        }
        .applicants-table tr:hover {
            background: #e3f2fd;
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
        .modal-body {
            margin-bottom: 24px;
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
        .modal-footer {
            text-align: right;
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
        .success-message {
            background: #4caf50;
            color: #fff;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 24px;
        }
        .error-message {
            background: #f44336;
            color: #fff;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 24px;
        }
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 24px;
            margin-left: 40px;
            margin-top: 10px;
        }
        .sidebar-btn {
            background: #fff;
            color: #222;
            border: none;
            border-radius: 6px;
            padding: 18px 0;
            font-size: 1.1em;
            font-weight: 500;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
            width: 220px;
            margin: 0 auto;
            display: block;
            transition: background 0.2s, color 0.2s, transform 0.2s;
            text-decoration: none;
            text-align: center;
        }
        .sidebar-btn:hover {
            background: #4fc3f7;
            color: #fff;
            transform: translateY(-2px);
        }
        .filters {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
        }
        .filters select {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: #fff;
            color: #222;
            font-size: 1em;
        }
        @media (max-width: 900px) {
            .main-content {
                flex-direction: column;
                align-items: stretch;
            }
            .sidebar {
                margin-left: 0;
                margin-top: 32px;
                align-items: center;
            }
        }
        .stats-container {
            background: #232a34;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 32px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .stat-card {
            background: #1a1f28;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
        }
        .stat-title {
            color: #888;
            font-size: 0.9em;
            margin-bottom: 8px;
        }
        .stat-value {
            color: #4fc3f7;
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .stat-percentage {
            color: #4caf50;
            font-size: 1.2em;
            margin-bottom: 8px;
        }
        .stat-progress {
            background: #333;
            height: 4px;
            border-radius: 2px;
            overflow: hidden;
        }
        .progress-bar {
            background: #4fc3f7;
            height: 100%;
            transition: width 0.3s ease;
        }
        .candidates-list {
            display: grid;
            gap: 20px;
            margin-top: 32px;
        }
        .candidate-card {
            background: #232a34;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #333;
        }
        .candidate-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        .candidate-info h3 {
            margin: 0;
            color: #4fc3f7;
        }
        .full-name {
            color: #888;
            font-size: 0.9em;
        }
        .candidate-details {
            margin-bottom: 20px;
        }
        .detail-row {
            display: flex;
            margin-bottom: 8px;
        }
        .detail-label {
            color: #888;
            width: 100px;
        }
        .detail-value {
            color: #fff;
        }
        .candidate-progress {
            margin-bottom: 20px;
        }
        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin: 0 20px;
        }
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: #333;
            z-index: 1;
        }
        .step {
            background: #1a1f28;
            border: 2px solid #333;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8em;
            color: #888;
            position: relative;
            z-index: 2;
        }
        .step.active {
            background: #4fc3f7;
            border-color: #4fc3f7;
            color: #fff;
        }
        .candidate-actions {
            display: flex;
            gap: 12px;
        }
        .update-btn, .view-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.2s;
        }
        .update-btn {
            background: #4fc3f7;
            color: #222;
        }
        .view-btn {
            background: #666;
            color: #fff;
        }
        .update-btn:hover {
            background: #0288d1;
            color: #fff;
        }
        .view-btn:hover {
            background: #444;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="logo">
            <span style="font-size:1.2em; margin-right:4px;">&#9675;</span> JPOST
        </div>
        <nav>
            <a href="index.php">Home</a>
            <a href="explore.php">Explore</a>
            <a href="account.php">Account</a>
        </nav>
        <div style="display:flex; align-items:center;">
            <form class="search" action="hr.php" method="GET">
                <input type="text" name="search" placeholder="Search applicants..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">&#128269;</button>
            </form>
            <a href="logout.php" style="color:#fff; text-decoration:none; margin-left:18px; background:#f44336; padding:8px 16px; border-radius:4px;">Logout</a>
        </div>
    </div>
    <div class="main-content">
        <div class="applicants-card">
            <h2>Applicants</h2>
            <?php if (isset($_GET['success'])): ?>
                <div class="success-message">
                    <?php 
                    if ($_GET['success'] === 'updated') echo 'Applicant status updated successfully!';
                    if ($_GET['success'] === 'deleted') echo 'Applicant deleted successfully!';
                    ?>
                </div>
            <?php endif; ?>
            
            <div class="filters">
                <form action="hr.php" method="GET" style="display:flex; gap:16px;">
                    <select name="status" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="In Review" <?php echo $status_filter === 'In Review' ? 'selected' : ''; ?>>In Review</option>
                        <option value="In Process" <?php echo $status_filter === 'In Process' ? 'selected' : ''; ?>>In Process</option>
                        <option value="Interview" <?php echo $status_filter === 'Interview' ? 'selected' : ''; ?>>Interview</option>
                        <option value="On Demand" <?php echo $status_filter === 'On Demand' ? 'selected' : ''; ?>>On Demand</option>
                        <option value="Accepted" <?php echo $status_filter === 'Accepted' ? 'selected' : ''; ?>>Accepted</option>
                        <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="In Waiting" <?php echo $status_filter === 'In Waiting' ? 'selected' : ''; ?>>In Waiting</option>
                    </select>
                    <?php if (!empty($search)): ?>
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <?php endif; ?>
                </form>
            </div>

            <table class="applicants-table">
                <tr>
                    <th>Name</th>
                    <th>Job Position</th>
                    <th>Job Type</th>
                    <th>Status</th>
                    <th>Applied</th>
                    <th>Actions</th>
                </tr>
                <?php if (empty($applicants)): ?>
                    <tr><td colspan="6" style="text-align:center;">No applicants found.</td></tr>
                <?php else: ?>
                    <?php foreach ($applicants as $app): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($app['name']); ?>
                                <?php if (!empty($app['full_name'] ?? '')): ?>
                                    <br><small style="color:#666;"><?php echo htmlspecialchars($app['full_name'] ?? ''); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($app['job'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($app['job_type'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="badge <?php echo strtolower(str_replace(' ', '', $app['status1'])); ?>">
                                    <?php echo htmlspecialchars($app['status1']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($app['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="edit-btn" onclick="openEditModal(<?php echo $app['id']; ?>, '<?php echo htmlspecialchars($app['status1']); ?>')">Edit</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this applicant?');">
                                        <input type="hidden" name="delete_applicant" value="<?php echo $app['id']; ?>">
                                        <button type="submit" class="delete-btn">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>
        </div>
        <div class="sidebar">
            <a class="sidebar-btn" href="job_postings.php">Job Postings</a>
            <a class="sidebar-btn" href="candidate_list.php">Candidate List</a>
            <a class="sidebar-btn" href="resume_parsing.php">Resume Parsing</a>
            <a class="sidebar-btn" href="reports.php">Reports</a>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Applicant Status</h2>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="edit_applicant" id="edit_applicant_id">
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select name="status" id="status" required>
                            <option value="In Review">In Review</option>
                            <option value="In Process">In Process</option>
                            <option value="Interview">Interview</option>
                            <option value="On Demand">On Demand</option>
                            <option value="Accepted">Accepted</option>
                            <option value="Cancelled">Cancelled</option>
                            <option value="In Waiting">In Waiting</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="save-btn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openEditModal(id, currentStatus) {
        document.getElementById('editModal').style.display = 'block';
        document.getElementById('edit_applicant_id').value = id;
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