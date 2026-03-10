<?php 
require_once 'config.php'; 

// Fetch the "Top Deal" product to show as the Hero Image
// We check for is_active = 1 to ensure we don't show deleted/hidden items
$stmt = $pdo->query("SELECT * FROM products WHERE is_top_deal = 1 AND is_active = 1 LIMIT 1");
$top = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Scrummy Nummy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@400;600;700&family=Material+Symbols+Outlined" rel="stylesheet"/>
    <script> tailwind.config = { theme: { extend: { colors: { primary: '#ec6d13' }, fontFamily: { sans: ['Work Sans'] } } } } </script>
</head>
<body class="bg-gray-50 pb-20 text-gray-900 font-sans">
    
    <?php include 'includes/header.php'; ?>

    <div class="p-6 pt-6">
        <h1 class="text-3xl font-bold mb-1">Good day ☀️</h1>
        <p class="text-gray-500 font-medium mb-6">Ready for your coffee break?</p>
        
        <?php if($top): ?>
        <a href="product.php?id=<?= $top['id'] ?>" class="block relative h-48 rounded-2xl overflow-hidden shadow-lg group">
            <img src="<?= htmlspecialchars($top['image_url']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent p-5 flex flex-col justify-end">
                <span class="bg-primary text-white text-xs font-bold px-2 py-1 rounded w-fit mb-2">New Arrival</span>
                <h2 class="text-white text-xl font-bold"><?= htmlspecialchars($top['name']) ?></h2>
            </div>
        </a>
        <?php else: ?>
        <div class="h-48 rounded-2xl bg-orange-100 flex items-center justify-center text-orange-400">
            <span class="font-bold">Check out our Menu!</span>
        </div>
        <?php endif; ?>
    </div>

    <div class="px-6 mb-8">
        <h3 class="text-lg font-bold mb-4">Quick Actions</h3>
        <div class="grid grid-cols-2 gap-4">
            <a href="menu.php" class="flex flex-col items-center p-4 bg-white border border-gray-100 rounded-xl shadow-sm active:scale-95 transition">
                <div class="size-10 bg-orange-50 text-orange-600 rounded-full flex items-center justify-center mb-2"><span class="material-symbols-outlined">restaurant_menu</span></div>
                <span class="font-bold text-sm">Full Menu</span>
            </a>
            <a href="orders.php" class="flex flex-col items-center p-4 bg-white border border-gray-100 rounded-xl shadow-sm active:scale-95 transition">
                <div class="size-10 bg-green-50 text-green-600 rounded-full flex items-center justify-center mb-2"><span class="material-symbols-outlined">history</span></div>
                <span class="font-bold text-sm">Reorder</span>
            </a>
        </div>
    </div>

    <?php include 'includes/bottom_nav.php'; ?>
    <script src="assets/js/app.js"></script>
</body>
</html>