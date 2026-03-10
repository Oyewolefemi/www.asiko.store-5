<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Load Brand Settings
$brandColor = $settings['primary_color'] ?? '#ec6d13';
$siteName = $settings['site_name'] ?? 'Scrummy Nummy';
?>
<header class="bg-white h-16 sticky top-0 w-full z-50 border-b border-gray-100 flex items-center justify-between px-4 shadow-sm">
    
    <a href="index.php" class="flex items-center gap-2">
        <?php if(!empty($settings['logo_url'])): ?>
            <img src="<?= htmlspecialchars($settings['logo_url']) ?>" alt="<?= htmlspecialchars($siteName) ?>" class="h-8 object-contain">
        <?php else: ?>
            <span class="text-xl font-black tracking-tight text-gray-900"><?= htmlspecialchars($siteName) ?></span>
        <?php endif; ?>
    </a>

    <div class="flex items-center gap-4">
        
        <a href="cart.php" class="relative text-gray-600 hover:text-primary transition flex items-center">
            <span class="material-symbols-outlined text-[26px]">shopping_cart</span>
            <span id="header-cart-badge" class="absolute -top-1 -right-1 bg-primary text-white text-[9px] font-bold size-4 flex items-center justify-center rounded-full hidden shadow-sm border border-white">0</span>
        </a>

        <?php if(isset($_SESSION['user_id'])): ?>
            <a href="profile.php" class="size-8 rounded-full bg-orange-50 text-primary flex items-center justify-center border border-orange-100 transition active:scale-95">
                <span class="material-symbols-outlined text-[20px]">person</span>
            </a>
        <?php else: ?>
            <a href="login.php" class="text-sm font-bold text-primary bg-orange-50 px-3 py-1.5 rounded-lg active:scale-95 transition">Login</a>
        <?php endif; ?>
        
    </div>
</header>

<script>
// Automatically update the cart bubble count on the public header
document.addEventListener('DOMContentLoaded', () => {
    function updateHeaderCart() {
        if(typeof cart !== 'undefined') {
            const items = cart.get();
            const count = items.reduce((sum, item) => sum + item.quantity, 0);
            const badge = document.getElementById('header-cart-badge');
            
            if(count > 0) {
                badge.textContent = count;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }
    }
    
    // Run on load
    updateHeaderCart();
    
    // Listen for custom cart update events (if your app.js triggers them)
    window.addEventListener('cartUpdated', updateHeaderCart);
});
</script>