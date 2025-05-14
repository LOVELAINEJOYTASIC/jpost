<?php
session_start();
require_once 'config.php';
$conn = getDBConnection();

// Check if user is admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: index.php?error=unauthorized');
    exit();
}

// Handle password change
if (isset($_POST['change_password'])) {
    $user_id = (int)$_POST['user_id'];
    $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    
    $update_sql = "UPDATE users SET password = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_password, $user_id);
    
    if ($update_stmt->execute()) {
        header('Location: security_updates.php?success=password_updated');
    } else {
        header('Location: security_updates.php?error=update_failed');
    }
    exit();
}

// Handle user deactivation
if (isset($_POST['toggle_user_status'])) {
    $user_id = (int)$_POST['user_id'];
    $new_status = $_POST['current_status'] === 'active' ? 'inactive' : 'active';
    
    $update_sql = "UPDATE users SET status = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_status, $user_id);
    
    if ($update_stmt->execute()) {
        header('Location: security_updates.php?success=status_updated');
    } else {
        header('Location: security_updates.php?error=update_failed');
    }
    exit();
}

// Fetch all users
$sql = "SELECT id, username, user_type, status, created_at FROM users ORDER BY created_at DESC";
$result = $conn->query($sql);

// Get security statistics
$stats = [
    'total_users' => 0,
    'active_users' => 0,
    'admin_users' => 0
];

$stats_sql = "SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
    SUM(CASE WHEN user_type = 'admin' THEN 1 ELSE 0 END) as admin_users
FROM users";
$stats_result = $conn->query($stats_sql);
if ($stats_result && $row = $stats_result->fetch_assoc()) {
    $stats = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Updates - JPOST</title>
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
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .security-card {
            background: #232a34;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }
        .security-card h2 {
            color: #4fc3f7;
            margin-top: 0;
            margin-bottom: 16px;
        }
        .security-card p {
            color: #ccc;
            line-height: 1.6;
            margin-bottom: 16px;
        }
        .security-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .security-list li {
            background: #1a1f28;
            padding: 16px;
            margin-bottom: 12px;
            border-radius: 8px;
            border-left: 4px solid #4fc3f7;
        }
        .security-list li h3 {
            color: #4fc3f7;
            margin: 0 0 8px 0;
        }
        .security-list li p {
            color: #ccc;
            margin: 0;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.9em;
            margin-left: 12px;
        }
        .status-active {
            background: #4caf50;
            color: #fff;
        }
        .status-pending {
            background: #ff9800;
            color: #fff;
        }
        .status-completed {
            background: #2196f3;
            color: #fff;
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
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: #1a1f28;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
        }
        .stat-title {
            color: #888;
            font-size: 0.9em;
            margin-bottom: 8px;
        }
        .stat-value {
            color: #4fc3f7;
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .security-alert {
            background: #ff9800;
            color: #fff;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .security-alert i {
            font-size: 1.5em;
        }
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 16px;
        }
        .action-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.2s;
        }
        .primary-btn {
            background: #4fc3f7;
            color: #fff;
        }
        .secondary-btn {
            background: #666;
            color: #fff;
        }
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        .modal-content {
            background: #232a34;
            width: 90%;
            max-width: 500px;
            margin: 50px auto;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.2);
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #ccc;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #444;
            border-radius: 4px;
            background: #1a1f28;
            color: #fff;
        }
        .success-message {
            background: #4caf50;
            color: #fff;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
        }
        .error-message {
            background: #f44336;
            color: #fff;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
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

    <div class="container">
        <a href="admin.php" style="display:inline-block; margin-bottom:24px; background:#4fc3f7; color:#181818; padding:10px 28px; border-radius:8px; text-decoration:none; font-weight:600; font-size:1.1em; box-shadow:0 2px 8px rgba(0,0,0,0.10); transition:background 0.2s, color 0.2s;">&larr; Back to Menu</a>

        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                <?php 
                if ($_GET['success'] === 'password_updated') echo 'Password updated successfully!';
                if ($_GET['success'] === 'status_updated') echo 'User status updated successfully!';
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="error-message">
                <?php 
                if ($_GET['error'] === 'update_failed') echo 'Failed to update. Please try again.';
                ?>
            </div>
        <?php endif; ?>

        <div class="security-card">
            <h2>Security Overview</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-title">Total Users</div>
                    <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Active Users</div>
                    <div class="stat-value"><?php echo $stats['active_users']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Admin Users</div>
                    <div class="stat-value"><?php echo $stats['admin_users']; ?></div>
                </div>
            </div>
            <div class="security-alert">
                <i>⚠️</i>
                <div>
                    <strong>Security Alert:</strong> Please ensure all admin accounts have strong passwords and 2FA enabled.
                </div>
            </div>
        </div>

        <div class="security-card">
            <h2>Recent Security Updates</h2>
            <ul class="security-list">
                <li>
                    <h3>Password Policy Update <span class="status-badge status-active">Active</span></h3>
                    <p>Enhanced password requirements implemented for all users. Minimum length increased to 12 characters with mandatory special characters.</p>
                    <div class="action-buttons">
                        <button class="action-btn primary-btn" onclick="viewDetails('password_policy')">View Details</button>
                    </div>
                </li>
                <li>
                    <h3>Two-Factor Authentication <span class="status-badge status-completed">Completed</span></h3>
                    <p>Successfully implemented 2FA for admin accounts. All admin users are now required to use 2FA for login.</p>
                    <div class="action-buttons">
                        <button class="action-btn primary-btn" onclick="viewDetails('2fa')">View Details</button>
                    </div>
                </li>
                <li>
                    <h3>Database Encryption <span class="status-badge status-pending">In Progress</span></h3>
                    <p>Implementing end-to-end encryption for sensitive user data. Expected completion: Next month.</p>
                    <div class="action-buttons">
                        <button class="action-btn primary-btn" onclick="viewDetails('encryption')">View Details</button>
                    </div>
                </li>
                <li>
                    <h3>Security Audit <span class="status-badge status-completed">Completed</span></h3>
                    <p>Annual security audit completed. All critical vulnerabilities have been addressed.</p>
                    <div class="action-buttons">
                        <button class="action-btn primary-btn" onclick="viewDetails('audit')">View Details</button>
                    </div>
                </li>
            </ul>
        </div>

        <div class="security-card">
            <h2>Security Recommendations</h2>
            <ul class="security-list">
                <li>
                    <h3>Regular Password Updates</h3>
                    <p>Ensure all admin accounts have strong, unique passwords and are updated regularly.</p>
                    <div class="action-buttons">
                        <button class="action-btn primary-btn" onclick="openPasswordModal()">Change Password</button>
                    </div>
                </li>
                <li>
                    <h3>System Updates</h3>
                    <p>Keep all systems and software up to date with the latest security patches.</p>
                    <div class="action-buttons">
                        <button class="action-btn primary-btn" onclick="checkUpdates()">Check Updates</button>
                    </div>
                </li>
                <li>
                    <h3>Access Control</h3>
                    <p>Regularly review and update user access permissions to maintain security.</p>
                    <div class="action-buttons">
                        <button class="action-btn primary-btn" onclick="reviewAccess()">Review Access</button>
                    </div>
                </li>
            </ul>
        </div>
    </div>

    <div class="footer">
        <a href="#">Security & Privacy</a>
        <a href="#">Terms and Condition</a>
        <a href="#">About</a>
        <a href="#">Report</a>
    </div>

    <!-- Password Change Modal -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <h2>Change Password</h2>
            <form method="POST">
                <input type="hidden" name="user_id" id="password_user_id">
                <div class="form-group">
                    <label for="new_password">New Password:</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <div class="action-buttons">
                    <button type="submit" name="change_password" class="action-btn primary-btn">Update Password</button>
                    <button type="button" class="action-btn secondary-btn" onclick="closePasswordModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openPasswordModal() {
            document.getElementById('passwordModal').style.display = 'block';
        }

        function closePasswordModal() {
            document.getElementById('passwordModal').style.display = 'none';
        }

        function viewDetails(type) {
            // Implement view details functionality
            alert('View details for ' + type + ' will be implemented soon.');
        }

        function checkUpdates() {
            // Implement check updates functionality
            alert('Checking for system updates...');
        }

        function reviewAccess() {
            // Implement review access functionality
            alert('Access review will be implemented soon.');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == document.getElementById('passwordModal')) {
                closePasswordModal();
            }
        }

        // Password confirmation validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    </script>
</body>
</html> 