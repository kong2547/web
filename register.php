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
    $fullname = filter_input(INPUT_POST, 'fullname', FILTER_SANITIZE_STRING);
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = 'user';
    $status = 'pending';

    // ✅ เพิ่ม fullname เข้าใน INSERT statement
    $stmt = $conn->prepare("INSERT INTO users (email, fullname, username, password, role, status) VALUES (?, ?, ?, ?, ?, ?)");
    
    try {
        if ($stmt->execute([$email, $fullname, $username, $password, $role, $status])) {
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
        body {
            font-family: Arial;
            background: url('engineer.png') no-repeat center center fixed;
            background-size: cover;
            display:flex; justify-content:center; align-items:center;
            min-height:100vh; margin:0;
        }
        .container {
            background: rgba(255,255,255,0.9);
            padding:20px;
            border-radius:8px;
            box-shadow:0 0 15px rgba(0,0,0,0.2);
            width:320px;
            text-align:center;
            backdrop-filter: blur(8px);
        }
        h2 { margin-bottom: 15px; color:#333; }
        input, button {
            width:100%;
            padding:10px;
            margin:8px 0;
            border-radius:4px;
            box-sizing: border-box;
        }
        input { border:1px solid #ccc; }
        button {
            background:#007bff;
            color:white;
            border:none;
            cursor:pointer;
            font-weight:bold;
        }
        button:hover { background:#0056b3; }
        .error {color:red; margin-top:10px;}
        a {color:#007bff; text-decoration:none;}
        a:hover {text-decoration: underline;}
    </style>
</head>
<body>
<div class="container">
    <h2>สมัครสมาชิก</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="email" name="email" placeholder="อีเมล" required>
        <input type="text" name="fullname" placeholder="ชื่อ-นามสกุล" required>
        <input type="text" name="username" placeholder="ชื่อผู้ใช้" required>
        <input type="password" name="password" placeholder="รหัสผ่าน" required>
        <button type="submit">สมัครสมาชิก</button>
    </form>
    <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
    <p><a href="login.php">กลับไปหน้า Login</a></p>
</div>
</body>
</html>
