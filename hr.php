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

// Fetch applicants from database with search functionality
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';

$sql = "SELECT id, name, email, phone, status1, status2, status3, created_at FROM applicants WHERE 1=1";
if (!empty($search)) {
    $sql .= " AND (name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%')";
}
if (!empty($status_filter)) {
    $sql .= " AND status1 = '$status_filter'";
}
$sql .= " ORDER BY created_at DESC";

$result = $conn->query($sql);
$applicants = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $applicants[] = $row;
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
            background: #fff;
            color: #222;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
            padding: 32px 32px 24px 32px;
            min-width: 400px;
            max-width: 800px;
            flex-grow: 1;
        }
        .applicants-card h2 {
            margin-top: 0;
            margin-bottom: 18px;
            font-size: 1.4em;
            font-weight: bold;
        }
        .applicants-table {
            width: 100%;
            border-collapse: collapse;
        }
        .applicants-table th, .applicants-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .applicants-table th {
            color: #222;
            font-weight: 600;
            background: #f7f7f7;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.9em;
            font-weight: 500;
        }
        .badge.inreview { background: #ffe082; color: #222; }
        .badge.inprocess { background: #80deea; color: #222; }
        .badge.interview { background: #4fc3f7; color: #222; }
        .badge.ondemand { background: #ffd54f; color: #222; }
        .badge.accepted { background: #a5d6a7; color: #222; }
        .badge.cancelled { background: #ef9a9a; color: #222; }
        .badge.inwaiting { background: #ff8a65; color: #fff; }
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
            <form class="search" style="margin:0;" action="hr.php" method="GET">
                <input type="text" name="search" placeholder="Search applicants..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">&#128269;</button>
            </form>
            <a href="logout.php" style="color:#fff; text-decoration:none; margin-left:18px; background:#f44336; padding:8px 16px; border-radius:4px;">Logout</a>
        </div>
    </div>
    <div class="main-content">
        <div class="applicants-card">
            <h2>Applicants</h2>
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
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Applied</th>
                </tr>
                <?php if (empty($applicants)): ?>
                    <tr><td colspan="5" style="text-align:center;">No applicants found.</td></tr>
                <?php else: ?>
                    <?php foreach ($applicants as $app): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($app['name']); ?></td>
                            <td><?php echo htmlspecialchars($app['email']); ?></td>
                            <td><?php echo htmlspecialchars($app['phone']); ?></td>
                            <td>
                                <span class="badge <?php echo strtolower(str_replace(' ', '', $app['status1'])); ?>">
                                    <?php echo htmlspecialchars($app['status1']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($app['created_at'])); ?></td>
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
</body>
</html> 