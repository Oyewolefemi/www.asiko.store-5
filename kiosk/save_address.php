<?php
include 'config.php';
include 'functions.php';
session_start();
$user_id = $_SESSION['user_id'] ?? 0;
$data = json_decode(file_get_contents('php://input'), true);

$full = sanitize($data['full_name'] ?? '');
$addr1= sanitize($data['address_line1'] ?? '');
$city = sanitize($data['city'] ?? '');
$st   = sanitize($data['state'] ?? '');

header('Content-Type: application/json');
if (!$user_id || !$full || !$addr1 || !$city || !$st) {
  echo json_encode(['success'=>false,'error'=>'Invalid input.']);
  exit;
}

$stmt = $pdo->prepare("
  INSERT INTO addresses
    (user_id, full_name, address_line1, city, state)
  VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([$user_id, $full, $addr1, $city, $st]);
$id = $pdo->lastInsertId();
$full_address = "$full, $addr1, $city, $st";
echo json_encode(['success'=>true,'id'=>$id,'full_address'=>$full_address]);
