<?php
session_start();
require_once 'config.php';
$conn = getDBConnection();

// Authentication: Only allow admin or hr
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['user_type']), ['admin', 'hr'])) {
    header('Location: login.php?error=not_logged_in');
    exit();
}

// Fetch jobseekers
$sql = "SELECT u.id, u.username, u.created_at, up.full_name, up.email FROM users u LEFT JOIN user_profiles up ON u.id = up.user_id WHERE LOWER(u.user_type) = 'jobseeker' ORDER BY u.created_at DESC";
$result = $conn->query($sql);
$jobseekers = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $jobseekers[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate List - JPOST</title>
    <style>
        body {
            background: linear-gradient(135deg, #181818 60%, #232a34 100%);
            color: #fff;
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            margin: 40px auto;
            background: #232a34;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 32px;
        }
        h1 {
            color: #4fc3f7;
            margin-bottom: 24px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            color: #222;
            border-radius: 8px;
            overflow: hidden;
        }
        th, td {
            padding: 14px 12px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }
        th {
            background: #f7f7f7;
            color: #222;
        }
        tr:last-child td {
            border-bottom: none;
        }
        tr:hover {
            background: #f1f8fb;
        }
        .back-btn {
            background: #666;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 20px;
        }
        .back-btn:hover {
            background: #777;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="hr.php" class="back-btn">← Back to HR Dashboard</a>
        <h1>Candidate List (Jobseekers)</h1>
        <table>
            <tr>
                <th>Username</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Registered At</th>
            </tr>
            <?php if (empty($jobseekers)): ?>
                <tr><td colspan="4" style="text-align:center;">No jobseekers found.</td></tr>
            <?php else: ?>
                <?php foreach ($jobseekers as $js): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($js['username']); ?></td>
                        <td><?php echo htmlspecialchars($js['full_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($js['email'] ?? ''); ?></td>
                        <td><?php echo date('M d, Y', strtotime($js['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </div>
</body>
</html> 