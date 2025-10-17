<?php
require 'db.php';
session_start();

// ✅ ตรวจสอบสิทธิ์เฉพาะ admin เท่านั้น
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("⛔ Access Denied: Only admin can run this script");
}

echo "<h2>🔧 ระบบรีเซ็ตลำดับ ID อัตโนมัติ</h2>";

try {
    $conn->beginTransaction();

    // ปิดการตรวจสอบ foreign key ชั่วคราว
    $conn->exec("SET FOREIGN_KEY_CHECKS = 0;");

    $tables = ['rooms', 'switches', 'switch_logs', 'esp_logs'];

    foreach ($tables as $tbl) {
        echo "<p>🛠️ กำลังรีเซ็ตตาราง <b>$tbl</b> ...</p>";

        // 1️⃣ รีเซ็ตลำดับ ID ใหม่
        $conn->exec("
            SET @count = 0;
            UPDATE `$tbl` SET `$tbl`.`id` = @count:=@count+1 ORDER BY `$tbl`.`id`;
        ");

        // 2️⃣ คำนวณค่า AUTO_INCREMENT ถัดไป
        $maxID = $conn->query("SELECT MAX(id) AS maxid FROM `$tbl`")->fetch(PDO::FETCH_ASSOC)['maxid'] ?? 0;
        $nextID = $maxID + 1;
        $conn->exec("ALTER TABLE `$tbl` AUTO_INCREMENT = $nextID;");

        echo "<p style='color:green'>✅ $tbl → ID ล่าสุดคือ <b>$maxID</b>, AUTO_INCREMENT ตั้งเป็น <b>$nextID</b></p>";
    }

    // เปิด foreign key checks กลับ
    $conn->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    // ✅ commit ปลอดภัย
    if ($conn->inTransaction()) {
        $conn->commit();
    }

    echo "<hr><h3 style='color:green'>🎉 เสร็จสิ้น! ระบบรีเซ็ตลำดับ ID สำเร็จทั้งหมด</h3>";
    echo "<a href='admin_dashboard.php' style='color:#4b6cb7;text-decoration:none;font-weight:bold;'>⬅️ กลับไปหน้าแดชบอร์ด</a>";

} catch (Exception $e) {
    // ✅ ป้องกัน rollback error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "<p style='color:red'>❌ เกิดข้อผิดพลาด: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
