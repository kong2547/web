<?php
/**
 * 📘 auto_log.php
 * -------------------------
 * ใช้ในหน้าเว็บ เช่น index.php, ipboard.php, plan.php
 * เพื่อเก็บ Log การเข้าใช้งานอัตโนมัติ
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'db.php'; // ✅ ใช้ path เดียวกับไฟล์จริง เช่นอยู่ใน /webcontrol/web/

if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
    $action = basename(parse_url($_SERVER['PHP_SELF'], PHP_URL_PATH)); // เช่น index.php, plan.php

    try {
        $stmt = $conn->prepare("INSERT INTO logs (username, action) VALUES (:username, :action)");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':action', $action);
        $stmt->execute();
    } catch (PDOException $e) {
        error_log("⚠️ Error inserting log: " . $e->getMessage());
    }
}
?>
