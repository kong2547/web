<?php
// ====================== schedule.php ======================
// Cron job ตรวจสอบ schedule → update switch → log
// ==========================================================
require 'db.php';
date_default_timezone_set("Asia/Bangkok");

function logDebug($msg){
  $path = __DIR__."/logs";
  if(!is_dir($path)) mkdir($path,0777,true);
  $line = "[".date("Y-m-d H:i:s")."] ".$msg."\n";
  file_put_contents($path."/schedule_debug.log", $line, FILE_APPEND);
  echo $line;
}

$today = date("Y-m-d");
$todayW = date("D");
$time = date("H:i:s");
logDebug("=== Start Schedule Check ($today $time) ===");

$stmt = $conn->prepare("SELECT * FROM schedule WHERE enabled=1 
AND (start_date IS NULL OR start_date<=?) 
AND (end_date IS NULL OR end_date>=?)");
$stmt->execute([$today,$today]);
$schedules=$stmt->fetchAll();

foreach($schedules as $sch){
  $start=strtotime($sch['start_time']);
  $end=strtotime($sch['end_time']);
  $now=strtotime($time);
  $match=false;
  if($start<=$end) $match=($now>=$start && $now<=$end);
  else $match=($now>=$start || $now<=$end);

  if($match){
    $swid=(int)str_replace("switch_","",$sch['device_id']);
    $st=$conn->prepare("SELECT * FROM switches WHERE id=?");
    $st->execute([$swid]);
    if($sw=$st->fetch()){
      $new=$sch['mode'];
      $conn->prepare("UPDATE switches SET status=? WHERE id=?")->execute([$new,$swid]);
      $conn->prepare("INSERT INTO switch_logs (switch_id, room_id, status) VALUES (?,?,?)")
           ->execute([$swid,$sw['room_id'],$new]);
      $conn->prepare("INSERT INTO logs_schedule (schedule_id,device_id,run_time,status) VALUES (?,?,NOW(),?)")
           ->execute([$sch['id'],$sch['device_id'],$new]);
      logDebug("✅ Schedule {$sch['id']} -> Switch {$swid} = {$new}");
    }
  } else {
    logDebug("⏹ Schedule {$sch['id']} period ended (No action)");
  }
}
logDebug("=== End ===\n");
?>