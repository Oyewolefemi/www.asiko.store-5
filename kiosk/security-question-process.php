<?php
// Start session at the very top (crucial for processing scripts)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'config.php';
include 'functions.php';

// Check if user is in the pending state
if (!isset($_SESSION['pending_user_id'])) {
    header("Location: auth.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Verify CSRF Token (Essential step)
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "Security token expired. Please try again.";
        header("Location: security-question.php");
        exit;
    }
    
    $userId = $_SESSION['pending_user_id'];
    $securityAnswerInput = $_POST['security_answer'] ?? '';

    if (empty($securityAnswerInput)) {
        $_SESSION['flash_error'] = "Please provide an answer to continue.";
        header("Location: security-question.php");
        exit;
    }

    try {
        // 2. Get stored hash and user details
        $stmt = $pdo->prepare("SELECT id, name, role, security_answer_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || empty($user['security_answer_hash'])) {
            $_SESSION['flash_error'] = "Security configuration missing. Please log in again.";
            header("Location: auth.php");
            exit;
        }

        // 3. Verify Answer
        if (verifyPassword($securityAnswerInput, $user['security_answer_hash'])) {
            // --- SUCCESSFUL LOGIN ---
            
            session_regenerate_id(true);

            // Promote pending session to real session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            // 4. Handle Remember Me
            if (isset($_SESSION['pending_remember_me']) && $_SESSION['pending_remember_me'] === true) {
                $selector = bin2hex(random_bytes(16));
                $validator = bin2hex(random_bytes(32));
                $cookie_value = $selector . ':' . $validator;
                
                setcookie('remember_me', $cookie_value, [
                    'expires' => time() + (86400 * 30),
                    'path' => '/',
                    'secure' => isset($_SERVER['HTTPS']),
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
                
                $hashed_validator = password_hash($validator, PASSWORD_DEFAULT);
                $expires = date('Y-m-d H:i:s', time() + (86400 * 30));
                $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ?")->execute([$user['id']]);
                $pdo->prepare("INSERT INTO auth_tokens (selector, hashed_validator, user_id, expires) VALUES (?, ?, ?, ?)")
                    ->execute([$selector, $hashed_validator, $user['id'], $expires]);
            }

          // 5. Clean up pending variables
unset($_SESSION['pending_user_id']);
unset($_SESSION['pending_user_name']);
unset($_SESSION['pending_user_role']);
unset($_SESSION['pending_remember_me']);

// FIX #15: Recalculate cart count on login so badge is accurate
try {
    $cart_stmt = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
    $cart_stmt->execute([$user['id']]);
    $_SESSION['cart_count'] = $cart_stmt->fetchColumn() ?: 0;
} catch (Exception $e) {
    $_SESSION['cart_count'] = 0;
}

// 6. Redirect to dashboard
if ($user['role'] === 'vendor' || $user['role'] === 'admin') {
    header("Location: Red/admin_dashboard.php");
} else {
    header("Location: my-account.php");
}
            // CRUCIAL: Exit immediately after sending the header to prevent any further script execution
            exit; 

        } else {
            // Incorrect answer
            $_SESSION['flash_error'] = "The security answer is incorrect.";
            header("Location: security-question.php");
            exit;
        }

    } catch (Exception $e) {
        error_log("Security error: " . $e->getMessage());
        $_SESSION['flash_error'] = "A system error occurred. Please try again.";
        header("Location: security-question.php");
        exit;
    }
} else {
    // Accessing via GET
    header("Location: auth.php");
    exit;
}
?>