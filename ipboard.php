<?php
session_start();
require 'db.php';
date_default_timezone_set("Asia/Bangkok");


include 'log_action.php';



$username = $_SESSION['username'];

// ✅ เก็บ Log การเข้าใช้งาน
$action = basename($_SERVER['PHP_SELF']);
$stmt = $conn->prepare("INSERT INTO logs (username, action) VALUES (?, ?)");
$stmt->execute([$username, $action]);

// ✅ Logout
if (isset($_GET['logout'])) {
    $stmt = $conn->prepare("INSERT INTO logs (username, action) VALUES (?, 'logout')");
    $stmt->execute([$username]);
    session_destroy();
    header('location: login.php');
    exit();
}

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// ✅ ดึงข้อมูล ESP ทั้งหมด (รวมชั้น/ห้อง) และเรียงให้ ESP32_001 อยู่บนสุด
$stmt = $conn->query("
    SELECT 
        s.esp_name,
        MAX(s.ip_address) AS ip_address,
        MAX(s.gateway) AS gateway,
        MAX(s.subnet) AS subnet,
        MAX(s.network_mode) AS mode,
        MAX(s.last_seen) AS last_seen,
        r.room_name,
        r.floor
    FROM switches s
    LEFT JOIN rooms r ON s.room_id = r.id
    GROUP BY s.esp_name
    ORDER BY 
        CASE WHEN s.esp_name = 'ESP32_001' THEN 0 ELSE 1 END, 
        r.floor, r.room_name
");
$rows = $stmt->fetchAll();

// ✅ นับจำนวนบอร์ดทั้งหมด และบอร์ดที่ออนไลน์
$totalBoards = count($rows);
$onlineBoards = 0;
foreach ($rows as $r) {
    if ((time() - strtotime($r['last_seen'])) < 30) {
        $onlineBoards++;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>📡 IP Board - สถานะ ESP32 ทั้งหมด</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
    font-family: "Segoe UI", Tahoma, sans-serif;
    background-color: #f8f9fa;
}
.table th, .table td {
    vertical-align: middle;
}
.online { color: #28a745; font-weight: bold; }
.offline { color: #dc3545; font-weight: bold; }

/* Navbar เหมือน plan.php */
.navbar {
    background: #343a40;
    color: #fff;
    padding: 10px 20px;
    display: flex;
    align-items: center;
}
.navbar a {
    color: #fff;
    text-decoration: none;
    margin-right: 15px;
    font-weight: bold;
}
.navbar a:hover {
    text-decoration: underline;
}
.navbar a.active,
.navbar a.active:hover {
    color: #ffc107 !important;
}
.summary-box {
    background: #fff;
    border-left: 6px solid #007bff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    border-radius: 8px;
    padding: 15px 20px;
    margin-bottom: 20px;
}
</style>
</head>
<body>

<!-- Navbar -->
<div class="navbar">
    <a href="index.php">🏠 หน้าแรก</a>
    <a href="building.php">🏢 อาคาร</a>
    <a href="plan.php">📐 แผนผัง</a>
    <a href="ipboard.php" class="active">📡 BOARD</a>
    <a href="logout.php" style="margin-left:auto; color:#ffc107;">🚪 ออกจากระบบ</a>
</div>

<div class="container py-4">
  <h3 class="mb-4 text-primary">📡 ตารางสถานะบอร์ด ESP32</h3>

  <!-- ✅ กล่องสรุปสถานะ -->
  <div class="summary-box">
      <h5 class="mb-1">📊 สรุปสถานะระบบ</h5>
      <p class="mb-0">
        ✅ บอร์ดทั้งหมด: <strong class="text-primary"><?= $totalBoards ?></strong> ตัว |
        🟢 ออนไลน์: <strong class="text-success"><?= $onlineBoards ?></strong> ตัว |
        🔴 ออฟไลน์: <strong class="text-danger"><?= $totalBoards - $onlineBoards ?></strong> ตัว
      </p>
  </div>

  <div class="table-responsive">
  <table class="table table-bordered table-striped table-hover align-middle">
    <thead class="table-dark text-center">
      <tr>
        <th>ESP Name</th>
        <th>IP Address</th>
        <th>Gateway</th>
        <th>Subnet</th>
        <th>Mode</th>
        <th>ชั้น</th>
        <th>ห้อง</th>
        <th>Last Seen</th>
        <th>สถานะ</th>
      </tr>
    </thead>
    <tbody class="text-center">
      <?php foreach ($rows as $r): 
        $isOnline = (time() - strtotime($r['last_seen'])) < 30;
      ?>
      <tr>
        <td><strong><?= htmlspecialchars($r['esp_name']) ?></strong></td>
        <td><?= htmlspecialchars($r['ip_address']) ?></td>
        <td><?= htmlspecialchars($r['gateway']) ?></td>
        <td><?= htmlspecialchars($r['subnet']) ?></td>
        <td><?= strtoupper($r['mode'] ?: '-') ?></td>
        <td><?= htmlspecialchars($r['floor']) ?></td>
        <td><?= htmlspecialchars($r['room_name']) ?></td>
        <td><?= htmlspecialchars($r['last_seen']) ?></td>
        <td class="<?= $isOnline ? 'online' : 'offline' ?>">
          <?= $isOnline ? '🟢 Online' : '🔴 Offline' ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
