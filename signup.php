<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
// Create database if it doesn't exist
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'jpost';
// Connect to MySQL server only (no db yet)
$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}
$conn->query("CREATE DATABASE IF NOT EXISTS `$db`");
$conn->close();
// Now connect to the actual database
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}
// Create users table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('jobseeker','employer','admin') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    $user_type = $_POST['user_type'] ?? '';
    if ($password !== $confirm) {
        $error = 'Passwords do not match!';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters.';
    } elseif (!in_array($user_type, ['jobseeker', 'employer', 'admin'])) {
        $error = 'Invalid user type.';
    } else {
        // Check for duplicate username
        $stmt = $conn->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = 'Username already taken!';
        } else {
            // Hash password and insert
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('INSERT INTO users (username, password, user_type) VALUES (?, ?, ?)');
            $stmt->bind_param('sss', $username, $hash, $user_type);
            if ($stmt->execute()) {
                $_SESSION['user_id'] = $stmt->insert_id;
                $_SESSION['username'] = $username;
                $_SESSION['user_type'] = $user_type;
                
                // Redirect based on user type
                switch ($user_type) {
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
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up</title>
    <style>
        body {
            background: #181818;
            color: #fff;
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .signup-container {
            background: #181818;
            width: 350px;
            margin: 48px auto;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.18);
            padding: 32px 28px 18px 28px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .signup-container h1 {
            font-size: 2.2em;
            font-weight: bold;
            margin-bottom: 0;
            text-align: center;
            border-bottom: 2px solid #fff;
            width: 100%;
            padding-bottom: 4px;
            letter-spacing: 1px;
        }
        .signup-container p {
            margin: 12px 0 18px 0;
            color: #ccc;
            font-size: 1em;
            text-align: center;
        }
        .signup-container label {
            display: block;
            margin-bottom: 4px;
            margin-top: 12px;
            font-size: 1em;
        }
        .signup-container input[type="text"],
        .signup-container input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            border-radius: 16px;
            border: none;
            margin-bottom: 8px;
            font-size: 1em;
            background: #fff;
            color: #222;
        }
        .signup-container .login-link {
            color: #ccc;
            font-size: 1em;
            margin-bottom: 8px;
        }
        .signup-container .login-link a {
            color: #4fc3f7;
            text-decoration: underline;
        }
        .signup-container button {
            width: 100%;
            background: #5bbcff;
            color: #222;
            font-weight: bold;
            border: none;
            border-radius: 16px;
            padding: 12px 0;
            font-size: 1.1em;
            margin: 10px 0 8px 0;
            cursor: pointer;
            transition: background 0.2s;
        }
        .signup-container button:hover {
            background: #0288d1;
            color: #fff;
        }
        .signup-container .terms {
            color: #4fc3f7;
            font-size: 1em;
            margin-top: 10px;
            display: flex;
            align-items: center;
        }
        .signup-container .terms input[type="checkbox"] {
            accent-color: #4fc3f7;
            margin-right: 6px;
        }
        .signup-container .terms a {
            color: #4fc3f7;
            text-decoration: underline;
            margin-left: 4px;
        }
        .user-type {
            display: flex;
            gap: 20px;
            margin: 12px 0;
            width: 100%;
        }
        .user-type label {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            margin: 0;
        }
        .user-type input[type="radio"] {
            accent-color: #4fc3f7;
            margin: 0;
        }
        .error {
            color: #ff5252;
            background: #fff3f3;
            border-radius: 8px;
            padding: 8px 12px;
            margin-bottom: 10px;
            width: 100%;
            text-align: center;
            font-size: 1em;
        }
    </style>
</head>
<body>
    <form class="signup-container" method="POST" autocomplete="off">
        <h1>SignUp</h1>
        <p>Please fill this form to create an account!</p>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <div class="user-type">
            <label>
                <input type="radio" name="user_type" value="jobseeker" required <?php if(isset($_POST['user_type']) && $_POST['user_type']==='jobseeker') echo 'checked'; ?>>
                Jobseeker
            </label>
            <label>
                <input type="radio" name="user_type" value="employer" required <?php if(isset($_POST['user_type']) && $_POST['user_type']==='employer') echo 'checked'; ?>>
                Employer
            </label>
        </div>
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
        <label for="confirm">Confirm Password</label>
        <input type="password" id="confirm" name="confirm" required>
        <div class="login-link">Already have an account? <a href="login.php">Login</a></div>
        <button type="submit">Submit</button>
        <div class="terms">
            <input type="checkbox" id="terms" required>
            <label for="terms" style="margin:0; color:#4fc3f7;">Terms and Condition</label>
        </div>
    </form>
</body>
</html> 