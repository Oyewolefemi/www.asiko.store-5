<?php
// kiosk/Red/header.php

include_once __DIR__ . '/../config.php';
include_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../EnvLoader.php';
EnvLoader::load(__DIR__ . '/../.env');

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// DUAL-AUTH SECURITY CHECK
if (!isset($_SESSION['admin_id'])) {
    
    $sso_token = $_COOKIE['asiko_sso_token'] ?? null;
    $isAuthenticatedViaSSO = false;
    
    if ($sso_token) {
        $parts = explode('.', $sso_token);
        if (count($parts) === 3) {
            list($header64, $payload64, $signature64) = $parts;
            $publicKeyPath = __DIR__ . '/../../hq/keys/public.pem'; 
            if (file_exists($publicKeyPath)) {
                $publicKey = openssl_pkey_get_public(file_get_contents($publicKeyPath));
                $signature = base64_decode(str_replace(['-', '_'], ['+', '/'], $signature64));
                $dataToSign = $header64 . "." . $payload64;
                
                if (openssl_verify($dataToSign, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1) {
                    $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload64)), true);
                    if (isset($payload['exp']) && $payload['exp'] >= time()) {
                        $_SESSION['admin_id'] = $payload['user_id'];
                        $_SESSION['admin_username'] = $payload['name'];
                        $_SESSION['admin_role'] = 'superadmin';
                        $_SESSION['store_name'] = 'Mall HQ';
                        $isAuthenticatedViaSSO = true;
                    }
                }
            }
        }
    }
    
    if (!$isAuthenticatedViaSSO) {
        header("Location: admin_auth.php");
        exit;
    }
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
    <title><?php echo htmlspecialchars($store_name, ENT_QUOTES, 'UTF-8'); ?> - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
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
                    <img src="<?php echo htmlspecialchars($logo_src, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo time(); ?>" alt="Logo" class="h-12 w-auto mx-auto mb-3 object-contain">
                <?php endif; ?>
                <h1 class="text-lg font-bold text-gray-800 truncate"><?php echo htmlspecialchars($store_name, ENT_QUOTES, 'UTF-8'); ?></h1>
                <span class="inline-block bg-gray-200 rounded-full px-3 py-1 text-xs font-semibold text-gray-600 mt-1">
                    <?php echo $is_super ? 'Super Admin' : 'Vendor Account'; ?>
                </span>
            </div>

            <nav class="p-4 space-y-1 flex-1 overflow-y-auto">
                <a href="admin_dashboard.php" class="nav-link flex items-center py-3 px-4 rounded transition duration-200 hover:bg-gray-100 <?php echo ($current_page == 'admin_dashboard.php' || $current_page == 'superadmin_dashboard.php') ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined mr-3 text-gray-500">dashboard</span> Dashboard
                </a>
                
                <a href="admin_products.php" class="nav-link flex items-center py-3 px-4 rounded transition duration-200 hover:bg-gray-100 <?php echo $current_page == 'admin_products.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined mr-3 text-gray-500">inventory_2</span> <?php echo $is_super ? 'All Products' : 'My Products'; ?>
                </a>

                <a href="orders.php" class="nav-link flex items-center py-3 px-4 rounded transition duration-200 hover:bg-gray-100 <?php echo $current_page == 'orders.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined mr-3 text-gray-500">shopping_bag</span> <?php echo $is_super ? 'Mall Orders' : 'My Orders'; ?>
                </a>

                <?php if ($is_super): ?>
                    <div class="mt-6 mb-2 px-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Mall Management</div>
                    
                    <a href="manage_admins.php" class="nav-link flex items-center py-3 px-4 rounded transition duration-200 hover:bg-gray-100 <?php echo $current_page == 'manage_admins.php' ? 'active' : ''; ?>">
                        <span class="material-symbols-outlined mr-3 text-gray-500">group</span> Vendors & Staff
                    </a>
                    <a href="customize.php" class="nav-link flex items-center py-3 px-4 rounded transition duration-200 hover:bg-gray-100 <?php echo $current_page == 'customize.php' ? 'active' : ''; ?>">
                        <span class="material-symbols-outlined mr-3 text-gray-500">palette</span> Site Appearance
                    </a>

                    <div class="mt-6 mb-2 px-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Intelligence</div>
                    
                    <a href="analytics.php" class="nav-link flex items-center py-3 px-4 rounded transition duration-200 hover:bg-gray-100 <?php echo $current_page == 'analytics.php' ? 'active' : ''; ?>">
                        <span class="material-symbols-outlined mr-3 text-gray-500">trending_up</span> Analytics
                    </a>
                    
                    <a href="customers.php" class="nav-link flex items-center py-3 px-4 rounded transition duration-200 hover:bg-gray-100 <?php echo $current_page == 'customers.php' ? 'active' : ''; ?>">
                        <span class="material-symbols-outlined mr-3 text-gray-500">contact_page</span> Data Bank
                    </a>
                <?php else: ?>
                    <div class="mt-6 mb-2 px-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Storefront</div>
                    
                    <a href="../store.php?vendor_id=<?php echo $_SESSION['admin_id']; ?>" target="_blank" class="nav-link flex items-center py-3 px-4 rounded transition duration-200 hover:bg-gray-100">
                        <span class="material-symbols-outlined mr-3 text-gray-500">storefront</span> View My Shop
                    </a>
                <?php endif; ?>

            </nav>

            <div class="p-4 border-t space-y-2 bg-gray-50">
                <a href="../kiosks.php" target="_blank" class="flex items-center text-gray-600 hover:text-blue-600 px-4 text-sm font-medium">
                    <span class="material-symbols-outlined mr-2 text-gray-500 text-lg">public</span> Visit Mall Directory
                </a>
                <a href="admin_profile.php" class="flex items-center text-gray-600 hover:text-blue-600 px-4 text-sm font-medium">
                    <span class="material-symbols-outlined mr-2 text-gray-500 text-lg">settings</span> Settings
                </a>
                <a href="admin_logout.php" class="flex items-center text-red-600 hover:bg-red-50 py-2 px-4 rounded transition duration-200 font-medium">
                     <span class="material-symbols-outlined mr-2 text-red-500 text-lg">logout</span> Logout
                </a>
            </div>
        </div>
        
        <div class="flex-1 flex flex-col min-h-screen">
            <div class="md:hidden bg-white shadow-sm p-4 flex justify-between items-center z-20 sticky top-0">
                <span class="font-bold text-gray-800"><?php echo htmlspecialchars($store_name, ENT_QUOTES, 'UTF-8'); ?></span>
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