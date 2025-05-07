<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore - JPOST</title>
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
            position: relative;
        }
        .navbar nav a:hover, .navbar nav a.active {
            color: #4fc3f7;
        }
        .navbar .search {
            display: flex;
            align-items: center;
            background: #fff;
            border-radius: 20px;
            padding: 4px 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
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
        .explore-container {
            margin: 48px auto 0 auto;
            width: 95%;
            max-width: 950px;
            min-width: 320px;
            background: #232a34ee;
            border-radius: 20px;
            border: 2px solid #fff;
            padding: 36px 0 0 0;
            min-height: 480px;
            position: relative;
            box-shadow: 0 8px 32px rgba(0,0,0,0.18);
        }
        .explore-title {
            text-align: center;
            font-size: 2em;
            font-weight: bold;
            letter-spacing: 1px;
            margin-bottom: 18px;
            color: #4fc3f7;
            text-shadow: 0 2px 8px #0002;
        }
        .explore-content {
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            gap: 32px;
            padding: 0 32px 32px 32px;
        }
        .explore-left {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 18px;
            margin-top: 18px;
        }
        .explore-left img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #fff;
            object-fit: cover;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
        }
        .explore-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .explore-search-bar {
            background: #fff;
            color: #222;
            border-radius: 16px;
            padding: 14px 28px;
            margin-bottom: 32px;
            margin-top: 18px;
            font-size: 1.2em;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 360px;
            max-width: 90vw;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
        }
        .explore-search-bar .search-icon {
            font-size: 1.3em;
            margin-left: 12px;
        }
        .explore-bubbles {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            justify-content: center;
            margin-bottom: 32px;
        }
        .bubble {
            padding: 12px 32px;
            border-radius: 24px;
            color: #fff;
            font-size: 1.15em;
            font-weight: 500;
            margin: 0 6px 12px 0;
            display: inline-block;
            cursor: pointer;
            transition: transform 0.13s, box-shadow 0.13s, background 0.13s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
            border: 2px solid transparent;
        }
        .bubble:hover {
            transform: scale(1.09) translateY(-2px);
            box-shadow: 0 6px 24px rgba(0,0,0,0.18);
            border: 2px solid #fff;
            background: #222 !important;
            color: #4fc3f7 !important;
        }
        .bubble1 { background: #c2185b; }
        .bubble2 { background: #7b1fa2; }
        .bubble3 { background: #00bcd4; color: #222; }
        .bubble4 { background: #8d6e63; }
        .bubble5 { background: #d84315; }
        .bubble6 { background: #fbc02d; color: #222; }
        .bubble7 { background: #1976d2; }
        .bubble8 { background: #388e3c; }
        .bubble9 { background: #512da8; }
        .bubble10 { background: #388e3c; }
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
        @media (max-width: 900px) {
            .explore-content {
                flex-direction: column;
                gap: 0;
                padding: 0 8px 32px 8px;
            }
            .explore-main {
                align-items: center;
            }
            .explore-search-bar {
                width: 98vw;
                max-width: 98vw;
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
            <a href="explore.php" class="active">Explore</a>
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
    <div class="explore-container">
        <div class="explore-title">Explore Opportunities</div>
        <div class="explore-content">
            <div class="explore-left">
                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/4/4a/Logo_of_the_Department_of_Labor_and_Employment_%28DOLE%29.svg/1200px-Logo_of_the_Department_of_Labor_and_Employment_%28DOLE%29.svg.png" alt="DOLE Logo" style="width:60px; height:60px;">
                <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" alt="People Icon" style="width:60px; height:60px;">
            </div>
            <div class="explore-main">
                <div class="explore-search-bar">
                    <input type="text" placeholder="Search jobs, locations, or skills..." style="border:none;outline:none;background:transparent;width:80%;font-size:1.1em;color:#222;">
                    <span class="search-icon">&#128269;</span>
                </div>
                <div class="explore-bubbles">
                    <span class="bubble bubble1">@Damilag</span>
                    <span class="bubble bubble2">Web Developer</span>
                    <span class="bubble bubble3">@Tankulan</span>
                    <span class="bubble bubble4">CareTaker</span>
                    <span class="bubble bubble5">@Manolo Fortich</span>
                    <span class="bubble bubble6">@Alae</span>
                    <span class="bubble bubble7">Waitress/Waiter</span>
                    <span class="bubble bubble9">Driver</span>
                    <span class="bubble bubble10">Farming</span>
                </div>
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
</html> 