<?php
session_start();
require 'db.php';
require 'functions.php';
include 'header.php';

$error = '';
$success = '';

// Fetch suppliers
$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch open low-stock tasks to populate a dropdown
$openRestockTasks = $pdo->query("
    SELECT id, title
    FROM tasks
    WHERE reference_type = 'product' AND status IN ('pending', 'in_progress')
    ORDER BY priority DESC, created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Add purchase order HEADER
if (isset($_POST['add_header'])) {
    $po_number = strtoupper(uniqid('PO'));
    $supplier_id = intval($_POST['supplier_id']);
    $order_date = $_POST['order_date'];
    $status = $_POST['status'];
    $notes = trim($_POST['notes']);
    $linked_task_id = intval($_POST['linked_task_id']);
    $user_id = $_SESSION['user_id'] ?? 1;

    if ($supplier_id === 0) {
        $error = "Supplier is required.";
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO purchase_orders (po_number, supplier_id, status, order_date, notes, created_by, total_amount)
                VALUES (?, ?, ?, ?, ?, ?, 0.00)
            ");
            $stmt->execute([$po_number, $supplier_id, $status, $order_date, $notes, $user_id]);
            $po_id = $pdo->lastInsertId();

            // Check if a task was linked and update its status
            if ($linked_task_id > 0) {
                $updateTask = $pdo->prepare("
                    UPDATE tasks 
                    SET status = 'in_progress', reference_type = 'purchase_order', reference_id = ?
                    WHERE id = ? AND status != 'completed'
                ");
                $updateTask->execute([$po_id, $linked_task_id]);
            }
            
            $pdo->commit();
            // Redirect to the new PO details page to add items
            header("Location: po_details.php?id=" . $po_id);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error creating PO: " . $e->getMessage();
        }
    }
}

// Delete PO
if (isset($_GET['delete'])) {
    // Note: Deleting from purchase_orders will CASCADE delete items if the foreign key is set correctly.
    $stmt = $pdo->prepare("DELETE FROM purchase_orders WHERE id=?");
    $stmt->execute([$_GET['delete']]);
    $success = "Purchase Order deleted.";
    header('Location: purchase_orders.php');
    exit;
}

// Fetch POs with item count and totals
$sql = "
    SELECT 
        po.*, 
        s.name AS supplier_name,
        COALESCE(COUNT(poi.id), 0) AS item_count
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    LEFT JOIN purchase_order_items poi ON po.id = poi.po_id
    GROUP BY po.id
    ORDER BY po.order_date DESC
";
$orders = $pdo->query($sql)->fetchAll();
?>
<div class="container mx-auto px-4 py-6">
    <h2 class="text-3xl font-bold mb-4">Purchase Orders</h2>

    <?php if ($error): ?><div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?=htmlspecialchars($error)?></div><?php endif; ?>
    <?php if ($success): ?><div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?=htmlspecialchars($success)?></div><?php endif; ?>

    <div class="bg-white p-4 rounded-lg shadow mb-6">
        <h3 class="text-xl font-semibold mb-4">Create New Purchase Order</h3>
        <form method="post" class="flex flex-wrap gap-2 items-end">
            <select name="supplier_id" required class="border p-2 rounded w-40">
                <option value="0">Select Supplier *</option>
                <?php foreach ($suppliers as $sup): ?>
                    <option value="<?=$sup['id']?>"><?=htmlspecialchars($sup['name'])?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="order_date" required value="<?=date('Y-m-d')?>" class="border p-2 rounded w-32">
            <select name="status" class="border p-2 rounded w-40">
                <option value="draft">Draft</option>
                <option value="sent">Sent</option>
                <option value="received">Received</option>
                <option value="cancelled">Cancelled</option>
            </select>
            
            <select name="linked_task_id" class="border p-2 rounded flex-1 min-w-[200px]">
                <option value="0">Link to Restock Task (Optional)</option>
                <?php foreach ($openRestockTasks as $task): ?>
                    <option value="<?=$task['id']?>"><?=htmlspecialchars($task['title'])?></option>
                <?php endforeach; ?>
            </select>
            
            <input name="notes" placeholder="Notes (Optional)" class="border p-2 rounded flex-1 min-w-[120px]">
            <button name="add_header" class="bg-blue-600 text-white px-4 py-2 rounded">Create PO</button>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full bg-white shadow rounded">
            <thead class="bg-gray-200">
                <tr>
                    <th class="p-2 text-left">PO Number</th>
                    <th class="p-2 text-left">Supplier</th>
                    <th class="p-2 text-left">Status</th>
                    <th class="p-2 text-right">Items</th>
                    <th class="p-2 text-right">Total Amount</th>
                    <th class="p-2 text-left">Order Date</th>
                    <th class="p-2 text-left">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $row): ?>
                <tr class="hover:bg-gray-50">
                    <td class="p-2 font-medium"><?=htmlspecialchars($row['po_number'])?></td>
                    <td class="p-2"><?=htmlspecialchars($row['supplier_name'])?></td>
                    <td class="p-2 capitalize">
                        <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full 
                            <?= match($row['status']) { 
                                'draft' => 'bg-gray-200 text-gray-800', 
                                'sent' => 'bg-blue-200 text-blue-800', 
                                'received' => 'bg-green-200 text-green-800', 
                                'cancelled' => 'bg-red-200 text-red-800',
                                default => 'bg-gray-100 text-gray-700'
                            } ?>">
                            <?=htmlspecialchars($row['status'])?>
                        </span>
                    </td>
                    <td class="p-2 text-right text-gray-600"><?= number_format($row['item_count']) ?></td>
                    <td class="p-2 text-right font-semibold">₦<?=number_format($row['total_amount'], 2)?></td>
                    <td class="p-2"><?=htmlspecialchars($row['order_date'])?></td>
                    <td class="p-2 whitespace-nowrap">
                        <a href="po_details.php?id=<?=$row['id']?>" class="text-blue-600 hover:underline mr-2">Manage Items</a>
                        <a href="purchase_orders.php?delete=<?=$row['id']?>" class="text-red-600 hover:underline" onclick="return confirm('Delete this PO?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>