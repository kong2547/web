<?php
// schedule_runner.php
require 'db.php';
date_default_timezone_set('Asia/Bangkok');

$nowDate = date('Y-m-d');
$nowTime = date('H:i:s');
$nowWeekday = date('N'); // 1=จันทร์ ... 7=อาทิตย์

$stmt = $conn->query("SELECT * FROM schedule WHERE enabled=1");
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($schedules as $sch) {
    $deviceId = $sch['device_id'];
    $mode     = strtolower($sch['mode']);    // daily | weekly | once
    $action   = strtolower($sch['action']);  // on | off
    $startDate= $sch['start_date'];
    $endDate  = $sch['end_date'];
    $weekdays = $sch['weekdays'];
    $startTime= $sch['start_time'];
    $endTime  = $sch['end_time'];

    // ตรวจสอบวันที่
    if ($startDate && $nowDate < $startDate) continue;
    if ($endDate && $nowDate > $endDate) continue;

    // ตรวจสอบโหมด
    $match = false;
    if ($mode === 'daily') {
        $match = true;
    } elseif ($mode === 'weekly') {
        if ($weekdays && in_array($nowWeekday, explode(',', $weekdays))) {
            $match = true;
        }
    } elseif ($mode === 'once') {
        if ($startDate === $nowDate) {
            $match = true;
        }
    }
    if (!$match) continue;

    // ตรวจสอบเวลา
    if ($nowTime >= $startTime && $nowTime <= $endTime) {
        if (preg_match('/(\d+)/', $deviceId, $m)) {
            $switchId = (int)$m[1];
            $u = $conn->prepare("UPDATE switches SET status=? WHERE id=?");
            $u->execute([$action, $switchId]);

            $log = $conn->prepare("INSERT INTO switch_logs (switch_id, room_id, status)
                VALUES (?, (SELECT room_id FROM switches WHERE id=?), ?)");
            $log->execute([$switchId, $switchId, $action]);

            echo "Switch $switchId set to $action\n";
        }
    }
}
