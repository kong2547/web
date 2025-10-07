<?php
session_start();
include 'db.php';

// ตรวจสอบความพยายามล็อกอิน
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt'] = time();
}

// สร้าง CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ตรวจสอบ CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }
    
    // ตรวจสอบความพยายามล็อกอิน
    if ($_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['last_attempt']) < 300) {
        $error = "คุณพยายามล็อกอินเกินกำหนด กรุณารอ 5 นาที";
    } else {
        // ฟิลเตอร์ input
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
        $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);

        $stmt = $conn->prepare("SELECT * FROM users WHERE username=? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['role'] !== 'admin' && $user['status'] !== 'active') {
                $error = "บัญชียังไม่ผ่านการอนุมัติจากผู้ดูแลระบบ";
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt'] = time();
            } else {
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['fullname'] = $user['username'];
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['login_attempts'] = 0;

                // บันทึกการล็อกอิน
                $action = "login";
                $stmt = $conn->prepare("INSERT INTO logs (username, action) VALUES (?, ?)");
                $stmt->execute([$user['username'], $action]);

                if ($user['role'] == 'admin') {
                    header("Location: admin_dashboard.php");
                } else {
                    header("Location: user_dashboard.php");
                }
                exit();
            }
        } else {
            $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt'] = time();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เข้าสู่ระบบ</title>
    <style>
        body {
            font-family: Arial;
            background: url('engineer.png') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        form {
            background:#fff;
            padding:20px;
            border-radius:8px;
            box-shadow:0 0 10px rgba(0,0,0,0.1);
            width:300px;
            text-align:center;
        }
        input {
            width:100%;
            padding:10px;
            margin:8px 0;
            border:1px solid #ccc;
            border-radius:4px;
            box-sizing:border-box;
        }
        button {
            background:#5cb85c;
            color:white;
            padding:10px;
            border:none;
            border-radius:4px;
            cursor:pointer;
            width:100%;
        }
        button:hover {opacity:0.9;}
        .error {color:red; margin-bottom:10px;}
        /* 🔹 ปุ่มบุคคลทั่วไป */
        .public-btn {
            display:block;
            text-decoration:none;
            margin-top:15px;
            background:#007bff;
            color:white;
            padding:10px;
            border-radius:4px;
            font-size:14px;
        }
        .public-btn:hover {
            background:#0056b3;
        }
    </style>
</head>
<body>
    <form method="post" action="">
        <h2>เข้าสู่ระบบ</h2>
        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
        
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="text" name="username" placeholder="ชื่อผู้ใช้" required><br>
        <input type="password" name="password" placeholder="รหัสผ่าน" required><br>
        <button type="submit">เข้าสู่ระบบ</button><br>
        <p><a href="register.php">สมัครสมาชิก</a></p>

        <!-- 🔹 ปุ่มเข้าสู่หน้าบุคคลทั่วไป -->
        <a href="public_control.php" class="public-btn">
            🔌 เข้าสู่หน้าควบคุมบุคคลทั่วไป
        </a>
    </form>
</body>
</html>
