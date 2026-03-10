<?php
require_once '../config.php';
include '../includes/admin_header.php';

// Ensure CSRF token exists for this session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Helper to validate CSRF on GET actions
function verify_get_csrf() {
    if (!isset($_GET['csrf']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf'])) {
        die("Security Check Failed: Invalid CSRF Token.");
    }
}

// Handle Toggles & Deletes (Now using Prepared Statements + CSRF Protection)
if (isset($_GET['toggle_active'])) {
    verify_get_csrf();
    $stmt = $pdo->prepare("UPDATE products SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$_GET['toggle_active']]);
    echo "<script>window.location.href='products.php';</script>";
    exit;
}

if (isset($_GET['toggle_deal'])) {
    verify_get_csrf();
    $stmt = $pdo->prepare("UPDATE products SET is_top_deal = NOT is_top_deal WHERE id = ?");
    $stmt->execute([$_GET['toggle_deal']]);
    echo "<script>window.location.href='products.php';</script>";
    exit;
}

if (isset($_GET['delete'])) {
    verify_get_csrf();
    $id = $_GET['delete'];
    $pdo->prepare("DELETE FROM product_ingredients WHERE product_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
    echo "<script>window.location.href='products.php';</script>";
    exit;
}

$products = $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Menu Items</h1>
        <p class="text-sm text-gray-500">Manage products shown to customers</p>
    </div>
    <a href="product_form.php" class="bg-gray-900 text-white px-4 py-2 rounded-lg font-bold text-sm hover:bg-black transition flex items-center gap-2">
        <span class="material-symbols-outlined text-sm">add</span> Add New
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-left border-collapse">
        <thead class="bg-gray-50 text-xs font-bold text-gray-500 uppercase border-b border-gray-100">
            <tr>
                <th class="p-4">Item</th>
                <th class="p-4">Price</th>
                <th class="p-4 text-center">Top Deal</th>
                <th class="p-4 text-center">Active</th>
                <th class="p-4 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 text-sm">
            <?php foreach($products as $p): ?>
            <tr class="hover:bg-gray-50/50 group">
                <td class="p-4 flex items-center gap-3">
                    <img src="../<?= htmlspecialchars($p['image_url']) ?>" class="size-10 rounded-lg object-cover bg-gray-100">
                    <div>
                        <span class="font-bold text-gray-900 block"><?= htmlspecialchars($p['name']) ?></span>
                        <?php if($p['sale_price']): ?>
                            <span class="text-[10px] text-red-500 font-bold">ON SALE</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="p-4 font-mono">
                    <?php if($p['sale_price']): ?>
                        <span class="line-through text-gray-400 text-xs">₦<?= number_format($p['price']) ?></span><br>
                        <span class="text-red-600 font-bold">₦<?= number_format($p['sale_price']) ?></span>
                    <?php else: ?>
                        ₦<?= number_format($p['price']) ?>
                    <?php endif; ?>
                </td>
                <td class="p-4 text-center">
                    <a href="?toggle_deal=<?= $p['id'] ?>&csrf=<?= $csrf_token ?>" class="material-symbols-outlined <?= $p['is_top_deal'] ? 'text-orange-500' : 'text-gray-200' ?> hover:scale-110 transition">star</a>
                </td>
                <td class="p-4 text-center">
                    <a href="?toggle_active=<?= $p['id'] ?>&csrf=<?= $csrf_token ?>" class="text-xs font-bold px-2 py-1 rounded <?= $p['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                        <?= $p['is_active'] ? 'Active' : 'Hidden' ?>
                    </a>
                </td>
                <td class="p-4 text-right">
                    <a href="product_form.php?id=<?= $p['id'] ?>" class="text-blue-600 font-bold hover:underline">Edit</a>
                    <a href="?delete=<?= $p['id'] ?>&csrf=<?= $csrf_token ?>" onclick="return confirm('Delete this item?')" class="text-red-500 font-bold ml-3 hover:underline">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</main></div></body></html>