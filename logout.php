<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';

if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
    $action = "logout";

    // บันทึก log ลงฐานข้อมูล
    $stmt = $conn->prepare("INSERT INTO logs (username, action) VALUES (?, ?)");
    $stmt->execute([$username, $action]);
}

// ทำลาย session และ redirect ออกจากระบบ
session_destroy();
header("Location: login.php");
exit();
?>