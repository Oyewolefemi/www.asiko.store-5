<?php
// kiosk/Red/approve_order.php
session_start();
include '../config.php';
include '../functions.php';

// Include the centralized mailer system
include_once __DIR__ . '/../../mailing/mailer.php';

// 1. Security Check
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'superadmin') {
    die("Access Denied. Only Super Admin can approve payments.");
}

// 2. CSRF & Method Protection
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Method not allowed');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    die('Invalid or expired security token. Please go back and try again.');
}

// 3. Get Order ID
$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

if ($order_id > 0) {
    try {
        // Fetch order details to get the user's email and order number
        $stmt_info = $pdo->prepare("
            SELECT o.order_number, u.email, u.name 
            FROM orders o 
            JOIN users u ON o.user_id = u.id 
            WHERE o.id = ?
        ");
        $stmt_info->execute([$order_id]);
        $order_info = $stmt_info->fetch();

        // Update Status
        $stmt = $pdo->prepare("UPDATE orders SET status = 'active', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$order_id]);

        // Send "Payment Received" Email
        if ($order_info && !empty($order_info['email'])) {
            $messageBody = "
                <h2 style='color: #10b981;'>Payment Confirmed!</h2>
                <p>Hi {$order_info['name']},</p>
                <p>Great news! We have successfully received the payment for your order <strong>#{$order_info['order_number']}</strong>.</p>
                <p>Your order is now being processed by our vendors. We will notify you once it has been dispatched.</p>
                <p>Thank you for shopping with us!</p>
            ";
            
            // Call the central mailer function
            sendAsikoMail($order_info['email'], $order_info['name'], "Payment Confirmed - Order #{$order_info['order_number']}", $messageBody);
        }

        header("Location: orders.php?msg=approved");
        exit;
    } catch (PDOException $e) {
        die("Error approving order: " . $e->getMessage());
    }
} else {
    header("Location: orders.php?error=invalid_id");
    exit;
}
?>