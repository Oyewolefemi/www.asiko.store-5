<?php
// kiosk/Red/header.php

include_once __DIR__ . '/../config.php';
include_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../EnvLoader.php';
EnvLoader::load(__DIR__ . '/../.env');

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_auth.php");
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
$raw_logo = EnvLoader::get('LOGO_PATH');
$user_role = $_SESSION['admin_role'] ?? 'admin'; 
$is_super = ($user_role === 'superadmin');
$store_name = $is_super ? 'Mall Admin' : ($_SESSION['store_name'] ?? 'My Store');

// Logo Logic
$logo_src = '';
if ($raw_logo) {
    if (strpos($raw_logo, 'Red/') === 0) {
        $logo_src = substr($raw_logo, 4);
    } else {
        $logo_src = '../' . ltrim($raw_logo, '/');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($store_name) ?> - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js" defer></script>
    <style>
        .nav-link.active { 
            background-color: #eff6ff; 
            color: #2563eb; 
            font-weight: 600; 
            border-right: 3px solid #2563eb; 
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    
    <div id="mobile-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden md:hidden"></div>

    <div class="flex min-h-screen">
        
        <div id="sidebar" class="w-64 bg-white shadow-xl fixed md:static h-full z-40 transform -translate-x-full md:translate-x-0 transition-transform duration-300 flex flex-col">
            
            <div class="p-6 border-b text-center bg-gray-50">
                <?php if ($logo_src): ?>
                    <img src="<?= htmlspecialchars($logo_src) ?>?v=<?= time() ?>" alt="Logo" class="h-12 w-auto mx-auto mb-3 object-contain">
                <?php endif; ?>
                <h1 class="text-lg font-bold text-gray-800 truncate"><?= htmlspecialchars($store_name) ?></h1>
                <span class="inline-block bg-gray-200 rounded-full px-3 py-1 text-xs font-semibold text-gray-600 mt-1">
                    <?= $is_super ? 'Super Admin' : 'Vendor Account' ?>
                </span>
            </div>

            <nav class="p-4 space-y-1 flex-1 overflow-y-auto">
                <a href="admin_dashboard.php" class="nav-link flex items-center py-3 px-4 rounded transition duration-200 hover:bg-gray-100 <?= ($current_page == 'admin_dashboard.php' || $current_page == 'superadmin_dashboard.php') ? 'active' : '' ?>">
                    <span class="mr-3">📊</span> Dashboard
                </a>
                
                <a href="admin_products.php" class="nav-link flex items-center py-3 px-4 rounded transition duration-200 hover:bg-gray-100 <?= $current_page == 'admin_products.php' ? 'active' : '' ?>">
                    <span class="mr-3">📦</span> <?= $is_super ? 'All Products' : 'My Products' ?>
                </a>

                <a href="orders.php" class="nav-link flex items-center py-3 px-4 rounded transition duration-200 hover:bg-gray-100 <?= $current_page == 'orders.php' ? 'active' : '' ?>">
                    <span class="mr-3">🛍️</span> <?= $is_super ? 'Mall Orders' : 'My Orders' ?>
                </a>

                <?php if ($is_super): ?>
                    <div class="mt-6 mb-2 px-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Mall Management</div>
                    
                    <a href="manage_admins.php" class="nav-link flex items-center py-3 px-4 rounded transition duration-200 hover:bg-gray-100 <?= $current_page == 'manage_admins.php' ? 'active' : '' ?>">
                        <span class="mr-3">👥</span> Vendors & Staff
                    </a>
                    <a href="customize.php" class="nav-link flex items-center py-3 px-4 rounded transition duration-200 hover:bg-gray-100 <?= $current_page == 'customize.php' ? 'active' : '' ?>">
                        <span class="mr-3">🎨</span> Site Appearance
                    </a>

                    <div class="mt-6 mb-2 px-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Intelligence</div>
                    
                    <a href="analytics.php" class="nav-link flex items-center py-3 px-4 rounded transition duration-200 hover:bg-gray-100 <?= $current_page == 'analytics.php' ? 'active' : '' ?>">
                        <span class="mr-3">📈</span> Analytics
                    </a>
                    
                    <a href="customers.php" class="nav-link flex items-center py-3 px-4 rounded transition duration-200 hover:bg-gray-100 <?= $current_page == 'customers.php' ? 'active' : '' ?>">
                        <span class="mr-3">📇</span> Data Bank
                    </a>
                <?php else: ?>
                    <div class="mt-6 mb-2 px-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Storefront</div>
                    
                    <a href="../store.php?vendor_id=<?= $_SESSION['admin_id'] ?>" target="_blank" class="nav-link flex items-center py-3 px-4 rounded transition duration-200 hover:bg-gray-100">
                        <span class="mr-3">🏪</span> View My Shop
                    </a>
                <?php endif; ?>

            </nav>

            <div class="p-4 border-t space-y-2 bg-gray-50">
                <a href="../kiosks.php" target="_blank" class="flex items-center text-gray-600 hover:text-blue-600 px-4 text-sm font-medium">
                    <span class="mr-2">🌍</span> Visit Mall Directory
                </a>
                <a href="admin_profile.php" class="flex items-center text-gray-600 hover:text-blue-600 px-4 text-sm font-medium">
                    <span class="mr-2">⚙️</span> Settings
                </a>
                <a href="admin_logout.php" class="flex items-center text-red-600 hover:bg-red-50 py-2 px-4 rounded transition duration-200 font-medium">
                     <span class="mr-2">🚪</span> Logout
                </a>
            </div>
        </div>
        
        <div class="flex-1 flex flex-col min-h-screen">
            <div class="md:hidden bg-white shadow-sm p-4 flex justify-between items-center z-20 sticky top-0">
                <span class="font-bold text-gray-800"><?= htmlspecialchars($store_name) ?></span>
                <button onclick="toggleSidebar()" class="text-gray-700 hover:text-blue-600 focus:outline-none">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
            
            <main class="flex-1 p-4 md:p-8">

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobile-overlay');
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    }
</script>