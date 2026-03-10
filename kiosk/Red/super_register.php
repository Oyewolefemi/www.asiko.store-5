<?php
// kiosk/Red/super_register.php

// --- DYNAMIC SECURITY CHECK ---
$config_file = __DIR__ . '/super_config.json';

// If no config exists, it's a fresh setup. Leave unlocked by default.
if (!file_exists($config_file)) {
    $super_config = ['locked' => false, 'allowed_ip' => ''];
} else {
    $super_config = json_decode(file_get_contents($config_file), true);
}

// If locked, verify the IP matches the whitelisted IP
if (isset($super_config['locked']) && $super_config['locked'] === true) {
    if ($_SERVER['REMOTE_ADDR'] !== $super_config['allowed_ip']) {
        die("
            <div style='font-family:sans-serif; text-align:center; padding:50px; background-color:#fef2f2; height:100vh;'>
                <h1 style='color:#b91c1c; font-size:2rem; margin-bottom:10px;'>Access Denied</h1>
                <p style='color:#4b5563;'>Super Admin registration is currently locked for security.</p>
                <p style='color:#4b5563;'>Please update the security settings from the Super Admin Dashboard to gain access.</p>
            </div>
        ");
    }
}
// ------------------------------

session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    if (file_exists(__DIR__ . '/../config.php')) {
        require_once __DIR__ . '/../config.php';
        require_once __DIR__ . '/../functions.php';
    } else {
        die("System Error: Configuration file not found.");
    }
} catch (Exception $e) {
    die("System Error: " . $e->getMessage());
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize(trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Username and Password are required.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = "Username already taken.";
        } else {
            $hash = secureHash($password);
            $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash, role) VALUES (?, ?, 'superadmin')");
            
            if ($stmt->execute([$username, $hash])) {
                $success = "Super Admin created successfully! <a href='admin_auth.php' class='underline font-bold'>Login here</a>.";
                
                // --- SMART AUTO-LOCK ---
                // Lock the file instantly and whitelist their current IP so it's secure immediately
                $super_config['locked'] = true;
                $super_config['allowed_ip'] = $_SERVER['REMOTE_ADDR'];
                file_put_contents($config_file, json_encode($super_config));
                
            } else {
                $error = "Database error. Could not register.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register Super Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-900 flex items-center justify-center min-h-screen px-4">
    <div class="bg-white w-full max-w-md p-8 rounded-lg shadow-2xl">
        <h2 class="text-3xl font-bold text-center text-gray-900 mb-2">Super Admin Setup</h2>
        
        <?php if (!isset($super_config['locked']) || $super_config['locked'] === false): ?>
        <div class="bg-yellow-50 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 text-sm">
            <p class="font-bold">⚠️ Initial Setup Mode</p>
            <p>Registration is currently open. Once you create your account, this page will <strong>automatically lock itself</strong> to your current IP address (<?= $_SERVER['REMOTE_ADDR'] ?>).</p>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4 border border-red-200"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-100 text-green-700 p-4 rounded mb-4 border border-green-200">
                <?= $success ?>
                <p class="text-xs mt-2 text-green-800">🔒 System automatically locked to IP: <?= $_SERVER['REMOTE_ADDR'] ?></p>
            </div>
        <?php else: ?>
            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-gray-700 font-bold mb-2">Username</label>
                    <input type="text" name="username" class="w-full border border-gray-300 p-3 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Create a username" required>
                </div>
                <div>
                    <label class="block text-gray-700 font-bold mb-2">Password</label>
                    <input type="password" name="password" class="w-full border border-gray-300 p-3 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Create a password" required>
                </div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded transition duration-200 shadow-md">
                    Create Super Admin
                </button>
            </form>
        <?php endif; ?>
        
        <div class="mt-6 text-center">
            <a href="admin_auth.php" class="text-sm text-gray-500 hover:text-gray-800 font-medium">Back to Regular Login</a>
        </div>
    </div>
</body>
</html>