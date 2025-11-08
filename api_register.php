<?php
header('Content-Type: application/json; charset=utf-8');
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Invalid request"]);
    exit();
}

$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$fullname = filter_input(INPUT_POST, 'fullname', FILTER_SANITIZE_STRING);
$username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);
$role = 'user';
$status = 'pending';

// ตรวจสอบซ้ำ
$check = $conn->prepare("SELECT COUNT(*) FROM users WHERE username=? OR email=?");
$check->execute([$username, $email]);
if ($check->fetchColumn() > 0) {
    echo json_encode(["success" => false, "message" => "ชื่อผู้ใช้หรืออีเมลนี้มีอยู่แล้ว"]);
    exit();
}

$stmt = $conn->prepare("INSERT INTO users (email, fullname, username, password, role, status)
                        VALUES (?, ?, ?, ?, ?, ?)");
try {
    $stmt->execute([$email, $fullname, $username, $password, $role, $status]);
    echo json_encode(["success" => true, "message" => "สมัครสมาชิกสำเร็จ! กรุณารอการอนุมัติจากผู้ดูแลระบบ"]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "ไม่สามารถสมัครสมาชิกได้"]);
}
?>
