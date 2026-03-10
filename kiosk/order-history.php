<?php
include 'header.php';
include 'config.php';
include 'functions.php';

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    // Redirect to login if not logged in
    header("Location: auth.php");
    exit;
}

$success_message = '';
$error_message = '';

// Handle order actions (cancel, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Note: A CSRF token check here would be ideal for production.
    // For now, we rely on the user_id in the session.
    
    $order_id = intval($_POST['order_id'] ?? 0);
    $action = $_POST['action'];
    
    // Verify order belongs to user
    $stmt = $pdo->prepare("SELECT id, status FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        $error_message = "Order not found or access denied.";
    } else {
        switch ($action) {
            case 'cancel':
                // Allow cancellation if pending or payment_submitted
                if (in_array($order['status'], ['pending', 'payment_submitted'])) {
                    $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$order_id]);
                    $success_message = "Order #$order_id has been cancelled successfully.";
                } else {
                    $error_message = "This order can no longer be cancelled.";
                }
                break;
                
            case 'reorder':
                // Get order items and add to cart
                $stmt = $pdo->prepare("
                    SELECT product_id, quantity, selected_options 
                    FROM order_details 
                    WHERE order_id = ?
                ");
                $stmt->execute([$order_id]);
                $items = $stmt->fetchAll();
                
                $added_count = 0;
                $pdo->beginTransaction();
                try {
                    foreach ($items as $item) {
                        // Check if product still exists
                        $stmt_prod = $pdo->prepare("SELECT id, options, stock_quantity FROM products WHERE id = ?");
                        $stmt_prod->execute([$item['product_id']]);
                        $product = $stmt_prod->fetch();
                        
                        if ($product) {
                            // Prepare options for query
                            $options_json = $item['selected_options'];
                            
                            // Check if this exact variant (product_id + options) is already in the cart
                            $sql_find = "SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?";
                            $params_find = [$user_id, $item['product_id']];
                            
                            if ($options_json) {
                                $sql_find .= " AND selected_options = ?";
                                $params_find[] = $options_json;
                            } else {
                                $sql_find .= " AND selected_options IS NULL";
                            }

                            $stmt_cart = $pdo->prepare($sql_find);
                            $stmt_cart->execute($params_find);
                            $existing_cart_item = $stmt_cart->fetch();
                            
                            $new_quantity = $existing_cart_item ? $existing_cart_item['quantity'] + $item['quantity'] : $item['quantity'];
                            
                            // Check stock before adding
                            if ($new_quantity > $product['stock_quantity']) {
                                // Skip this item or add up to stock? For re-order, skipping is safer.
                                $error_message = "Some items could not be re-ordered due to insufficient stock.";
                                continue;
                            }

                            if ($existing_cart_item) {
                                $stmtUpdate = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                                $stmtUpdate->execute([$new_quantity, $existing_cart_item['id']]);
                            } else {
                                $stmtInsert = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity, selected_options) VALUES (?, ?, ?, ?)");
                                $stmtInsert->execute([$user_id, $item['product_id'], $item['quantity'], $options_json]);
                            }
                            $added_count++;
                        }
                    }
                    $pdo->commit();
                    
                    if ($added_count > 0) {
                        $success_message = "$added_count item types from order #$order_id have been added to your cart.";
                    } else if (empty($error_message)) {
                        $error_message = "No items could be added to cart. Products may no longer be available.";
                    }
                    
                    // Update cart count in session
                    $cart_stmt = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
                    $cart_stmt->execute([$user_id]);
                    $_SESSION['cart_count'] = $cart_stmt->fetchColumn() ?: 0;
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error_message = "An error occurred while reordering: " . $e->getMessage();
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
        case 'pending': 
            return 'bg-yellow-100 text-yellow-800';
        case 'payment_submitted': 
            return 'bg-blue-100 text-blue-800'; 
        case 'confirmed':
        case 'active': 
            return 'bg-green-100 text-green-800';
        case 'processing': 
            return 'bg-purple-100 text-purple-800';
        case 'shipped': 
            return 'bg-indigo-100 text-indigo-800';
        case 'delivered': 
            return 'bg-green-100 text-green-800';
        case 'cancelled': 
            return 'bg-red-100 text-red-800';
        default: 
            return 'bg-gray-100 text-gray-800';
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
/* ... [styles from original file] ... */
.orders-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 20px;
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
    border: 1px solid var(--luxury-border);
    border-radius: 6px;
    font-size: 14px;
    background: white;
}
.order-card {
    background: white;
    border: 1px solid var(--luxury-border);
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 20px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
}
.order-card:hover {
    border-color: var(--primary-color);
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}
.order-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
    flex-wrap: wrap;
    gap: 15px;
}
.order-id { font-size: 1.125rem; font-weight: 600; color: var(--luxury-black); }
.order-date { color: var(--luxury-gray); font-size: 14px; }
.status-badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
.order-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}
.detail-group { background: var(--luxury-light-gray); padding: 15px; border-radius: 8px; }
.detail-label { font-size: 12px; color: var(--luxury-gray); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
.detail-value { font-weight: 500; color: var(--luxury-black); }
.order-items { background: var(--luxury-light-gray); padding: 15px; border-radius: 8px; margin-bottom: 20px; }
.order-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.btn { 
    padding: 8px 16px; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer;
    transition: all 0.3s ease; border: none; text-decoration: none; display: inline-block; text-align: center;
}
.btn-primary { background: var(--primary-color); color: white; }
.btn-primary:hover { background: var(--primary-color-hover); }
.btn-secondary { background: var(--luxury-light-gray); color: #333; border: 1px solid var(--luxury-border); }
.btn-secondary:hover { background: #e9ecef; }
.btn-danger { background: #ef4444; color: white; }
.btn-danger:hover { background: #dc2626; }

/* Pagination styles from products.css for consistency */
.pagination { display: flex; justify-content: center; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin: 2rem 0; }
.pagination a, .pagination span { padding: 0.5rem 0.75rem; border: 1px solid var(--luxury-border); border-radius: 6px; color: var(--luxury-gray); text-decoration: none; font-weight: 600; min-width: 40px; text-align: center; transition: all 0.2s; }
.pagination a:hover { background: var(--luxury-light-gray); color: var(--luxury-black); }
.pagination .current { background: var(--primary-color); color: white; border-color: var(--primary-color); }
.empty-state { text-align: center; padding: 60px 20px; color: #666; }
.empty-state h3 { font-size: 24px; margin-bottom: 15px; color: #1a1a1a; }
</style>

<main class="orders-container">
    <div class="orders-header">
        <h1 class="text-3xl font-bold" style="color: var(--luxury-black);">My Orders</h1>
        
        <div class="filter-section">
            <form method="GET" class="flex gap-3 items-center">
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Orders</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending Payment</option>
                    <option value="payment_submitted" <?= $status_filter === 'payment_submitted' ? 'selected' : '' ?>>Payment Submitted</option>
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active/Confirmed</option>
                    <option value="processing" <?= $status_filter === 'processing' ? 'selected' : '' ?>>Processing</option>
                    <option value="shipped" <?= $status_filter === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                    <option value="delivered" <?= $status_filter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </form>
            
            <a href="products.php" class="btn btn-primary">Continue Shopping</a>
        </div>
    </div>
    
    <?php if ($error_message) echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>$error_message</div>"; ?>
    <?php if ($success_message) echo "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4'>$success_message</div>"; ?>


    <?php if (empty($orders)): ?>
        <div class="empty-state">
            <h3>No Orders Found</h3>
            <p>You haven't placed any orders yet<?= !empty($status_filter) ? " with status '" . htmlspecialchars($status_filter) . "'" : '' ?>.</p>
            <a href="products.php" class="btn btn-primary" style="margin-top: 20px;">Start Shopping</a>
        </div>
    <?php else: ?>
        <?php foreach ($orders as $order): 
            $grand_total = $order['total_amount'] + $order['delivery_fee'];
        ?>
            <div class="order-card">
                <div class="order-header">
                    <div>
                        <div class="order-id">Order #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></div>
                        <div class="order-date">
                            Placed on <?= date('M j, Y \a\t g:i A', strtotime($order['created_at'])) ?>
                        </div>
                    </div>
                    <span class="status-badge <?= getStatusBadgeClass($order['status']) ?>">
                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $order['status']))) ?>
                    </span>
                </div>

                <div class="order-details">
                    <div class="detail-group">
                        <div class="detail-label">Total Amount</div>
                        <div class="detail-value"><?= formatCurrency($grand_total) ?></div>
                        <div style="font-size: 12px; color: #666; margin-top: 5px;">
                            Products: <?= formatCurrency($order['total_amount']) ?><br>
                            Delivery: <?= formatCurrency($order['delivery_fee']) ?>
                        </div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-label">Payment Method</div>
                        <div class="detail-value"><?= getPaymentMethodDisplay($order['payment_method']) ?></div>
                        <div class="detail-label" style="margin-top: 10px;">Delivery Option</div>
                        <div class="detail-value"><?= htmlspecialchars($order['delivery_option'] ?? '') ?></div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-label">Shipping Address</div>
                        <div class="detail-value">
                            <?= htmlspecialchars($order['full_name'] ?? '') ?><br>
                            <?= htmlspecialchars($order['address_line1'] ?? '') ?><br>
                            <?= htmlspecialchars($order['city'] ?? '') ?>, <?= htmlspecialchars($order['state'] ?? '') ?>
                        </div>
                    </div>
                </div>

                <div class="order-items">
                    <div class="detail-label">Items (<?= $order['item_count'] ?>)</div>
                    <div class="detail-value">
                        <?= htmlspecialchars($order['items_summary'] ?? 'N/A') ?>
                    </div>
                </div>

                <div class="order-actions">
                    <a href="order-detail.php?id=<?= $order['id'] ?>" class="btn btn-secondary">View Details</a>
                    
                    <?php if ($order['status'] === 'pending'): ?>
                        <a href="confirm_payment.php?order_id=<?= $order['id'] ?>" class="btn btn-primary">Complete Payment</a>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to cancel this order?')">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <input type="hidden" name="action" value="cancel">
                            <button type="submit" class="btn btn-danger">Cancel Order</button>
                        </form>
                    <?php elseif ($order['status'] === 'payment_submitted'): ?>
                         <a href="confirm_payment.php?order_id=<?= $order['id'] ?>" class="btn btn-primary">Resend Payment Proof</a>
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

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php 
                $query_params = $_GET;
                if ($page > 1):
                    $query_params['page'] = $page - 1;
                ?>
                    <a href="?<?= http_build_query($query_params) ?>">&laquo; Previous</a>
                <?php endif; ?>
                
                <?php 
                // Determine pagination range
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);

                if ($start > 1) {
                    $query_params['page'] = 1;
                    echo '<a href="?' . http_build_query($query_params) . '">1</a>';
                    if ($start > 2) {
                        echo '<span class="px-3 py-2">...</span>';
                    }
                }

                for ($i = $start; $i <= $end; $i++):
                    $query_params['page'] = $i; 
                ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?<?= http_build_query($query_params) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end < $total_pages):
                    if ($end < $total_pages - 1) {
                        echo '<span class="px-3 py-2">...</span>';
                    }
                    $query_params['page'] = $total_pages;
                ?>
                    <a href="?<?= http_build_query($query_params) ?>"><?= $total_pages ?></a>
                <?php endif; ?>

                <?php if ($page < $total_pages): 
                    $query_params['page'] = $page + 1;
                ?>
                    <a href="?<?= http_build_query($query_params) ?>">Next &raquo;</a>
                <?php endif; ?>
            </div>
            
            <div style="text-align: center; margin-top: 20px; color: #666; font-size: 14px;">
                Showing <?= $offset + 1 ?>-<?= min($offset + $limit, $total_orders) ?> of <?= $total_orders ?> orders
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php include 'footer.php'; ?>