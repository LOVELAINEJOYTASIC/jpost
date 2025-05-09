<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Only allow access if user is admin
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['user_type'] ?? '') !== 'admin') {
    header('Location: login.php?error=unauthorized');
    exit();
}

require_once 'config.php';
$conn = getDBConnection();
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
            <form class="search" style="margin:0;">
                <input type="text" placeholder="Find your dream job at JPost">
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
</body>
</html> 