<?php
session_start();
require 'db.php';
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

// ✅ เปิดโหมด Debug เพื่อดูข้อผิดพลาด PHP
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 📁 โฟลเดอร์เก็บไฟล์รูปภาพ
$uploadDir = "uploads/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// 📌 ฟังก์ชันสำหรับแจ้งเตือน
function set_alert($message, $type = 'success') {
    $_SESSION['alert_message'] = $message;
    $_SESSION['alert_type'] = $type;
}
function display_alert() {
    if (isset($_SESSION['alert_message'])) {
        $message = htmlspecialchars($_SESSION['alert_message']);
        $type = htmlspecialchars($_SESSION['alert_type']);
        echo "<div class='alert alert-$type text-center'>$message</div>";
        unset($_SESSION['alert_message']);
        unset($_SESSION['alert_type']);
    }
}

// 📸 ส่วนอัปโหลดภาพแปลน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload'])) {
    $floor = isset($_POST['floor']) ? (int)$_POST['floor'] : 0;

    // ✅ ตรวจสอบหมายเลขชั้น
    if ($floor < 1 || $floor > 10) {
        set_alert("ไม่สามารถอัปโหลดได้! หมายเลขชั้นไม่ถูกต้อง", "danger");
        header("Location: plan.php");
        exit();
    }

    // ✅ ตรวจสอบสิทธิ์โฟลเดอร์
    if (!is_writable($uploadDir)) {
        set_alert("❌ โฟลเดอร์ uploads ไม่มีสิทธิ์เขียนไฟล์! กรุณา chmod 777", "danger");
        header("Location: plan.php");
        exit();
    }

    $file = $_FILES['plan'];

    // ✅ แสดงข้อมูล Debug ไฟล์ (ชั่วคราว)
    echo "<pre>";
    print_r($_FILES);
    echo "</pre>";

    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($ext, $allowed)) {
            // ✅ ตั้งชื่อไฟล์ใหม่ไม่ซ้ำ
            $newFilename = "floor{$floor}_" . time() . "." . $ext;
            $targetFile = $uploadDir . $newFilename;

            // ✅ ตรวจสอบไฟล์เก่า
            $stmt = $conn->prepare("SELECT filename FROM plans WHERE floor = ?");
            $stmt->execute([$floor]);
            $existing = $stmt->fetch();

            if ($existing) {
                if (file_exists($uploadDir . $existing['filename'])) {
                    unlink($uploadDir . $existing['filename']);
                }
                $stmt = $conn->prepare("UPDATE plans SET filename=?, uploaded_at=NOW() WHERE floor=?");
                $stmt->execute([$newFilename, $floor]);
                set_alert("⚠️ แทนที่แปลนชั้น $floor เรียบร้อย", "warning");
            } else {
                $stmt = $conn->prepare("INSERT INTO plans (floor, filename, uploaded_at) VALUES (?, ?, NOW())");
                $stmt->execute([$floor, $newFilename]);
                set_alert("✅ อัพโหลดแปลนชั้น $floor สำเร็จ!", "success");
            }

            // ✅ ตรวจสอบผลลัพธ์การย้ายไฟล์
            if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
                set_alert("❌ ไม่สามารถย้ายไฟล์ไปยังโฟลเดอร์ uploads/ ได้ กรุณาตรวจสอบสิทธิ์", "danger");
            }
        } else {
            set_alert("❌ อนุญาตเฉพาะไฟล์ JPG, JPEG, PNG, GIF เท่านั้น", "danger");
        }
    } else {
        // ✅ แสดงข้อผิดพลาดของ $_FILES['error']
        $errorCode = $file['error'];
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => "ไฟล์มีขนาดใหญ่เกินค่า upload_max_filesize",
            UPLOAD_ERR_FORM_SIZE => "ไฟล์มีขนาดใหญ่เกินที่ฟอร์มกำหนด",
            UPLOAD_ERR_PARTIAL => "ไฟล์อัปโหลดมาไม่ครบ",
            UPLOAD_ERR_NO_FILE => "ไม่ได้เลือกไฟล์",
            UPLOAD_ERR_NO_TMP_DIR => "ไม่มีโฟลเดอร์ temp ในเซิร์ฟเวอร์",
            UPLOAD_ERR_CANT_WRITE => "ไม่สามารถเขียนไฟล์ลงดิสก์ได้",
            UPLOAD_ERR_EXTENSION => "ส่วนขยาย PHP ปฏิเสธการอัปโหลด",
        ];
        $msg = $errorMessages[$errorCode] ?? "เกิดข้อผิดพลาดไม่ทราบสาเหตุ (code $errorCode)";
        set_alert("❌ $msg", "danger");
    }

    header("Location: plan.php");
    exit();
}

// 📸 ส่วนลบภาพ
if (isset($_GET['delete'])) {
    $floor = (int)$_GET['delete'];
    $stmt = $conn->prepare("SELECT filename FROM plans WHERE floor = ?");
    $stmt->execute([$floor]);
    $row = $stmt->fetch();

    if ($row) {
        if (file_exists($uploadDir . $row['filename'])) {
            unlink($uploadDir . $row['filename']);
        }
        $stmt = $conn->prepare("DELETE FROM plans WHERE floor = ?");
        $stmt->execute([$floor]);
        set_alert("🗑️ ลบแปลนชั้น $floor สำเร็จ!", "warning");
    } else {
        set_alert("❌ ไม่พบข้อมูลแปลนชั้น $floor", "danger");
    }
    header("Location: plan.php");
    exit();
}

// 📸 ดึงข้อมูลทั้งหมด
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
body { font-family: "Segoe UI", sans-serif; background-color: #f8f9fa; }
.card-img-container img { max-height: 250px; }
.navbar { background: #343a40; color:#fff; padding:10px 20px; display:flex; align-items:center; }
.navbar a { color:#fff; text-decoration:none; margin-right:15px; font-weight:bold; }
.navbar a.active { color:#ffc107 !important; }
</style>
</head>
<body>

<!-- Navbar -->
<div class="navbar">
    <a href="index.php">🏠 หน้าแรก</a>
    <a href="building.php">🏢 อาคาร</a>
    <a href="plan.php" class="active">📐 แผนผัง</a>
    <a href="ipboard.php">📡 BOARD</a>
    <a href="logout.php" style="margin-left:auto; color:#ffc107;">🚪 ออกจากระบบ</a>
</div>

<div class="container py-5">
    <h2 class="text-center text-primary mb-4">📐 จัดการแปลนอาคาร (10 ชั้น)</h2>
    <?php display_alert(); ?>

    <?php for ($i = 10; $i >= 1; $i--): ?>
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">ชั้น <?= $i ?></h4>
        </div>
        <div class="card-body text-center">
            <?php if (isset($plansByFloor[$i])): ?>
                <div class="card-img-container mb-2">
                    <img src="uploads/<?= htmlspecialchars($plansByFloor[$i]['filename']) ?>" class="rounded shadow">
                </div>
                <p class="text-muted small">อัพโหลดเมื่อ: <?= $plansByFloor[$i]['uploaded_at'] ?></p>
                <a href="?delete=<?= $i ?>" onclick="return confirm('ลบแปลนชั้นนี้หรือไม่?')" class="btn btn-danger btn-sm w-100">🗑️ ลบรูป</a>
            <?php else: ?>
                <p class="text-muted my-4">ยังไม่มีแปลน</p>
            <?php endif; ?>
        </div>
        <div class="card-footer">
            <form method="post" enctype="multipart/form-data" class="d-flex flex-column flex-sm-row gap-2">
                <input type="hidden" name="floor" value="<?= $i ?>">
                <input type="file" name="plan" class="form-control" required>
                <button type="submit" name="upload" class="btn btn-success">อัพโหลด</button>
            </form>
        </div>
    </div>
    <?php endfor; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
