<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Only allow access if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Database connection
require_once 'config.php';
$conn = getDBConnection();

// Add search and filter functionality
$search_query = '';
$status_filter = '';
$date_filter = '';

// Add sorting functionality
$sort_by = isset($_GET['sort']) ? $conn->real_escape_string($_GET['sort']) : 'date';
$sort_order = isset($_GET['order']) ? $conn->real_escape_string($_GET['order']) : 'desc';

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $search_query = htmlspecialchars($_GET['search']);
}

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $status_filter = $conn->real_escape_string($_GET['status']);
}

if (isset($_GET['date']) && !empty($_GET['date'])) {
    $date_filter = $conn->real_escape_string($_GET['date']);
}

// Modify the applications query to include search, filters, and sorting
$applications_query = "SELECT 
    ja.id as application_id,
    ja.status as application_status,
    ja.created_at as applied_date,
    j.job,
    j.company,
    j.salary,
    j.requirements,
    int_status.status as interview_status,
    int_status.interview_date,
    int_status.interview_time,
    int_status.interview_type,
    int_status.location as interview_location,
    int_status.duration,
    int_status.interviewer,
    int_status.notes as interview_notes,
    int_feedback.technical_rating,
    int_feedback.communication_rating,
    int_feedback.experience_rating,
    int_feedback.overall_rating,
    int_feedback.strengths,
    int_feedback.weaknesses,
    int_feedback.feedback_notes
FROM job_applications ja
LEFT JOIN jobs j ON ja.job_id = j.id
LEFT JOIN interview_status int_status ON ja.id = int_status.application_id
LEFT JOIN interview_feedback int_feedback ON int_status.id = int_feedback.interview_id
WHERE ja.user_id = ?";

if (!empty($search_query)) {
    $applications_query .= " AND (j.job LIKE '%$search%' OR j.company LIKE '%$search%')";
}

if (!empty($status_filter)) {
    $applications_query .= " AND ja.status = '$status_filter'";
}

if (!empty($date_filter)) {
    switch ($date_filter) {
        case 'week':
            $applications_query .= " AND ja.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            break;
        case 'month':
            $applications_query .= " AND ja.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
        case 'year':
            $applications_query .= " AND ja.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
    }
}

// Add sorting
switch ($sort_by) {
    case 'company':
        $applications_query .= " ORDER BY j.company " . ($sort_order === 'asc' ? 'ASC' : 'DESC');
        break;
    case 'status':
        $applications_query .= " ORDER BY ja.status " . ($sort_order === 'asc' ? 'ASC' : 'DESC');
        break;
    case 'date':
    default:
        $applications_query .= " ORDER BY ja.created_at " . ($sort_order === 'asc' ? 'ASC' : 'DESC');
        break;
}

// Get status summary
$summary_query = "SELECT 
    status,
    COUNT(*) as count
FROM job_applications
WHERE user_id = ?
GROUP BY status";

$summary_stmt = $conn->prepare($summary_query);
$summary_stmt->bind_param("i", $user_id);
$summary_stmt->execute();
$summary_result = $summary_stmt->get_result();

$status_summary = [
    'Pending' => 0,
    'Accepted' => 0,
    'Rejected' => 0
];

while ($row = $summary_result->fetch_assoc()) {
    $status_summary[$row['status']] = $row['count'];
}

// Get user's applications with detailed status
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare($applications_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$applications = $stmt->get_result();

// Get application statistics
$stats_query = "SELECT 
    COUNT(*) as total_applications,
    COALESCE(AVG(CASE WHEN ja.status = 'Accepted' THEN 1 ELSE 0 END) * 100, 0) as acceptance_rate,
    COALESCE(AVG(CASE WHEN ja.status = 'Rejected' THEN 1 ELSE 0 END) * 100, 0) as rejection_rate,
    COALESCE(AVG(CASE WHEN ja.status = 'Pending' THEN 1 ELSE 0 END) * 100, 0) as pending_rate,
    COUNT(DISTINCT j.company) as unique_companies
FROM job_applications ja
LEFT JOIN jobs j ON ja.job_id = j.id
WHERE ja.user_id = ?";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Add export functionality
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="applications_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Job Title', 'Company', 'Status', 'Applied Date', 'Interview Status', 'Interview Date', 'Interview Type', 'Technical Rating', 'Communication Rating', 'Overall Rating']);
    
    $export_stmt = $conn->prepare($applications_query);
    $export_stmt->bind_param("i", $user_id);
    $export_stmt->execute();
    $export_result = $export_stmt->get_result();
    
    while ($row = $export_result->fetch_assoc()) {
        fputcsv($output, [
            $row['job'],
            $row['company'],
            $row['application_status'],
            date('Y-m-d', strtotime($row['applied_date'])),
            $row['interview_status'],
            $row['interview_date'] ? date('Y-m-d', strtotime($row['interview_date'])) : '',
            $row['interview_type'],
            $row['technical_rating'],
            $row['communication_rating'],
            $row['overall_rating']
        ]);
    }
    
    fclose($output);
    exit();
}

// Handle application notes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_note') {
    $application_id = $conn->real_escape_string($_POST['application_id']);
    $note = $conn->real_escape_string($_POST['note']);
    
    $note_query = "INSERT INTO application_notes (application_id, note, created_at) VALUES (?, ?, NOW())";
    $note_stmt = $conn->prepare($note_query);
    $note_stmt->bind_param("is", $application_id, $note);
    $note_stmt->execute();
    
    header('Location: ' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING']);
    exit();
}

// Get application notes
$notes_query = "SELECT an.*, ja.job_id 
                FROM application_notes an 
                JOIN job_applications ja ON an.application_id = ja.id 
                WHERE ja.user_id = ? 
                ORDER BY an.created_at DESC";
$notes_stmt = $conn->prepare($notes_query);
$notes_stmt->bind_param("i", $user_id);
$notes_stmt->execute();
$notes_result = $notes_stmt->get_result();

$application_notes = [];
while ($note = $notes_result->fetch_assoc()) {
    if (!isset($application_notes[$note['application_id']])) {
        $application_notes[$note['application_id']] = [];
    }
    $application_notes[$note['application_id']][] = $note;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Applications - JPOST</title>
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
        .navbar nav a:hover, .navbar nav a.active {
            color: #4fc3f7;
        }
        .container {
            max-width: 1200px;
            margin: 32px auto;
            padding: 0 16px;
        }
        .application-card {
            background: #232a34;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border: 1px solid #333;
        }
        .application-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        .job-title {
            font-size: 1.4em;
            color: #4fc3f7;
            margin: 0;
        }
        .company-name {
            color: #888;
            margin: 4px 0;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .status-pending { background: #ffd700; color: #000; }
        .status-accepted { background: #4caf50; color: #fff; }
        .status-rejected { background: #f44336; color: #fff; }
        .interview-section {
            background: #2a323d;
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
        }
        .interview-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }
        .interview-status {
            font-size: 1.1em;
            font-weight: bold;
        }
        .interview-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }
        .detail-item {
            background: #1a1f28;
            padding: 8px 12px;
            border-radius: 6px;
        }
        .detail-label {
            color: #888;
            font-size: 0.9em;
            margin-bottom: 4px;
        }
        .detail-value {
            color: #fff;
        }
        .feedback-section {
            background: #2a323d;
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
        }
        .rating {
            display: flex;
            gap: 4px;
            margin: 8px 0;
        }
        .star {
            color: #ffd700;
            font-size: 1.2em;
        }
        .empty-star {
            color: #444;
        }
        .footer {
            width: 100%;
            background: #181818;
            border-top: 2px solid #333;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 18px 0 10px 0;
            position: static;
            left: 0;
            bottom: 0;
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
            <a href="track_candidate.php" class="active">Track Applications</a>
        </nav>
        <div style="display:flex; align-items:center;">
            <a href="logout.php" style="color:#fff; text-decoration:none; margin-left:18px; background:#f44336; padding:8px 16px; border-radius:4px;">Logout</a>
        </div>
    </div>

    <div class="container">
        <h1 style="color: #4fc3f7; margin-bottom: 24px;">Track Your Applications</h1>
        
        <!-- Action Buttons -->
        <div style="display: flex; gap: 16px; margin-bottom: 24px;">
            <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" 
               style="background: #4caf50; color: #fff; padding: 12px 24px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                <span style="font-size: 1.2em;">📊</span> Export to CSV
            </a>
        </div>

        <!-- Application Statistics -->
        <div style="background: #232a34; padding: 24px; border-radius: 12px; margin-bottom: 24px;">
            <h2 style="color: #4fc3f7; margin-top: 0; margin-bottom: 16px;">Application Statistics</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <div style="text-align: center;">
                    <div style="font-size: 2em; font-weight: bold; color: #4fc3f7;"><?php echo $stats['total_applications']; ?></div>
                    <div style="color: #888;">Total Applications</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 2em; font-weight: bold; color: #4caf50;"><?php echo number_format((float)$stats['acceptance_rate'], 1); ?>%</div>
                    <div style="color: #888;">Acceptance Rate</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 2em; font-weight: bold; color: #f44336;"><?php echo number_format((float)$stats['rejection_rate'], 1); ?>%</div>
                    <div style="color: #888;">Rejection Rate</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 2em; font-weight: bold; color: #ffd700;"><?php echo $stats['unique_companies']; ?></div>
                    <div style="color: #888;">Companies Applied</div>
                </div>
            </div>
        </div>

        <!-- Status Summary -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
            <div style="background: #232a34; padding: 16px; border-radius: 8px; text-align: center;">
                <h3 style="color: #ffd700; margin: 0 0 8px 0;">Pending</h3>
                <div style="font-size: 2em; font-weight: bold;"><?php echo $status_summary['Pending']; ?></div>
            </div>
            <div style="background: #232a34; padding: 16px; border-radius: 8px; text-align: center;">
                <h3 style="color: #4caf50; margin: 0 0 8px 0;">Accepted</h3>
                <div style="font-size: 2em; font-weight: bold;"><?php echo $status_summary['Accepted']; ?></div>
            </div>
            <div style="background: #232a34; padding: 16px; border-radius: 8px; text-align: center;">
                <h3 style="color: #f44336; margin: 0 0 8px 0;">Rejected</h3>
                <div style="font-size: 2em; font-weight: bold;"><?php echo $status_summary['Rejected']; ?></div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div style="background: #232a34; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
            <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <div>
                    <input type="text" name="search" placeholder="Search jobs or companies..." value="<?php echo htmlspecialchars($search_query); ?>" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #444; background: #1a1f28; color: #fff;">
                </div>
                <div>
                    <select name="status" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #444; background: #1a1f28; color: #fff;">
                        <option value="">All Statuses</option>
                        <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Accepted" <?php echo $status_filter === 'Accepted' ? 'selected' : ''; ?>>Accepted</option>
                        <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div>
                    <select name="date" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #444; background: #1a1f28; color: #fff;">
                        <option value="">All Time</option>
                        <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>Last Week</option>
                        <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>Last Month</option>
                        <option value="year" <?php echo $date_filter === 'year' ? 'selected' : ''; ?>>Last Year</option>
                    </select>
                </div>
                <div>
                    <select name="sort" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #444; background: #1a1f28; color: #fff;">
                        <option value="date" <?php echo $sort_by === 'date' ? 'selected' : ''; ?>>Sort by Date</option>
                        <option value="company" <?php echo $sort_by === 'company' ? 'selected' : ''; ?>>Sort by Company</option>
                        <option value="status" <?php echo $sort_by === 'status' ? 'selected' : ''; ?>>Sort by Status</option>
                    </select>
                </div>
                <div>
                    <select name="order" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #444; background: #1a1f28; color: #fff;">
                        <option value="desc" <?php echo $sort_order === 'desc' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="asc" <?php echo $sort_order === 'asc' ? 'selected' : ''; ?>>Oldest First</option>
                    </select>
                </div>
                <div>
                    <button type="submit" style="width: 100%; padding: 8px; border-radius: 4px; border: none; background: #4fc3f7; color: #222; font-weight: bold; cursor: pointer;">Apply Filters</button>
                </div>
            </form>
        </div>

        <!-- Results Summary -->
        <div style="color: #888; margin-bottom: 16px;">
            <?php if (!empty($search_query) || !empty($status_filter) || !empty($date_filter)): ?>
                Showing results for:
                <?php if (!empty($search_query)): ?>
                    <span style="color: #4fc3f7;">"<?php echo htmlspecialchars($search_query); ?>"</span>
                <?php endif; ?>
                <?php if (!empty($status_filter)): ?>
                    <span style="color: #4fc3f7;">Status: <?php echo htmlspecialchars($status_filter); ?></span>
                <?php endif; ?>
                <?php if (!empty($date_filter)): ?>
                    <span style="color: #4fc3f7;">Time: <?php echo htmlspecialchars($date_filter); ?></span>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($applications->num_rows > 0): ?>
            <?php 
            $current_date = null;
            while($app = $applications->fetch_assoc()): 
                $app_date = date('F j, Y', strtotime($app['applied_date']));
                if ($current_date !== $app_date):
                    if ($current_date !== null) echo '</div>'; // Close previous date group
                    $current_date = $app_date;
            ?>
                <div style="margin: 24px 0 16px 0;">
                    <h3 style="color: #4fc3f7; margin: 0;"><?php echo $app_date; ?></h3>
                    <div style="border-left: 2px solid #333; margin-left: 8px; padding-left: 16px;">
            <?php endif; ?>
                <div class="application-card">
                    <div class="application-header">
                        <div>
                            <h2 class="job-title"><?php echo htmlspecialchars($app['job']); ?></h2>
                            <div class="company-name"><?php echo htmlspecialchars($app['company']); ?></div>
                        </div>
                        <div class="status-badge status-<?php echo strtolower($app['application_status']); ?>">
                            <?php echo htmlspecialchars($app['application_status']); ?>
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Applied On</div>
                        <div class="detail-value"><?php echo date('F j, Y', strtotime($app['applied_date'])); ?></div>
                    </div>

                    <!-- Application Notes -->
                    <div style="margin-top: 16px;">
                        <h4 style="color: #4fc3f7; margin: 0 0 8px 0;">Notes</h4>
                        <?php if (isset($application_notes[$app['application_id']])): ?>
                            <div style="margin-bottom: 12px;">
                                <?php foreach ($application_notes[$app['application_id']] as $note): ?>
                                    <div style="background: #1a1f28; padding: 12px; border-radius: 8px; margin-bottom: 8px;">
                                        <div style="color: #888; font-size: 0.9em; margin-bottom: 4px;">
                                            <?php echo date('F j, Y g:i A', strtotime($note['created_at'])); ?>
                                        </div>
                                        <div><?php echo nl2br(htmlspecialchars($note['note'])); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" style="display: flex; gap: 8px;">
                            <input type="hidden" name="action" value="add_note">
                            <input type="hidden" name="application_id" value="<?php echo $app['application_id']; ?>">
                            <input type="text" name="note" placeholder="Add a note..." required
                                   style="flex: 1; padding: 8px; border-radius: 4px; border: 1px solid #444; background: #1a1f28; color: #fff;">
                            <button type="submit" 
                                    style="padding: 8px 16px; border-radius: 4px; border: none; background: #4fc3f7; color: #222; font-weight: bold; cursor: pointer;">
                                Add Note
                            </button>
                        </form>
                    </div>

                    <!-- Progress Timeline -->
                    <div style="margin-top: 16px;">
                        <h4 style="color: #4fc3f7; margin: 0 0 8px 0;">Application Progress</h4>
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <div style="width: 24px; height: 24px; border-radius: 50%; background: #4caf50; display: flex; align-items: center; justify-content: center; color: #fff;">✓</div>
                            <div>Application Submitted</div>
                        </div>
                        <?php if ($app['interview_status']): ?>
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                <div style="width: 24px; height: 24px; border-radius: 50%; background: #4caf50; display: flex; align-items: center; justify-content: center; color: #fff;">✓</div>
                                <div>Interview Scheduled</div>
                            </div>
                        <?php endif; ?>
                        <?php if ($app['technical_rating']): ?>
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                <div style="width: 24px; height: 24px; border-radius: 50%; background: #4caf50; display: flex; align-items: center; justify-content: center; color: #fff;">✓</div>
                                <div>Interview Completed</div>
                            </div>
                        <?php endif; ?>
                        <?php if ($app['application_status'] === 'Accepted'): ?>
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                <div style="width: 24px; height: 24px; border-radius: 50%; background: #4caf50; display: flex; align-items: center; justify-content: center; color: #fff;">✓</div>
                                <div>Application Accepted</div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($app['interview_status']): ?>
                        <div class="interview-section">
                            <div class="interview-header">
                                <span class="interview-status">Interview Status: <?php echo htmlspecialchars($app['interview_status']); ?></span>
                            </div>
                            
                            <?php if ($app['interview_status'] === 'Scheduled' || $app['interview_status'] === 'Rescheduled'): ?>
                                <div class="interview-details">
                                    <?php if ($app['interview_date']): ?>
                                        <div class="detail-item">
                                            <div class="detail-label">Date</div>
                                            <div class="detail-value"><?php echo date('F j, Y', strtotime($app['interview_date'])); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($app['interview_time']): ?>
                                        <div class="detail-item">
                                            <div class="detail-label">Time</div>
                                            <div class="detail-value"><?php echo date('g:i A', strtotime($app['interview_time'])); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($app['interview_type']): ?>
                                        <div class="detail-item">
                                            <div class="detail-label">Type</div>
                                            <div class="detail-value"><?php echo htmlspecialchars($app['interview_type']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($app['interview_location']): ?>
                                        <div class="detail-item">
                                            <div class="detail-label">Location/Link</div>
                                            <div class="detail-value"><?php echo htmlspecialchars($app['interview_location']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($app['duration']): ?>
                                        <div class="detail-item">
                                            <div class="detail-label">Duration</div>
                                            <div class="detail-value"><?php echo htmlspecialchars($app['duration']); ?> minutes</div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($app['interviewer']): ?>
                                        <div class="detail-item">
                                            <div class="detail-label">Interviewer</div>
                                            <div class="detail-value"><?php echo htmlspecialchars($app['interviewer']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($app['interview_notes']): ?>
                                    <div class="detail-item" style="margin-top: 12px;">
                                        <div class="detail-label">Notes</div>
                                        <div class="detail-value"><?php echo nl2br(htmlspecialchars($app['interview_notes'])); ?></div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($app['technical_rating']): ?>
                        <div class="feedback-section">
                            <h3 style="color: #4fc3f7; margin-top: 0;">Interview Feedback</h3>
                            
                            <div class="detail-item">
                                <div class="detail-label">Technical Skills</div>
                                <div class="rating">
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <span class="star <?php echo $i <= $app['technical_rating'] ? 'filled' : 'empty-star'; ?>">★</span>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">Communication Skills</div>
                                <div class="rating">
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <span class="star <?php echo $i <= $app['communication_rating'] ? 'filled' : 'empty-star'; ?>">★</span>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">Experience Level</div>
                                <div class="rating">
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <span class="star <?php echo $i <= $app['experience_rating'] ? 'filled' : 'empty-star'; ?>">★</span>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">Overall Rating</div>
                                <div class="rating">
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <span class="star <?php echo $i <= $app['overall_rating'] ? 'filled' : 'empty-star'; ?>">★</span>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <?php if ($app['strengths']): ?>
                                <div class="detail-item">
                                    <div class="detail-label">Strengths</div>
                                    <div class="detail-value"><?php echo nl2br(htmlspecialchars($app['strengths'])); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($app['weaknesses']): ?>
                                <div class="detail-item">
                                    <div class="detail-label">Areas for Improvement</div>
                                    <div class="detail-value"><?php echo nl2br(htmlspecialchars($app['weaknesses'])); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($app['feedback_notes']): ?>
                                <div class="detail-item">
                                    <div class="detail-label">Additional Notes</div>
                                    <div class="detail-value"><?php echo nl2br(htmlspecialchars($app['feedback_notes'])); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php 
            endwhile;
            if ($current_date !== null) echo '</div></div>'; // Close last date group
            ?>
        <?php else: ?>
            <div style="text-align: center; padding: 48px; background: #232a34; border-radius: 12px;">
                <h2 style="color: #4fc3f7; margin-bottom: 16px;">No Applications Yet</h2>
                <p style="color: #888;">You haven't applied to any jobs yet. Start exploring opportunities!</p>
                <a href="explore.php" style="display: inline-block; background: #4fc3f7; color: #222; padding: 12px 24px; border-radius: 8px; text-decoration: none; margin-top: 16px; font-weight: bold;">Browse Jobs</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <a href="#">Security & Privacy</a>
        <a href="#">Terms and Condition</a>
        <a href="#">About</a>
        <a href="#">Report</a>
    </div>
</body>
</html> 