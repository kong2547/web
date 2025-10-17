<?php
session_start();
include 'db.php';
include 'log_action.php';

// ✅ ตรวจสอบการล็อกอิน
if (!isset($_SESSION['username'])) {
    header('location: login.php');
    exit();
}

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

// เพิ่มห้องใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_room'])) {
    $floor = $_POST['floor'];
    $room_name = $_POST['room_name'];
    $stmt = $conn->prepare("INSERT INTO rooms (floor, room_name) VALUES (?, ?)");
    $stmt->execute([$floor, $room_name]);
    header("Location: building.php");
    exit();
}

// ลบห้อง
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_room'])) {
    $room_id = $_POST['room_id'];
    $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
    $stmt->execute([$room_id]);
    header("Location: building.php");
    exit();
}

// ดึงข้อมูลห้องทั้งหมด
$rooms = $conn->query("SELECT * FROM rooms ORDER BY floor DESC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการอาคาร</title>
    <style>
        body {
    font-family: "Segoe UI", Tahoma, sans-serif;
    background: #f8f9fa;   /* เทาอ่อน อ่านง่ายกว่า #fff ล้วน */
    margin: 0;
    padding: 0;
}

/* Navbar */
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
.navbar a:hover {
    text-decoration: underline;
}

.container {
    padding: 25px;
    max-width: 1000px;
    margin: auto;
}

h1 {
    text-align: center;
    color: #333;   /* จากเดิมสีขาว → เทาเข้ม */
    margin-bottom: 30px;
    font-weight: 600;
}

/* Layout อาคาร */
.floor {
    display: flex;
    align-items: flex-start;
    margin-bottom: 20px;
    border-radius: 8px;
    background: #fff;  /* ขาวทึบ อ่านง่าย */
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

.delete-btn {
    background:#dc3545;
    color:#fff;
    border:none;
    border-radius:5px;
    padding:4px 8px;
    font-size: 12px;
    cursor:pointer;
}
.delete-btn:hover { background:#b02a37; }

/* ฟอร์มเพิ่มห้อง */
.add-room-form {
    display: flex;
    gap: 5px;
    margin-top: 10px;
    flex-wrap: wrap;
}
.add-room-form input {
    flex: 1;
    min-width: 120px;
    padding: 6px;
    border: 1px solid #ccc;
    border-radius: 5px;
}
.add-btn {
    background: #28a745;
    color:#fff;
    border:none;
    border-radius: 5px;
    padding: 6px 12px;
    cursor:pointer;
}
.add-btn:hover { background:#218838; }

/* Responsive */
@media (max-width: 768px) {
    .floor { flex-direction: column; }
    .floor-label {
        width: 100%;
        border-radius: 0;
    }
}

    </style>
</head>
<body>
    <!-- Navbar -->
    <div class="navbar">
        <a href="index.php">🏠 หน้าแรก</a>
        <a href="building.php">🏢 อาคาร</a>
        <a href="plan.php">📐 แผนผังตำแหน่งห้องภายในชั้น</a>
        <a href="ipboard.php" class="active">📡 BOARD</a>
        <a href="logout.php" style="margin-left:auto; color:#ffc107;">🚪 ออกจากระบบ</a>
    </div>

    <div class="container">
        <h1>🏢 อาคารศรีวิศววิทยา คณะวิศวกรรมศาสตร์</h1>

        <?php for ($f=10; $f>=1; $f--): ?>
            <div class="floor">
                <div class="floor-label">ชั้น <?= $f ?></div>
                <div class="floor-rooms">
                    <?php foreach ($rooms as $room): ?>
                        <?php if ($room['floor'] == $f): 
                            $stmt_status = $conn->prepare("SELECT COUNT(*) FROM switches WHERE room_id = ? AND status = 'on'");
                            $stmt_status->execute([$room['id']]);
                            $switches_on = $stmt_status->fetchColumn();
                            $room_class = ($switches_on > 0) ? 'on' : 'off';
                        ?>
                            <div>
                                <a class="room <?= $room_class ?>" href="room.php?id=<?= $room['id'] ?>">
                                    <?= htmlspecialchars($room['room_name']) ?>
                                </a>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="room_id" value="<?= $room['id'] ?>">
                                    <button type="submit" name="delete_room" class="delete-btn" onclick="return confirm('คุณต้องการลบห้องนี้ใช่หรือไม่?');">ลบ</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <!-- ฟอร์มเพิ่มห้อง -->
                    <form method="post" class="add-room-form">
                        <input type="hidden" name="floor" value="<?= $f ?>">
                        <input type="text" name="room_name" placeholder="ชื่อห้อง" required>
                        <button type="submit" name="add_room" class="add-btn">+ เพิ่มห้อง</button>
                    </form>
                </div>
            </div>
        <?php endfor; ?>
    </div>
</body>
</html>
