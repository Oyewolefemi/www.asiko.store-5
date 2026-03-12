<?php
// hq/login.php
session_start();

// 1. Load HQ Database Connection
require_once __DIR__ . '/EnvLoader.php';
EnvLoader::load(__DIR__ . '/../.env');

try {
    // FIXED: Now properly connecting to hq_db!
    $pdo = new PDO("mysql:host=".$_ENV['DB_HOST'].";dbname=".$_ENV['DB_NAME_HQ'].";charset=".$_ENV['DB_CHARSET'], $_ENV['DB_USER_HQ'], $_ENV['DB_PASS_HQ']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log('HQ Database connection failed: ' . $e->getMessage());
    die('System unavailable. Please try again later.');
}

$error = '';
$app_slug = $_GET['app'] ?? 'hq';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $target_app = $_POST['app_slug'];

    // 2. Verify against the HQ Vault
    $stmt = $pdo->prepare("SELECT id, username, password, role FROM admins WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        
        // --- ADDED: Set the Master Session for the HQ Dashboard ---
        $_SESSION['master_admin_id'] = $admin['id'];
        $_SESSION['master_admin_user'] = $admin['username'];
        // ----------------------------------------------------------
        
        // 3. Build the JWT Payload
        $payload = [
            'iss' => 'asiko_hq',
            'aud' => $target_app,
            'iat' => time(),
            'exp' => time() + (86400),
            'user_id' => $admin['id'],
            'name' => $admin['username'],
            'role' => $admin['role'] ?? 'superadmin'
        ];

        // 4. Sign the Token
        $header = json_encode(['typ' => 'JWT', 'alg' => 'RS256']);
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));
        
        $privateKeyPath = __DIR__ . '/keys/private.pem';
        if (!file_exists($privateKeyPath)) {
            error_log('HQ SSO private key missing.');
            $error = 'Authentication service temporarily unavailable.';
        }

        $privateKeyRaw = file_exists($privateKeyPath) ? file_get_contents($privateKeyPath) : '';
        if (strpos($privateKeyRaw, 'REPLACE_WITH_RUNTIME_PRIVATE_KEY_FROM_SECRET_MANAGER') !== false) {
            error_log('HQ SSO private key placeholder detected.');
            $error = 'Authentication service temporarily unavailable.';
        }

        if (!$error) {
            $privateKey = openssl_pkey_get_private($privateKeyRaw);
            if (!$privateKey) {
                error_log('Failed to parse HQ private key.');
                $error = 'Authentication service temporarily unavailable.';
            }
        }

        if (!$error) {
            $signature = '';
            openssl_sign($base64UrlHeader . "." . $base64UrlPayload, $signature, $privateKey, OPENSSL_ALGO_SHA256);
            $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

            $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;

            // 5. Set the Master Cookie
            setcookie('asiko_sso_token', $jwt, [
                'expires' => time() + 86400,
                'path' => '/',
                'domain' => '',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            // 6. Redirect back to the requesting app
            if ($target_app === 'hq') {
                header("Location: index.php");
            } else {
                $safe_path = preg_replace('/[^a-zA-Z0-9_\/-]/', '', $target_app);
                header("Location: /" . ltrim($safe_path, '/'));
            }
            exit;
        }
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asiko SSO - Secure Login</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 h-screen flex items-center justify-center font-sans">
    <div class="bg-white p-8 rounded-xl shadow-lg max-w-md w-full border border-gray-200">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-gray-800">Asiko Ecosystem</h1>
            <p class="text-sm text-gray-500 mt-2">Log in once to access <span class="font-bold text-green-600 uppercase"><?php echo htmlspecialchars($app_slug); ?></span></p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 text-red-600 p-3 rounded mb-4 text-sm text-center border border-red-200 font-bold">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="app_slug" value="<?php echo htmlspecialchars($app_slug); ?>">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Username</label>
                <input type="text" name="username" required class="w-full px-4 py-3 rounded border border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-900 transition">
            </div>
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                <input type="password" name="password" required class="w-full px-4 py-3 rounded border border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-900 transition">
            </div>
            <button type="submit" class="w-full bg-gray-900 hover:bg-black text-white font-bold py-3 px-4 rounded-lg shadow-md transition">
                Authenticate & Continue
            </button>
        </form>
    </div>
</body>
</html>