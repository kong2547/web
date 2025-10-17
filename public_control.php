<?php
session_start();
include 'db.php';

// --------------------------
// ถ้ามี ?access=<token> → แสดงสวิตช์ในห้องนั้น
// --------------------------
if (isset($_GET['access'])) {
    $token = $_GET['access'];

    // ดึงข้อมูลห้องจาก token
    $stmt = $conn->prepare("SELECT * FROM rooms WHERE public_token = ?");
    $stmt->execute([$token]);
    $room = $stmt->fetch();
    if (!$room) die("❌ Token ไม่ถูกต้อง หรือห้องนี้ไม่มีสิทธิ์เข้าถึง");

    $room_id = $room['id'];

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
<title>ห้อง <?= htmlspecialchars($room['room_name']) ?> (โหมดบุคคลทั่วไป)</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{font-family:"Segoe UI",sans-serif;background:#f5f6f8}
.header{background:#007bff;color:#fff;padding:12px}
.container-main{max-width:800px;margin:20px auto}
.switch-card{display:flex;align-items:center;justify-content:space-between;padding:12px;margin-bottom:10px;border-radius:8px;background:#fff;border:1px solid #dee2e6;box-shadow:0 2px 4px rgba(0,0,0,0.05)}
.toggle-switch{position:relative;display:inline-block;width:50px;height:24px}
.toggle-switch input{opacity:0;width:0;height:0}
.slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background-color:#ccc;transition:.3s;border-radius:24px}
.slider:before{position:absolute;content:"";height:18px;width:18px;left:3px;bottom:3px;background-color:white;transition:.3s;border-radius:50%}
input:checked + .slider{background-color:#28a745}
input:checked + .slider:before{transform:translateX(26px)}
.room-image{width:100%;max-height:350px;object-fit:cover;border-radius:10px}
</style>
</head>
<body>
<header class="header">
  <div class="container-main d-flex justify-content-between align-items-center">
    <h5 class="mb-0">ห้อง: <?= htmlspecialchars($room['room_name']) ?> (ชั้น <?= htmlspecialchars($room['floor']) ?>)</h5>
    <a href="login.php" class="btn btn-warning btn-sm text-dark">← กลับระบบหลัก</a>
  </div>
</header>

<div class="container-main">
  <div class="card shadow-sm mb-4">
    <div class="card-body text-center">
      <h6 class="text-primary mb-2">🖼️ รูปห้อง</h6>
      <?php if ($roomImg): ?>
        <img src="<?= $roomImg ?>?v=<?= time() ?>" class="room-image">
      <?php else: ?>
        <p class="text-muted">ยังไม่มีรูปห้อง</p>
      <?php endif; ?>
      <div id="espStatus" class="mt-2 text-secondary">กำลังโหลดสถานะ ESP...</div>
    </div>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <h6 class="text-primary">🔌 สวิตช์ในห้องนี้</h6>
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
              <input type="checkbox" <?= $sw['status']==='on'?'checked':'' ?> onchange="toggleSwitch('<?= $sw['esp_name'] ?>', <?= $sw['gpio_pin'] ?>, this.checked, <?= $room_id ?>)">
              <span class="slider"></span>
            </label>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function toggleSwitch(espName, gpio, state, roomId){
  const status = state ? 1 : 0;
  fetch(`api.php?cmd=update&esp_name=${espName}&gpio=${gpio}&status=${status}&room_id=${roomId}`)
  .then(r=>r.json()).then(d=>{
    if(!d.success) alert("❌ อัปเดตไม่สำเร็จ");
  }).catch(()=>alert("❌ การเชื่อมต่อ API ล้มเหลว"));
}

function refreshESPStatus(){
  fetch(`api.php?cmd=esp_status&room_id=<?= $room_id ?>`)
  .then(r=>r.json()).then(data=>{
    let html="";
    if(data.length===0) html="<span class='text-muted'>ไม่มี ESP ในห้องนี้</span>";
    else data.forEach(e=>{
      let c=e.online?"text-success":"text-danger";
      let t=e.online?"🟢 ออนไลน์":"🔴 ออฟไลน์";
      html+=`<div><strong class='${c}'>${e.esp_name}</strong> — ${t}</div>`;
    });
    document.getElementById("espStatus").innerHTML=html;
  }).catch(()=>document.getElementById("espStatus").innerHTML="<span class='text-warning'>⚠️ โหลดสถานะ ESP ไม่ได้</span>");
}

setInterval(refreshESPStatus,10000);
refreshESPStatus();
</script>
</body>
</html>
<?php exit(); } ?>
