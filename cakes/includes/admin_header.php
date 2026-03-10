<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Prevent Browser Caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$isInSubfolder = strpos($_SERVER['SCRIPT_NAME'], '/inventory/') !== false;
$pathToAdmin = $isInSubfolder ? '../' : ''; 

if (!isset($_SESSION['admin_id'])) { 
    header("Location: " . $pathToAdmin . "login.php"); 
    exit; 
}

$root = "/scrummy 6"; 

// Defaults
$brandColor  = $settings['primary_color'] ?? '#ec6d13';
$headerColor = $settings['header_bg'] ?? '#ffffff';
$bodyColor   = $settings['body_bg'] ?? '#f9fafb';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asiko+ Admin</title>
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
    <style>
        body { background-color: <?= $bodyColor ?> !important; }
        /* Only apply header color to the top navbar, not the sidebar */
        .admin-top-bar { background-color: <?= $headerColor ?> !important; }
    </style>
</head>
<body class="font-sans text-gray-900 flex min-h-screen">

    <aside class="hidden lg:flex flex-col w-64 bg-dark text-white fixed h-full z-50">
        <div class="h-16 flex items-center px-6 border-b border-gray-800">
            <span class="text-xl font-bold tracking-wider">Asiko<span class="text-primary">+</span></span>
        </div>
        
        <nav class="flex-1 py-6 space-y-1 px-3">
            <div class="px-3 mb-2 text-xs font-bold text-gray-500 uppercase tracking-wider">Business</div>
            <a href="<?= $pathToAdmin ?>index.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-gray-800 transition group">
                <span class="material-symbols-outlined mr-3 text-gray-400 group-hover:text-white">dashboard</span> Dashboard
            </a>
            <a href="<?= $pathToAdmin ?>orders.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-gray-800 transition group">
                <span class="material-symbols-outlined mr-3 text-gray-400 group-hover:text-white">receipt_long</span> Orders
            </a>

            <div class="px-3 mt-6 mb-2 text-xs font-bold text-gray-500 uppercase tracking-wider">Management</div>
            <a href="<?= $pathToAdmin ?>products.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-gray-800 transition group">
                <span class="material-symbols-outlined mr-3 text-gray-400 group-hover:text-white">restaurant_menu</span> Menu Items
            </a>
            <a href="<?= $pathToAdmin ?>inventory/index.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-gray-800 transition group">
                <span class="material-symbols-outlined mr-3 text-gray-400 group-hover:text-white">inventory_2</span> Supplies
            </a>

            <div class="px-3 mt-6 mb-2 text-xs font-bold text-gray-500 uppercase tracking-wider">System</div>
            <a href="<?= $pathToAdmin ?>settings.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-gray-800 transition group">
                <span class="material-symbols-outlined mr-3 text-gray-400 group-hover:text-white">settings</span> Settings
            </a>
        </nav>

        <div class="p-4 border-t border-gray-800">
            <a href="<?= $pathToAdmin ?>logout.php" class="flex items-center text-red-400 hover:text-red-300 text-sm font-bold">
                <span class="material-symbols-outlined mr-2 text-lg">logout</span> Logout
            </a>
        </div>
    </aside>

    <div class="lg:hidden fixed top-0 w-full bg-dark text-white z-50 h-16 flex items-center justify-between px-4 shadow-md">
        <span class="font-bold text-lg">Asiko<span class="text-primary">+</span></span>
        <button onclick="document.getElementById('mob-menu').classList.toggle('hidden')" class="p-2 rounded hover:bg-gray-800">
            <span class="material-symbols-outlined">menu</span>
        </button>
    </div>

    <div id="mob-menu" class="hidden fixed inset-0 z-40 bg-gray-900/95 backdrop-blur-sm lg:hidden pt-20 px-6 space-y-4">
        <a href="<?= $pathToAdmin ?>index.php" class="block text-white text-lg font-bold border-b border-gray-700 pb-2">Dashboard</a>
        <a href="<?= $pathToAdmin ?>orders.php" class="block text-white text-lg font-bold border-b border-gray-700 pb-2">Orders</a>
        <a href="<?= $pathToAdmin ?>products.php" class="block text-white text-lg font-bold border-b border-gray-700 pb-2">Menu</a>
        <a href="<?= $pathToAdmin ?>inventory/index.php" class="block text-white text-lg font-bold border-b border-gray-700 pb-2">Supplies</a>
        <a href="<?= $pathToAdmin ?>settings.php" class="block text-white text-lg font-bold border-b border-gray-700 pb-2">Settings</a>
        <a href="<?= $pathToAdmin ?>logout.php" class="block text-red-500 text-lg font-bold pt-4">Logout</a>
        <button onclick="document.getElementById('mob-menu').classList.add('hidden')" class="absolute top-4 right-4 text-white p-2 bg-gray-800 rounded-full">
            <span class="material-symbols-outlined">close</span>
        </button>
    </div>

    <div class="flex-1 lg:ml-64 flex flex-col min-h-screen pt-16 lg:pt-0">
        <header class="admin-top-bar hidden lg:flex h-16 shadow-sm border-b border-gray-100 items-center justify-between px-8 sticky top-0 z-30 transition-colors">
            <h2 class="font-bold text-gray-700 capitalize text-lg tracking-tight">
                <?= str_replace(['.php', '_', '-'], ['',' ', ' '], basename($_SERVER['PHP_SELF'])) ?>
            </h2>
            <div class="flex items-center gap-3">
                <div class="text-right">
                    <div class="text-sm font-bold text-gray-900"><?= $_SESSION['admin_name'] ?? 'Admin' ?></div>
                    <div class="text-xs text-gray-500">Manager</div>
                </div>
                <div class="size-10 rounded-full bg-primary text-white flex items-center justify-center font-bold text-lg shadow-sm">
                    <?= strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)) ?>
                </div>
            </div>
        </header>
        
        <main class="p-4 lg:p-8 max-w-7xl mx-auto w-full">