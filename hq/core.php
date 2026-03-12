<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim(trim($value), "'\"");
    }
}

define('APPS_CONFIG_FILE', __DIR__ . '/app_configs.json');

if (!file_exists(APPS_CONFIG_FILE)) {
    error_log('HQ configuration file missing: ' . APPS_CONFIG_FILE);
}

function getConfiguredApps(): array {
    if (!file_exists(APPS_CONFIG_FILE)) {
        return [];
    }

    $data = file_get_contents(APPS_CONFIG_FILE);
    return json_decode($data, true) ?: [];
}

function getActiveAppConfig(): ?array {
    $apps = getConfiguredApps();
    $activeId = $_SESSION['active_app'] ?? null;

    if ($activeId && isset($apps[$activeId])) {
        return $apps[$activeId];
    }

    return !empty($apps) ? reset($apps) : null;
}

function getDbCredentialsForAppId(string $appId): array {
    $prefix = strtoupper($appId);
    return [
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'name' => $_ENV["DB_NAME_{$prefix}"] ?? '',
        'user' => $_ENV["DB_USER_{$prefix}"] ?? '',
        'pass' => $_ENV["DB_PASS_{$prefix}"] ?? '',
    ];
}

function getActivePDO(): ?PDO {
    $config = getActiveAppConfig();
    if (!$config) {
        return null;
    }

    $activeAppId = $_SESSION['active_app'] ?? ($config['app_id'] ?? '');
    $db = getDbCredentialsForAppId($activeAppId);

    if (empty($db['name']) || empty($db['user'])) {
        error_log("DB credentials missing for app '{$activeAppId}' in environment variables.");
        return null;
    }

    try {
        $pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Dynamic DB connection failed for app '{$activeAppId}': " . $e->getMessage());
        return null;
    }
}

if (empty($_SESSION['hq_csrf_token'])) {
    $_SESSION['hq_csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['switch_app'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['hq_csrf_token'], $token)) {
        http_response_code(403);
        exit('Security check failed.');
    }

    $apps = getConfiguredApps();
    $target = $_POST['switch_app'];

    if (isset($apps[$target])) {
        $_SESSION['active_app'] = $target;
    }

    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}
?>
