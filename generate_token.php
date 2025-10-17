<?php
require 'db.php';
require_once 'phpqrcode/qrlib.php'; // โหลดไลบรารี QR Code
header('Content-Type: text/html; charset=utf-8');

// ===============================
// สร้างโฟลเดอร์เก็บ QR ถ้ายังไม่มี
// ===============================
$qrDir = __DIR__ . "/qrcodes/";
if (!is_dir($qrDir)) {
    mkdir($qrDir, 0777, true);
}

// ===============================
// ฟังก์ชันสุ่ม Token
// ===============================
function generateToken($length = 16) {
    return bin2hex(random_bytes($length / 2));
}

// ===============================
// อัปเดต token เฉพาะห้องที่ยังไม่มี
// ===============================
$stmt = $conn->query("SELECT id, room_name, floor, public_token FROM rooms ORDER BY floor DESC, id ASC");
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rooms as $r) {
    if (empty($r['public_token'])) {
        $token = generateToken();
        $update = $conn->prepare("UPDATE rooms SET public_token = ? WHERE id = ?");
        $update->execute([$token, $r['id']]);
    }
}

// ดึงข้อมูลใหม่หลังอัปเดต
$stmt = $conn->query("SELECT id, room_name, floor, public_token FROM rooms ORDER BY floor DESC, id ASC");
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// แยกตามชั้น
$floors = [];
foreach ($rooms as $r) {
    $floors[$r['floor']][] = $r;
}

// ตรวจสอบ URL base
$baseURL = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
         . "://" . $_SERVER['HTTP_HOST']
         . dirname($_SERVER['PHP_SELF']);

// ===============================
// สร้าง QR Code ของแต่ละห้อง
// ===============================
foreach ($rooms as $r) {
    $link = $baseURL . "/public_control.php?access=" . $r['public_token'];
    $qrFile = $qrDir . "room_" . $r['id'] . ".png";

    // ถ้ายังไม่มีไฟล์ QR หรือมีแต่เก่า → สร้างใหม่
    if (!file_exists($qrFile)) {
        QRcode::png($link, $qrFile, QR_ECLEVEL_L, 5);
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>QR Code สำหรับห้องทั้งหมด (Offline)</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body {
    font-family: "Kanit", sans-serif;
    background: #f4f7fb;
    color: #333;
    margin: 0;
    padding: 20px;
}
h1 {
    text-align: center;
    color: #007bff;
    margin-bottom: 10px;
}
p.sub {
    text-align: center;
    color: #666;
    margin-bottom: 40px;
}
.btn-bar {
    text-align: center;
    margin-bottom: 30px;
}
button {
    background: #007bff;
    color: white;
    border: none;
    border-radius: 6px;
    padding: 10px 20px;
    margin: 5px;
    cursor: pointer;
    font-size: 16px;
    transition: 0.3s;
}
button:hover { background: #0056b3; }
.back-btn { background: #6c757d; }
.back-btn:hover { background: #5a6268; }
.print-btn { background: #28a745; }
.print-btn:hover { background: #218838; }
.zip-btn { background: #ff9800; }
.zip-btn:hover { background: #e68900; }

.floor-box {
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
    margin-bottom: 30px;
}
.floor-box h2 {
    color: #444;
    margin-bottom: 10px;
}
.room-item {
    display: inline-block;
    text-align: center;
    border: 1px solid #ddd;
    border-radius: 10px;
    padding: 15px;
    margin: 10px;
    background: #fafafa;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    transition: 0.2s;
}
.room-item:hover {
    background: #e9f5ff;
    transform: translateY(-3px);
}
.room-item img {
    width: 160px;
    height: 160px;
    margin-bottom: 8px;
}
.room-item strong {
    display: block;
    margin-bottom: 5px;
    color: #007bff;
}
.room-item small {
    font-size: 12px;
    color: #555;
    word-wrap: break-word;
}
.footer {
    text-align: center;
    color: #888;
    font-size: 14px;
    margin-top: 50px;
}
</style>
</head>
<body>

<h1>🔒 QR Code สำหรับห้องทั้งหมด (Offline)</h1>
<p class="sub">สแกนเพื่อเปิดหน้าควบคุมเฉพาะห้องนั้น (QR ไม่หมดอายุ / ไม่ต้องต่อเน็ต)</p>

<div class="btn-bar">
    <button class="back-btn" onclick="window.location.href='admin_dashboard.php'">← กลับหน้าแดชบอร์ด</button>
    <button onclick="window.print()">🖨️ พิมพ์ QR ทั้งหมด</button>
    <button class="zip-btn" onclick="window.location.href='zip_qr.php'">📦 ดาวน์โหลด QR ทั้งหมด (ZIP)</button>
</div>

<?php foreach ($floors as $floor => $roomsInFloor): ?>
<div class="floor-box" id="floor<?= $floor ?>">
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <h2>🏢 ชั้น <?= htmlspecialchars($floor) ?></h2>
        <button class="print-btn" onclick="printFloor(<?= $floor ?>)">🖨️ พิมพ์ QR ชั้นนี้</button>
    </div>
    <?php foreach ($roomsInFloor as $row): 
        $link = $baseURL . "/public_control.php?access=" . $row['public_token'];
    ?>
        <div class="room-item">
            <strong><?= htmlspecialchars($row['room_name']) ?></strong>
            <img src="qrcodes/room_<?= $row['id'] ?>.png" alt="QR Code">
            <small><?= htmlspecialchars($link) ?></small>
        </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<div class="footer">
    ระบบควบคุมอาคาร © <?= date("Y") ?> | พัฒนาโดย Suphawit IoT Smart Building
</div>

<script>
// 🖨️ พิมพ์เฉพาะชั้น
function printFloor(floor) {
    const divContent = document.getElementById("floor" + floor).innerHTML;
    const win = window.open("", "_blank");
    win.document.write(`
        <html><head><meta charset="UTF-8">
        <title>พิมพ์ QR ชั้น ${floor}</title>
        <style>
        body { font-family:'Kanit',sans-serif;text-align:center; }
        img { width:180px;height:180px;margin:10px; }
        </style></head>
        <body>${divContent}</body></html>
    `);
    win.document.close();
    win.print();
}
</script>

</body>
</html>
