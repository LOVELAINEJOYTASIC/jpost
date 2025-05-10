<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Create database connection
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'jpost';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// Create user_profiles table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS user_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    full_name VARCHAR(255),
    birthday DATE,
    address TEXT,
    contact VARCHAR(255),
    application TEXT,
    avatar VARCHAR(255),
    status ENUM('Active', 'Offline') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $full_name = trim($conn->real_escape_string($_POST['full_name']));
    $birthday = trim($conn->real_escape_string($_POST['birthday']));
    $address = trim($conn->real_escape_string($_POST['address']));
    $contact = trim($conn->real_escape_string($_POST['contact']));
    $application = trim($conn->real_escape_string($_POST['application']));
    $status = trim($conn->real_escape_string($_POST['status']));
    
    // Validate inputs
    $errors = [];
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    if (empty($birthday)) {
        $errors[] = "Birthday is required";
    }
    if (empty($address)) {
        $errors[] = "Address is required";
    }
    if (empty($contact)) {
        $errors[] = "Contact information is required";
    } elseif (!filter_var($contact, FILTER_VALIDATE_EMAIL) && !preg_match('/^[0-9+\-\s()]{10,}$/', $contact)) {
        $errors[] = "Please enter a valid email or phone number";
    }
    if (empty($application)) {
        $errors[] = "Application letter is required";
    }
    
    // Handle file upload
    $avatar_path = '';
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploads_dir = 'uploads';
        if (!is_dir($uploads_dir)) {
            mkdir($uploads_dir, 0777, true);
        }
        $tmp_name = $_FILES['avatar']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed)) {
            if ($_FILES['avatar']['size'] <= 5 * 1024 * 1024) {
                $filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
                $target = "$uploads_dir/$filename";
                if (move_uploaded_file($tmp_name, $target)) {
                    $avatar_path = $target;
                    if (isset($profile['avatar']) && $profile['avatar'] && file_exists($profile['avatar'])) {
                        unlink($profile['avatar']);
                    }
                } else {
                    $errors[] = "Failed to upload image";
                }
            } else {
                $errors[] = "Image size should be less than 5MB";
            }
        } else {
            $errors[] = "Invalid image format. Allowed formats: " . implode(', ', $allowed);
        }
    }
    
    if (empty($errors)) {
        // Update profile
        $sql = "UPDATE user_profiles SET 
                full_name = ?, 
                birthday = ?, 
                address = ?, 
                contact = ?, 
                application = ?, 
                status = ?";
        
        $params = [$full_name, $birthday, $address, $contact, $application, $status];
        $types = "ssssss";
        
        if ($avatar_path) {
            $sql .= ", avatar = ?";
            $params[] = $avatar_path;
            $types .= "s";
        }
        
        $sql .= " WHERE user_id = ?";
        $params[] = $user_id;
        $types .= "i";
        
        $stmt = $conn->prepare($sql);
        
        // Create references for bind_param
        $bind_params = array();
        $bind_params[] = &$types;
        for($i = 0; $i < count($params); $i++) {
            $bind_params[] = &$params[$i];
        }
        
        call_user_func_array(array($stmt, 'bind_param'), $bind_params);
        
        if ($stmt->execute()) {
            $success_message = "Profile updated successfully!";
            // Update session data
            $_SESSION['user_name'] = $full_name;
            $_SESSION['user_status'] = $status;
        } else {
            $error_message = "Error updating profile: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Fetch existing profile data
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM user_profiles WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();

// Fetch accepted jobs
$accepted_jobs_sql = "SELECT j.*, ja.status, ja.created_at as applied_date 
                      FROM jobs j 
                      INNER JOIN job_applications ja ON j.id = ja.job_id 
                      WHERE ja.user_id = ? AND ja.status IN ('Accepted', 'Pending')
                      ORDER BY ja.created_at DESC";
$accepted_jobs_stmt = $conn->prepare($accepted_jobs_sql);
$accepted_jobs_stmt->bind_param("i", $user_id);
$accepted_jobs_stmt->execute();
$accepted_jobs = $accepted_jobs_stmt->get_result();

// Set default values
$full_name = $profile['full_name'] ?? '';
$birthday = $profile['birthday'] ?? '';
$address = $profile['address'] ?? '';
$contact = $profile['contact'] ?? '';
$application = $profile['application'] ?? '';
$avatar = $profile['avatar'] ?? 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png';
$status = $profile['status'] ?? 'Active';

$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account - JPOST</title>
    <style>
        body {
            background: #181818;
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
        .navbar .search {
            display: flex;
            align-items: center;
            background: #eee;
            border-radius: 20px;
            padding: 4px 12px;
        }
        .navbar .search input {
            background: transparent;
            border: none;
            color: #222;
            outline: none;
            padding: 6px 8px;
            font-size: 1em;
            width: 200px;
        }
        .navbar .search button {
            background: none;
            border: none;
            color: #222;
            cursor: pointer;
            font-size: 1.2em;
        }
        .navbar .settings {
            margin-left: 18px;
            font-size: 1.7em;
            color: #4fc3f7;
            cursor: pointer;
        }
        .account-container {
            margin: 48px auto 0 auto;
            width: 95%;
            max-width: 900px;
            min-width: 320px;
            background: #181818;
            border-radius: 16px;
            border: 2px solid #fff;
            padding: 32px 0 32px 0;
            min-height: 400px;
            position: relative;
        }
        .account-content {
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            justify-content: center;
            gap: 48px;
            padding: 0 48px;
        }
        .account-info {
            flex: 1.2;
            margin-top: 18px;
        }
        .account-info h2 {
            font-size: 1.3em;
            font-weight: 600;
            margin-bottom: 18px;
        }
        .account-info ul {
            list-style: none;
            padding: 0;
            margin: 0 0 12px 0;
        }
        .account-info ul li {
            margin: 7px 0;
            font-size: 1.08em;
        }
        .account-info .resume-link {
            color: #4fc3f7;
            text-decoration: underline;
            cursor: pointer;
        }
        .account-avatar {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .account-avatar img {
            width: 240px;
            height: 240px;
            border-radius: 50%;
            background: #fff;
            object-fit: cover;
            border: 4px solid #b2ebf2;
        }
        .account-status {
            margin-top: 18px;
            font-size: 1.2em;
            color: #b2ebf2;
            font-weight: bold;
        }
        .footer {
            width: 100%;
            background: #181818;
            border-top: 2px solid #333;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 18px 0 10px 0;
            position: static;
            left: 0;
            bottom: 0;
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
        @media (max-width: 700px) {
            .account-content {
                flex-direction: column;
                gap: 0;
                padding: 0 8px;
                align-items: center;
            }
            .account-avatar img {
                width: 120px;
                height: 120px;
            }
        }
        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            width: 100%;
        }
        .success-message {
            background: rgba(76, 175, 80, 0.1);
            color: #4caf50;
            border: 1px solid rgba(76, 175, 80, 0.2);
        }
        .error-message {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border: 1px solid rgba(244, 67, 54, 0.2);
        }
        .account-info input:focus,
        .account-info textarea:focus {
            outline: none;
            box-shadow: 0 0 0 2px #4fc3f7;
            transition: all 0.3s ease;
        }
        .account-info input,
        .account-info textarea {
            background: #222;
            color: #fff;
            border: 1px solid #333;
            transition: all 0.3s ease;
        }
        .account-info input:hover,
        .account-info textarea:hover {
            border-color: #4fc3f7;
        }
        .message {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            animation: slideIn 0.5s ease-out;
        }
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
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
            <a href="account.php" class="active">Account</a>
        </nav>
        <div style="display:flex; align-items:center;">
            <div class="search">
                <input type="text" placeholder="Find your dream job at JPost">
                <button>&#128269;</button>
            </div>
            <span class="settings">&#9881;</span>
        </div>
    </div>
    <div class="account-container">
        <form class="account-content" method="POST" enctype="multipart/form-data">
            <?php if ($success_message): ?>
                <div class="message success-message"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="message error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            <div class="account-info">
                <h2>Profile Management</h2>
                <ul style="list-style:none; padding:0; margin:0 0 12px 0;">
                    <li>*Full Name: <input type="text" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required style="width:90%;padding:4px 8px;margin:4px 0;border-radius:8px;border:none;"></li>
                    <li>*Birthday: <input type="date" name="birthday" value="<?php echo htmlspecialchars($birthday); ?>" required style="width:90%;padding:4px 8px;margin:4px 0;border-radius:8px;border:none;"></li>
                    <li>*Address: <input type="text" name="address" value="<?php echo htmlspecialchars($address); ?>" required style="width:90%;padding:4px 8px;margin:4px 0;border-radius:8px;border:none;"></li>
                    <li>*Contacts/Email Address: <input type="text" name="contact" value="<?php echo htmlspecialchars($contact); ?>" required style="width:90%;padding:4px 8px;margin:4px 0;border-radius:8px;border:none;"></li>
                    <li>*Application Letter (skills/position): <textarea name="application" required style="width:90%;padding:4px 8px;margin:4px 0;border-radius:8px;border:none;resize:vertical;"><?php echo htmlspecialchars($application); ?></textarea></li>
                </ul>
                <button type="submit" style="margin-top:10px;padding:8px 24px;border-radius:8px;background:#4fc3f7;color:#222;font-weight:bold;border:none;cursor:pointer;">Update Profile</button>
            </div>
            <div class="account-avatar">
                <label for="avatar" style="cursor:pointer;display:block;">
                    <img id="avatarPreview" src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar" />
                </label>
                <input type="file" id="avatar" name="avatar" accept="image/*" style="display:none;">
                <button type="button" onclick="document.getElementById('avatar').click();" style="margin-top:8px;padding:6px 18px;border-radius:8px;background:#4fc3f7;color:#222;font-weight:bold;border:none;cursor:pointer;">Change Photo</button>
                <div class="account-status">
                    Status: 
                    <select name="status" style="background:#222;color:#4fc3f7;border-radius:6px;padding:4px 10px;border:1px solid #4fc3f7;font-weight:bold;">
                        <option value="Active" <?php if($status==="Active") echo "selected"; ?>>Active</option>
                        <option value="Offline" <?php if($status==="Offline") echo "selected"; ?>>Offline</option>
                    </select>
                </div>
            </div>
        </form>
        <div style="margin-top: 32px; padding: 0 48px;">
            <h2 style="color: #4fc3f7; margin-bottom: 18px;">My Applications</h2>
            <div style="display: flex; flex-wrap: wrap; gap: 18px;">
                <?php if ($accepted_jobs && $accepted_jobs->num_rows > 0): ?>
                    <?php while($job = $accepted_jobs->fetch_assoc()): ?>
                        <div style="background: #232a34; padding: 18px; border-radius: 8px; flex: 1; min-width: 280px; max-width: 400px; border: 1px solid #4fc3f7;">
                            <h3 style="color: #4fc3f7; margin: 0 0 12px 0;"><?php echo htmlspecialchars($job['job']); ?></h3>
                            <p style="margin: 8px 0;"><strong>Company:</strong> <?php echo htmlspecialchars($job['company']); ?></p>
                            <p style="margin: 8px 0;"><strong>Salary:</strong> <?php echo htmlspecialchars($job['salary']); ?></p>
                            <p style="margin: 8px 0;"><strong>Requirements:</strong> <?php echo nl2br(htmlspecialchars($job['requirements'])); ?></p>
                            <p style="margin: 8px 0; color: #4fc3f7;"><strong>Applied:</strong> <?php echo date('F j, Y', strtotime($job['applied_date'])); ?></p>
                            <p style="margin: 8px 0; color: <?php echo $job['status'] === 'Accepted' ? '#4caf50' : '#ffc107'; ?>;">
                                <strong>Status:</strong> <?php echo htmlspecialchars($job['status']); ?>
                            </p>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="background: #232a34; padding: 24px; border-radius: 8px; width: 100%; text-align: center; color: #888;">
                        <p style="margin: 0;">No applications yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="footer">
            <a href="#">Security & Privacy</a>
            <a href="#">Terms and Condition</a>
            <a href="#">About</a>
            <a href="#">Report</a>
        </div>
    </div>
</body>
<script>
// Show preview of uploaded image
const avatarInput = document.getElementById('avatar');
const avatarPreview = document.getElementById('avatarPreview');
avatarInput.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(ev) {
            avatarPreview.src = ev.target.result;
        };
        reader.readAsDataURL(file);
    }
});
</script>
</html> 