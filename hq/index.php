<?php
// admin/index.php
require_once 'core.php';

// Security Check
if (!isset($_SESSION['master_admin_id'])) {
    header("Location: login.php");
    exit;
}

$apps = getConfiguredApps();

// Function to peek into an app's database and fetch stats
function fetchAppStats($app) {
    $stats = ['orders' => 0, 'revenue' => 0, 'users' => 0, 'status' => 'Online', 'error' => ''];
    $db = getDbCredentialsForAppId($app['app_id']);

    if (empty($db['name']) || empty($db['user'])) {
        $stats['status'] = 'Offline';
        $stats['error'] = 'Missing environment credentials.';
        return $stats;
    }

    try {
        $pdo = new PDO(
            "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4",
            $db['user'],
            $db['pass']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Dynamically fetch stats based on the expected tables for each app type
        if ($app['app_type'] === 'kiosk') {
            $stats['orders'] = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn() ?: 0;
            $stats['revenue'] = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status='active'")->fetchColumn() ?: 0;
            $stats['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 0;
            
        } elseif ($app['app_type'] === 'scrummy') {
            $stats['orders'] = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn() ?: 0;
            $stats['revenue'] = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status='delivered'")->fetchColumn() ?: 0;
            $stats['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 0;
            
        } elseif ($app['app_type'] === 'erp') {
            // Adjust to your exact ERP tables if different
            $stats['orders'] = $pdo->query("SELECT COUNT(*) FROM purchase_orders")->fetchColumn() ?: 0;
            $stats['revenue'] = $pdo->query("SELECT SUM(total_amount) FROM sales")->fetchColumn() ?: 0;
            $stats['users'] = $pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn() ?: 0;
        }
    } catch (PDOException $e) {
        $stats['status'] = 'Offline';
        $stats['error'] = 'Connection failed or tables missing.';
    }
    
    return $stats;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HQ Directory - Asiko Master</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
</head>
<body class="bg-gray-100 font-sans flex min-h-screen">

    <div class="w-64 bg-gray-900 text-white flex flex-col shadow-2xl relative z-10 shrink-0">
        <div class="p-6 border-b border-gray-800 text-center">
            <h1 class="text-2xl font-black tracking-wider text-white">ASIKO <span class="text-blue-500">HQ</span></h1>
            <p class="text-[10px] text-gray-400 mt-1 uppercase tracking-widest">Master Launchpad</p>
        </div>

        <nav class="flex-1 p-4 space-y-2 mt-4">
            <a href="index.php" class="flex items-center gap-3 py-2 px-3 bg-blue-600 text-white rounded font-bold transition">
                <span class="material-symbols-outlined text-sm">dashboard</span> Directory
            </a>
            <a href="app_manager.php" class="flex items-center gap-3 py-2 px-3 hover:bg-gray-800 text-gray-300 rounded font-medium transition">
                <span class="material-symbols-outlined text-sm">settings_input_component</span> App Setup
            </a>
            <a href="../mailing/index.php" class="flex items-center gap-3 py-2 px-3 hover:bg-gray-800 text-gray-300 rounded font-medium transition mt-6">
                <span class="material-symbols-outlined text-sm">mail</span> Global Mailing
            </a>
        </nav>

        <div class="p-4 border-t border-gray-800">
            <a href="logout.php" class="flex items-center gap-3 py-2 px-3 hover:bg-red-600 rounded font-bold text-gray-400 hover:text-white transition">
                <span class="material-symbols-outlined text-sm">logout</span> Terminate Session
            </a>
        </div>
    </div>

    <div class="flex-1 flex flex-col bg-gray-50 h-screen overflow-y-auto">
        <header class="bg-white shadow-sm border-b px-8 py-5 sticky top-0 z-20 flex justify-between items-center">
            <div>
                <h2 class="text-xl font-bold text-gray-800">Active Directory</h2>
                <p class="text-xs text-gray-500 mt-1">Select a system to manage its dedicated environment.</p>
            </div>
            <div class="text-sm font-bold text-gray-600 flex items-center gap-2">
                <span class="material-symbols-outlined text-gray-400">shield_person</span>
                <?= htmlspecialchars($_SESSION['master_admin_user']) ?>
            </div>
        </header>

        <main class="p-8">
            <?php if (empty($apps)): ?>
                <div class="bg-white p-12 rounded-xl shadow-sm border border-gray-200 text-center max-w-2xl mx-auto mt-10">
                    <span class="material-symbols-outlined text-6xl text-gray-300 mb-4">apps</span>
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">Directory is Empty</h2>
                    <p class="text-gray-500 mb-8">You have not configured any applications to manage yet.</p>
                    <a href="app_manager.php" class="bg-gray-900 text-white font-bold py-3 px-8 rounded-lg shadow hover:bg-black transition">
                        Setup First Application
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php foreach ($apps as $id => $app): 
                        // Peak into the DB for stats
                        $stats = fetchAppStats($app);
                    ?>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col hover:shadow-lg transition duration-200">
                            
                            <div class="p-5 border-b border-gray-100 flex justify-between items-start bg-gray-50">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center shrink-0">
                                        <span class="material-symbols-outlined">
                                            <?php 
                                                if($app['app_type'] == 'kiosk') echo 'storefront';
                                                elseif($app['app_type'] == 'scrummy') echo 'restaurant';
                                                elseif($app['app_type'] == 'erp') echo 'precision_manufacturing';
                                                else echo 'widgets';
                                            ?>
                                        </span>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-gray-900 text-lg leading-tight"><?= htmlspecialchars($app['app_name']) ?></h3>
                                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest"><?= htmlspecialchars($app['app_type']) ?> System</p>
                                    </div>
                                </div>
                                <?php if ($stats['status'] === 'Online'): ?>
                                    <span class="flex w-3 h-3 bg-green-500 rounded-full shadow" title="Database Online"></span>
                                <?php else: ?>
                                    <span class="flex w-3 h-3 bg-red-500 rounded-full shadow" title="Database Offline/Error"></span>
                                <?php endif; ?>
                            </div>

                            <div class="p-5 flex-1">
                                <?php if ($stats['status'] === 'Online'): ?>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Total Revenue</p>
                                            <p class="text-xl font-black text-gray-800">₦<?= number_format($stats['revenue']) ?></p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Total Orders</p>
                                            <p class="text-xl font-black text-gray-800"><?= number_format($stats['orders']) ?></p>
                                        </div>
                                        <div class="col-span-2 border-t pt-3 mt-1">
                                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Registered Users / Items</p>
                                            <p class="text-lg font-black text-gray-800"><?= number_format($stats['users']) ?></p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="bg-red-50 p-4 rounded border border-red-100 h-full flex items-center justify-center text-center">
                                        <div>
                                            <span class="material-symbols-outlined text-red-300 text-3xl mb-1">database</span>
                                            <p class="text-xs text-red-600 font-bold">Live Stats Unavailable</p>
                                            <p class="text-[10px] text-red-400 mt-1"><?= htmlspecialchars($stats['error']) ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="p-5 border-t border-gray-100 bg-white grid grid-cols-2 gap-3">
                                <form method="POST" class="w-full">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['hq_csrf_token']) ?>">
                                    <button type="submit" name="switch_app" value="<?= htmlspecialchars($id) ?>" class="w-full text-center py-2.5 px-3 border border-gray-300 text-gray-600 font-bold text-xs rounded hover:bg-gray-50 transition">
                                        Set Active
                                    </button>
                                </form>
                                <a href="<?= htmlspecialchars($app['folder_path']) ?>" class="text-center py-2.5 px-3 bg-blue-600 text-white font-bold text-xs rounded hover:bg-blue-700 transition flex items-center justify-center gap-1 shadow-sm">
                                    Open Dashboard <span class="material-symbols-outlined text-xs">open_in_new</span>
                                </a>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

</body>
</html>