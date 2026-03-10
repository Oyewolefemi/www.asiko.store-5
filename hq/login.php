<?php
// admin/login.php
session_start();

// We need a hardcoded "Anchor" connection just to verify the Super Admin.
// We load the main Kiosk environment for this.
require_once __DIR__ . '/../kiosk/EnvLoader.php';
EnvLoader::load(__DIR__ . '/../kiosk/.env');

// Using your exact .env values as fallbacks in case EnvLoader pathing fails
$anchor_host = EnvLoader::get('DB_HOST', '127.0.0.1');
$anchor_name = EnvLoader::get('DB_NAME', 'asiko');
$anchor_user = EnvLoader::get('DB_USER', 'levi');
$anchor_pass = EnvLoader::get('DB_PASS', 'Blueradish@8');

try {
    $pdo_anchor = new PDO("mysql:host=$anchor_host;dbname=$anchor_name;charset=utf8mb4", $anchor_user, $anchor_pass);
    $pdo_anchor->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // If it fails, print a clean, detailed error box to the screen
    die("
        <div style='font-family: sans-serif; padding: 20px; background: #fee2e2; color: #991b1b; border: 1px solid #f87171; border-radius: 8px; max-width: 600px; margin: 40px auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
            <h3 style='margin-top: 0; font-size: 20px;'>Master Anchor Connection Failed</h3>
            <p><b>Attempted Host:</b> " . htmlspecialchars($anchor_host) . "</p>
            <p><b>Attempted DB Name:</b> " . htmlspecialchars($anchor_name) . "</p>
            <p><b>Attempted User:</b> " . htmlspecialchars($anchor_user) . "</p>
            <hr style='border: 0; border-top: 1px solid #fca5a5; margin: 15px 0;'>
            <p><b>MySQL Error:</b> " . htmlspecialchars($e->getMessage()) . "</p>
            <p style='font-size: 12px; margin-bottom: 0; color: #b91c1c; margin-top: 15px;'><em>Check your relative path to kiosk/.env and database permissions.</em></p>
        </div>
    ");
}

// Redirect if already logged in
if (isset($_SESSION['master_admin_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    try {
        $stmt = $pdo_anchor->prepare("SELECT * FROM admins WHERE username = ? AND role = 'superadmin'");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password_hash'])) {
            
            // 1. HQ Session
            $_SESSION['master_admin_id'] = $admin['id'];
            $_SESSION['master_admin_user'] = $admin['username'];

            // 2. UNIVERSAL SSO KEYS
            // These keys instantly unlock Kiosk (Red), Scrummy, and the Mailing module
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_role'] = 'superadmin';
            $_SESSION['admin_name'] = 'HQ Master';
            
            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid Super Admin credentials.";
        }
    } catch (PDOException $e) {
        $error = "Database query error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Master Control - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-900 flex items-center justify-center h-screen font-sans">
    <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-sm">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-black text-gray-800 tracking-wider">ASIKO <span class="text-blue-600">HQ</span></h1>
            <p class="text-[10px] text-gray-500 uppercase tracking-widest mt-2 font-bold">System Architecture</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 p-3 text-sm rounded mb-6 font-bold flex items-start gap-2">
                <span>⚠️</span> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Username</label>
                <input type="text" name="username" required class="w-full border border-gray-300 p-3 rounded-lg bg-gray-50 outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Password</label>
                <input type="password" name="password" required class="w-full border border-gray-300 p-3 rounded-lg bg-gray-50 outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition">
            </div>
            <button type="submit" class="w-full bg-gray-900 hover:bg-black text-white font-bold py-3.5 rounded-lg transition shadow-lg mt-2">
                Initialize System
            </button>
        </form>
        
        <div class="mt-6 text-center border-t pt-4">
            <p class="text-xs text-gray-400">Authorized Personnel Only</p>
        </div>
    </div>
</body>
</html>