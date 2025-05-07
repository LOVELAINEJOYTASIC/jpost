<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Location: explore.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        body {
            background: #181818;
            color: #fff;
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .login-container {
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
        .login-container h1 {
            font-size: 2.2em;
            font-weight: bold;
            margin-bottom: 0;
            text-align: center;
            border-bottom: 2px solid #fff;
            width: 100%;
            padding-bottom: 4px;
            letter-spacing: 1px;
        }
        .login-container p {
            margin: 12px 0 18px 0;
            color: #ccc;
            font-size: 1em;
            text-align: center;
        }
        .login-container label {
            display: block;
            margin-bottom: 4px;
            margin-top: 12px;
            font-size: 1em;
        }
        .login-container input[type="text"],
        .login-container input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            border-radius: 16px;
            border: none;
            margin-bottom: 8px;
            font-size: 1em;
            background: #fff;
            color: #222;
        }
        .login-container .forgot {
            color: #ccc;
            font-size: 0.95em;
            margin-bottom: 10px;
            text-align: left;
            width: 100%;
        }
        .login-container .forgot a {
            color: #4fc3f7;
            text-decoration: underline;
        }
        .login-container button {
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
        .login-container button:hover {
            background: #0288d1;
            color: #fff;
        }
        .login-container .signup-link {
            color: #ccc;
            font-size: 1em;
            margin-bottom: 8px;
        }
        .login-container .signup-link a {
            color: #4fc3f7;
            text-decoration: underline;
        }
        .login-container .terms {
            color: #4fc3f7;
            font-size: 1em;
            margin-top: 10px;
            display: flex;
            align-items: center;
        }
        .login-container .terms input[type="checkbox"] {
            accent-color: #4fc3f7;
            margin-right: 6px;
        }
        .login-container .terms a {
            color: #4fc3f7;
            text-decoration: underline;
            margin-left: 4px;
        }
    </style>
</head>
<body>
    <form class="login-container" method="POST">
        <h1>LOGIN</h1>
        <p>Fill in your credentials to login.</p>
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required>
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
        <div class="forgot"><a href="#">Forgot password?</a></div>
        <button type="submit">Login</button>
        <div class="signup-link">Don't you have account? <a href="signup.php">Sign up</a></div>
        <div class="terms">
            <input type="checkbox" id="terms" required>
            <label for="terms" style="margin:0; color:#4fc3f7;">Terms and Condition</label>
        </div>
    </form>
</body>
</html> 