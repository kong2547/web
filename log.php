<?php
session_start();
include 'db.php';

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// ดึง activity ล่าสุดของแต่ละ user
$sql = "SELECT l1.username, l1.action, l1.created_at 
        FROM logs l1
        INNER JOIN (
            SELECT username, MAX(created_at) AS max_time
            FROM logs
            GROUP BY username
        ) l2 ON l1.username = l2.username AND l1.created_at = l2.max_time
        ORDER BY l1.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute();
$logs = $stmt->fetchAll();

echo "<table border='1' cellpadding='8'>
        <tr>
            <th>Username</th>
            <th>กิจกรรมล่าสุด</th>
            <th>เวลา</th>
            <th>สถานะ</th>
        </tr>";

foreach ($logs as $row) {
    $last = strtotime($row['created_at']);
    $isOnline = ($row['action'] !== 'logout' && (time() - $last <= 60)); 

    echo "<tr>
            <td>{$row['username']}</td>
            <td>{$row['action']}</td>
            <td>{$row['created_at']}</td>
            <td>" . ($isOnline ? "<span style='color:green;'>ออนไลน์</span>" : "<span style='color:red;'>ออฟไลน์</span>") . "</td>
          </tr>";
}

echo "</table>";
?>