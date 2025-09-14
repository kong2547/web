<?php
// room.php
session_start();
require 'db.php';

if (!isset($_GET['id'])) {
    die("ไม่พบห้อง");
}
$room_id = (int)$_GET['id'];

// ตรวจสอบ login (ถ้าคุณต้องการ)
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
$username = $_SESSION['username'];

/* ----------------- ดึงข้อมูลห้อง ----------------- */
$stmt = $conn->prepare("SELECT * FROM rooms WHERE id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch();
if (!$room) die("ห้องไม่ถูกต้อง");

/* ----------------- Toggle switch ผ่าน GET (UI) ----------------- */
if (isset($_GET['toggle'])) {
    $sid = (int)$_GET['toggle'];
    // ดึงสถานะปัจจุบัน
    $q = $conn->prepare("SELECT status FROM switches WHERE id=? AND room_id=?");
    $q->execute([$sid, $room_id]);
    $sw = $q->fetch();
    if ($sw) {
        $new = ($sw['status'] === 'on') ? 'off' : 'on';
        $u = $conn->prepare("UPDATE switches SET status=? WHERE id=?");
        $u->execute([$new, $sid]);
        // log
        $log = $conn->prepare("INSERT INTO switch_logs (switch_id, room_id, status) VALUES (?, ?, ?)");
        $log->execute([$sid, $room_id, $new]);
    }
    header("Location: room.php?id=$room_id");
    exit();
}

/* ----------------- เพิ่มสวิตช์ (จากฟอร์ม) ----------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_switch'])) {
    $name = trim($_POST['switch_name']);
    $board = trim($_POST['board']);
    $gpio = (int)$_POST['gpio_pin'];
    $ins = $conn->prepare("INSERT INTO switches (room_id, switch_name, board, gpio_pin, status) VALUES (?, ?, ?, ?, 'off')");
    $ins->execute([$room_id, $name, $board, $gpio]);
    header("Location: room.php?id=$room_id");
    exit();
}

/* ----------------- ลบสวิตช์ ----------------- */
if (isset($_GET['delete'])) {
    $sid = (int)$_GET['delete'];
    $d = $conn->prepare("DELETE FROM switches WHERE id=? AND room_id=?");
    $d->execute([$sid, $room_id]);
    header("Location: room.php?id=$room_id");
    exit();
}

/* ----------------- ดึงสวิตช์ และ logs ----------------- */
$stmt = $conn->prepare("SELECT * FROM switches WHERE room_id = ? ORDER BY id ASC");
$stmt->execute([$room_id]);
$switches = $stmt->fetchAll();

/* logs เพื่อกราฟ (24h / 7d / 30d / custom) */
$range = $_GET['range'] ?? '1d';
$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;

if ($range === '7d') {
    $stmt = $conn->prepare("SELECT switch_id, status, created_at FROM switch_logs WHERE room_id=? AND created_at >= NOW() - INTERVAL 7 DAY ORDER BY created_at ASC");
    $stmt->execute([$room_id]);
} elseif ($range === '30d') {
    $stmt = $conn->prepare("SELECT switch_id, status, created_at FROM switch_logs WHERE room_id=? AND created_at >= NOW() - INTERVAL 30 DAY ORDER BY created_at ASC");
    $stmt->execute([$room_id]);
} elseif ($range === 'custom' && $start && $end) {
    $stmt = $conn->prepare("SELECT switch_id, status, created_at FROM switch_logs WHERE room_id=? AND created_at BETWEEN ? AND ? ORDER BY created_at ASC");
    $stmt->execute([$room_id, $start.' 00:00:00', $end.' 23:59:59']);
} else {
    $stmt = $conn->prepare("SELECT switch_id, status, created_at FROM switch_logs WHERE room_id=? AND created_at >= NOW() - INTERVAL 1 DAY ORDER BY created_at ASC");
    $stmt->execute([$room_id]);
}
$logs = $stmt->fetchAll();

/* ----------------- SCHEDULE: เพิ่ม / แสดง / ลบ / toggle (ย่อ ๆ) ----------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_schedule'])) {
    // รับค่าจากฟอร์ม
    $device_id = $_POST['device_id']; // จะเป็น switch_X
    $mode = $_POST['mode'] === 'off' ? 'off' : 'on';
    $weekdays = isset($_POST['weekdays']) ? implode(',', $_POST['weekdays']) : null;
    $start_date = $_POST['start_date'] ?: null;
    $end_date   = $_POST['end_date'] ?: null;
    $start_time = $_POST['start_time'] ?: null;
    $end_time   = $_POST['end_time'] ?: null;
    $enabled    = isset($_POST['enabled']) ? 1 : 0;

    $ins = $conn->prepare("INSERT INTO schedule (device_id, mode, weekdays, start_date, end_date, start_time, end_time, enabled) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $ins->execute([$device_id, $mode, $weekdays, $start_date, $end_date, $start_time, $end_time, $enabled]);
    header("Location: room.php?id=$room_id");
    exit();
}
if (isset($_GET['del_schedule'])) {
    $sid = (int)$_GET['del_schedule'];
    $d = $conn->prepare("DELETE FROM schedule WHERE id=?");
    $d->execute([$sid]);
    header("Location: room.php?id=$room_id");
    exit();
}
if (isset($_GET['toggle_schedule'])) {
    $sid = (int)$_GET['toggle_schedule'];
    $q = $conn->prepare("SELECT enabled FROM schedule WHERE id=?");
    $q->execute([$sid]);
    $r = $q->fetch();
    if ($r) {
        $new = $r['enabled'] ? 0 : 1;
        $u = $conn->prepare("UPDATE schedule SET enabled=? WHERE id=?");
        $u->execute([$new, $sid]);
    }
    header("Location: room.php?id=$room_id");
    exit();
}

/* ดึง schedule ของ switches ในห้องนี้ */
$ids = array_column($switches, 'id');
$schedules = [];
if (count($ids) > 0) {
    // สร้าง placeholder สำหรับ IN clause
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    // สร้าง array ของ device_id ที่จะใช้ใน SQL query
    $params = array_map(fn($id) => "switch_$id", $ids);
    
    $stmt = $conn->prepare("SELECT * FROM schedule WHERE device_id IN ($placeholders) ORDER BY id DESC");
    $stmt->execute($params);
    $schedules = $stmt->fetchAll();

/* ----------------- อัปโหลด/ลบ รูปห้อง ----------------- */
$uploadDir = "uploads/";

// อัปโหลดรูปใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_image'])) {
    if (!empty($_FILES['room_image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['room_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($ext, $allowed)) {
            die("❌ อนุญาตเฉพาะไฟล์ JPG, JPEG, PNG, GIF เท่านั้น");
        }

        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        // สร้างชื่อไฟล์ใหม่
        $fileName = "room_" . $room_id . "." . $ext;
        $filePath = $uploadDir . $fileName;

        // ลบไฟล์เก่า
        foreach ($allowed as $e) {
            $oldFile = $uploadDir . "room_" . $room_id . "." . $e;
            if (file_exists($oldFile)) unlink($oldFile);
        }

        // ย้ายไฟล์ไปโฟลเดอร์ uploads
        if (move_uploaded_file($_FILES['room_image']['tmp_name'], $filePath)) {
            // บันทึกชื่อไฟล์ใน DB
            $u = $conn->prepare("UPDATE rooms SET image=? WHERE id=?");
            $u->execute([$fileName, $room_id]);
        }

        header("Location: room.php?id=$room_id");
        exit();
    }
}

// ลบรูป
if (isset($_GET['delete_image'])) {
    $deleted = false;
    foreach (['jpg','jpeg','png','gif'] as $e) {
        $file = $uploadDir . "room_" . $room_id . "." . $e;
        if (file_exists($file)) {
            unlink($file);
            $deleted = true;
        }
    }
    if ($deleted) {
        $u = $conn->prepare("UPDATE rooms SET image=NULL WHERE id=?");
        $u->execute([$room_id]);
    }
    header("Location: room.php?id=$room_id");
    exit();
}




}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>จัดการห้อง <?= htmlspecialchars($room['room_name']) ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
/* สไตล์สั้น ๆ ให้หน้าดูสะอาด */
body{background:#f4f6f9;font-family: "Segoe UI", Tahoma, sans-serif;}
.header{background:#2c3e50;color:#fff;padding:14px}
.header a{color:#f1c40f}
.container-main{max-width:1200px;margin:20px auto}
.left{min-height:400px}
.switch-card{display:flex;align-items:center;justify-content:space-between;padding:12px;margin-bottom:10px;border-radius:8px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.05)}
.dev-img{width:120px;height:120px;object-fit:cover;border-radius:50%;border:4px solid #0d6efd}
.form-sm input, .form-sm select{width:100%;padding:8px;margin-bottom:8px}
.table-sched td, .table-sched th{vertical-align:middle}

/* สไตล์ Toggle Switch */
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: #ccc;
    transition: .3s;
    border-radius: 24px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: #27ae60; /* สีเขียวเมื่อเปิด */
}

input:checked + .slider:before {
    transform: translateX(26px);
}
</style>
</head>
<body>
<header class="header">
  <div class="container-main">
    <h4>ห้อง: <?= htmlspecialchars($room['room_name']) ?> (ชั้น <?= htmlspecialchars($room['floor']) ?>) &nbsp;
      <a href="building.php">← กลับ</a>
    </h4>
  </div>
</header>

<div class="container-main">
  <div class="row g-3">
    <div class="col-md-5 left">
      <h5>🔌 สวิตช์ที่มี</h5>
      <?php foreach ($switches as $sw): ?>
        <div class="switch-card">
          <div>
            <strong><?= htmlspecialchars($sw['switch_name']) ?></strong><br>
            <small>Board: <?= htmlspecialchars($sw['board']) ?> | GPIO: <?= htmlspecialchars($sw['gpio_pin']) ?></small>
          </div>
          <div class="text-end">
            <a class="btn btn-sm btn-danger" href="room.php?id=<?= $room_id ?>&delete=<?= $sw['id'] ?>" onclick="return confirm('ลบสวิตช์ใช่หรือไม่?')">ลบ</a>
            
            <label class="toggle-switch">
              <input type="checkbox" <?= $sw['status']==='on'?'checked':'' ?>
                     onchange="window.location.href='room.php?id=<?= $room_id ?>&toggle=<?= $sw['id'] ?>'">
              <span class="slider"></span>
            </label>
          </div>
        </div>
      <?php endforeach; ?>

      <div class="card p-3 mt-3">
        <h6>➕ เพิ่มสวิตช์ใหม่</h6>
        <form method="post" class="form-sm">
          <input name="switch_name" placeholder="ชื่อสวิตช์" required>
          <input name="board" placeholder="Board (เช่น ESP32-A)" required>
          <input name="gpio_pin" type="number" placeholder="GPIO pin" required>
          <button class="btn btn-primary" name="add_switch">บันทึก</button>
        </form>
      </div>

      <div class="card p-3 mt-3">
        <h6>⏰ สร้าง Schedule</h6>
        <form method="post" class="form-sm">
          <label>สวิตช์</label>
          <select name="device_id" required>
            <?php foreach ($switches as $sw): ?>
              <option value="switch_<?= $sw['id'] ?>"><?= htmlspecialchars($sw['switch_name']) ?></option>
            <?php endforeach; ?>
          </select>
          <label>โหมด</label>
          <select name="mode">
              <option value="on">เปิด</option>
              <option value="off">ปิด</option>
          </select>

          <label>วัน (เลือกได้หลายวัน)</label><br>
          <?php $days = ['Mon'=>'จ','Tue'=>'อ','Wed'=>'พ','Thu'=>'พฤ','Fri'=>'ศ','Sat'=>'ส','Sun'=>'อา'];
            foreach($days as $k=>$v) echo "<label style='margin-right:6px'><input type='checkbox' name='weekdays[]' value='$k'> $v</label>";
          ?>
          <div class="row mt-2">
            <div class="col">
              <label>วันที่เริ่ม</label>
              <input type="date" name="start_date" class="form-control">
            </div>
            <div class="col">
              <label>วันที่จบ</label>
              <input type="date" name="end_date" class="form-control">
            </div>
          </div>
          <div class="row mt-2">
            <div class="col">
              <label>เวลาเริ่ม</label>
              <input type="time" name="start_time" class="form-control" required>
            </div>
            <div class="col">
              <label>เวลาจบ</label>
              <input type="time" name="end_time" class="form-control" required>
            </div>
          </div>
          <label class="mt-2"><input type="checkbox" name="enabled" checked> เปิดใช้งาน</label><br>
          <button class="btn btn-success mt-2" name="add_schedule">เพิ่ม Schedule</button>
        </form>
      </div>
    </div>

    <div class="col-md-7">
      <div class="card p-3 mb-3">
        <h6>📊 กราฟประวัติการเปิด/ปิด</h6>
        <form method="get" class="row g-2 mb-2">
          <input type="hidden" name="id" value="<?= $room_id ?>">
          <div class="col-auto">
            <select name="range" class="form-select">
              <option value="1d" <?= ($range==='1d')?"selected":"" ?>>24 ชั่วโมง</option>
              <option value="7d" <?= ($range==='7d')?"selected":"" ?>>7 วัน</option>
              <option value="30d" <?= ($range==='30d')?"selected":"" ?>>30 วัน</option>
              <option value="custom" <?= ($range==='custom')?"selected":"" ?>>กำหนดเอง</option>
            </select>
          </div>
          <div class="col">
            <input type="date" name="start" class="form-control" value="<?= htmlspecialchars($start ?? '') ?>">
          </div>
          <div class="col">
            <input type="date" name="end" class="form-control" value="<?= htmlspecialchars($end ?? '') ?>">
          </div>
          <div class="col-auto">
            <button class="btn btn-primary">แสดง</button>
          </div>
        </form>
        <canvas id="switchChart" style="width:100%;height:300px"></canvas>
      </div>

      <div class="card p-3 mb-3">
        <h6>📋 รายการ Schedule</h6>
        <?php if (count($schedules)===0): ?>
          <p>ยังไม่มี Schedule สำหรับสวิตช์ในห้องนี้</p>
        <?php else: ?>
          <table class="table table-sm table-sched">
            <thead><tr><th>ID</th><th>Device</th><th>Mode</th><th>Weekdays</th><th>Date Range</th><th>Time</th><th>Enabled</th><th>Action</th></tr></thead>
            <tbody>
              <?php foreach($schedules as $sch): ?>
                <tr>
                  <td><?= $sch['id'] ?></td>
                  <td><?= htmlspecialchars($sch['device_id']) ?></td>
                  <td><?= htmlspecialchars(strtoupper($sch['mode'])) ?></td>
                  <td><?= htmlspecialchars($sch['weekdays']?:'ทุกวัน') ?></td>
                  <td>
                    <?= htmlspecialchars($sch['start_date'] ? date('d/m/Y', strtotime($sch['start_date'])) : '-') ?> 
                    - 
                    <?= htmlspecialchars($sch['end_date'] ? date('d/m/Y', strtotime($sch['end_date'])) : '-') ?>
                  </td>
                  <td><?= htmlspecialchars($sch['start_time']) ?> - <?= htmlspecialchars($sch['end_time']) ?></td>
                  <td><?= $sch['enabled'] ? 'ใช้งาน' : 'ปิด' ?></td>
                  <td>
                    <a class="btn btn-sm btn-outline-secondary" href="room.php?id=<?= $room_id ?>&toggle_schedule=<?= $sch['id'] ?>">Toggle</a>
                    <a class="btn btn-sm btn-danger" href="room.php?id=<?= $room_id ?>&del_schedule=<?= $sch['id'] ?>" onclick="return confirm('ลบ schedule?')">Delete</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <div class="card p-3">
  <h6>🖼️ รูปห้อง</h6>
  <?php
    $roomImg = null;
    foreach (['jpg','jpeg','png','gif'] as $ext) {
        $file = "uploads/room_" . $room_id . "." . $ext;
        if (file_exists($file)) {
            $roomImg = $file;
            break;
        }
    }
  ?>
  <?php if ($roomImg): ?>
    <img src="<?= $roomImg ?>?v=<?= time() ?>" alt="room"
         style="width:100%;border-radius:8px;object-fit:cover;max-height:350px;">
    <br><br>
    <a href="room.php?id=<?= $room_id ?>&delete_image=1" class="btn btn-danger"
       onclick="return confirm('ลบรูปห้องนี้หรือไม่?')">ลบรูป</a>
  <?php else: ?>
    <p>ยังไม่มีรูปห้อง</p>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="mt-2">
    <input type="file" name="room_image" accept="image/*" class="form-control mb-2" required>
    <button type="submit" name="upload_image" class="btn btn-primary">อัปโหลด / เปลี่ยนรูป</button>
  </form>
</div>


    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ดึงข้อมูลจาก PHP
const rawLogs = <?= json_encode($logs) ?>;
const switches = <?= json_encode($switches) ?>;

// ฟังก์ชันเลือกสีแบบตายตัว (จะได้ไม่สุ่มมั่ว)
const colors = [
  "#e74c3c", // แดง
  "#3498db", // น้ำเงิน
  "#2ecc71", // เขียว
  "#f39c12", // ส้ม
  "#9b59b6", // ม่วง
  "#1abc9c"  // ฟ้าเขียว
];

// เตรียม labels = เวลา log (แกน X)
const labels = rawLogs.map(l => l.created_at);

// datasets แยกตาม switch
const datasets = switches.map((sw, i) => {
  const swLogs = rawLogs.filter(l => l.switch_id == sw.id);
  return {
    label: sw.switch_name,
    data: swLogs.map(l => (l.status === 'on' ? 1 : 0)),
    fill: false,
    borderWidth: 3,
    tension: 0.3,
    borderColor: colors[i % colors.length],
    pointBackgroundColor: colors[i % colors.length],
    pointRadius: 4
  };
});

// วาดกราฟ
const ctx = document.getElementById('switchChart').getContext('2d');
new Chart(ctx, {
  type: 'line',
  data: {
    labels: labels,
    datasets: datasets
  },
  options: {
    responsive: true,
    plugins: {
      legend: {
        display: true,
        position: 'bottom',
        labels: {
          font: { size: 14 },
          color: "#333"
        }
      },
      title: {
        display: true,
        text: "สถานะสวิตช์ (ON/OFF)",
        font: { size: 18 }
      }
    },
    scales: {
      y: {
        min: 0,
        max: 1,
        ticks: {
          stepSize: 1,
          callback: v => v === 1 ? 'ON' : 'OFF'
        },
        grid: { color: "#ddd" }
      },
      x: {
        grid: { color: "#f0f0f0" },
        ticks: { maxRotation: 45, minRotation: 30 }
      }
    }
  }
});
</script>


</body>
</html>