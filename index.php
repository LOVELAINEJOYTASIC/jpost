<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JPOST</title>
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
            margin-right: 32px;
        }
        .navbar .logo img {
            height: 32px;
            margin-right: 8px;
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
        .navbar .spacer {
            flex: 1;
        }
        .navbar .search {
            display: flex;
            align-items: center;
            background: #222;
            border-radius: 20px;
            padding: 4px 12px;
        }
        .navbar .search input {
            background: transparent;
            border: none;
            color: #fff;
            outline: none;
            padding: 6px 8px;
            font-size: 1em;
            width: 200px;
        }
        .navbar .search button {
            background: none;
            border: none;
            color: #fff;
            cursor: pointer;
            font-size: 1.2em;
        }
        .navbar .settings {
            margin-left: 18px;
            font-size: 1.7em;
            color: #4fc3f7;
            cursor: pointer;
        }
        .main-banner {
            background: #5bbcff;
            margin: 40px auto 0 auto;
            border-radius: 10px;
            width: 80%;
            min-width: 340px;
            max-width: 900px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 40px 32px 32px;
            position: relative;
        }
        .main-banner .left {
            flex: 1.2;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .main-banner .right {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }
        .main-banner h1 {
            color: #fff;
            font-size: 2.2em;
            font-weight: bold;
            margin: 0 0 18px 0;
            font-style: italic;
            text-align: center;
        }
        .main-banner .whats-new {
            background: #222;
            color: #fff;
            border-radius: 16px;
            padding: 24px 28px;
            margin-bottom: 18px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
            width: 340px;
            margin-left: auto;
            margin-right: auto;
            text-align: center;
        }
        .main-banner .whats-new h2 {
            margin: 0 0 10px 0;
            font-size: 1.3em;
            font-weight: 600;
        }
        .main-banner .whats-new ul {
            list-style: none;
            padding: 0;
            margin: 10px 0 0 0;
            text-align: left;
            display: inline-block;
        }
        .main-banner .whats-new ul li {
            margin: 7px 0;
            font-size: 1em;
            display: flex;
            align-items: center;
        }
        .main-banner .whats-new ul li span {
            margin-right: 8px;
        }
        .main-banner .register-btn {
            display: block;
            background: #4fc3f7;
            color: #222;
            font-weight: bold;
            text-align: center;
            border-radius: 8px;
            padding: 12px 0;
            margin-top: 18px;
            text-decoration: none;
            font-size: 1.1em;
            transition: background 0.2s;
        }
        .main-banner .register-btn:hover {
            background: #0288d1;
            color: #fff;
        }
        .main-banner .banner-img {
            max-width: 260px;
            border-radius: 10px;
            background: #fff;
            padding: 8px;
        }
        .main-banner .banner-img img {
            width: 100%;
            border-radius: 10px;
        }
        .main-banner .checklist {
            position: absolute;
            left: -110px;
            bottom: -30px;
            width: 180px;
            z-index: 1;
        }
        .footer {
            width: 100%;
            background: #181818;
            border-top: 2px solid #333;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 18px 0 10px 0;
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
        @media (max-width: 800px) {
            .main-banner {
                flex-direction: column;
                align-items: flex-start;
                padding: 24px 10px 24px 10px;
            }
            .main-banner .right {
                justify-content: center;
                margin-top: 15px;
            }
            .main-banner .checklist {
                display: none;
            }
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #222;
            margin: 8% auto;
            padding: 32px 24px 24px 24px;
            border: 1px solid #888;
            width: 320px;
            border-radius: 12px;
            color: #fff;
            position: relative;
            box-shadow: 0 4px 24px rgba(0,0,0,0.18);
        }
        .close {
            color: #aaa;
            position: absolute;
            top: 10px;
            right: 18px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: #fff;
        }
        .modal-btn {
            display: block;
            width: 100%;
            background: #5bbcff;
            color: #222;
            font-weight: bold;
            border: none;
            border-radius: 16px;
            padding: 12px 0;
            font-size: 1.1em;
            text-align: center;
            text-decoration: none;
            margin-bottom: 8px;
            transition: background 0.2s;
        }
        .modal-btn:hover {
            background: #0288d1;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="logo">
            <span style="font-size:1.2em; margin-right:4px;">&#128188;</span> JPOST
        </div>
        <nav style="margin-left: 24px; display: flex; align-items: center;">
            <a href="index.php">Home</a>
            <a href="explore.php">Explore</a>
            <a href="account.php">Account</a>
        </nav>
        <div style="display:flex; align-items:center;">
            <div class="search">
                <input type="text" placeholder="Find your dream job at JPost">
                <button>&#128269;</button>
            </div>
            <span class="settings">&#9881;</span>
        </div>
    </div>
    <div class="main-banner">
        <img src="" class="checklist" alt="Checklist" />
        <div class="left">
            <h1><span style="color:#fff; font-style:italic; font-weight:700;">Connecting Talent with Opportunity!</span></h1>
            <div class="whats-new">
                <h2>What's New?</h2>
                <div style="font-size:1em; margin-bottom:8px;">LOCAL JOB HIRING! <span style="color:#f44336;">&#128204;</span></div>
                <div style="font-size:1em; margin-bottom:8px; text-decoration:underline;">@VALENCIA PRIME TRADING</div>
                <ul>
                    <li><span>&#128188;</span> Office Clerk</li>
                    <li><span>&#128295;</span> Maintenance Staff</li>
                    <li><span>&#128230;</span> Warehouse Helper</li>
                    <li><span>&#128663;</span> Company Driver</li>
                    <li><span>&#128179;</span> Accounting Assistant</li>
                </ul>
                <a href="#" class="register-btn" id="openModalBtn">Register now!</a>
            </div>
        </div>
        <div class="right">
            <div class="banner-img">
                <img src="https://i.pinimg.com/736x/12/14/c7/1214c7112b9a353035eaea169981947e.jpg"Professional Woman" />
            </div>
        </div>
    </div>
    <div class="footer">
        <a href="#">Security & Privacy</a>
        <a href="#">Terms and Condition</a>
        <a href="#">About</a>
        <a href="#">Report</a>
    </div>

    <!-- Modal for Register -->
    <div id="registerModal" class="modal">
        <div class="modal-content">
            <span class="close" id="closeModalBtn">&times;</span>
            <h2 style="text-align:center; margin-bottom: 24px;">Register</h2>
            <div style="display: flex; flex-direction: column; gap: 18px; align-items: center;">
                <a href="signup.php" class="modal-btn">Sign Up</a>
                <a href="login.php" class="modal-btn">Login</a>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('openModalBtn').onclick = function() {
        document.getElementById('registerModal').style.display = 'block';
    };
    document.getElementById('closeModalBtn').onclick = function() {
        document.getElementById('registerModal').style.display = 'none';
    };
    window.onclick = function(event) {
        var modal = document.getElementById('registerModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    };
    </script>
</body>
</html> 