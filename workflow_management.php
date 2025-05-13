<?php
session_start();
require_once 'config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_stage'])) {
        $stage_name = $_POST['stage_name'];
        $stage_description = $_POST['stage_description'];
        $stage_order = $_POST['stage_order'];
        
        $sql = "INSERT INTO workflow_stages (stage_name, stage_description, stage_order) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $stage_name, $stage_description, $stage_order);
        $stmt->execute();
    }
    
    if (isset($_POST['edit_stage'])) {
        $stage_id = $_POST['stage_id'];
        $stage_name = $_POST['stage_name'];
        $stage_description = $_POST['stage_description'];
        $stage_order = $_POST['stage_order'];
        
        $sql = "UPDATE workflow_stages SET stage_name = ?, stage_description = ?, stage_order = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssii", $stage_name, $stage_description, $stage_order, $stage_id);
        $stmt->execute();
    }
    
    if (isset($_POST['delete_stage'])) {
        $stage_id = $_POST['stage_id'];
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
}

// Fetch existing workflow stages
$sql = "SELECT * FROM workflow_stages ORDER BY stage_order ASC";
$result = $conn->query($sql);
$stages = $result->fetch_all(MYSQLI_ASSOC);
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
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .workflow-section {
            background: #222;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stage-list {
            display: grid;
            gap: 15px;
            margin-top: 20px;
        }
        .stage-item {
            background: #333;
            padding: 15px;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }
        .stage-item .drag-handle {
            cursor: move;
            color: #666;
            margin-right: 10px;
        }
        .stage-item .actions {
            display: flex;
            gap: 10px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #4fc3f7;
        }
        input[type="text"],
        input[type="number"],
        textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #444;
            border-radius: 4px;
            background: #333;
            color: #fff;
        }
        button {
            background: #4fc3f7;
            color: #181818;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        button:hover {
            background: #81d4fa;
        }
        .delete-btn {
            background: #f44336;
            color: white;
        }
        .delete-btn:hover {
            background: #e57373;
        }
        .edit-btn {
            background: #ff9800;
            color: white;
        }
        .edit-btn:hover {
            background: #ffb74d;
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
        .pipeline-view {
            display: flex;
            overflow-x: auto;
            padding: 20px 0;
            gap: 15px;
        }
        .pipeline-stage {
            min-width: 250px;
            background: #333;
            border-radius: 8px;
            padding: 15px;
        }
        .pipeline-stage h3 {
            margin-top: 0;
            color: #4fc3f7;
            border-bottom: 2px solid #444;
            padding-bottom: 10px;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
        }
        .modal-content {
            background: #222;
            width: 90%;
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
            border-radius: 8px;
            position: relative;
        }
        .close-modal {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        .close-modal:hover {
            color: #fff;
        }
        .save-order-btn {
            background: #4caf50;
            color: white;
            margin-top: 20px;
        }
        .save-order-btn:hover {
            background: #66bb6a;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="admin.php" class="back-btn">← Back to Admin Dashboard</a>
    <h1>Workflow Management</h1>
    <p>This is a placeholder page for workflow management. (Coming soon!)</p>
    </div>
</body>
</html> 