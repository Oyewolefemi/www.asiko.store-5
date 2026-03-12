<?php
// kiosk/Red/orders.php
include 'header.php';
include_once '../functions.php'; 

$is_super = ($_SESSION['admin_role'] ?? '') === 'superadmin';
$current_admin_id = $_SESSION['admin_id'];
$success = '';

// Handle Order Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = sanitize($_POST['status']);
    
    // In MVP, updating the base order status updates it for everyone. 
    // (A true multi-vendor system would split sub-orders, but this works perfectly for now).
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    if ($stmt->execute([$new_status, $order_id])) {
        $success = "Order #{$order_id} status updated to " . ucfirst($new_status);
    }
}

// 1. Fetch Orders
if ($is_super) {
    // Superadmin sees all orders
    $stmt = $pdo->query("SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone 
                         FROM orders o 
                         LEFT JOIN users u ON o.user_id = u.id 
                         ORDER BY o.created_at DESC");
} else {
    // Vendor only sees orders containing their products
    $stmt = $pdo->prepare("SELECT DISTINCT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone 
                           FROM orders o 
                           JOIN order_items oi ON o.id = oi.order_id 
                           JOIN products p ON oi.product_id = p.id 
                           LEFT JOIN users u ON o.user_id = u.id 
                           WHERE p.admin_id = ? 
                           ORDER BY o.created_at DESC");
    $stmt->execute([$current_admin_id]);
}
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getStatusBadge($status) {
    $classes = [
        'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
        'processing' => 'bg-blue-100 text-blue-800 border-blue-200',
        'shipped' => 'bg-purple-100 text-purple-800 border-purple-200',
        'delivered' => 'bg-green-100 text-green-800 border-green-200',
        'cancelled' => 'bg-red-100 text-red-800 border-red-200'
    ];
    $class = $classes[$status] ?? 'bg-gray-100 text-gray-800 border-gray-200';
    return "<span class='px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider border {$class}'>{$status}</span>";
}
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Order Management</h1>
        <p class="text-sm text-gray-500">
            <?= $is_super ? 'Monitor all transactions across the mall.' : 'View orders and required variants for your storefront.' ?>
        </p>
    </div>
</div>

<?php if ($success): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm font-bold flex items-center gap-2">
        <span class="material-symbols-outlined">check_circle</span> <?= $success ?>
    </div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <?php if (empty($orders)): ?>
        <div class="p-12 text-center text-gray-500">
            <span class="material-symbols-outlined text-5xl mb-3 opacity-50">inbox</span>
            <p class="text-lg font-bold">No orders found.</p>
            <p class="text-sm">When customers purchase your products, they will appear here.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500 border-b border-gray-200">
                    <tr>
                        <th class="p-4 font-bold">Order Details</th>
                        <th class="p-4 font-bold">Customer Info</th>
                        <th class="p-4 font-bold">Items Purchased (Variants)</th>
                        <th class="p-4 font-bold text-right">Vendor Revenue</th>
                        <th class="p-4 font-bold text-center">Status Action</th>
                    </tr>
                </thead>
                <tbody class="text-sm text-gray-700 divide-y divide-gray-100">
                    <?php foreach ($orders as $order): 
                        // Fetch only the items in this order that belong to the logged-in vendor (or all if Superadmin)
                        $items_sql = "SELECT oi.*, p.name as product_name, p.image_path, p.admin_id 
                                      FROM order_items oi 
                                      JOIN products p ON oi.product_id = p.id 
                                      WHERE oi.order_id = ?";
                        $params = [$order['id']];
                        if (!$is_super) {
                            $items_sql .= " AND p.admin_id = ?";
                            $params[] = $current_admin_id;
                        }
                        $stmt_items = $pdo->prepare($items_sql);
                        $stmt_items->execute($params);
                        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Calculate Vendor's specific cut of this order
                        $vendor_total = 0;
                        foreach($items as $i) { $vendor_total += ($i['price'] * $i['quantity']); }
                    ?>
                        <tr class="hover:bg-gray-50 transition items-start">
                            <td class="p-4 align-top">
                                <div class="font-black text-gray-900 text-base mb-1">#<?= htmlspecialchars($order['order_number'] ?? $order['id']) ?></div>
                                <div class="text-xs text-gray-500 font-mono mb-2"><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></div>
                                <?= getStatusBadge($order['status']) ?>
                            </td>
                            
                            <td class="p-4 align-top">
                                <div class="font-bold text-gray-800"><?= htmlspecialchars($order['customer_name'] ?? 'Guest') ?></div>
                                <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($order['customer_email']) ?></div>
                                <div class="text-xs text-gray-500 mt-0.5 font-mono"><?= htmlspecialchars($order['customer_phone'] ?? 'No Phone') ?></div>
                                
                                <?php if(!empty($order['shipping_address'])): ?>
                                    <div class="mt-3 text-[11px] bg-gray-100 p-2 rounded text-gray-600 leading-tight">
                                        <span class="font-bold block mb-0.5">Shipping Address:</span>
                                        <?= nl2br(htmlspecialchars($order['shipping_address'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            
                            <td class="p-4 align-top">
                                <div class="space-y-3">
                                    <?php foreach ($items as $item): 
                                        $img = !empty($item['image_path']) ? '../' . htmlspecialchars($item['image_path']) : '../kiosk/uploads/default_product.png';
                                        
                                        // DECODE THE VARIANT JSON!
                                        $options = json_decode($item['options'], true);
                                        $variant_text = '';
                                        if (is_array($options) && !empty($options)) {
                                            $variant_text = $options['Variant'] ?? implode(', ', $options);
                                        }
                                    ?>
                                        <div class="flex gap-3 bg-white border border-gray-100 p-2 rounded-lg shadow-sm">
                                            <img src="<?= $img ?>" class="w-12 h-12 object-cover rounded border border-gray-200" alt="Item">
                                            <div class="flex-1">
                                                <div class="font-bold text-gray-800 leading-tight"><?= htmlspecialchars($item['product_name']) ?></div>
                                                <div class="flex justify-between items-center mt-1">
                                                    <span class="text-xs font-bold text-gray-500"><?= $item['quantity'] ?>x ₦<?= number_format($item['price']) ?></span>
                                                </div>
                                                
                                                <?php if($variant_text): ?>
                                                    <div class="mt-1.5 inline-block bg-blue-50 border border-blue-200 text-blue-700 text-[10px] font-bold px-2 py-0.5 rounded shadow-sm">
                                                        SKU: <?= htmlspecialchars($variant_text) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            
                            <td class="p-4 align-top text-right">
                                <div class="font-black text-lg text-gray-900">₦<?= number_format($vendor_total, 2) ?></div>
                                <?php if($is_super): ?>
                                    <div class="text-[10px] text-gray-400 mt-1 uppercase font-bold tracking-wider">Total Order: ₦<?= number_format($order['total_amount'], 2) ?></div>
                                <?php endif; ?>
                            </td>
                            
                            <td class="p-4 align-top text-center">
                                <form method="POST" class="inline-flex flex-col gap-2 w-full max-w-[140px] mx-auto">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <select name="status" class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white font-medium">
                                        <option value="pending" <?= $order['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="processing" <?= $order['status'] == 'processing' ? 'selected' : '' ?>>Processing</option>
                                        <option value="shipped" <?= $order['status'] == 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                        <option value="delivered" <?= $order['status'] == 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                        <option value="cancelled" <?= $order['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                    <button type="submit" name="update_status" class="w-full bg-gray-800 hover:bg-black text-white text-xs font-bold py-2 rounded transition shadow-sm">
                                        Update Status
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php echo "</main></div></body></html>"; ?>