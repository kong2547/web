<?php
// toggle_api.php
session_start();
require 'db.php';
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: [];
if (empty($input['switch_id']) || empty($input['csrf_token'])) {
    echo json_encode(['success'=>false,'error'=>'missing_params']); exit;
}
if (!hash_equals($_SESSION['csrf_token'] ?? '', $input['csrf_token'])) {
    echo json_encode(['success'=>false,'error'=>'csrf']); exit;
}

$sid = (int)$input['switch_id'];

// ดึงสวิตช์
$q = $conn->prepare("SELECT id, room_id, status FROM switches WHERE id = ?");
$q->execute([$sid]);
$sw = $q->fetch();
if (!$sw) { echo json_encode(['success'=>false,'error'=>'not_found']); exit; }

$desired = isset($input['status']) ? ($input['status']==='on' ? 'on' : 'off') : ($sw['status']==='on' ? 'off' : 'on');

if ($sw['status'] !== $desired) {
    $u = $conn->prepare("UPDATE switches SET status = ? WHERE id = ?");
    $u->execute([$desired, $sid]);
    $l = $conn->prepare("INSERT INTO switch_logs (switch_id, room_id, status) VALUES (?, ?, ?)");
    $l->execute([$sid, $sw['room_id'] ?? null, $desired]);
}

echo json_encode(['success'=>true,'switch_id'=>$sid,'status'=>$desired]);
