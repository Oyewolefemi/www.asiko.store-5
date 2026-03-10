<?php
session_start();
require 'db.php';
require 'functions.php';
include 'header.php';

// --- Data Preparation ---

// Inventory stats
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalInventoryQty = $pdo->query("SELECT SUM(quantity_in_stock) FROM products")->fetchColumn();
$totalInventoryValue = $pdo->query("SELECT SUM(quantity_in_stock * selling_price) FROM products")->fetchColumn();

// Financial Calculations (using logic introduced in finance.php)

// 1. Operational Income (manual income + fees, excluding sales revenue)
$totalOperationalIncome = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM finance_records WHERE type = 'income' AND category_id NOT IN (SELECT id FROM finance_categories WHERE name = 'Sales Revenue')")->fetchColumn();

// 2. Operational Expenses (all expenses recorded in finance_records)
$totalOperationalExpenses = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM finance_records WHERE type = 'expense'")->fetchColumn();

// 3. Total Sales Revenue (from stock_movements)
$salesRevenueQuery = $pdo->query("
    SELECT COALESCE(SUM(sm.unit_cost * ABS(sm.quantity_change)), 0) AS total_sales
    FROM stock_movements sm
    WHERE sm.movement_type = 'sale'
");
$totalSalesRevenue = $salesRevenueQuery->fetchColumn();

// 4. Total Cost of Goods Sold (COGS)
$cogsQuery = $pdo->query("
    SELECT COALESCE(SUM(p.cost_price * ABS(sm.quantity_change)), 0) AS total_cogs
    FROM stock_movements sm
    JOIN products p ON sm.product_id = p.id
    WHERE sm.movement_type = 'sale'
");
$totalCOGS = $cogsQuery->fetchColumn();

// 5. Calculate profits
$grossProfit = $totalSalesRevenue - $totalCOGS;
$netProfit = $grossProfit + $totalOperationalIncome - $totalOperationalExpenses;


// Top 5 products by quantity
$topProducts = $pdo->query("
    SELECT name, quantity_in_stock
    FROM products
    ORDER BY quantity_in_stock DESC
    LIMIT 5
")->fetchAll();

// Expense by category
$expenseByCategory = $pdo->query("
    SELECT fc.name AS category_name, SUM(fr.amount) AS total
    FROM finance_records fr
    LEFT JOIN finance_categories fc ON fr.category_id = fc.id
    WHERE fr.type = 'expense'
    GROUP BY fc.name
    ORDER BY total DESC
")->fetchAll();

// Low stock products
$lowStock = $pdo->query("
    SELECT name, quantity_in_stock, unit, minimum_stock_level
    FROM products
    WHERE quantity_in_stock <= minimum_stock_level
    ORDER BY quantity_in_stock ASC
")->fetchAll();

// Currency symbol
$currencySymbol = getSystemSetting('currency_symbol', '₦');

?>
<div class="container mx-auto px-4 py-6">
    <h2 class="text-3xl font-bold text-gray-800 mb-6">Business Intelligence Reports</h2>
    
    <!-- Financial Summary -->
    <div class="mb-6">
        <h3 class="text-xl font-semibold mb-3 border-b pb-2">Financial Health Overview</h3>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <!-- Revenue -->
            <div class="bg-green-50 p-4 rounded-xl shadow-md border-l-4 border-green-500">
                <div class="font-semibold text-sm text-green-800">Total Revenue</div>
                <div class="text-2xl font-bold text-green-600"><?= formatCurrency($totalSalesRevenue, $currencySymbol) ?></div>
            </div>
            <!-- COGS -->
            <div class="bg-red-50 p-4 rounded-xl shadow-md border-l-4 border-red-500">
                <div class="font-semibold text-sm text-red-800">Total COGS</div>
                <div class="text-2xl font-bold text-red-600"><?= formatCurrency($totalCOGS, $currencySymbol) ?></div>
            </div>
            <!-- Gross Profit -->
            <div class="bg-blue-50 p-4 rounded-xl shadow-md border-l-4 border-blue-500">
                <div class="font-semibold text-sm text-blue-800">Gross Profit</div>
                <div class="text-2xl font-bold text-blue-600"><?= formatCurrency($grossProfit, $currencySymbol) ?></div>
            </div>
             <!-- Expenses -->
            <div class="bg-yellow-50 p-4 rounded-xl shadow-md border-l-4 border-yellow-500">
                <div class="font-semibold text-sm text-yellow-800">Op. Expenses</div>
                <div class="text-2xl font-bold text-yellow-600"><?= formatCurrency($totalOperationalExpenses, $currencySymbol) ?></div>
            </div>
            <!-- Net Profit -->
            <div class="bg-gray-200 p-4 rounded-xl shadow-md border-l-4 border-gray-500">
                <div class="font-semibold text-sm text-gray-800">Net Profit</div>
                <div class="text-2xl font-bold <?= $netProfit >= 0 ? 'text-green-700' : 'text-red-700' ?>"><?= formatCurrency($netProfit, $currencySymbol) ?></div>
            </div>
        </div>
    </div>

    <!-- Inventory and Stock Reports -->
    <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <!-- Inventory Value -->
        <div class="bg-white p-6 rounded-xl shadow-lg">
            <h3 class="text-lg font-semibold mb-3 border-b pb-2">Inventory Summary</h3>
            <div class="space-y-3">
                <div class="flex justify-between items-center text-sm">
                    <span class="font-medium text-gray-600">Total Products:</span>
                    <span class="font-bold text-lg text-blue-600"><?= $totalProducts ?></span>
                </div>
                <div class="flex justify-between items-center text-sm">
                    <span class="font-medium text-gray-600">Total Units in Stock:</span>
                    <span class="font-bold text-lg text-gray-800"><?= number_format($totalInventoryQty ?: 0) ?></span>
                </div>
                <div class="flex justify-between items-center text-sm">
                    <span class="font-medium text-gray-600">Total Stock Value (Retail):</span>
                    <span class="font-bold text-lg text-green-600"><?= formatCurrency($totalInventoryValue ?: 0, $currencySymbol) ?></span>
                </div>
            </div>
        </div>

        <!-- Low Stock Products -->
        <div class="bg-white p-6 rounded-xl shadow-lg md:col-span-2">
            <h3 class="text-lg font-semibold mb-3 border-b pb-2 text-red-600">⚠️ Low Stock Alert (Action Required)</h3>
            <?php if (count($lowStock) === 0): ?>
                <div class="text-center text-gray-500 py-4">No low stock products found.</div>
            <?php else: ?>
                <ul class="space-y-2 max-h-48 overflow-y-auto">
                    <?php foreach ($lowStock as $prod): ?>
                        <li class="flex justify-between items-center p-2 bg-red-50 rounded border border-red-200">
                            <span class="text-sm font-medium text-red-800"><?= htmlspecialchars($prod['name']) ?></span>
                            <span class="text-sm text-red-600">
                                **<?= number_format($prod['quantity_in_stock']) ?>** <?= htmlspecialchars($prod['unit']) ?> 
                                (Min: <?= htmlspecialchars($prod['minimum_stock_level']) ?>)
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="mt-3 text-right">
                    <a href="tasks.php?status=pending" class="text-sm text-blue-600 hover:underline">View Automated Restock Tasks →</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Expense Breakdown and Top Products -->
    <div class="mb-6 grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Expense Breakdown by Category -->
        <div class="bg-white p-6 rounded-xl shadow-lg">
            <h3 class="text-lg font-semibold mb-4 border-b pb-2">Expense Breakdown</h3>
            <?php if (empty($expenseByCategory)): ?>
                <div class="text-center text-gray-500 py-4">No expense records found.</div>
            <?php else: ?>
                <ul class="space-y-3">
                    <?php foreach ($expenseByCategory as $cat): ?>
                        <li class="flex justify-between items-center p-2 bg-gray-50 rounded">
                            <span class="text-sm font-medium text-gray-800"><?= htmlspecialchars($cat['category_name'] ?? 'Uncategorized') ?></span>
                            <span class="text-sm font-bold text-red-600"><?= formatCurrency($cat['total'], $currencySymbol) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- Top Products by Stock -->
        <div class="bg-white p-6 rounded-xl shadow-lg">
            <h3 class="text-lg font-semibold mb-4 border-b pb-2">Top Products by Stock Quantity</h3>
            <?php if (empty($topProducts)): ?>
                <div class="text-center text-gray-500 py-4">No products found.</div>
            <?php else: ?>
                <ul class="space-y-3">
                    <?php foreach ($topProducts as $prod): ?>
                        <li class="flex justify-between items-center p-2 bg-gray-50 rounded">
                            <span class="text-sm font-medium text-gray-800"><?= htmlspecialchars($prod['name']) ?></span>
                            <span class="text-sm font-bold text-green-700"><?= number_format($prod['quantity_in_stock']) ?> units</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
