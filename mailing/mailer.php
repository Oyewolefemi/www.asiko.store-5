<?php
// mailing/mailer.php
// The Universal PHPMailer Engine

// Security: Prevent direct URL access to this engine file
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    die('Security Error: Direct access to the mailer engine is not permitted.');
}

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail_status = 'failed';
$mail_error = 'Unknown error';

// 1. Ensure required data has been passed from the API listener or Parent Script
if (empty($recipient_email) || empty($subject) || empty($body)) {
    $mail_error = 'Critical Error: Missing recipient, subject, or body in mailer engine.';
    return; // Stop execution, return failure to the parent script
}

// 2. Load Master .env configuration (if not already loaded by the parent script)
if (empty($_ENV['SMTP_HOST'])) {
    $envPath = __DIR__ . '/../.env';
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim(trim($value), "\"'");
        }
    } else {
        $mail_error = 'Configuration Error: Master .env file missing.';
        return;
    }
}

// 3. Fire up PHPMailer
$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = $_ENV['SMTP_HOST'] ?? '';
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['SMTP_USER'] ?? '';
    $mail->Password   = $_ENV['SMTP_PASS'] ?? '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $_ENV['SMTP_PORT'] ?? 587;

    // From Header
    $fromEmail = $_ENV['SMTP_FROM_EMAIL'] ?? 'no-reply@asiko.store';
    $fromName  = $_ENV['SMTP_FROM_NAME'] ?? 'Asiko Store';
    $mail->setFrom($fromEmail, $fromName);

    // Add Recipients (Handles both multiple recipients array or a single string)
    if (is_array($recipient_email)) {
        foreach ($recipient_email as $email) {
            if (!empty($email)) $mail->addAddress(trim($email));
        }
    } else {
        $r_name = !empty($recipient_name) ? trim($recipient_name) : '';
        $mail->addAddress(trim($recipient_email), $r_name);
    }

    // Content Settings
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $body;
    
    // Auto-generate plain text fallback if one wasn't provided
    $mail->AltBody = !empty($alt_body) ? $alt_body : strip_tags(str_replace('<br>', "\n", $body));

    // Send!
    $mail->send();
    $mail_status = 'success';
    $mail_error  = null;

} catch (Exception $e) {
    $mail_status = 'failed';
    $mail_error  = "Mailer Error: {$mail->ErrorInfo}";
    error_log("PHPMailer Exception: " . $mail_error);
} catch (\Exception $e) {
    $mail_status = 'failed';
    $mail_error  = "General Error: " . $e->getMessage();
    error_log("General Mail Exception: " . $mail_error);
}
?>