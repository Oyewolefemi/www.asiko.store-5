<?php
// mailing/db.php

require_once __DIR__ . '/EnvLoader.php';
EnvLoader::load(__DIR__ . '/../.env');

try {
    // Primary Connection: The isolated Mailing Database
    $pdo = new PDO("mysql:host=".$_ENV['DB_HOST'].";dbname=".$_ENV['DB_NAME_MAILING'].";charset=".$_ENV['DB_CHARSET'], $_ENV['DB_USER_MAILING'], $_ENV['DB_PASS_MAILING']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Secondary Connection: Asiko DB (Read-only access for syncing users)
    $pdo_asiko = new PDO("mysql:host=".$_ENV['DB_HOST'].";dbname=".$_ENV['DB_NAME_ASIKO'].";charset=".$_ENV['DB_CHARSET'], $_ENV['DB_USER_ASIKO'], $_ENV['DB_PASS_ASIKO']);
    $pdo_asiko->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("Mailing Database connection failed: " . $e->getMessage());
}
?>