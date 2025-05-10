<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// If user is already logged in, redirect to appropriate page
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_type'] === 'admin') {
        header('Location: admin.php');
    } else if ($_SESSION['user_type'] === 'employer') {
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
        error_log("User found in database. User type: " . $user['user_type']);
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_type'] = $user['user_type'];
            
            error_log("Password verified. Setting session variables.");
            error_log("Session user type: " . $_SESSION['user_type']);
            
            // Redirect based on user type
            if ($user['user_type'] === 'admin') {
                header('Location: admin.php');
            } else if ($user['user_type'] === 'employer') {
                header('Location: dashboard.php');
            } else {
                header('Location: track_candidate.php');
            }
            exit();
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
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #444;
            border-radius: 6px;
            background: #1a1f28;
            color: #fff;
            font-size: 1em;
            box-sizing: border-box;
        }
        input:focus {
            outline: none;
            border-color: #4fc3f7;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #4fc3f7;
            color: #222;
            border: none;
            border-radius: 6px;
            font-size: 1em;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }
        button:hover {
            background: #81d4fa;
        }
        .error {
            color: #f44336;
            margin-bottom: 16px;
            text-align: center;
        }
        .signup-link {
            text-align: center;
            margin-top: 16px;
            color: #888;
        }
        .signup-link a {
            color: #4fc3f7;
            text-decoration: none;
        }
        .signup-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <span style="font-size:1.2em; margin-right:4px;">&#128188;</span> JPOST
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
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
            
            <button type="submit">Login</button>
        </form>
        
        <div class="signup-link">
            Don't have an account? <a href="signup.php">Sign up</a>
        </div>
    </div>
</body>
</html> 