<?php
// mailing/api/event.php
header('Content-Type: application/json');

// 1. Include the DB connection
require_once '../db.php'; 

// 2. Read the incoming JSON payload
$request_body = file_get_contents('php://input');
$data = json_decode($request_body, true);

// Check if payload is empty/invalid
if ($data === null || empty($request_body)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Payload is empty or invalid JSON.'
    ]);
    exit;
}

// 3. Validate required fields
if (!isset($data['app_slug']) || !isset($data['event_type']) || !isset($data['recipient_email']) || !isset($data['payload'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Missing required fields.'
    ]);
    exit;
}

$app_slug = $data['app_slug'];
$event_type = $data['event_type'];
$recipient_email = $data['recipient_email'];
$payload = is_array($data['payload']) ? json_encode($data['payload']) : $data['payload'];

// 4. Insert into the Mailing Queue using PDO
try {
    // Notice we use $pdo here to match your db.php
    $stmt = $pdo->prepare("INSERT INTO mailing_queue (app_slug, event_type, recipient_email, payload, status, next_attempt_at) VALUES (:app_slug, :event_type, :recipient_email, :payload, 'pending', NOW())");
    
    $stmt->execute([
        ':app_slug' => $app_slug,
        ':event_type' => $event_type,
        ':recipient_email' => $recipient_email,
        ':payload' => $payload
    ]);
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success', 
        'message' => 'Event queued successfully.',
        'queue_id' => $pdo->lastInsertId()
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Failed to queue event.',
        'debug' => $e->getMessage() // This will print the exact DB error if it fails again
    ]);
}
?>