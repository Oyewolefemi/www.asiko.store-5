<?php
// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load Env (Smart Path Loading for Security)
require_once __DIR__ . '/includes/EnvLoader.php';

$secureEnvPath = dirname(__DIR__) . '/.env'; // Ideal: Master file at root

if (file_exists($secureEnvPath)) {
    EnvLoader::load($secureEnvPath);
    $_SESSION['env_status'] = 'secure';
} else {
    die("Critical Error: Master configuration file missing.");
}

// Database Connection using SCRUMMY prefix
try {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $db   = getenv('DB_NAME_SCRUMMY');
    $user = getenv('DB_USER_SCRUMMY');
    $pass = getenv('DB_PASS_SCRUMMY');

    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Connection Error. Please check your configuration.");
}

// --- NEW: Load Settings ---
$settings = [
    'site_name' => 'Scrummy Nummy',
    'primary_color' => '#ec6d13',
    'logo_url' => ''
];
try {
    $stmt = $pdo->query("SELECT key_name, value FROM settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['key_name']] = $row['value'];
    }
} catch (Exception $e) { /* Fail silently if table doesn't exist yet */ }

// Helper Functions
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getBankDetails() {
    return [
        'bank' => getenv('BANK_NAME'),
        'number' => getenv('BANK_ACC_NO_MANUAL'),
        'name' => getenv('BANK_ACC_NAME_MANUAL')
    ];
}
?>