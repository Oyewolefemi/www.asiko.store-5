<?php
// mailing/mailer.php
// ============================================================
// PHPMailer engine. Called by event.php (and can be called
// directly for manual broadcasts from compose.php).
//
// EXPECTS these variables to already be set before require:
//   $recipient_email  (string)  e.g. 'john@example.com'
//   $recipient_name   (string)  e.g. 'John Doe'
//   $subject          (string)  e.g. 'Order #42 Confirmed'
//   $body             (string)  Full HTML email body
//   $alt_body         (string)  Plain text fallback
//
// SETS after execution:
//   $mail_status  ('success' | 'failed')
//   $mail_error   (string | null)
// ============================================================

// Don't run standalone
if (!defined('API_MAIL_CALL') && !defined('COMPOSE_MAIL_CALL')) {
    die("Direct access not allowed.");
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Support both Composer autoload and manual drop-in
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/PHPMailer/src/PHPMailer.php')) {
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';
} else {
    $mail_status = 'failed';
    $mail_error  = 'PHPMailer not found. Run: composer require phpmailer/phpmailer OR drop PHPMailer/src/ into mailing/.';
    error_log($mail_error);
    return;
}

// Pull SMTP config from DB (mail_settings) or fall back to .env
$smtp_host = '';
$smtp_user = '';
$smtp_pass = '';
$smtp_port = 587;
$smtp_from_name = '';

// If $pdo exists (called from event.php), pull from DB settings first
if (isset($pdo)) {
    try {
        $settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM mail_settings");
        $db_settings   = [];
        while ($row = $settings_stmt->fetch()) {
            $db_settings[$row['setting_key']] = $row['setting_value'];
        }
        $smtp_host      = $db_settings['smtp_host']      ?? '';
        $smtp_user      = $db_settings['smtp_user']      ?? '';
        $smtp_pass      = $db_settings['smtp_pass']      ?? '';
        $smtp_port      = (int)($db_settings['smtp_port'] ?? 587);
        $smtp_from_name = $db_settings['smtp_from_name'] ?? '';
    } catch (Exception $e) {
        // DB read failed — fall through to .env
    }
}

// Fall back to .env if DB settings are empty
if (empty($smtp_host)) $smtp_host      = $_ENV['SMTP_HOST']      ?? '';
if (empty($smtp_user)) $smtp_user      = $_ENV['SMTP_USER']      ?? '';
if (empty($smtp_pass)) $smtp_pass      = $_ENV['SMTP_PASS']      ?? '';
if (empty($smtp_port)) $smtp_port      = (int)($_ENV['SMTP_PORT'] ?? 587);
if (empty($smtp_from_name)) $smtp_from_name = $_ENV['SMTP_FROM_NAME'] ?? 'Mailing System';

if (empty($smtp_host) || empty($smtp_user) || empty($smtp_pass)) {
    $mail_status = 'failed';
    $mail_error  = 'SMTP credentials not configured. Check mail_settings table or .env file.';
    error_log($mail_error);
    return;
}

// Send
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host        = $smtp_host;
    $mail->SMTPAuth    = true;
    $mail->Username    = $smtp_user;
    $mail->Password    = $smtp_pass;
    $mail->SMTPSecure  = $smtp_port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port        = $smtp_port;

    $mail->setFrom($smtp_user, $smtp_from_name);
    $mail->addAddress($recipient_email, $recipient_name);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $body;
    $mail->AltBody = $alt_body ?? strip_tags(str_replace('<br>', "\n", $body));

    $mail->send();
    $mail_status = 'success';
    $mail_error  = null;

} catch (Exception $e) {
    $mail_status = 'failed';
    $mail_error  = $mail->ErrorInfo;
    error_log("Mailer error [{$recipient_email}]: {$mail_error}");
}
