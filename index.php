<?php
session_start();
include 'db.php';
include 'log_action.php';

// ✅ ตรวจสอบการล็อกอิน
if (!isset($_SESSION['username'])) {
    header('location: login.php');
    exit();
}

$username = $_SESSION['username'];

// ✅ เก็บ Log การเข้าใช้งาน
$action = basename($_SERVER['PHP_SELF']);
$stmt = $conn->prepare("INSERT INTO logs (username, action) VALUES (?, ?)");
$stmt->execute([$username, $action]);

// ✅ Logout
if (isset($_GET['logout'])) {
    $stmt = $conn->prepare("INSERT INTO logs (username, action) VALUES (?, 'logout')");
    $stmt->execute([$username]);
    session_destroy();
    header('location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ระบบควบคุมตึก 10 ชั้น</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
           background: url('engineer.png') no-repeat center center fixed; /* ใส่ไฟล์รูป */
            background-size: cover; /* ปรับให้เต็มหน้าจอ */
            color: #333;
        }
        /* ✅ Overlay */
        body::before {
            content: "";
            position: fixed;
            top:0; left:0; right:0; bottom:0;
            background: rgba(0,0,0,0.4);
            z-index: -1;
        }
        /* ✅ Navbar */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(0,0,0,0.75);
            padding: 12px 25px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }
        .navbar h1 {
            color: #fff;
            font-size: 22px;
            margin: 0;
        }
        .navbar a {
            color: #fff;
            margin-left: 18px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
        }
        .navbar a:hover {
            color: #00d4a0;
        }
        /* ✅ Container */
        .container {
            max-width: 1100px;
            margin: 40px auto;
            text-align: center;
            background: rgba(255,255,255,0.9);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }
        h2 {
            margin-bottom: 30px;
            color: #222;
        }
        /* ✅ ปุ่มการ์ด */
        .grid {
            display: flex;
            flex-wrap: wrap;
            gap: 25px;
            justify-content: center;
        }
        .card-button {
            width: 200px;
            height: 140px;
            background: linear-gradient(135deg, #06a769, #03995d);
            color: white;
            border-radius: 15px;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease-in-out;
            box-shadow: 0 6px 12px rgba(0,0,0,0.2);
        }
        .card-button:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 10px 18px rgba(0,0,0,0.3);
        }
        .icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        /* ✅ Back */
        .back {
            margin-top: 30px;
            display: inline-block;
            font-weight: bold;
            text-decoration: none;
            color: #0077cc;
            transition: 0.3s;
        }
        .back:hover {
            text-decoration: underline;
            color: #005fa3;
        }
    </style>
</head>
<body>

<!-- ✅ Navigation -->
<div class="navbar">
    <h1>ระบบจัดการพลังงาน อาคารศรีวิศววิทยา คณะวิศวกรรมศาสตร์</h1>
    <div>
        <a href="about.php">About</a>
        <a href="index.php?logout=1">Logout</a>
    </div>
</div>

<div class="container">
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> 👋</h2>

    <div class="grid">
        <a href="building.php" class="card-button">
            <div class="icon">💡</div>
            Light Control
        </a>
        <a href="air.html" class="card-button">
            <div class="icon">❄️</div>
            Air Control
        </a>
        <a href="fan.php" class="card-button">
            <div class="icon">🌀</div>
            Fan Control
        </a>
        <!--<a href="change_theme.php" class="card-button">
            <div class="icon">⚙️</div>
            Settings
        </a> -->
    </div>

    <p>
        <a href="user_dashboard.php?back=1" class="back"> Back</a>
    </p>
</div>
</body>
</html>
