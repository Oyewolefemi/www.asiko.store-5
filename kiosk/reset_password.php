<?php
include_once 'config.php';
include_once 'functions.php';
require_once 'EnvLoader.php';

$error = "";
$success = "";
$validToken = false;

// 1. Verify Token
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $resetRequest = $stmt->fetch();

    if ($resetRequest) {
        $validToken = true;
    } else {
        $error = "Invalid or expired token. Please request a new one.";
    }
} else {
    header("Location: auth.php");
    exit();
}

// 2. Handle Password Update
if (isset($_POST['update_password']) && $validToken) {
    $new_pass = $_POST['password'];
    $confirm_pass = $_POST['confirm_password'];

    if ($new_pass !== $confirm_pass) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_pass) < 4) { // Basic check
        $error = "Password is too short.";
    } else {
        // Use secureHash() from functions.php to match auth.php login logic
        $hashed_password = function_exists('secureHash') ? secureHash($new_pass) : password_hash($new_pass, PASSWORD_DEFAULT);
        $email = $resetRequest['email'];

        try {
            $pdo->beginTransaction();

            // Update Password
            $update = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            $update->execute([$hashed_password, $email]);

            // Delete Token
            $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

            $pdo->commit();
            $success = "Password updated successfully! <a href='auth.php' class='underline font-bold text-blue-600'>Login now</a>.";
            $validToken = false; // Hide form

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "System error: " . $e->getMessage();
        }
    }
}

include 'header.php';
?>

<main class="bg-gray-50 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full bg-white p-8 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold text-center text-gray-900 mb-6">Set New Password</h2>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 text-sm"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 text-sm"><?= $success ?></div>
        <?php endif; ?>

        <?php if ($validToken): ?>
        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700">New Password</label>
                <input type="password" name="password" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500 sm:text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Confirm Password</label>
                <input type="password" name="confirm_password" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500 sm:text-sm">
            </div>

            <button type="submit" name="update_password" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg px-4 py-2 transition">
                Update Password
            </button>
        </form>
        <?php else: ?>
             <div class="text-center mt-4">
                <a href="forgot_password.php" class="text-sm text-blue-600 hover:text-blue-500">Request new link</a>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include 'footer.php'; ?>