<?php
// --- 1. SMART LOADER (For PHPMailer) ---
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require 'vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/PHPMailer/src/PHPMailer.php')) {
    require 'PHPMailer/src/Exception.php';
    require 'PHPMailer/src/PHPMailer.php';
    require 'PHPMailer/src/SMTP.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once 'config.php';
include_once 'functions.php';
require_once 'EnvLoader.php';

// Load Environment Variables
EnvLoader::load(__DIR__ . '/.env');

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['user_role']) && ($_SESSION['user_role'] === 'vendor' || $_SESSION['user_role'] === 'admin')) {
        header("Location: Red/admin_dashboard.php");
    } else {
        header("Location: my-account.php");
    }
    exit;
}

$error = '';
$success = '';
$active_tab = 'login'; 

$csrf_token = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = "Session expired or invalid token. Please refresh and try again.";
    } else {
        // --- REGISTRATION LOGIC ---
        if (isset($_POST['register'])) {
            $active_tab = 'register';
            $name = sanitize($_POST['name']);
            $email = sanitize($_POST['email']);
            $phone = sanitize($_POST['phone']); 
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            $security_question = sanitize($_POST['security_question']);
            $security_answer = sanitize($_POST['security_answer']);

            if ($password !== $confirm_password) {
                $error = "Passwords do not match.";
            } elseif (empty($name) || empty($email) || empty($phone) || empty($password) || empty($security_question) || empty($security_answer)) {
                $error = "All fields are required.";
            } else {
                // Check if email OR phone already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
                $stmt->execute([$email, $phone]);
                if ($stmt->fetch()) {
                    $error = "An account with this email or phone number already exists.";
                } else {
                    $password_hash = secureHash($password); 
                    $security_answer_hash = secureHash($security_answer);
                    try {
                        $stmt = $pdo->prepare(
                            "INSERT INTO users (name, email, phone, password, security_question, security_answer_hash, role) VALUES (?, ?, ?, ?, ?, ?, 'customer')"
                        );
                        $stmt->execute([$name, $email, $phone, $password_hash, $security_question, $security_answer_hash]);
                        
                        // --- SEND WELCOME EMAIL START ---
                        $mail = new PHPMailer(true);
                        try {
                            // Server Settings
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
                            $mail->addAddress($email, $name);
                            $mail->addReplyTo($fromEmail);

                            // Content
                            $mail->isHTML(true);
                            $mail->Subject = 'Welcome to Asiko Store!';
                            $mail->Body    = "
                                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; color: #333;'>
                                    <h2 style='color: #06b6d4;'>Welcome to Asiko!</h2>
                                    <p>Hi <strong>" . htmlspecialchars($name) . "</strong>,</p>
                                    <p>Thank you for joining us. Your account has been successfully created.</p>
                                    <p>You can now log in to explore our products, track orders, and enjoy a seamless shopping experience.</p>
                                    <p style='margin-top: 25px;'>
                                        <a href='https://" . $_SERVER['HTTP_HOST'] . "/kiosk/auth.php' style='background-color: #06b6d4; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                                            Login Now
                                        </a>
                                    </p>
                                    <p style='margin-top: 30px; font-size: 12px; color: #999;'>Happy Shopping,<br>The Asiko Team</p>
                                </div>
                            ";
                            $mail->AltBody = "Welcome to Asiko, $name! Your account is ready. Login at https://" . $_SERVER['HTTP_HOST'] . "/kiosk/auth.php";

                            $mail->send();
                        } catch (Exception $e) {
                            // Silently fail email so user still registers successfully
                            // You could log $mail->ErrorInfo here if needed
                        }
                        // --- SEND WELCOME EMAIL END ---

                        $success = "Registration successful! We have sent a welcome email. Please log in with your phone number.";
                        $active_tab = 'login';

                    } catch (Exception $e) {
                        $error = "Registration failed: " . $e->getMessage();
                    }
                }
            }
        }

        // --- LOGIN LOGIC (Phone Number) ---
        if (isset($_POST['login'])) {
            $active_tab = 'login';
            $phone = sanitize($_POST['phone']); 
            $password = $_POST['password'];

            if (empty($phone) || empty($password)) {
                $error = "Phone number and password are required.";
            } else {
                // Fetch user by Phone
                $stmt = $pdo->prepare("SELECT id, name, password, role FROM users WHERE phone = ?");
                $stmt->execute([$phone]);
                $user = $stmt->fetch();

                if ($user && verifyPassword($password, $user['password'])) {
                    // Set pending session variables for 2FA
                    $_SESSION['pending_user_id'] = $user['id'];
                    $_SESSION['pending_user_name'] = $user['name'];
                    $_SESSION['pending_user_role'] = $user['role'];
                    $_SESSION['pending_remember_me'] = isset($_POST['remember_me']);

                    header("Location: security-question.php");
                    exit;
                } else {
                    $error = "Invalid phone number or password.";
                }
            }
        }
    }
    $csrf_token = generateCsrfToken(); // Regenerate for next request
}

include 'header.php';
?>

<style>
    .tab-button.active { border-color: var(--primary-color, #06b6d4); color: var(--primary-color, #06b6d4); }
    .btn-primary-bg { background-color: var(--primary-color, #06b6d4); color: white; }
    .btn-primary-bg:hover { background-color: var(--primary-color-hover, #0891b2); }
</style>

<main class="bg-gray-50 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full bg-white p-8 rounded-lg shadow-md" x-data="{ activeTab: '<?= $active_tab ?>' }">
        
        <div class="mb-6 border-b border-gray-200">
            <nav class="-mb-px flex space-x-6">
                <button @click="activeTab = 'login'" :class="{ 'active': activeTab === 'login' }" class="tab-button whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">Login</button>
                <button @click="activeTab = 'register'" :class="{ 'active': activeTab === 'register' }" class="tab-button whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">Register</button>
            </nav>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div x-show="activeTab === 'login'" x-cloak>
            <h2 class="text-2xl font-bold text-center text-gray-900 mb-6">Login with Phone</h2>
            <form class="space-y-6" action="auth.php" method="POST">
                <input type="hidden" name="login" value="1">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
                
                <div>
                    <label for="login-phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
                    <input id="login-phone" name="phone" type="tel" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500 sm:text-sm" placeholder="e.g., 08012345678">
                </div>
                <div>
                    <label for="login-password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input id="login-password" name="password" type="password" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500 sm:text-sm">
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember_me" name="remember_me" type="checkbox" class="h-4 w-4 text-yellow-600 focus:ring-yellow-500 border-gray-300 rounded">
                        <label for="remember_me" class="ml-2 block text-sm text-gray-900">Remember me</label>
                    </div>
                    <div class="text-sm">
                        <a href="forgot_password.php" class="font-medium text-blue-600 hover:text-blue-500">Forgot password?</a>
                    </div>
                </div>

                <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white btn-primary-bg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">Sign in</button>
            </form>
        </div>

        <div x-show="activeTab === 'register'" style="display: none;" x-cloak>
            <h2 class="text-2xl font-bold text-center text-gray-900 mb-6">Create Account</h2>
            <form class="space-y-4" action="auth.php" method="POST">
                <input type="hidden" name="register" value="1">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
                
                <input type="text" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Full Name">
                <input type="email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Email Address">
                <input type="tel" name="phone" required class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Phone Number">
                
                <input type="password" name="password" required class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Password">
                <input type="password" name="confirm_password" required class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Confirm Password">
                
                <select name="security_question" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-gray-600">
                    <option value="" disabled selected>Select a security question</option>
                    <option value="What was your first pet's name?">What was your first pet's name?</option>
                    <option value="What is your mother's maiden name?">What is your mother's maiden name?</option>
                    <option value="What city were you born in?">What city were you born in?</option>
                </select>
                <input type="text" name="security_answer" required class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Security Answer">

                <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white btn-primary-bg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">Register</button>
            </form>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<?php include 'footer.php'; ?>