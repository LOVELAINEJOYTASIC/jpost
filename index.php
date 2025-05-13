<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'config.php';

// Get database connection
$conn = getDBConnection();

// Initialize tables
initializeTables($conn);

// Add search functionality
$search_query = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = htmlspecialchars($_GET['search']);
}

// Add advanced search parameters
$job_type = isset($_GET['job_type']) ? $conn->real_escape_string($_GET['job_type']) : '';
$location = isset($_GET['location']) ? $conn->real_escape_string($_GET['location']) : '';

// Get job count for search results
$jobs_count = 0;
if (!empty($search_query)) {
    $search = $conn->real_escape_string($search_query);
    $count_query = "SELECT COUNT(*) as count FROM jobs WHERE job LIKE '%$search%' OR company LIKE '%$search%' OR requirements LIKE '%$search%' OR salary LIKE '%$search%'";
    $result = $conn->query($count_query);
    if ($result) {
        $row = $result->fetch_assoc();
        $jobs_count = $row['count'];
    }
}

// Remove any redirection logic that might interfere with login page access
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JPOST - Job Portal</title>
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
        .navbar nav a:hover {
            color: #4fc3f7;
        }
        .hero {
            text-align: center;
            padding: 80px 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        .hero h1 {
            font-size: 3em;
            margin-bottom: 20px;
            color: #4fc3f7;
        }
        .hero p {
            font-size: 1.2em;
            color: #ccc;
            margin-bottom: 40px;
        }
        .cta-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
        }
        .cta-button {
            padding: 12px 32px;
            border-radius: 24px;
            font-size: 1.1em;
            font-weight: bold;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .primary-button {
            background: #4fc3f7;
            color: #222;
        }
        .secondary-button {
            background: transparent;
            color: #fff;
            border: 2px solid #4fc3f7;
        }
        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .footer {
            width: 100%;
            background: #181818;
            border-top: 2px solid #333;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 18px 0;
            position: fixed;
            bottom: 0;
            left: 0;
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
        .navbar .search button {
            background: none;
            border: none;
            color: #fff;
            cursor: pointer;
            font-size: 1.2em;
        }
        .navbar .settings {
            margin-left: 18px;
            font-size: 1.7em;
            color: #4fc3f7;
            cursor: pointer;
        }
        .main-banner {
            background: #5bbcff;
            margin: 40px auto 0 auto;
            border-radius: 10px;
            width: 80%;
            min-width: 340px;
            max-width: 900px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 40px 32px 32px;
            position: relative;
        }
        .main-banner .left {
            flex: 1.2;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .main-banner .right {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }
        .main-banner h1 {
            color: #fff;
            font-size: 2.2em;
            font-weight: bold;
            margin: 0 0 18px 0;
            font-style: italic;
            text-align: center;
        }
        .main-banner .whats-new {
            background: #222;
            color: #fff;
            border-radius: 16px;
            padding: 24px 28px;
            margin-bottom: 18px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
            width: 340px;
            margin-left: auto;
            margin-right: auto;
            text-align: center;
        }
        .main-banner .whats-new h2 {
            margin: 0 0 10px 0;
            font-size: 1.3em;
            font-weight: 600;
        }
        .main-banner .whats-new ul {
            list-style: none;
            padding: 0;
            margin: 10px 0 0 0;
            text-align: left;
            display: inline-block;
        }
        .main-banner .whats-new ul li {
            margin: 7px 0;
            font-size: 1em;
            display: flex;
            align-items: center;
        }
        .main-banner .whats-new ul li span {
            margin-right: 8px;
        }
        .main-banner .register-btn {
            display: block;
            background: #4fc3f7;
            color: #222;
            font-weight: bold;
            text-align: center;
            border-radius: 8px;
            padding: 12px 0;
            margin-top: 18px;
            text-decoration: none;
            font-size: 1.1em;
            transition: background 0.2s;
        }
        .main-banner .register-btn:hover {
            background: #0288d1;
            color: #fff;
        }
        .main-banner .banner-img {
            max-width: 320px;
            border-radius: 16px;
            background: #fff;
            padding: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            transition: transform 0.3s ease;
        }
        .main-banner .banner-img:hover {
            transform: translateY(-5px);
        }
        .main-banner .banner-img img {
            width: 100%;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            object-fit: cover;
            height: 400px;
        }
        .main-banner .checklist {
            position: absolute;
            left: -110px;
            bottom: -30px;
            width: 180px;
            z-index: 1;
        }
        @media (max-width: 800px) {
            .main-banner {
                flex-direction: column;
                align-items: flex-start;
                padding: 24px 10px 24px 10px;
            }
            .main-banner .right {
                justify-content: center;
                margin-top: 15px;
            }
            .main-banner .checklist {
                display: none;
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
            width: 320px;
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
        .modal-btn {
            display: block;
            width: 100%;
            background: #5bbcff;
            color: #222;
            font-weight: bold;
            border: none;
            border-radius: 16px;
            padding: 12px 0;
            font-size: 1.1em;
            text-align: center;
            text-decoration: none;
            margin-bottom: 8px;
            transition: background 0.2s;
        }
        .modal-btn:hover {
            background: #0288d1;
            color: #fff;
        }
        .footer-bar {
            width: 100%;
            background: #181818;
            border-top: 2px solid #fff;
            position: fixed;
            left: 0;
            bottom: 0;
            z-index: 100;
            padding: 0;
        }
        .footer-links {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 60px;
            padding: 4px 0 0 0;
        }
        .footer-links a {
            color: #fff;
            text-decoration: underline;
            font-size: 0.95em;
            padding: 0 8px 2px 8px;
            transition: color 0.2s;
        }
        .footer-links a:hover {
            color: #4fc3f7;
        }
        @media (max-width: 600px) {
            .footer-links {
                gap: 18px;
                font-size: 0.9em;
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
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="account.php">Account</a>
                <?php if ($_SESSION['user_type'] === 'employer'): ?>
                    <a href="dashboard.php">Dashboard</a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>
        <div style="display:flex; align-items:center; gap: 16px;">
            <form action="explore.php" method="GET" class="search" style="display:flex; align-items:center;">
                <input type="text" name="search" placeholder="Search jobs..." value="<?php echo $search_query; ?>" style="padding: 8px 12px; border-radius: 4px; border: 1px solid #333; background: #222; color: #fff;">
                <button type="submit" style="background: none; border: none; color: #fff; cursor: pointer; font-size: 1.2em; padding: 8px;">&#128269;</button>
            </form>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="logout.php" style="color:#fff; text-decoration:none; background:#f44336; padding:8px 16px; border-radius:4px;">Logout</a>
            <?php else: ?>
                <a href="login.php" style="color:#fff; text-decoration:none;">Login</a>
                <a href="signup.php" style="background:#4fc3f7; color:#222; padding:8px 16px; border-radius:16px; text-decoration:none; font-weight:bold;">Sign Up</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="hero">
        <h1>Find Your Dream Job</h1>
        <div class="cta-buttons">
            <a href="signup.php" class="cta-button primary-button">Get Started</a>
            <a href="explore.php" class="cta-button secondary-button">Browse Jobs</a>
        </div>
    </div>

    <div class="main-banner">
        <div class="left">
            <h1><span style="color:#fff; font-style:italic; font-weight:700;">Connecting Talent with Opportunity!</span></h1>
            <div class="whats-new">
                <h2>What's New?</h2>
                <div style="font-size:1em; margin-bottom:8px;">LOCAL JOB HIRING! <span style="color:#f44336;">&#128204;</span></div>
                <div style="font-size:1em; margin-bottom:8px; text-decoration:underline;">@VALENCIA PRIME TRADING</div>
                <ul>
                    <li><span>&#128188;</span> Office Clerk</li>
                    <li><span>&#128295;</span> Maintenance Staff</li>
                    <li><span>&#128230;</span> Warehouse Helper</li>
                    <li><span>&#128663;</span> Company Driver</li>
                    <li><span>&#128179;</span> Accounting Assistant</li>
                </ul>
                <a href="#" class="register-btn" id="openModalBtn">Register now!</a>
            </div>
        </div>
        <div class="right">
            <div class="banner-img">
                <img src="https://i.pinimg.com/736x/96/52/19/96521986718d625fb0889668ffaf1a61.jpg" alt="Professional Woman" />
            </div>
        </div>
    </div>

    <!-- Modal for Register -->
    <div id="registerModal" class="modal">
        <div class="modal-content">
            <span class="close" id="closeModalBtn">&times;</span>
            <h2 style="text-align:center; margin-bottom: 24px;">Register</h2>
            <div style="display: flex; flex-direction: column; gap: 18px; align-items: center;">
                <a href="signup.php" class="modal-btn">Sign Up</a>
                <a href="login.php" class="modal-btn">Login</a>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('openModalBtn').onclick = function() {
        document.getElementById('registerModal').style.display = 'block';
    };
    document.getElementById('closeModalBtn').onclick = function() {
        document.getElementById('registerModal').style.display = 'none';
    };
    window.onclick = function(event) {
        var modal = document.getElementById('registerModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    };
    </script>

    <?php if (isset($_SESSION['user_id'])): ?>
        <div style="background:#222;color:#4fc3f7;text-align:center;padding:8px;">You are logged in as <b><?php echo htmlspecialchars($_SESSION['username']); ?></b><?php echo isset($_SESSION['user_type']) ? ' (' . htmlspecialchars($_SESSION['user_type']) . ')' : ''; ?>.</div>
    <?php endif; ?>

    <?php
    if (isset($_SESSION['user_type'])) {
        error_log('user_type in session: ' . $_SESSION['user_type']);
    }
    ?>

    <footer class="footer-bar">
        <div class="footer-links">
            <a href="#">Security & Privacy</a>
            <a href="#">Terms and Condition</a>
            <a href="#">About</a>
            <a href="#">Report</a>
        </div>
    </footer>
</body>
</html> 