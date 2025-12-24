<?php
require 'db.php';
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set("Asia/Bangkok");

/* 🔧 ดึง IP ของผู้เรียก API */
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}

$cmd = $_GET['cmd'] ?? '';

switch ($cmd) {

/* ✅ 1. UPDATE STATUS (ESP หรือ Web เรียกได้) */
case 'update':
    $esp_name = $_GET['esp_name'] ?? '';
    $gpio     = $_GET['gpio'] ?? '';
    $status   = $_GET['status'] ?? '';
    $room_id  = $_GET['room_id'] ?? null;
    $ip_addr  = getClientIP();

    if (!$esp_name || $gpio === '' || $status === '') {
        http_response_code(400);
        echo json_encode(["error" => "Missing parameters"]);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM switches WHERE esp_name=? AND gpio_pin=?");
    $stmt->execute([$esp_name, $gpio]);
    $sw = $stmt->fetch();

    if ($sw) {
        // ❗ ถ้ามาจากเว็บ (localhost/::1) ห้ามเปลี่ยน IP ของ ESP
        if ($ip_addr == '::1' || $ip_addr == '127.0.0.1') {
            $ip_addr = $sw['ip_address']; // ใช้ IP เดิม
        }

        // อัปเดตสถานะ
        $u = $conn->prepare("UPDATE switches 
                             SET status=?, ip_address=?, last_seen=NOW() 
                             WHERE id=?");
        $u->execute([$status == '1' ? 'on' : 'off', $ip_addr, $sw['id']]);

        // บันทึก log
        $log = $conn->prepare("INSERT INTO switch_logs (switch_id, room_id, status) VALUES (?, ?, ?)");
        $log->execute([$sw['id'], $sw['room_id'], $status == '1' ? 'on' : 'off']);

        
    } else {
        // ถ้ายังไม่มี ให้เพิ่มใหม่ (เฉพาะกรณี ESP ส่งมา)
        $u = $conn->prepare("INSERT INTO switches 
            (esp_name, gpio_pin, status, ip_address, gateway, subnet, dns, network_mode, last_seen, room_id) 
            VALUES (?, ?, ?, ?, '', '', '', 'dhcp', NOW(), ?)");
        $u->execute([$esp_name, $gpio, $status == '1' ? 'on' : 'off', $ip_addr, $room_id]);
    }
    echo json_encode(["success" => true]);
break;

/* ✅ 2. GET STATUS (สำหรับ ESP) */
case 'get_status':
    $esp_name = $_GET['esp_name'] ?? '';
    $gpio     = $_GET['gpio'] ?? '';
    if (!$esp_name || $gpio === '') {
        echo json_encode(["error" => "Missing parameters"]);
        exit;
    }

    $stmt = $conn->prepare("SELECT status FROM switches WHERE esp_name=? AND gpio_pin=?");
    $stmt->execute([$esp_name, $gpio]);
    $sw = $stmt->fetch();

    if ($sw) {
        echo json_encode(["status" => $sw['status'] === 'on']);
    } else {
        $conn->prepare("INSERT INTO switches (esp_name, gpio_pin, status, last_seen) 
                        VALUES (?, ?, 'off', NOW())")->execute([$esp_name, $gpio]);
        echo json_encode(["status" => false, "msg" => "auto-added"]);
    }

    $conn->prepare("UPDATE switches SET last_seen=NOW(), ip_address=? WHERE esp_name=?")
         ->execute([getClientIP(), $esp_name]);
break;

/* ✅ 3. GET SWITCH STATUS (สำหรับวัด latency - ใหม่) */
case 'get_switch_status':
    $switch_id = (int)($_GET['switch_id'] ?? 0);
    $room_id = (int)($_GET['room_id'] ?? 0);
    
    if (!$switch_id || !$room_id) {
        echo json_encode(['success' => false, 'error' => 'Missing switch_id or room_id']);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT status FROM switches WHERE id = ? AND room_id = ?");
    $stmt->execute([$switch_id, $room_id]);
    $switch = $stmt->fetch();
    
    if ($switch) {
        echo json_encode([
            'success' => true, 
            'status' => $switch['status'],
            'switch_id' => $switch_id,
            'room_id' => $room_id
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Switch not found']);
    }
break;

/* ✅ 4. LOG LATENCY (ใหม่) */
case 'log_latency':
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
        exit;
    }
    
    $switch_id = (int)($input['switch_id'] ?? 0);
    $room_id = (int)($input['room_id'] ?? 0);
    $web_to_server_ms = (int)($input['web_to_server_ms'] ?? 0);
    $full_latency_ms = (int)($input['full_latency_ms'] ?? 0);
    $status = $input['status'] ?? 'unknown';
    
    if (!$switch_id || !$room_id) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }
    
    $stmt = $conn->prepare("INSERT INTO latency_logs (switch_id, room_id, web_to_server_ms, full_latency_ms, status, created_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $switch_id,
        $room_id,
        $web_to_server_ms,
        $full_latency_ms,
        $status,
        $input['timestamp'] ?? date('Y-m-d H:i:s')
    ]);
    
    echo json_encode(['success' => true, 'id' => $conn->lastInsertId()]);
break;

/* ✅ 5. CHECK SCHEDULE */
case 'check_schedule':
    $esp_name = $_GET['esp_name'] ?? '';
    $gpio     = $_GET['gpio'] ?? '';
    $time     = $_GET['time'] ?? date("H:i:s");

    if (!$esp_name || $gpio === '') {
        echo json_encode(["error" => "Missing parameters"]);
        exit;
    }

    $stmt = $conn->prepare("SELECT id FROM switches WHERE esp_name=? AND gpio_pin=?");
    $stmt->execute([$esp_name, $gpio]);
    $sw = $stmt->fetch();

    if (!$sw) {
        echo json_encode(["status" => false]);
        exit;
    }

    $device_id = "switch_" . $sw['id'];
    $stmt = $conn->prepare("SELECT * FROM schedule WHERE device_id=? AND enabled=1 AND esp_name=?");
    $stmt->execute([$device_id, $esp_name]);
    $rows = $stmt->fetchAll();

    $today  = date("Y-m-d");
    $todayW = date("D");
    $status = false;

    foreach ($rows as $r) {
        if ($r['start_date'] && $today < $r['start_date']) continue;
        if ($r['end_date'] && $today > $r['end_date']) continue;

        if ($r['weekdays']) {
            $wd = explode(",", $r['weekdays']);
            if (!in_array($todayW, $wd)) continue;
        }

        $start = strtotime($r['start_time']);
        $end   = strtotime($r['end_time']);
        $now   = strtotime($time);

        $match = ($start <= $end)
            ? ($now >= $start && $now <= $end)
            : ($now >= $start || $now <= $end);

        if ($match) $status = ($r['mode'] === 'on');
    }

    echo json_encode(["status" => $status, "server_time" => date("H:i:s")]);

    $conn->prepare("UPDATE switches SET last_seen=NOW(), ip_address=? WHERE esp_name=?")
         ->execute([getClientIP(), $esp_name]);
break;

/* ✅ 6. HEARTBEAT */
case 'heartbeat':
    $esp_name = $_GET['esp_name'] ?? '';
    $conn->prepare("UPDATE switches 
                    SET last_seen=NOW(), ip_address=? 
                    WHERE esp_name=?")
         ->execute([getClientIP(), $esp_name]);
    echo json_encode(["ok" => true]);
break;

/* ✅ 7. ESP STATUS */
case 'esp_status':
    $room_id = (int)($_GET['room_id'] ?? 0);
    $stmt = $conn->prepare("
        SELECT esp_name,
               MAX(ip_address) AS ip_address,
               MAX(last_seen) AS last_seen,
               MAX(network_mode) AS network_mode,
               MAX(gateway) AS gateway,
               MAX(subnet) AS subnet,
               MAX(dns) AS dns
        FROM switches
        WHERE room_id=?
        GROUP BY esp_name
    ");
    $stmt->execute([$room_id]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['online'] = (time() - strtotime($r['last_seen'])) < 30;
        $r['network_mode'] = $r['network_mode'] ?? '';
    }
    echo json_encode($rows);
break;

/* ✅ 8. LIST STATUS (ใช้ใน public_control.php) */
case 'list_statuses':
    $room_id = (int)($_GET['room_id'] ?? 0);
    $stmt = $conn->prepare("SELECT gpio_pin, status FROM switches WHERE room_id=?");
    $stmt->execute([$room_id]);
    echo json_encode($stmt->fetchAll());
break;

/* ✅ 9. GET SERVER TIME */
case 'get_time':
    echo json_encode([
        "time"  => date("H:i:s"),
        "day"   => (int)date("j"),
        "month" => (int)date("n"),
        "year"  => (int)date("Y"),
        "weekday" => date("D")
    ]);
break;

/* ✅ 10. GET SCHEDULE (join GPIO) */
case 'get_schedule':
    $esp_name = $_GET['esp_name'] ?? '';
    if (!$esp_name) {
        echo json_encode(["error" => "esp_name required"]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT s.*, sw.gpio_pin
        FROM schedule s
        JOIN switches sw ON sw.id = CAST(SUBSTRING(s.device_id, 8) AS UNSIGNED)
        WHERE s.enabled = 1
          AND s.esp_name = ?
          AND (s.start_date IS NULL OR CURDATE() >= s.start_date)
          AND (s.end_date IS NULL OR DATE_FORMAT(CURDATE(), '%m-%d') <= DATE_FORMAT(s.end_date, '%m-%d'))
    ");
    $stmt->execute([$esp_name]);
    echo json_encode($stmt->fetchAll());
break;

/* ✅ 11. UPDATE IP (ESP ใช้รายงานตัวเอง) */
case 'update_ip':
    $esp_name = $_GET['esp_name'] ?? '';
    $ip = $_GET['ip'] ?? '';
    $gw = $_GET['gateway'] ?? '';
    $sn = $_GET['subnet'] ?? '';
    $dns = $_GET['dns'] ?? '';
    $mode = $_GET['mode'] ?? 'dhcp';

    if (!$esp_name || !$ip) {
        echo json_encode(["error" => "Missing esp_name or ip"]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE switches 
        SET ip_address=?, gateway=?, subnet=?, dns=?, network_mode=?, last_seen=NOW()
        WHERE esp_name=?");
    $stmt->execute([$ip, $gw, $sn, $dns, $mode, $esp_name]);

    echo json_encode(["success" => true]);
break;

/* ✅ 12. LOG WIFI RECONNECT (ใช้เวลาเซิร์ฟเวอร์เป็นหลัก) */
case 'log_reconnect':
    $esp_name = $_GET['esp_name'] ?? '';
    $ip       = $_GET['ip'] ?? getClientIP();

    if (!$esp_name) {
        echo json_encode(["error" => "esp_name required"]);
        exit;
    }

    // ✅ ใช้เวลาเซิร์ฟเวอร์แทน (ไม่พึ่งเวลาจาก ESP)
    $server_date = date("Y-m-d");
    $server_time = date("H:i:s");

    // ✅ เพิ่มข้อมูลการเชื่อมต่อ Wi-Fi ลงตาราง esp_logs
    $stmt = $conn->prepare("
        INSERT INTO esp_logs (esp_name, ip_address, log_date, log_time, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$esp_name, $ip, $server_date, $server_time]);

    echo json_encode([
        "success" => true,
        "msg" => "Wi-Fi reconnect logged (server time)",
        "server_date" => $server_date,
        "server_time" => $server_time
    ]);
break;

/* ✅ 13. LATENCY TEST RESPONSE (ใหม่) */
case 'latency_test_response':
    $esp_name = $_GET['esp_name'] ?? '';
    $gpio = $_GET['gpio'] ?? '';
    $action = $_GET['action'] ?? '';
    $esp_time = $_GET['esp_time'] ?? '';
    
    if (!$esp_name || !$gpio) {
        echo json_encode(["error" => "Missing parameters"]);
        exit;
    }
    
    echo json_encode([
        "success" => true,
        "message" => "Latency test response received",
        "esp_name" => $esp_name,
        "gpio" => $gpio,
        "action" => $action,
        "esp_time" => $esp_time,
        "server_time" => date("H:i:s"),
        "received_at" => date("Y-m-d H:i:s")
    ]);
break;

// ✅ ตรวจสอบว่ามีคำสั่งใหม่หรือไม่
if ($_GET['cmd'] === 'check_update') {
    $esp = $_GET['esp_name'];
    $stmt = $conn->prepare("SELECT updated FROM esp_status WHERE esp_name=?");
    $stmt->execute([$esp]);
    $row = $stmt->fetch();
    if ($row && $row['updated'] == 1) {
        echo "update=1";
    } else {
        echo "update=0";
    }
    exit;
}

// ✅ รีเซต flag หลัง ESP ดึงแล้ว
if ($_GET['cmd'] === 'reset_update') {
    $esp = $_GET['esp_name'];
    $stmt = $conn->prepare("UPDATE esp_status SET updated=0 WHERE esp_name=?");
    $stmt->execute([$esp]);
    echo "reset=ok";
    exit;
}

case 'log_latency':
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO latency_logs 
        (switch_id, room_id, web_to_server_ms, full_latency_ms, status, action_type, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $data['switch_id'], 
        $data['room_id'], 
        $data['web_to_server_ms'], 
        $data['full_latency_ms'], 
        $data['status'], 
        $data['action_type']
    ]);

    echo json_encode(['success' => true]);
    break;


    
default:
    echo json_encode(["error" => "Invalid cmd: $cmd"]);
}
?>