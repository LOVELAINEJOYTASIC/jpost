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
$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($job_id <= 0) {
    header('Location: job_postings.php?error=invalid_job');
    exit();
}

// Fetch job details with employer information
$sql = "SELECT j.*, e.company_name, e.company_logo, e.industry, e.website 
        FROM jobs j 
        LEFT JOIN employers e ON j.employer_id = e.id 
        WHERE j.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();

if (!$job) {
    header('Location: job_postings.php?error=job_not_found');
    exit();
}

// Get application count
$app_sql = "SELECT COUNT(*) as total FROM job_applications WHERE job_id = ?";
$app_stmt = $conn->prepare($app_sql);
$app_stmt->bind_param("i", $job_id);
$app_stmt->execute();
$app_result = $app_stmt->get_result();
$app_count = $app_result->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($job['title']); ?> - JPOST</title>
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
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .job-header {
            background: #fff;
            color: #222;
            border-radius: 10px;
            padding: 32px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
        }
        .company-info {
            display: flex;
            align-items: center;
            margin-bottom: 24px;
        }
        .company-logo {
            width: 64px;
            height: 64px;
            border-radius: 8px;
            margin-right: 16px;
            object-fit: cover;
        }
        .company-details h2 {
            margin: 0 0 8px 0;
            font-size: 1.8em;
        }
        .company-details .company-name {
            color: #666;
            font-size: 1.2em;
            margin-bottom: 8px;
        }
        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 16px;
        }
        .meta-item {
            background: #f5f5f5;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
            color: #666;
        }
        .job-content {
            background: #fff;
            color: #222;
            border-radius: 10px;
            padding: 32px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
        }
        .section {
            margin-bottom: 32px;
        }
        .section h3 {
            margin: 0 0 16px 0;
            font-size: 1.4em;
            color: #333;
        }
        .section p {
            margin: 0 0 16px 0;
            line-height: 1.6;
            color: #444;
        }
        .requirements-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }
        .requirements-list li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            color: #444;
        }
        .requirements-list li:last-child {
            border-bottom: none;
        }
        .actions {
            display: flex;
            gap: 16px;
            margin-top: 24px;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1em;
            transition: background 0.2s;
            text-decoration: none;
            display: inline-block;
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
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.9em;
            font-weight: 500;
            margin-left: 16px;
        }
        .status-badge.active { background: #a5d6a7; color: #222; }
        .status-badge.pending { background: #ffe082; color: #222; }
        .status-badge.closed { background: #ef9a9a; color: #222; }
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
        <div class="job-header">
            <div class="company-info">
                <?php if (!empty($job['company_logo'])): ?>
                    <img src="<?php echo htmlspecialchars($job['company_logo']); ?>" alt="Company Logo" class="company-logo">
                <?php endif; ?>
                <div class="company-details">
                    <h2><?php echo htmlspecialchars($job['title']); ?></h2>
                    <div class="company-name"><?php echo htmlspecialchars($job['company_name']); ?></div>
                    <span class="status-badge <?php echo strtolower($job['status']); ?>">
                        <?php echo ucfirst($job['status']); ?>
                    </span>
                </div>
            </div>
            <div class="job-meta">
                <span class="meta-item"><?php echo htmlspecialchars($job['location']); ?></span>
                <span class="meta-item"><?php echo htmlspecialchars($job['employment_type']); ?></span>
                <span class="meta-item"><?php echo htmlspecialchars($job['salary_range']); ?></span>
                <span class="meta-item"><?php echo htmlspecialchars($job['category']); ?></span>
                <span class="meta-item"><?php echo $app_count; ?> Applicants</span>
            </div>
        </div>
        <div class="job-content">
            <div class="section">
                <h3>Job Description</h3>
                <p><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>
            </div>
            <div class="section">
                <h3>Requirements</h3>
                <ul class="requirements-list">
                    <?php foreach (explode("\n", $job['requirements']) as $requirement): ?>
                        <li><?php echo htmlspecialchars($requirement); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="section">
                <h3>Company Information</h3>
                <p><strong>Industry:</strong> <?php echo htmlspecialchars($job['industry']); ?></p>
                <p><strong>Website:</strong> <a href="<?php echo htmlspecialchars($job['website']); ?>" target="_blank"><?php echo htmlspecialchars($job['website']); ?></a></p>
            </div>
            <div class="actions">
                <a href="job_applicants.php?job_id=<?php echo $job_id; ?>" class="btn btn-primary">View Applicants</a>
                <button class="btn btn-secondary" onclick="window.history.back()">Back to List</button>
            </div>
        </div>
    </div>
</body>
</html> 