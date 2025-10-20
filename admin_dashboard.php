<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header("Location: login.php");
  exit();
}

// ตรวจสอบว่ามีการตั้งค่า theme ในคุกกี้หรือไม่
$currentTheme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'default';

// ฟังก์ชันดึงค่าสีตามธีม
function getThemeStyles($theme) {
    $themes = [
        'default' => [
            'primary' => '#4b6cb7',
            'secondary' => '#182848',
            'accent' => '#ff6b6b',
            'text' => '#333',
            'background' => 'linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%)',
            'card-bg' => '#ffffff',
            'card-border' => '#eaeaea'
        ],
        'dark' => [
            'primary' => '#2d3748',
            'secondary' => '#1a202c',
            'accent' => '#e53e3e',
            'text' => '#e2e8f0',
            'background' => 'linear-gradient(135deg, #2d3748 0%, #1a202c 100%)',
            'card-bg' => '#2d3748',
            'card-border' => '#4a5568'
        ],
        'green' => [
            'primary' => '#38a169',
            'secondary' => '#2f855a',
            'accent' => '#dd6b20',
            'text' => '#2d3748',
            'background' => 'linear-gradient(135deg, #f0fff4 0%, #c6f6d5 100%)',
            'card-bg' => '#ffffff',
            'card-border' => '#eaeaea'
        ],
        'purple' => [
            'primary' => '#805ad5',
            'secondary' => '#6b46c1',
            'accent' => '#ed64a6',
            'text' => '#322659',
            'background' => 'linear-gradient(135deg, #faf5ff 0%, #e9d8fd 100%)',
            'card-bg' => '#ffffff',
            'card-border' => '#eaeaea'
        ]
    ];
    return $themes[$theme] ?? $themes['default'];
}

$themeStyles = getThemeStyles($currentTheme);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>แดชบอร์ดผู้ดูแลระบบ</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;500;700&display=swap" rel="stylesheet" />
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Kanit', sans-serif; }
    body {
      background: <?php echo $themeStyles['background']; ?>;
      min-height: 100vh; padding: 20px;
      color: <?php echo $themeStyles['text']; ?>;
    }
    .container {
      max-width: 1200px; margin: 0 auto;
      background-color: white; border-radius: 15px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); overflow: hidden;
    }
    .header {
      background: linear-gradient(to right, <?php echo $themeStyles['primary']; ?>, <?php echo $themeStyles['secondary']; ?>);
      color: white; padding: 30px; text-align: center;
    }
    .header h1 { font-weight: 700; margin-bottom: 10px; font-size: 2.2rem; }
    .menu-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px; padding: 30px;
    }
    .menu-card {
      background: <?php echo $themeStyles['card-bg']; ?>;
      border-radius: 10px; padding: 25px; text-align: center;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
      transition: transform 0.3s, box-shadow 0.3s;
      border: 1px solid <?php echo $themeStyles['card-border']; ?>;
    }
    .menu-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }
    .menu-card i { font-size: 2.5rem; margin-bottom: 15px; display: block; color: <?php echo $themeStyles['primary']; ?>; }
    .menu-card a {
      display: block; color: <?php echo $themeStyles['text']; ?>;
      text-decoration: none; font-weight: 500; font-size: 1.1rem;
      padding: 10px; border-radius: 5px;
      transition: background-color 0.3s, color 0.3s;
    }
    .menu-card a:hover {
      background-color: <?php echo $themeStyles['primary']; ?>;
      color: white;
    }
    .logout { text-align: center; padding: 20px; border-top: 1px solid <?php echo $themeStyles['card-border']; ?>; }
    .logout a {
      display: inline-block; padding: 12px 25px;
      background-color: <?php echo $themeStyles['accent']; ?>;
      color: white; text-decoration: none; border-radius: 5px;
      font-weight: 500; transition: background-color 0.3s;
    }
    .logout a:hover { opacity: 0.85; }
    .logout i { margin-right: 8px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>👨‍💼 ยินดีต้อนรับผู้ดูแล: <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
      <p>โปรดเลือกเมนูการจัดการที่ต้องการใช้งาน</p>
    </div>

    <div class="menu-grid">
      <div class="menu-card">
        <i class="fas fa-users-cog"></i>
        <a href="index.php">จัดการอาคาร</a>
      </div>
      <div class="menu-card">
        <i class="fas fa-user-check"></i>
        <a href="admin_approval.php">ดูผู้ใช้</a>
      </div>
      <div class="menu-card">
        <i class="fas fa-database"></i>
        <a href="user_data.php">จัดการผู้ใช้</a>
      </div>
      <div class="menu-card">
        <i class="fas fa-clipboard-list"></i>
        <a href="system_logs.php">บันทึกระบบ</a>
      </div>
      <div class="menu-card">
        <i class="fas fa-calendar-alt"></i>
        <a href="generate_token.php">QR-Code All Room</a>
      </div>

      <!-- ✅ ปุ่มรีเซ็ต ID -->
      <div class="menu-card">
        <i class="fas fa-tools"></i>
        <a href="fix_switch_id.php" onclick="return confirm('⚠️ แน่ใจหรือไม่ว่าจะรีเซ็ตรหัส ID ของฐานข้อมูล? ควรสำรองข้อมูลก่อนดำเนินการ!');">
          รีเซ็ตลำดับ ID ฐานข้อมูล
        </a>
      </div>

      <div class="menu-card">
        <i class="fas fa-cog"></i>
        <a href="settings.php">ตั้งค่าระบบ</a>
      </div>

      <div class="menu-card">
        <i class="fas fa-tools"></i>
        <a href="reset_password_admin.php" >
          แก้ password
        </a>
      </div>

    </div>

    <div class="logout">
      <a href="logout.php"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
    </div>
  </div>
</body>
</html>
