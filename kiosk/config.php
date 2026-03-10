<?php
// config.php
// Securely load DB config from .env file (no external library)
if (!function_exists('loadEnv')) {
    function loadEnv($path) {
        if (!file_exists($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (substr(trim($line), 0, 1) === '#' || strpos($line, '=') === false) { 
                continue;
            }
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, "'\" ");
            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }
}

// Load master .env file from root
loadEnv(__DIR__ . '/../.env');

// Database config from .env using ASIKO prefix
$host    = $_ENV['DB_HOST'] ?? '127.0.0.1';
$db      = $_ENV['DB_NAME_ASIKO'] ?? '';
$user    = $_ENV['DB_USER_ASIKO'] ?? '';
$pass    = $_ENV['DB_PASS_ASIKO'] ?? '';
$charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>