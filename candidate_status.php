<?php
session_start();
require_once 'config.php';
$conn = getDBConnection();

// Only allow admin
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['user_type'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit();
}

// Fetch all job applications with user info
$sql = "SELECT u.username, u.id as user_id, ja.status, j.job, j.company, ja.created_at
        FROM job_applications ja
        INNER JOIN users u ON ja.user_id = u.id
        INNER JOIN jobs j ON ja.job_id = j.id
        ORDER BY ja.created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Candidate Status - Admin</title>
    <style>
        body{background:#181818;color:#fff;font-family:'Segoe UI',Arial,sans-serif;text-align:center;padding-top:40px;}
        a{color:#4fc3f7;text-decoration:underline;}
        table{margin:0 auto;background:#232a34;border-radius:10px;box-shadow:0 2px 8px #0004;min-width:700px;}
        th,td{padding:12px 18px;}
        th{background:#4fc3f7;color:#222;}
        tr:nth-child(even){background:#222;}
        tr:nth-child(odd){background:#232a34;}
        .status-pending{color:#ffd700;font-weight:bold;}
        .status-accepted{color:#4caf50;font-weight:bold;}
        .status-rejected{color:#f44336;font-weight:bold;}
    </style>
</head>
<body>
    <h1>Candidate Status</h1>
    <table>
        <tr>
            <th>Username</th>
            <th>Job Title</th>
            <th>Company</th>
            <th>Status</th>
            <th>Applied At</th>
        </tr>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['job']); ?></td>
                    <td><?php echo htmlspecialchars($row['company']); ?></td>
                    <td class="status-<?php echo strtolower($row['status']); ?>">
                        <?php echo htmlspecialchars($row['status']); ?>
                    </td>
                    <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="5">No applications found.</td></tr>
        <?php endif; ?>
    </table>
    <br>
    <a href="admin.php">&larr; Back to Admin Dashboard</a>
</body>
</html> 