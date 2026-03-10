<?php
require_once '../config.php';
include '../includes/admin_header.php';

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Update Status
if (isset($_POST['update_status'])) {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Security Check Failed: Invalid CSRF Token.");
    }

    $oid = $_POST['order_id'];
    $st = $_POST['status'];
    $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$st, $oid]);
    echo "<script>window.location.href='orders.php';</script>";
    exit;
}

// 1. Fetch all orders (Query 1)
$orders = $pdo->query("SELECT o.*, u.name as user_name, u.phone FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC")->fetchAll();

// 2. N+1 Fix: Fetch all related order items in ONE bulk query (Query 2)
$orderIds = array_column($orders, 'id');
$itemsByOrder = [];

if (!empty($orderIds)) {
    $placeholders = str_repeat('?,', count($orderIds) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        WHERE order_id IN ($placeholders)
    ");
    $stmt->execute($orderIds);
    $allOrderItems = $stmt->fetchAll();

    // Group items by their order_id for fast lookup
    foreach ($allOrderItems as $item) {
        $itemsByOrder[$item['order_id']][] = $item;
    }
}
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Order Management</h1>
    <p class="text-sm text-gray-500">Track and fulfill customer orders</p>
</div>

<div class="space-y-6">
    <?php foreach($orders as $o): 
        // Retrieve items from our pre-fetched array instead of querying the DB
        $itemList = $itemsByOrder[$o['id']] ?? [];
    ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-4 bg-gray-50 border-b border-gray-100 flex justify-between items-start">
            <div>
                <h3 class="font-bold text-lg text-gray-900">Order #<?= $o['id'] ?> 
                    <span class="text-sm font-normal text-gray-500">
                        by <?= htmlspecialchars($o['guest_name'] ?? $o['user_name'] ?? 'Guest') ?>
                    </span>
                </h3>
                <p class="text-xs text-gray-500"><?= htmlspecialchars($o['created_at']) ?></p>
            </div>
            <div class="text-right">
                <div class="font-bold text-lg text-primary">₦<?= number_format($o['total_amount']) ?></div>
                <span class="text-[10px] uppercase font-bold text-gray-400 bg-white px-2 py-1 rounded border border-gray-200">
                    <?= $o['payment_method'] === 'cod' ? 'Pay on Delivery' : 'Bank Transfer' ?>
                </span>
            </div>
        </div>

        <div class="p-4 grid md:grid-cols-2 gap-6">
            <div>
                <h4 class="text-xs font-bold uppercase text-gray-400 mb-2">Delivery Info</h4>
                <div class="bg-gray-50 p-3 rounded-lg text-sm border border-gray-100">
                    <p class="font-medium text-gray-800"><?= htmlspecialchars($o['delivery_address']) ?></p>
                    <p class="mt-2 text-blue-600 font-bold flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">call</span> 
                        <?= htmlspecialchars($o['guest_phone'] ?? $o['phone'] ?? 'N/A') ?>
                    </p>
                </div>
            </div>
            <div>
                <h4 class="text-xs font-bold uppercase text-gray-400 mb-2">Items Ordered</h4>
                <ul class="space-y-2 text-sm">
                    <?php foreach($itemList as $i): ?>
                    <li class="flex justify-between items-center pb-2 border-b border-gray-50 last:border-0 last:pb-0">
                        <span class="font-medium text-gray-700">
                            <span class="font-bold text-gray-900"><?= (int)$i['quantity'] ?>x</span> 
                            <?= htmlspecialchars($i['name']) ?>
                        </span>
                        <span class="text-gray-400">₦<?= number_format($i['price_at_time']) ?></span>
                    </li>
                    <?php if(!empty($i['notes'])): ?>
                        <li class="text-xs text-orange-600 italic pl-6 mt-[-4px]">"<?= htmlspecialchars($i['notes']) ?>"</li>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="p-3 bg-gray-50 flex justify-end">
            <form method="POST" class="flex items-center gap-2">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                
                <select name="status" class="bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-bold outline-none focus:border-primary cursor-pointer">
                    <option value="pending" <?= $o['status']=='pending'?'selected':'' ?>>Pending</option>
                    <option value="processing" <?= $o['status']=='processing'?'selected':'' ?>>Cooking / Processing</option>
                    <option value="en_route" <?= $o['status']=='en_route'?'selected':'' ?>>Out for Delivery</option>
                    <option value="completed" <?= $o['status']=='completed'?'selected':'' ?>>Completed</option>
                    <option value="cancelled" <?= $o['status']=='cancelled'?'selected':'' ?>>Cancelled</option>
                </select>
                <button name="update_status" class="bg-gray-900 text-white px-4 py-2 rounded-lg font-bold text-sm hover:bg-black transition">Update</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>

</main></div></body></html>