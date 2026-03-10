<?php
// FIX: Use '../' because we are in /admin/, not /admin/inventory/
require_once '../config.php'; 

// Include the new Admin Header (Sidebar system)
include '../includes/admin_header.php';

// Stats Logic
$pending = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$active = $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('processing','en_route')")->fetchColumn();
$revenue = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status = 'completed'")->fetchColumn();

// Low Stock Alert (New Feature from Asiko Logic)
// Check if inventory table exists first to avoid errors during migration
try {
    $lowStock = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity <= min_level")->fetchColumn();
} catch (Exception $e) {
    $lowStock = 0; // Default to 0 if table missing
}

// Recent Orders
$orders = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
    <p class="text-gray-500">Welcome back, <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></p>
</div>

<?php if (isset($_SESSION['env_status']) && $_SESSION['env_status'] === 'vulnerable'): ?>
    <div class="bg-red-50 border-l-4 border-red-600 p-4 mb-8 rounded-r-xl shadow-sm flex items-start gap-3">
        <span class="material-symbols-outlined text-red-600 mt-0.5">gpp_maybe</span>
        <div>
            <h3 class="font-bold text-red-800 text-sm">Security Risk Detected</h3>
            <p class="text-xs text-red-600 mt-1">
                Your <strong>.env</strong> file is currently located in the public web directory. 
                For maximum security, please move the `.env` file one directory level up (outside of your public folder).
            </p>
        </div>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between">
        <div>
            <div class="text-gray-500 text-xs font-bold uppercase mb-1">Pending Orders</div>
            <div class="text-3xl font-bold text-orange-600"><?= $pending ?></div>
        </div>
        <div class="size-12 rounded-full bg-orange-50 flex items-center justify-center text-orange-600">
            <span class="material-symbols-outlined">notifications_active</span>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between">
        <div>
            <div class="text-gray-500 text-xs font-bold uppercase mb-1">Kitchen Active</div>
            <div class="text-3xl font-bold text-blue-600"><?= $active ?></div>
        </div>
        <div class="size-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-600">
            <span class="material-symbols-outlined">soup_kitchen</span>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between">
        <div>
            <div class="text-gray-500 text-xs font-bold uppercase mb-1">Total Revenue</div>
            <div class="text-3xl font-bold text-green-600">₦<?= number_format($revenue ?? 0) ?></div>
        </div>
        <div class="size-12 rounded-full bg-green-50 flex items-center justify-center text-green-600">
            <span class="material-symbols-outlined">payments</span>
        </div>
    </div>

    <a href="inventory/index.php" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between group hover:border-red-200 transition">
        <div>
            <div class="text-gray-500 text-xs font-bold uppercase mb-1">Low Stock Items</div>
            <div class="text-3xl font-bold <?= $lowStock > 0 ? 'text-red-600' : 'text-gray-800' ?>"><?= $lowStock ?></div>
        </div>
        <div class="size-12 rounded-full bg-red-50 flex items-center justify-center text-red-600 group-hover:scale-110 transition">
            <span class="material-symbols-outlined">warning</span>
        </div>
    </a>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="p-6 border-b border-gray-100 flex justify-between items-center">
        <h3 class="font-bold text-lg">Recent Orders</h3>
        <a href="orders.php" class="text-sm font-bold text-primary hover:underline">View All</a>
    </div>
    <div class="divide-y divide-gray-100">
        <?php foreach($orders as $o): ?>
        <div class="p-4 flex justify-between items-center hover:bg-gray-50 transition">
            <div class="flex items-center gap-4">
                <div class="size-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 font-bold text-xs">
                    #<?= $o['id'] ?>
                </div>
                <div>
                    <div class="font-bold text-gray-900">Order #<?= $o['id'] ?></div>
                    <div class="text-xs text-gray-500"><?= $o['created_at'] ?></div>
                </div>
            </div>
            <div class="text-right">
                <div class="font-bold text-gray-900">₦<?= number_format($o['total_amount']) ?></div>
                <span class="inline-block px-2 py-0.5 rounded text-[10px] uppercase font-bold bg-gray-100 text-gray-600 mt-1"><?= $o['status'] ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

</body>
</html>