<?php
session_start();
require 'db.php';
require 'functions.php';
include 'header.php';

// Fetch orders with user name
$sql = "
    SELECT o.*, u.name AS customer_name
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    ORDER BY o.order_date DESC
";
$orders = $pdo->query($sql)->fetchAll();
?>
<h2 class="text-2xl font-bold mb-4">Customer Orders</h2>

<table class="min-w-full bg-white shadow rounded">
    <thead class="bg-gray-200">
        <tr>
            <th class="p-2">Order #</th>
            <th class="p-2">Customer</th>
            <th class="p-2">Status</th>
            <th class="p-2">Grand Total</th>
            <th class="p-2">Order Date</th>
            <th class="p-2">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($orders as $order): ?>
        <tr>
            <td class="p-2"><?=htmlspecialchars($order['order_number'])?></td>
            <td class="p-2"><?=htmlspecialchars($order['customer_name'])?></td>
            <td class="p-2 capitalize"><?=htmlspecialchars($order['status'])?></td>
            <td class="p-2">₦<?=number_format($order['grand_total'], 2)?></td>
            <td class="p-2"><?=htmlspecialchars($order['order_date'])?></td>
            <td class="p-2">
                <a href="order_details.php?id=<?=$order['id']?>" class="text-blue-600 hover:underline">View</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php include 'footer.php'; ?>
