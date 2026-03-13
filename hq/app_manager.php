<?php
// admin/app_manager.php
require_once 'core.php';

// Security Check
if (!isset($_SESSION['master_admin_id'])) {
    header("Location: login.php");
    exit;
}

$apps = getConfiguredApps();
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_app'])) {
        $app_id = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $_POST['app_id'])); // Clean ID
        
        if (isset($apps[$app_id])) {
            $error = "An application with this ID already exists.";
        } else {
            $apps[$app_id] = [
                'app_id' => $app_id,
                'app_name' => trim($_POST['app_name']),
                'app_type' => $_POST['app_type'], 
                'folder_path' => trim($_POST['folder_path']), // e.g., 'Red' or '../scrummy/admin'
                'db_host' => trim($_POST['db_host']),
                'db_name' => trim($_POST['db_name']),
                'db_user' => trim($_POST['db_user']),
                'db_pass' => $_POST['db_pass']
            ];
            
            // Save back to JSON
            file_put_contents(APPS_CONFIG_FILE, json_encode($apps, JSON_PRETTY_PRINT));
            $msg = "Application '{$_POST['app_name']}' configured successfully!";
        }
    }

    if (isset($_POST['delete_app'])) {
        $del_id = $_POST['delete_app_id'];
        if (isset($apps[$del_id])) {
            unset($apps[$del_id]);
            file_put_contents(APPS_CONFIG_FILE, json_encode($apps, JSON_PRETTY_PRINT));
            $msg = "Application removed from HQ directory.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>App Configurations - Asiko HQ</title>
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
            <a href="index.php" class="flex items-center gap-3 py-2 px-3 hover:bg-gray-800 text-gray-300 rounded font-bold transition">
                <span class="material-symbols-outlined text-sm">dashboard</span> Directory
            </a>
            <a href="app_manager.php" class="flex items-center gap-3 py-2 px-3 bg-blue-600 text-white rounded font-bold transition">
                <span class="material-symbols-outlined text-sm">settings_input_component</span> App Setup
            </a>
            <a href="../mailing/index.php" class="flex items-center gap-3 py-2 px-3 hover:bg-gray-800 rounded font-medium text-gray-300 transition mt-6">
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
        <header class="bg-white shadow-sm border-b px-8 py-5 sticky top-0 z-20">
            <h2 class="text-xl font-bold text-gray-800">System Setup & Connections</h2>
        </header>

        <main class="p-8">
            <?php if($msg): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 p-4 rounded-lg mb-6 font-bold flex items-center gap-2">
                    <span class="material-symbols-outlined text-green-500">check_circle</span> <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-lg mb-6 font-bold flex items-center gap-2">
                    <span class="material-symbols-outlined text-red-500">error</span> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <div class="lg:col-span-1 bg-white p-6 rounded-xl shadow-sm border border-gray-200 h-fit">
                    <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-blue-500">add_box</span> Register New System
                    </h3>
                    <form method="POST">
                        <input type="hidden" name="add_app" value="1">
                        
                        <div class="space-y-4">
                            <div>
                                <label class="text-xs font-bold text-gray-500 uppercase">App ID (No Spaces)</label>
                                <input type="text" name="app_id" required placeholder="e.g. kiosk" class="w-full border p-2 rounded bg-gray-50 outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-500 uppercase">Display Name</label>
                                <input type="text" name="app_name" required placeholder="e.g. Asiko Mall" class="w-full border p-2 rounded bg-gray-50 outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-500 uppercase">System Type</label>
                                <select name="app_type" class="w-full border p-2 rounded bg-gray-50 outline-none focus:border-blue-500">
                                    <option value="kiosk">Multi-Vendor Mall (Kiosk)</option>
                                    <option value="scrummy">Food Ordering (Scrummy)</option>
                                    <option value="erp">Asiko ERP</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-500 uppercase">Admin Folder Path</label>
                                <input type="text" name="folder_path" required placeholder="e.g. Red" class="w-full border p-2 rounded bg-gray-50 outline-none focus:border-blue-500">
                                <p class="text-[10px] text-gray-400 mt-1">The exact folder name to launch (e.g., 'Red' or '../scrummy/admin').</p>
                            </div>

                            <hr class="my-4 border-gray-100">
                            <h4 class="text-xs font-bold text-gray-800 uppercase flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">database</span> DB Credentials (For Live Stats)
                            </h4>
                            
                            <div>
                                <label class="text-[10px] font-bold text-gray-500 uppercase">DB Host</label>
                                <input type="text" name="db_host" required value="127.0.0.1" class="w-full border p-2 rounded bg-gray-50 text-sm">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-gray-500 uppercase">DB Name</label>
                                <input type="text" name="db_name" required placeholder="asiko" class="w-full border p-2 rounded bg-gray-50 text-sm">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-[10px] font-bold text-gray-500 uppercase">DB User</label>
                                    <input type="text" name="db_user" required placeholder="root" class="w-full border p-2 rounded bg-gray-50 text-sm">
                                </div>
                                <div>
                                    <label class="text-[10px] font-bold text-gray-500 uppercase">DB Pass</label>
                                    <input type="password" name="db_pass" class="w-full border p-2 rounded bg-gray-50 text-sm">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-lg mt-6 hover:bg-blue-700 transition shadow-lg">
                            Save Configuration
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-2">
                    <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-gray-500">view_list</span> Managed Systems
                    </h3>
                    
                    <?php if (empty($apps)): ?>
                        <div class="bg-yellow-50 text-yellow-800 p-8 rounded-xl border border-yellow-200 text-center">
                            <span class="material-symbols-outlined text-4xl mb-2">construction</span>
                            <p class="font-bold">No Systems Configured.</p>
                            <p class="text-sm mt-1">Register your first application on the left to populate the HQ directory.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach ($apps as $id => $app): ?>
                                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200">
                                    <div class="flex justify-between items-start mb-2">
                                        <h4 class="font-bold text-gray-900 text-lg flex items-center gap-1">
                                            <?php 
                                                if($app['app_type'] == 'kiosk') echo '🛍️';
                                                elseif($app['app_type'] == 'scrummy') echo '🍔';
                                                elseif($app['app_type'] == 'erp') echo '⚙️';
                                            ?>
                                            <?= htmlspecialchars($app['app_name']) ?>
                                        </h4>
                                    </div>
                                    <div class="text-[11px] text-gray-600 space-y-1 mb-4 font-mono bg-gray-50 p-2 rounded border">
                                        <p><strong>Path:</strong> <?= htmlspecialchars($app['folder_path']) ?></p>
                                        <p><strong>DB Name:</strong> <?= htmlspecialchars($app['db_name']) ?></p>
                                    </div>
                                    
                                    <div class="flex justify-end gap-2 border-t pt-3 mt-2">
                                        <form method="POST" onsubmit="return confirm('Remove this application configuration?');">
                                            <input type="hidden" name="delete_app_id" value="<?= $id ?>">
                                            <button type="submit" name="delete_app" class="text-xs font-bold text-red-500 hover:text-red-700 hover:bg-red-50 px-3 py-1.5 rounded transition">
                                                Delete Setup
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>
</body>
</html>