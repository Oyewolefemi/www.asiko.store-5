<?php
// FILE: admin/logout.php
require_once '../config.php';

// 1. Unset all session variables
$_SESSION = [];

// 2. Kill the session cookie (if it exists)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destroy the session entirely
session_destroy();

// 4. Redirect to Admin Login
header("Location: login.php");
exit;
?>