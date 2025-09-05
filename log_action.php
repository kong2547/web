<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';

if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
    $action = basename(parse_url($_SERVER['PHP_SELF'], PHP_URL_PATH)); // ชื่อไฟล์ปัจจุบัน เช่น dashboard.php

    try {
        $stmt = $conn->prepare("INSERT INTO logs (username, action) VALUES (:username, :action)");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':action', $action);
        $stmt->execute();
    } catch(PDOException $e) {
        error_log("Error inserting log: " . $e->getMessage());
    }
}
