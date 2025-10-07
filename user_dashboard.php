<?php
session_start();
include 'db.php';

if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
    $action = basename($_SERVER['PHP_SELF']);

    $stmt = $conn->prepare("INSERT INTO logs (username, action) VALUES (?, ?)");
    $stmt->execute([$username, $action]);
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Dashboard</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            padding: 30px;
           background: url('engineer.png') no-repeat center center fixed; /* ใส่ไฟล์รูป */
            background-size: cover; /* ปรับให้เต็มหน้าจอ */
        }
        .container {
            max-width: 600px;
            margin: auto;
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            text-align: center;
        }
        h2 {
            color: #2c3e50;
        }
        p {
            font-size: 1.1rem;
        }
        .btn-link {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.2s ease;
            margin: 10px 0;
        }
        .btn-link:hover {
            background-color: #2980b9;
        }
        .logout {
            color: red;
            font-weight: bold;
            text-decoration: none;
        }
        .logout:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>สวัสดีคุณ <?= htmlspecialchars($_SESSION['fullname']) ?> 👋</h2>
    <p><strong>ชื่อผู้ใช้:</strong> <?= htmlspecialchars($_SESSION['username']) ?></p>
    <p><strong>สิทธิ์:</strong> <?= htmlspecialchars($_SESSION['role']) ?></p>

    <!-- ปุ่มไปยังหน้าควบคุม -->
    <p><a href="index.php" class="btn-link">ไปยังหน้าควบคุมอุปกรณ์</a></p>

    <!-- ปุ่ม logout -->
    <p><a href="logout.php" class="logout">ออกจากระบบ</a></p>
</div>
</body>
</html>