<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms and Conditions - JPOST</title>
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
        .terms-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 32px;
            background: #232a34;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .terms-container h1 {
            color: #4fc3f7;
            margin-bottom: 24px;
            text-align: center;
        }
        .terms-container h2 {
            color: #4fc3f7;
            margin-top: 32px;
            margin-bottom: 16px;
        }
        .terms-container p {
            line-height: 1.6;
            margin-bottom: 16px;
        }
        .terms-container ul {
            margin-bottom: 16px;
            padding-left: 24px;
        }
        .terms-container li {
            margin-bottom: 8px;
            line-height: 1.6;
        }
        .back-btn {
            display: inline-block;
            background: #4fc3f7;
            color: #222;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 24px;
            transition: background 0.2s;
        }
        .back-btn:hover {
            background: #81d4fa;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="logo">
            <span style="font-size:1.2em; margin-right:4px;">&#9675;</span> JPOST
        </div>
        <nav>
            <a href="index.php">Home</a>
            <a href="explore.php">Explore</a>
            <a href="account.php">Account</a>
        </nav>
    </div>

    <div class="terms-container">
        <h1>Terms and Conditions</h1>
        <p>Effective Date: March 15, 2024</p>

        <p>Welcome to JPOST ("we", "our", or "us"). By accessing or using our website/app ("Service"), you agree to be bound by the following terms and conditions ("Terms"). If you do not agree to these Terms, please do not use the Service.</p>

        <h2>1. Use of the Service</h2>
        <p>You agree to use the Service only for lawful purposes and in accordance with these Terms. You must be at least 18 years old to use the Service.</p>

        <h2>2. Account Registration</h2>
        <p>To access certain features, you may need to register for an account. You agree to provide accurate and complete information and to keep your account credentials secure.</p>

        <h2>3. Intellectual Property</h2>
        <p>All content on the Service, including text, images, logos, and software, is the property of JPOST or its licensors and is protected by intellectual property laws.</p>

        <h2>4. Prohibited Activities</h2>
        <p>You agree not to:</p>
        <ul>
            <li>Use the Service for any unlawful purpose.</li>
            <li>Attempt to interfere with or compromise the integrity or security of the Service.</li>
            <li>Use automated means to access the Service.</li>
        </ul>

        <h2>5. Termination</h2>
        <p>We may suspend or terminate your access to the Service at our sole discretion, without notice, if you violate these Terms.</p>

        <h2>6. Disclaimer of Warranties</h2>
        <p>The Service is provided "as is" without warranties of any kind, either express or implied. We do not guarantee that the Service will be uninterrupted or error-free.</p>

        <h2>7. Limitation of Liability</h2>
        <p>To the fullest extent permitted by law, we are not liable for any damages arising out of your use or inability to use the Service.</p>

        <h2>8. Changes to These Terms</h2>
        <p>We may update these Terms from time to time. Changes will be effective when posted. Continued use of the Service after changes means you accept the updated Terms.</p>

        <h2>9. Governing Law</h2>
        <p>These Terms are governed by the laws of the Philippines, without regard to conflict of law principles.</p>

        <h2>10. Contact Us</h2>
        <p>If you have questions about these Terms, contact us at: support@jpost.com</p>

        <?php
        // Get the referrer URL
        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        $host = $_SERVER['HTTP_HOST'];
        
        // Check if referrer is from our site
        $isInternalReferrer = strpos($referrer, $host) !== false;
        
        // Default back URL
        $backUrl = 'index.php';
        
        // If referrer exists and is from our site, use it
        if ($isInternalReferrer) {
            $backUrl = $referrer;
        }
        ?>
        <a href="<?php echo htmlspecialchars($backUrl); ?>" class="back-btn">Back</a>
    </div>
</body>
</html> 