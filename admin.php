<?php
session_start();
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
        .admin-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 70vh;
        }
        .admin-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 32px 48px;
            justify-content: center;
            margin-top: 60px;
        }
        .admin-btn {
            background: #fff;
            color: #181818;
            border: none;
            border-radius: 16px;
            padding: 22px 38px;
            font-size: 1.2em;
            font-weight: 600;
            margin: 12px;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
            transition: background 0.2s, color 0.2s, transform 0.2s;
            min-width: 220px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }
        .admin-btn:hover {
            background: #4fc3f7;
            color: #181818;
            transform: translateY(-3px) scale(1.04);
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
        @media (max-width: 900px) {
            .admin-buttons {
                flex-direction: column;
                gap: 18px;
            }
            .admin-btn {
                min-width: 180px;
                width: 90vw;
                max-width: 350px;
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
        <div style="display:flex; align-items:center; gap: 16px;">
            <form action="explore.php" method="GET" class="search" style="display:flex; align-items:center;">
                <input type="text" name="search" placeholder="Search jobs..." style="padding: 8px 12px; border-radius: 4px; border: 1px solid #333; background: #222; color: #fff;">
                <button type="submit" style="background: none; border: none; color: #fff; cursor: pointer; font-size: 1.2em; padding: 8px;">&#128269;</button>
            </form>
            <a href="logout.php" style="color:#fff; text-decoration:none; background:#f44336; padding:8px 16px; border-radius:4px;">Logout</a>
        </div>
    </div>
    <div class="admin-container">
        <h1 style="margin-top:40px; font-size:2.2em; color:#4fc3f7;">Admin Dashboard</h1>
        <div class="admin-buttons">
            <a class="admin-btn" href="manage_users.php">Manage Users</a>
            <a class="admin-btn" href="candidate_status.php">Candidate Status</a>
            <a class="admin-btn" href="security_updates.php">Security Updates</a>
            <a class="admin-btn" href="track_candidate.php">Track Candidate</a>
            <a class="admin-btn" href="workflow_management.php">Workflow Management</a>
            <a class="admin-btn" href="application_overview.php">Application Overview</a>
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