<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
    
    // Check session timeout (30 minutes)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_unset();
        session_destroy();
        header('Location: login.php?error=timeout');
        exit();
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
}

function getUserType() {
    return $_SESSION['user_type'] ?? null;
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getUsername() {
    return $_SESSION['username'] ?? null;
}

function isEmployer() {
    return getUserType() === 'employer';
}

function isJobSeeker() {
    return getUserType() === 'jobseeker';
}

function redirectBasedOnUserType() {
    if (isEmployer()) {
        header('Location: dashboard.php');
    } else {
        header('Location: explore.php');
    }
    exit();
}

function logout() {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}
?> 