<?php
session_start();
include 'db.php';

// สร้าง CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ตรวจสอบ CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }
    
    // ฟิลเตอร์ input
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = 'user';
    $status = 'pending';

    $stmt = $conn->prepare("INSERT INTO users (email, username, password, role, status) VALUES (?, ?, ?, ?, ?)");
    
    try {
        if ($stmt->execute([$email, $username, $password, $role, $status])) {
            header("Location: login.php?success=1");
            exit();
        }
    } catch (PDOException $e) {
        $error = "ชื่อผู้ใช้หรืออีเมลมีอยู่แล้ว!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>สมัครสมาชิก</title>
    <style>
        body {font-family: Arial; background:#f4f4f4; display:flex; justify-content:center; align-items:center; min-height:100vh;}
        .container {background:#fff; padding:20px; border-radius:8px; box-shadow:0 0 10px rgba(0,0,0,0.1); width:300px; text-align:center;}
        input {width:100%; padding:10px; margin:8px 0; border:1px solid #ccc; border-radius:4px;}
        button {background:#007bff; color:white; padding:10px; border:none; border-radius:4px; cursor:pointer; width:100%;}
        button:hover {opacity:0.9;}
        .error {color:red; margin-top:10px;}
    </style>
</head>
<body>
<div class="container">
    <h2>สมัครสมาชิก</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="email" name="email" placeholder="อีเมล" required><br>
        <input type="text" name="fullname"placeholder="ชื่อ-นามสกุล"required><br>
        <input type="text" name="username" placeholder="ชื่อผู้ใช้" required><br>
        <input type="password" name="password" placeholder="รหัสผ่าน" required><br>
        <button type="submit">สมัครสมาชิก</button>
    </form>
    <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
    <p><a href="login.php">กลับไปหน้า Login</a></p>
</div>
</body>
</html>