<?php
// mailing/api/event.php
// ============================================================
// THE ZERO-CODE CONNECTION LAYER
// Any app POSTs an event here. This endpoint checks the mapping
// table, builds the email from the template, and sends it.
// No changes needed here when adding a new app — just add rows
// to mail_event_mappings via the UI.
// ============================================================

header('Content-Type: application/json');

// 1. Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

// 2. Load master .env
function loadEnv($path) {
    if (!file_exists($path)) return false;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim(trim($value), "\"'");
    }
    return true;
}

loadEnv(__DIR__ . '/../../.env');

// 3. Authenticate via API key
$headers     = getallheaders();
$authHeader  = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$expectedKey = $_ENV['MAILING_API_KEY'] ?? '';

if (empty($expectedKey) || $authHeader !== 'Bearer ' . $expectedKey) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

// 4. Parse payload
$payload = json_decode(file_get_contents('php://input'), true);

if (
    empty($payload['event']) ||
    empty($payload['app'])   ||
    empty($payload['data'])  ||
    !is_array($payload['data'])
) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload. Required: event, app, data.']);
    exit;
}

$event  = trim($payload['event']);
$app    = trim($payload['app']);
$data   = $payload['data'];

// 5. Connect to mailing DB
try {
    $pdo = new PDO(
        "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME_MAILING']};charset=utf8mb4",
        $_ENV['DB_USER_MAILING'],
        $_ENV['DB_PASS_MAILING']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Mailing DB connection failed.']);
    exit;
}

// 6. Look up the event mapping
// Prioritise exact app match over wildcard 'any'
$stmt = $pdo->prepare("
    SELECT m.*, t.*
    FROM mail_event_mappings m
    LEFT JOIN mail_templates t ON t.id = m.template_id
    WHERE m.event_name = ?
      AND (m.app_source = ? OR m.app_source = 'any')
      AND m.is_active = 1
    ORDER BY CASE WHEN m.app_source = ? THEN 0 ELSE 1 END
    LIMIT 1
");
$stmt->execute([$event, $app, $app]);
$mapping = $stmt->fetch();

if (!$mapping) {
    // No active mapping found — not an error, just nothing to send
    echo json_encode(['status' => 'skipped', 'message' => "No active mapping for event '{$event}' from app '{$app}'."]);
    exit;
}

// 7. Resolve the recipient email from the data payload
$recipientField = $mapping['recipient_field'] ?? 'customer_email';
$recipientEmail = $data[$recipientField] ?? '';

if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
    logHistory($pdo, $app, $recipientEmail, '', $event, 'failed', "Recipient field '{$recipientField}' missing or invalid in data payload.");
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => "Recipient email not found in data['{$recipientField}'].'"]);
    exit;
}

// 8. Build subject and body by replacing {{placeholders}} with real data
$subject = replacePlaceholders($mapping['subject_template'], $data);
$body    = buildFullEmail($mapping, $data);

// 9. Fire the mailer engine
$recipient_email = $recipientEmail;
$recipient_name  = $data['customer_name'] ?? $data['admin_name'] ?? '';
$alt_body        = strip_tags(str_replace('<br>', "\n", $body));

define('API_MAIL_CALL', true);
$mail_status = 'failed';
$mail_error  = 'Mailer engine not found.';

if (file_exists(__DIR__ . '/../mailer.php')) {
    require __DIR__ . '/../mailer.php';
} 

// 10. Log to email_history
logHistory($pdo, $app, $recipientEmail, $subject, $event, $mail_status, $mail_error ?? null);

// 11. Respond
if ($mail_status === 'success') {
    echo json_encode(['status' => 'success', 'message' => 'Email sent.', 'event' => $event, 'app' => $app]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Email failed.', 'debug' => $mail_error]);
}


// ============================================================
// HELPERS
// ============================================================

/**
 * Replace all {{key}} placeholders in a string with values from $data
 */
function replacePlaceholders(string $template, array $data): string {
    foreach ($data as $key => $value) {
        if (is_string($value) || is_numeric($value)) {
            $template = str_replace('{{' . $key . '}}', htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'), $template);
        }
    }
    // Remove any unreplaced placeholders so they don't show in the email
    return preg_replace('/\{\{[a-zA-Z0-9_]+\}\}/', '', $template);
}

/**
 * Build the full HTML email by wrapping the body_template inside the
 * selected design template (header + card + footer)
 */
function buildFullEmail(array $mapping, array $data): string {
    $body    = replacePlaceholders($mapping['body_template'] ?? '', $data);
    $bgColor = $mapping['email_bg_color']  ?? '#f8faf9';
    $cardBg  = $mapping['email_card_bg']   ?? '#ffffff';
    $textCol = $mapping['email_body_text'] ?? '#1a1a1a';
    $linkCol = $mapping['email_link_color'] ?? '#2563eb';
    $footerBg   = $mapping['email_footer_bg']   ?? '#f3f4f6';
    $footerText = $mapping['email_footer_text'] ?? '#6b7280';

    // --- Header ---
    if (!empty($mapping['email_header_raw'])) {
        $header = $mapping['email_header_raw'];
    } else {
        $headerText = htmlspecialchars($mapping['email_header_text'] ?? 'Notification', ENT_QUOTES, 'UTF-8');
        $headerFont = $mapping['email_header_font'] ?? 'Arial, sans-serif';
        $headerImg  = $mapping['email_header_img'] ?? '';
        $bgStyle    = $headerImg
            ? "background-image:url('{$headerImg}');background-size:cover;background-position:center;"
            : "background-color:#1a1a1a;";
        $header = "
        <div style='text-align:center;padding:40px 20px;{$bgStyle}'>
            <h1 style='color:#ffffff;margin:0;font-family:{$headerFont};font-size:28px;font-weight:bold;text-shadow:1px 1px 3px rgba(0,0,0,0.8);'>
                {$headerText}
            </h1>
        </div>";
    }

    // --- Footer ---
    if (!empty($mapping['email_footer_raw'])) {
        $footer = $mapping['email_footer_raw'];
    } else {
        $year = date('Y');
        $footer = "
        <div style='padding:24px;text-align:center;background-color:{$footerBg};border-top:1px solid #e5e7eb;'>
            <p style='margin:0;font-size:11px;color:{$footerText};font-family:Arial,sans-serif;text-transform:uppercase;letter-spacing:1px;font-weight:bold;'>
                &copy; {$year} All rights reserved
            </p>
            <p style='margin:6px 0 0;font-size:10px;color:{$footerText};font-family:Arial,sans-serif;opacity:0.7;'>
                You received this email because you have an account with us.
            </p>
        </div>";
    }

    // --- Assemble ---
    return "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1.0'></head>
<body style='margin:0;padding:40px 16px;background-color:{$bgColor};font-family:Arial,sans-serif;'>
    <div style='max-width:560px;margin:0 auto;background-color:{$cardBg};border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);'>
        {$header}
        <div style='padding:32px;color:{$textCol};font-size:15px;line-height:1.6;'>
            <style>a{{color:{$linkCol};}} table td{{padding:8px 12px;border-bottom:1px solid #f0f0f0;font-size:14px;}}</style>
            {$body}
        </div>
        {$footer}
    </div>
</body>
</html>";
}

/**
 * Insert a row into email_history
 */
function logHistory(PDO $pdo, string $app, string $email, string $subject, string $event, string $status, ?string $error): void {
    try {
        $pdo->prepare("
            INSERT INTO email_history (app_source, recipient_email, subject, event_name, status, error_message)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$app, $email, $subject, $event, $status, $error]);
    } catch (PDOException $e) {
        error_log('Email history log failed: ' . $e->getMessage());
    }
}
