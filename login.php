<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Include database configuration
require_once 'config.php';

// Get database connection
$conn = getDBConnection();

// Initialize tables if they don't exist
initializeTables($conn);

// Only redirect if user is already logged in
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['user_type']) {
        case 'admin':
            header('Location: admin.php');
            break;
        case 'employer':
            header('Location: dashboard.php');
            break;
        case 'jobseeker':
            header('Location: explore.php');
            break;
        default:
            header('Location: explore.php');
    }
    exit();
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        // Check user credentials
        $stmt = $conn->prepare('SELECT id, username, password, user_type FROM users WHERE username = ?');
        if (!$stmt) {
            $error = 'Database error: ' . $conn->error;
        } else {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_type'] = $user['user_type'];
                    
                    // Debug output
                    error_log('LOGIN SESSION: ' . print_r($_SESSION, true));
                    
                    // Robust redirection logic
                    if (strtolower(trim($user['user_type'])) === 'admin') {
                        header('Location: admin.php');
                        exit();
                    } else if (strtolower(trim($user['user_type'])) === 'employer') {
                        header('Location: dashboard.php');
                        exit();
                    } else {
                        header('Location: explore.php');
                        exit();
                    }
                } else {
                    $error = 'Invalid password.';
                }
            } else {
                $error = 'User not found.';
            }
            $stmt->close();
        }
    }
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
            justify-content: center;
            align-items: center;
        }
        .login-container {
            background: #232a34;
            padding: 32px;
            border-radius: 12px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.18);
        }
        .login-container h1 {
            text-align: center;
            margin-bottom: 24px;
            color: #4fc3f7;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #ccc;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 6px;
            background: #fff;
            color: #222;
            font-size: 1em;
        }
        .form-group input:focus {
            outline: 2px solid #4fc3f7;
        }
        .error {
            color: #ff5252;
            background: #fff3f3;
            padding: 8px 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.9em;
        }
        .login-button {
            width: 100%;
            padding: 12px;
            background: #4fc3f7;
            color: #222;
            border: none;
            border-radius: 6px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }
        .login-button:hover {
            background: #0288d1;
            color: #fff;
        }
        .signup-link {
            text-align: center;
            margin-top: 20px;
            color: #ccc;
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
        <h1>Login to JPOST</h1>
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
            <button type="submit" class="login-button">Login</button>
        </form>
        <div class="signup-link">
            Don't have an account? <a href="signup.php">Sign up</a>
        </div>
    </div>
</body>
</html> 