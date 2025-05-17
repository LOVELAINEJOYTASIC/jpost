<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Check if user is logged in and is an employer
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['user_type']) !== 'employer') {
    header('Location: login.php');
    exit();
}

require_once 'config.php';
$conn = getDBConnection();

// Handle new job posting
if (isset($_POST['post_new_job'])) {
    $job_title = $conn->real_escape_string($_POST['job_title']);
    $company = $conn->real_escape_string($_POST['company']);
    $requirements = $conn->real_escape_string($_POST['requirements']);
    $salary = $conn->real_escape_string($_POST['salary']);
    $address = $conn->real_escape_string($_POST['address']);
    $hours_of_duty = $conn->real_escape_string($_POST['hours_of_duty']);
    $job_type = $conn->real_escape_string($_POST['job_type']);
    
    // Check if columns exist before adding them
    $check_hours = $conn->query("SHOW COLUMNS FROM jobs LIKE 'hours_of_duty'");
    if ($check_hours->num_rows == 0) {
        $conn->query("ALTER TABLE jobs ADD COLUMN hours_of_duty VARCHAR(100) NULL");
    }
    
    $check_job_type = $conn->query("SHOW COLUMNS FROM jobs LIKE 'job_type'");
    if ($check_job_type->num_rows == 0) {
        $conn->query("ALTER TABLE jobs ADD COLUMN job_type ENUM('Full Time', 'Part Time') DEFAULT 'Full Time'");
    }
    
    $insert_sql = "INSERT INTO jobs (job, company, requirements, salary, address, hours_of_duty, job_type) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("sssssss", $job_title, $company, $requirements, $salary, $address, $hours_of_duty, $job_type);
    
    if ($insert_stmt->execute()) {
        header('Location: dashboard.php?success=posted');
        exit();
    } else {
        $error = "Error posting job: " . $insert_stmt->error;
    }
    $insert_stmt->close();
}

// Handle job deletion
if (isset($_POST['delete_job'])) {
    $job_id = (int)$_POST['delete_job'];
    $delete_sql = "DELETE FROM jobs WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $job_id);
    if ($delete_stmt->execute()) {
        header('Location: dashboard.php?success=deleted');
        exit();
    }
    $delete_stmt->close();
}

// Handle job editing
if (isset($_POST['edit_job'])) {
    $job_id = (int)$_POST['edit_job'];
    $job_title = $conn->real_escape_string($_POST['job_title']);
    $company = $conn->real_escape_string($_POST['company']);
    $requirements = $conn->real_escape_string($_POST['requirements']);
    $salary = $conn->real_escape_string($_POST['salary']);
    $address = $conn->real_escape_string($_POST['address']);
    $hours_of_duty = $conn->real_escape_string($_POST['hours_of_duty']);
    $job_type = $conn->real_escape_string($_POST['job_type']);
    
    // Check if columns exist before adding them
    $check_hours = $conn->query("SHOW COLUMNS FROM jobs LIKE 'hours_of_duty'");
    if ($check_hours->num_rows == 0) {
        $conn->query("ALTER TABLE jobs ADD COLUMN hours_of_duty VARCHAR(100) NULL");
    }
    
    $check_job_type = $conn->query("SHOW COLUMNS FROM jobs LIKE 'job_type'");
    if ($check_job_type->num_rows == 0) {
        $conn->query("ALTER TABLE jobs ADD COLUMN job_type ENUM('Full Time', 'Part Time') DEFAULT 'Full Time'");
    }
    
    $update_sql = "UPDATE jobs SET job = ?, company = ?, requirements = ?, salary = ?, address = ?, hours_of_duty = ?, job_type = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sssssssi", $job_title, $company, $requirements, $salary, $address, $hours_of_duty, $job_type, $job_id);
    if ($update_stmt->execute()) {
        header('Location: dashboard.php?success=updated');
        exit();
    }
    $update_stmt->close();
}

// Handle interview status update
if (isset($_POST['update_interview_status'])) {
    $applicant_id = (int)$_POST['applicant_id'];
    $interview_status = $conn->real_escape_string($_POST['interview_status']);
    
    $update_sql = "UPDATE applicants SET status2 = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $interview_status, $applicant_id);
    
    if ($update_stmt->execute()) {
        header('Location: dashboard.php?success=interview_updated');
        exit();
    }
    $update_stmt->close();
}

// Handle recruit status update
if (isset($_POST['update_recruit_status'])) {
    $applicant_id = (int)$_POST['applicant_id'];
    $recruit_status = $conn->real_escape_string($_POST['recruit_status']);
    $offer_details = $conn->real_escape_string($_POST['offer_details'] ?? '');
    $recruitment_notes = $conn->real_escape_string($_POST['recruitment_notes'] ?? '');
    
    $update_sql = "UPDATE applicants SET 
        status3 = ?,
        offer_details = ?,
        recruitment_notes = ?,
        last_updated = NOW()
        WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sssi", $recruit_status, $offer_details, $recruitment_notes, $applicant_id);
    
    if ($update_stmt->execute()) {
        header('Location: dashboard.php?success=recruit_updated');
        exit();
    }
    $update_stmt->close();
}

// Handle adding notes
if (isset($_POST['add_note'])) {
    $applicant_id = (int)$_POST['applicant_id'];
    $note = $conn->real_escape_string($_POST['note']);
    
    $insert_sql = "INSERT INTO candidate_notes (applicant_id, note, created_at) VALUES (?, ?, NOW())";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("is", $applicant_id, $note);
    
    if ($insert_stmt->execute()) {
        header('Location: dashboard.php?success=note_added');
        exit();
    }
    $insert_stmt->close();
}

// Handle sending emails
if (isset($_POST['send_email'])) {
    $applicant_id = (int)$_POST['applicant_id'];
    $subject = $conn->real_escape_string($_POST['email_subject']);
    $body = $conn->real_escape_string($_POST['email_body']);
    
    // Get applicant email
    $email_sql = "SELECT email FROM applicants WHERE id = ?";
    $email_stmt = $conn->prepare($email_sql);
    $email_stmt->bind_param("i", $applicant_id);
    $email_stmt->execute();
    $email_result = $email_stmt->get_result();
    $applicant = $email_result->fetch_assoc();
    
    if ($applicant) {
        // Send email using your preferred method
        // For now, we'll just store it in the database
        $insert_sql = "INSERT INTO email_logs (applicant_id, subject, body, sent_at) VALUES (?, ?, ?, NOW())";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iss", $applicant_id, $subject, $body);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
    
    header('Location: dashboard.php?success=email_sent');
    exit();
}

// Handle bulk actions
if (isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $applicant_ids = explode(',', $_POST['applicant_ids']);
    
    foreach ($applicant_ids as $id) {
        $applicant_id = (int)$id;
        switch ($action) {
            case 'send_offer':
                $status = 'Offer Sent';
                break;
            case 'mark_hired':
                $status = 'Hired';
                break;
            case 'mark_declined':
                $status = 'Offer Declined';
                break;
            default:
                continue 2;
        }
        
        $update_sql = "UPDATE applicants SET status3 = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $status, $applicant_id);
        $update_stmt->execute();
        $update_stmt->close();
    }
    
    header('Location: dashboard.php?success=bulk_updated');
    exit();
}

// Fetch employer's posted jobs
$jobs_sql = "SELECT * FROM jobs ORDER BY created_at DESC";
$jobs_stmt = $conn->prepare($jobs_sql);
$jobs_stmt->execute();
$jobs_result = $jobs_stmt->get_result();

// Fetch all applicants for employer's jobs
$applicants_sql = "SELECT a.*, j.job as job_title, j.company 
                  FROM applicants a 
                  JOIN jobs j ON a.job_id = j.id 
                  WHERE a.status1 IN ('In Review', 'In Process', 'Interview', 'On Demand', 'Accepted', 'Cancelled', 'In Waiting')
                  ORDER BY a.created_at DESC";
$applicants_stmt = $conn->prepare($applicants_sql);
$applicants_stmt->execute();
$applicants_result = $applicants_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Dashboard - JPOST</title>
    <style>
        body {
            background: linear-gradient(135deg, #181818 60%, #232a34 100%);
            color: #fff;
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            background: #181818;
            border-bottom: 2px solid #333;
            height: 60px;
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
            text-decoration: underline;
        }
        .navbar .logout-btn {
            background: #f44336;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .navbar .logout-btn:hover {
            background: #d32f2f;
        }
        .dashboard-container {
            margin: 48px auto 0 auto;
            width: 95%;
            max-width: 1000px;
            min-width: 320px;
            background: transparent;
            border-radius: 16px;
            padding: 32px 0 32px 0;
            min-height: 480px;
            position: relative;
            display: flex;
            flex-direction: row;
            gap: 48px;
            justify-content: center;
        }
        .job-card {
            background: #fff;
            color: #222;
            border-radius: 8px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.18);
            padding: 32px 28px 24px 28px;
            min-width: 300px;
            max-width: 340px;
            margin: auto 0;
            text-align: center;
        }
        .job-card .profile-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 20px auto;
            border: 3px solid #5bbcff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .job-card h2 {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 18px;
        }
        .job-card .job-details {
            text-align: left;
            margin-bottom: 18px;
            font-size: 1.1em;
        }
        .job-card .post-btn {
            width: 70%;
            background: #4fc3f7;
            color: #222;
            font-weight: bold;
            border: none;
            border-radius: 8px;
            padding: 12px 0;
            font-size: 1.1em;
            margin: 10px 0 0 0;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-block;
            text-decoration: none;
            opacity: 0.5;
            cursor: not-allowed;
        }
        .dashboard-actions {
            display: flex;
            flex-direction: column;
            gap: 24px;
            margin: auto 0;
            position: relative;
            z-index: 10;
        }
        .dashboard-actions button {
            width: 200px;
            padding: 18px 0;
            border: none;
            border-radius: 4px;
            font-size: 1.15em;
            font-weight: bold;
            cursor: pointer;
            margin-bottom: 0;
            text-align: center;
            display: block;
            position: relative;
            transition: all 0.2s ease;
            outline: none;
            -webkit-tap-highlight-color: transparent;
            pointer-events: auto;
        }
        .dashboard-actions .candidate-list { 
            background: #7ed957; 
            color: #222;
        }
        .dashboard-actions .resume { 
            background: #ffb366; 
            color: #222;
        }
        .dashboard-actions .interview { 
            background: #f7f7b6; 
            color: #222;
        }
        .dashboard-actions .recruit { 
            background: #008080; 
            color: #fff;
        }
        .dashboard-actions button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .dashboard-actions button:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        .dashboard-actions button:focus {
            outline: 2px solid #4fc3f7;
            outline-offset: 2px;
        }
        .section-title {
            color: #4fc3f7;
            font-size: 1.3em;
            font-weight: bold;
            margin: 32px 0 12px 0;
        }
        .posted-jobs {
            width: 95%;
            max-width: 1100px;
            margin: 0 auto 32px auto;
        }
        .jobs-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
        }
        .job-tile {
            background: #fff;
            color: #222;
            border-radius: 8px;
            box-shadow: 0 2px 8px #0002;
            padding: 18px 22px;
            min-width: 220px;
            max-width: 320px;
            flex: 1;
            position: relative;
            margin-bottom: 12px;
        }
        .job-tile .job-title {
            color: #1ca7ec;
            font-weight: bold;
            font-size: 1.1em;
            margin-bottom: 6px;
        }
        .job-tile .job-info {
            font-size: 0.98em;
            margin-bottom: 8px;
        }
        .job-tile .job-info b {
            font-weight: 600;
        }
        .job-tile .job-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }
        .job-tile .edit-btn {
            background: #4fc3f7;
            color: #222;
            border: none;
            border-radius: 6px;
            padding: 6px 14px;
            cursor: pointer;
            font-weight: bold;
            flex: 1;
        }
        .job-tile .edit-btn:hover {
            background: #0288d1;
            color: #fff;
        }
        .job-tile .delete-btn {
            background: #f44336;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 6px 14px;
            cursor: pointer;
            font-weight: bold;
            flex: 1;
        }
        .job-tile .delete-btn:hover {
            background: #b71c1c;
        }
        @media (max-width: 900px) {
            .dashboard-container {
                flex-direction: column;
                gap: 24px;
                padding: 0 8px 32px 8px;
                align-items: center;
            }
            .dashboard-actions button {
                width: 90vw;
                max-width: 300px;
            }
            .job-card {
                min-width: 90vw;
                max-width: 98vw;
            }
            .posted-jobs {
                width: 98vw;
            }
        }
        
        /* Add styles for the post job modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
        }
        .modal-content {
            background: #232a34;
            padding: 24px;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            margin: 48px auto;
            color: #fff;
            position: relative;
            z-index: 1001;
        }
        .modal-content h2 {
            margin-top: 0;
            color: #4fc3f7;
        }
        .modal-content input,
        .modal-content textarea {
            width: 100%;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #444;
            background: #2a323d;
            color: #fff;
            margin-bottom: 16px;
        }
        .modal-content textarea {
            min-height: 100px;
        }
        .modal-content label {
            display: block;
            margin-bottom: 8px;
        }
        .modal-buttons {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        .modal-buttons button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .success-message {
            background: #4caf50;
            color: white;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }

        /* Candidate List Styles */
        .candidates-list {
            max-height: 600px;
            overflow-y: auto;
            margin: 20px 0;
        }
        .candidate-card {
            background: #2a323d;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .candidate-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .candidate-header h3 {
            margin: 0;
            color: #4fc3f7;
        }
        .candidate-status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .candidate-status.pending {
            background: #ffd700;
            color: #000;
        }
        .candidate-status.interviewed {
            background: #4fc3f7;
            color: #000;
        }
        .candidate-status.hired {
            background: #4caf50;
            color: #fff;
        }
        .candidate-status.rejected {
            background: #f44336;
            color: #fff;
        }
        .candidate-details {
            margin-bottom: 16px;
        }
        .candidate-details p {
            margin: 8px 0;
            color: #fff;
        }
        .candidate-actions {
            display: flex;
            gap: 12px;
        }
        .view-resume-btn, .update-status-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            text-align: center;
            flex: 1;
        }
        .view-resume-btn {
            background: #4fc3f7;
            color: #222;
        }
        .update-status-btn {
            background: #666;
            color: #fff;
        }
        .view-resume-btn:hover {
            background: #0288d1;
            color: #fff;
        }
        .update-status-btn:hover {
            background: #444;
        }
        .no-candidates {
            text-align: center;
            padding: 32px;
            color: #666;
            font-size: 1.1em;
        }

        /* Updated Candidate List Styles */
        .status-container {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .candidate-status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .candidate-status.in-review {
            background: #ffd700;
            color: #000;
        }
        .candidate-status.in-process {
            background: #4fc3f7;
            color: #000;
        }
        .candidate-status.interview {
            background: #9c27b0;
            color: #fff;
        }
        .candidate-status.on-demand {
            background: #ff9800;
            color: #000;
        }
        .candidate-status.accepted {
            background: #4caf50;
            color: #fff;
        }
        .candidate-status.cancelled {
            background: #f44336;
            color: #fff;
        }
        .candidate-status.in-waiting {
            background: #607d8b;
            color: #fff;
        }
        .candidate-status.secondary {
            background: #666;
            color: #fff;
            font-size: 0.8em;
        }

        /* Interview Management Styles */
        .interview-list {
            max-height: 600px;
            overflow-y: auto;
            margin: 20px 0;
        }
        .interview-card {
            background: #2a323d;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .interview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .interview-header h3 {
            margin: 0;
            color: #4fc3f7;
        }
        .interview-status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .interview-status.pending {
            background: #ffd700;
            color: #000;
        }
        .interview-status.interviewed {
            background: #4caf50;
            color: #fff;
        }
        .interview-details {
            margin-bottom: 16px;
        }
        .interview-details p {
            margin: 8px 0;
            color: #fff;
        }
        .interview-actions {
            display: flex;
            gap: 12px;
        }
        .status-form {
            display: flex;
            gap: 12px;
            width: 100%;
        }
        .status-select {
            flex: 1;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #444;
            background: #2a323d;
            color: #fff;
        }
        .update-interview-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            background: #4fc3f7;
            color: #222;
            cursor: pointer;
            font-weight: bold;
        }
        .update-interview-btn:hover {
            background: #0288d1;
            color: #fff;
        }
        .no-interviews {
            text-align: center;
            padding: 32px;
            color: #666;
            font-size: 1.1em;
        }

        /* Recruit Modal Styles */
        .recruit-list {
            max-height: 600px;
            overflow-y: auto;
            margin: 20px 0;
        }
        .recruit-card {
            background: #2a323d;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .recruit-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .recruit-header h3 {
            margin: 0;
            color: #4fc3f7;
        }
        .recruit-status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .recruit-status.pending { background: #ffd700; color: #000; }
        .recruit-status.offer-sent { background: #2196f3; color: #fff; }
        .recruit-status.offer-accepted { background: #4caf50; color: #fff; }
        .recruit-status.offer-declined { background: #f44336; color: #fff; }
        .recruit-status.onboarding { background: #9c27b0; color: #fff; }
        .recruit-status.hired { background: #4caf50; color: #fff; }
        .recruit-details {
            margin-bottom: 16px;
        }
        .recruit-details p {
            margin: 8px 0;
            color: #fff;
        }
        .recruit-form {
            display: flex;
            gap: 12px;
            width: 100%;
        }
        .recruit-select {
            flex: 1;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #444;
            background: #2a323d;
            color: #fff;
        }
        .update-recruit-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            background: #4fc3f7;
            color: #222;
            cursor: pointer;
            font-weight: bold;
        }
        .update-recruit-btn:hover {
            background: #0288d1;
            color: #fff;
        }
        .no-recruits {
            text-align: center;
            padding: 32px;
            color: #666;
            font-size: 1.1em;
        }
        .recruit-form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .form-group label {
            color: #4fc3f7;
            font-weight: 500;
        }
        .recruit-textarea {
            width: 100%;
            min-height: 80px;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #444;
            background: #2a323d;
            color: #fff;
            resize: vertical;
        }
        .recruit-form-footer {
            display: flex;
            justify-content: flex-end;
        }
        .recruit-filters {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        .filter-group select {
            width: 100%;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #444;
            background: #2a323d;
            color: #fff;
        }
        .recruit-card {
            position: relative;
        }
        .recruit-timestamp {
            position: absolute;
            top: 16px;
            right: 16px;
            font-size: 0.8em;
            color: #888;
        }
        .recruit-history {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #444;
        }
        .history-item {
            font-size: 0.9em;
            color: #888;
            margin-bottom: 4px;
        }
        .recruit-search-sort {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .search-box {
            flex: 2;
            min-width: 200px;
        }
        .search-box input {
            width: 100%;
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #444;
            background: #2a323d;
            color: #fff;
        }
        .sort-options {
            flex: 1;
            min-width: 150px;
        }
        .sort-options select {
            width: 100%;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #444;
            background: #2a323d;
            color: #fff;
        }
        .quick-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }
        .quick-action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.2s;
        }
        .quick-action-btn.offer {
            background: #2196f3;
            color: #fff;
        }
        .quick-action-btn.offer:hover {
            background: #1976d2;
        }
        .quick-action-btn.hire {
            background: #4caf50;
            color: #fff;
        }
        .quick-action-btn.hire:hover {
            background: #388e3c;
        }
        .quick-action-btn.decline {
            background: #f44336;
            color: #fff;
        }
        .quick-action-btn.decline:hover {
            background: #d32f2f;
        }
        .recruit-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .stat-box {
            background: #2a323d;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-box .number {
            font-size: 1.5em;
            font-weight: bold;
            color: #4fc3f7;
        }
        .stat-box .label {
            font-size: 0.9em;
            color: #888;
        }
        .recruit-tools {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .bulk-actions {
            display: flex;
            gap: 8px;
        }
        .bulk-select {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #444;
            background: #2a323d;
            color: #fff;
            min-width: 200px;
        }
        .bulk-action-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            background: #4fc3f7;
            color: #222;
            cursor: pointer;
            font-weight: bold;
        }
        .bulk-action-btn:hover {
            background: #0288d1;
            color: #fff;
        }
        .view-options {
            display: flex;
            gap: 16px;
        }
        .view-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #fff;
            cursor: pointer;
        }
        .candidate-notes {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #444;
        }
        .notes-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .notes-header h4 {
            margin: 0;
            color: #4fc3f7;
        }
        .add-note-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            background: #4fc3f7;
            color: #222;
            cursor: pointer;
            font-size: 0.9em;
        }
        .note-item {
            background: #1a1f28;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 8px;
        }
        .note-content {
            color: #fff;
            margin-bottom: 8px;
        }
        .note-meta {
            font-size: 0.8em;
            color: #888;
        }
        .email-templates {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #444;
        }
        .template-header h4 {
            margin: 0 0 12px 0;
            color: #4fc3f7;
        }
        .template-list {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .template-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            background: #2a323d;
            color: #fff;
            cursor: pointer;
            transition: all 0.2s;
        }
        .template-btn:hover {
            background: #4fc3f7;
            color: #222;
        }
        .recruit-card {
            position: relative;
        }
        .recruit-card .checkbox-wrapper {
            position: absolute;
            top: 16px;
            left: 16px;
        }
        .recruit-card .checkbox-wrapper input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
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
    </style>
</head>
<body>
    <div class="navbar">
        <div class="logo">
            <span style="font-size:1.2em; margin-right:4px;">&#128188;</span> JPOST
        </div>
        <nav>
            <a href="#">Home</a>
            <a href="#">Explore</a>
            <a href="#">Account</a>
            <a href="#" class="active">Dashboard</a>
        </nav>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
    <div class="dashboard-container">
        <div class="job-card">
            <img src="https://cdn.dribbble.com/userupload/19543520/file/original-d33225063d5eb06e3ee96ccf2334a0a3.gif" alt="Professional Woman" class="profile-image">
            <h2>Post a Job</h2>
            <div class="job-details">
                <div>Fill in the details below to post a new job opening.</div>
            </div>
            <button type="button" class="post-btn" id="postNewJobBtn" disabled style="opacity:0.5;cursor:not-allowed;">Post New Job</button>
        </div>
        <div class="dashboard-actions">
            <button type="button" class="candidate-list" onclick="openCandidateModal()">View Candidate List</button>
            <button type="button" class="interview" onclick="openInterviewModal()">View Interview List</button>
            <button type="button" class="recruit" onclick="openRecruitModal()">View Recruit List</button>
        </div>
    </div>
    <div class="posted-jobs">
        <div class="section-title">Your Posted Jobs</div>
        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                <?php 
                if ($_GET['success'] === 'updated') echo 'Job updated successfully!';
                if ($_GET['success'] === 'deleted') echo 'Job deleted successfully!';
                if ($_GET['success'] === 'posted') echo 'Job posted successfully!';
                if ($_GET['success'] === 'interview_updated') echo 'Interview status updated successfully!';
                if ($_GET['success'] === 'recruit_updated') echo 'Recruitment status updated successfully!';
                if ($_GET['success'] === 'note_added') echo 'Note added successfully!';
                if ($_GET['success'] === 'email_sent') echo 'Email sent successfully!';
                if ($_GET['success'] === 'bulk_updated') echo 'Bulk actions applied successfully!';
                ?>
            </div>
        <?php endif; ?>
        <div class="jobs-grid">
            <?php if ($jobs_result->num_rows > 0): ?>
                <?php while ($job = $jobs_result->fetch_assoc()): ?>
                    <div class="job-tile">
                        <div class="job-title"><?php echo htmlspecialchars($job['job']); ?></div>
                        <div class="job-info"><b>Company:</b> <?php echo htmlspecialchars($job['company']); ?></div>
                        <div class="job-info"><b>Requirements:</b> <?php echo htmlspecialchars($job['requirements']); ?></div>
                        <div class="job-info"><b>Salary:</b> <?php echo htmlspecialchars($job['salary']); ?></div>
                        <div class="job-info"><b>Address:</b> <?php echo htmlspecialchars($job['address']); ?></div>
                        <div class="job-info"><b>Hours of Duty:</b> <?php echo htmlspecialchars($job['hours_of_duty'] ?? ''); ?></div>
                        <div class="job-info"><b>Job Type:</b> <?php echo htmlspecialchars($job['job_type'] ?? ''); ?></div>
                        <div class="job-info"><b>Posted:</b> <?php echo date('Y-m-d H:i:s', strtotime($job['created_at'])); ?></div>
                        <div class="job-actions">
                            <button class="edit-btn" onclick="openEditModal(<?php echo $job['id']; ?>, '<?php echo htmlspecialchars($job['job']); ?>', '<?php echo htmlspecialchars($job['company']); ?>', '<?php echo htmlspecialchars($job['requirements']); ?>', '<?php echo htmlspecialchars($job['salary']); ?>', '<?php echo htmlspecialchars($job['address']); ?>', '<?php echo htmlspecialchars($job['hours_of_duty'] ?? ''); ?>', '<?php echo htmlspecialchars($job['job_type'] ?? ''); ?>')">Edit</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this job posting?');">
                                <input type="hidden" name="delete_job" value="<?php echo $job['id']; ?>">
                                <button type="submit" class="delete-btn">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align: center; width: 100%; padding: 20px; color: #666;">
                    No jobs posted yet. Click "Post New Job" to create your first job posting.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Job Modal -->
    <div id="editModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000;">
        <div class="modal-content" style="background: #232a34; padding: 24px; border-radius: 12px; width: 90%; max-width: 500px; margin: 48px auto; color: #fff;">
            <h2 style="margin-top: 0; color: #4fc3f7;">Edit Job</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="edit_job" id="edit_job_id">
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px;">Job Title</label>
                    <input type="text" name="job_title" id="edit_job_title" required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #444; background: #2a323d; color: #fff;">
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px;">Company</label>
                    <input type="text" name="company" id="edit_company" required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #444; background: #2a323d; color: #fff;">
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px;">Requirements</label>
                    <textarea name="requirements" id="edit_requirements" required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #444; background: #2a323d; color: #fff; min-height: 100px;"></textarea>
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px;">Salary</label>
                    <input type="text" name="salary" id="edit_salary" required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #444; background: #2a323d; color: #fff;">
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px;">Address</label>
                    <input type="text" name="address" id="edit_address" required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #444; background: #2a323d; color: #fff;">
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px;">Hours of Duty</label>
                    <input type="text" name="hours_of_duty" id="edit_hours_of_duty" required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #444; background: #2a323d; color: #fff;">
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px;">Job Type</label>
                    <select name="job_type" id="edit_job_type" required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #444; background: #2a323d; color: #fff;">
                        <option value="Full Time">Full Time</option>
                        <option value="Part Time">Part Time</option>
                    </select>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button type="submit" style="flex: 1; padding: 12px; background: #4fc3f7; color: #222; border: none; border-radius: 4px; cursor: pointer;">Save Changes</button>
                    <button type="button" onclick="closeEditModal()" style="flex: 1; padding: 12px; background: #666; color: #fff; border: none; border-radius: 4px; cursor: pointer;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Post New Job Modal -->
    <div id="postModal" class="modal">
        <div class="modal-content" style="background: #232a34; padding: 24px; border-radius: 12px; width: 90%; max-width: 500px; margin: 48px auto; color: #fff;">
            <h2 style="margin-top: 0; color: #4fc3f7;">Post New Job</h2>
            <form method="POST" id="postJobForm">
                <input type="hidden" name="post_new_job" value="1">
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px;">Job Title</label>
                    <input type="text" name="job_title" required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #444; background: #2a323d; color: #fff;">
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px;">Company</label>
                    <input type="text" name="company" required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #444; background: #2a323d; color: #fff;">
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px;">Requirements</label>
                    <textarea name="requirements" required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #444; background: #2a323d; color: #fff; min-height: 100px;"></textarea>
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px;">Salary</label>
                    <input type="text" name="salary" required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #444; background: #2a323d; color: #fff;">
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px;">Address</label>
                    <input type="text" name="address" required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #444; background: #2a323d; color: #fff;">
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px;">Hours of Duty</label>
                    <input type="text" name="hours_of_duty" required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #444; background: #2a323d; color: #fff;">
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px;">Job Type</label>
                    <select name="job_type" required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #444; background: #2a323d; color: #fff;">
                        <option value="Full Time">Full Time</option>
                        <option value="Part Time">Part Time</option>
                    </select>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button type="submit" style="flex: 1; padding: 12px; background: #4fc3f7; color: #222; border: none; border-radius: 4px; cursor: pointer;">Post Job</button>
                    <button type="button" onclick="closePostModal()" style="flex: 1; padding: 12px; background: #666; color: #fff; border: none; border-radius: 4px; cursor: pointer;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Candidate List Modal -->
    <div id="candidateModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <h2>Candidate List</h2>
            <div class="candidates-list">
                <?php if ($applicants_result->num_rows > 0): ?>
                    <?php
                    // Reset pointer if needed (in case you looped before)
                    $applicants_result->data_seek(0);
                    while ($applicant = $applicants_result->fetch_assoc()): ?>
                        <div class="candidate-card">
                            <div class="candidate-header">
                                <h3><?php echo htmlspecialchars($applicant['name']); ?></h3>
                                <div class="status-container">
                                    <span class="candidate-status <?php echo strtolower(str_replace(' ', '-', $applicant['status1'])); ?>">
                                        <?php echo htmlspecialchars($applicant['status1']); ?>
                                    </span>
                                    <?php if (!empty($applicant['status2'])): ?>
                                        <span class="candidate-status secondary">
                                            <?php echo htmlspecialchars($applicant['status2']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($applicant['status3'])): ?>
                                        <span class="candidate-status secondary">
                                            <?php echo htmlspecialchars($applicant['status3']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="candidate-details">
                                <p><strong>Applied for:</strong> <?php echo htmlspecialchars($applicant['job_title']); ?></p>
                                <p><strong>Company:</strong> <?php echo htmlspecialchars($applicant['company']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($applicant['email']); ?></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($applicant['phone']); ?></p>
                                <p><strong>Applied on:</strong> <?php echo date('Y-m-d H:i:s', strtotime($applicant['created_at'])); ?></p>
                            </div>
                            <div class="candidate-actions">
                                <?php if (!empty($applicant['resume_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($applicant['resume_url']); ?>" target="_blank" class="view-resume-btn">View Resume</a>
                                <?php endif; ?>
                                <button onclick="updateStatus(<?php echo $applicant['id']; ?>, 'status1')" class="update-status-btn">Update Status</button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-candidates">
                        No candidates have applied yet.
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-buttons">
                <button type="button" onclick="closeCandidateModal()" style="background: #666; color: #fff;">Close</button>
            </div>
        </div>
    </div>

    <!-- Interview List Modal -->
    <div id="interviewModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <h2>Interview List</h2>
            <div class="interview-list">
                <?php
                // Only show applicants with status1 = 'Interview'
                $applicants_result->data_seek(0);
                $has_interviews = false;
                while ($applicant = $applicants_result->fetch_assoc()):
                    if (strtolower($applicant['status1']) === 'interview'):
                        $has_interviews = true;
                ?>
                    <div class="interview-card">
                        <div class="interview-header">
                            <h3><?php echo htmlspecialchars($applicant['name']); ?></h3>
                            <span class="interview-status"><?php echo htmlspecialchars($applicant['status1']); ?></span>
                        </div>
                        <div class="interview-details">
                            <p><strong>Applied for:</strong> <?php echo htmlspecialchars($applicant['job_title']); ?></p>
                            <p><strong>Company:</strong> <?php echo htmlspecialchars($applicant['company']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($applicant['email']); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($applicant['phone']); ?></p>
                            <p><strong>Applied on:</strong> <?php echo date('Y-m-d H:i:s', strtotime($applicant['created_at'])); ?></p>
                        </div>
                        <div class="interview-actions">
                            <form method="POST" class="status-form">
                                <input type="hidden" name="applicant_id" value="<?php echo $applicant['id']; ?>">
                                <select name="interview_status" class="status-select" required>
                                    <option value="">Update Interview Status</option>
                                    <option value="Interviewed" <?php if($applicant['status2']=='Interviewed') echo 'selected'; ?>>Interviewed</option>
                                    <option value="Pending" <?php if($applicant['status2']=='Pending') echo 'selected'; ?>>Pending</option>
                                    <option value="Rejected" <?php if($applicant['status2']=='Rejected') echo 'selected'; ?>>Rejected</option>
                                </select>
                                <button type="submit" name="update_interview_status" class="update-interview-btn">Update</button>
                            </form>
                        </div>
                    </div>
                <?php
                    endif;
                endwhile;
                if (!$has_interviews):
                ?>
                    <div class="no-interviews">No candidates scheduled for interview yet.</div>
                <?php endif; ?>
            </div>
            <div class="modal-buttons">
                <button type="button" onclick="closeInterviewModal()" style="background: #666; color: #fff;">Close</button>
            </div>
        </div>
    </div>

    <!-- Recruit List Modal -->
    <div id="recruitModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <h2>Recruit List</h2>
            <div class="recruit-list">
                <?php
                // Only show applicants with status3 (recruitment) set
                $applicants_result->data_seek(0);
                $has_recruits = false;
                while ($applicant = $applicants_result->fetch_assoc()):
                    if (!empty($applicant['status3'])):
                        $has_recruits = true;
                ?>
                    <div class="recruit-card">
                        <div class="recruit-header">
                            <h3><?php echo htmlspecialchars($applicant['name']); ?></h3>
                            <span class="recruit-status <?php echo strtolower(str_replace(' ', '-', $applicant['status3'])); ?>">
                                <?php echo htmlspecialchars($applicant['status3']); ?>
                            </span>
                        </div>
                        <div class="recruit-details">
                            <p><strong>Applied for:</strong> <?php echo htmlspecialchars($applicant['job_title']); ?></p>
                            <p><strong>Company:</strong> <?php echo htmlspecialchars($applicant['company']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($applicant['email']); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($applicant['phone']); ?></p>
                            <p><strong>Offer Details:</strong> <?php echo htmlspecialchars($applicant['offer_details'] ?? ''); ?></p>
                            <p><strong>Recruitment Notes:</strong> <?php echo htmlspecialchars($applicant['recruitment_notes'] ?? ''); ?></p>
                            <p><strong>Last Updated:</strong> <?php echo isset($applicant['last_updated']) ? htmlspecialchars($applicant['last_updated']) : ''; ?></p>
                        </div>
                    </div>
                <?php
                    endif;
                endwhile;
                if (!$has_recruits):
                ?>
                    <div class="no-recruits">No candidates in recruitment yet.</div>
                <?php endif; ?>
            </div>
            <div class="modal-buttons">
                <button type="button" onclick="closeRecruitModal()" style="background: #666; color: #fff;">Close</button>
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
        // Enable the Post New Job button and open the modal
        document.getElementById('postNewJobBtn').disabled = false;
        document.getElementById('postNewJobBtn').style.opacity = '1';
        document.getElementById('postNewJobBtn').style.cursor = 'pointer';

        document.getElementById('postNewJobBtn').onclick = function() {
            document.getElementById('postModal').style.display = 'block';
        };

        function closePostModal() {
            document.getElementById('postModal').style.display = 'none';
        }

        // Optional: Close modals when clicking outside modal content
        window.onclick = function(event) {
            var postModal = document.getElementById('postModal');
            var editModal = document.getElementById('editModal');
            var candidateModal = document.getElementById('candidateModal');
            var interviewModal = document.getElementById('interviewModal');
            var recruitModal = document.getElementById('recruitModal');
            if (event.target === postModal) postModal.style.display = 'none';
            if (event.target === editModal) editModal.style.display = 'none';
            if (event.target === candidateModal) candidateModal.style.display = 'none';
            if (event.target === interviewModal) interviewModal.style.display = 'none';
            if (event.target === recruitModal) recruitModal.style.display = 'none';
        };

        // Show the Edit Job modal and fill in the form
        function openEditModal(id, job, company, requirements, salary, address, hours_of_duty, job_type) {
            document.getElementById('edit_job_id').value = id;
            document.getElementById('edit_job_title').value = job;
            document.getElementById('edit_company').value = company;
            document.getElementById('edit_requirements').value = requirements;
            document.getElementById('edit_salary').value = salary;
            document.getElementById('edit_address').value = address;
            document.getElementById('edit_hours_of_duty').value = hours_of_duty;
            document.getElementById('edit_job_type').value = job_type;
            document.getElementById('editModal').style.display = 'block';
        }

        // Close the Edit Job modal
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function openCandidateModal() {
            document.getElementById('candidateModal').style.display = 'block';
        }
        function closeCandidateModal() {
            document.getElementById('candidateModal').style.display = 'none';
        }

        function openInterviewModal() {
            document.getElementById('interviewModal').style.display = 'block';
        }
        function closeInterviewModal() {
            document.getElementById('interviewModal').style.display = 'none';
        }

        function openRecruitModal() {
            document.getElementById('recruitModal').style.display = 'block';
        }
        function closeRecruitModal() {
            document.getElementById('recruitModal').style.display = 'none';
        }
    </script>
</body>
</html>