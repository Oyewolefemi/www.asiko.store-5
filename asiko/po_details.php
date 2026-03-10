<?php
session_start();
require 'db.php';
require 'functions.php';
include 'header.php';

$poId = intval($_GET['id'] ?? 0);

if ($poId === 0) {
    header('Location: purchase_orders.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT po.*, s.name as supplier_name 
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    WHERE po.id = ?
");
$stmt->execute([$poId]);
$po = $stmt->fetch();

if (!$po) {
    header('Location: purchase_orders.php');
    exit;
}

// Fetch existing items for this PO
$items = $pdo->prepare("
    SELECT poi.*, p.name as product_name, p.unit
    FROM purchase_order_items poi
    JOIN products p ON poi.product_id = p.id
    WHERE poi.po_id = ?
");
$items->execute([$poId]);
$poItems = $items->fetchAll();

$products = $pdo->query("SELECT id, name, unit FROM products ORDER BY name")->fetchAll();

?>

<div class="container mx-auto px-4 py-6">
    <h2 class="text-3xl font-bold mb-4">Manage Purchase Order #<?= htmlspecialchars($po['po_number']) ?></h2>
    
    <div class="bg-white p-6 rounded-lg shadow mb-6">
        <h3 class="text-xl font-semibold mb-4">Order Summary</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div><span class="font-medium">Supplier:</span> <?= htmlspecialchars($po['supplier_name']) ?></div>
            <div><span class="font-medium">Order Date:</span> <?= htmlspecialchars($po['order_date']) ?></div>
            <div><span class="font-medium">Status:</span> <span class="capitalize"><?= htmlspecialchars($po['status']) ?></span></div>
            <div><span class="font-medium">Total:</span> ₦<?= number_format($po['total_amount'], 2) ?></div>
            <div class="col-span-4"><span class="font-medium">Notes:</span> <?= htmlspecialchars($po['notes']) ?: '-' ?></div>
        </div>
    </div>
    
    <div class="bg-white p-6 rounded-lg shadow mb-6">
        <h3 class="text-xl font-semibold mb-4">Add Items</h3>
        <form method="post" action="po_details_process.php">
            <input type="hidden" name="po_id" value="<?= $poId ?>">
            <p class="text-red-500">
                Item addition/receiving logic is simulated here. This form should submit to a processing script to update the PO, inventory costs, and stock.
            </p>
            <button type="button" class="bg-gray-500 text-white px-4 py-2 rounded mt-4">Simulate Adding Item</button>
        </form>
    </div>
    
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b">
            <h3 class="text-xl font-semibold">Items in PO (<?= count($poItems) ?>)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Qty Ordered</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Qty Received</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Cost</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Cost</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($poItems)): ?>
                        <tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">No items added yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($poItems as $item): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap font-medium"><?= htmlspecialchars($item['product_name']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-right"><?= number_format($item['quantity_ordered'], 2) ?> <?= htmlspecialchars($item['unit']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-right"><?= number_format($item['quantity_received'], 2) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">₦<?= number_format($item['unit_cost'], 2) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-right font-semibold">₦<?= number_format($item['total_cost'], 2) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <button class="text-purple-600 hover:underline">Receive</button> | <button class="text-red-600 hover:underline">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>