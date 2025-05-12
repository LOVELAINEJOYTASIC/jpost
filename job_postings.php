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

// Get search and filter parameters
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$category = isset($_GET['category']) ? $conn->real_escape_string($_GET['category']) : '';
$status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';

// Build the query
$sql = "SELECT j.*, e.company_name, e.company_logo 
        FROM jobs j 
        LEFT JOIN employers e ON j.employer_id = e.id 
        WHERE 1=1";

if (!empty($search)) {
    $sql .= " AND (j.title LIKE '%$search%' OR j.description LIKE '%$search%' OR e.company_name LIKE '%$search%')";
}
if (!empty($category)) {
    $sql .= " AND j.category = '$category'";
}
if (!empty($status)) {
    $sql .= " AND j.status = '$status'";
}

$sql .= " ORDER BY j.created_at DESC";

$result = $conn->query($sql);
$jobs = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }
}

// Get unique categories for filter
$categories = [];
$cat_result = $conn->query("SELECT DISTINCT category FROM jobs WHERE category IS NOT NULL");
if ($cat_result) {
    while ($row = $cat_result->fetch_assoc()) {
        $categories[] = $row['category'];
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
        .navbar .search {
            display: flex;
            align-items: center;
            background: #f7f7d0;
            border-radius: 20px;
            padding: 4px 12px;
            margin-left: 24px;
        }
        .navbar .search input {
            background: transparent;
            border: none;
            color: #222;
            outline: none;
            padding: 6px 8px;
            font-size: 1em;
            width: 180px;
        }
        .navbar .search button {
            background: none;
            border: none;
            color: #222;
            cursor: pointer;
            font-size: 1.2em;
        }
        .main-content {
            padding: 40px;
        }
        .filters {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            color: #222;
        }
        .filters select, .filters input {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: #fff;
            color: #222;
            font-size: 1em;
        }
        .jobs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
            margin-top: 24px;
        }
        .job-card {
            background: #fff;
            color: #222;
            border-radius: 10px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
        }
        .job-card .company {
            display: flex;
            align-items: center;
            margin-bottom: 16px;
        }
        .job-card .company-logo {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            margin-right: 12px;
            object-fit: cover;
        }
        .job-card .company-name {
            font-weight: 600;
            color: #666;
        }
        .job-card h3 {
            margin: 0 0 12px 0;
            font-size: 1.2em;
        }
        .job-card .details {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
        }
        .job-card .detail {
            background: #f5f5f5;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.9em;
            color: #666;
        }
        .job-card .description {
            color: #666;
            font-size: 0.95em;
            margin-bottom: 16px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .job-card .actions {
            display: flex;
            gap: 12px;
        }
        .job-card .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background 0.2s;
        }
        .job-card .btn-primary {
            background: #4fc3f7;
            color: #fff;
        }
        .job-card .btn-secondary {
            background: #f5f5f5;
            color: #666;
        }
        .job-card .btn:hover {
            opacity: 0.9;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.9em;
            font-weight: 500;
            margin-left: auto;
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
            <form class="search" style="margin:0;" action="job_postings.php" method="GET">
                <input type="text" name="search" placeholder="Search jobs..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">&#128269;</button>
            </form>
            <a href="logout.php" style="color:#fff; text-decoration:none; margin-left:18px; background:#f44336; padding:8px 16px; border-radius:4px;">Logout</a>
        </div>
    </div>
    <div class="main-content">
        <div class="filters">
            <form action="job_postings.php" method="GET" style="display:flex; gap:16px; width:100%;">
                <select name="category" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="status" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="closed" <?php echo $status === 'closed' ? 'selected' : ''; ?>>Closed</option>
                </select>
                <?php if (!empty($search)): ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <?php endif; ?>
            </form>
        </div>
        <div class="jobs-grid">
            <?php if (empty($jobs)): ?>
                <div style="grid-column: 1/-1; text-align: center; color: #fff; padding: 40px;">
                    No jobs found matching your criteria.
                </div>
            <?php else: ?>
                <?php foreach ($jobs as $job): ?>
                    <div class="job-card">
                        <div class="company">
                            <?php if (!empty($job['company_logo'])): ?>
                                <img src="<?php echo htmlspecialchars($job['company_logo']); ?>" alt="Company Logo" class="company-logo">
                            <?php endif; ?>
                            <div class="company-name"><?php echo htmlspecialchars($job['company_name']); ?></div>
                            <span class="status-badge <?php echo strtolower($job['status']); ?>">
                                <?php echo ucfirst($job['status']); ?>
                            </span>
                        </div>
                        <h3><?php echo htmlspecialchars($job['title']); ?></h3>
                        <div class="details">
                            <span class="detail"><?php echo htmlspecialchars($job['category']); ?></span>
                            <span class="detail"><?php echo htmlspecialchars($job['location']); ?></span>
                            <span class="detail"><?php echo htmlspecialchars($job['employment_type']); ?></span>
                        </div>
                        <div class="description">
                            <?php echo htmlspecialchars($job['description']); ?>
                        </div>
                        <div class="actions">
                            <button class="btn btn-primary" onclick="viewJob(<?php echo $job['id']; ?>)">View Details</button>
                            <button class="btn btn-secondary" onclick="viewApplicants(<?php echo $job['id']; ?>)">View Applicants</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <script>
        function viewJob(jobId) {
            window.location.href = `job_details.php?id=${jobId}`;
        }
        
        function viewApplicants(jobId) {
            window.location.href = `job_applicants.php?job_id=${jobId}`;
        }
    </script>
</body>
</html> 