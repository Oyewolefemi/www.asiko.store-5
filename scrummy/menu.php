<?php 
require_once 'config.php';

// Fetch Categories and Products
$cats = $pdo->query("SELECT * FROM categories")->fetchAll();
$prods = $pdo->query("SELECT * FROM products WHERE is_active = 1 ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Menu - Scrummy Nummy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@400;600;700&family=Material+Symbols+Outlined" rel="stylesheet"/>
    <script> tailwind.config = { theme: { extend: { colors: { primary: '#ec6d13' }, fontFamily: { sans: ['Work Sans'] } } } } </script>
</head>
<body class="bg-gray-50 pb-24 font-sans text-gray-900">

    <?php include 'includes/header.php'; ?>

    <div class="sticky top-[64px] z-40 bg-gray-50 py-3 pl-4 border-b border-gray-200/50 backdrop-blur-sm">
        <div class="flex gap-2 overflow-x-auto no-scrollbar pr-4">
            <button class="bg-primary text-white px-4 h-8 rounded-lg text-sm font-bold shadow-md shrink-0">All</button>
            <?php foreach($cats as $c): ?>
                <button class="bg-white border border-gray-200 text-gray-600 px-4 h-8 rounded-lg text-sm font-medium shrink-0 whitespace-nowrap"><?= htmlspecialchars($c['name']) ?></button>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="px-4 mt-4 space-y-4">
        <?php foreach($prods as $p): ?>
        <a href="product.php?id=<?= $p['id'] ?>" class="flex gap-4 bg-white p-3 rounded-xl shadow-sm border border-gray-100 active:scale-[0.99] transition-transform group">
            <div class="flex-1 flex flex-col justify-between">
                <div>
                    <h4 class="font-bold text-gray-900 line-clamp-1 group-hover:text-primary transition-colors"><?= htmlspecialchars($p['name']) ?></h4>
                    <p class="text-xs text-gray-500 mt-1 line-clamp-2"><?= htmlspecialchars($p['description']) ?></p>
                </div>
                <div class="mt-2 font-bold text-primary">₦<?= number_format($p['price']) ?></div>
            </div>
            <div class="relative size-24 shrink-0">
                <img src="<?= htmlspecialchars($p['image_url']) ?>" class="size-full object-cover rounded-lg bg-gray-100">
                <button onclick="event.preventDefault(); addToCartDirect(<?= htmlspecialchars(json_encode([
                    'id'=>$p['id'], 'name'=>$p['name'], 'price'=>$p['price'], 'image'=>$p['image_url']
                ])) ?>)" class="absolute -bottom-2 -right-2 size-8 bg-white text-gray-900 rounded-full shadow border border-gray-100 flex items-center justify-center active:bg-primary active:text-white transition-colors">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                </button>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <?php include 'includes/bottom_nav.php'; ?>
    <script src="assets/js/app.js"></script>
    <script>
        // Helper for the quick-add button to work without redirecting
        function addToCartDirect(product) {
            cart.add(product);
        }
    </script>
    <style>.no-scrollbar::-webkit-scrollbar { display: none; }</style>
</body>
</html>