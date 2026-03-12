<?php
// mailing/cron_dispatcher.php

// 1. Load Dependencies (db.php automatically loads EnvLoader and $pdo for us)
require_once __DIR__ . '/db.php'; 
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 2. Fetch pending or due-for-retry emails (Limit 10 to prevent timeout)
$query = "SELECT * FROM mailing_queue 
          WHERE status = 'pending' 
          OR (status = 'failed' AND next_attempt_at <= NOW()) 
          LIMIT 10";
$stmt = $pdo->query($query);
$queue_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($queue_items)) {
    echo "Queue is empty. Nothing to send.\n";
    exit;
}

// 3. Process Each Email
foreach ($queue_items as $row) {
    $queue_id = $row['id'];
    $app_slug = $row['app_slug'];
    $event_type = $row['event_type'];
    $recipient_email = $row['recipient_email'];
    $payload = json_decode($row['payload'], true);
    $retry_count = $row['retry_count'];

    // Define where the assigned template HTML file should live
    $template_path = __DIR__ . "/templates/{$app_slug}_{$event_type}.html";

    // Fallback if the template doesn't exist
    if (!file_exists($template_path)) {
        markAsFailed($pdo, $queue_id, $retry_count, "Template not found: {$template_path}");
        continue;
    }

    // 4. Load the Template and Inject Variables
    $html_body = file_get_contents($template_path);
    if (is_array($payload)) {
        foreach ($payload as $key => $value) {
            // Replaces placeholders like {{customer_name}}
            $html_body = str_replace("{{" . $key . "}}", $value, $html_body);
        }
    }

    // 5. Send via PHPMailer
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = $_ENV['SMTP_SECURE'] ?: PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = $_ENV['SMTP_PORT'] ?: 465;

        $mail->setFrom($_ENV['SMTP_FROM_EMAIL'], $_ENV['SMTP_FROM_NAME']);
        $mail->addAddress($recipient_email);
        $mail->isHTML(true);
        $mail->Subject = ucfirst($app_slug) . " - " . ucfirst(str_replace('_', ' ', $event_type));
        $mail->Body    = $html_body;

        $mail->send();

        // 6. Mark as Sent
        $update_stmt = $pdo->prepare("UPDATE mailing_queue SET status = 'sent' WHERE id = :id");
        $update_stmt->execute([':id' => $queue_id]);
        
        echo "Successfully sent queue ID {$queue_id} to {$recipient_email}<br>\n";

    } catch (Exception $e) {
        // 7. Handle Failures & 10-Minute Retries
        markAsFailed($pdo, $queue_id, $retry_count, $mail->ErrorInfo);
    }
}

// Helper Function for Failures
function markAsFailed($pdo, $queue_id, $retry_count, $error_msg) {
    $new_retry_count = $retry_count + 1;
    
    if ($new_retry_count >= 5) {
        $status = 'permanently_failed';
        $next_attempt = NULL;
    } else {
        $status = 'failed';
        $next_attempt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    }

    $stmt = $pdo->prepare("UPDATE mailing_queue SET status = :status, retry_count = :retry_count, next_attempt_at = :next_attempt WHERE id = :id");
    $stmt->execute([
        ':status' => $status,
        ':retry_count' => $new_retry_count,
        ':next_attempt' => $next_attempt,
        ':id' => $queue_id
    ]);
    
    echo "Failed to send queue ID {$queue_id}. Retries: {$new_retry_count}. Error: {$error_msg}<br>\n";
}
?>