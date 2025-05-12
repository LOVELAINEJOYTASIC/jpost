<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';
$conn = getDBConnection();

// Authentication: Only allow admin or hr
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || 
    !in_array(strtolower($_SESSION['user_type']), ['admin', 'hr'])) {
    header('Location: login.php?error=unauthorized');
    exit();
}

// Get job ID from URL
$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

if ($job_id <= 0) {
    header('Location: job_postings.php?error=invalid_job');
    exit();
}

// Get job details
$job_sql = "SELECT j.title, e.company_name 
            FROM jobs j 
            LEFT JOIN employers e ON j.employer_id = e.id 
            WHERE j.id = ?";
$job_stmt = $conn->prepare($job_sql);
$job_stmt->bind_param("i", $job_id);
$job_stmt->execute();
$job = $job_stmt->get_result()->fetch_assoc();

if (!$job) {
    header('Location: job_postings.php?error=job_not_found');
    exit();
}

// Get applicants with their application status
$sql = "SELECT a.*, ja.status as application_status, ja.application_date 
        FROM applicants a 
        INNER JOIN job_applications ja ON a.id = ja.applicant_id 
        WHERE ja.job_id = ? 
        ORDER BY ja.application_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();
$applicants = [];
while ($row = $result->fetch_assoc()) {
    $applicants[] = $row;
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['applicant_id']) && isset($_POST['status'])) {
    $update_sql = "UPDATE job_applications 
                  SET status = ? 
                  WHERE job_id = ? AND applicant_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sii", $_POST['status'], $job_id, $_POST['applicant_id']);
    
    if ($update_stmt->execute()) {
        // Refresh the page to show updated status
        header("Location: job_applicants.php?job_id=$job_id&success=status_updated");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicants for <?php echo htmlspecialchars($job['title']); ?> - JPOST</title>
    <style>
        body {
            background: #101014;
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
            background: #101014;
            border-bottom: 2px solid #222;
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
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .page-header {
            background: #fff;
            color: #222;
            border-radius: 10px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
        }
        .page-header h1 {
            margin: 0;
            font-size: 1.8em;
        }
        .page-header .company {
            color: #666;
            margin-top: 8px;
        }
        .applicants-table {
            width: 100%;
            background: #fff;
            color: #222;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
            border-collapse: collapse;
        }
        .applicants-table th, .applicants-table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .applicants-table th {
            background: #f7f7f7;
            font-weight: 600;
        }
        .applicants-table tr:last-child td {
            border-bottom: none;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.9em;
            font-weight: 500;
        }
        .status-badge.pending { background: #ffe082; color: #222; }
        .status-badge.reviewed { background: #80deea; color: #222; }
        .status-badge.interviewed { background: #4fc3f7; color: #222; }
        .status-badge.accepted { background: #a5d6a7; color: #222; }
        .status-badge.rejected { background: #ef9a9a; color: #222; }
        .actions {
            display: flex;
            gap: 8px;
        }
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background 0.2s;
        }
        .btn-primary {
            background: #4fc3f7;
            color: #fff;
        }
        .btn-secondary {
            background: #f5f5f5;
            color: #666;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .status-select {
            padding: 4px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fff;
            color: #222;
            font-size: 0.9em;
        }
        .success-message {
            background: #a5d6a7;
            color: #222;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 24px;
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
            <a href="job_postings.php" class="btn btn-secondary" style="margin-right:16px;">Back to Jobs</a>
            <a href="logout.php" style="color:#fff; text-decoration:none; background:#f44336; padding:8px 16px; border-radius:4px;">Logout</a>
        </div>
    </div>
    <div class="main-content">
        <div class="page-header">
            <h1>Applicants for <?php echo htmlspecialchars($job['title']); ?></h1>
            <div class="company"><?php echo htmlspecialchars($job['company_name']); ?></div>
        </div>
        
        <?php if (isset($_GET['success']) && $_GET['success'] === 'status_updated'): ?>
            <div class="success-message">Application status updated successfully.</div>
        <?php endif; ?>

        <table class="applicants-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Applied Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($applicants)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">No applicants found for this job.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($applicants as $applicant): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($applicant['name']); ?></td>
                            <td><?php echo htmlspecialchars($applicant['email']); ?></td>
                            <td><?php echo htmlspecialchars($applicant['phone']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($applicant['application_date'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="applicant_id" value="<?php echo $applicant['id']; ?>">
                                    <select name="status" class="status-select" onchange="this.form.submit()">
                                        <option value="pending" <?php echo $applicant['application_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="reviewed" <?php echo $applicant['application_status'] === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                        <option value="interviewed" <?php echo $applicant['application_status'] === 'interviewed' ? 'selected' : ''; ?>>Interviewed</option>
                                        <option value="accepted" <?php echo $applicant['application_status'] === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                                        <option value="rejected" <?php echo $applicant['application_status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                </form>
                            </td>
                            <td>
                                <div class="actions">
                                    <?php if (!empty($applicant['resume_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($applicant['resume_url']); ?>" target="_blank" class="btn btn-primary">View Resume</a>
                                    <?php endif; ?>
                                    <button class="btn btn-secondary" onclick="viewApplicant(<?php echo $applicant['id']; ?>)">View Details</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <script>
        function viewApplicant(applicantId) {
            window.location.href = `applicant_details.php?id=${applicantId}`;
        }
    </script>
</body>
</html> 