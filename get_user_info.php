<?php
header('Content-Type: application/json; charset=utf-8');
include 'db.php';

// ✅ ต้องใช้ GET เท่านั้น
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    echo json_encode(["success" => false, "message" => "Invalid request"]);
    exit();
}

// ✅ รับชื่อผู้ใช้จากพารามิเตอร์
if (!isset($_GET['username']) || empty($_GET['username'])) {
    echo json_encode(["success" => false, "message" => "Missing username"]);
    exit();
}

$username = filter_input(INPUT_GET, 'username', FILTER_SANITIZE_STRING);

// ✅ ดึงข้อมูล fullname และ email จากฐานข้อมูล
$stmt = $conn->prepare("SELECT fullname, email FROM users WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo json_encode([
        "success" => true,
        "fullname" => $user['fullname'],
        "email" => $user['email']
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "User not found"
    ]);
}
?>
