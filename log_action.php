<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';

if (!function_exists('log_action')) {
    function log_action($conn, $username, $action) {
        try {
            $stmt = $conn->prepare("INSERT INTO logs (username, action) VALUES (:username, :action)");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':action', $action);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("❌ Error inserting log: " . $e->getMessage());
        }
    }
}
