<?php
session_start();
require_once 'config.php';
$conn = getDBConnection();

// Check if user is admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: index.php?error=unauthorized');
    exit();
}

// Add notes column if it doesn't exist
$check_column = $conn->query("SHOW COLUMNS FROM job_applications LIKE 'notes'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE job_applications ADD COLUMN notes TEXT");
}

// Add updated_at column if it doesn't exist
$check_updated_at = $conn->query("SHOW COLUMNS FROM job_applications LIKE 'updated_at'");
if ($check_updated_at->num_rows == 0) {
    $conn->query("ALTER TABLE job_applications ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
}

// Define valid statuses
$valid_statuses = [
    'Pending' => 'status-pending',
    'Under Review' => 'status-review',
    'Shortlisted' => 'status-shortlisted',
    'Interview Scheduled' => 'status-interview',
    'Offered' => 'status-offered',
    'Accepted' => 'status-accepted',
    'Rejected' => 'status-rejected',
    'Withdrawn' => 'status-withdrawn'
];

// Handle delete functionality
if (isset($_POST['delete_candidate'])) {
    $candidate_id = (int)$_POST['delete_candidate'];
    $delete_sql = "DELETE FROM job_applications WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $candidate_id);
    if ($delete_stmt->execute()) {
        header('Location: candidate_status.php?success=deleted');
        exit();
    }
    $delete_stmt->close();
}

// Handle edit functionality
if (isset($_POST['edit_candidate'])) {
    $candidate_id = (int)$_POST['edit_candidate'];
    $status = $conn->real_escape_string($_POST['status']);
    $notes = $conn->real_escape_string($_POST['notes']);
    
    // Validate status
    if (!array_key_exists($status, $valid_statuses)) {
        header('Location: candidate_status.php?error=invalid_status');
        exit();
    }
    
    $update_sql = "UPDATE job_applications SET status = ?, notes = ?, updated_at = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssi", $status, $notes, $candidate_id);
    if ($update_stmt->execute()) {
        header('Location: candidate_status.php?success=updated');
        exit();
    } else {
        header('Location: candidate_status.php?error=update_failed');
        exit();
    }
    $update_stmt->close();
}

// Fetch all job applications with user info
$sql = "SELECT u.username, u.id as user_id, ja.id as application_id, ja.status, ja.notes, j.job, j.company, ja.created_at, up.resume_file
        FROM job_applications ja
        INNER JOIN users u ON ja.user_id = u.id
        INNER JOIN jobs j ON ja.job_id = j.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        ORDER BY ja.created_at DESC";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Candidate Status - Admin</title>
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
            min-width: 700px;
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
        .status-pending {
            color: #ffd700;
            font-weight: bold;
        }
        .status-review {
            color: #ff9800;
            font-weight: bold;
        }
        .status-shortlisted {
            color: #2196f3;
            font-weight: bold;
        }
        .status-interview {
            color: #9c27b0;
            font-weight: bold;
        }
        .status-offered {
            color: #00bcd4;
            font-weight: bold;
        }
        .status-accepted {
            color: #4caf50;
            font-weight: bold;
        }
        .status-rejected {
            color: #f44336;
            font-weight: bold;
        }
        .status-withdrawn {
            color: #9e9e9e;
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
        .error-message {
            background: #f44336;
            color: #fff;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 24px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <h1>Candidate Status</h1>
    <?php if (isset($_GET['success'])): ?>
        <div class="success-message">
            <?php 
            if ($_GET['success'] === 'updated') echo 'Candidate status updated successfully!';
            if ($_GET['success'] === 'deleted') echo 'Candidate deleted successfully!';
            ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="error-message">
            <?php 
            if ($_GET['error'] === 'invalid_status') echo 'Invalid status selected!';
            if ($_GET['error'] === 'update_failed') echo 'Failed to update candidate status!';
            ?>
        </div>
    <?php endif; ?>
    <table>
        <tr>
            <th>Username</th>
            <th>Job Title</th>
            <th>Company</th>
            <th>Status</th>
            <th>Applied At</th>
            <th>Resume</th> <!-- Add this -->
            <th>Actions</th>
        </tr>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['job']); ?></td>
                    <td><?php echo htmlspecialchars($row['company']); ?></td>
                    <td class="<?php echo $valid_statuses[$row['status']] ?? ''; ?>">
                        <?php echo htmlspecialchars($row['status']); ?>
                    </td>
                    <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                    <td>
                        <?php if (!empty($row['resume_file'])): ?>
                            <a href="<?php echo htmlspecialchars($row['resume_file']); ?>" target="_blank" style="color:#4fc3f7;text-decoration:underline;">View Resume</a>
                        <?php else: ?>
                            <span style="color:#aaa;">No Resume</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button class="edit-btn" onclick="openEditModal(<?php echo $row['application_id']; ?>, '<?php echo htmlspecialchars($row['status']); ?>', '<?php echo htmlspecialchars($row['notes'] ?? ''); ?>')">Edit</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this application?');">
                                <input type="hidden" name="delete_candidate" value="<?php echo $row['application_id']; ?>">
                                <button type="submit" class="delete-btn">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="7">No applications found.</td></tr>
        <?php endif; ?>
    </table>
    <br>
    <a href="admin.php">&larr; Back to Admin Dashboard</a>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Update Candidate Status</h2>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" id="editForm">
                <input type="hidden" name="edit_candidate" id="edit_candidate_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select name="status" id="status" required>
                            <?php foreach ($valid_statuses as $status => $class): ?>
                                <option value="<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="notes">Notes:</label>
                        <textarea name="notes" id="notes" placeholder="Add any notes about this status change..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="cancel-btn" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="save-btn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openEditModal(id, currentStatus, currentNotes) {
        document.getElementById('editModal').style.display = 'block';
        document.getElementById('edit_candidate_id').value = id;
        document.getElementById('status').value = currentStatus;
        document.getElementById('notes').value = currentNotes;
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
    </script>
</body>
</html>