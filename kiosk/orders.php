<?php
include 'header.php';
include 'config.php';
include 'functions.php';

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    printError("You must be logged in to view orders.");
    include 'footer.php';
    exit;
}

// Handle order actions (cancel, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $order_id = intval($_POST['order_id'] ?? 0);
    $action = $_POST['action'];
    
    // Verify order belongs to user
    $stmt = $pdo->prepare("SELECT id, status FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        printError("Order not found or access denied.");
    } else {
        switch ($action) {
            case 'cancel':
                if ($order['status'] === 'pending') {
                    $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$order_id]);
                    printSuccess("Order #$order_id has been cancelled successfully.");
                } else {
                    printError("Only pending orders can be cancelled.");
                }
                break;
                
            case 'reorder':
                // Get order items and add to cart
                $stmt = $pdo->prepare("
                    SELECT product_id, quantity 
                    FROM order_details 
                    WHERE order_id = ?
                ");
                $stmt->execute([$order_id]);
                $items = $stmt->fetchAll();
                
                $added_count = 0;
                foreach ($items as $item) {
                    // Check if product still exists
                    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
                    $stmt->execute([$item['product_id']]);
                    if ($stmt->fetch()) {
                        // Add to cart or update quantity
                        $stmt = $pdo->prepare("
                            INSERT INTO cart (user_id, product_id, quantity, added_at)
                            VALUES (?, ?, ?, NOW())
                            ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
                        ");
                        $stmt->execute([$user_id, $item['product_id'], $item['quantity']]);
                        $added_count++;
                    }
                }
                
                if ($added_count > 0) {
                    printSuccess("$added_count items from order #$order_id have been added to your cart.");
                } else {
                    printError("No items could be added to cart. Products may no longer be available.");
                }
                break;
        }
    }
}

// Pagination setup
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Get order status filter
$status_filter = $_GET['status'] ?? '';
$where_clause = "WHERE o.user_id = ?";
$params = [$user_id];

if (!empty($status_filter)) {
    $where_clause .= " AND o.status = ?";
    $params[] = $status_filter;
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) FROM orders o $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_orders = $stmt->fetchColumn();
$total_pages = ceil($total_orders / $limit);

// Fetch orders with address and order details
$sql = "
    SELECT 
        o.id,
        o.order_date,
        o.total_amount,
        o.delivery_fee,
        o.status,
        o.payment_method,
        o.delivery_option,
        o.created_at,
        a.full_name,
        a.address_line1,
        a.city,
        a.state,
        COUNT(od.id) as item_count,
        GROUP_CONCAT(
            CONCAT(p.name, ' (', od.quantity, 'x)')
            ORDER BY p.name
            SEPARATOR ', '
        ) as items_summary
    FROM orders o
    LEFT JOIN addresses a ON o.address_id = a.id
    LEFT JOIN order_details od ON o.id = od.order_id
    LEFT JOIN products p ON od.product_id = p.id
    $where_clause
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Helper function to get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending': return 'bg-yellow-100 text-yellow-800';
        case 'confirmed': return 'bg-blue-100 text-blue-800';
        case 'processing': return 'bg-purple-100 text-purple-800';
        case 'shipped': return 'bg-indigo-100 text-indigo-800';
        case 'delivered': return 'bg-green-100 text-green-800';
        case 'cancelled': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

// Helper function to get payment method display
function getPaymentMethodDisplay($method) {
    switch ($method) {
        case 'manual': return 'Bank Transfer';
        case 'card': return 'Credit/Debit Card';
        case 'bank_transfer': return 'Bank Transfer';
        case 'paypal': return 'PayPal';
        default: return ucfirst($method);
    }
}
?>

<style>
.orders-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 20px;
    font-family: 'Inter', sans-serif;
}

.orders-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 20px;
}

.filter-section {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    background: white;
}

.order-card {
    background: white;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 20px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
}

.order-card:hover {
    border-color: #5ce1e6;
    box-shadow: 0 4px 20px rgba(92, 225, 230, 0.1);
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
    flex-wrap: wrap;
    gap: 15px;
}

.order-id {
    font-size: 18px;
    font-weight: 600;
    color: #1a1a1a;
}

.order-date {
    color: #666;
    font-size: 14px;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.order-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.detail-group {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
}

.detail-label {
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
}

.detail-value {
    font-weight: 500;
    color: #1a1a1a;
}

.order-items {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.order-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-primary {
    background: #5ce1e6;
    color: white;
}

.btn-primary:hover {
    background: #4dd4d9;
}

.btn-secondary {
    background: #f8f8f8;
    color: #333;
    border: 1px solid #ddd;
}

.btn-secondary:hover {
    background: #f0f0f0;
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin-top: 40px;
}

.pagination a, .pagination span {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    text-decoration: none;
    color: #333;
    transition: all 0.3s ease;
}

.pagination a:hover {
    background: #5ce1e6;
    color: white;
    border-color: #5ce1e6;
}

.pagination .current {
    background: #5ce1e6;
    color: white;
    border-color: #5ce1e6;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.empty-state h3 {
    font-size: 24px;
    margin-bottom: 15px;
    color: #1a1a1a;
}

@media (max-width: 768px) {
    .orders-container {
        padding: 20px 15px;
    }
    
    .orders-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .order-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .order-details {
        grid-template-columns: 1fr;
    }
    
    .order-actions {
        justify-content: center;
    }
}
</style>

<main class="orders-container">
    <div class="orders-header">
        <h1 class="text-3xl font-bold" style="color: #1a1a1a;">My Orders</h1>
        
        <div class="filter-section">
            <form method="GET" class="flex gap-3 items-center">
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Orders</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="processing" <?= $status_filter === 'processing' ? 'selected' : '' ?>>Processing</option>
                    <option value="shipped" <?= $status_filter === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                    <option value="delivered" <?= $status_filter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </form>
            
            <a href="products.php" class="btn btn-primary">Continue Shopping</a>
        </div>
    </div>

    <?php if (empty($orders)): ?>
        <div class="empty-state">
            <h3>No Orders Found</h3>
            <p>You haven't placed any orders yet<?= !empty($status_filter) ? " with status '$status_filter'" : '' ?>.</p>
            <a href="products.php" class="btn btn-primary" style="margin-top: 20px;">Start Shopping</a>
        </div>
    <?php else: ?>
        <?php foreach ($orders as $order): 
            $grand_total = $order['total_amount'] + $order['delivery_fee'];
        ?>
            <div class="order-card">
                <div class="order-header">
                    <div>
                        <div class="order-id">Order #<?= $order['id'] ?></div>
                        <div class="order-date">
                            Placed on <?= date('M j, Y \a\t g:i A', strtotime($order['created_at'])) ?>
                        </div>
                    </div>
                    <span class="status-badge <?= getStatusBadgeClass($order['status']) ?>">
                        <?= ucfirst($order['status']) ?>
                    </span>
                </div>

                <div class="order-details">
                    <div class="detail-group">
                        <div class="detail-label">Total Amount</div>
                        <div class="detail-value">₦<?= number_format($grand_total, 2) ?></div>
                        <div style="font-size: 12px; color: #666; margin-top: 5px;">
                            Products: ₦<?= number_format($order['total_amount'], 2) ?><br>
                            Delivery: ₦<?= number_format($order['delivery_fee'], 2) ?>
                        </div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-label">Payment Method</div>
                        <div class="detail-value"><?= getPaymentMethodDisplay($order['payment_method']) ?></div>
                        <div class="detail-label" style="margin-top: 10px;">Delivery Option</div>
                        <div class="detail-value"><?= htmlspecialchars($order['delivery_option']) ?></div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-label">Shipping Address</div>
                        <div class="detail-value">
                            <?= htmlspecialchars($order['full_name']) ?><br>
                            <?= htmlspecialchars($order['address_line1']) ?><br>
                            <?= htmlspecialchars($order['city']) ?>, <?= htmlspecialchars($order['state']) ?>
                        </div>
                    </div>
                </div>

                <div class="order-items">
                    <div class="detail-label">Items (<?= $order['item_count'] ?>)</div>
                    <div class="detail-value">
                        <?= htmlspecialchars($order['items_summary']) ?>
                    </div>
                </div>

                <div class="order-actions">
                <a href="order-detail.php?id=<?= $order['id'] ?>" class="btn btn-secondary">View Details</a>                    
                    <?php if ($order['status'] === 'pending'): ?>
                        <a href="confirm_payment.php?order_id=<?= $order['id'] ?>" class="btn btn-primary">Confirm Payment</a>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to cancel this order?')">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <input type="hidden" name="action" value="cancel">
                            <button type="submit" class="btn btn-danger">Cancel Order</button>
                        </form>
                    <?php endif; ?>
                    
                    <?php if (in_array($order['status'], ['delivered', 'cancelled'])): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <input type="hidden" name="action" value="reorder">
                            <button type="submit" class="btn btn-primary">Reorder</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?>">&laquo; Previous</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?>">Next &raquo;</a>
                <?php endif; ?>
            </div>
            
            <div style="text-align: center; margin-top: 20px; color: #666; font-size: 14px;">
                Showing <?= $offset + 1 ?>-<?= min($offset + $limit, $total_orders) ?> of <?= $total_orders ?> orders
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php include 'footer.php'; ?>