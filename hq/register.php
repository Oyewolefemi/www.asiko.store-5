<?php
// hq/register.php
session_start();

// 1. Load HQ Database Connection
require_once __DIR__ . '/EnvLoader.php';
EnvLoader::load(__DIR__ . '/../.env');

try {
    $pdo = new PDO("mysql:host=".$_ENV['DB_HOST'].";dbname=".$_ENV['DB_NAME_HQ'].";charset=".$_ENV['DB_CHARSET'], $_ENV['DB_USER_HQ'], $_ENV['DB_PASS_HQ']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log('HQ Database connection failed: ' . $e->getMessage());
    die('System unavailable. Please try again later.');
}

$message = '';
$message_type = ''; // 'error' or 'success'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'] ?? 'superadmin';

    if (empty($username) || empty($password)) {
        $message = "Username and password are required.";
        $message_type = "error";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $message_type = "error";
    } else {
        // Check if the username is already taken
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        
        if ($stmt->fetch()) {
            $message = "This username is already taken.";
            $message_type = "error";
        } else {
            // Hash the password and insert the new admin
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_stmt = $pdo->prepare("INSERT INTO admins (username, password, role) VALUES (:username, :password, :role)");
            
            if ($insert_stmt->execute([':username' => $username, ':password' => $hashed_password, ':role' => $role])) {
                $message = "Admin account created successfully! You can now log in.";
                $message_type = "success";
            } else {
                $message = "A database error occurred while creating the account.";
                $message_type = "error";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HQ Vault - Register Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 h-screen flex items-center justify-center font-sans">

    <div class="bg-white p-8 rounded-xl shadow-lg max-w-md w-full border border-gray-200">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-gray-800">Register HQ Admin</h1>
            <p class="text-sm text-gray-500 mt-2">Create a new access account for the ecosystem.</p>
        </div>

        <?php if ($message): ?>
            <div class="p-3 rounded mb-4 text-sm text-center font-bold <?php echo $message_type === 'success' ? 'bg-green-50 text-green-600 border border-green-200' : 'bg-red-50 text-red-600 border border-red-200'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Username</label>
                <input type="text" name="username" required class="w-full px-4 py-3 rounded border border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-900 transition">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Role</label>
                <select name="role" class="w-full px-4 py-3 rounded border border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-900 transition bg-white">
                    <option value="superadmin">Super Admin</option>
                    <option value="manager">Manager</option>
                    <option value="vendor">Vendor</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                <input type="password" name="password" required class="w-full px-4 py-3 rounded border border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-900 transition">
            </div>

            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2">Confirm Password</label>
                <input type="password" name="confirm_password" required class="w-full px-4 py-3 rounded border border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-900 transition">
            </div>

            <button type="submit" class="w-full bg-gray-900 hover:bg-black text-white font-bold py-3 px-4 rounded-lg shadow-md transition mb-4">
                Create Admin Account
            </button>
            
            <div class="text-center">
                <a href="login.php" class="text-sm text-gray-500 hover:text-gray-800">Return to Login</a>
            </div>
        </form>
    </div>

</body>
</html>