<?php
session_start();
require 'db.php';
require_once 'functions.php'; // Ensure functions are available for isSetupComplete check

// --- PRE-LOGIN CHECKS ---
// Check if the initial setup is complete. If not, redirect to setup.php.
// We use a try-catch block in case the system_settings table hasn't been created yet.
try {
    if (!function_exists('getSystemSetting') || getSystemSetting('setup_completed') !== 'true') {
        header('Location: setup.php');
        exit;
    }
} catch (PDOException $e) {
    // If the database check fails (e.g., table not found), assume setup is needed.
    header('Location: setup.php');
    exit;
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameOrEmail = trim($_POST['username']);
    $password = $_POST['password'];

    if (!empty($usernameOrEmail) && !empty($password)) {
        try {
            // Retrieve user details along with the corresponding admin_id if they are an admin
            $stmt = $pdo->prepare("
                SELECT u.id, u.name, u.email, u.password, u.role, a.id as admin_id
                FROM users u 
                LEFT JOIN admins a ON u.id = a.user_id
                WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Set consistent session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                
                // IMPORTANT: If user is an admin, set admin_id for module context
                if ($user['role'] === 'admin' && $user['admin_id']) {
                    $_SESSION['admin_id'] = $user['admin_id'];
                }

                // Redirect to dashboard
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid credentials or user is inactive.';
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'A system error occurred. Please try again later.';
        }
    } else {
        $error = 'Please enter both email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-xl shadow-md w-full max-w-sm">
        <h1 class="text-2xl font-bold text-center mb-6">Login</h1>
        <?php if ($error): ?>
            <p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Username or Email</label>
                <input type="text" name="username" class="mt-1 block w-full px-3 py-2 border rounded-lg shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" name="password" class="mt-1 block w-full px-3 py-2 border rounded-lg shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
            </div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg px-4 py-2 transition">
                Login
            </button>
        </form>
    </div>
</body>
</html>
