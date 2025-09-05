<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header("Location: login.php");
  exit();
}

// ฟังก์ชันจัดการธีมสี
function getTheme() {
    if (isset($_COOKIE['theme'])) {
        return $_COOKIE['theme'];
    }
    return 'default';
}

function setTheme($theme) {
    setcookie('theme', $theme, time() + (86400 * 30), "/"); // 30 วัน
    header("Location: settings.php");
    exit();
}

// เปลี่ยนธีมเมื่อมีการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['theme'])) {
    setTheme($_POST['theme']);
}

$currentTheme = getTheme();

// ฟังก์ชันสร้างสไตล์ตามธีม
function getThemeStyles($theme) {
    $themes = [
        'default' => [
            'primary' => '#4b6cb7',
            'secondary' => '#182848',
            'accent' => '#ff6b6b',
            'text' => '#333',
            'background' => 'linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%)'
        ],
        'dark' => [
            'primary' => '#2d3748',
            'secondary' => '#1a202c',
            'accent' => '#e53e3e',
            'text' => '#e2e8f0',
            'background' => 'linear-gradient(135deg, #2d3748 0%, #1a202c 100%)'
        ],
        'green' => [
            'primary' => '#38a169',
            'secondary' => '#2f855a',
            'accent' => '#dd6b20',
            'text' => '#2d3748',
            'background' => 'linear-gradient(135deg, #f0fff4 0%, #c6f6d5 100%)'
        ],
        'purple' => [
            'primary' => '#805ad5',
            'secondary' => '#6b46c1',
            'accent' => '#ed64a6',
            'text' => '#322659',
            'background' => 'linear-gradient(135deg, #faf5ff 0%, #e9d8fd 100%)'
        ]
    ];
    
    return $themes[$theme] ?? $themes['default'];
}

$themeStyles = getThemeStyles($currentTheme);

// ฟังก์ชันช่วยแปลง HEX เป็น RGB
function hex2rgb($hex) {
    $hex = str_replace("#", "", $hex);
    if(strlen($hex) == 3) {
        $r = hexdec(substr($hex,0,1).substr($hex,0,1));
        $g = hexdec(substr($hex,1,1).substr($hex,1,1));
        $b = hexdec(substr($hex,2,1).substr($hex,2,1));
    } else {
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));
    }
    return [$r, $g, $b];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>ตั้งค่าระบบ - เปลี่ยนธีมสี</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;500;700&display=swap" rel="stylesheet" />
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Kanit', sans-serif;
    }

    body {
      background: <?php echo $themeStyles['background']; ?>;
      min-height: 100vh;
      padding: 20px;
      color: <?php echo $themeStyles['text']; ?>;
    }

    .container {
      max-width: 1200px;
      margin: 0 auto;
      background-color: white;
      border-radius: 15px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      overflow: hidden;
    }

    .header {
      background: linear-gradient(to right, <?php echo $themeStyles['primary']; ?>, <?php echo $themeStyles['secondary']; ?>);
      color: white;
      padding: 30px;
      text-align: center;
    }

    .header h1 {
      font-weight: 700;
      margin-bottom: 10px;
      font-size: 2.2rem;
    }

    .header p {
      font-weight: 300;
      opacity: 0.9;
    }

    .content {
      padding: 30px;
    }

    .back-button {
      display: inline-block;
      margin-bottom: 20px;
      padding: 10px 20px;
      background-color: <?php echo $themeStyles['primary']; ?>;
      color: white;
      text-decoration: none;
      border-radius: 5px;
      font-weight: 500;
      transition: background-color 0.3s;
    }

    .back-button:hover {
      background-color: <?php echo $themeStyles['secondary']; ?>;
    }

    .back-button i {
      margin-right: 8px;
    }

    .theme-options {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-top: 20px;
    }

    .theme-option {
      border: 2px solid #eaeaea;
      border-radius: 10px;
      overflow: hidden;
      transition: transform 0.3s, box-shadow 0.3s;
      cursor: pointer;
    }

    .theme-option:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    .theme-option input[type="radio"] {
      display: none;
    }

    .theme-option input[type="radio"]:checked + label {
      border-color: <?php echo $themeStyles['accent']; ?>;
      box-shadow: 0 0 0 3px rgba(<?php echo join(',', hex2rgb($themeStyles['accent'])); ?>, 0.3);
    }

    .theme-option label {
      display: block;
      padding: 20px;
      cursor: pointer;
      background: white;
      border: 2px solid transparent;
      border-radius: 10px;
    }

    .theme-preview {
      height: 100px;
      border-radius: 8px;
      margin-bottom: 15px;
      display: flex;
    }

    .theme-primary {
      flex: 7;
      background: <?php echo $themeStyles['primary']; ?>;
    }

    .theme-secondary {
      flex: 3;
      background: <?php echo $themeStyles['secondary']; ?>;
    }

    .theme-name {
      font-weight: 500;
      text-align: center;
      margin-bottom: 5px;
    }

    .theme-colors {
      display: flex;
      justify-content: center;
      gap: 5px;
      margin-top: 10px;
    }

    .color-dot {
      width: 20px;
      height: 20px;
      border-radius: 50%;
      display: inline-block;
    }

    .theme-actions {
      margin-top: 30px;
      text-align: center;
    }

    .btn-save {
      padding: 12px 30px;
      background-color: <?php echo $themeStyles['primary']; ?>;
      color: white;
      border: none;
      border-radius: 5px;
      font-size: 1.1rem;
      font-weight: 500;
      cursor: pointer;
      transition: background-color 0.3s;
    }

    .btn-save:hover {
      background-color: <?php echo $themeStyles['secondary']; ?>;
    }

    .current-theme {
      text-align: center;
      margin-top: 20px;
      padding: 15px;
      background-color: rgba(<?php echo join(',', hex2rgb($themeStyles['primary'])); ?>, 0.1);
      border-radius: 8px;
      font-weight: 500;
    }

    @media (max-width: 768px) {
      .theme-options {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      }
      
      .header h1 {
        font-size: 1.8rem;
      }
    }

    @media (max-width: 480px) {
      .theme-options {
        grid-template-columns: 1fr;
      }
      
      body {
        padding: 10px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>⚙️ ตั้งค่าระบบ - เปลี่ยนธีมสี</h1>
      <p>เลือกธีมสีที่ต้องการใช้ในระบบ</p>
    </div>

    <div class="content">
      <a href="admin_dashboard.php" class="back-button"><i class="fas fa-arrow-left"></i> กลับสู่แดชบอร์ด</a>
      
      <div class="current-theme">
        ธีมปัจจุบัน: <strong><?php 
        $themeNames = [
          'default' => 'ค่าเริ่มต้น (น้ำเงิน)',
          'dark' => 'มืด (Dark)',
          'green' => 'ธรรมชาติ (เขียว)',
          'purple' => 'รอยัล (ม่วง)'
        ];
        echo $themeNames[$currentTheme] ?? 'ค่าเริ่มต้น';
        ?></strong>
      </div>
      
      <form method="POST" action="settings.php">
        <div class="theme-options">
          <!-- ธีมค่าเริ่มต้น -->
          <div class="theme-option">
            <input type="radio" id="theme-default" name="theme" value="default" <?php echo $currentTheme === 'default' ? 'checked' : ''; ?>>
            <label for="theme-default">
              <div class="theme-preview">
                <div class="theme-primary" style="background: #4b6cb7;"></div>
                <div class="theme-secondary" style="background: #182848;"></div>
              </div>
              <div class="theme-name">ค่าเริ่มต้น (น้ำเงิน)</div>
              <div class="theme-colors">
                <span class="color-dot" style="background: #4b6cb7;"></span>
                <span class="color-dot" style="background: #182848;"></span>
                <span class="color-dot" style="background: #ff6b6b;"></span>
              </div>
            </label>
          </div>
          
          <!-- ธีมมืด -->
          <div class="theme-option">
            <input type="radio" id="theme-dark" name="theme" value="dark" <?php echo $currentTheme === 'dark' ? 'checked' : ''; ?>>
            <label for="theme-dark">
              <div class="theme-preview">
                <div class="theme-primary" style="background: #2d3748;"></div>
                <div class="theme-secondary" style="background: #1a202c;"></div>
              </div>
              <div class="theme-name">มืด (Dark)</div>
              <div class="theme-colors">
                <span class="color-dot" style="background: #2d3748;"></span>
                <span class="color-dot" style="background: #1a202c;"></span>
                <span class="color-dot" style="background: #e53e3e;"></span>
              </div>
            </label>
          </div>
          
          <!-- ธีมธรรมชาติ -->
          <div class="theme-option">
            <input type="radio" id="theme-green" name="theme" value="green" <?php echo $currentTheme === 'green' ? 'checked' : ''; ?>>
            <label for="theme-green">
              <div class="theme-preview">
                <div class="theme-primary" style="background: #38a169;"></div>
                <div class="theme-secondary" style="background: #2f855a;"></div>
              </div>
              <div class="theme-name">ธรรมชาติ (เขียว)</div>
              <div class="theme-colors">
                <span class="color-dot" style="background: #38a169;"></span>
                <span class="color-dot" style="background: #2f855a;"></span>
                <span class="color-dot" style="background: #dd6b20;"></span>
              </div>
            </label>
          </div>
          
          <!-- ธีมรอยัล -->
          <div class="theme-option">
            <input type="radio" id="theme-purple" name="theme" value="purple" <?php echo $currentTheme === 'purple' ? 'checked' : ''; ?>>
            <label for="theme-purple">
              <div class="theme-preview">
                <div class="theme-primary" style="background: #805ad5;"></div>
                <div class="theme-secondary" style="background: #6b46c1;"></div>
              </div>
              <div class="theme-name">รอยัล (ม่วง)</div>
              <div class="theme-colors">
                <span class="color-dot" style="background: #805ad5;"></span>
                <span class="color-dot" style="background: #6b46c1;"></span>
                <span class="color-dot" style="background: #ed64a6;"></span>
              </div>
            </label>
          </div>
        </div>
        
        <div class="theme-actions">
          <button type="submit" class="btn-save"><i class="fas fa-save"></i> บันทึกการเปลี่ยนแปลง</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>