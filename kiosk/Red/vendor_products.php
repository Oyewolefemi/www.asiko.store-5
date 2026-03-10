<?php
// dns/kiosk/Red/vendor_products.php
include 'header.php';

$sql = "SELECT p.*, a.store_name, a.username 
        FROM products p 
        LEFT JOIN admins a ON p.admin_id = a.id 
        WHERE p.admin_id != ? 
        ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['admin_id']]);
$others_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Other Vendors' Products</h1>
    <p class="text-gray-500">Browse inventory from other stores on the platform.</p>
</div>

<div class="bg-white shadow rounded-lg overflow-hidden">
    <table class="w-full text-left">
        <thead class="bg-gray-100 border-b text-gray-600 uppercase text-xs">
            <tr>
                <th class="p-4">Product</th>
                <th class="p-4">Vendor / Store</th>
                <th class="p-4">Category</th>
                <th class="p-4">Price</th>
                <th class="p-4">Stock</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php if (count($others_products) > 0): ?>
                <?php foreach ($others_products as $p): ?>
                <tr class="hover:bg-gray-50 transition">
                    <td class="p-4">
                        <div class="flex items-center gap-3">
                            <?php if (!empty($p['image_path'])): ?>
                                <img src="<?= htmlspecialchars($p['image_path']) ?>" class="w-10 h-10 object-cover rounded border">
                            <?php else: ?>
                                <div class="w-10 h-10 bg-gray-200 rounded flex items-center justify-center text-xs">N/A</div>
                            <?php endif; ?>
                            <div>
                                <div class="font-bold text-gray-800"><?= htmlspecialchars($p['name']) ?></div>
                                </div>
                        </div>
                    </td>
                    <td class="p-4">
                        <?php if ($p['store_name']): ?>
                            <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded font-bold">
                                <?= htmlspecialchars($p['store_name']) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-gray-500 text-sm">@<?= htmlspecialchars($p['username'] ?? 'Unknown') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="p-4 text-sm text-gray-600"><?= htmlspecialchars($p['category']) ?></td>
                    <td class="p-4 font-bold text-gray-800">₦<?= number_format($p['price'], 2) ?></td>
                    <td class="p-4">
                        <span class="px-2 py-1 rounded text-xs font-bold <?= $p['stock_quantity'] < 5 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
                            <?= $p['stock_quantity'] ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="p-8 text-center text-gray-500">
                        No products found from other vendors.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php echo "</main></div></body></html>"; ?>