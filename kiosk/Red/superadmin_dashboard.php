<?php
// kiosk/Red/superadmin_dashboard.php
include 'header.php'; 

// Security Check: Double check if user is actually a superadmin
if ($_SESSION['admin_role'] !== 'superadmin') {
    header("Location: admin_dashboard.php");
    exit();
}

// --- SECURITY CONFIGURATION LOGIC ---
$config_file = __DIR__ . '/super_config.json';

// Create default config if it doesn't exist
if (!file_exists($config_file)) {
    $default_config = ['locked' => true, 'allowed_ip' => '127.0.0.1'];
    file_put_contents($config_file, json_encode($default_config));
}

$super_config = json_decode(file_get_contents($config_file), true);
$security_msg = '';

// Handle Security Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_security'])) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $super_config['locked'] = isset($_POST['locked']) ? true : false;
        $super_config['allowed_ip'] = sanitize(trim($_POST['allowed_ip']));
        
        if (file_put_contents($config_file, json_encode($super_config))) {
            $security_msg = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4 font-bold'>Security settings updated successfully.</div>";
        } else {
            $security_msg = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4 font-bold'>Failed to save settings. Check file permissions.</div>";
        }
    } else {
        $security_msg = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4 font-bold'>Session expired. Please try again.</div>";
    }
}
$csrf_token = generateCsrfToken();
// ------------------------------------

// Fetch Global Statistics
try {
    $stats_stmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM users) as total_users,
            (SELECT COUNT(*) FROM products) as total_products,
            (SELECT COUNT(*) FROM orders) as total_orders,
            (SELECT SUM(total_amount + delivery_fee) FROM orders WHERE status = 'active') as total_revenue,
            (SELECT COUNT(*) FROM admins) as total_admins
    ");
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    printError("Error fetching stats: " . $e->getMessage());
}

// Fetch GLOBAL Activities (All Admins)
try {
    $act_sql = "SELECT a.*, ad.username, ad.role 
                FROM admin_activities a 
                JOIN admins ad ON a.admin_id = ad.id 
                ORDER BY a.created_at DESC LIMIT 20";
    $recent_activities = $pdo->query($act_sql)->fetchAll();
} catch (Exception $e) {
    $recent_activities = [];
}
?>

<div class="flex justify-between items-center mb-8">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Super Admin Overview</h1>
        <p class="text-gray-500 text-sm">Welcome back, Boss.</p>
    </div>
    <span class="bg-purple-600 text-white px-4 py-2 rounded-full text-sm font-bold shadow">
        SUPERADMIN MODE
    </span>
</div>

<?= $security_msg ?>

<div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-6 mb-8">
    <div class="bg-white p-6 rounded-lg shadow border-t-4 border-purple-600">
        <h3 class="text-xs font-bold text-gray-400 uppercase">Total Revenue</h3>
        <p class="text-2xl font-bold text-gray-800">₦<?= number_format($stats['total_revenue'] ?? 0, 2) ?></p>
    </div>
    <div class="bg-white p-6 rounded-lg shadow border-t-4 border-purple-600">
        <h3 class="text-xs font-bold text-gray-400 uppercase">Total Orders</h3>
        <p class="text-2xl font-bold text-gray-800"><?= $stats['total_orders'] ?? 0 ?></p>
    </div>
    <div class="bg-white p-6 rounded-lg shadow border-t-4 border-purple-600">
        <h3 class="text-xs font-bold text-gray-400 uppercase">Products</h3>
        <p class="text-2xl font-bold text-gray-800"><?= $stats['total_products'] ?? 0 ?></p>
    </div>
    <div class="bg-white p-6 rounded-lg shadow border-t-4 border-purple-600">
        <h3 class="text-xs font-bold text-gray-400 uppercase">Customers</h3>
        <p class="text-2xl font-bold text-gray-800"><?= $stats['total_users'] ?? 0 ?></p>
    </div>
    <div class="bg-white p-6 rounded-lg shadow border-t-4 border-pink-500 bg-pink-50">
        <h3 class="text-xs font-bold text-pink-700 uppercase">Admin Staff</h3>
        <p class="text-2xl font-bold text-pink-700"><?= $stats['total_admins'] ?? 0 ?></p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
    <div class="bg-white rounded-xl shadow-lg p-6 border border-red-100">
        <h3 class="font-bold text-gray-800 text-lg mb-4 flex items-center gap-2">
            🔒 System Security
        </h3>
        <p class="text-xs text-gray-500 mb-4">Manage access to the <code class="bg-gray-100 px-1 rounded">super_register.php</code> file.</p>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="update_security" value="1">
            
            <label class="flex items-center cursor-pointer bg-gray-50 p-3 rounded border">
                <input type="checkbox" name="locked" class="form-checkbox h-5 w-5 text-purple-600" <?= $super_config['locked'] ? 'checked' : '' ?>>
                <span class="ml-3 text-sm font-bold text-gray-700">Lock Super Registration</span>
            </label>
            
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Whitelisted IP Address</label>
                <input type="text" name="allowed_ip" value="<?= htmlspecialchars($super_config['allowed_ip']) ?>" class="w-full border p-2 rounded text-sm focus:ring-purple-500 focus:border-purple-500" placeholder="e.g. 192.168.1.1">
                <p class="text-[10px] text-gray-400 mt-1">Your current IP: <strong><?= $_SERVER['REMOTE_ADDR'] ?></strong></p>
            </div>
            
            <button type="submit" class="w-full bg-gray-800 hover:bg-black text-white font-bold py-2 rounded transition shadow">
                Save Security Settings
            </button>
        </form>
    </div>

    <div class="lg:col-span-2 bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
            <h3 class="font-bold text-gray-800">🌍 Global System Activity Log</h3>
            <span class="text-xs text-gray-500">Real-time tracking of all staff</span>
        </div>
        <div class="overflow-x-auto h-80 overflow-y-auto">
            <table class="w-full text-left text-sm text-gray-600">
                <thead class="bg-gray-100 text-gray-600 uppercase font-bold text-xs sticky top-0">
                    <tr>
                        <th class="px-6 py-3">Staff Member</th>
                        <th class="px-6 py-3">Action</th>
                        <th class="px-6 py-3">Details</th>
                        <th class="px-6 py-3">Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (count($recent_activities) > 0): ?>
                        <?php foreach ($recent_activities as $activity): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-3 font-medium text-gray-900">
                                <?= htmlspecialchars($activity['username']) ?>
                                <span class="ml-2 px-2 py-0.5 rounded text-[10px] uppercase font-bold <?= $activity['role'] === 'superadmin' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' ?>">
                                    <?= htmlspecialchars($activity['role']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-3">
                                <span class="px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                    <?= htmlspecialchars($activity['action']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-3 text-gray-500"><?= htmlspecialchars($activity['details']) ?></td>
                            <td class="px-6 py-3 text-gray-400 text-xs font-mono">
                                <?= date('M j, H:i', strtotime($activity['created_at'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="px-6 py-8 text-center text-gray-400">No activities recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php echo "</main></div></body></html>"; ?>