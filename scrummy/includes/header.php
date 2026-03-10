<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['admin_id'])) { 
    $loginPath = (strpos($_SERVER['SCRIPT_NAME'], '/inventory/') !== false) ? '../../admin/login.php' : 'login.php';
    header("Location: $loginPath"); 
    exit; 
}

// Determine relative path for links based on folder depth
$isInSubfolder = strpos($_SERVER['SCRIPT_NAME'], '/inventory/') !== false;
$base = $isInSubfolder ? '../' : '';

// Load Brand Color from settings if available
$brandColor = $settings['primary_color'] ?? '#ec6d13';
$headerBg = $settings['header_bg'] ?? '#ffffff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($settings['site_name'] ?? 'Asiko+') ?> - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@400;600;700&family=Material+Symbols+Outlined" rel="stylesheet"/>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { primary: '<?= $brandColor ?>', dark: '#111827' },
                    fontFamily: { sans: ['Work Sans'] }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 font-sans text-gray-900 flex min-h-screen">

    <aside class="hidden lg:flex flex-col w-64 bg-dark text-white fixed h-full z-50 shadow-xl">
        <div class="h-16 flex items-center px-6 border-b border-gray-800">
            <?php if(!empty($settings['logo_url'])): ?>
                <img src="<?= $isInSubfolder ? '../../' : '../' ?><?= htmlspecialchars($settings['logo_url']) ?>" class="h-8 max-w-full object-contain">
            <?php else: ?>
                <span class="text-xl font-bold tracking-wider">Asiko<span class="text-primary">+</span></span>
            <?php endif; ?>
        </div>
        
        <nav class="flex-1 py-6 space-y-1 px-3 overflow-y-auto">
            <div class="px-3 mb-2 text-xs font-bold text-gray-500 uppercase tracking-wider">Overview</div>
            <a href="<?= $base ?>index.php" class="flex items-center px-3 py-2.5 rounded-xl hover:bg-gray-800 transition">
                <span class="material-symbols-outlined mr-3 text-gray-400">dashboard</span> Dashboard
            </a>
            <a href="<?= $base ?>orders.php" class="flex items-center px-3 py-2.5 rounded-xl hover:bg-gray-800 transition">
                <span class="material-symbols-outlined mr-3 text-gray-400">receipt_long</span> Orders
            </a>

            <div class="px-3 mt-6 mb-2 text-xs font-bold text-gray-500 uppercase tracking-wider">Management</div>
            <a href="<?= $base ?>products.php" class="flex items-center px-3 py-2.5 rounded-xl hover:bg-gray-800 transition">
                <span class="material-symbols-outlined mr-3 text-gray-400">restaurant_menu</span> Menu Items
            </a>
            <a href="<?= $base ?>inventory/index.php" class="flex items-center px-3 py-2.5 rounded-xl hover:bg-gray-800 transition">
                <span class="material-symbols-outlined mr-3 text-gray-400">inventory_2</span> Supplies
            </a>

            <div class="px-3 mt-6 mb-2 text-xs font-bold text-gray-500 uppercase tracking-wider">System</div>
            <a href="<?= $base ?>settings.php" class="flex items-center px-3 py-2.5 rounded-xl hover:bg-gray-800 transition">
                <span class="material-symbols-outlined mr-3 text-gray-400">tune</span> Settings
            </a>
            <a href="<?= $base ?>staff.php" class="flex items-center px-3 py-2.5 rounded-xl hover:bg-gray-800 transition">
                <span class="material-symbols-outlined mr-3 text-gray-400">badge</span> Staff Accounts
            </a>
        </nav>

        <div class="p-4 border-t border-gray-800 bg-gray-900">
            <div class="flex items-center justify-between px-2">
                <div class="text-xs">
                    <p class="text-gray-400 uppercase font-bold tracking-wider mb-0.5">Logged In As</p>
                    <p class="text-white font-bold truncate max-w-[120px]"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></p>
                </div>
                <a href="<?= $base ?>logout.php" class="flex items-center justify-center size-10 rounded-xl bg-red-500/10 text-red-400 hover:bg-red-500 hover:text-white transition group" title="Logout">
                    <span class="material-symbols-outlined text-[20px] group-hover:translate-x-0.5 transition-transform">logout</span>
                </a>
            </div>
        </div>
    </aside>

    <header class="lg:hidden bg-[<?= $headerBg ?>] h-16 fixed top-0 w-full z-40 border-b border-gray-200 flex items-center justify-between px-4 shadow-sm">
        <span class="text-xl font-bold tracking-wider text-gray-900">Asiko<span class="text-primary">+</span></span>
        <button class="p-2 bg-gray-100 rounded-lg text-gray-600">
            <span class="material-symbols-outlined">menu</span>
        </button>
    </header>

    <div class="flex-1 lg:ml-64 flex flex-col min-h-screen pt-16 lg:pt-0 bg-gray-50/50">
        <main class="p-4 lg:p-8 max-w-7xl mx-auto w-full">