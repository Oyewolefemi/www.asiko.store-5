<?php
require_once '../../config.php';
include '../../includes/admin_header.php';

// Fetch Logs
$logs = $pdo->query("
    SELECT l.*, i.name as item_name, i.unit, a.username as admin_name 
    FROM inventory_logs l 
    JOIN inventory i ON l.inventory_id = i.id 
    JOIN admins a ON l.admin_id = a.id 
    ORDER BY l.created_at DESC LIMIT 100
")->fetchAll();
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Inventory Logs</h1>
    <p class="text-sm text-gray-500">Recent stock movements</p>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-left">
        <thead class="bg-gray-50 text-xs font-bold text-gray-500 uppercase border-b">
            <tr>
                <th class="p-4">Date</th>
                <th class="p-4">Item</th>
                <th class="p-4">Action</th>
                <th class="p-4">Change</th>
                <th class="p-4">By</th>
                <th class="p-4">Note</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 text-sm">
            <?php foreach($logs as $l): 
                $color = match($l['action']) {
                    'restock' => 'text-green-600 bg-green-50',
                    'deduct' => 'text-blue-600 bg-blue-50',
                    'waste' => 'text-red-600 bg-red-50',
                    default => 'text-gray-600 bg-gray-50'
                };
            ?>
            <tr class="hover:bg-gray-50">
                <td class="p-4 text-gray-500 font-mono text-xs"><?= $l['created_at'] ?></td>
                <td class="p-4 font-bold"><?= htmlspecialchars($l['item_name']) ?></td>
                <td class="p-4">
                    <span class="px-2 py-1 rounded text-xs font-bold uppercase <?= $color ?>">
                        <?= $l['action'] ?>
                    </span>
                </td>
                <td class="p-4 font-mono font-bold <?= $l['qty_change'] > 0 ? 'text-green-600' : 'text-red-600' ?>">
                    <?= $l['qty_change'] > 0 ? '+' : '' ?><?= $l['qty_change'] ?> <?= $l['unit'] ?>
                </td>
                <td class="p-4 text-gray-500"><?= htmlspecialchars($l['admin_name']) ?></td>
                <td class="p-4 text-gray-400 italic"><?= htmlspecialchars($l['note']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body></html>