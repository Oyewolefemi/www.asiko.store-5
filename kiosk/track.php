<?php
include 'header.php';
include 'config.php';
include 'functions.php';

$order = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = intval($_POST['order_id'] ?? 0);
    // --- FIX: Change variable name to phone for consistency with login ---
    $phone = sanitize($_POST['phone'] ?? '');

    if ($order_id <= 0 || empty($phone)) {
        $error = "Please provide both an Order ID and the phone number used to place the order.";
    } else {
        try {
            // --- FIX: Join with users table on phone number ---
            $stmt = $pdo->prepare("
                SELECT 
                    o.id,
                    o.order_date,
                    o.total_amount,
                    o.delivery_fee,
                    o.status,
                    o.shipping_address,
                    o.created_at,
                    u.email,
                    u.phone
                FROM orders o 
                JOIN users u ON o.user_id = u.id
                WHERE o.id = ? AND u.phone = ?
            ");
            $stmt->execute([$order_id, $phone]);
            $order = $stmt->fetch();
            
            if (!$order) {
                $error = "No matching order found for this ID and phone number combination. Please check your details and try again.";
            }

        } catch (Exception $e) {
            $error = "An error occurred while fetching your order details.";
            error_log("Order tracking error: " . $e->getMessage());
        }
    }
}

// --- FIX: Updated Status Configuration to include all system statuses ---
$statusConfig = [
    'pending'           => ['label' => 'Order Placed (Unpaid)', 'class' => 'status-pending'],
    'payment_submitted' => ['label' => 'Payment Submitted', 'class' => 'status-submitted'],
    'confirmed'         => ['label' => 'Confirmed', 'class' => 'status-confirmed'],
    'active'            => ['label' => 'Active (Processing)', 'class' => 'status-active'], // Added
    'processing'        => ['label' => 'Processing', 'class' => 'status-processing'],
    'shipped'           => ['label' => 'Shipped', 'class' => 'status-shipped'],
    'delivered'         => ['label' => 'Delivered', 'class' => 'status-delivered'],
    'cancelled'         => ['label' => 'Cancelled', 'class' => 'status-cancelled']
];
?>

<style>
/* Re-using styles from order-detail.php for consistency */
.luxury-container { max-width: 900px; margin: 0 auto; padding: 60px 20px; font-family: 'Inter', sans-serif; background: #fefefe; min-height: 100vh; }
.luxury-header { border-bottom: 1px solid #e8e8e8; padding-bottom: 40px; margin-bottom: 50px; }
.order-title { font-size: 28px; font-weight: 300; letter-spacing: -0.02em; color: var(--luxury-black); margin: 0 0 20px 0; }
.order-meta { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 20px; }
.meta-item { display: flex; flex-direction: column; min-width: 150px; }
.meta-label { font-size: 11px; font-weight: 500; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 8px; }
.meta-value { font-size: 16px; font-weight: 400; color: var(--luxury-black); }
.status-badge { display: inline-block; padding: 6px 16px; border-radius: 2px; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }

/* Styles matching order-history.php badge classes */
.status-pending { background: #fff3cd; color: #856404; }
.status-submitted { background: #cce5ff; color: #004085; } /* New: Using light blue for submitted */
.status-active, .status-confirmed { background: #d4edda; color: #155724; } /* Green for active/confirmed */
.status-processing { background: #d6d8d9; color: #383d41; }
.status-shipped { background: #b8daff; color: #004085; }
.status-delivered { background: #d4edda; color: #155724; }
.status-cancelled { background: #f8d7da; color: #721c24; }

.btn { padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; border: 1px solid transparent; }
.btn-primary { background: var(--primary-cyan); color: white; border-color: var(--primary-cyan); }
.btn-primary:hover { background: var(--primary-cyan-hover); border-color: var(--primary-cyan-hover); transform: translateY(-1px); }
</style>

<main class="container mx-auto py-10 px-4">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-3xl font-bold text-center mb-8" style="color: var(--luxury-black);">Track Your Order</h1>

        <form method="POST" action="track.php" class="border rounded-lg p-6 mb-8 bg-white shadow-sm">
            <p class="mb-4 text-luxury-gray">Enter your order ID and the phone number used at checkout.</p>
            <div class="mb-4">
                <label for="order_id" class="block text-sm font-medium text-gray-700 mb-2">Order ID</label>
                <input type="text" id="order_id" name="order_id" class="w-full border p-2 rounded-lg" placeholder="e.g., 123" required value="<?= htmlspecialchars($_POST['order_id'] ?? '') ?>">
            </div>
            <div class="mb-4">
                <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                <input type="tel" id="phone" name="phone" class="w-full border p-2 rounded-lg" placeholder="e.g., 08012345678" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-primary w-full">Track Order</button>
        </form>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded text-center">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($order): 
            $currentStatus = $statusConfig[$order['status']] ?? ['label' => ucfirst($order['status']), 'class' => 'status-default'];
            $orderDate = new DateTime($order['order_date']);
            $grandTotal = floatval($order['total_amount']) + floatval($order['delivery_fee']);
        ?>
            <div class="luxury-container" style="padding: 0; min-height: 0;">
                <header class="luxury-header">
                    <h1 class="order-title">Order #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></h1>
                    
                    <div class="order-meta">
                        <div class="meta-item">
                            <span class="meta-label">Order Date</span>
                            <span class="meta-value"><?= $orderDate->format('F j, Y') ?></span>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">Status</span>
                            <span class="status-badge <?= $currentStatus['class'] ?>">
                                <?= $currentStatus['label'] ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($order['shipping_address'])): ?>
                        <div class="meta-item">
                            <span class="meta-label">Delivery Address</span>
                            <span class="meta-value"><?= htmlspecialchars($order['shipping_address']) ?></span>
                        </div>
                        <?php endif; ?>

                        <div class="meta-item">
                            <span class="meta-label">Total</span>
                            <span class="meta-value"><?= formatCurrency($grandTotal) ?></span>
                        </div>
                    </div>
                </header>

                <div class="mt-8 p-6 border rounded-lg bg-gray-50">
                    <h3 class="text-lg font-semibold mb-4">Tracking History</h3>
                    <p class="text-gray-700">Detailed tracking history would be displayed here after you set up the `order_tracking` table (if needed).</p>
                    <div class="mt-4">
                        <span class="status-badge <?= $currentStatus['class'] ?>">
                            <?= $currentStatus['label'] ?>
                        </span>
                        <span class="text-sm text-gray-500 ml-2">— As of <?= date('F j, Y g:i A') ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include 'footer.php'; ?>