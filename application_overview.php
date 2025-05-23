<?php
session_start();
require_once 'config.php';
$conn = getDBConnection();

// Authentication: Only allow admin
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['user_type']) !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get total applications count
$total_apps_sql = "SELECT COUNT(*) as total FROM applicants";
$total_apps_result = $conn->query($total_apps_sql);
$total_applications = $total_apps_result->fetch_assoc()['total'];

// Get applications by status
$status_sql = "SELECT 
    status1,
    COUNT(*) as count,
    (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM applicants)) as percentage
FROM applicants 
GROUP BY status1";
$status_result = $conn->query($status_sql);
$status_stats = [];
if ($status_result && $status_result->num_rows > 0) {
    while ($row = $status_result->fetch_assoc()) {
        $status_stats[$row['status1']] = [
            'count' => $row['count'],
            'percentage' => round($row['percentage'], 1)
        ];
    }
}

// Get applications by job type
$job_type_sql = "SELECT 
    j.job_type,
    COUNT(a.id) as count,
    (COUNT(a.id) * 100.0 / (SELECT COUNT(*) FROM applicants)) as percentage
FROM applicants a
JOIN jobs j ON a.job_id = j.id
GROUP BY j.job_type";
$job_type_result = $conn->query($job_type_sql);
$job_type_stats = [];
if ($job_type_result && $job_type_result->num_rows > 0) {
    while ($row = $job_type_result->fetch_assoc()) {
        $job_type_stats[$row['job_type']] = [
            'count' => $row['count'],
            'percentage' => round($row['percentage'], 1)
        ];
    }
}

// Get recent applications
$recent_apps_sql = "SELECT 
    a.*, 
    j.job,
    j.job_type,
    up.full_name,
    up.contact
FROM applicants a
JOIN jobs j ON a.job_id = j.id
LEFT JOIN user_profiles up ON a.user_id = up.user_id
ORDER BY a.created_at DESC
LIMIT 10";
$recent_apps_result = $conn->query($recent_apps_sql);
$recent_applications = [];
if ($recent_apps_result && $recent_apps_result->num_rows > 0) {
    while ($row = $recent_apps_result->fetch_assoc()) {
        $recent_applications[] = $row;
    }
}

// Get monthly trends
$monthly_trends_sql = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as count
FROM applicants 
GROUP BY DATE_FORMAT(created_at, '%Y-%m')
ORDER BY month DESC
LIMIT 12";
$monthly_trends_result = $conn->query($monthly_trends_sql);
$monthly_trends = [];
if ($monthly_trends_result && $monthly_trends_result->num_rows > 0) {
    while ($row = $monthly_trends_result->fetch_assoc()) {
        $monthly_trends[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Overview - JPOST</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .navbar nav a:hover {
            color: #4fc3f7;
        }
        .main-content {
            padding: 40px;
            max-width: 1200px;
            margin: 0 auto;
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
        .overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 24px;
            margin-top: 24px;
        }
        .overview-card {
            background: #232a34;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .overview-card h2 {
            color: #4fc3f7;
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.4em;
        }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 20px;
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
            font-size: 1.8em;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .stat-percentage {
            color: #4caf50;
            font-size: 1.1em;
        }
        .recent-apps {
            margin-top: 24px;
        }
        .recent-apps table {
            width: 100%;
            border-collapse: collapse;
            background: #1a1f28;
            border-radius: 8px;
            overflow: hidden;
        }
        .recent-apps th, .recent-apps td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #333;
        }
        .recent-apps th {
            background: #232a34;
            color: #4fc3f7;
        }
        .recent-apps tr:last-child td {
            border-bottom: none;
        }
        .recent-apps tr:hover {
            background: #2a323d;
        }
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: 500;
        }
        .badge.in-review { background: #2196f3; color: #fff; }
        .badge.in-process { background: #4caf50; color: #fff; }
        .badge.interview { background: #ff9800; color: #fff; }
        .badge.accepted { background: #4caf50; color: #fff; }
        .badge.cancelled { background: #f44336; color: #fff; }
        @media (max-width: 768px) {
            .overview-grid {
                grid-template-columns: 1fr;
            }
            .main-content {
                padding: 20px;
            }
            .recent-apps {
                overflow-x: auto;
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
            <a href="index.php">Home</a>
            <a href="explore.php">Explore</a>
            <a href="admin.php">Admin Dashboard</a>
        </nav>
        <div>
            <a href="logout.php" style="color:#fff; text-decoration:none; background:#f44336; padding:8px 16px; border-radius:4px;">Logout</a>
        </div>
    </div>

    <div class="main-content">
        <a href="admin.php" class="back-btn">← Back to Admin Dashboard</a>
        <h1>Application Overview</h1>

        <div class="overview-grid">
            <!-- Total Applications -->
            <div class="overview-card">
                <h2>Total Applications</h2>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_applications; ?></div>
                    <div class="stat-title">Total Applications Received</div>
                </div>
            </div>

            <!-- Application Status Distribution -->
            <div class="overview-card">
                <h2>Application Status Distribution</h2>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            <!-- Job Type Distribution -->
            <div class="overview-card">
                <h2>Applications by Job Type</h2>
                <div class="chart-container">
                    <canvas id="jobTypeChart"></canvas>
                </div>
            </div>

            <!-- Monthly Trends -->
            <div class="overview-card">
                <h2>Monthly Application Trends</h2>
                <div class="chart-container">
                    <canvas id="monthlyTrendsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Applications -->
        <div class="overview-card recent-apps">
            <h2>Recent Applications</h2>
            <table>
                <tr>
                    <th>Applicant</th>
                    <th>Job Position</th>
                    <th>Job Type</th>
                    <th>Status</th>
                    <th>Applied Date</th>
                </tr>
                <?php foreach ($recent_applications as $app): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($app['full_name'] ?? $app['name']); ?>
                            <?php if (!empty($app['contact'])): ?>
                                <br><small style="color:#888;"><?php echo htmlspecialchars($app['contact']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($app['job']); ?></td>
                        <td><?php echo htmlspecialchars($app['job_type']); ?></td>
                        <td>
                            <span class="badge <?php echo strtolower(str_replace(' ', '-', $app['status1'])); ?>">
                                <?php echo htmlspecialchars($app['status1']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($app['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <script>
        // Status Distribution Chart
        new Chart(document.getElementById('statusChart'), {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_keys($status_stats)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($status_stats, 'count')); ?>,
                    backgroundColor: [
                        '#2196f3',
                        '#4caf50',
                        '#ff9800',
                        '#4caf50',
                        '#f44336',
                        '#9c27b0',
                        '#795548'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            color: '#fff'
                        }
                    }
                }
            }
        });

        // Job Type Chart
        new Chart(document.getElementById('jobTypeChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($job_type_stats)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($job_type_stats, 'count')); ?>,
                    backgroundColor: [
                        '#4fc3f7',
                        '#2196f3',
                        '#1976d2',
                        '#0d47a1'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            color: '#fff'
                        }
                    }
                }
            }
        });

        // Monthly Trends Chart
        new Chart(document.getElementById('monthlyTrendsChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthly_trends, 'month')); ?>,
                datasets: [{
                    label: 'Applications',
                    data: <?php echo json_encode(array_column($monthly_trends, 'count')); ?>,
                    borderColor: '#4fc3f7',
                    tension: 0.1,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#fff'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#fff'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#fff'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html> 
</html> 