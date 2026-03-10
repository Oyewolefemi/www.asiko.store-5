<?php
// dns/kiosk/Red/admin_auth.php
// SIMPLE & STRONG: Login Only (Registration Disabled)

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

try {
    // Load system files
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../functions.php';
    require_once __DIR__ . '/../EnvLoader.php';
    EnvLoader::load(__DIR__ . '/../.env');
} catch (Exception $e) { die("System Error."); }

// Redirect if already logged in
if (isset($_SESSION['admin_id'])) {
    if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'superadmin') {
        header("Location: superadmin_dashboard.php");
    } else {
        header("Location: admin_dashboard.php");
    }
    exit();
}

$error = '';
$csrf_token = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Session expired. Please refresh.";
    } else {
        $username = sanitize(trim($_POST['username']));
        $password = $_POST['password'];

        $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && verifyPassword($password, $admin['password_hash'])) { 
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role']; 
            
            // Log the login
            logActivity($admin['id'], 'Login', 'Logged in successfully');

            if ($admin['role'] === 'superadmin') {
                header("Location: superadmin_dashboard.php");
            } else {
                header("Location: admin_dashboard.php");
            }
            exit();
        } else {
            $error = "Incorrect username or password.";
        }
    }
    $csrf_token = generateCsrfToken();
}

$store_name = EnvLoader::get('STORE_NAME', 'Administration');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?= htmlspecialchars($store_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-sm bg-white rounded-xl shadow-2xl overflow-hidden">
        <div class="bg-gray-50 p-6 border-b text-center">
            <h2 class="text-xl font-bold text-gray-800">Admin Portal</h2>
            <p class="text-xs text-gray-500 mt-1">Authorized Personnel Only</p>
        </div>

        <div class="p-8">
            <?php if ($error): ?>
                <div class="bg-red-50 text-red-600 p-3 rounded text-sm mb-4 border border-red-200 text-center">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Username</label>
                    <input type="text" name="username" class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none transition" placeholder="Enter username" required>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Password</label>
                    <input type="password" name="password" class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none transition" placeholder="••••••••" required>
                </div>

                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition shadow-lg mt-2">
                    Sign In
                </button>
            </form>
        </div>
        
        <div class="bg-gray-50 p-4 text-center border-t border-gray-100">
            <p class="text-xs text-gray-400">&copy; <?= date('Y') ?> <?= htmlspecialchars($store_name) ?></p>
        </div>
    </div>

</body>
</html>