<?php
// kiosk/Red/admin_auth.php
session_start();

// 1. If already logged in, go straight to the dashboard
if (isset($_SESSION['admin_id'])) {
    header("Location: admin_dashboard.php");
    exit;
}

// 2. THE MASTER VAULT BACKDOOR (For Super Admins)
$sso_token = $_COOKIE['asiko_sso_token'] ?? null;
if ($sso_token) {
    $parts = explode('.', $sso_token);
    if (count($parts) === 3) {
        list($header64, $payload64, $signature64) = $parts;
        $publicKeyPath = __DIR__ . '/../../hq/keys/public.pem'; 
        if (file_exists($publicKeyPath)) {
            $publicKey = openssl_pkey_get_public(file_get_contents($publicKeyPath));
            $signature = base64_decode(str_replace(['-', '_'], ['+', '/'], $signature64));
            $dataToSign = $header64 . "." . $payload64;
            
            if (openssl_verify($dataToSign, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1) {
                $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload64)), true);
                if (isset($payload['exp']) && $payload['exp'] >= time()) {
                    // VIP Pass accepted! Log you in as Super Admin automatically
                    $_SESSION['admin_id'] = $payload['user_id']; 
                    $_SESSION['admin_username'] = $payload['name'];
                    $_SESSION['admin_role'] = 'superadmin';
                    $_SESSION['store_name'] = 'Mall HQ';
                    header("Location: admin_dashboard.php");
                    exit;
                }
            }
        }
    }
}

// 3. LOCAL VENDOR LOGIN (For 3rd Party Sellers)
require_once __DIR__ . '/../config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    try {
        // Querying the local Kiosk 'asiko' database for vendors
        $stmt = $pdo->prepare("SELECT id, username, password_hash, role, store_name FROM admins WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($vendor && password_verify($password, $vendor['password_hash'])) {
            $_SESSION['admin_id'] = $vendor['id'];
            $_SESSION['admin_username'] = $vendor['username'];
            $_SESSION['admin_role'] = $vendor['role'];
            $_SESSION['store_name'] = $vendor['store_name'] ?? 'My Store';
            
            header("Location: admin_dashboard.php");
            exit;
        } else {
            $error = "Invalid vendor credentials.";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Central - Asiko Mall</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 h-screen flex items-center justify-center font-sans">
    <div class="bg-white p-8 rounded-xl shadow-lg max-w-md w-full border border-gray-200">
        <div class="text-center mb-8">
            <div class="flex justify-center mb-4">
                <div class="h-14 w-14 bg-red-600 text-white rounded-xl flex items-center justify-center shadow-md">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                </div>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">Seller Central</h1>
            <p class="text-sm text-gray-500 mt-2">Manage your Asiko Mall storefront</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 text-red-600 p-3 rounded mb-4 text-sm text-center border border-red-200 font-bold">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Vendor Username</label>
                <input type="text" name="username" required class="w-full px-4 py-3 rounded border border-gray-300 focus:outline-none focus:ring-2 focus:ring-red-500 transition">
            </div>
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                <input type="password" name="password" required class="w-full px-4 py-3 rounded border border-gray-300 focus:outline-none focus:ring-2 focus:ring-red-500 transition">
            </div>
            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-4 rounded-lg shadow-md transition">
                Sign In to Storefront
            </button>
        </form>
        
        <div class="mt-6 text-center pt-6 border-t border-gray-100">
            <p class="text-sm text-gray-600">Want to sell on Asiko Mall?</p>
            <a href="vendor_register.php" class="text-red-600 font-bold hover:underline mt-1 inline-block">Register as a Vendor</a>
        </div>
        
        <div class="mt-6 text-center text-xs text-gray-400">
            <p>Secured by Asiko Ecosystem</p>
        </div>
    </div>
</body>
</html>