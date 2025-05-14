<?php
session_start();
require_once 'config.php';
$conn = getDBConnection();

// Authentication: Only allow admin or hr
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['user_type']), ['admin', 'hr'])) {
    header('Location: login.php?error=not_logged_in');
    exit();
}

// Get applicant statistics
$applicant_stats_sql = "SELECT 
    status1,
    COUNT(*) as count,
    (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM applicants)) as percentage
FROM applicants 
GROUP BY status1";

$applicant_stats_result = $conn->query($applicant_stats_sql);
$applicant_stats = [];
if ($applicant_stats_result && $applicant_stats_result->num_rows > 0) {
    while ($row = $applicant_stats_result->fetch_assoc()) {
        $applicant_stats[$row['status1']] = [
            'count' => $row['count'],
            'percentage' => round($row['percentage'], 1)
        ];
    }
}

// Get job statistics
$job_stats_sql = "SELECT 
    status,
    COUNT(*) as count,
    (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM jobs)) as percentage
FROM jobs 
GROUP BY status";

$job_stats_result = $conn->query($job_stats_sql);
$job_stats = [];
if ($job_stats_result && $job_stats_result->num_rows > 0) {
    while ($row = $job_stats_result->fetch_assoc()) {
        $job_stats[$row['status']] = [
            'count' => $row['count'],
            'percentage' => round($row['percentage'], 1)
        ];
    }
}

// Get monthly application trends
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

// Get job type distribution
$job_type_sql = "SELECT 
    job_type,
    COUNT(*) as count,
    (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM jobs)) as percentage
FROM jobs 
GROUP BY job_type";

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Reports - JPOST</title>
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
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 24px;
            margin-top: 24px;
        }
        .report-card {
            background: #232a34;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .report-card h2 {
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
        @media (max-width: 768px) {
            .reports-grid {
                grid-template-columns: 1fr;
            }
            .main-content {
                padding: 20px;
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
            <a href="hr.php">HR Dashboard</a>
        </nav>
        <div>
            <a href="logout.php" style="color:#fff; text-decoration:none; background:#f44336; padding:8px 16px; border-radius:4px;">Logout</a>
        </div>
    </div>

    <div class="main-content">
        <a href="hr.php" class="back-btn">← Back to HR Dashboard</a>
        <h1>HR Reports & Analytics</h1>

        <div class="reports-grid">
            <!-- Applicant Status Distribution -->
            <div class="report-card">
                <h2>Applicant Status Distribution</h2>
                <div class="chart-container">
                    <canvas id="applicantStatusChart"></canvas>
                </div>
            </div>

            <!-- Job Status Distribution -->
            <div class="report-card">
                <h2>Job Status Distribution</h2>
                <div class="chart-container">
                    <canvas id="jobStatusChart"></canvas>
                </div>
            </div>

            <!-- Monthly Application Trends -->
            <div class="report-card">
                <h2>Monthly Application Trends</h2>
                <div class="chart-container">
                    <canvas id="monthlyTrendsChart"></canvas>
                </div>
            </div>

            <!-- Job Type Distribution -->
            <div class="report-card">
                <h2>Job Type Distribution</h2>
                <div class="chart-container">
                    <canvas id="jobTypeChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Applicant Status Chart
        new Chart(document.getElementById('applicantStatusChart'), {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_keys($applicant_stats)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($applicant_stats, 'count')); ?>,
                    backgroundColor: [
                        '#4fc3f7',
                        '#2196f3',
                        '#1976d2',
                        '#0d47a1',
                        '#64b5f6',
                        '#90caf9',
                        '#bbdefb'
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

        // Job Status Chart
        new Chart(document.getElementById('jobStatusChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($job_stats)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($job_stats, 'count')); ?>,
                    backgroundColor: [
                        '#4caf50',
                        '#2196f3',
                        '#f44336'
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

        // Job Type Chart
        new Chart(document.getElementById('jobTypeChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($job_type_stats)); ?>,
                datasets: [{
                    label: 'Number of Jobs',
                    data: <?php echo json_encode(array_column($job_type_stats, 'count')); ?>,
                    backgroundColor: '#4fc3f7'
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