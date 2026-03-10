<?php
// asikp/asiko/db.php

// 1. Define a function to parse the .env file manually
// (This avoids needing to include complex loaders from the other folder)
if (!function_exists('loadEnvConfig')) {
    function loadEnvConfig($path) {
        if (!file_exists($path)) {
            die("Configuration Error: .env file not found at $path");
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Skip comments and lines without equals signs
            if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) {
                continue;
            }
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Remove quotes if present
            if (substr($value, 0, 1) === '"' && substr($value, -1) === '"') {
                $value = substr($value, 1, -1);
            } elseif (substr($value, 0, 1) === "'" && substr($value, -1) === "'") {
                $value = substr($value, 1, -1);
            }
            
            $_ENV[$name] = $value;
        }
    }
}

// 2. Locate the Kiosk .env file (Sibling directory)
// We assume structure is: /root/asiko/db.php AND /root/kiosk/.env
$envPath = __DIR__ . '/../kiosk/.env';

// 3. Load the variables
loadEnvConfig($envPath);

// 4. Set DB Credentials from .env
$host     = $_ENV['DB_HOST'] ?? '127.0.0.1';
$dbname   = $_ENV['DB_NAME'] ?? 'asiko';
$username = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASS'] ?? '';
$charset  = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // Hide the actual password in logs for security
    error_log("Database connection failed: " . $e->getMessage());
    die("System Error: Could not connect to the database. Please check configuration.");
}
?>