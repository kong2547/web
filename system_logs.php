<?php
session_start();
include 'db.php';

// ตรวจสอบการเข้าถึง (ขอแค่ login ก็พอ)
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

// ดึง logs จากฐานข้อมูล โดยแสดงแค่รายชื่อผู้ใช้แบบไม่ซ้ำกัน
// พร้อมแสดงการกระทำทั้งหมดและเวลาล่าสุดที่ทำ
$stmt = $conn->query("
    SELECT 
        l.username,
        GROUP_CONCAT(DISTINCT l.action ORDER BY l.created_at DESC SEPARATOR ', ') AS actions,
        MAX(l.created_at) AS last_activity
    FROM 
        logs l
    INNER JOIN users u ON l.username = u.username   -- ดึงเฉพาะที่มีใน users
    GROUP BY 
        l.username
    ORDER BY 
        last_activity DESC
    LIMIT 50
");

$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Kanit', sans-serif; 
            margin: 0;
            padding: 20px;
            background-color: #f4f7f9;
        }
        .container {
            max-width: 900px;
            margin: auto;
            background-color: #ffffff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        h2 { 
            text-align: center;
            color: #2c3e50;
            margin-bottom: 25px;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px;
        }
        th, td { 
            border: 1px solid #e0e6ed; 
            padding: 12px; 
            text-align: left;
        }
        th { 
            background-color: #34495e; 
            color: white; 
            text-transform: uppercase;
            font-size: 14px;
        }
        tr:nth-child(even) { 
            background-color: #f9f9f9; 
        }
        .back-button {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .back-button:hover {
            background-color: #2980b9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>📜 System Logs (สรุปการเข้าใช้งาน)</h2>
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Actions</th>
                    <th>Last Activity</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="3" style="text-align: center;">ไม่มีข้อมูล Log</td>
                </tr>
                <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= htmlspecialchars($log['username']) ?></td>
                    <td><?= htmlspecialchars($log['actions']) ?></td>
                    <td><?= htmlspecialchars($log['last_activity']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <a href="admin_dashboard.php" class="back-button">กลับสู่หน้าหลัก</a>
    </div>
</body>
</html>