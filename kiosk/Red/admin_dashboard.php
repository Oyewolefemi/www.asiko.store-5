<?php
// kiosk/Red/admin_dashboard.php
include 'header.php';

$is_super = ($_SESSION['admin_role'] ?? '') === 'superadmin';
$current_admin_id = $_SESSION['admin_id'];

// THE FIX: Safe fallback for the username using the null coalescing operator (??)
$admin_name = $_SESSION['admin_username'] ?? 'Store Manager';

// Fetch basic stats dynamically based on role
$stats = ['products' => 0, 'orders' => 0, 'revenue' => 0, 'low_stock' => 0];

try {
    if ($is_super) {
        // Super Admin sees EVERYTHING
        $stats['products'] = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
        $stats['orders'] = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
        $stats['revenue'] = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status != 'cancelled'")->fetchColumn() ?: 0;
        $stats['low_stock'] = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_quantity < 5")->fetchColumn();
    } else {
        // Vendor only sees their own stats
        $stmt_prod = $pdo->prepare("SELECT COUNT(*) FROM products WHERE admin_id = ?");
        $stmt_prod->execute([$current_admin_id]);
        $stats['products'] = $stmt_prod->fetchColumn();

        $stmt_orders = $pdo->prepare("SELECT COUNT(DISTINCT o.id) FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id WHERE p.admin_id = ?");
        $stmt_orders->execute([$current_admin_id]);
        $stats['orders'] = $stmt_orders->fetchColumn();

        $stmt_rev = $pdo->prepare("SELECT SUM(oi.price * oi.quantity) FROM order_items oi JOIN products p ON oi.product_id = p.id JOIN orders o ON oi.order_id = o.id WHERE p.admin_id = ? AND o.status != 'cancelled'");
        $stmt_rev->execute([$current_admin_id]);
        $stats['revenue'] = $stmt_rev->fetchColumn() ?: 0;
        
        $stmt_stock = $pdo->prepare("SELECT COUNT(*) FROM products WHERE admin_id = ? AND stock_quantity < 5");
        $stmt_stock->execute([$current_admin_id]);
        $stats['low_stock'] = $stmt_stock->fetchColumn();
    }
} catch (Exception $e) {
    error_log("Dashboard Stats Error: " . $e->getMessage());
}
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800 tracking-tight">
        Welcome back, <?php echo htmlspecialchars($admin_name, ENT_QUOTES, 'UTF-8'); ?>!
    </h1>
    <p class="text-gray-500 mt-1">Here is what is happening in your store today.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
    
    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 flex items-center gap-4">
        <div class="w-12 h-12 bg-green-100 text-green-600 rounded-full flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined">payments</span>
        </div>
        <div>
            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Total Revenue</p>
            <p class="text-2xl font-black text-gray-800">₦<?php echo number_format($stats['revenue'], 2); ?></p>
        </div>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 flex items-center gap-4">
        <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined">shopping_bag</span>
        </div>
        <div>
            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Total Orders</p>
            <p class="text-2xl font-black text-gray-800"><?php echo number_format($stats['orders']); ?></p>
        </div>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 flex items-center gap-4">
        <div class="w-12 h-12 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined">inventory_2</span>
        </div>
        <div>
            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Active Products</p>
            <p class="text-2xl font-black text-gray-800"><?php echo number_format($stats['products']); ?></p>
        </div>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 flex items-center gap-4">
        <div class="w-12 h-12 <?php echo $stats['low_stock'] > 0 ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-400'; ?> rounded-full flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined">warning</span>
        </div>
        <div>
            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Low Stock Items</p>
            <p class="text-2xl font-black text-gray-800"><?php echo number_format($stats['low_stock']); ?></p>
        </div>
    </div>

</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
        <h3 class="font-bold text-gray-800 mb-4 border-b pb-2">Quick Actions</h3>
        <div class="grid grid-cols-2 gap-4">
            <a href="admin_products.php" class="bg-gray-50 hover:bg-gray-100 border border-gray-200 p-4 rounded-lg flex flex-col items-center justify-center text-center transition">
                <span class="material-symbols-outlined text-blue-500 mb-2">add_box</span>
                <span class="text-sm font-bold text-gray-700">Add Product</span>
            </a>
            <a href="orders.php" class="bg-gray-50 hover:bg-gray-100 border border-gray-200 p-4 rounded-lg flex flex-col items-center justify-center text-center transition">
                <span class="material-symbols-outlined text-green-500 mb-2">local_shipping</span>
                <span class="text-sm font-bold text-gray-700">Fulfill Orders</span>
            </a>
            <a href="admin_profile.php" class="bg-gray-50 hover:bg-gray-100 border border-gray-200 p-4 rounded-lg flex flex-col items-center justify-center text-center transition">
                <span class="material-symbols-outlined text-purple-500 mb-2">storefront</span>
                <span class="text-sm font-bold text-gray-700">Store Profile</span>
            </a>
            <a href="../store.php?vendor_id=<?php echo $current_admin_id; ?>" target="_blank" class="bg-gray-50 hover:bg-gray-100 border border-gray-200 p-4 rounded-lg flex flex-col items-center justify-center text-center transition">
                <span class="material-symbols-outlined text-gray-500 mb-2">visibility</span>
                <span class="text-sm font-bold text-gray-700">View Live Store</span>
            </a>
        </div>
    </div>
</div>

<?php echo "</main></div></body></html>"; ?>