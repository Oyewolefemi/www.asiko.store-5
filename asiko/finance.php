<?php
session_start();
require 'db.php';
require 'functions.php';
include 'header.php';

$error = '';
$success = '';

// Initialize default finance categories if none exist
$categoryCount = $pdo->query("SELECT COUNT(*) FROM finance_categories")->fetchColumn();
if ($categoryCount == 0) {
    $defaultCategories = [
        ['Sales Revenue', 'income'],
        ['Product Returns', 'expense'],
        ['Inventory Purchase', 'expense'],
        ['Delivery Fees', 'income'],
        ['Operating Expenses', 'expense'],
        ['Marketing', 'expense'],
        ['Utilities', 'expense'],
        ['Refunds', 'expense']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO finance_categories (name, type) VALUES (?, ?)");
    foreach ($defaultCategories as $cat) {
        $stmt->execute($cat);
    }
}

// Fetch all finance categories
$categories = $pdo->query("SELECT id, name, type FROM finance_categories WHERE is_active = 1 ORDER BY type, name")->fetchAll(PDO::FETCH_ASSOC);

// Add manual income/expense
if (isset($_POST['add_manual'])) {
    $type = $_POST['type'];
    $amount = floatval($_POST['amount']);
    $desc = trim($_POST['description']);
    $date = $_POST['transaction_date'];
    $category_id = intval($_POST['category_id']);
    $user_id = $_SESSION['user_id'] ?? 1;

    if ($amount <= 0) {
        $error = "Amount must be greater than zero.";
    } elseif ($category_id <= 0) {
        $error = "Category is required.";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO finance_records (type, category_id, amount, description, transaction_date, reference_type, created_by)
            VALUES (?, ?, ?, ?, ?, 'manual', ?)
        ");
        $stmt->execute([$type, $category_id, $amount, $desc, $date, $user_id]);
        $success = "Manual transaction added successfully.";
    }
}

// Function to automatically record order-related finance entries
function recordOrderFinance($pdo, $order_id, $user_id) {
    // Get order details
    $stmt = $pdo->prepare("
        SELECT o.*, SUM(oi.total_price) as items_total 
        FROM orders o 
        LEFT JOIN order_items oi ON o.id = oi.order_id 
        WHERE o.id = ? 
        GROUP BY o.id
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (!$order) return;
    
    // Get category IDs
    $salesCategory = $pdo->query("SELECT id FROM finance_categories WHERE name = 'Sales Revenue' AND type = 'income' LIMIT 1")->fetchColumn();
    $deliveryCategory = $pdo->query("SELECT id FROM finance_categories WHERE name = 'Delivery Fees' AND type = 'income' LIMIT 1")->fetchColumn();
    
    if (!$salesCategory || !$deliveryCategory) return;
    
    // Record sales revenue
    if ($order['subtotal'] > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO finance_records (type, category_id, amount, description, transaction_date, reference_type, reference_id, created_by)
            VALUES ('income', ?, ?, ?, CURDATE(), 'order', ?, ?)
        ");
        $stmt->execute([
            $salesCategory, 
            $order['subtotal'], 
            "Sales revenue from order #" . $order['order_number'], 
            $order_id, 
            $user_id
        ]);
    }
    
    // Record delivery fee
    if ($order['delivery_fee'] > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO finance_records (type, category_id, amount, description, transaction_date, reference_type, reference_id, created_by)
            VALUES ('income', ?, ?, ?, CURDATE(), 'order', ?, ?)
        ");
        $stmt->execute([
            $deliveryCategory, 
            $order['delivery_fee'], 
            "Delivery fee from order #" . $order['order_number'], 
            $order_id, 
            $user_id
        ]);
    }
}

// Function to record purchase order finance entries
function recordPurchaseFinance($pdo, $po_id, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ?");
    $stmt->execute([$po_id]);
    $po = $stmt->fetch();
    
    if (!$po || $po['status'] != 'received') return;
    
    $expenseCategory = $pdo->query("SELECT id FROM finance_categories WHERE name = 'Inventory Purchase' AND type = 'expense' LIMIT 1")->fetchColumn();
    if (!$expenseCategory) return;
    
    $stmt = $pdo->prepare("
        INSERT INTO finance_records (type, category_id, amount, description, transaction_date, reference_type, reference_id, created_by)
        VALUES ('expense', ?, ?, ?, CURDATE(), 'purchase_order', ?, ?)
    ");
    $stmt->execute([
        $expenseCategory,
        $po['total_amount'],
        "Inventory purchase - PO #" . $po['po_number'],
        $po_id,
        $user_id
    ]);
}

// Process order confirmation (simulate admin confirming an order)
if (isset($_POST['confirm_order'])) {
    $order_id = intval($_POST['order_id']);
    $user_id = $_SESSION['user_id'] ?? 1;
    
    // Update order status
    $stmt = $pdo->prepare("UPDATE orders SET status = 'confirmed', approved_by = ?, approved_at = NOW() WHERE id = ?");
    $stmt->execute([$user_id, $order_id]);
    
    // Record finance entries
    recordOrderFinance($pdo, $order_id, $user_id);
    
    $success = "Order confirmed and finance records updated.";
}


// =============================================================================
// UPDATED FINANCIAL CALCULATIONS
// =============================================================================

// 1. Calculate operational income (manual income + fees, excluding sales revenue)
$totalOperationalIncome = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM finance_records WHERE type = 'income' AND category_id NOT IN (SELECT id FROM finance_categories WHERE name = 'Sales Revenue')")->fetchColumn();

// 2. Calculate operational expenses (all expenses, not including COGS if it were tracked as such)
$totalOperationalExpenses = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM finance_records WHERE type = 'expense'")->fetchColumn();

// 3. Calculate total sales revenue (all confirmed sales, for Gross Profit)
$salesRevenueQuery = $pdo->query("
    SELECT COALESCE(SUM(sm.unit_cost * ABS(sm.quantity_change)), 0) AS total_sales
    FROM stock_movements sm
    WHERE sm.movement_type = 'sale'
");
$totalSalesRevenue = $salesRevenueQuery->fetchColumn();

// 4. Calculate total cost of goods sold (COGS)
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

// =============================================================================

// Get recent orders for demo
$recentOrders = $pdo->query("
    SELECT id, order_number, grand_total, status 
    FROM orders 
    WHERE status = 'pending' 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll();

// Fetch finance records with category names and totals
$sql = "
    SELECT fr.*, fc.name AS category_name, fc.type as category_type,
           u.name as created_by_name
    FROM finance_records fr
    LEFT JOIN finance_categories fc ON fr.category_id = fc.id
    LEFT JOIN users u ON fr.created_by = u.id
    ORDER BY fr.transaction_date DESC, fr.id DESC
    LIMIT 50
";
$records = $pdo->query($sql)->fetchAll();

// Get monthly summary
$monthlySummary = $pdo->query("
    SELECT 
        DATE_FORMAT(transaction_date, '%Y-%m') as month,
        type,
        SUM(amount) as total
    FROM finance_records 
    WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(transaction_date, '%Y-%m'), type
    ORDER BY month DESC
")->fetchAll();
?>

<div class="container mx-auto px-4 py-6">
    <h2 class="text-3xl font-bold mb-6">Finance Management</h2>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?=htmlspecialchars($error)?></div>
    <?php elseif ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?=htmlspecialchars($success)?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow border-green-300 border-l-4">
            <h3 class="text-sm font-medium text-gray-500">Total Revenue</h3>
            <p class="text-2xl font-bold text-green-600">₦<?=number_format($totalSalesRevenue, 2)?></p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow border-red-300 border-l-4">
            <h3 class="text-sm font-medium text-gray-500">Total COGS</h3>
            <p class="text-2xl font-bold text-red-600">₦<?=number_format($totalCOGS, 2)?></p>
        </div>
        <div class="bg-blue-100 p-4 rounded-lg shadow">
            <h3 class="text-sm font-medium text-blue-800">Gross Profit</h3>
            <p class="text-2xl font-bold text-blue-600">₦<?=number_format($grossProfit, 2)?></p>
        </div>
        <div class="bg-orange-100 p-4 rounded-lg shadow">
            <h3 class="text-sm font-medium text-orange-800">Operational Expenses</h3>
            <p class="text-2xl font-bold text-orange-600">₦<?=number_format($totalOperationalExpenses, 2)?></p>
        </div>
        <div class="bg-green-200 p-4 rounded-lg shadow">
            <h3 class="text-sm font-medium text-green-800">Net Profit</h3>
            <p class="text-2xl font-bold text-green-700">₦<?=number_format($netProfit, 2)?></p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-xl font-semibold mb-4">Add Manual Transaction</h3>
            <form method="post">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Type</label>
                        <select name="type" required class="w-full border border-gray-300 p-2 rounded">
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Amount</label>
                        <input name="amount" type="number" min="0" step="0.01" required placeholder="0.00" 
                               class="w-full border border-gray-300 p-2 rounded">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Category</label>
                        <select name="category_id" required class="w-full border border-gray-300 p-2 rounded">
                            <option value="">Select Category</option>
                            <optgroup label="Income Categories">
                                <?php foreach ($categories as $cat): if ($cat['type'] == 'income'): ?>
                                    <option value="<?=$cat['id']?>"><?=htmlspecialchars($cat['name'])?></option>
                                <?php endif; endforeach; ?>
                            </optgroup>
                            <optgroup label="Expense Categories">
                                <?php foreach ($categories as $cat): if ($cat['type'] == 'expense'): ?>
                                    <option value="<?=$cat['id']?>"><?=htmlspecialchars($cat['name'])?></option>
                                <?php endif; endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Date</label>
                        <input name="transaction_date" type="date" required value="<?=date('Y-m-d')?>" 
                               class="w-full border border-gray-300 p-2 rounded">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Description</label>
                    <input name="description" placeholder="Transaction description" 
                           class="w-full border border-gray-300 p-2 rounded">
                </div>
                
                <button name="add_manual" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                    Add Transaction
                </button>
            </form>
        </div>

        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-xl font-semibold mb-4">Pending Orders</h3>
            <p class="text-sm text-gray-600 mb-4">Confirming orders automatically creates finance records.</p>
            
            <?php if (empty($recentOrders)): ?>
                <p class="text-gray-500">No pending orders</p>
            <?php else: ?>
                <?php foreach ($recentOrders as $order): ?>
                <div class="flex justify-between items-center py-2 border-b last:border-b-0">
                    <div>
                        <span class="font-medium"><?=htmlspecialchars($order['order_number'])?></span>
                        <span class="text-gray-500">(₦<?=number_format($order['grand_total'], 2)?>)</span>
                    </div>
                    <form method="post" class="inline">
                        <input type="hidden" name="order_id" value="<?=$order['id']?>">
                        <button name="confirm_order" class="bg-green-600 text-white px-3 py-1 text-sm rounded hover:bg-green-700">
                            Confirm
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow mt-6">
        <div class="px-6 py-4 border-b">
            <h3 class="text-xl font-semibold">Recent Finance Records</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($records as $record): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?=date('M j, Y', strtotime($record['transaction_date']))?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?=$record['type'] == 'income' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'?>">
                                <?=ucfirst($record['type'])?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium <?=$record['type'] == 'income' ? 'text-green-600' : 'text-red-600'?>">
                            ₦<?=number_format($record['amount'], 2)?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?=htmlspecialchars($record['category_name'])?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900 max-w-xs truncate">
                            <?=htmlspecialchars($record['description'])?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <span class="inline-flex px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                <?=ucfirst(str_replace('_', ' ', $record['reference_type']))?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?=htmlspecialchars($record['created_by_name'])?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>