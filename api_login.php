<?php
header('Content-Type: application/json; charset=utf-8');
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Invalid request"]);
    exit();
}

$username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
$password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);

$stmt = $conn->prepare("SELECT * FROM users WHERE username=? LIMIT 1");
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {
    if ($user['role'] !== 'admin' && $user['status'] !== 'active') {
        echo json_encode([
            "success" => false,
            "message" => "บัญชียังไม่ผ่านการอนุมัติจากผู้ดูแลระบบ"
        ]);
    } else {
        echo json_encode([
            "success" => true,
            "user" => [
                "id" => $user['id'],
                "username" => $user['username'],
                "fullname" => $user['fullname'],
                "role" => $user['role'],
                "status" => $user['status']
            ]
        ]);
    }
} else {
    echo json_encode(["success" => false, "message" => "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง"]);
}
?>
