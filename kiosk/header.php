<?php
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once 'config.php';
include_once 'functions.php';
require_once 'EnvLoader.php';

if (!defined('ENV_LOADED')) {
    EnvLoader::load(__DIR__ . '/.env');
    define('ENV_LOADED', true);
}

$store_name = htmlspecialchars(EnvLoader::get('STORE_NAME', 'ASIKO'));

// --- LOGO LOGIC ---
$raw_logo_path = EnvLoader::get('LOGO_PATH', 'ASIKO.png');
$logo_url = get_logo_url($raw_logo_path); 

$absolute_logo_path = __DIR__ . '/' . $raw_logo_path;
$logo_version = file_exists($absolute_logo_path) ? filemtime($absolute_logo_path) : time();

// --- CUSTOMIZER SETTINGS ---
$theme_color = htmlspecialchars(EnvLoader::get('THEME_COLOR', '#2563eb')); 
$background_color = htmlspecialchars(EnvLoader::get('BACKGROUND_COLOR', '#f3f4f6')); 
$text_color = htmlspecialchars(EnvLoader::get('TEXT_COLOR', '#1f2937'));
$font_family = htmlspecialchars(EnvLoader::get('FONT_FAMILY', 'Inter'));
$font_size = htmlspecialchars(EnvLoader::get('FONT_SIZE', '16'));

$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    try {
        if (isset($_SESSION['cart_count'])) {
            $cart_count = $_SESSION['cart_count'];
        } else {
            $cart_stmt = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
            $cart_stmt->execute([$_SESSION['user_id']]);
            $cart_count = $cart_stmt->fetchColumn() ?: 0;
            $_SESSION['cart_count'] = $cart_count; 
        }
    } catch (Exception $e) { $cart_count = 0; }
}
$current_page = basename($_SERVER['PHP_SELF']);
$asset_version = file_exists(__DIR__ . '/assets/css/custom.css') ? filemtime(__DIR__ . '/assets/css/custom.css') : time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $store_name ?></title>
    <link rel="icon" type="image/png" href="icodo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <link href="https://fonts.googleapis.com/css2?family=<?= urlencode($font_family) ?>:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css?v=<?= $asset_version ?>">
    <link rel="stylesheet" href="assets/css/custom.css?v=<?= $asset_version ?>">
    
    <style>
        :root {
            --theme-color: <?= $theme_color ?>;
            --text-color: <?= $text_color ?>;
            --bg-color: <?= $background_color ?>;
        }
        html { font-size: <?= $font_size ?>px; }
        body { 
            font-family: '<?= $font_family ?>', sans-serif !important; 
            background-color: var(--bg-color) !important; 
            color: var(--text-color) !important; 
        }
        
        .text-theme { color: var(--theme-color) !important; }
        .text-theme-hover:hover { color: var(--theme-color) !important; filter: brightness(0.8); }
        .bg-theme { background-color: var(--theme-color) !important; color: #ffffff !important; }
        .bg-theme-hover:hover { background-color: var(--theme-color) !important; filter: brightness(0.85); }
        .border-theme { border-color: var(--theme-color) !important; }
        .nav-link.active { color: var(--theme-color) !important; border-bottom: 2px solid var(--theme-color); }
    </style>
    
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="text-base">
    <div class="page-wrapper relative">
        <header class="luxury-header bg-white shadow-sm sticky top-0 z-40" id="header">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between h-20">
                <a href="index.php" class="logo flex-shrink-0 flex items-center">
                    <img src="<?= $logo_url ?>?v=<?= $logo_version ?>" alt="<?= $store_name ?> Logo" class="h-12 w-auto object-contain">
                </a>
                
                <nav class="hidden md:flex items-center space-x-6 font-medium">
                    <a href="index.php" class="nav-link hover:text-gray-900 transition <?= $current_page == 'index.php' ? 'active' : '' ?>">Home</a>
                    <a href="kiosks.php" class="nav-link hover:text-gray-900 transition <?= $current_page == 'kiosks.php' ? 'active' : '' ?>">Stores</a>
                    <a href="products.php" class="nav-link hover:text-gray-900 transition <?= $current_page == 'products.php' ? 'active' : '' ?>">Products</a>
                    <a href="cart.php" class="nav-link relative hover:text-gray-900 transition <?= $current_page == 'cart.php' ? 'active' : '' ?>">
                        Cart
                        <?php if ($cart_count > 0): ?>
                            <span class="absolute -top-2 -right-3 bg-theme text-white text-[10px] px-1.5 py-0.5 rounded-full shadow-sm" id="cart-count"><?= $cart_count ?></span>
                        <?php endif; ?>
                    </a>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="my-account.php" class="nav-link hover:text-gray-900 transition <?= $current_page == 'my-account.php' ? 'active' : '' ?>">Account</a>
                        <a href="logout.php" class="text-red-500 hover:text-red-700 transition font-bold">Logout</a>
                    <?php else: ?>
                        <a href="auth.php" class="bg-theme bg-theme-hover px-5 py-2 rounded-full font-bold shadow-sm transition">Sign In</a>
                    <?php endif; ?>
                </nav>

                <div class="md:hidden flex items-center gap-4">
                    <a href="cart.php" class="relative">
                        <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        <?php if ($cart_count > 0): ?>
                            <span class="absolute -top-1 -right-2 bg-theme text-white text-[10px] px-1.5 py-0.5 rounded-full"><?= $cart_count ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <button id="mobile-menu-button" type="button" class="inline-flex items-center justify-center p-2 rounded-md text-gray-600 hover:text-black hover:bg-gray-100 focus:outline-none transition">
                        <svg id="hamburger-icon" class="h-6 w-6 block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg id="close-icon" class="h-6 w-6 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </header>

        <div id="mobile-menu" class="hidden md:hidden bg-white border-t border-gray-100 shadow-xl absolute w-full z-50 transition-all">
            <div class="px-4 pt-2 pb-6 space-y-2 shadow-inner">
                <a href="index.php" class="block px-3 py-3 rounded-md text-base font-bold <?= $current_page == 'index.php' ? 'text-theme bg-gray-50' : 'text-gray-900 hover:bg-gray-50' ?>">Home</a>
                <a href="kiosks.php" class="block px-3 py-3 rounded-md text-base font-bold <?= $current_page == 'kiosks.php' ? 'text-theme bg-gray-50' : 'text-gray-900 hover:bg-gray-50' ?>">Stores</a>
                <a href="products.php" class="block px-3 py-3 rounded-md text-base font-bold <?= $current_page == 'products.php' ? 'text-theme bg-gray-50' : 'text-gray-900 hover:bg-gray-50' ?>">Products</a>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="my-account.php" class="block px-3 py-3 rounded-md text-base font-bold <?= $current_page == 'my-account.php' ? 'text-theme bg-gray-50' : 'text-gray-900 hover:bg-gray-50' ?>">My Account</a>
                    <a href="logout.php" class="block px-3 py-3 rounded-md text-base font-bold text-red-600 hover:bg-red-50">Logout</a>
                <?php else: ?>
                    <div class="pt-4 mt-2 border-t border-gray-100">
                        <a href="auth.php" class="block w-full text-center px-3 py-3 rounded-full text-base font-bold bg-theme bg-theme-hover">Sign In / Register</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const btn = document.getElementById('mobile-menu-button');
                const menu = document.getElementById('mobile-menu');
                const hamburger = document.getElementById('hamburger-icon');
                const closeIcon = document.getElementById('close-icon');

                if (btn && menu) {
                    btn.addEventListener('click', () => {
                        menu.classList.toggle('hidden');
                        
                        // Toggle between Hamburger and X icons
                        if (menu.classList.contains('hidden')) {
                            hamburger.classList.remove('hidden');
                            hamburger.classList.add('block');
                            closeIcon.classList.remove('block');
                            closeIcon.classList.add('hidden');
                        } else {
                            hamburger.classList.remove('block');
                            hamburger.classList.add('hidden');
                            closeIcon.classList.remove('hidden');
                            closeIcon.classList.add('block');
                        }
                    });
                }
            });
        </script>