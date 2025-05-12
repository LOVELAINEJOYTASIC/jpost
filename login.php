<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// If user is already logged in, redirect to appropriate page
if (isset($_SESSION['user_id'])) {
    if (strtolower($_SESSION['user_type']) === 'admin') {
        header('Location: admin.php');
    } else if (strtolower($_SESSION['user_type']) === 'hr') {
        header('Location: hr.php');
    } else if (strtolower($_SESSION['user_type']) === 'employer') {
        header('Location: dashboard.php');
    } else {
        header('Location: track_candidate.php');
    }
    exit();
}

require_once 'config.php';
$conn = getDBConnection();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];
    
    // Debug log
    error_log("Login attempt for username: " . $username);
    
    $query = "SELECT id, username, password, user_type FROM users WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        error_log("User found in database. Raw user type from DB: " . $user['user_type']);
        error_log("Raw user data: " . print_r($user, true));
        
        if (password_verify($password, $user['password'])) {
            // Clear any existing session data
            session_unset();
            session_destroy();
            session_start();
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_type'] = strtolower(trim($user['user_type'])); // Ensure lowercase and trim whitespace
            
            error_log("Password verified. Session variables set:");
            error_log("user_id: " . $_SESSION['user_id']);
            error_log("username: " . $_SESSION['username']);
            error_log("user_type: " . $_SESSION['user_type']);
            error_log("Full session data: " . print_r($_SESSION, true));
            
            // Debug the comparison
            error_log("Comparing user_type '" . $_SESSION['user_type'] . "' with 'admin'");
            error_log("Comparison result: " . (strtolower($_SESSION['user_type']) === 'admin' ? 'true' : 'false'));
            
            // Redirect based on user type
            $user_type = strtolower($_SESSION['user_type']);
            error_log("User type for redirection: " . $user_type);
            
            if ($user_type === 'admin') {
                error_log("User is admin - Redirecting to admin.php");
                header('Location: admin.php');
                exit();
            } else if ($user_type === 'hr') {
                error_log("User is HR - Redirecting to hr.php");
                header('Location: hr.php');
                exit();
            } else if ($user_type === 'employer') {
                error_log("User is employer - Redirecting to dashboard.php");
                header('Location: dashboard.php');
                exit();
            } else {
                error_log("User is regular user - Redirecting to track_candidate.php");
                header('Location: track_candidate.php');
                exit();
            }
        } else {
            error_log("Password verification failed");
            $error = 'Invalid username or password';
        }
    } else {
        error_log("No user found with username: " . $username);
        $error = 'Invalid username or password';
    }
    
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - JPOST</title>
    <style>
        body {
            background: linear-gradient(135deg, #181818 60%, #232a34 100%);
            color: #fff;
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: #232a34;
            padding: 32px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
            margin: 16px;
        }
        .logo {
            text-align: center;
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 24px;
            color: #4fc3f7;
        }
        .form-group {
            margin-bottom: 16px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #888;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #444;
            border-radius: 6px;
            background: #2a323d;
            color: #fff;
            font-size: 1em;
            margin-bottom: 0;
            box-sizing: border-box;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #4fc3f7;
            background: #2a323d;
        }
        .error {
            color: #f44336;
            margin-bottom: 16px;
            text-align: center;
        }
        .btn {
            width: 100%;
            padding: 12px;
            background: #4fc3f7;
            color: #222;
            border: none;
            border-radius: 6px;
            font-size: 1em;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #81d4fa;
        }
        .links {
            margin-top: 24px;
            text-align: center;
        }
        .links a {
            color: #4fc3f7;
            text-decoration: none;
            margin: 0 8px;
        }
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <span style="font-size:1.2em; margin-right:4px;">&#9675;</span> JPOST
        </div>
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn">Login</button>
        </form>
        <div class="links">
            <a href="signup.php">Create Account</a>
            <a href="forgot_password.php">Forgot Password?</a>
        </div>
    </div>
</body>
</html> 