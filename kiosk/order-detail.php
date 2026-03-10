<?php 
include 'header.php'; 
include 'config.php'; 
include 'functions.php'; // This file already contains getProductImage, so we remove the duplicate.

// Enhanced session validation
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    header("Location: auth.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = intval($_GET['id'] ?? 0);

if ($order_id <= 0) {
    printError("Invalid order ID.");
    include 'footer.php';
    exit;
}

try {
    // Fetch the order header
    $stmt = $pdo->prepare("
        SELECT 
            o.id,
            o.order_date,
            o.total_amount,
            o.delivery_fee,
            o.status,
            o.shipping_address,
            o.created_at,
            o.payment_method,
            o.delivery_option
        FROM orders o 
        WHERE o.id = ? AND o.user_id = ?
    ");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        throw new Exception("Order not found or access denied.");
    }

    // Fetch the line items
    $stmtItems = $pdo->prepare("
        SELECT 
            od.product_id,
            od.quantity,
            od.price_at_purchase,
            od.discount_applied,
            od.selected_options,
            p.name,
            p.description,
            p.image_path,
            p.category
        FROM order_details od 
        JOIN products p ON p.id = od.product_id 
        WHERE od.order_id = ?
        ORDER BY p.name ASC
    ");
    $stmtItems->execute([$order_id]);
    $items = $stmtItems->fetchAll();

} catch (Exception $e) {
    error_log("Order details error for user {$user_id}, order {$order_id}: " . $e->getMessage());
    printError("We're unable to display your order details at this time. Please contact support if this issue persists.");
    include 'footer.php';
    exit;
}

// *** FIXED: Updated status configuration to be consistent with all app states ***
$statusConfig = [
    'pending' => ['label' => 'Pending Payment', 'class' => 'status-pending'],
    'payment_submitted' => ['label' => 'Payment Submitted', 'class' => 'status-processing'], // Using 'processing' style for this
    'confirmed' => ['label' => 'Confirmed', 'class' => 'status-confirmed'],
    'approved' => ['label' => 'Approved', 'class' => 'status-confirmed'], // 'approved' from admin maps to 'confirmed'
    'active' => ['label' => 'Active', 'class' => 'status-confirmed'], // 'active' from admin maps to 'confirmed'
    'processing' => ['label' => 'Processing', 'class' => 'status-processing'],
    'shipped' => ['label' => 'Shipped', 'class' => 'status-shipped'],
    'delivered' => ['label' => 'Delivered', 'class' => 'status-delivered'],
    'cancelled' => ['label' => 'Cancelled', 'class' => 'status-cancelled']
];


$currentStatus = $statusConfig[$order['status']] ?? ['label' => ucfirst($order['status']), 'class' => 'status-default'];
$grandTotal = floatval($order['total_amount']) + floatval($order['delivery_fee']);
$orderDate = new DateTime($order['order_date']);

// *** REMOVED: Redundant getProductImage function is now loaded from functions.php ***
?>

<style>
/* Luxury minimalist styles */
:root {
    /* These variables are now correctly inherited from header.php, but kept for clarity */
    --primary-cyan: <?= htmlspecialchars(EnvLoader::get('THEME_COLOR', '#dbcb36')) ?>;
    --primary-cyan-hover: <?= darken_color(htmlspecialchars(EnvLoader::get('THEME_COLOR', '#dbcb36')), 10) ?>;
    --luxury-black: <?= htmlspecialchars(EnvLoader::get('TEXT_COLOR', '#1a1a1a')) ?>;
    --luxury-gray: #666666;
    --luxury-light-gray: #f8f8f8;
    --luxury-border: #e8e8e8;
}
.luxury-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 60px 20px;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: #fefefe;
    min-height: 100vh;
}

.luxury-header {
    border-bottom: 1px solid var(--luxury-border);
    padding-bottom: 40px;
    margin-bottom: 50px;
}

.order-title {
    font-size: 28px;
    font-weight: 300;
    letter-spacing: -0.02em;
    color: var(--luxury-black);
    margin: 0 0 20px 0;
}

.order-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 30px;
    margin-top: 30px;
}

.meta-item {
    display: flex;
    flex-direction: column;
}

.meta-label {
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #888;
    margin-bottom: 8px;
}

.meta-value {
    font-size: 16px;
    font-weight: 400;
    color: var(--luxury-black);
}

.status-badge {
    display: inline-block;
    padding: 6px 16px;
    border-radius: 2px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* --- FIXED: Added all status classes for consistency --- */
.status-pending { background: #fff3cd; color: #856404; } /* yellow */
.status-payment_submitted { background: #d1ecf1; color: #0c5460; } /* blue */
.status-confirmed { background: #d1ecf1; color: #0c5460; } /* blue */
.status-processing { background: #d6d8d9; color: #383d41; } /* gray */
.status-shipped { background: #cce5ff; color: #004085; } /* lighter blue */
.status-delivered { background: #d4edda; color: #155724; } /* green */
.status-cancelled { background: #f8d7da; color: #721c24; } /* red */
.status-approved { background: #d4edda; color: #155724; } /* green */
.status-default { background: #e2e3e5; color: #383d41; }


.items-section {
    margin: 60px 0;
}

.section-title {
    font-size: 18px;
    font-weight: 400;
    letter-spacing: -0.01em;
    color: #1a1a1a;
    margin-bottom: 40px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f0f0f0;
}

.item-card {
    display: flex;
    align-items: flex-start;
    padding: 30px 0;
    border-bottom: 1px solid #f5f5f5;
    gap: 25px;
}

.item-card:last-child {
    border-bottom: none;
}

.item-image {
    width: 80px;
    height: 80px;
    object-fit: cover;
    background: #f8f8f8;
    border: 1px solid #eee;
}

.item-image-placeholder {
    width: 80px;
    height: 80px;
    background: #f8f8f8;
    border: 1px solid #eee;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: #aaa;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.item-details {
    flex: 1;
}

.item-name {
    font-size: 16px;
    font-weight: 400;
    color: #1a1a1a;
    margin: 0 0 8px 0;
}

.item-meta {
    font-size: 14px;
    color: #666;
    margin: 4px 0;
}

.item-options {
    font-size: 13px;
    color: #888;
    font-style: italic;
}

.item-price {
    text-align: right;
    min-width: 120px;
}

.price-main {
    font-size: 16px;
    font-weight: 400;
    color: #1a1a1a;
}

.price-sub {
    font-size: 13px;
    color: #888;
    margin-top: 4px;
}

.order-summary {
    background: #fafafa;
    padding: 40px;
    margin-top: 60px;
    border: 1px solid #eee;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    font-size: 15px;
}

.summary-row:not(:last-child) {
    border-bottom: 1px solid #eee;
}

.summary-label {
    color: #666;
    font-weight: 400;
}

.summary-value {
    color: #1a1a1a;
    font-weight: 400;
}

.summary-total {
    font-size: 20px;
    font-weight: 500;
    padding-top: 20px;
    margin-top: 20px;
    border-top: 2px solid #ddd;
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
    color: #888;
}

@media (max-width: 768px) {
    .luxury-container {
        padding: 40px 15px;
    }
    
    .order-meta {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .item-card {
        flex-direction: column;
        gap: 15px;
    }
    
    .item-price {
        text-align: left;
        min-width: auto;
    }
    
    .order-summary {
        padding: 25px;
    }
}
</style>

<main class="luxury-container">
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
                    <?= htmlspecialchars(str_replace('_', ' ', $currentStatus['label'])) ?>
                </span>
            </div>
            
            <?php if (!empty($order['shipping_address'])): ?>
            <div class="meta-item">
                <span class="meta-label">Delivery Address</span>
                <span class="meta-value"><?= htmlspecialchars($order['shipping_address']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </header>

    <section class="items-section">
        <h2 class="section-title">Order Items</h2>
        
        <?php if (empty($items)): ?>
            <div class="empty-state">
                <p>No items found for this order.</p>
            </div>
        <?php else: ?>
            <div class="items-list">
                <?php 
                $subtotal = 0;
                foreach ($items as $item): 
                    $subtotal += floatval($item['price_at_purchase']) * intval($item['quantity']);
                    $options = json_decode($item['selected_options'], true);
                    $product_image = getProductImage($item['image_path']); // This will now use the global function
                ?>
                    <article class="item-card">
                        <?php if ($product_image && $product_image !== 'https://placehold.co/100x100/f8f9fa/ccc?text=No+Image'): ?>
                            <img src="<?= $product_image ?>" 
                                 alt="<?= htmlspecialchars($item['name']) ?>" 
                                 class="item-image">
                        <?php else: ?>
                            <div class="item-image-placeholder">No Image</div>
                        <?php endif; ?>
                        
                        <div class="item-details">
                            <h3 class="item-name"><?= htmlspecialchars($item['name']) ?></h3>
                            <?php if (!empty($item['category'])): ?>
                                <p class="item-meta">Category: <?= htmlspecialchars($item['category']) ?></p>
                            <?php endif; ?>
                            
                            <?php if (is_array($options) && !empty($options)): ?>
                                <p class="item-options">
                                    <?php 
                                    $option_strings = [];
                                    foreach ($options as $key => $value) {
                                        $option_strings[] = "<strong>" . htmlspecialchars($key) . ":</strong> " . htmlspecialchars($value);
                                    }
                                    echo implode(', ', $option_strings);
                                    ?>
                                </p>
                            <?php endif; ?>

                            <p class="item-meta">Quantity: <?= intval($item['quantity']) ?></p>
                        </div>
                        
                        <div class="item-price">
                            <div class="price-main">₦<?= number_format($item['price_at_purchase'] * $item['quantity'], 2) ?></div>
                            <div class="price-sub">₦<?= number_format($item['price_at_purchase'], 2) ?> each</div>
                            <?php if ($item['discount_applied'] > 0): ?>
                                <div class="price-sub text-green-600">Saved ₦<?= number_format($item['discount_applied'] * $item['quantity'], 2) ?></div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="order-summary">
        <div class="summary-row">
            <span class="summary-label">Subtotal</span>
            <span class="summary-value">₦<?= number_format($order['total_amount'], 2) ?></span>
        </div>
        
        <div class="summary-row">
            <span class="summary-label">Delivery Fee</span>
            <span class="summary-value">₦<?= number_format($order['delivery_fee'], 2) ?></span>
        </div>
        
        <div class="summary-row summary-total">
            <span class="summary-label">Total</span>
            <span class="summary-value">₦<?= number_format($grandTotal, 2) ?></span>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>