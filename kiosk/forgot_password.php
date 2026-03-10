<?php
// --- 1. SMART LOADER (Detects PHPMailer automatically) ---
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require 'vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/PHPMailer/src/PHPMailer.php')) {
    require 'PHPMailer/src/Exception.php';
    require 'PHPMailer/src/PHPMailer.php';
    require 'PHPMailer/src/SMTP.php';
} else {
    // Fallback look-up
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require '../vendor/autoload.php';
    } else {
        die("Error: PHPMailer not found. Please run 'composer require phpmailer/phpmailer'.");
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

include_once 'config.php';
include_once 'functions.php';
require_once 'EnvLoader.php';

// --- 2. LOAD .ENV SETTINGS ---
EnvLoader::load(__DIR__ . '/.env');

$msg = "";
$error = "";

if (isset($_POST['reset_request'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    if (empty($email)) {
        $error = "Please enter your email address.";
    } else {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate Token
            $token = bin2hex(random_bytes(32));
            $expires_at = date("Y-m-d H:i:s", strtotime('+1 hour'));

            // Clean old tokens & Save new one
            $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
            $insert = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            
            if ($insert->execute([$email, $token, $expires_at])) {
                
                // Build Reset Link
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $domain = $_SERVER['HTTP_HOST'];
                $path_prefix = strpos($_SERVER['REQUEST_URI'], 'kiosk') !== false ? '/kiosk' : '';
                $resetLink = $protocol . "://" . $domain . $path_prefix . "/reset_password.php?token=" . $token;

                // --- 3. SEND EMAIL (PHPMailer + Brevo) ---
                $mail = new PHPMailer(true);

                try {
                    // Server Settings (Loaded from .env)
                    $mail->isSMTP();
                    $mail->Host       = EnvLoader::get('SMTP_HOST');
                    $mail->SMTPAuth   = true;
                    $mail->Username   = EnvLoader::get('SMTP_USER');
                    $mail->Password   = EnvLoader::get('SMTP_PASS');
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
                    $mail->Port       = EnvLoader::get('SMTP_PORT', 587);

                    // Recipients
                    $fromEmail = EnvLoader::get('SMTP_FROM_EMAIL', 'no-reply@asiko.store');
                    $fromName  = EnvLoader::get('SMTP_FROM_NAME', 'Asiko Store');
                    
                    $mail->setFrom($fromEmail, $fromName);
                    $mail->addAddress($email, $user['name'] ?? 'Customer');
                    $mail->addReplyTo($fromEmail);

                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = 'Reset Your Password - ' . $fromName;
                    $mail->Body    = "
                        <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                            <h2 style='color: #06b6d4;'>Password Reset</h2>
                            <p>Hi " . htmlspecialchars($user['name']) . ",</p>
                            <p>You requested a password reset. Click the button below to continue:</p>
                            <p>
                                <a href='$resetLink' style='background-color: #06b6d4; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>
                                    Reset Password
                                </a>
                            </p>
                            <p style='margin-top:20px; font-size:12px; color:#777;'>Or copy this link:<br>$resetLink</p>
                            <p style='font-size:12px; color:#999; margin-top:30px;'>This link is valid for 1 hour.</p>
                        </div>
                    ";
                    $mail->AltBody = "Reset Link: $resetLink";

                    $mail->send();

                    // Success Message
                    $msg = "<div class='bg-green-50 border border-green-200 text-green-800 p-4 rounded mb-4'>
                                <strong>Success!</strong> A reset link has been sent to <strong>$email</strong>.<br>
                                Please check your Inbox and Spam folder.
                            </div>";

                } catch (Exception $e) {
                    $error = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
                    // Fallback Link (Visible only if email fails)
                    $msg = "<div class='bg-yellow-50 border border-yellow-200 text-yellow-800 p-4 rounded mb-4'>
                                <strong>System Notice:</strong> Email failed to send.<br>
                                <a href='$resetLink' class='underline font-bold'>Click here to reset manually</a>
                            </div>";
                }
            }
        } else {
            // Security: Don't reveal if user exists
            $msg = "<div class='bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded mb-4'>
                        If an account exists for this email, we have processed your request.
                    </div>";
        }
    }
}

include 'header.php';
?>

<style>
    .btn-primary-bg { 
        background-color: var(--primary-color, #06b6d4); 
        color: white; 
    }
    .btn-primary-bg:hover { 
        background-color: var(--primary-color-hover, #0891b2); 
    }
</style>

<main class="bg-gray-50 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full bg-white p-8 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold text-center text-gray-900 mb-6">Reset Password</h2>
        
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4 text-sm"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?= $msg ?>

        <form method="POST" class="space-y-6">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                <input id="email" name="email" type="email" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-cyan-500 focus:border-cyan-500 sm:text-sm" placeholder="Enter your email">
            </div>

            <button type="submit" name="reset_request" class="w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white btn-primary-bg focus:outline-none transition">
                Send Reset Link
            </button>
            
            <div class="text-center mt-4">
                <a href="auth.php" class="text-sm text-gray-600 hover:text-cyan-600">Back to Login</a>
            </div>
        </form>
    </div>
</main>

<?php include 'footer.php'; ?>