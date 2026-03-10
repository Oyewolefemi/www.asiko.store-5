<?php
// Always include db.php at the top so you have the database connection
include_once 'db.php';
require_once 'functions.php';

// The following functions now come from the updated functions.php
// getSectorTerm(), getSystemSetting()

$settingsFile = 'settings.json';
// Use getSystemSetting() to get all necessary dynamic values
$businessName = getSystemSetting('business_name', 'Inventory & Finance System');

// --- DYNAMIC TERMS FETCHED ---
$inventoryTitle = getSectorTerm('inventory_link', 'Products');
$ordersTitle = getSectorTerm('orders_title', 'Orders');
$categoriesTitle = getSectorTerm('category_label', 'Categories');
$tasksTitle = getSectorTerm('low_stock_task', 'Tasks');
// -----------------------------

$settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [
    'business_name' => '',
    'currency' => '₦',
    'low_stock_threshold' => 5
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($settings['business_name'] ?: 'Inventory & Finance System') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100">
<div class="min-h-screen flex" x-data="{ open: false }">
    <nav class="hidden md:flex w-60 min-h-screen bg-gray-800 text-white flex-col gap-2 py-8 px-4 shadow-lg">
        <div class="text-xl font-bold mb-8 text-center tracking-wide">
            <?= htmlspecialchars($businessName) ?>
        </div>
        <a href="dashboard.php" class="py-2 px-3 rounded hover:bg-gray-700 flex items-center gap-2">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M3 12L12 3l9 9" />
                <path d="M9 21V9h6v12" />
            </svg>
            Dashboard
        </a>
        <a href="inventory.php" class="py-2 px-3 rounded hover:bg-gray-700 flex items-center gap-2">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="3" y="7" width="18" height="13" rx="2"/>
                <path d="M16 3v4M8 3v4"/>
            </svg>
            <?= $inventoryTitle ?>
        </a>
        
        <a href="tasks.php" class="py-2 px-3 rounded hover:bg-gray-700 flex items-center gap-2">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M9 12h.01M15 12h.01M12 7v.01M12 17v.01"/>
                <path d="M21 12c0-4.418-4.03-8-9-8s-9 3.582-9 8 4.03 8 9 8 9-3.582 9-8z"/>
            </svg>
            <?= $tasksTitle ?>
        </a>
        <a href="sales.php" class="py-2 px-3 rounded hover:bg-gray-700 flex items-center gap-2">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="9" cy="21" r="1"/>
                <circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/>
            </svg>
            Sales
        </a>
        <a href="finance.php" class="py-2 px-3 rounded hover:bg-gray-700 flex items-center gap-2">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="2" y="7" width="20" height="14" rx="3"/>
                <path d="M16 3v4M8 3v4"/>
            </svg>
            Finance
        </a>
       <a href="categories.php" class="py-2 px-3 rounded hover:bg-gray-700 flex items-center gap-2">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M20 12V7a2 2 0 00-2-2h-5.586a1 1 0 00-.707.293l-7.707 7.707a2 2 0 000 2.828l5.586 5.586a2 2 0 002.828 0l7.707-7.707a1 1 0 00.293-.707V9a2 2 0 00-2-2h-3"/>
            </svg>
            <?= $categoriesTitle ?>
        </a>
        <a href="reports.php" class="py-2 px-3 rounded hover:bg-gray-700 flex items-center gap-2">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M4 19V7M12 19V3M20 19V11"/>
            </svg>
            Reports
        </a>
        <a href="export.php" class="py-2 px-3 rounded hover:bg-gray-700 flex items-center gap-2">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M12 19V5m0 0l-7 7m7-7l7 7"/>
            </svg>
            Export
        </a>
        <a href="settings.php" class="py-2 px-3 rounded hover:bg-gray-700 flex items-center gap-2">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="3"/>
                <path d="M19.4 15a1.65 1.65 0 00.33 1.82l1.38 1.38a1.65 1.65 0 001.82.33 1.65 1.65 0 001.1-1.44V14a1.65 1.65 0 00-.33-1.82l-1.38-1.38a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1.1 1.44V14z"/>
            </svg>
            Settings
        </a>
        <a href="audit.php" class="py-2 px-3 rounded hover:bg-gray-700 flex items-center gap-2">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="4" y="4" width="16" height="16" rx="2"/>
                <path d="M8 2v4M16 2v4"/>
                <path d="M4 10h16"/>
            </svg>
            Audit Log
        </a>
        <a href="logout.php" class="py-2 px-3 rounded hover:bg-red-700 flex items-center gap-2 mt-4 border-t border-gray-700 pt-4">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Logout
        </a>
    </nav>

    <nav class="md:hidden fixed w-full z-20 bg-gray-800 text-white flex items-center justify-between px-4 py-3 shadow">
        <button @click="open = !open" aria-label="Open menu">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 8h16M4 16h16"/>
            </svg>
        </button>
        <div class="text-lg font-bold tracking-wide">
            <?= htmlspecialchars($businessName) ?>
        </div>
        <div></div>
    </nav>
    <nav x-show="open" x-cloak @click.away="open=false" class="fixed inset-y-0 left-0 z-30 w-56 bg-gray-900 text-white flex flex-col gap-2 py-8 px-4 shadow-lg transition-transform duration-200 md:hidden" x-transition>
        <button @click="open=false" class="self-end mb-6" aria-label="Close menu">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
        <a href="dashboard.php" @click="open=false" class="py-2 px-3 rounded hover:bg-gray-700 flex items-center gap-2">Dashboard</a>
        <a href="inventory.php" @click="open=false" class="py-2 px-3 rounded hover:bg-gray-700 flex items-center gap-2"><?= $inventoryTitle ?></a>
        <a href="tasks.php" @click="open=false" class="py-2 px-3 rounded hover:bg-gray-700 flex items-center gap-2"><?= $tasksTitle ?></a>
        <a href="sales.php" @click="open=false" class="py-2 px-3 rounded hover:bg-gray-700 flex items-center gap-2">Sales</a>
        <a href="finance.php" @click="open=false" class="py-2 px-3 rounded hover:bg-gray-700 flex items-center gap-2">Finance</a>
        <a href="suppliers.php" @click="open=false" class="py-2 px-3 rounded hover:bg-gray-700 flex items-center gap-2">Suppliers</a>
        <a href="categories.php" @click="open=false" class="py-2 px-3 rounded hover:bg-gray-700 flex items-center gap-2"><?= $categoriesTitle ?></a>
        <a href="reports.php" @click="open=false" class="py-2 px-3 rounded hover:bg-gray-700 flex items-center gap-2">Reports</a>
        <a href="export.php" @click="open=false" class="py-2 px-3 rounded hover:bg-gray-700 flex items-center gap-2">Export</a>
        <a href="settings.php" @click="open=false" class="py-2 px-3 rounded hover:bg-gray-700 flex items-center gap-2">Settings</a>
        <a href="audit.php" @click="open=false" class="py-2 px-3 rounded hover:bg-gray-700 flex items-center gap-2">Audit Log</a>
        <a href="logout.php" @click="open=false" class="py-2 px-3 rounded hover:bg-red-700 flex items-center gap-2 mt-4 border-t border-gray-700 pt-4">Logout</a>
    </nav>
    <div class="flex-1 min-h-screen bg-gray-100 p-4 md:p-8 mt-12 md:mt-0">