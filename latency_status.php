<?php
// latency_status.php
session_start();
require 'db.php';

if (!isset($_SESSION['username'])) {
    header('location: login.php');
    exit();
}

// รับค่าการกรอง
$floor_filter = $_GET['floor'] ?? '';
$room_filter = $_GET['room'] ?? '';
$date_range = $_GET['date_range'] ?? '7d';
$action_type = $_GET['action_type'] ?? 'all';

// คำนวณวันที่
switch($date_range) {
    case '1d': $date_condition = "DATE(l.created_at) = CURDATE()"; break;
    case '3d': $date_condition = "l.created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)"; break;
    case '7d': $date_condition = "l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"; break;
    case '30d': $date_condition = "l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"; break;
    default: $date_condition = "l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
}

// สร้างเงื่อนไข WHERE
$where_conditions = [$date_condition];
$params = [];

if (!empty($floor_filter)) {
    $where_conditions[] = "r.floor = ?";
    $params[] = $floor_filter;
}

if (!empty($room_filter)) {
    $where_conditions[] = "r.room_name LIKE ?";
    $params[] = "%$room_filter%";
}

if ($action_type !== 'all') {
    $where_conditions[] = "l.action_type = ?";
    $params[] = $action_type;
}

$where_clause = implode(" AND ", $where_conditions);

// ดึงสถิติ latency
$sql = "
    SELECT 
        r.floor,
        r.room_name,
        s.switch_name,
        s.esp_name,
        l.action_type,
        AVG(l.full_latency_ms) AS avg_latency,
        MIN(l.full_latency_ms) AS min_latency,
        MAX(l.full_latency_ms) AS max_latency,
        COUNT(*) AS test_count,
        DATE(l.created_at) AS test_date,
        -- คำนวณประสิทธิภาพ
        CASE 
            WHEN AVG(l.full_latency_ms) < 500 THEN 'ดีมาก'
            WHEN AVG(l.full_latency_ms) < 1000 THEN 'ปานกลาง'
            ELSE 'ต้องปรับปรุง'
        END AS performance
    FROM latency_logs l
    JOIN switches s ON l.switch_id = s.id
    JOIN rooms r ON l.room_id = r.id
    WHERE $where_clause
    GROUP BY r.floor, r.room_name, s.switch_name, l.action_type, DATE(l.created_at)
    ORDER BY r.floor ASC, r.room_name ASC, test_date DESC, s.switch_name ASC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$stats = $stmt->fetchAll();

// ดึงข้อมูลสำหรับ dropdown
$floors = $conn->query("SELECT DISTINCT floor FROM rooms ORDER BY floor")->fetchAll();
$rooms = $conn->query("SELECT DISTINCT room_name FROM rooms ORDER BY room_name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>📊 สถิติ Latency</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card { border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .table thead th { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        
        .latency-excellent { background: linear-gradient(135deg, #4CAF50, #45a049); color: white; }
        .latency-good { background: linear-gradient(135deg, #8BC34A, #7CB342); color: white; }
        .latency-warning { background: linear-gradient(135deg, #FFC107, #FFA000); color: black; }
        .latency-poor { background: linear-gradient(135deg, #F44336, #D32F2F); color: white; }
        
        .performance-badge { 
            border-radius: 20px; 
            padding: 4px 12px;
            font-size: 0.85em;
            font-weight: 500;
        }
        
        .filter-section { 
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .floor-badge {
            background: linear-gradient(135deg, #FF6B6B, #FF8E53);
            color: white;
            font-size: 1.1em;
            padding: 8px 15px;
            border-radius: 25px;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-1"><i class="fas fa-tachometer-alt me-2"></i>สถิติประสิทธิภาพ Latency</h1>
                        <p class="text-muted mb-0">วิเคราะห์ความเร็วในการควบคุมสวิตช์ทั่วทั้งอาคาร</p>
                    </div>
                    <a href="admin_dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>กลับหน้าหลัก
                    </a>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="filter-section">
                    <h5 class="mb-3"><i class="fas fa-filter me-2"></i>ตัวกรองข้อมูล</h5>
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">เลือกชั้น</label>
                            <select name="floor" class="form-select">
                                <option value="">ทั้งหมด</option>
                                <?php foreach($floors as $floor): ?>
                                    <option value="<?= $floor['floor'] ?>" <?= $floor_filter == $floor['floor'] ? 'selected' : '' ?>>
                                        ชั้น <?= htmlspecialchars($floor['floor']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">ค้นหาห้อง</label>
                            <select name="room" class="form-select">
                                <option value="">ทั้งหมด</option>
                                <?php foreach($rooms as $room): ?>
                                    <option value="<?= $room['room_name'] ?>" <?= $room_filter == $room['room_name'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($room['room_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">ช่วงเวลา</label>
                            <select name="date_range" class="form-select">
                                <option value="1d" <?= $date_range == '1d' ? 'selected' : '' ?>>1 วัน</option>
                                <option value="3d" <?= $date_range == '3d' ? 'selected' : '' ?>>3 วัน</option>
                                <option value="7d" <?= $date_range == '7d' ? 'selected' : '' ?>>7 วัน</option>
                                <option value="30d" <?= $date_range == '30d' ? 'selected' : '' ?>>30 วัน</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">ประเภท</label>
                            <select name="action_type" class="form-select">
                                <option value="all" <?= $action_type == 'all' ? 'selected' : '' ?>>ทั้งหมด</option>
                                <option value="เปิด" <?= $action_type == 'เปิด' ? 'selected' : '' ?>>เปิด</option>
                                <option value="ปิด" <?= $action_type == 'ปิด' ? 'selected' : '' ?>>ปิด</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i>แสดงผล
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <i class="fas fa-bolt fa-2x mb-2"></i>
                    <h4 class="mb-1"><?= number_format(count($stats)) ?></h4>
                    <p class="mb-0">รายการทั้งหมด</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center" style="background: linear-gradient(135deg, #4CAF50, #45a049);">
                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                    <h4 class="mb-1">
                        <?= number_format(count(array_filter($stats, fn($s) => $s['avg_latency'] < 500))) ?>
                    </h4>
                    <p class="mb-0">ประสิทธิภาพดีมาก</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center" style="background: linear-gradient(135deg, #FFC107, #FFA000);">
                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                    <h4 class="mb-1">
                        <?= number_format(count(array_filter($stats, fn($s) => $s['avg_latency'] >= 500 && $s['avg_latency'] < 1000))) ?>
                    </h4>
                    <p class="mb-0">ปานกลาง</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center" style="background: linear-gradient(135deg, #F44336, #D32F2F);">
                    <i class="fas fa-times-circle fa-2x mb-2"></i>
                    <h4 class="mb-1">
                        <?= number_format(count(array_filter($stats, fn($s) => $s['avg_latency'] >= 1000))) ?>
                    </h4>
                    <p class="mb-0">ต้องปรับปรุง</p>
                </div>
            </div>
        </div>

        <!-- Main Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-table me-2"></i>
                            รายละเอียดสถิติ Latency
                            <small class="text-muted ms-2">(เรียงตามชั้นและห้อง)</small>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($stats)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th width="8%">ชั้น</th>
                                            <th width="15%">ห้อง</th>
                                            <th width="15%">สวิตช์</th>
                                            <th width="10%">ESP</th>
                                            <th width="8%">ประเภท</th>
                                            <th width="12%">วันที่</th>
                                            <th width="12%">ค่าเฉลี่ย</th>
                                            <th width="10%">ต่ำสุด</th>
                                            <th width="10%">สูงสุด</th>
                                            <th width="10%">จำนวน</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $current_floor = null;
                                        foreach ($stats as $index => $stat): 
                                            if ($current_floor !== $stat['floor']):
                                                $current_floor = $stat['floor'];
                                        ?>
                                        <tr class="table-secondary">
                                            <td colspan="10" class="text-center py-3">
                                                <span class="floor-badge">
                                                    <i class="fas fa-building me-2"></i>ชั้น <?= htmlspecialchars($stat['floor']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        
                                        <tr>
                                            <td class="text-center fw-bold"><?= htmlspecialchars($stat['floor']) ?></td>
                                            <td>
                                                <i class="fas fa-door-open me-2 text-primary"></i>
                                                <?= htmlspecialchars($stat['room_name']) ?>
                                            </td>
                                            <td>
                                                <i class="fas fa-plug me-2 text-success"></i>
                                                <?= htmlspecialchars($stat['switch_name']) ?>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?= htmlspecialchars($stat['esp_name']) ?></small>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($stat['action_type'] === 'เปิด'): ?>
                                                    <span class="badge bg-success">🔛 เปิด</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">🔜 ปิด</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <small><?= date('d/m/Y', strtotime($stat['test_date'])) ?></small>
                                            </td>
                                            <td class="text-center">
                                                <?php
                                                $latency_class = '';
                                                if ($stat['avg_latency'] < 500) $latency_class = 'latency-excellent';
                                                elseif ($stat['avg_latency'] < 1000) $latency_class = 'latency-good';
                                                elseif ($stat['avg_latency'] < 1500) $latency_class = 'latency-warning';
                                                else $latency_class = 'latency-poor';
                                                ?>
                                                <span class="badge performance-badge <?= $latency_class ?>">
                                                    <?= number_format($stat['avg_latency'], 0) ?> ms
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <small><?= number_format($stat['min_latency'], 0) ?> ms</small>
                                            </td>
                                            <td class="text-center">
                                                <small><?= number_format($stat['max_latency'], 0) ?> ms</small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-info"><?= $stat['test_count'] ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">ไม่พบข้อมูล</h5>
                                <p class="text-muted">ลองเปลี่ยนตัวกรองหรือช่วงเวลาดูครับ</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h6><i class="fas fa-info-circle me-2"></i>คำอธิบายสี</h6>
                        <div class="row text-center">
                            <div class="col-md-3">
                                <span class="badge performance-badge latency-excellent">น้อยกว่า 500ms</span>
                                <small class="d-block text-muted mt-1">ดีมาก ⭐⭐⭐⭐⭐</small>
                            </div>
                            <div class="col-md-3">
                                <span class="badge performance-badge latency-good">500-1000ms</span>
                                <small class="d-block text-muted mt-1">ดี ⭐⭐⭐⭐</small>
                            </div>
                            <div class="col-md-3">
                                <span class="badge performance-badge latency-warning">1000-1500ms</span>
                                <small class="d-block text-muted mt-1">ปานกลาง ⭐⭐⭐</small>
                            </div>
                            <div class="col-md-3">
                                <span class="badge performance-badge latency-poor">มากกว่า 1500ms</span>
                                <small class="d-block text-muted mt-1">ต้องปรับปรุง ⭐⭐</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>