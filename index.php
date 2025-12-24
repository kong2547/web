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
$role = $_SESSION['role'] ?? 'user'; // ✅ เพิ่มตัวแปร role

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
    <!-- ✅ ฟอนต์ Kanit -->
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
    <!-- ✅ Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            font-family: 'Kanit', sans-serif;
            margin: 0;
            background: url('engineer.png') no-repeat center center fixed;
            background-size: cover;
            color: #333;
        }

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
        .navbar a:hover { color: #00d4a0; }

        .floating-logo {
            position: fixed;
            top: 68px;
            left: 24px;
            width: 120px;
            height: auto;
            filter: drop-shadow(0 6px 12px rgba(0,0,0,.35));
            pointer-events: none;
            z-index: 1;
        }

        .container {
            max-width: 800px;
            margin: 150px auto 40px;
            text-align: center;
            background: rgba(255,255,255,0.85);
            padding: 30px 25px;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }

        h2 {
            margin-bottom: 30px;
            color: #222;
            font-weight: 600;
        }

        .grid {
            display: flex;
            flex-wrap: wrap;
            gap: 120px;
            justify-content: center;
        }

        .card-button {
            width: 220px;
            height: 160px;
            border-radius: 20px;
            background: linear-gradient(135deg, #4facfe, #00f2fe);
            color: white;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            font-weight: 600;
            transition: transform .3s ease, box-shadow .3s ease, filter .3s ease;
            box-shadow: 0 8px 20px rgba(0,0,0,0.25);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.25);
            text-shadow: 0 1px 3px rgba(0,0,0,0.25);
        }
        .card-button:hover {
            transform: translateY(-6px) scale(1.06);
            box-shadow: 0 12px 26px rgba(0,0,0,0.35);
            filter: brightness(1.07);
        }

        .icon {
            font-size: 50px;
            margin-bottom: 12px;
            line-height: 1;
        }

        /* 🎨 ธีมสี */
        .light-card {
            background: linear-gradient(135deg, #f6d365, #fda085);
        }
        .light-card .icon {
            color: #ffe066;
            text-shadow: 0 0 8px #ffb700, 0 0 16px #ffcc33, 0 0 24px #ffd966;
        }
        .light-card:hover .icon i {
            text-shadow: 0 0 14px #ffd43b, 0 0 28px #ffc107, 0 0 50px #fff176;
        }

        /* 🔵 Air Control */
        .air-card {
            background: linear-gradient(135deg, #89f7fe, #66a6ff);
        }
        .air-card .icon {
            color: #e6faff;
            text-shadow: 0 0 6px #0066cc, 0 0 12px #0099ff;
        }
        .air-card:hover .icon i {
            animation: pulseAir 1.8s infinite;
        }

        .fan-card {
            background: linear-gradient(135deg, #a18cd1, #fbc2eb);
        }
        .fan-card .icon {
            color: #b366ff;
            text-shadow: 0 0 6px #8a2be2, 0 0 14px #d580ff;
        }
        .fan-card:hover .icon i {
            transform: rotate(360deg);
            transition: transform 0.8s ease;
            text-shadow: 0 0 12px #d580ff, 0 0 28px #e1b3ff;
        }

        @keyframes pulseAir {
            0%   { text-shadow: 0 0 6px #0066cc, 0 0 12px #0099ff; }
            50%  { text-shadow: 0 0 22px #00ccff, 0 0 48px #80f7ff; }
            100% { text-shadow: 0 0 6px #0066cc, 0 0 12px #0099ff; }
        }

        /* ✅ ปุ่ม Back — ขนาดใหญ่ + Gradient + Icon */
        .back {
            display: inline-block;
            margin-top: 40px;
            padding: 14px 36px;
            font-size: 1.2rem;
            font-weight: 600;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #ff6b6b, #ff3b3b);
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        .back i {
            margin-right: 8px;
        }
        .back:hover {
            background: linear-gradient(135deg, #ff8787, #ff4d4d);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body>

<div class="navbar">
    <h1>ระบบจัดการพลังงาน อาคารศรีวิศววิทยา คณะวิศวกรรมศาสตร์</h1>
    <div>
        <a href="/webcontrol/howtoweb.pdf">How to Use </a>
        <a href="about.php">About</a>
        <a href="index.php?logout=1">Logout</a>
    </div>
</div>

<img src="logo.png" alt="Logo" class="floating-logo">

<div class="container">
    <h2>Welcome <?= htmlspecialchars($_SESSION['username']) ?> 👋</h2>

    <div class="grid">
        <a href="building.php" class="card-button light-card">
            <div class="icon"><i class="fa-solid fa-lightbulb"></i></div>
            Light Control
        </a>
        <a href="/aircontrol/test/index.html" class="card-button air-card">
            <div class="icon"><i class="fa-solid fa-snowflake"></i></div>
            Air Control
        </a>
        <!--
        <a href="fan.php" class="card-button fan-card">
            <div class="icon"><i class="fa-solid fa-fan"></i></div>
            Fan Control
        </a>
        -->
    </div>

    <!-- ✅ ปุ่ม Back -->
    <p>
        <?php if ($role === 'admin'): ?>
            <a href="admin_dashboard.php" class="back">
                <i class="fa-solid fa-arrow-left"></i> กลับไปหน้าแอดมิน
            </a>
        <?php else: ?>
            <a href="user_dashboard.php" class="back">
                <i class="fa-solid fa-arrow-left"></i> กลับไปหน้าผู้ใช้
            </a>
        <?php endif; ?>
    </p>
</div>
</body>
</html>
