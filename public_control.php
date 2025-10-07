<?php
include 'db.php';

// --------------------------
// ถ้ามี ?id=<room_id> → แสดงสวิตช์ในห้องนั้น
// --------------------------
if (isset($_GET['id'])) {
    $room_id = (int)$_GET['id'];

    // ดึงข้อมูลห้อง
    $stmt = $conn->prepare("SELECT * FROM rooms WHERE id = ?");
    $stmt->execute([$room_id]);
    $room = $stmt->fetch();
    if (!$room) die("❌ ไม่พบห้องนี้");

    // ดึงสวิตช์ทั้งหมดในห้อง
    $stmt = $conn->prepare("SELECT * FROM switches WHERE room_id = ? ORDER BY id ASC");
    $stmt->execute([$room_id]);
    $switches = $stmt->fetchAll();

    // ตรวจว่ามีรูปห้องไหม
    $roomImg = null;
    foreach (['jpg','jpeg','png','gif'] as $ext) {
        $file = "uploads/room_" . $room_id . "." . $ext;
        if (file_exists($file)) { $roomImg = $file; break; }
    }
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>ควบคุมห้อง <?= htmlspecialchars($room['room_name']) ?> (บุคคลภายนอก)</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
    background-color: #f8f9fa;
    font-family: "Segoe UI", Tahoma, sans-serif;
}
.header {
    background: #007bff;
    color: #fff;
    padding: 12px;
}
.container-main {
    max-width: 800px;
    margin: 20px auto;
}
.switch-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px;
    margin-bottom: 10px;
    border-radius: 8px;
    background: #fff;
    border: 1px solid #dee2e6;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.slider {
    position: absolute; cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: #ccc;
    transition: .3s;
    border-radius: 24px;
}
.slider:before {
    position: absolute;
    content: "";
    height: 18px; width: 18px;
    left: 3px; bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
}
input:checked + .slider { background-color: #28a745; }
input:checked + .slider:before { transform: translateX(26px); }
.room-image {
    width: 100%;
    max-height: 350px;
    object-fit: cover;
    border-radius: 10px;
}
.esp-status {
    margin-top: 10px;
}
</style>
</head>
<body>
<header class="header">
    <div class="container-main d-flex justify-content-between align-items-center">
        <h5 class="mb-0">ห้อง: <?= htmlspecialchars($room['room_name']) ?> (ชั้น <?= htmlspecialchars($room['floor']) ?>)</h5>
        <a href="public_control.php" class="btn btn-warning btn-sm text-dark">← กลับหน้าหลัก</a>
    </div>
</header>

<div class="container-main">

    <!-- รูปห้อง -->
    <div class="card shadow-sm mb-4">
        <div class="card-body text-center">
            <h6 class="text-primary mb-2">🖼️ รูปห้อง</h6>
            <?php if ($roomImg): ?>
                <img src="<?= $roomImg ?>?v=<?= time() ?>" alt="room" class="room-image">
            <?php else: ?>
                <p class="text-muted">ยังไม่มีรูปห้อง</p>
            <?php endif; ?>

            <div id="espStatus" class="esp-status text-center text-secondary">กำลังโหลดสถานะ ESP...</div>
        </div>
    </div>

    <!-- สวิตช์ -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h6 class="text-primary">🔌 สวิตช์ในห้องนี้</h6>
            <div id="switchList">
                <?php if (empty($switches)): ?>
                    <p class="text-muted">ยังไม่มีสวิตช์ในห้องนี้</p>
                <?php else: ?>
                    <?php foreach ($switches as $sw): ?>
                        <div class="switch-card">
                            <div>
                                <strong><?= htmlspecialchars($sw['switch_name']) ?></strong><br>
                                <small class="text-muted">ESP: <?= htmlspecialchars($sw['esp_name']) ?> | GPIO: <?= htmlspecialchars($sw['gpio_pin']) ?></small>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" <?= $sw['status']==='on'?'checked':'' ?>
                                       onchange="toggleSwitch('<?= $sw['esp_name'] ?>', <?= $sw['gpio_pin'] ?>, this.checked, <?= $room_id ?>)">
                                <span class="slider"></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// ✅ อัปเดตสถานะสวิตช์
function toggleSwitch(espName, gpio, state, roomId) {
    const status = state ? 1 : 0;
    fetch(`api.php?cmd=update&esp_name=${espName}&gpio=${gpio}&status=${status}&room_id=${roomId}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) alert("❌ ไม่สามารถอัปเดตสถานะได้");
        })
        .catch(() => alert("❌ การเชื่อมต่อ API ล้มเหลว"));
}

// ✅ โหลดสถานะสวิตช์ทุก 5 วินาที
function refreshSwitches() {
    fetch(`api.php?cmd=list_statuses&room_id=<?= $room_id ?>`)
        .then(r => r.json())
        .then(data => {
            data.forEach(sw => {
                document.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                    if (cb.outerHTML.includes(`gpio=${sw.gpio_pin}`)) {
                        cb.checked = (sw.status === 'on');
                    }
                });
            });
        })
        .catch(() => console.log("⚠️ refresh failed"));
}

// ✅ โหลดสถานะ ESP ทุก 10 วินาที
function refreshESPStatus() {
    fetch(`api.php?cmd=esp_status&room_id=<?= $room_id ?>`)
        .then(r => r.json())
        .then(data => {
            let html = "";
            if (data.length === 0) {
                html = "<span class='text-muted'>ไม่มี ESP ในห้องนี้</span>";
            } else {
                data.forEach(e => {
                    let color = e.online ? "text-success" : "text-danger";
                    let text = e.online ? "🟢 ออนไลน์" : "🔴 ออฟไลน์";
                    html += `<div><strong class='${color}'>${e.esp_name}</strong> — ${text}</div>`;
                });
            }
            document.getElementById("espStatus").innerHTML = html;
        })
        .catch(() => {
            document.getElementById("espStatus").innerHTML = `<span class='text-warning'>⚠️ โหลดสถานะ ESP ไม่ได้</span>`;
        });
}

setInterval(refreshSwitches, 5000);
setInterval(refreshESPStatus, 10000);
refreshESPStatus();
</script>
</body>
</html>
<?php
exit();
}

// --------------------------
// หน้า overview อาคาร
// --------------------------
$rooms = $conn->query("SELECT * FROM rooms ORDER BY floor DESC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>ระบบควบคุมไฟ - บุคคลภายนอก</title>
<style>
body {
    font-family: "Segoe UI", Tahoma, sans-serif;
    background: #f8f9fa;
    margin: 0;
    padding: 0;
}
.navbar {
    background: #007bff;
    color: #fff;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.navbar a {
    color: #fff;
    text-decoration: none;
    margin-right: 20px;
    font-weight: 500;
}
.navbar a:hover { text-decoration: underline; }
.container {
    padding: 25px;
    max-width: 1000px;
    margin: auto;
}
h1 {
    text-align: center;
    color: #333;
    margin-bottom: 30px;
    font-weight: 600;
}
.floor {
    display: flex;
    align-items: flex-start;
    margin-bottom: 20px;
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    overflow: hidden;
    flex-wrap: wrap;
}
.floor-label {
    background: #007bff;
    color: #fff;
    padding: 15px;
    font-weight: bold;
    font-size: 15px;
    min-width: 80px;
    text-align: center;
}
.floor-rooms {
    flex: 1;
    padding: 12px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.room {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 14px;
    color: #fff;
    text-decoration: none;
    transition: all 0.2s;
}
.room.on { background:#28a745; }
.room.off { background:#6c757d; }
.room:hover { opacity: 0.85; }
.footer {
    text-align: center;
    color: #666;
    margin-top: 40px;
    font-size: 14px;
}
.back-login {
    display:inline-block;
    margin-top:10px;
    background:#ffc107;
    color:#000;
    text-decoration:none;
    padding:6px 12px;
    border-radius:6px;
}
.back-login:hover { background:#e0a800; }
</style>
</head>
<body>
<div class="navbar">
    <a href="login.php">🔑 กลับหน้าเข้าสู่ระบบ</a>
    <a href="public_control.php" class="active">🌐 บุคคลภายนอก</a>
    <a href="#" style="margin-left:auto; color:#ffc107;">👤 Guest Mode</a>
</div>

<div class="container">
    <h1>🌐 ระบบควบคุมไฟสำหรับบุคคลภายนอก</h1>

    <?php for ($f=10; $f>=1; $f--): ?>
        <div class="floor">
            <div class="floor-label">ชั้น <?= $f ?></div>
            <div class="floor-rooms">
                <?php 
                foreach ($rooms as $room):
                    if ($room['floor'] == $f):
                        $stmt_status = $conn->prepare("SELECT COUNT(*) FROM switches WHERE room_id = ? AND status = 'on'");
                        $stmt_status->execute([$room['id']]);
                        $switches_on = $stmt_status->fetchColumn();
                        $room_class = ($switches_on > 0) ? 'on' : 'off';
                ?>
                    <a class="room <?= $room_class ?>" href="public_control.php?id=<?= $room['id'] ?>">
                        <?= htmlspecialchars($room['room_name']) ?>
                    </a>
                <?php 
                    endif;
                endforeach;
                ?>
            </div>
        </div>
    <?php endfor; ?>

    <div class="footer">
        <p>ระบบควบคุมไฟ © 2025 มหาวิทยาลัยเทคโนโลยีราชมงคลศรีวิชัย สงขลา</p>
        <a href="login.php" class="back-login">กลับหน้าเข้าสู่ระบบ</a>
    </div>
</div>
</body>
</html>
