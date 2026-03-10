<?php
// kiosk/Red/admin_dashboard.php
include 'header.php'; 

// Check Role
$is_super = ($_SESSION['admin_role'] ?? '') === 'superadmin';
$admin_id = $_SESSION['admin_id'];

// --- STATS CALCULATION ---
try {
    if ($is_super) {
        // GLOBAL STATS (Super Admin)
        $stats_stmt = $pdo->query("
            SELECT 
                (SELECT COUNT(*) FROM users) as total_users,
                (SELECT COUNT(*) FROM products) as total_products,
                (SELECT COUNT(*) FROM orders) as total_orders,
                (SELECT SUM(total_amount + delivery_fee) FROM orders WHERE status = 'active') as total_revenue
        ");
        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // VENDOR SPECIFIC STATS
        // Revenue: Sum of (price_at_purchase * qty) from order_details linked to this vendor's products
        // Orders: Count of distinct orders that contain at least one of this vendor's products
        $sql = "
            SELECT 
                (SELECT COUNT(*) FROM products WHERE admin_id = ?) as total_products,
                
                (SELECT COUNT(DISTINCT od.order_id) 
                 FROM order_details od 
                 JOIN products p ON od.product_id = p.id 
                 WHERE p.admin_id = ?) as total_orders,
                 
                (SELECT COALESCE(SUM(od.price_at_purchase * od.quantity), 0) 
                 FROM order_details od 
                 JOIN products p ON od.product_id = p.id 
                 JOIN orders o ON od.order_id = o.id
                 WHERE p.admin_id = ? AND o.status = 'active') as total_revenue
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$admin_id, $admin_id, $admin_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_users'] = 'N/A'; // Vendors don't see total users
    }
} catch (Exception $e) {
    $stats = ['total_products' => 0, 'total_orders' => 0, 'total_revenue' => 0, 'total_users' => 0];
}

// Fetch Activities
try {
    $act_sql = "SELECT * FROM admin_activities WHERE admin_id = ? ORDER BY created_at DESC LIMIT 5";
    $act_stmt = $pdo->prepare($act_sql);
    $act_stmt->execute([$admin_id]);
    $recent_activities = $act_stmt->fetchAll();
} catch (Exception $e) { $recent_activities = []; }
?>

    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">
                <?= $is_super ? 'Mall Overview' : 'Vendor Dashboard' ?>
            </h1>
            <p class="text-gray-500">
                Welcome, <span class="font-bold text-blue-600"><?= htmlspecialchars($_SESSION['admin_username']) ?></span>
            </p>
        </div>
        <?php if(!$is_super): ?>
            <span class="bg-blue-100 text-blue-700 px-4 py-2 rounded-full text-sm font-bold">
                <?= htmlspecialchars($_SESSION['store_name'] ?? 'My Store') ?>
            </span>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow border-l-4 border-green-500">
            <h3 class="text-sm font-medium text-gray-500"><?= $is_super ? 'Total Mall Revenue' : 'My Sales' ?></h3>
            <p class="text-3xl font-bold text-green-600">₦<?= number_format($stats['total_revenue'] ?? 0, 2) ?></p>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow border-l-4 border-blue-500">
            <h3 class="text-sm font-medium text-gray-500"><?= $is_super ? 'Total Orders' : 'My Orders' ?></h3>
            <p class="text-3xl font-bold text-gray-900"><?= $stats['total_orders'] ?? 0 ?></p>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow border-l-4 border-yellow-500">
            <h3 class="text-sm font-medium text-gray-500">Active Products</h3>
            <p class="text-3xl font-bold text-gray-900"><?= $stats['total_products'] ?? 0 ?></p>
        </div>
        
        <?php if($is_super): ?>
        <div class="bg-white p-6 rounded-lg shadow border-l-4 border-indigo-500">
            <h3 class="text-sm font-medium text-gray-500">Registered Customers</h3>
            <p class="text-3xl font-bold text-gray-900"><?= $stats['total_users'] ?? 0 ?></p>
        </div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-bold text-gray-800">My Recent Activity</h3>
        </div>
        <table class="w-full text-left text-sm text-gray-600">
            <tbody class="divide-y divide-gray-100">
                <?php if (count($recent_activities) > 0): ?>
                    <?php foreach ($recent_activities as $activity): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 font-medium text-gray-800"><?= htmlspecialchars($activity['action']) ?></td>
                        <td class="px-6 py-3"><?= htmlspecialchars($activity['details']) ?></td>
                        <td class="px-6 py-3 text-right text-gray-400 text-xs"><?= date('M j, H:i', strtotime($activity['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="px-6 py-4 text-center text-gray-400">No recent activities found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php echo "</main></div></body></html>"; ?>