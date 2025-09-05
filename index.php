<?php
session_start();


include 'db.php';

include 'log_action.php';

if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
    $action = basename($_SERVER['PHP_SELF']); // บันทึกชื่อไฟล์ที่เข้าใช้งาน

    // ใช้ PDO อย่างถูกต้อง
    try {
        $stmt = $conn->prepare("INSERT INTO logs (username, action) VALUES (:username, :action)");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':action', $action);
        $stmt->execute();
    } catch(PDOException $e) {
        // ไม่ต้องแสดง error เพื่อไม่ให้影響การทำงานหลัก
        error_log("Error inserting log: " . $e->getMessage());
    }
}


include 'db.php';

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
    // บันทึกการ logout
    $stmt = $conn->prepare("INSERT INTO logs (username, action) VALUES (?, 'logout')");
    $stmt->execute([$username]);
    
    session_destroy();
    header('location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ระบบควบคุมตึก 10 ชั้น</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            padding: 30px;
            background: url('building.webp') no-repeat center center fixed;
            background-size: cover;
        }
        .container {
            max-width: 960px;
            margin: auto;
            text-align: center;
        }
        h2 {
            margin-bottom: 30px;
            color: #333;
        }
        .grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
        }
        .card-button {
            width: 180px;
            height: 120px;
            background-color: #06a769;
            color: white;
            border-radius: 12px;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: bold;
            transition: 0.3s;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .card-button:hover {
            background-color: #03995d;
            transform: translateY(-2px);
        }
        .icon {
            font-size: 28px;
            margin-bottom: 8px;
        }
        .logout, .back {
            margin-top: 30px;
            display: inline-block;
            font-weight: bold;
            text-decoration: none;
        }
        .logout { color: red; }
        .logout:hover, .back:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="container">
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> 👋</h2>

    <div class="grid">
        <a href="light.php" class="card-button">
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
        <a href="change_theme.php" class="card-button">
            <div class="icon">⚙️</div>
            Settings
        </a>
        
        </a>
    </div>

    <p>
        <a href="index.php?logout=1" class="logout">Logout</a> |
        <a href="user_dashboard.php?back=1" class="back">Back</a>
    </p>
</div>
</body>
</html>