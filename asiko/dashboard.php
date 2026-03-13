<?php
/**
 * File: dashboard.php
 * Status: LIVE DATA IMPLEMENTED
 * Logic: Fetches all dashboard metrics from the database using complex aggregate and recent queries.
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';
require_once 'functions.php';

// -------------------- Auth & Admin Gate --------------------
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Ensure current user is an admin
try {
    $adminCheck = $pdo->prepare('SELECT id, admin_level FROM admins WHERE user_id = ?');
    $adminCheck->execute([$_SESSION['user_id']]);
    $adminRow = $adminCheck->fetch(PDO::FETCH_ASSOC);
    if (!$adminRow) {
        header('Location: login.php');
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Admin check failed.';
    exit;
}

// -------------------- Settings & Dynamic Terms --------------------
$businessName = getSystemSetting('business_name', 'Dashboard');
$currencySymbol = getSystemSetting('currency_symbol', '₦');
$lowStockThreshold = (int) getSystemSetting('low_stock_threshold', 5);

$mainTitle = getSectorTerm('main_title', 'Dashboard');
$inventoryLinkTitle = getSectorTerm('inventory_link', 'Products');
$grossProfitLabel = getSectorTerm('gross_profit_label', 'Gross Profit');

// -------------------- Data Initialization --------------------
// Initialize all variables to zero/empty to prevent errors if DB queries fail
$stats = [
    'totalOrders' => 0, 'approvedOrders' => 0, 'pendingOrders' => 0, 
    'totalRevenue' => 0.0, 'monthlyRevenue' => 0.0, 'totalProductsSold' => 0,
    'totalProducts' => 0, 'lowStock' => 0, 'totalSuppliers' => 0, 
    'totalIncome' => 0.0, 'totalExpense' => 0.0, 'grossProfit' => 0.0
];
$recentProducts = [];
$recentOrders = [];
$lowStockProducts = [];
$orderTotalColumn = 'grand_total'; // Column name for order total

// -------------------- LIVE DATA QUERIES --------------------
try {
    // Orders aggregate & Revenue Calculation
    $q = $pdo->query(
        "SELECT 
            COUNT(*) AS total_orders,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) AS approved_orders,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
            SUM(CASE WHEN status = 'confirmed' THEN COALESCE($orderTotalColumn,0) ELSE 0 END) AS total_revenue,
            SUM(CASE WHEN status = 'confirmed' 
                     AND MONTH(order_date) = MONTH(CURRENT_DATE()) 
                     AND YEAR(order_date) = YEAR(CURRENT_DATE())
                     THEN COALESCE($orderTotalColumn,0) ELSE 0 END) AS monthly_revenue
          FROM orders"
    );
    $row = $q->fetch(PDO::FETCH_ASSOC) ?: [];
    
    $stats['totalOrders']    = (int)($row['total_orders'] ?? 0);
    $stats['approvedOrders'] = (int)($row['approved_orders'] ?? 0);
    $stats['pendingOrders']  = (int)($row['pending_orders'] ?? 0);
    $stats['totalRevenue']   = (float)($row['total_revenue'] ?? 0);
    $stats['monthlyRevenue'] = (float)($row['monthly_revenue'] ?? 0);

    // Products sold (only confirmed orders)
    $qSold = $pdo->query(
        "SELECT COALESCE(SUM(oi.quantity),0) AS total_sold
           FROM order_items oi
           JOIN orders o ON o.id = oi.order_id
          WHERE o.status = 'confirmed'"
    );
    $stats['totalProductsSold'] = (int)($qSold->fetchColumn() ?: 0);

    // Inventory stats
    $stats['totalProducts'] = (int)($pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn() ?: 0);

    $stLow = $pdo->prepare("SELECT COUNT(*) FROM products WHERE is_active = 1 AND quantity_in_stock <= ?");
    $stLow->execute([$lowStockThreshold]);
    $stats['lowStock'] = (int)($stLow->fetchColumn() ?: 0);

    // Suppliers (active)
    $stats['totalSuppliers'] = (int)($pdo->query("SELECT COUNT(*) FROM suppliers WHERE is_active = 1")->fetchColumn() ?: 0);

    // Finance stats
    $stats['totalIncome']  = (float)($pdo->query("SELECT COALESCE(SUM(amount),0) FROM finance_records WHERE type = 'income'")->fetchColumn() ?: 0);
    $stats['totalExpense'] = (float)($pdo->query("SELECT COALESCE(SUM(amount),0) FROM finance_records WHERE type = 'expense'")->fetchColumn() ?: 0);
    $stats['grossProfit'] = $stats['totalIncome'] - $stats['totalExpense'];

    // Recent orders (Live Data)
    $recentOrders = $pdo->query(
        "SELECT o.id, o.order_number, o.order_date, o.status, o.$orderTotalColumn AS amount,
                COALESCE(u.name, COALESCE(u.email,'Guest')) AS customer
           FROM orders o
      LEFT JOIN users u ON u.id = o.user_id
       ORDER BY o.order_date DESC
          LIMIT 5" // Limit to 5 for the dashboard widget
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Low stock products list (Live Data)
    $stLowList = $pdo->prepare(
        "SELECT name, quantity_in_stock, minimum_stock_level 
           FROM products
          WHERE is_active = 1
            AND quantity_in_stock <= ?
       ORDER BY quantity_in_stock ASC
          LIMIT 5"
    );
    $stLowList->execute([$lowStockThreshold]);
    $lowStockProducts = $stLowList->fetchAll(PDO::FETCH_ASSOC) ?: [];

} catch (PDOException $e) {
    // Audit log should catch this; for UI, fall back to zeros/empty arrays
    error_log("Dashboard Data Fetch Failed: " . $e->getMessage());
}
// -----------------------------------------------------------

function formatDate($date) {
    return date('M j, g:i A', strtotime($date));
}

function statusBadge(string $status): string {
    return match (strtolower($status)) {
        'pending'    => 'bg-yellow-100 text-yellow-800',
        'confirmed'  => 'bg-blue-100 text-blue-800',
        'processing' => 'bg-purple-100 text-purple-800',
        'shipped'    => 'bg-indigo-100 text-indigo-800',
        'delivered'  => 'bg-green-100 text-green-800',
        'cancelled'  => 'bg-red-100 text-red-800',
        default      => 'bg-gray-100 text-gray-800',
    };
}

// --- Dynamic Color Class Helper (Sophisticated Look) ---
function getMetricClass(string $type): string {
    return match ($type) {
        'revenue' => 'bg-blue-50 border-blue-200 text-blue-800',
        'profit'  => 'bg-green-50 border-green-200 text-green-800',
        'value'   => 'bg-gray-50 border-gray-200 text-gray-800',
        'alert'   => 'bg-red-50 border-red-200 text-red-700',
        default   => 'bg-gray-50 border-gray-200 text-gray-800',
    };
}

include 'header.php';
?>

<div class="space-y-10">
    <h1 class="text-3xl font-light tracking-tight text-gray-900 mb-8 border-b pb-3">
        <?= htmlspecialchars($mainTitle) ?> Overview
    </h1>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        
        <div class="p-6 rounded-xl shadow-lg border <?= getMetricClass('revenue') ?> hover:shadow-xl transition duration-300">
            <p class="text-sm font-medium uppercase tracking-wider">Revenue (MTD)</p>
            <p class="mt-2 text-3xl font-extrabold">
                <?= $currencySymbol . number_format($stats['monthlyRevenue'], 2) ?>
            </p>
            <p class="text-xs mt-2 font-medium">
                <?= number_format($stats['approvedOrders']) ?> Confirmed Orders
            </p>
        </div>

        <div class="p-6 rounded-xl shadow-lg border <?= getMetricClass('profit') ?> hover:shadow-xl transition duration-300">
            <p class="text-sm font-medium uppercase tracking-wider"><?= htmlspecialchars($grossProfitLabel) ?></p>
            <p class="mt-2 text-3xl font-extrabold">
                <?= $currencySymbol . number_format($stats['grossProfit'], 2) ?>
            </p>
            <p class="text-xs mt-2 font-medium">
                Income: <span class="text-green-700 font-semibold"><?php echo formatCurrency($stats['totalIncome']); ?></span> / 
                Expense: <span class="text-red-600 font-semibold"><?php echo formatCurrency($stats['totalExpense']); ?></span>
            </p>
        </div>

        <div class="p-6 rounded-xl shadow-lg border <?= getMetricClass('value') ?> hover:shadow-xl transition duration-300">
            <p class="text-sm font-medium uppercase tracking-wider">Total <?= htmlspecialchars($inventoryLinkTitle) ?></p>
            <p class="mt-2 text-3xl font-extrabold">
                <?= number_format($stats['totalProducts']) ?> Items
            </p>
            <p class="text-xs mt-2 font-medium">
                <?= number_format($stats['totalSuppliers']) ?> Active Suppliers
            </p>
        </div>
        
        <div class="p-6 rounded-xl shadow-lg border <?= getMetricClass('alert') ?> hover:shadow-xl transition duration-300">
            <p class="text-sm font-medium uppercase tracking-wider">Pending Orders</p>
            <p class="mt-2 text-3xl font-extrabold">
                <?= number_format($stats['pendingOrders']) ?> Awaiting Action
            </p>
            <a href="orders.php" class="text-xs mt-2 font-medium text-red-600 hover:text-red-700 transition block">
                Process Orders →
            </a>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-1 bg-white p-6 rounded-xl shadow-lg border border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900 mb-4 border-b pb-3">
                Critical Alerts
            </h2>
            
            <?php if ($stats['lowStock'] > 0): ?>
                <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200">
                    <p class="text-sm font-bold text-red-600 mb-1">
                        ⚠️ Low Stock: <?= number_format($stats['lowStock']) ?> <?= htmlspecialchars($inventoryLinkTitle) ?>
                    </p>
                    <ul class="list-disc list-inside text-xs text-red-500 ml-2 space-y-0.5">
                        <?php foreach ($lowStockProducts as $product): ?>
                            <li><?= htmlspecialchars($product['name']) ?> (Min: <?= $product['minimum_stock_level'] ?? $lowStockThreshold ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="inventory.php?status=low_stock" class="mt-2 text-xs font-semibold text-blue-600 hover:text-blue-700 transition block">
                        Create Restock Task →
                    </a>
                </div>
            <?php endif; ?>
            
            <?php if ($stats['pendingOrders'] > 0): ?>
                <div class="p-3 rounded-lg bg-yellow-50 border border-yellow-200">
                    <p class="text-sm font-bold text-yellow-600 mb-1">
                        ⏳ Pending Orders: <?= number_format($stats['pendingOrders']) ?>
                    </p>
                    <a href="orders.php?status=pending" class="text-xs font-semibold text-blue-600 hover:text-blue-700 transition block">
                        Review Awaiting Confirmation →
                    </a>
                </div>
            <?php elseif ($stats['lowStock'] == 0): ?>
                <div class="flex items-center justify-center p-8 text-gray-500">
                    <div class="text-center">
                        <div class="text-3xl mb-2">🎉</div>
                        <div class="text-sm">No immediate critical alerts.</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-lg border border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900 mb-4 border-b pb-3">
                Recent Orders
            </h2>
            <ul class="divide-y divide-gray-100">
                <?php if (empty($recentOrders)): ?>
                    <li class="py-4 text-center text-gray-500">No recent orders found.</li>
                <?php else: ?>
                    <?php foreach ($recentOrders as $order): ?>
                        <li class="py-3 flex justify-between items-center hover:bg-gray-50 px-2 rounded-md">
                            <div class="flex flex-col">
                                <p class="text-sm font-medium text-gray-900">#<?php echo htmlspecialchars($order['order_number'] ?? $order['id']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($order['customer']); ?></p>
                            </div>
                            <div class="flex items-center space-x-4">
                                <span class="text-sm font-semibold text-gray-800"><?= $currencySymbol . number_format($order['amount'] ?? 0, 2) ?></span>
                                <span class="px-2 py-1 text-xs rounded-full <?php echo statusBadge($order['status']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
            <div class="mt-6 text-right">
                <a href="orders.php" class="text-sm font-medium text-blue-600 hover:text-blue-700 transition">
                    View All Orders →
                </a>
            </div>
        </div>

    </div>
</div>

<?php include 'footer.php'; ?>