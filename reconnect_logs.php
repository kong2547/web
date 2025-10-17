<?php
session_start();
require 'db.php';
date_default_timezone_set("Asia/Bangkok");

// ==============================
// 🔍 รับค่ากรองจากฟอร์ม
// ==============================
$filter_date = $_GET['date'] ?? '';
$filter_esp  = $_GET['esp'] ?? '';
$filter_ip   = $_GET['ip'] ?? '';

// ==============================
// 📋 สร้าง Query ดึงข้อมูล
// ==============================
$query = "SELECT * FROM esp_logs WHERE 1=1";
$params = [];

if ($filter_date) {
    $query .= " AND log_date = ?";
    $params[] = $filter_date;
}
if ($filter_esp) {
    $query .= " AND esp_name LIKE ?";
    $params[] = "%$filter_esp%";
}
if ($filter_ip) {
    $query .= " AND ip_address LIKE ?";
    $params[] = "%$filter_ip%";
}

// ✅ เรียงจากข้อมูลเก่า → ใหม่ (ASC)
$query .= " ORDER BY log_date ASC, log_time ASC, id ASC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>ESP32 Reconnect Logs</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
  body { background-color: #f9fafb; }
  .container {
    max-width: 1100px;
    background: white;
    padding: 25px 30px;
    margin-top: 30px;
    border-radius: 14px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
  }
  h4 { font-weight: 600; }
  table { font-size: 15px; }
  thead th {
    background-color: #007bff;
    color: white;
    text-align: center;
    vertical-align: middle;
  }
  tbody td {
    vertical-align: middle;
  }
  .form-label {
    font-weight: 500;
  }
  .btn-primary {
    background-color: #007bff;
    border: none;
  }
  .btn-primary:hover {
    background-color: #0069d9;
  }
  .back-btn {
    text-decoration: none;
    font-weight: 500;
  }
  .footer-btn {
    border-radius: 8px;
  }
</style>
</head>
<body>

<div class="container">
  <!-- 🔙 ปุ่มย้อนกลับ -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <a href="ipboard.php" class="btn btn-outline-secondary back-btn">
      <i class="bi bi-arrow-left-circle"></i> ย้อนกลับไปยังหน้า BOARD 
    </a>
    <h4 class="text-primary m-0">
      <i class="bi bi-router"></i> ESP32 Reconnect Logs
    </h4>
  </div>

  <!-- 🔎 ฟอร์มค้นหา -->
  <form method="get" class="row g-3 mb-4">
    <div class="col-md-3">
      <label class="form-label">วันที่</label>
      <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>" class="form-control">
    </div>
    <div class="col-md-3">
      <label class="form-label">ชื่อ ESP</label>
      <input type="text" name="esp" value="<?= htmlspecialchars($filter_esp) ?>" placeholder="เช่น ESP32_001" class="form-control">
    </div>
    <div class="col-md-3">
      <label class="form-label">IP Address</label>
      <input type="text" name="ip" value="<?= htmlspecialchars($filter_ip) ?>" placeholder="เช่น 172.26." class="form-control">
    </div>
    <div class="col-md-3 d-flex align-items-end">
      <button type="submit" class="btn btn-primary w-100">
        <i class="bi bi-search"></i> ค้นหา
      </button>
    </div>
  </form>

  <!-- 📊 ตารางแสดงข้อมูล -->
  <div class="table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead>
        <tr>
          <th width="5%">#</th>
          <th>ชื่อ ESP</th>
          <th>IP Address</th>
          <th>วันที่</th>
          <th>เวลา</th>
          <th>บันทึกเมื่อ</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($logs) > 0): ?>
          <?php foreach ($logs as $i => $row): ?>
            <tr>
              <td class="text-center fw-semibold"><?= $i + 1 ?></td>
              <td class="text-primary fw-semibold"><?= htmlspecialchars($row['esp_name']) ?></td>
              <td><?= htmlspecialchars($row['ip_address']) ?></td>
              <td class="text-center"><?= htmlspecialchars($row['log_date']) ?></td>
              <td class="text-center"><?= htmlspecialchars($row['log_time']) ?></td>
              <td class="text-center text-muted"><?= htmlspecialchars($row['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="6" class="text-center text-muted py-3">
              <i class="bi bi-exclamation-circle"></i> ไม่มีข้อมูลในช่วงที่ค้นหา
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- ⚙️ ปุ่มล้างตัวกรอง -->
  <div class="d-flex justify-content-end mt-3">
    <a href="reconnect_logs.php" class="btn btn-secondary btn-sm footer-btn">
      <i class="bi bi-x-circle"></i> ล้างตัวกรอง
    </a>
  </div>
</div>

</body>
</html>
