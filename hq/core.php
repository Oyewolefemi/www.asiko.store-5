<?php
// admin/core.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load master Env manually so HQ can access all mapped databases
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim(trim($value), "'\"");
    }
}

define('APPS_CONFIG_FILE', __DIR__ . '/app_configs.json');

// Ensure the config file exists
if (!file_exists(APPS_CONFIG_FILE)) {
    file_put_contents(APPS_CONFIG_FILE, json_encode([]));
}

/**
 * Gets all configured applications
 */
function getConfiguredApps() {
    $data = file_get_contents(APPS_CONFIG_FILE);
    return json_decode($data, true) ?: [];
}

/**
 * Gets the configuration for the currently active app
 */
function getActiveAppConfig() {
    $apps = getConfiguredApps();
    $active_id = $_SESSION['active_app'] ?? null;
    
    if ($active_id && isset($apps[$active_id])) {
        return $apps[$active_id];
    }
    
    // Fallback: If no active app, return the first one found, or null
    return !empty($apps) ? reset($apps) : null;
}

/**
 * Dynamically connects to the database of the active application
 */
function getActivePDO() {
    $config = getActiveAppConfig();
    if (!$config) {
        return null; // No apps configured yet
    }

    // Determine prefix based on the active app (e.g., 'scrummy' becomes 'SCRUMMY')
    $active_app_name = $_SESSION['active_app'] ?? 'ASIKO';
    $prefix = strtoupper($active_app_name);
    
    // Check master .env first, fallback to JSON config
    $host = $_ENV['DB_HOST'] ?? $config['db_host'] ?? '127.0.0.1';
    $db   = $_ENV["DB_NAME_{$prefix}"] ?? $config['db_name'] ?? '';
    $user = $_ENV["DB_USER_{$prefix}"] ?? $config['db_user'] ?? '';
    $pass = $_ENV["DB_PASS_{$prefix}"] ?? $config['db_pass'] ?? '';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Dynamic DB Connection Failed for App '{$config['app_name']}' ($prefix): " . $e->getMessage());
        return null; // Handle failure gracefully in the UI
    }
}

// Handle App Switching
if (isset($_GET['switch_app'])) {
    $apps = getConfiguredApps();
    $target = $_GET['switch_app'];
    
    if (isset($apps[$target])) {
        $_SESSION['active_app'] = $target;
    }
    // Redirect to drop the GET parameter
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}
?>