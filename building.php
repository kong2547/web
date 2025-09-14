<?php
include 'db.php';

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
        body { font-family: sans-serif; background: #f4f4f4; padding:0; margin:0; }

        /* Navbar */
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

        .container { padding:20px; }

        /* Grid layout สำหรับแสดง 10 ชั้น */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .floor {
            background: #fff;
            padding:15px;
            border-radius:8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .floor h2 {
            margin-top:0;
            margin-bottom:10px;
            color: #007bff;
        }

        .room-container { 
            display: flex; 
            align-items: center; 
            gap: 5px; 
            margin:5px 0; 
            flex-wrap: wrap;
        }

        .room { 
            padding:8px 12px; 
            color:#fff; 
            border-radius:5px; 
            text-decoration:none; 
            font-size: 14px;
        }

        .room.on { background:#28a745; } /* สีเขียวสำหรับสถานะ 'เปิด' */
        .room.off { background:#6c757d; } /* สีเทาสำหรับสถานะ 'ปิด' */

        .add-btn { 
            display:inline-block; 
            padding:5px 10px; 
            margin-top:10px; 
            background:#28a745; 
            color:#fff; 
            border-radius:5px; 
            cursor:pointer; 
            border: none; 
        }

        .delete-btn { 
            display:inline-block; 
            padding:5px 10px; 
            background:#dc3545; 
            color:#fff; 
            border-radius:5px; 
            cursor:pointer; 
            border: none; 
            font-size: 12px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <div class="navbar">
        <a href="index.php">🏠 หน้าแรก</a>
        <a href="building.php">🏢 อาคาร</a>
        <a href="plan.php">🏢แผนผังตำแหน่งห้องภายในชั้น</a>
        <a href="logout.php" style="margin-left:auto; color:#ffc107;">🚪 ออกจากระบบ</a>
    </div>

    <div class="container">
        <h1>🏢 อาคารศรีวิศววิทยา คณะวิศวกรรมศาสตร์</h1>

        <div class="grid">
            <?php for ($f=10; $f>=1; $f--): ?>
                <div class="floor">
                    <h2>ชั้น <?= $f ?></h2>
                    <?php foreach ($rooms as $room): ?>
                        <?php if ($room['floor'] == $f): 
                            // ตรวจสอบสถานะของสวิตช์ในห้อง
                            $stmt_status = $conn->prepare("SELECT COUNT(*) FROM switches WHERE room_id = ? AND status = 'on'");
                            $stmt_status->execute([$room['id']]);
                            $switches_on = $stmt_status->fetchColumn();

                            $room_class = ($switches_on > 0) ? 'on' : 'off';
                        ?>
                            <div class="room-container">
                                <a class="room <?= $room_class ?>" href="room.php?id=<?= $room['id'] ?>">
                                    <?= htmlspecialchars($room['room_name']) ?>
                                </a>
                                <!-- ปุ่มลบห้อง -->
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="room_id" value="<?= $room['id'] ?>">
                                    <button type="submit" name="delete_room" class="delete-btn" onclick="return confirm('คุณต้องการลบห้องนี้ใช่หรือไม่?');">ลบ</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <!-- ฟอร์มเพิ่มห้อง -->
                    <form method="post" style="display:inline-flex; align-items:center; margin-top:10px;">
                        <input type="hidden" name="floor" value="<?= $f ?>">
                        <input type="text" name="room_name" placeholder="ชื่อห้อง" required>
                        <button type="submit" name="add_room" class="add-btn">+ เพิ่มห้อง</button>
                    </form>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</body>
</html>
