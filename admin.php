<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['user_type']) !== 'admin') {
    header('Location: login.php');
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "jpost";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Remove or comment out the schema change code below:
// $sql = "ALTER TABLE users ADD COLUMN notes TEXT NULL";
// if ($conn->query($sql) === TRUE) {
//     echo "Column 'notes' added successfully.";
// } else {
//     echo "Error updating table: " . $conn->error;
// }

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - JPOST</title>
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
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 32px 48px;
            justify-content: center;
            margin-top: 60px;
            width: 100%;
            max-width: 1100px;
        }
        .admin-card {
            background: #232a34;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
            padding: 32px 24px 28px 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 220px;
        }
        .admin-card h3 {
            margin: 0 0 10px 0;
            color: #4fc3f7;
            font-size: 1.3em;
            font-weight: 600;
        }
        .admin-card p {
            color: #ccc;
            font-size: 1em;
            margin-bottom: 24px;
            text-align: center;
        }
        .admin-card .admin-btn {
            margin: 0;
            min-width: 160px;
            width: 100%;
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
        .note-highlight {
            background: #fffbe7;
            color: #b26a00;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 500;
            display: inline-block;
            margin: 0;
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
                grid-template-columns: 1fr;
                gap: 18px;
            }
            .admin-card {
                min-width: unset;
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
            <div class="admin-card">
                <h3>Manage Users</h3>
                <p>View, edit, and remove users from the platform.</p>
                <a class="admin-btn" href="manage_users.php">Manage Users</a>
            </div>
            <div class="admin-card">
                <h3>Candidate Status</h3>
                <p>Manage candidate status options for job applications.</p>
                <a href="candidate_status.php" class="admin-btn">Manage Status</a>
            </div>
            <div class="admin-card">
                <h3>Security Updates</h3>
                <p>View and manage security updates and recommendations.</p>
                <a href="security_updates.php" class="admin-btn">View Updates</a>
            </div>
            <div class="admin-card">
                <h3>Track Candidate</h3>
                <p>Track the progress and status of candidates throughout the hiring process.</p>
                <a class="admin-btn" href="track_candidate.php">Track Candidate</a>
            </div>
            <div class="admin-card">
                <h3>Workflow Management</h3>
                <p>Manage and optimize the recruitment workflow and processes.</p>
                <a class="admin-btn" href="workflow_management.php">Workflow Management</a>
            </div>
            <div class="admin-card">
                <h3>Application Overview</h3>
                <p>Get an overview of all job applications and their statuses.</p>
                <a class="admin-btn" href="application_overview.php">Application Overview</a>
            </div>
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