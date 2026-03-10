<?php
session_start();
require_once 'db.php';
require_once 'functions.php';
include 'header.php';

// Load settings
$settingsFile = 'settings.json';
$settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [
    'business_name' => 'Business Management System',
    'currency' => '₦',
    'low_stock_threshold' => 5
];

// Date range filter
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Fetch products for dropdown/autocomplete
$products = $pdo->query("SELECT * FROM products WHERE is_active = 1 ORDER BY name")->fetchAll();

// Add Direct Sale
if (isset($_POST['add_sale'])) {
    $productName = trim($_POST['product_name']);
    $quantity = floatval($_POST['quantity']);
    $unit_price = floatval($_POST['unit_price']);
    $date = $_POST['date'];
    $sold_by = trim($_POST['sold_by']);
    $buyer_name = trim($_POST['buyer_name']);

    $stmt = $pdo->prepare("SELECT * FROM products WHERE name = ?");
    $stmt->execute([$productName]);
    $product = $stmt->fetch();
    
    if (!$product) {
        $error = "Product not found.";
    } elseif ($quantity <= 0 || $unit_price < 0) {
        $error = "Quantity and price must be valid.";
    } elseif ($quantity > $product['quantity_in_stock']) {
        $error = "Not enough stock for this sale. Available: " . $product['quantity_in_stock'];
    } else {
        try {
            $pdo->beginTransaction();
            
            $total_price = $quantity * $unit_price;
            
            $stmt = $pdo->prepare("
                INSERT INTO stock_movements (product_id, movement_type, reference_type, quantity_change, 
                                           quantity_before, quantity_after, unit_cost, notes, created_by)
                VALUES (?, 'sale', 'manual', ?, ?, ?, ?, ?, ?)
            ");
            $quantity_before = $product['quantity_in_stock'];
            $quantity_after = $quantity_before - $quantity;
            $notes = "Direct sale to: " . $buyer_name . " | Sold by: " . $sold_by;
            $stmt->execute([
                $product['id'], 
                -$quantity, 
                $quantity_before, 
                $quantity_after, 
                $unit_price, 
                $notes, 
                $_SESSION['admin_id'] ?? $_SESSION['user_id']
            ]);
            
            $stmt = $pdo->prepare("UPDATE products SET quantity_in_stock = quantity_in_stock - ? WHERE id = ?");
            $stmt->execute([$quantity, $product['id']]);
            
            $stmt = $pdo->prepare("SELECT id FROM finance_categories WHERE type = 'income' AND name LIKE '%sale%' LIMIT 1");
            $stmt->execute();
            $financeCategory = $stmt->fetch();
            
            if ($financeCategory) {
                $stmt = $pdo->prepare("
                    INSERT INTO finance_records (type, category_id, amount, description, reference_type, 
                                               transaction_date, created_by)
                    VALUES ('income', ?, ?, ?, 'manual', ?, ?)
                ");
                $description = "Direct sale: {$product['name']} (Qty: {$quantity}) to {$buyer_name}";
                $stmt->execute([
                    $financeCategory['id'], 
                    $total_price, 
                    $description, 
                    $date, 
                    $_SESSION['admin_id'] ?? $_SESSION['user_id']
                ]);
            }
            
            $pdo->commit();
            $success = "Sale recorded successfully.";
            
        } catch (Exception $e) {
            $pdo->rollback();
            $error = "Error recording sale: " . $e->getMessage();
        }
    }
}

// Approve Order
if (isset($_POST['approve_order'])) {
    $order_id = intval($_POST['order_id']);
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            SELECT o.*, u.email 
            FROM orders o 
            JOIN users u ON u.id = o.user_id 
            WHERE o.id = ? AND o.status = 'pending'
        ");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        
        if (!$order) {
            throw new Exception('Order not found or already processed.');
        }
        
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET status = 'confirmed', approved_at = CURRENT_TIMESTAMP, 
                approved_by = ?, approved_by_admin = 1 
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['admin_id'] ?? $_SESSION['user_id'], $order_id]);
        
        $pdo->commit();
        $success = "Order #{$order_id} approved successfully.";
        
    } catch (Exception $e) {
        $pdo->rollback();
        $error = "Error approving order: " . $e->getMessage();
    }
}

// Get comprehensive sales statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_orders,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
        COUNT(CASE WHEN status IN ('confirmed', 'processing', 'shipped') THEN 1 END) as active_orders,
        COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_orders,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders,
        COALESCE(SUM(CASE WHEN status IN ('confirmed', 'processing', 'shipped', 'delivered') 
                     THEN grand_total END), 0) as total_revenue,
        COALESCE(AVG(CASE WHEN status IN ('confirmed', 'processing', 'shipped', 'delivered') 
                     THEN grand_total END), 0) as avg_order_value
    FROM orders
    WHERE order_date BETWEEN ? AND ?
");
$stmt->execute([$dateFrom, $dateTo . ' 23:59:59']);
$orderStats = $stmt->fetch();

// Get sales by payment method
$paymentStats = $pdo->prepare("
    SELECT 
        payment_method,
        COUNT(*) as order_count,
        SUM(grand_total) as total_amount
    FROM orders
    WHERE status IN ('confirmed', 'processing', 'shipped', 'delivered')
    AND order_date BETWEEN ? AND ?
    GROUP BY payment_method
");
$paymentStats->execute([$dateFrom, $dateTo . ' 23:59:59']);
$paymentMethods = $paymentStats->fetchAll();

// Get top selling products
$topProducts = $pdo->prepare("
    SELECT 
        p.name,
        p.sku,
        SUM(ABS(sm.quantity_change)) as total_sold,
        SUM(ABS(sm.quantity_change) * sm.unit_cost) as revenue,
        p.unit
    FROM stock_movements sm
    JOIN products p ON sm.product_id = p.id
    WHERE sm.movement_type = 'sale'
    AND sm.created_at BETWEEN ? AND ?
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 10
");
$topProducts->execute([$dateFrom, $dateTo . ' 23:59:59']);
$topSellingProducts = $topProducts->fetchAll();

// Get sales by category
$categorySales = $pdo->prepare("
    SELECT 
        COALESCE(c.name, 'Uncategorized') as category_name,
        COUNT(DISTINCT sm.id) as transactions,
        SUM(ABS(sm.quantity_change)) as units_sold,
        SUM(ABS(sm.quantity_change) * sm.unit_cost) as revenue
    FROM stock_movements sm
    JOIN products p ON sm.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE sm.movement_type = 'sale'
    AND sm.created_at BETWEEN ? AND ?
    GROUP BY c.id
    ORDER BY revenue DESC
");
$categorySales->execute([$dateFrom, $dateTo . ' 23:59:59']);
$salesByCategory = $categorySales->fetchAll();

// Get daily sales trend
$dailySales = $pdo->prepare("
    SELECT 
        DATE(sm.created_at) as sale_date,
        COUNT(DISTINCT sm.id) as transactions,
        SUM(ABS(sm.quantity_change)) as units_sold,
        SUM(ABS(sm.quantity_change) * sm.unit_cost) as revenue
    FROM stock_movements sm
    WHERE sm.movement_type = 'sale'
    AND sm.created_at BETWEEN ? AND ?
    GROUP BY DATE(sm.created_at)
    ORDER BY sale_date ASC
");
$dailySales->execute([$dateFrom, $dateTo . ' 23:59:59']);
$dailySalesTrend = $dailySales->fetchAll();

// Get customer purchase frequency
$customerStats = $pdo->prepare("
    SELECT 
        u.name,
        u.email,
        COUNT(o.id) as order_count,
        SUM(o.grand_total) as total_spent,
        MAX(o.order_date) as last_order_date
    FROM users u
    JOIN orders o ON u.id = o.user_id
    WHERE o.status IN ('confirmed', 'processing', 'shipped', 'delivered')
    AND o.order_date BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY total_spent DESC
    LIMIT 10
");
$customerStats->execute([$dateFrom, $dateTo . ' 23:59:59']);
$topCustomers = $customerStats->fetchAll();

// Get low stock products
$lowStockProducts = $pdo->prepare("
    SELECT name, quantity_in_stock, minimum_stock_level, unit
    FROM products 
    WHERE quantity_in_stock <= minimum_stock_level AND is_active = 1
    ORDER BY quantity_in_stock ASC
    LIMIT 5
");
$lowStockProducts->execute();
$lowStock = $lowStockProducts->fetchAll();

// Get pending orders
$pendingOrders = $pdo->query("
    SELECT o.id, o.order_number, u.email, u.name, o.order_date, o.grand_total, o.payment_method
    FROM orders o
    JOIN users u ON u.id = o.user_id
    WHERE o.status = 'pending' AND o.payment_method = 'manual'
    ORDER BY o.order_date DESC
")->fetchAll();

// Get recent sales
$recentSales = $pdo->prepare("
    SELECT sm.*, p.name as product_name, p.unit, u.name as user_name
    FROM stock_movements sm
    JOIN products p ON sm.product_id = p.id
    LEFT JOIN users u ON sm.created_by = u.id
    WHERE sm.movement_type = 'sale' AND sm.reference_type IN ('order', 'manual')
    AND sm.created_at BETWEEN ? AND ?
    ORDER BY sm.created_at DESC
    LIMIT 20
");
$recentSales->execute([$dateFrom, $dateTo . ' 23:59:59']);
$recentSalesData = $recentSales->fetchAll();

// Get all orders
$allOrders = $pdo->prepare("
    SELECT o.id, o.order_number, u.email, u.name, o.order_date, o.grand_total, 
           o.status, o.payment_method, o.approved_at
    FROM orders o
    JOIN users u ON u.id = o.user_id
    WHERE o.order_date BETWEEN ? AND ?
    ORDER BY o.order_date DESC
");
$allOrders->execute([$dateFrom, $dateTo . ' 23:59:59']);
$allOrdersData = $allOrders->fetchAll();

// Get profit analysis (if cost_price is available)
$profitAnalysis = $pdo->prepare("
    SELECT 
        p.name,
        SUM(ABS(sm.quantity_change)) as units_sold,
        p.cost_price,
        AVG(sm.unit_cost) as avg_selling_price,
        SUM(ABS(sm.quantity_change) * sm.unit_cost) as revenue,
        SUM(ABS(sm.quantity_change) * p.cost_price) as cost,
        SUM(ABS(sm.quantity_change) * (sm.unit_cost - p.cost_price)) as profit,
        CASE 
            WHEN p.cost_price > 0 THEN 
                ((AVG(sm.unit_cost) - p.cost_price) / p.cost_price * 100)
            ELSE 0 
        END as profit_margin
    FROM stock_movements sm
    JOIN products p ON sm.product_id = p.id
    WHERE sm.movement_type = 'sale'
    AND sm.created_at BETWEEN ? AND ?
    AND p.cost_price > 0
    GROUP BY p.id
    ORDER BY profit DESC
    LIMIT 10
");
$profitAnalysis->execute([$dateFrom, $dateTo . ' 23:59:59']);
$profitData = $profitAnalysis->fetchAll();

// Calculate total profit/loss
$totalProfit = 0;
$totalRevenue = 0;
$totalCost = 0;
foreach ($profitData as $item) {
    $totalProfit += $item['profit'];
    $totalRevenue += $item['revenue'];
    $totalCost += $item['cost'];
}

// Get sales performance summary
$salesSummary = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT DATE(sm.created_at)) as days_with_sales,
        COUNT(DISTINCT sm.product_id) as unique_products_sold,
        SUM(ABS(sm.quantity_change)) as total_units_sold,
        SUM(ABS(sm.quantity_change) * sm.unit_cost) as total_sales_value
    FROM stock_movements sm
    WHERE sm.movement_type = 'sale'
    AND sm.created_at BETWEEN ? AND ?
");
$salesSummary->execute([$dateFrom, $dateTo . ' 23:59:59']);
$summary = $salesSummary->fetch();

// Get order status breakdown
$statusBreakdown = $pdo->prepare("
    SELECT 
        status,
        COUNT(*) as count,
        SUM(grand_total) as total_value
    FROM orders
    WHERE order_date BETWEEN ? AND ?
    GROUP BY status
    ORDER BY count DESC
");
$statusBreakdown->execute([$dateFrom, $dateTo . ' 23:59:59']);
$orderStatuses = $statusBreakdown->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($settings['business_name']) ?> - Sales Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
</head>
<body class="bg-gray-100">
<div class="flex min-h-screen">
    
    <div class="flex-1 p-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Sales & Analytics Dashboard</h1>
            <p class="text-gray-600 mt-2">Comprehensive sales data and insights</p>
        </div>

        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <form method="get" class="flex gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" 
                           class="border border-gray-300 rounded-md px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" 
                           class="border border-gray-300 rounded-md px-3 py-2">
                </div>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                    Filter
                </button>
                <a href="?" class="bg-gray-500 text-white px-6 py-2 rounded-md hover:bg-gray-600">
                    Reset
                </a>
            </form>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500">Total Orders</h3>
                <p class="text-3xl font-bold text-gray-900"><?= $orderStats['total_orders'] ?></p>
                <p class="text-sm text-gray-500 mt-1">Delivered: <?= $orderStats['delivered_orders'] ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500">Pending Orders</h3>
                <p class="text-3xl font-bold text-yellow-600"><?= $orderStats['pending_orders'] ?></p>
                <p class="text-sm text-gray-500 mt-1">Cancelled: <?= $orderStats['cancelled_orders'] ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500">Total Revenue</h3>
                <p class="text-3xl font-bold text-green-600"><?= $settings['currency'] ?><?= number_format($orderStats['total_revenue'], 2) ?></p>
                <p class="text-sm text-gray-500 mt-1">Units: <?= number_format($summary['total_units_sold'] ?? 0, 0) ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500">Avg Order Value</h3>
                <p class="text-3xl font-bold text-blue-600"><?= $settings['currency'] ?><?= number_format($orderStats['avg_order_value'], 2) ?></p>
                <p class="text-sm text-gray-500 mt-1">Products: <?= number_format($summary['unique_products_sold'] ?? 0, 0) ?></p>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($lowStock)): ?>
            <div class="bg-orange-100 border border-orange-400 text-orange-700 px-4 py-3 rounded mb-6">
                <h4 class="font-bold">⚠️ Low Stock Alert:</h4>
                <ul class="mt-2">
                    <?php foreach ($lowStock as $item): ?>
                        <li><?= htmlspecialchars($item['name']) ?> - Only <?= $item['quantity_in_stock'] ?> <?= htmlspecialchars($item['unit']) ?> remaining</li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div x-data="{ activeTab: 'overview' }" class="bg-white rounded-lg shadow">
            <div class="border-b border-gray-200 overflow-x-auto">
                <nav class="flex space-x-8 px-6 min-w-max">
                    <button @click="activeTab = 'overview'" 
                            :class="activeTab === 'overview' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500'"
                            class="py-4 px-1 border-b-2 font-medium text-sm hover:text-gray-700 whitespace-nowrap">
                        📊 Overview
                    </button>
                    <button @click="activeTab = 'direct_sales'" 
                            :class="activeTab === 'direct_sales' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500'"
                            class="py-4 px-1 border-b-2 font-medium text-sm hover:text-gray-700 whitespace-nowrap">
                        💰 Direct Sales
                    </button>
                    <button @click="activeTab = 'top_products'" 
                            :class="activeTab === 'top_products' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500'"
                            class="py-4 px-1 border-b-2 font-medium text-sm hover:text-gray-700 whitespace-nowrap">
                        🏆 Top Products
                    </button>
                    <button @click="activeTab = 'categories'" 
                            :class="activeTab === 'categories' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500'"
                            class="py-4 px-1 border-b-2 font-medium text-sm hover:text-gray-700 whitespace-nowrap">
                        📦 By Category
                    </button>
                    <button @click="activeTab = 'daily_trend'" 
                            :class="activeTab === 'daily_trend' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500'"
                            class="py-4 px-1 border-b-2 font-medium text-sm hover:text-gray-700 whitespace-nowrap">
                        📈 Daily Trend
                    </button>
                    <button @click="activeTab = 'customers'" 
                            :class="activeTab === 'customers' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500'"
                            class="py-4 px-1 border-b-2 font-medium text-sm hover:text-gray-700 whitespace-nowrap">
                        👥 Top Customers
                    </button>
                    <button @click="activeTab = 'profit'" 
                            :class="activeTab === 'profit' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500'"
                            class="py-4 px-1 border-b-2 font-medium text-sm hover:text-gray-700 whitespace-nowrap">
                        💵 Profit Analysis
                    </button>
                    <button @click="activeTab = 'pending_orders'" 
                            :class="activeTab === 'pending_orders' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500'"
                            class="py-4 px-1 border-b-2 font-medium text-sm hover:text-gray-700 whitespace-nowrap">
                        ⏳ Pending (<?= count($pendingOrders) ?>)
                    </button>
                    <button @click="activeTab = 'all_orders'" 
                            :class="activeTab === 'all_orders' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500'"
                            class="py-4 px-1 border-b-2 font-medium text-sm hover:text-gray-700 whitespace-nowrap">
                        📋 All Orders
                    </button>
                    <button @click="activeTab = 'sales_history'" 
                            :class="activeTab === 'sales_history' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500'"
                            class="py-4 px-1 border-b-2 font-medium text-sm hover:text-gray-700 whitespace-nowrap">
                        📝 Sales History
                    </button>
                </nav>
            </div>

            <div x-show="activeTab === 'overview'" class="p-6">
                <h3 class="text-lg font-semibold mb-4">Sales Overview</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="border border-gray-200 rounded-lg p-4 bg-blue-50">
                        <div class="text-sm text-gray-600">Days with Sales</div>
                        <div class="text-2xl font-bold text-blue-700"><?= $summary['days_with_sales'] ?? 0 ?> days</div>
                    </div>
                    <div class="border border-gray-200 rounded-lg p-4 bg-purple-50">
                        <div class="text-sm text-gray-600">Products Sold</div>
                        <div class="text-2xl font-bold text-purple-700"><?= $summary['unique_products_sold'] ?? 0 ?> items</div>
                    </div>
                    <div class="border border-gray-200 rounded-lg p-4 bg-green-50">
                        <div class="text-sm text-gray-600">Total Units Sold</div>
                        <div class="text-2xl font-bold text-green-700"><?= number_format($summary['total_units_sold'] ?? 0, 0) ?></div>
                    </div>
                </div>

                <div class="mb-6">
                    <h4 class="font-medium text-gray-700 mb-3">Order Status Breakdown</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <?php foreach ($orderStatuses as $status): ?>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <?php
                                $statusColors = [
                                    'pending' => 'yellow',
                                    'confirmed' => 'blue',
                                    'processing' => 'purple',
                                    'shipped' => 'indigo',
                                    'delivered' => 'green',
                                    'cancelled' => 'red'
                                ];
                                $color = $statusColors[$status['status']] ?? 'gray';
                                ?>
                                <div class="text-sm text-gray-500"><?= ucfirst($status['status']) ?></div>
                                <div class="text-2xl font-bold text-<?= $color ?>-600"><?= $status['count'] ?> orders</div>
                                <div class="text-sm text-gray-600"><?= $settings['currency'] ?><?= number_format($status['total_value'], 2) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="mb-6">
                    <h4 class="font-medium text-gray-700 mb-3">Sales by Payment Method</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <?php if (empty($paymentMethods)): ?>
                            <div class="col-span-3 text-center py-4 text-gray-500">
                                No payment data available for the selected period
                            </div>
                        <?php else: ?>
                            <?php foreach ($paymentMethods as $pm): ?>
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="text-sm text-gray-500"><?= ucfirst($pm['payment_method']) ?></div>
                                    <div class="text-2xl font-bold text-gray-900"><?= $pm['order_count'] ?> orders</div>
                                    <div class="text-sm text-green-600"><?= $settings['currency'] ?><?= number_format($pm['total_amount'], 2) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($profitData)): ?>
                    <div class="mb-6">
                        <h4 class="font-medium text-gray-700 mb-3">Gross Profitability Summary (Based on Cost Price)</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="border border-gray-200 rounded-lg p-4 bg-blue-50">
                                <div class="text-sm text-gray-600">Total Revenue</div>
                                <div class="text-2xl font-bold text-blue-700"><?= $settings['currency'] ?><?= number_format($totalRevenue, 2) ?></div>
                            </div>
                            <div class="border border-gray-200 rounded-lg p-4 bg-orange-50">
                                <div class="text-sm text-gray-600">Total Cost (COGS)</div>
                                <div class="text-2xl font-bold text-orange-700"><?= $settings['currency'] ?><?= number_format($totalCost, 2) ?></div>
                            </div>
                            <div class="border border-gray-200 rounded-lg p-4 <?= $totalProfit >= 0 ? 'bg-green-50' : 'bg-red-50' ?>">
                                <div class="text-sm text-gray-600">Gross Profit</div>
                                <div class="text-2xl font-bold <?= $totalProfit >= 0 ? 'text-green-700' : 'text-red-700' ?>">
                                    <?= $settings['currency'] ?><?= number_format($totalProfit, 2) ?>
                                </div>
                                <div class="text-xs text-gray-600">
                                    Margin: <?= $totalRevenue > 0 ? number_format(($totalProfit / $totalRevenue) * 100, 1) : 0 ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div x-show="activeTab === 'direct_sales'" class="p-6">
                <h3 class="text-lg font-semibold mb-4">Record Direct Sale</h3>
                
                <form method="post" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Product</label>
                        <input name="product_name" required list="productlist" placeholder="Select product" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <datalist id="productlist">
                            <?php foreach ($products as $p): ?>
                                <option value="<?= htmlspecialchars($p['name']) ?>">
                                    Stock: <?= $p['quantity_in_stock'] ?> | Price: <?= $settings['currency'] ?><?= number_format($p['selling_price'], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                        <input name="quantity" type="number" min="0.01" step="0.01" required placeholder="Qty" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit Price</label>
                        <input name="unit_price" type="number" min="0" step="0.01" required placeholder="Price" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                        <input name="date" type="date" required value="<?= date('Y-m-d') ?>" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sold By</label>
                        <input name="sold_by" placeholder="Staff name" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Buyer Name</label>
                        <input name="buyer_name" placeholder="Customer name" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="md:col-span-2">
                        <button name="add_sale" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 font-medium">
                            📝 Record Sale
                        </button>
                    </div>
                </form>
            </div>

            <div x-show="activeTab === 'top_products'" class="p-6">
                <h3 class="text-lg font-semibold mb-4">Top Selling Products</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rank</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Units Sold</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php $rank = 1; foreach ($topSellingProducts as $product): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $rank++ ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($product['name']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($product['sku']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right"><?= number_format($product['total_sold'], 2) ?> <?= htmlspecialchars($product['unit']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        <?= $settings['currency'] ?><?= number_format($product['revenue'], 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div x-show="activeTab === 'categories'" class="p-6">
                <h3 class="text-lg font-semibold mb-4">Sales by Category</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Transactions</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Units Sold</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($salesByCategory as $cat): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($cat['category_name']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        <?= number_format($cat['transactions']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        <?= number_format($cat['units_sold'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        <?= $settings['currency'] ?><?= number_format($cat['revenue'], 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div x-show="activeTab === 'daily_trend'" class="p-6">
                <h3 class="text-lg font-semibold mb-4">Daily Sales Trend</h3>
                
                <?php if (!empty($dailySalesTrend)): ?>
                    <div class="mb-8 p-4 border rounded-lg shadow-inner bg-gray-50">
                        <h4 class="font-medium text-gray-700 mb-3">Revenue Trend Over Period</h4>
                        <div style="height: 300px;">
                            <canvas id="dailyRevenueChart"></canvas>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Transactions</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Units Sold</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($dailySalesTrend as $day): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?= date('D, M j, Y', strtotime($day['sale_date'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        <?= number_format($day['transactions']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        <?= number_format($day['units_sold'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        <?= $settings['currency'] ?><?= number_format($day['revenue'], 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($dailySalesTrend)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                        No sales data available for the selected period
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (!empty($dailySalesTrend)): ?>
                    <script>
                        document.addEventListener('alpine:init', () => {
                            // Ensure the chart initializes only when its tab is active
                            Alpine.effect(() => {
                                if (Alpine.store('activeTab') === 'daily_trend' && !window.dailyRevenueChartInitialized) {
                                    
                                    const rawData = <?= json_encode($dailySalesTrend) ?>;
                                    
                                    const labels = rawData.map(item => new Date(item.sale_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
                                    const revenueData = rawData.map(item => parseFloat(item.revenue));

                                    const ctx = document.getElementById('dailyRevenueChart').getContext('2d');
                                    new Chart(ctx, {
                                        type: 'line',
                                        data: {
                                            labels: labels,
                                            datasets: [{
                                                label: 'Daily Revenue (<?= $settings['currency'] ?>)',
                                                data: revenueData,
                                                borderColor: 'rgb(59, 130, 246)',
                                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                                tension: 0.3,
                                                fill: true
                                            }]
                                        },
                                        options: {
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            scales: {
                                                y: {
                                                    beginAtZero: true,
                                                    title: {
                                                        display: true,
                                                        text: 'Revenue'
                                                    }
                                                }
                                            },
                                            plugins: {
                                                legend: {
                                                    display: true
                                                }
                                            }
                                        }
                                    });
                                    window.dailyRevenueChartInitialized = true;
                                }
                            });
                        });
                    </script>
                <?php endif; ?>
            </div>
            <div x-show="activeTab === 'customers'" class="p-6">
                <h3 class="text-lg font-semibold mb-4">Top Customers by Spending</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Orders</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Spent</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Order</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($topCustomers as $customer): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($customer['name']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= htmlspecialchars($customer['email']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        <?= number_format($customer['order_count']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        <?= $settings['currency'] ?><?= number_format($customer['total_spent'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('M j, Y', strtotime($customer['last_order_date'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($topCustomers)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                        No customer data available for the selected period
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div x-show="activeTab === 'profit'" class="p-6">
                <h3 class="text-lg font-semibold mb-4">Profit Analysis by Product</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Units Sold</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Revenue</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cost</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Profit</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Margin %</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($profitData as $profit): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($profit['name']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        <?= number_format($profit['units_sold'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        <?= $settings['currency'] ?><?= number_format($profit['revenue'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        <?= $settings['currency'] ?><?= number_format($profit['cost'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-right <?= $profit['profit'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                        <?= $settings['currency'] ?><?= number_format($profit['profit'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right <?= $profit['profit_margin'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                        <?= number_format($profit['profit_margin'], 1) ?>%
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($profitData)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                        No profit data available. Make sure products have cost prices set.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div x-show="activeTab === 'pending_orders'" class="p-6">
                <?php if (empty($pendingOrders)): ?>
                    <div class="text-center py-8">
                        <div class="text-gray-400 text-6xl mb-4">📋</div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No pending orders</h3>
                        <p class="text-gray-500">All manual payment orders have been processed.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order #</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Payment</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($pendingOrders as $order): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            #<?= $order['order_number'] ?? $order['id'] ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= date('M j, Y', strtotime($order['order_date'])) ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            <div><?= htmlspecialchars($order['name']) ?></div>
                                            <div class="text-xs text-gray-500"><?= htmlspecialchars($order['email']) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                            <?= $settings['currency'] ?><?= number_format($order['grand_total'], 2) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                                <?= ucfirst($order['payment_method']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <form method="post" class="inline">
                                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                <button name="approve_order" 
                                                        onclick="return confirm('Approve this order?')"
                                                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm font-medium">
                                                    ✅ Approve
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

            <div x-show="activeTab === 'all_orders'" class="p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order #</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Payment</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Approved</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($allOrdersData as $order): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        #<?= $order['order_number'] ?? $order['id'] ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('M j, Y', strtotime($order['order_date'])) ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <div><?= htmlspecialchars($order['name']) ?></div>
                                        <div class="text-xs text-gray-500"><?= htmlspecialchars($order['email']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        <?= $settings['currency'] ?><?= number_format($order['grand_total'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">
                                            <?= ucfirst($order['payment_method']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <?php
                                        $statusClasses = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'confirmed' => 'bg-blue-100 text-blue-800',
                                            'processing' => 'bg-purple-100 text-purple-800',
                                            'shipped' => 'bg-indigo-100 text-indigo-800',
                                            'delivered' => 'bg-green-100 text-green-800',
                                            'cancelled' => 'bg-red-100 text-red-800'
                                        ];
                                        $class = $statusClasses[$order['status']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?= $class ?>">
                                            <?= ucfirst($order['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= $order['approved_at'] ? date('M j, Y g:i A', strtotime($order['approved_at'])) : 'N/A' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div x-show="activeTab === 'sales_history'" class="p-6">
                <h3 class="text-lg font-semibold mb-4">Recent Sales Activity</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Quantity</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Unit Price</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recentSalesData as $sale): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('M j, Y g:i A', strtotime($sale['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= htmlspecialchars($sale['product_name']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        <?= number_format(abs($sale['quantity_change']), 2) ?> <?= htmlspecialchars($sale['unit']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        <?= $settings['currency'] ?><?= number_format($sale['unit_cost'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        <?= $settings['currency'] ?><?= number_format(abs($sale['quantity_change']) * $sale['unit_cost'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?= $sale['reference_type'] === 'manual' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' ?>">
                                            <?= $sale['reference_type'] === 'manual' ? 'Direct Sale' : 'Order Sale' ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                                        <?= htmlspecialchars($sale['notes']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-fill price when product is selected
document.addEventListener('DOMContentLoaded', function() {
    const productInput = document.querySelector('input[name="product_name"]');
    const priceInput = document.querySelector('input[name="unit_price"]');
    
    // Set a global store for Alpine.js to track the active tab
    Alpine.store('activeTab', 'overview');

    document.querySelectorAll('.border-b-2').forEach(button => {
        button.addEventListener('click', () => {
            const tab = button.getAttribute('@click').match(/'(.*)'/)[1];
            Alpine.store('activeTab', tab);
        });
    });

    if (productInput && priceInput) {
        productInput.addEventListener('input', function() {
            const productName = this.value;
            const products = <?= json_encode($products) ?>;
            
            const product = products.find(p => p.name === productName);
            if (product) {
                priceInput.value = product.selling_price;
            }
        });
    }
});
</script>
</body>
</html>