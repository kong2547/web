<?php
session_start();
require 'db.php'; // เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล

// 📌 โฟลเดอร์สำหรับเก็บไฟล์รูปภาพ
$uploadDir = "uploads/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// 📌 ฟังก์ชันสำหรับจัดการข้อความแจ้งเตือน
function set_alert($message, $type = 'success') {
    $_SESSION['alert_message'] = $message;
    $_SESSION['alert_type'] = $type;
}

// 📌 ฟังก์ชันสำหรับแสดงข้อความแจ้งเตือน
function display_alert() {
    if (isset($_SESSION['alert_message'])) {
        $message = htmlspecialchars($_SESSION['alert_message']);
        $type = htmlspecialchars($_SESSION['alert_type']);
        echo "<div class='alert alert-$type'>$message</div>";
        unset($_SESSION['alert_message']);
        unset($_SESSION['alert_type']);
    }
}

// 📌 ส่วนจัดการการอัพโหลดรูปภาพ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload'])) {
    $floor = isset($_POST['floor']) ? (int)$_POST['floor'] : 0;

    // 📌 เพิ่มการตรวจสอบความถูกต้องของหมายเลขชั้น (ต้องเป็นตัวเลขและอยู่ในช่วง 1-10)
    if ($floor < 1 || $floor > 10) {
        set_alert("ไม่สามารถอัปโหลดได้ กรุณาเลือกหมายเลขชั้นที่ถูกต้อง!", "danger");
        header("Location: plan.php");
        exit();
    }
    
    $file = $_FILES['plan'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($ext, $allowed)) {
            // สร้างชื่อไฟล์ใหม่เพื่อป้องกันการซ้ำ
            $newFilename = "floor{$floor}_" . time() . "." . $ext;
            $targetFile = $uploadDir . $newFilename;

            // ตรวจสอบว่ามีไฟล์เก่าสำหรับชั้นนี้หรือไม่
            $stmt = $conn->prepare("SELECT filename FROM plans WHERE floor = ?");
            $stmt->execute([$floor]);
            $existing = $stmt->fetch();

            if ($existing) {
                // ลบไฟล์เก่าถ้ามี
                if (file_exists($uploadDir . $existing['filename'])) {
                    unlink($uploadDir . $existing['filename']);
                }
                // อัพเดทข้อมูลในฐานข้อมูล
                $stmt = $conn->prepare("UPDATE plans SET filename = ?, uploaded_at = NOW() WHERE floor = ?");
                $stmt->execute([$newFilename, $floor]);
                set_alert("แทนที่แปลนชั้น $floor เรียบร้อย", "warning");
            } else {
                // เพิ่มข้อมูลใหม่ในฐานข้อมูล
                $stmt = $conn->prepare("INSERT INTO plans (floor, filename) VALUES (?, ?)");
                // 📌 แก้ไขลำดับของตัวแปรใน execute() ให้ถูกต้อง
                $stmt->execute([$floor, $newFilename]);
                set_alert("อัพโหลดแปลนชั้น $floor สำเร็จ!", "success");
            }

            // ย้ายไฟล์ที่อัพโหลดไปยังโฟลเดอร์ปลายทาง
            move_uploaded_file($file['tmp_name'], $targetFile);
        } else {
            set_alert("อนุญาตเฉพาะไฟล์ JPG, JPEG, PNG, GIF เท่านั้น!", "danger");
        }
    } else {
        set_alert("เกิดข้อผิดพลาดในการอัพโหลดไฟล์!", "danger");
    }

    // Redirect กลับไปหน้าเดิมเพื่อไม่ให้เกิดการอัพโหลดซ้ำเมื่อ refresh
    header("Location: plan.php");
    exit();
}

// 📌 ส่วนจัดการการลบรูปภาพ
if (isset($_GET['delete'])) {
    $floor = (int)$_GET['delete'];
    
    // ดึงชื่อไฟล์จากฐานข้อมูล
    $stmt = $conn->prepare("SELECT filename FROM plans WHERE floor = ?");
    $stmt->execute([$floor]);
    $row = $stmt->fetch();

    if ($row) {
        // ลบไฟล์จากโฟลเดอร์
        if (file_exists($uploadDir . $row['filename'])) {
            unlink($uploadDir . $row['filename']);
        }
        // ลบข้อมูลออกจากฐานข้อมูล
        $stmt = $conn->prepare("DELETE FROM plans WHERE floor = ?");
        $stmt->execute([$floor]);
        set_alert("ลบแปลนชั้น $floor สำเร็จ!", "warning");
    } else {
        set_alert("ไม่พบข้อมูลแปลนชั้น $floor", "danger");
    }
    
    // Redirect กลับไปหน้าเดิม
    header("Location: plan.php");
    exit();
}

// 📌 ดึงข้อมูลแปลนทั้งหมดจากฐานข้อมูล
$plans = $conn->query("SELECT * FROM plans ORDER BY floor ASC")->fetchAll();
$plansByFloor = [];
foreach ($plans as $p) {
    $plansByFloor[$p['floor']] = $p;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการแปลนอาคาร</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        .card-img-container {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .card-img-container img {
            max-height: 250px;
            width: auto;
            height: auto;
        }
        /* Navbar styles */
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
    </style>
</head>
<body class="bg-light">
    <!-- Navbar -->
    <div class="navbar">
        <a href="index.php">🏠 หน้าแรก</a>
        <a href="building.php">🏢 อาคาร</a>
        <a href="plan.php" class="active">🏢 แผนผังตำแหน่งห้องภายในชั้น</a>
        <a href="logout.php" style="margin-left:auto; color:#ffc107;">🚪 ออกจากระบบ</a>
    </div>

<div class="container py-5">
    <h2 class="mb-4 text-center text-primary">📐 จัดการแปลนอาคาร (10 ชั้น)</h2>

    <?php display_alert(); ?>

    <div class="row g-4">
        <?php for ($i = 10; $i >= 1; $i--): ?>
            <div class="col-12 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-primary text-white">
                        <h4 class="card-title mb-0">ชั้น <?= $i ?></h4>
                    </div>
                    <div class="card-body p-3 text-center">
                        <?php if (isset($plansByFloor[$i])): ?>
                            <div class="card-img-container rounded mb-3">
                                <img src="uploads/<?= htmlspecialchars($plansByFloor[$i]['filename']) ?>" alt="แปลนชั้น <?= $i ?>" class="img-fluid rounded">
                            </div>
                            <small class="text-muted">อัพโหลดเมื่อ: <?= $plansByFloor[$i]['uploaded_at'] ?></small>
                            <a href="?delete=<?= $i ?>" class="btn btn-danger btn-sm w-100 mt-2" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบรูปชั้นนี้?')">ลบรูป</a>
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center" style="min-height: 250px;">
                                <p class="text-muted">ยังไม่มีแปลน</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-light">
                        <form method="post" enctype="multipart/form-data" class="d-flex flex-column flex-sm-row">
                            <input type="hidden" name="floor" value="<?= $i ?>">
                            <input type="file" name="plan" class="form-control me-sm-2 mb-2 mb-sm-0" required>
                            <button type="submit" name="upload" class="btn btn-success">อัพโหลด</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endfor; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
