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
        // ฟิลเตอร์ input (คงเดิม)
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
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- ฟอนต์ & ไอคอน -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <style>
        :root{
            --brand:#0e6fff;
            --brand-2:#f59e0b;
            --accent:#22c55e;
            --text:#1f2937;
            --card-bg:rgba(255,255,255,.85);   /* ✅ ปรับให้กล่องสว่างขึ้น */
            --card-border:rgba(255,255,255,.45);
            --link:#1d4ed8;
            --link-hover:#1e40af;
        }

        *{box-sizing:border-box}
        html,body{height:100%}
        body {
            font-family: "Sarabun", Arial, sans-serif;
            color: var(--text);
            background: url('engineer.png') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            position: relative;
            padding: 24px;
            overflow-x: hidden;
            filter: brightness(1.05); /* ✅ เพิ่มความสว่างภาพรวม */
        }

        /* ✅ ปรับเลเยอร์มืดให้จางลง */
        body::before{
            content:"";
            position:fixed; inset:0;
            background:linear-gradient(to bottom, rgba(0,0,0,.05), rgba(0,0,0,.15));
            z-index:0;
        }
        body::after{
            content:"";
            position:fixed; inset:0;
            background:
              radial-gradient(800px 400px at 80% 15%, rgba(255,255,255,.22), transparent 60%),
              radial-gradient(600px 300px at 15% 85%, rgba(14,111,255,.12), transparent 55%);
            z-index:0;
            pointer-events:none;
        }

        h2, input, button, .system-title, .link-line, .public-btn {
            font-family: "Sarabun", Arial, sans-serif !important;
        }

        .bg-logo {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 110px;
            height: auto;
            z-index: 2;
            filter: drop-shadow(0 0 10px rgba(255,255,255,.85))
                    drop-shadow(0 6px 14px rgba(0,0,0,.25));
            pointer-events: none;
            animation: floatLogo 4s ease-in-out infinite alternate;
        }
        @keyframes floatLogo{
            from{ transform: translateX(-50%) translateY(0) }
            to  { transform: translateX(-50%) translateY(-8px) }
        }

        .system-title {
            position: fixed;
            top: 225px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 26px;
            font-weight: 700;
            color: #222;
            background: rgba(255,255,255,.92);
            padding: 10px 22px;
            border-radius: 999px;
            border: 1px solid var(--card-border);
            box-shadow: 0 8px 24px rgba(0,0,0,.18);
            z-index: 2;
            display:flex; align-items:center; gap:10px;
            backdrop-filter: blur(6px) saturate(140%);
        }
        .system-title .dot{
            width:10px; height:10px; border-radius:999px; background:var(--brand-2);
            box-shadow:0 0 0 6px rgba(245,158,11,.18);
        }

        form {
            background: var(--card-bg);
            padding: 28px 24px 24px;
            border-radius: 18px;
            border: 1px solid var(--card-border);
            backdrop-filter: blur(12px) saturate(180%);
            box-shadow: 0 20px 45px rgba(0,0,0,.28);
            width: 100%;
            max-width: 420px;
            text-align: center;
            margin-top: 250px;
            position: relative;
            z-index: 1;
            animation: fadeInUp .8s ease-out both;
        }
        @keyframes fadeInUp{
            from{ opacity:0; transform: translateY(36px) }
            to  { opacity:1; transform: translateY(0) }
        }

        h2{
            margin:0 0 14px;
            font-size:23px; font-weight:700;
            letter-spacing:.3px;
            color:#111827;
            text-shadow: 0 1px 0 rgba(255,255,255,.6);
        }
        .divider{
            height:1px; width:100%;
            background:linear-gradient(90deg, transparent, rgba(0,0,0,.10), transparent);
            margin:10px 0 18px;
        }

        input {
            width: 100%;
            padding: 12px 14px;
            margin: 10px 0;
            border: 1.2px solid #d1d5db;
            border-radius: 12px;
            background: #fff;
            font-size: 15px;
            outline: none;
            transition: border-color .15s, box-shadow .15s, transform .08s;
            box-shadow: 0 2px 6px rgba(0,0,0,.05) inset;
        }
        input:hover{ box-shadow: 0 0 0 3px rgba(14,111,255,.06) }
        input:focus{
            border-color: var(--brand);
            box-shadow: 0 0 0 4px rgba(14,111,255,.15);
        }

        button {
            background: linear-gradient(270deg, #16a34a, #22c55e, #10b981);
            background-size: 200% 200%;
            color: white;
            padding: 12px 14px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            width: 100%;
            font-weight: 700;
            font-size: 15px;
            box-shadow: 0 10px 22px rgba(34,197,94,.35);
            transition: transform .06s ease, filter .15s, box-shadow .15s;
            animation: gradientMove 3s ease infinite;
        }
        @keyframes gradientMove{
            0%{background-position:0% 50%}
            50%{background-position:100% 50%}
            100%{background-position:0% 50%}
        }
        button:hover { filter: brightness(1.03); box-shadow: 0 14px 26px rgba(34,197,94,.45); }
        button:active { transform: translateY(1px); }

        .error {
            color: #dc2626;
            background:#fee2e2;
            border:1px solid #fecaca;
            padding:10px 12px;
            border-radius:10px;
            margin-bottom:12px;
            font-size:14px;
            text-align: left;
            box-shadow: 0 6px 12px rgba(220,38,38,.08);
        }

        .link-line { margin-top: 12px; }
        .link-line a {
            display:inline-block;
            color: var(--link);
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            background: rgba(255,255,255,.55);
            padding:6px 12px;
            border-radius:10px;
            box-shadow:0 6px 14px rgba(0,0,0,.10);
            transition: transform .15s, background .15s, color .15s;
        }
        .link-line a:hover { color:#fff; background: #2563eb; transform: translateY(-2px); }

        .public-btn {
            display:block;
            text-decoration:none;
            margin-top:14px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color:white;
            padding:12px 14px;
            border-radius:12px;
            font-size:15px;
            font-weight:800;
            letter-spacing:.2px;
            box-shadow: 0 10px 22px rgba(37,99,235,.35);
            transition: transform .06s ease, filter .15s, box-shadow .15s;
        }
        .public-btn:hover { filter: brightness(1.03); box-shadow: 0 14px 26px rgba(37,99,235,.45); }
        .public-btn:active { transform: translateY(1px); }

        input:focus-visible, button:focus-visible, .public-btn:focus-visible, .link-line a:focus-visible{
            outline: 3px solid #0e6fff55; outline-offset: 3px;
        }

        @media (max-width:480px){
            .system-title{ top: 170px; font-size:18px }
            form{ margin-top: 220px; padding: 24px }
        }
    </style>
</head>
<body>
    <img src="rmutsv-logo.png"" alt="RMUTSV Logo" class="bg-logo">

    <div class="system-title"><span class="dot"></span> ระบบบริหารจัดการแสงสว่างในอาคารด้วยฐานข้อมูล</div>

    <form method="post" action="">
        <h2>เข้าสู่ระบบ</h2>
        <div class="divider"></div>

        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
        
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

        <input type="text" name="username" placeholder="ชื่อผู้ใช้" required autocomplete="username" autofocus>
        <input type="password" name="password" placeholder="รหัสผ่าน" required autocomplete="current-password">
        <button type="submit">เข้าสู่ระบบ</button>

        <p class="link-line"><a href="register.php"><i class="fa-regular fa-id-card"></i> สมัครสมาชิก</a></p>

        <!--<a href="public_control.php" class="public-btn">
            <i class="fa-solid fa-plug"></i> เข้าสู่หน้าควบคุมบุคคลทั่วไป
        </a> -->
    </form>
</body>
</html>
