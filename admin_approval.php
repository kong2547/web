<?php
session_start();
include 'db.php';

// ตรวจสอบสิทธิ์ admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// อนุมัติหรือปฏิเสธ
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($_GET['action'] == 'approve') {
        $stmt = $conn->prepare("UPDATE users SET status='active' WHERE id=?");
        $stmt->execute([$id]);
    } elseif ($_GET['action'] == 'reject') {
        $stmt = $conn->prepare("UPDATE users SET status='rejected' WHERE id=?");
        $stmt->execute([$id]);
    }
    header("Location: admin_approval.php");
    exit();
}

// ดึงผู้ใช้ทั้งหมด (เรียงตาม status และ id)
$stmt = $conn->query("SELECT * FROM users ORDER BY id ASC");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Admin - อนุมัติผู้ใช้</title>
    <style>
        body {font-family: Arial, sans-serif; background:#f9f9f9; padding:20px;}
        .container {max-width:900px; margin:auto; background:#fff; padding:20px; border-radius:10px; box-shadow:0 0 10px rgba(0,0,0,0.1);}
        h2 {color:#2c3e50; margin-bottom:20px;}
        table {width:100%; border-collapse:collapse;}
        th, td {border:1px solid #ddd; padding:10px; text-align:center;}
        th {background:#3498db; color:white;}
        tr:nth-child(even){background:#f2f2f2;}
        a {text-decoration:none; font-weight:bold;}
        .approve {color:green;}
        .reject {color:red;}
        .back {display:inline-block; margin-top:15px; padding:10px 15px; background:#3498db; color:white; border-radius:6px;}
        .back:hover {background:#2980b9;}
    </style>
</head>
<body>
<div class="container">
    <h2>👥 จัดการผู้ใช้</h2>

    <?php if (count($users) > 0): ?>
        <table>
            <tr>
                <th>ID</th>
                <th>อีเมล</th>
                <th>ชื่อผู้ใช้</th>
                <th>สิทธิ์</th>
                <th>สถานะ</th>
                <th>การดำเนินการ</th>
            </tr>
            <?php foreach ($users as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['id']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= htmlspecialchars($row['username']) ?></td>
                    <td><?= htmlspecialchars($row['role']) ?></td>
                    <td>
                        <?php if ($row['status'] == 'pending'): ?>
                            ⏳ รออนุมัติ
                        <?php elseif ($row['status'] == 'active'): ?>
                            ✅ ใช้งานได้
                        <?php else: ?>
                            ❌ ถูกปฏิเสธ
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row['status'] == 'pending'): ?>
                            <a href="admin_approval.php?action=approve&id=<?= $row['id'] ?>" class="approve" onclick="return confirm('ยืนยันการอนุมัติ?')">อนุมัติ</a> |
                            <a href="admin_approval.php?action=reject&id=<?= $row['id'] ?>" class="reject" onclick="return confirm('ยืนยันการปฏิเสธ?')">ปฏิเสธ</a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>ยังไม่มีผู้ใช้</p>
    <?php endif; ?>

    <a href="admin_dashboard.php" class="back">← กลับหน้าแดชบอร์ด</a>
</div>
</body>
</html>