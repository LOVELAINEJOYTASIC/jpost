<?php
session_start();
require_once 'config.php';
$conn = getDBConnection();

// Check if user is admin
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['user_type']) !== 'admin') {
    header('Location: login.php');
    exit();
}

// Auto-create workflow_stages table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS workflow_stages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stage_name VARCHAR(255) NOT NULL,
    stage_description TEXT,
    stage_order INT NOT NULL
)");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_stage'])) {
        $stage_name = trim($_POST['stage_name']);
        $stage_description = trim($_POST['stage_description']);
        $stage_order = (int)$_POST['stage_order'];
        $sql = "INSERT INTO workflow_stages (stage_name, stage_description, stage_order) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $stage_name, $stage_description, $stage_order);
        $stmt->execute();
    }
    if (isset($_POST['edit_stage'])) {
        $stage_id = (int)$_POST['stage_id'];
        $stage_name = trim($_POST['stage_name']);
        $stage_description = trim($_POST['stage_description']);
        $stage_order = (int)$_POST['stage_order'];
        $sql = "UPDATE workflow_stages SET stage_name = ?, stage_description = ?, stage_order = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssii", $stage_name, $stage_description, $stage_order, $stage_id);
        $stmt->execute();
    }
    if (isset($_POST['delete_stage'])) {
        $stage_id = (int)$_POST['stage_id'];
        $sql = "DELETE FROM workflow_stages WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $stage_id);
        $stmt->execute();
    }
    if (isset($_POST['reorder_stages'])) {
        $orders = json_decode($_POST['orders'], true);
        foreach ($orders as $order) {
            $sql = "UPDATE workflow_stages SET stage_order = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $order['order'], $order['id']);
            $stmt->execute();
        }
    }
    header('Location: workflow_management.php');
    exit();
}

// Fetch all jobs for dropdown
$jobs_result = $conn->query("SELECT id, job, company FROM jobs ORDER BY created_at DESC");
$jobs = $jobs_result ? $jobs_result->fetch_all(MYSQLI_ASSOC) : [];

// Get selected job
$selected_job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

// Fetch workflow stages for the selected job (or all if none selected)
if ($selected_job_id) {
    // For now, show global stages (future: allow per-job workflows)
    $sql = "SELECT * FROM workflow_stages ORDER BY stage_order ASC";
} else {
$sql = "SELECT * FROM workflow_stages ORDER BY stage_order ASC";
}
$result = $conn->query($sql);
$stages = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Fetch applicant count per stage for the selected job
$applicants_per_stage = [];
if ($selected_job_id) {
    $stage_names = array_map(function($s) { return $s['stage_name']; }, $stages);
    foreach ($stage_names as $stage_name) {
        $count_sql = "SELECT COUNT(*) as count FROM applicants WHERE job_id = $selected_job_id AND status1 = '" . $conn->real_escape_string($stage_name) . "'";
        $count_result = $conn->query($count_sql);
        $row = $count_result ? $count_result->fetch_assoc() : ['count' => 0];
        $applicants_per_stage[$stage_name] = $row['count'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workflow Management - JPOST</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
        }
        h1 {
            color: #4fc3f7;
            text-align: center;
            margin-top: 32px;
            margin-bottom: 32px;
        }
        .workflow-table {
            width: 100%;
            background: #232a34;
            border-radius: 10px;
            box-shadow: 0 2px 8px #0004;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 32px;
        }
        .workflow-table th, .workflow-table td {
            padding: 12px 18px;
            text-align: left;
            border-bottom: 1px solid #333;
        }
        .workflow-table th {
            background: #4fc3f7;
            font-weight: 600;
            color: #222;
        }
        .workflow-table tr:nth-child(even) {
            background: #222;
        }
        .workflow-table tr:nth-child(odd) {
            background: #232a34;
        }
        .workflow-table tr:hover {
            background: #2c3440;
        }
        .actions {
            display: flex;
            gap: 8px;
        }
        .btn {
            background: #4fc3f7;
            color: #181818;
            border: none;
            border-radius: 6px;
            padding: 8px 18px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
        }
        .btn:hover {
            background: #0288d1;
            color: #fff;
        }
        .delete-btn {
            background: #f44336;
            color: #fff;
        }
        .delete-btn:hover {
            background: #d32f2f;
        }
        .edit-btn {
            background: #ff9800;
            color: #fff;
        }
        .edit-btn:hover {
            background: #fbc02d;
        }
        .updown-btn {
            background: #666;
            color: #fff;
            padding: 4px 10px;
            font-size: 1em;
        }
        .updown-btn:hover {
            background: #888;
        }
        .form-section {
            background: #232a34;
            border-radius: 10px;
            box-shadow: 0 2px 8px #0004;
            padding: 24px;
            margin-bottom: 32px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            color: #4fc3f7;
            display: block;
            margin-bottom: 8px;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #444;
            border-radius: 6px;
            background: #181818;
            color: #fff;
            font-size: 1em;
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
            .container {
                padding: 0 4px;
            }
            .workflow-table th, .workflow-table td {
                padding: 8px 6px;
                font-size: 0.95em;
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
            <a href="admin.php">Menu</a>
            <a href="index.php">Home</a>
            <a href="explore.php">Explore</a>
            <a href="account.php">Account</a>
        </nav>
        <div style="display:flex; align-items:center; gap: 16px;">
            <a href="logout.php" style="color:#fff; text-decoration:none; background:#f44336; padding:8px 16px; border-radius:4px;">Logout</a>
        </div>
    </div>
    <div class="container">
        <a href="admin.php" style="display:inline-block; margin-bottom:24px; background:#4fc3f7; color:#181818; padding:10px 28px; border-radius:8px; text-decoration:none; font-weight:600; font-size:1.1em; box-shadow:0 2px 8px rgba(0,0,0,0.10); transition:background 0.2s, color 0.2s;">&larr; Back to Admin Dashboard</a>
    <h1>Workflow Management</h1>
        <form method="GET" style="margin-bottom:24px; text-align:center;">
            <label for="job_id" style="color:#4fc3f7; font-weight:600; margin-right:8px;">Select Job:</label>
            <select name="job_id" id="job_id" onchange="this.form.submit()" style="padding:8px 16px; border-radius:6px; font-size:1em;">
                <option value="0">-- All Jobs --</option>
                <?php foreach ($jobs as $job): ?>
                    <option value="<?php echo $job['id']; ?>" <?php if ($selected_job_id == $job['id']) echo 'selected'; ?>><?php echo htmlspecialchars($job['job'] . ' @ ' . $job['company']); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php if ($selected_job_id): ?>
            <div style="background:#232a34; border-radius:10px; padding:18px; margin-bottom:24px;">
                <h2 style="color:#4fc3f7; margin-top:0;">Applicants per Stage</h2>
                <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px;">
                    <?php foreach ($stages as $stage): ?>
                        <div style="background:#1a1f28; border-radius:8px; padding:12px; text-align:center;">
                            <div style="color:#4fc3f7; font-weight:600; margin-bottom:6px;"><?php echo htmlspecialchars($stage['stage_name']); ?></div>
                            <div style="font-size:2em; font-weight:bold; color:#fff;"> <?php echo $applicants_per_stage[$stage['stage_name']] ?? 0; ?> </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        <div class="form-section">
            <h2 style="color:#4fc3f7;">Add New Stage</h2>
            <form method="POST" style="margin-top:18px;">
                <div class="form-group">
                    <label for="stage_name">Stage Name</label>
                    <input type="text" id="stage_name" name="stage_name" required>
                </div>
                <div class="form-group">
                    <label for="stage_description">Description</label>
                    <textarea id="stage_description" name="stage_description" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label for="stage_order">Order</label>
                    <input type="number" id="stage_order" name="stage_order" min="1" value="<?php echo count($stages) + 1; ?>" required>
                </div>
                <button type="submit" name="add_stage" class="btn">Add Stage</button>
            </form>
        </div>
        <table class="workflow-table">
            <tr>
                <th>Order</th>
                <th>Stage Name</th>
                <th>Description</th>
                <th>Actions</th>
            </tr>
            <?php if (empty($stages)): ?>
                <tr><td colspan="4" style="text-align:center; color:#888;">No workflow stages found.</td></tr>
            <?php else: ?>
                <?php foreach ($stages as $stage): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($stage['stage_order']); ?></td>
                        <td><?php echo htmlspecialchars($stage['stage_name']); ?></td>
                        <td><?php echo htmlspecialchars($stage['stage_description']); ?></td>
                        <td class="actions">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="stage_id" value="<?php echo $stage['id']; ?>">
                                <button type="submit" name="delete_stage" class="btn delete-btn" onclick="return confirm('Delete this stage?');">Delete</button>
                            </form>
                            <button class="btn edit-btn" onclick="openEditModal(<?php echo $stage['id']; ?>, '<?php echo htmlspecialchars(addslashes($stage['stage_name'])); ?>', '<?php echo htmlspecialchars(addslashes($stage['stage_description'])); ?>', <?php echo $stage['stage_order']; ?>)">Edit</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
        <!-- Edit Modal -->
        <div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.7); z-index:1000; align-items:center; justify-content:center;">
            <div style="background:#232a34; padding:32px; border-radius:12px; max-width:400px; margin:auto; position:relative;">
                <span style="position:absolute; top:12px; right:18px; font-size:1.5em; color:#888; cursor:pointer;" onclick="closeEditModal()">&times;</span>
                <h2 style="color:#4fc3f7; margin-top:0;">Edit Stage</h2>
                <form method="POST" id="editForm">
                    <input type="hidden" name="stage_id" id="edit_stage_id">
                    <div class="form-group">
                        <label for="edit_stage_name">Stage Name</label>
                        <input type="text" id="edit_stage_name" name="stage_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_stage_description">Description</label>
                        <textarea id="edit_stage_description" name="stage_description" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="edit_stage_order">Order</label>
                        <input type="number" id="edit_stage_order" name="stage_order" min="1" required>
                    </div>
                    <button type="submit" name="edit_stage" class="btn">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
    <div class="footer">
        <a href="#">Security & Privacy</a>
        <a href="#">Terms and Condition</a>
        <a href="#">About</a>
        <a href="#">Report</a>
    </div>
    <script>
        function openEditModal(id, name, desc, order) {
            document.getElementById('editModal').style.display = 'flex';
            document.getElementById('edit_stage_id').value = id;
            document.getElementById('edit_stage_name').value = name;
            document.getElementById('edit_stage_description').value = desc;
            document.getElementById('edit_stage_order').value = order;
        }
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html> 