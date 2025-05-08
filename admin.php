<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'config.php';

// Debug information
error_log("Session data: " . print_r($_SESSION, true));

// Get database connection
$conn = getDBConnection();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    error_log("Access denied - User not logged in or not admin");
    error_log("User ID: " . ($_SESSION['user_id'] ?? 'not set'));
    error_log("User Type: " . ($_SESSION['user_type'] ?? 'not set'));
    header('Location: login.php');
    exit();
}

// Fetch admin statistics
$stats = array();

// Total users count
$users_sql = "SELECT COUNT(*) as total FROM users WHERE user_type != 'admin'";
$users_result = $conn->query($users_sql);
$stats['total_users'] = $users_result->fetch_assoc()['total'];

// Total jobs count
$jobs_sql = "SELECT COUNT(*) as total FROM jobs";
$jobs_result = $conn->query($jobs_sql);
$stats['total_jobs'] = $jobs_result->fetch_assoc()['total'];

// Total applications count
$applications_sql = "SELECT COUNT(*) as total FROM job_applications";
$applications_result = $conn->query($applications_sql);
$stats['total_applications'] = $applications_result->fetch_assoc()['total'];

// Pending applications count
$pending_sql = "SELECT COUNT(*) as total FROM job_applications WHERE status = 'Pending'";
$pending_result = $conn->query($pending_sql);
$stats['pending_applications'] = $pending_result->fetch_assoc()['total'];

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
            position: relative;
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
            width: 220px;
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
        .admin-container {
            margin: 48px auto 0 auto;
            width: 95%;
            max-width: 1100px;
            min-width: 320px;
            background: #181818;
            border-radius: 16px;
            border: 2px solid #fff;
            padding: 32px 0 32px 0;
            min-height: 400px;
            position: relative;
        }
        .admin-title {
            text-align: center;
            font-size: 2.5em;
            font-weight: bold;
            letter-spacing: 2px;
            margin-bottom: 32px;
            color: #fff;
            text-transform: uppercase;
            text-decoration: underline;
        }
        .stats-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 24px;
            margin-bottom: 48px;
            padding: 0 24px;
        }
        .stat-card {
            background: #232a34;
            padding: 24px;
            border-radius: 12px;
            min-width: 200px;
            text-align: center;
            border: 1px solid #4fc3f7;
        }
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #4fc3f7;
            margin: 12px 0;
        }
        .stat-label {
            color: #fff;
            font-size: 1.1em;
        }
        .admin-buttons {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 32px 48px;
            margin-top: 32px;
        }
        .admin-btn {
            background: #fff;
            color: #222;
            border: none;
            border-radius: 24px;
            padding: 18px 38px;
            font-size: 1.2em;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
            margin-bottom: 12px;
            transition: background 0.2s, color 0.2s, transform 0.2s;
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
            .admin-buttons {
                flex-direction: column;
                align-items: center;
                gap: 18px;
            }
            .stats-container {
                flex-direction: column;
                align-items: center;
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
            <a href="login.php" style="color:#fff; text-decoration:none; margin-right:18px;">Login</a>
        </nav>
        <div style="display:flex; align-items:center;">
            <form class="search" style="margin:0;">
                <input type="text" placeholder="Find your dream job at JPost">
                <button type="submit">&#128269;</button>
            </form>
            <span class="settings">&#9881;</span>
        </div>
    </div>
    <div class="admin-container">
        <div class="admin-title">ADMIN DASHBOARD</div>
        
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-label">Total Users</div>
                <div class="stat-number"><?php echo $stats['total_users']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Jobs</div>
                <div class="stat-number"><?php echo $stats['total_jobs']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Applications</div>
                <div class="stat-number"><?php echo $stats['total_applications']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pending Applications</div>
                <div class="stat-number"><?php echo $stats['pending_applications']; ?></div>
            </div>
        </div>

        <div class="admin-buttons">
            <button class="admin-btn" onclick="location.href='manage_users.php'">Manage Users</button>
            <button class="admin-btn" onclick="location.href='candidate_status.php'">Candidate Status</button>
            <button class="admin-btn" onclick="location.href='security_updates.php'">Security Updates</button>
            <button class="admin-btn" onclick="location.href='track_candidate.php'">Track Candidate</button>
            <button class="admin-btn" onclick="location.href='workflow_management.php'">Workflow Management</button>
            <button class="admin-btn" onclick="location.href='application_overview.php'">Application Overview</button>
        </div>
    </div>
    <div class="footer">
        <a href="#">Security & Privacy</a>
        <a href="#">Terms and Condition</a>
        <a href="#">About</a>
        <a href="#">Report</a>
    </div>
</body>
</html> 