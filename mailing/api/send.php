<?php
// mailing/api/send.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 1. Handle preflight OPTIONS request (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. Load Master Environment Variables to check API Key
function loadMasterEnv($path) {
    if (!file_exists($path)) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Master configuration missing."]);
        exit;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim(trim($value), "\"'");
    }
}

// Load .env from root directory
loadMasterEnv(__DIR__ . '/../../.env');

// 3. Authenticate Request
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$expectedKey = $_ENV['MAILING_API_KEY'] ?? '';

if (empty($expectedKey) || $authHeader !== 'Bearer ' . $expectedKey) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized access. Invalid API Key."]);
    exit;
}

// 4. Parse JSON Payload
$jsonPayload = file_get_contents('php://input');
$data = json_decode($jsonPayload, true);

if (!$data || empty($data['recipient_email']) || empty($data['subject']) || empty($data['body'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid payload. Missing required fields."]);
    exit;
}

// Map parameters for mailer.php to consume
$app_source      = $data['app_source'] ?? 'System';
$recipient_email = $data['recipient_email'];
$recipient_name  = $data['recipient_name'] ?? '';
$subject         = $data['subject'];
$body            = $data['body'];
$alt_body        = $data['alt_body'] ?? strip_tags($body);

// 5. Trigger the Local Mailer Engine
// Define a flag so mailer.php knows it is running in background API mode
define('API_MAIL_CALL', true);

// We define baseline status before including the mailer
$mail_status = 'failed';
$mail_error  = 'Mailer logic not executed.';

// Include the centralized engine (We will refactor this file in Step 2)
if (file_exists(__DIR__ . '/../mailer.php')) {
    require_once __DIR__ . '/../mailer.php';
} else {
    $mail_error = 'mailer.php engine file missing.';
}

// 6. Log attempt to Mailing Database
$db_host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$db_name = $_ENV['DB_NAME_MAILING'] ?? 'mailing_db';
$db_user = $_ENV['DB_USER_MAILING'] ?? 'root';
$db_pass = $_ENV['DB_PASS_MAILING'] ?? '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("INSERT INTO email_history (app_source, recipient_email, subject, status, error_message) VALUES (?, ?, ?, ?, ?)");
    $db_status = ($mail_status === 'success') ? 'sent' : 'failed';
    $stmt->execute([$app_source, $recipient_email, $subject, $db_status, $mail_error ?? null]);
} catch (PDOException $e) {
    error_log("Mailing API DB Logging Error: " . $e->getMessage());
}

// 7. Return Final Response
if ($mail_status === 'success') {
    echo json_encode(["status" => "success", "message" => "Email dispatched successfully."]);
} else {
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "Email failed to send.", 
        "debug" => $mail_error ?? 'Unknown Mailer Error'
    ]);
}
?>