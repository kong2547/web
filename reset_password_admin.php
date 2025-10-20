<?php
// reset_password_admin.php
// เพิ่มฟีเจอร์: แสดงรหัสชั่วคราว, dropdown user, และ HTTP Basic Auth
// ปรับค่า HTTP AUTH ด้านล่างก่อนใช้งาน
// !! ลบไฟล์นี้เมื่อใช้งานเสร็จ !!

session_start();
require 'db.php'; // <-- ปรับ path ให้ตรงกับโปรเจกต์ของคุณ (เช่น '../db.php')

// -------------------- CONFIG: HTTP BASIC AUTH --------------------
$HTTP_AUTH_USER = 'admin';    // <-- เปลี่ยนค่า
$HTTP_AUTH_PASS = 'admin123'; // <-- เปลี่ยนค่าให้แข็งแรง

// Handle HTTP Basic Auth (simple)
if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="Admin Reset Password"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Authentication required.';
    exit;
} else {
    if ($_SERVER['PHP_AUTH_USER'] !== $HTTP_AUTH_USER || $_SERVER['PHP_AUTH_PW'] !== $HTTP_AUTH_PASS) {
        header('WWW-Authenticate: Basic realm="Admin Reset Password"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Invalid credentials.';
        exit;
    }
}

// -------------------- CHECK SESSION ADMIN --------------------
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "Access denied. You must be logged in as admin to access this page.";
    exit;
}

// CSRF token
if (empty($_SESSION['csrf_token_resetpw'])) {
    $_SESSION['csrf_token_resetpw'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token_resetpw'];

$errors = [];
$success = '';
$show_temp_password = false;
$temp_password_shown = ''; // temporary password to display

// Fetch users for dropdown (id, username, fullname)
try {
    $uQ = $conn->prepare("SELECT id, username, COALESCE(fullname,'') AS fullname FROM users ORDER BY id ASC");
    $uQ->execute();
    $all_users = $uQ->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $all_users = [];
    $errors[] = "ไม่สามารถดึงรายชื่อผู้ใช้ได้: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token_resetpw']) {
        $errors[] = "Invalid CSRF token.";
    } else {
        // identifier can come from dropdown 'user_select' or manual 'identifier'
        $identifier = trim($_POST['user_select'] ?? '');
        $manual_identifier = trim($_POST['identifier'] ?? '');
        if ($identifier === '' && $manual_identifier !== '') {
            $identifier = $manual_identifier;
        }

        $use_random = isset($_POST['use_random']) ? true : false;
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // If user chose random password generation, create temp password
        if ($use_random) {
            // generate a reasonably strong temporary password
            $new_password = bin2hex(random_bytes(6)); // 12 hex chars (~12 char)
            // To add more entropy and variety, mix with readable chars:
            $new_password .= substr(str_shuffle('!@#$%^&*AaBbCcDdEe1234567890'), 0, 2); // now ~14 chars
            $confirm_password = $new_password;
        }

        if ($identifier === '') {
            $errors[] = "กรุณาเลือกหรือใส่ User ID / Username";
        }
        if (strlen($new_password) < 1) {
    $errors[] = "กรุณากรอกรหัสผ่าน (ห้ามว่างเปล่า)";
}

        if ($new_password !== $confirm_password) {
            $errors[] = "รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน";
        }

        if (empty($errors)) {
            // Find user by id or username
            if (ctype_digit($identifier)) {
                $stmt = $conn->prepare("SELECT id, username, fullname FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$identifier]);
            } else {
                $stmt = $conn->prepare("SELECT id, username, fullname FROM users WHERE username = ? LIMIT 1");
                $stmt->execute([$identifier]);
            }
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $errors[] = "ไม่พบผู้ใช้ที่ระบุ";
            } else {
                // create hash
                if (defined('PASSWORD_ARGON2ID')) {
                    $hash = password_hash($new_password, PASSWORD_ARGON2ID);
                } else {
                    $hash = password_hash($new_password, PASSWORD_BCRYPT);
                }

                // update
                $u_stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $ok = $u_stmt->execute([$hash, $user['id']]);

                if ($ok) {
                    // optional log if logs table exist
                    try {
                        $admin_user = $_SESSION['username'] ?? 'admin';
                        $note = "Reset password for user_id={$user['id']} ({$user['username']}) by {$admin_user}";
                        $log_stmt = $conn->prepare("INSERT INTO logs (username, action, note, created_at) VALUES (?, ?, ?, NOW())");
                        $log_stmt->execute([$admin_user, 'reset_password', $note]);
                    } catch (Exception $e) {
                        // ignore logging errors
                    }

                    $show_temp_password = true;
                    $temp_password_shown = $new_password;
                    $success = "รีเซ็ตรหัสผ่านสำเร็จสำหรับผู้ใช้: " . htmlspecialchars($user['username']);
                } else {
                    $errors[] = "อัปเดตฐานข้อมูลล้มเหลว";
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>Admin Reset Password (Protected)</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="card mx-auto" style="max-width:900px;">
        <div class="card-body">
            <h4 class="card-title mb-3">Reset Password (Admin only) — Protected by HTTP Auth & Session</h4>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                    <?php foreach ($errors as $e): ?>
                        <li><?php echo htmlspecialchars($e); ?></li>
                    <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <?php if ($show_temp_password): ?>
                        <hr>
                        <p class="mb-0"><strong>รหัสชั่วคราว:</strong>
                            <span class="badge bg-warning text-dark" id="tempPass"><?php echo htmlspecialchars($temp_password_shown); ?></span>
                            <button class="btn btn-sm btn-outline-secondary ms-2" onclick="copyTemp()">คัดลอกรหัส</button>
                            <small class="text-muted d-block mt-1">แนะนำให้ผู้ใช้เปลี่ยนรหัสทันทีหลังล็อกอิน</small>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="post" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="col-12">
                    <label class="form-label">เลือกผู้ใช้ (dropdown)</label>
                    <select id="user_select" name="user_select" class="form-select" onchange="onUserSelectChange()">
                        <option value="">-- เลือกผู้ใช้ --</option>
                        <?php foreach ($all_users as $u): ?>
                            <option value="<?php echo htmlspecialchars($u['id']); ?>">
                                <?php echo htmlspecialchars($u['id'] . ' — ' . $u['username'] . ' ' . ($u['fullname'] ? '(' . $u['fullname'] . ')' : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">หรือพิมพ์ ID / username ด้านล่างเพื่อค้นหา/ระบุเอง</div>
                </div>

                <div class="col-12">
                    <label class="form-label">User ID หรือ Username (ถ้าต้องการระบุเอง)</label>
                    <input type="text" name="identifier" id="identifier" class="form-control" placeholder="ใส่ id เช่น 1 หรือ username เช่น user01" value="<?php echo isset($_POST['identifier']) ? htmlspecialchars($_POST['identifier']) : ''; ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">รหัสผ่านใหม่</label>
                    <input type="password" name="new_password" id="new_password" class="form-control" placeholder="รหัสผ่านใหม่ กรุณากรอกรหัสผ่าน (ห้ามว่างเปล่า)">
                </div>

                <div class="col-md-6">
                    <label class="form-label">ยืนยันรหัสผ่าน</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="ยืนยันรหัสผ่านใหม่">
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="use_random" name="use_random" onchange="onUseRandomChange()">
                        <label class="form-check-label" for="use_random">
                            สร้างรหัสชั่วคราวอัตโนมัติ (Random temporary password)
                        </label>
                    </div>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">รีเซ็ตรหัสผ่าน</button>
                    <a href="admin_dashboard.php" class="btn btn-secondary">ย้อนกลับ</a>
                    <!--<button type="button" class="btn btn-outline-danger float-end" onclick="promptDeleteFile()">แนะนำ: ลบไฟล์นี้หลังใช้งาน</button>-->
                </div>
            </form>

            <hr>
            <p class="small text-muted">หมายเหตุความปลอดภัย: หลังรีเซ็ต ให้ผู้ใช้เปลี่ยนรหัสผ่านทันที เขียนบันทึกเหตุการณ์ไว้ในระบบ และลบไฟล์นี้หากไม่ต้องใช้งานถาวร</p>
        </div>
    </div>
</div>

<script>
function onUserSelectChange(){
    var sel = document.getElementById('user_select');
    var id = sel.value;
    if(id){
        document.getElementById('identifier').value = id;
    }
}
function onUseRandomChange(){
    var chk = document.getElementById('use_random');
    if(chk.checked){
        // disable password fields to avoid confusion
        document.getElementById('new_password').value = '';
        document.getElementById('confirm_password').value = '';
        document.getElementById('new_password').disabled = true;
        document.getElementById('confirm_password').disabled = true;
    } else {
        document.getElementById('new_password').disabled = false;
        document.getElementById('confirm_password').disabled = false;
    }
}
function copyTemp(){
    var text = document.getElementById('tempPass').innerText;
    navigator.clipboard.writeText(text).then(function(){
        alert('คัดลอกรหัสชั่วคราวแล้ว');
    }, function(){
        alert('ไม่สามารถคัดลอกได้');
    });
}
function promptDeleteFile(){
    if(confirm('แนะนำให้ลบไฟล์นี้เมื่อใช้งานเสร็จ (OK เพื่อดูคำสั่งลบ)')) {
        alert('คำสั่งลบ (บน Windows/XAMPP):\n1) ปิด Apache\n2) ลบไฟล์ reset_password_admin.php จากโฟลเดอร์ htdocs/yourproject\n\nบน Linux/Ubuntu: ใช้คำสั่ง:\nsudo rm /path/to/yourproject/reset_password_admin.php');
    }
}
</script>
</body>
</html>
