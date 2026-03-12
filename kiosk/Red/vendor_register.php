<?php
// kiosk/Red/vendor_register.php
session_start();
require_once __DIR__ . '/../config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $store_name = trim($_POST['store_name']);
    
    // Auto-generate a clean slug from the store name
    $store_slug = preg_replace('/[^a-z0-9-]/', '-', strtolower($store_name));
    
    if (empty($username) || empty($password) || empty($store_name)) {
        $error = "All fields are required.";
    } else {
        try {
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            
            if ($stmt->fetch()) {
                $error = "That username is already taken. Please choose another.";
            } else {
                // Ensure slug is unique by appending random numbers if needed
                $slugCheck = $pdo->prepare("SELECT id FROM admins WHERE store_slug = ?");
                $slugCheck->execute([$store_slug]);
                if ($slugCheck->fetch()) {
                    $store_slug .= '-' . rand(100, 999);
                }

                // Insert the new Vendor
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $insert = $pdo->prepare("INSERT INTO admins (username, password_hash, role, store_name, store_slug) VALUES (?, ?, 'admin', ?, ?)");
                
                if ($insert->execute([$username, $hashed_password, $store_name, $store_slug])) {
                    $success = "Registration successful! You can now log in to Seller Central.";
                } else {
                    $error = "Failed to create vendor account.";
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Become a Seller - Asiko Mall</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 h-screen flex items-center justify-center font-sans">
    <div class="bg-white p-8 rounded-xl shadow-lg max-w-md w-full border border-gray-200">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-gray-800">Become a Seller</h1>
            <p class="text-sm text-gray-500 mt-2">Open your store on Asiko today.</p>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-50 text-green-700 p-4 rounded mb-6 text-sm text-center border border-green-200 font-bold">
                <?= htmlspecialchars($success) ?><br><br>
                <a href="admin_auth.php" class="underline">Click here to Sign In</a>
            </div>
        <?php else: ?>
            
            <?php if ($error): ?>
                <div class="bg-red-50 text-red-600 p-3 rounded mb-4 text-sm text-center border border-red-200 font-bold">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Business/Store Name</label>
                    <input type="text" name="store_name" required class="w-full px-4 py-3 rounded border border-gray-300 focus:outline-none focus:ring-2 focus:ring-red-500 transition">
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Login Username</label>
                    <input type="text" name="username" required class="w-full px-4 py-3 rounded border border-gray-300 focus:outline-none focus:ring-2 focus:ring-red-500 transition">
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Secure Password</label>
                    <input type="password" name="password" required class="w-full px-4 py-3 rounded border border-gray-300 focus:outline-none focus:ring-2 focus:ring-red-500 transition">
                </div>

                <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-4 rounded-lg shadow-md transition">
                    Create Seller Account
                </button>
            </form>
            
            <div class="mt-6 text-center pt-6 border-t border-gray-100">
                <p class="text-sm text-gray-600">Already have a store?</p>
                <a href="admin_auth.php" class="text-red-600 font-bold hover:underline mt-1 inline-block">Sign In to Seller Central</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>