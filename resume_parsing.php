<?php
session_start();
require_once 'config.php';
$conn = getDBConnection();

// Only admin can access
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: index.php?error=unauthorized');
    exit();
}

// Fetch all jobseekers with resumes
$sql = "SELECT u.id, u.username, up.resume_file
        FROM users u
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE up.resume_file IS NOT NULL AND up.resume_file != ''";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Resume Parsing - Admin</title>
    <style>
        body { background: #181818; color: #fff; font-family: 'Segoe UI', Arial, sans-serif; text-align: center; padding-top: 40px; }
        table { margin: 0 auto; background: #232a34; border-radius: 10px; box-shadow: 0 2px 8px #0004; min-width: 600px; }
        th, td { padding: 12px 18px; }
        th { background: #4fc3f7; color: #222; }
        tr:nth-child(even) { background: #222; }
        tr:nth-child(odd) { background: #232a34; }
        a { color: #4fc3f7; text-decoration: underline; }
    </style>
</head>
<body>
    <h1>Resume Parsing (All Jobseekers)</h1>
    <table>
        <tr>
            <th>Username</th>
            <th>Resume</th>
        </tr>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td>
                        <?php if (!empty($row['resume_file'])): ?>
                            <a href="<?php echo htmlspecialchars($row['resume_file']); ?>" target="_blank">View Resume</a>
                        <?php else: ?>
                            <span style="color:#aaa;">No Resume</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="2">No jobseekers with resumes found.</td></tr>
        <?php endif; ?>
    </table>
    <br>
    <a href="admin.php">&larr; Back to Admin Dashboard</a>
</body>
</html>