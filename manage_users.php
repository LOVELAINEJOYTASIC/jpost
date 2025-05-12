<?php
session_start();
require_once 'config.php';
$conn = getDBConnection();

// Check if user is admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: index.php?error=unauthorized');
    exit();
}

// Add status column if it doesn't exist
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN status ENUM('active', 'offline') DEFAULT 'offline'");
}

// Handle delete functionality
if (isset($_POST['delete_user'])) {
    $user_id = (int)$_POST['delete_user'];
    $delete_sql = "DELETE FROM users WHERE id = ? AND user_type = 'jobseeker'";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $user_id);
    if ($delete_stmt->execute()) {
        header('Location: manage_users.php?success=deleted');
        exit();
    }
    $delete_stmt->close();
}

// Handle edit functionality
if (isset($_POST['edit_user'])) {
    $user_id = (int)$_POST['edit_user'];
    $status = $conn->real_escape_string($_POST['status']);
    $notes = $conn->real_escape_string($_POST['notes']);
    
    $update_sql = "UPDATE users SET status = ?, notes = ? WHERE id = ? AND user_type = 'jobseeker'";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssi", $status, $notes, $user_id);
    if ($update_stmt->execute()) {
        header('Location: manage_users.php?success=updated');
        exit();
    }
    $update_stmt->close();
}

// Fetch all jobseeker users with their profiles
$sql = "SELECT u.*, up.full_name, up.birthday, up.address, up.contact, up.application 
        FROM users u 
        LEFT JOIN user_profiles up ON u.id = up.user_id 
        WHERE u.user_type = 'jobseeker' 
        ORDER BY u.created_at DESC";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users - Admin</title>
    <style>
        body {
            background: #181818;
            color: #fff;
            font-family: 'Segoe UI', Arial, sans-serif;
            text-align: center;
            padding-top: 40px;
        }
        a {
            color: #4fc3f7;
            text-decoration: underline;
        }
        table {
            margin: 0 auto;
            background: #232a34;
            border-radius: 10px;
            box-shadow: 0 2px 8px #0004;
            min-width: 900px;
        }
        th, td {
            padding: 12px 18px;
        }
        th {
            background: #4fc3f7;
            color: #222;
        }
        tr:nth-child(even) {
            background: #222;
        }
        tr:nth-child(odd) {
            background: #232a34;
        }
        .status-active {
            color: #4caf50;
            font-weight: bold;
        }
        .status-offline {
            color: #f44336;
            font-weight: bold;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        .edit-btn, .delete-btn {
            padding: 6px 12px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.2s;
        }
        .edit-btn {
            background: #4fc3f7;
            color: #fff;
        }
        .delete-btn {
            background: #f44336;
            color: #fff;
        }
        .edit-btn:hover, .delete-btn:hover {
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
            color: #fff;
            width: 90%;
            max-width: 500px;
            margin: 50px auto;
            padding: 24px;
            border-radius: 10px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.2);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .modal-header h2 {
            margin: 0;
            color: #fff;
        }
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            color: #fff;
        }
        .modal-body {
            margin-bottom: 24px;
        }
        .form-group {
            margin-bottom: 16px;
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #fff;
        }
        .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #444;
            border-radius: 6px;
            background: #181818;
            color: #fff;
            font-size: 1em;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .modal-footer {
            text-align: right;
        }
        .save-btn {
            background: #4fc3f7;
            color: #fff;
            border: none;
            padding: 8px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1em;
        }
        .save-btn:hover {
            background: #0288d1;
        }
        .success-message {
            background: #4caf50;
            color: #fff;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 24px;
            display: inline-block;
        }
        .search-bar {
            margin-bottom: 20px;
        }
        .search-bar input {
            padding: 8px 16px;
            border: none;
            border-radius: 20px;
            width: 300px;
            background: #232a34;
            color: #fff;
            font-size: 1em;
        }
        .search-bar input:focus {
            outline: none;
            box-shadow: 0 0 0 2px #4fc3f7;
        }
    </style>
</head>
<body>
    <h1>Manage Users</h1>
    <?php if (isset($_GET['success'])): ?>
        <div class="success-message">
            <?php 
            if ($_GET['success'] === 'updated') echo 'User status updated successfully!';
            if ($_GET['success'] === 'deleted') echo 'User deleted successfully!';
            ?>
        </div>
    <?php endif; ?>

    <div class="search-bar">
        <input type="text" id="searchInput" placeholder="Search users..." onkeyup="searchUsers()">
    </div>

    <table>
        <tr>
            <th>Username</th>
            <th>Full Name</th>
            <th>Contact</th>
            <th>Status</th>
            <th>Joined</th>
            <th>Actions</th>
        </tr>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <tr class="user-row">
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['full_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($row['contact'] ?? 'N/A'); ?></td>
                    <td class="status-<?php echo strtolower($row['status'] ?? 'offline'); ?>">
                        <?php echo ucfirst($row['status'] ?? 'offline'); ?>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                    <td>
                        <div class="action-buttons">
                            <button class="edit-btn" onclick="openEditModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['status'] ?? 'offline'); ?>', '<?php echo htmlspecialchars($row['notes'] ?? ''); ?>')">Edit</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                <input type="hidden" name="delete_user" value="<?php echo $row['id']; ?>">
                                <button type="submit" class="delete-btn">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6">No users found.</td></tr>
        <?php endif; ?>
    </table>
    <br>
    <a href="admin.php">&larr; Back to Admin Dashboard</a>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit User Status</h2>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="edit_user" id="edit_user_id">
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select name="status" id="status" required>
                            <option value="active">Active</option>
                            <option value="offline">Offline</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="notes">Notes:</label>
                        <textarea name="notes" id="notes" placeholder="Add any notes about the user..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="save-btn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openEditModal(id, currentStatus, currentNotes) {
        document.getElementById('editModal').style.display = 'block';
        document.getElementById('edit_user_id').value = id;
        document.getElementById('status').value = currentStatus;
        document.getElementById('notes').value = currentNotes || '';
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target == document.getElementById('editModal')) {
            closeEditModal();
        }
    }

    // Search functionality
    function searchUsers() {
        const input = document.getElementById('searchInput');
        const filter = input.value.toLowerCase();
        const rows = document.getElementsByClassName('user-row');

        for (let row of rows) {
            const username = row.cells[0].textContent.toLowerCase();
            const fullName = row.cells[1].textContent.toLowerCase();
            const contact = row.cells[2].textContent.toLowerCase();
            
            if (username.includes(filter) || fullName.includes(filter) || contact.includes(filter)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    }
    </script>
</body>
</html> 