<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Debug information
error_log("Admin page accessed - Session contents: " . print_r($_SESSION, true));

// Only allow access if user is admin
if (!isset($_SESSION['user_id'])) {
    error_log("No user_id in session - Redirecting to login");
    header('Location: login.php?error=not_logged_in');
    exit();
}

require_once 'config.php';
$conn = getDBConnection();

// Check if user is admin
$user_id = $_SESSION['user_id'];
error_log("Checking admin status for user_id: " . $user_id);

$check_admin = $conn->prepare("SELECT user_type FROM users WHERE id = ?");
$check_admin->bind_param("i", $user_id);
$check_admin->execute();
$result = $check_admin->get_result();
$user = $result->fetch_assoc();

error_log("Database user data: " . print_r($user, true));
error_log("Session user_type: " . ($_SESSION['user_type'] ?? 'not set'));

if (!$user || strtolower($user['user_type']) !== 'admin') {
    error_log("Access denied - User type is not admin: " . ($user['user_type'] ?? 'not set'));
    header('Location: index.php?error=unauthorized');
    exit();
}

error_log("Access granted - User is admin");
$check_admin->close();

// Add search functionality
$search_query = '';
$search_type = 'all'; // Default to searching all

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $search_query = htmlspecialchars($_GET['search']);
}

if (isset($_GET['type']) && !empty($_GET['type'])) {
    $search_type = $conn->real_escape_string($_GET['type']);
}

// Add advanced search parameters
$user_type = isset($_GET['user_type']) ? $conn->real_escape_string($_GET['user_type']) : '';
$job_type = isset($_GET['job_type']) ? $conn->real_escape_string($_GET['job_type']) : '';
$date_from = isset($_GET['date_from']) ? $conn->real_escape_string($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? $conn->real_escape_string($_GET['date_to']) : '';

// Modify the queries to include advanced filters
$users_query = "SELECT * FROM users WHERE 1=1";
$jobs_query = "SELECT * FROM jobs WHERE 1=1";

if (!empty($search_query)) {
    if ($search_type === 'all' || $search_type === 'users') {
        $users_query .= " AND (username LIKE '%$search%' OR user_type LIKE '%$search%' OR email LIKE '%$search%')";
    }
    if ($search_type === 'all' || $search_type === 'jobs') {
        $jobs_query .= " AND (job LIKE '%$search%' OR company LIKE '%$search%' OR requirements LIKE '%$search%' OR salary LIKE '%$search%')";
    }
}

if (!empty($user_type) && ($search_type === 'all' || $search_type === 'users')) {
    $users_query .= " AND user_type = '$user_type'";
}

if (!empty($job_type) && ($search_type === 'all' || $search_type === 'jobs')) {
    $jobs_query .= " AND job_type = '$job_type'";
}

if (!empty($date_from)) {
    if ($search_type === 'all' || $search_type === 'users') {
        $users_query .= " AND created_at >= '$date_from'";
    }
    if ($search_type === 'all' || $search_type === 'jobs') {
        $jobs_query .= " AND created_at >= '$date_from'";
    }
}

if (!empty($date_to)) {
    if ($search_type === 'all' || $search_type === 'users') {
        $users_query .= " AND created_at <= '$date_to'";
    }
    if ($search_type === 'all' || $search_type === 'jobs') {
        $jobs_query .= " AND created_at <= '$date_to'";
    }
}

$users = $conn->query($users_query);
$jobs = $conn->query($jobs_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - JPOST</title>
    <style>
        body {
            background: #181818;
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
        }
        .admin-btns-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 60vh;
        }
        .admin-btns-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 32px 48px;
            max-width: 800px;
            margin: 0 auto;
        }
        .admin-btn {
            background: #fff;
            color: #222;
            border: none;
            border-radius: 16px;
            padding: 18px 0;
            font-size: 1.1em;
            font-weight: 500;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
            transition: background 0.2s, color 0.2s, transform 0.2s;
            width: 220px;
            margin: 0 auto;
            display: block;
        }
        .admin-btn:hover {
            background: #4fc3f7;
            color: #fff;
            transform: translateY(-2px);
        }
        .footer {
            width: 100%;
            background: #181818;
            border-top: 2px solid #333;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 12px 0 8px 0;
            position: fixed;
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
            .admin-btns-grid {
                grid-template-columns: 1fr;
                gap: 18px;
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
            <a href="account.php">Account</a>
        </nav>
        <div style="display:flex; align-items:center;">
            <form class="search" style="margin:0;" action="admin.php" method="GET">
                <input type="text" name="search" placeholder="Search users or jobs..." value="<?php echo $search_query; ?>">
                <select name="type" style="background:transparent;border:none;color:#222;outline:none;margin-right:8px;">
                    <option value="all" <?php echo $search_type === 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="users" <?php echo $search_type === 'users' ? 'selected' : ''; ?>>Users</option>
                    <option value="jobs" <?php echo $search_type === 'jobs' ? 'selected' : ''; ?>>Jobs</option>
                </select>
                <button type="submit">&#128269;</button>
            </form>
            <span class="settings">&#9881;</span>
            <a href="logout.php" style="color:#fff; text-decoration:none; margin-left:18px; background:#f44336; padding:8px 16px; border-radius:4px;">Logout</a>
        </div>
    </div>
    <div class="admin-btns-container">
        <div class="admin-btns-grid">
            <button class="admin-btn">Manage Users</button>
            <a class="admin-btn" href="candidate_status.php">Candidate Status</a>
            <button class="admin-btn">Security Updates</button>
            <button class="admin-btn">Track Candidate</button>
            <button class="admin-btn">Workflow Management</button>
            <button class="admin-btn">Application Overview</button>
        </div>
    </div>
    <div class="footer">
        <a href="#">Security & Privacy</a>
        <a href="#">Terms and Condition</a>
        <a href="#">About</a>
        <a href="#">Report</a>
    </div>
    <div style="color:yellow;background:#222;padding:8px;margin:8px 32px;">
        Search Results: 
        <?php if (!empty($search_query) || !empty($user_type) || !empty($job_type) || !empty($date_from) || !empty($date_to)): ?>
            Found <?php echo $users->num_rows; ?> users and <?php echo $jobs->num_rows; ?> jobs
            <?php if (!empty($search_query)): ?>
                matching "<?php echo $search_query; ?>"
            <?php endif; ?>
            <?php if (!empty($user_type)): ?>
                with user type <?php echo htmlspecialchars($user_type); ?>
            <?php endif; ?>
            <?php if (!empty($job_type)): ?>
                with job type <?php echo htmlspecialchars($job_type); ?>
            <?php endif; ?>
            <?php if (!empty($date_from) || !empty($date_to)): ?>
                created between 
                <?php 
                    if (!empty($date_from) && !empty($date_to)) {
                        echo date('M d, Y', strtotime($date_from)) . " and " . date('M d, Y', strtotime($date_to));
                    } elseif (!empty($date_from)) {
                        echo "after " . date('M d, Y', strtotime($date_from));
                    } elseif (!empty($date_to)) {
                        echo "before " . date('M d, Y', strtotime($date_to));
                    }
                ?>
            <?php endif; ?>
        <?php else: ?>
            Showing all users and jobs
        <?php endif; ?>
    </div>
    <div class="advanced-search" style="background:#fff;border-radius:12px;padding:16px;margin:16px 32px;">
        <form action="admin.php" method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
            <input type="hidden" name="search" value="<?php echo $search_query; ?>">
            <input type="hidden" name="type" value="<?php echo $search_type; ?>">
            
            <?php if ($search_type === 'all' || $search_type === 'users'): ?>
            <div>
                <label style="display:block;color:#222;margin-bottom:4px;font-weight:500;">User Type</label>
                <select name="user_type" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
                    <option value="">All User Types</option>
                    <option value="admin" <?php echo $user_type === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="employer" <?php echo $user_type === 'employer' ? 'selected' : ''; ?>>Employer</option>
                    <option value="user" <?php echo $user_type === 'user' ? 'selected' : ''; ?>>User</option>
                </select>
            </div>
            <?php endif; ?>
            
            <?php if ($search_type === 'all' || $search_type === 'jobs'): ?>
            <div>
                <label style="display:block;color:#222;margin-bottom:4px;font-weight:500;">Job Type</label>
                <select name="job_type" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
                    <option value="">All Job Types</option>
                    <option value="Full Time" <?php echo $job_type === 'Full Time' ? 'selected' : ''; ?>>Full Time</option>
                    <option value="Part Time" <?php echo $job_type === 'Part Time' ? 'selected' : ''; ?>>Part Time</option>
                    <option value="Remote" <?php echo $job_type === 'Remote' ? 'selected' : ''; ?>>Remote</option>
                    <option value="Internship" <?php echo $job_type === 'Internship' ? 'selected' : ''; ?>>Internship</option>
                </select>
            </div>
            <?php endif; ?>
            
            <div>
                <label style="display:block;color:#222;margin-bottom:4px;font-weight:500;">Date Range</label>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="date" name="date_from" value="<?php echo $date_from; ?>" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
                    <span style="color:#666;">to</span>
                    <input type="date" name="date_to" value="<?php echo $date_to; ?>" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
                </div>
            </div>
            
            <div style="display:flex;align-items:flex-end;">
                <button type="submit" style="background:#4fc3f7;color:#222;border:none;padding:8px 24px;border-radius:6px;font-weight:bold;cursor:pointer;width:100%;">Apply Filters</button>
            </div>
        </form>
    </div>
</body>
</html> 