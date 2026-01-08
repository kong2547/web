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
<html lang="th"> 
<head> 
    <meta charset="UTF-8"> 
    <title>User Dashboard</title> 
    <!-- Google Font --> 
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet"> 
    <!-- Font Awesome --> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"> 

    <style> 
        body { 
            font-family: 'Kanit', sans-serif; 
            margin: 0; 
            padding: 0; 
            height: 100vh; 
            background: url('engineer.png') no-repeat center center fixed; 
            background-size: cover; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
        } 

        .logo { 
            position: fixed; 
            top: 20px; 
            left: 20px; 
            width: 120px; 
            height: auto; 
            z-index: 1000; 
        } 

        .container { 
            max-width: 600px; 
            width: 100%; 
            background: rgba(255, 255, 255, 0.85); 
            padding: 2.5rem; 
            border-radius: 20px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.35); 
            text-align: center; 
            border: 1px solid rgba(255, 255, 255, 0.6); 
            animation: fadeIn 1s ease-in-out; 
        } 

        h2 { 
            color: #1e1e1e; 
            margin-bottom: 15px; 
            font-weight: 600; 
            font-size: 1.8rem; 
        } 

        p { 
            font-size: 1.1rem; 
            color: #2c2c2c; 
            margin: 12px 0;       
            line-height: 1.8;     
        }

        /* เดิม: ปรับระยะระหว่าง label และค่า */
        .container p strong {
            display: inline-block;
            width: 95px;          
            text-align: right;    
            margin-right: 8px;    
        }

       .user-info {
    text-align: center;
    display: inline-block;
    transform: translateX(-30px); /* ✅ ขยับไปทางซ้าย 30px */
}

        .btn-link { 
            display: inline-block; 
            background: linear-gradient(135deg, #4facfe, #00f2fe); 
            color: white; 
            padding: 14px 28px; 
            border-radius: 40px; 
            text-decoration: none; 
            font-weight: bold; 
            transition: all 0.35s ease; 
            margin: 15px 0; 
            box-shadow: 0 6px 15px rgba(0,0,0,0.3); 
        } 

        .btn-link:hover { 
            background: linear-gradient(135deg, #43e97b, #38f9d7); 
            transform: scale(1.05); 
            box-shadow: 0 10px 20px rgba(0,0,0,0.35); 
        } 

        .logout { 
            display: inline-block; 
            margin-top: 15px; 
            color: #ff6b6b; 
            font-weight: bold; 
            text-decoration: none; 
            transition: 0.3s; 
        } 

        .logout:hover { 
            color: #ff3b3b; 
            text-decoration: underline; 
        } 

        @keyframes fadeIn { 
            from { opacity: 0; transform: translateY(25px); } 
            to { opacity: 1; transform: translateY(0); } 
        } 
    </style> 
</head> 

<body> 
    <img src="logo.png" alt="RUTS Logo" class="logo"> 

    <div class="container"> 
        <h2>สวัสดีคุณ <?= htmlspecialchars($_SESSION['fullname'] ?? 'ผู้ใช้งาน') ?> 👋</h2> 

        <!-- ✅ ครอบเฉพาะส่วนชื่อผู้ใช้/สิทธิ์ -->
        <div class="user-info">
            <p><strong>ชื่อผู้ใช้ :</strong> <?= htmlspecialchars($_SESSION['username']) ?></p> 
            <p><strong>สิทธิ์ :</strong> <?= htmlspecialchars($_SESSION['role']) ?></p> 
        </div>

        <p><a href="index.php" class="btn-link"><i class="fas fa-bolt"></i> ไปยังหน้าควบคุมอุปกรณ์</a></p> 
        <p><a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a></p> 
    </div> 
</body> 
</html>
