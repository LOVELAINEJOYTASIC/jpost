<?php
session_start();
require_once 'config.php';
$conn = getDBConnection();

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['user_type']) !== 'admin') {
    header('Location: login.php');
    exit();
}

// --- Handle export to CSV ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="candidates.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['#', 'Name', 'Job Position', 'Status', 'Applied At']);
    $sql = "SELECT a.id, a.name, a.status, up.full_name, a.created_at, j.job AS job_position
            FROM applicants a
            LEFT JOIN user_profiles up ON a.user_id = up.user_id
            LEFT JOIN jobs j ON a.job_id = j.id
            ORDER BY a.id DESC";
    $result = $conn->query($sql);
    $i = 1;
    while ($row = $result->fetch_assoc()) {
        $displayName = $row['full_name'] ?: $row['name'];
        fputcsv($out, [
            $i++,
            $displayName,
            $row['job_position'],
            ucfirst($row['status']),
            $row['created_at']
        ]);
    }
    fclose($out);
    exit();
}

// Handle Edit Candidate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_candidate'])) {
    $id = (int)$_POST['edit_id'];
    $name = $conn->real_escape_string($_POST['edit_name']);
    $status = $conn->real_escape_string($_POST['edit_status']);
    $job_id = (int)$_POST['edit_job_id'];
    $conn->query("UPDATE applicants SET name='$name', status='$status', job_id=$job_id WHERE id=$id");
    header("Location: track_candidate.php");
    exit();
}

// Handle Delete Candidate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_candidate'])) {
    $id = (int)$_POST['delete_id'];
    $conn->query("DELETE FROM applicants WHERE id=$id");
    header("Location: track_candidate.php");
    exit();
}

// Fetch jobs for edit dropdown
$jobs_list = [];
$jobs_res = $conn->query("SELECT id, job FROM jobs ORDER BY job");
while ($row = $jobs_res->fetch_assoc()) {
    $jobs_list[$row['id']] = $row['job'];
}

// --- Handle search and status filter ---
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$where = [];
if ($search) $where[] = "(a.name LIKE '%$search%' OR up.full_name LIKE '%$search%')";
if ($status_filter) $where[] = "a.status = '$status_filter'";
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT a.id, a.name, a.status, up.full_name, a.created_at, j.job AS job_position, a.job_id
        FROM applicants a
        LEFT JOIN user_profiles up ON a.user_id = up.user_id
        LEFT JOIN jobs j ON a.job_id = j.id
        $where_sql
        ORDER BY a.id DESC";
$result = $conn->query($sql);

// Analytics: Count total and by status (manual for now)
$analytics = [
    'total' => 17,
    'applied' => 17,
    'interview' => 5,
    'hired' => 9,
    'rejected' => 6
];
$analytics['total'] = $analytics['applied'] + $analytics['interview'] + $analytics['hired'] + $analytics['rejected'];

// Function to highlight search term
function highlight($text, $search) {
    if (!$search) return htmlspecialchars($text);
    return preg_replace('/(' . preg_quote($search, '/') . ')/i', '<mark>$1</mark>', htmlspecialchars($text));
}

// Status options for filter
$status_options = [
    '' => 'All',
    'applied' => 'Applied',
    'interview' => 'Interview',
    'hired' => 'Hired',
    'rejected' => 'Rejected'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Track Candidate - JPOST</title>
    <style>
        body { background: #181818; color: #fff; font-family: 'Segoe UI', Arial, sans-serif; }
        .container { max-width: 1000px; margin: 30px auto; }
        table { width: 100%; background: #232a34; border-radius: 10px; border-collapse: collapse; }
        th, td { padding: 12px 18px; text-align: left; }
        th { background: #4fc3f7; color: #222; }
        tr:nth-child(even) { background: #222; }
        tr:nth-child(odd) { background: #232a34; }
        .status { font-weight: bold; padding: 4px 12px; border-radius: 12px; display: inline-block; }
        .status-hired { background: #4caf50; color: #fff; }
        .status-interview { background: #ff9800; color: #fff; }
        .status-applied { background: #2196f3; color: #fff; }
        .status-rejected { background: #f44336; color: #fff; }
        .search-box { margin-bottom: 18px; }
        .action-btn { padding: 4px 10px; border-radius: 6px; border: none; background: #4fc3f7; color: #222; cursor: pointer; }
        .action-btn:hover { background: #0288d1; color: #fff; }
        mark { background: #ffe082; color: #222; padding: 0 2px; border-radius: 2px; }
        @media (max-width: 700px) {
            table, th, td { font-size: 13px; }
            .container { padding: 0 4px; }
            th, td { padding: 8px 6px; }
        }
        .back-link { color:#4fc3f7; display:inline-block; margin-top:20px; text-decoration:none; }
        .back-link:hover { text-decoration:underline; }
        .export-link { float:right; margin-bottom:10px; color:#4fc3f7; text-decoration:none; }
        .export-link:hover { text-decoration:underline; }
    </style>
</head>
<body>
<div class="container">
    <h1>Track Candidate</h1>
    <p>This page shows candidate progress and status throughout the hiring process.</p>
    <a href="?export=csv" class="export-link">Export to CSV</a>
    <form method="get" class="search-box">
        <input type="text" name="search" placeholder="Search by name..." value="<?php echo htmlspecialchars($search); ?>" style="padding:6px 12px; border-radius:6px; border:none;">
        <select name="status" style="padding:6px 12px; border-radius:6px; border:none;">
            <?php foreach ($status_options as $val => $label): ?>
                <option value="<?php echo $val; ?>" <?php if ($status_filter === $val) echo 'selected'; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" style="padding:6px 16px; border-radius:6px; background:#4fc3f7; color:#222; border:none;">Search</button>
        <?php if ($search || $status_filter): ?>
            <a href="track_candidate.php" style="margin-left:10px; color:#f44336;">Clear</a>
        <?php endif; ?>
    </form>
    <div class="analytics" style="margin-bottom:24px; display:flex; gap:24px; flex-wrap:wrap;">
        <div style="background:#232a34; padding:16px 24px; border-radius:10px;">
            <strong>Total Candidates:</strong> <?php echo $analytics['total']; ?>
        </div>
        <div style="background:#2196f3; padding:16px 24px; border-radius:10px;">
            <strong>Applied:</strong> <?php echo $analytics['applied']; ?>
        </div>
        <div style="background:#ff9800; padding:16px 24px; border-radius:10px;">
            <strong>Interview:</strong> <?php echo $analytics['interview']; ?>
        </div>
        <div style="background:#4caf50; padding:16px 24px; border-radius:10px;">
            <strong>Hired:</strong> <?php echo $analytics['hired']; ?>
        </div>
        <div style="background:#f44336; padding:16px 24px; border-radius:10px;">
            <strong>Rejected:</strong> <?php echo $analytics['rejected']; ?>
        </div>
    </div>
    <table>
        <tr>
            <th>#</th>
            <th>Name</th>
            <th>Job Position</th>
            <th>Status</th>
            <th>Applied At</th>
            <th>Action</th>
        </tr>
        <?php if ($result && $result->num_rows > 0): $i = 1; ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td>
                        <?php
                            $displayName = $row['full_name'] ?: $row['name'];
                            echo highlight($displayName, $search);
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($row['job_position']); ?></td>
                    <td>
                        <span class="status status-<?php echo htmlspecialchars($row['status']); ?>">
                            <?php echo ucfirst($row['status']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                    <td>
                        <a href="view_candidate.php?id=<?php echo $row['id']; ?>" class="action-btn">View</a>
                        <button type="button"
                            class="action-btn"
                            style="background:#ffeb3b; color:#222;"
                            onclick="openEditModal(
                                <?php echo $row['id']; ?>,
                                '<?php echo htmlspecialchars(addslashes($row['full_name'] ?: $row['name'])); ?>',
                                '<?php echo htmlspecialchars($row['status']); ?>',
                                '<?php echo htmlspecialchars($row['job_position']); ?>',
                                '<?php echo (int)$row['job_id']; ?>'
                            )"
                        >Edit</button>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this candidate?');">
                            <input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>">
                            <button type="submit" name="delete_candidate" class="action-btn" style="background:#f44336; color:#fff;">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6">No candidates found.</td></tr>
        <?php endif; ?>
    </table>
    <a href="admin.php" class="back-link">&larr; Back to Admin Dashboard</a>
</div>

<!-- Edit Candidate Modal -->
<div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.7); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:#232a34; padding:32px; border-radius:12px; max-width:400px; margin:auto; position:relative;">
        <span style="position:absolute; top:12px; right:18px; font-size:1.5em; color:#888; cursor:pointer;" onclick="closeEditModal()">&times;</span>
        <h2 style="color:#4fc3f7; margin-top:0;">Edit Candidate</h2>
        <form method="POST">
            <input type="hidden" name="edit_id" id="edit_id">
            <div class="form-group">
                <label for="edit_name">Name</label>
                <input type="text" id="edit_name" name="edit_name" required>
            </div>
            <div class="form-group">
                <label for="edit_status">Status</label>
                <select id="edit_status" name="edit_status" required>
                    <option value="applied">Applied</option>
                    <option value="interview">Interview</option>
                    <option value="hired">Hired</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div class="form-group">
                <label for="edit_job_id">Job Position</label>
                <select id="edit_job_id" name="edit_job_id" required>
                    <?php foreach ($jobs_list as $jid => $jname): ?>
                        <option value="<?php echo $jid; ?>"><?php echo htmlspecialchars($jname); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="edit_candidate" class="action-btn" style="background:#4fc3f7; color:#222;">Save Changes</button>
        </form>
    </div>
</div>

<script>
function openEditModal(id, name, status, job_position, job_id) {
    document.getElementById('editModal').style.display = 'flex';
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_status').value = status;
    document.getElementById('edit_job_id').value = job_id;
}
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
window.onclick = function(event) {
    var modal = document.getElementById('editModal');
    if (event.target == modal) {
        closeEditModal();
    }
}

<div class="footer">
        <a href="#">Security & Privacy</a>
        <a href="#">Terms and Condition</a>
        <a href="#">About</a>
        <a href="#">Report</a>
    </div>
</script>
</body>
</html>
