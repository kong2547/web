<?php
session_start();
require 'db.php';

// ✅ ตรวจสอบสิทธิ์ว่าเป็น admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// ✅ ตรวจสอบว่ามี ID ส่งมาหรือไม่
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "❌ ไม่พบ ID ที่ต้องการลบ";
    exit();
}

$user_id = (int) $_GET['id'];

// ✅ ป้องกันการลบ admin ตัวเอง
if ($_SESSION['user_id'] == $user_id) {
    echo "⚠️ ไม่สามารถลบบัญชีของตัวเองได้";
    exit();
}

// ✅ ลบผู้ใช้
$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$result = $stmt->execute([$user_id]);

if ($result) {
    header("Location: user_data.php?msg=deleted");
    exit();
} else {
    echo "❌ ไม่สามารถลบผู้ใช้ได้";
}
?>
