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
$sql = "SELECT id, username, user_type, status, last_login, created_at FROM users ORDER BY created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Updates - Admin</title>
    <style>
        body {
            background: #181818;
            color: #fff;
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .back-btn {
            color: #4fc3f7;
            text-decoration: none;
            font-size: 1.1em;
        }
        .security-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .security-card {
            background: #232a34;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .security-card h3 {
            color: #4fc3f7;
            margin-top: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: #232a34;
            border-radius: 10px;
            overflow: hidden;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #333;
        }
        th {
            background: #4fc3f7;
            color: #181818;
        }
        tr:hover {
            background: #2c3440;
        }
        .status-active {
            color: #4caf50;
            font-weight: bold;
        }
        .status-inactive {
            color: #f44336;
            font-weight: bold;
        }
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.2s;
        }
        .change-password-btn {
            background: #4fc3f7;
            color: #fff;
        }
        .toggle-status-btn {
            background: #ff9800;
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
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.2);
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #444;
            border-radius: 4px;
            background: #181818;
            color: #fff;
        }
        .success-message {
            background: #4caf50;
            color: #fff;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .error-message {
            background: #f44336;
            color: #fff;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Security Updates</h1>
            <a href="admin.php" class="back-btn">&larr; Back to Admin Dashboard</a>
        </div>

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

        <div class="security-grid">
            <div class="security-card">
                <h3>User Management</h3>
                <p>Manage user accounts, passwords, and access levels.</p>
            </div>
            <div class="security-card">
                <h3>Activity Log</h3>
                <p>Monitor user activity and system access.</p>
            </div>
            <div class="security-card">
                <h3>Security Settings</h3>
                <p>Configure system-wide security parameters.</p>
            </div>
        </div>

        <h2>User Accounts</h2>
        <table>
            <tr>
                <th>Username</th>
                <th>User Type</th>
                <th>Status</th>
                <th>Last Login</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['user_type']); ?></td>
                        <td class="status-<?php echo $row['status']; ?>">
                            <?php echo ucfirst($row['status']); ?>
                        </td>
                        <td><?php echo $row['last_login'] ? date('Y-m-d H:i', strtotime($row['last_login'])) : 'Never'; ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                        <td>
                            <button class="action-btn change-password-btn" onclick="openPasswordModal(<?php echo $row['id']; ?>)">Change Password</button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                <input type="hidden" name="current_status" value="<?php echo $row['status']; ?>">
                                <button type="submit" name="toggle_user_status" class="action-btn toggle-status-btn">
                                    <?php echo $row['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6">No users found.</td></tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- Password Change Modal -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <h2>Change User Password</h2>
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
                <button type="submit" name="change_password" class="action-btn change-password-btn">Update Password</button>
                <button type="button" class="action-btn" onclick="closePasswordModal()" style="background: #666;">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function openPasswordModal(userId) {
            document.getElementById('passwordModal').style.display = 'block';
            document.getElementById('password_user_id').value = userId;
        }

        function closePasswordModal() {
            document.getElementById('passwordModal').style.display = 'none';
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