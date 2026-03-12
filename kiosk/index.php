<?php
// kiosk/index.php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

ob_start();
include 'header.php'; 

// Fetch Content
$hero_title = htmlspecialchars(html_entity_decode(EnvLoader::get('HERO_TITLE', 'Welcome to the Mall'), ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
$hero_subtitle = htmlspecialchars(html_entity_decode(EnvLoader::get('HERO_SUBTITLE', 'Explore independent stores and unique finds.'), ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');

try {
    $cat_sql = "SELECT p.category, COUNT(*) as product_count, 
                (SELECT image_path FROM products p2 WHERE p2.category = p.category AND p2.image_path IS NOT NULL AND p2.status='published' LIMIT 1) as sample_image
                FROM products p 
                WHERE p.category IS NOT NULL AND p.category != '' AND p.status='published'
                GROUP BY p.category 
                ORDER BY product_count DESC LIMIT 8";
    $categories = $pdo->query($cat_sql)->fetchAll(PDO::FETCH_ASSOC);

    $store_sql = "SELECT id, store_name, store_slug, store_logo FROM admins 
                  WHERE store_name IS NOT NULL AND store_slug IS NOT NULL 
                  ORDER BY created_at DESC LIMIT 4";
    $featured_stores = $pdo->query($store_sql)->fetchAll(PDO::FETCH_ASSOC);

    $prod_sql = "SELECT p.*, a.store_name, a.id as vendor_id 
                 FROM products p 
                 JOIN admins a ON p.admin_id = a.id 
                 WHERE p.status='published'
                 ORDER BY p.created_at DESC LIMIT 10";
    $recent_products = $pdo->query($prod_sql)->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $categories = []; $featured_stores = []; $recent_products = [];
}
?>

<main class="bg-gray-50 min-h-screen">
    <div class="hero text-white py-16 md:py-24 text-center relative" style="background-image: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.5)), url('assets/img/mall-bg.jpg'); background-size: cover; background-position: center; min-height: 50vh; display: flex; align-items: center;">
        <div class="container mx-auto px-4 relative z-10">
            <h1 class="text-3xl md:text-5xl lg:text-6xl font-black mb-3 md:mb-5 tracking-tight drop-shadow-lg leading-tight"><?= $hero_title ?></h1>
            <p class="text-base md:text-xl text-gray-200 mb-8 drop-shadow-md max-w-2xl mx-auto opacity-90"><?= $hero_subtitle ?></p>
            <div class="flex flex-col sm:flex-row justify-center gap-4">
                <a href="kiosks.php" class="bg-theme bg-theme-hover px-8 py-3.5 md:py-4 rounded-full font-bold shadow-lg transition transform hover:-translate-y-1 text-sm md:text-base">Browse Stores</a>
                <a href="products.php" class="bg-white bg-opacity-20 backdrop-filter backdrop-blur-md border-2 border-white text-white px-8 py-3.5 md:py-4 rounded-full font-bold hover:bg-white hover:text-black transition transform hover:-translate-y-1 text-sm md:text-base">Shop All Products</a>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 py-12 md:py-16">
        
        <?php if (!empty($featured_stores)): ?>
            <div class="mb-12 md:mb-16">
                <div class="flex justify-between items-end mb-6 border-b border-gray-200 pb-2">
                    <h2 class="text-2xl md:text-3xl font-bold text-gray-900 tracking-tight">Featured Stores</h2>
                    <a href="kiosks.php" class="text-theme text-theme-hover font-bold text-[10px] md:text-xs uppercase tracking-wider hidden sm:block">View All →</a>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
                    <?php foreach ($featured_stores as $store): 
                        $logo = !empty($store['store_logo']) ? get_logo_url($store['store_logo']) : 'kiosk/uploads/logos/default_logo.png';
                        $sName = htmlspecialchars(html_entity_decode($store['store_name'] ?? 'Store', ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
                    ?>
                        <a href="store.php?vendor_id=<?= $store['id'] ?>" class="group block bg-white rounded-xl shadow-sm hover:shadow-md border border-gray-100 overflow-hidden transition duration-300">
                            <div class="p-5 flex items-center space-x-4">
                                <img src="<?= htmlspecialchars($logo) ?>" alt="<?= $sName ?>" class="w-14 h-14 md:w-16 md:h-16 rounded-full object-cover border border-gray-100 group-hover:border-theme transition bg-gray-50">
                                <div class="overflow-hidden">
                                    <h3 class="font-bold text-base md:text-lg text-gray-800 group-hover:text-theme transition truncate"><?= $sName ?></h3>
                                    <span class="inline-block mt-1 bg-green-50 border border-green-100 text-green-700 text-[9px] md:text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full">● Open</span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($recent_products)): ?>
            <div class="mb-12 md:mb-16">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-900 tracking-tight mb-6 border-b border-gray-200 pb-2">Fresh from the Mall</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3 md:gap-5">
                    <?php foreach ($recent_products as $product): 
                        $img = getProductImage($product['image_path']);
                        $imgSafe = $img ?? 'assets/img/no-image.png';
                        
                        $vendorName = htmlspecialchars(html_entity_decode($product['store_name'] ?? 'Asiko Mall', ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
                        $productName = htmlspecialchars(html_entity_decode($product['name'] ?? '', ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
                        $catName = htmlspecialchars(html_entity_decode($product['category'] ?? 'General', ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
                        $has_sale = !empty($product['sale_price']) && $product['sale_price'] > 0;
                        $display_price = $has_sale ? $product['sale_price'] : $product['price'];
                    ?>
                        <article class="bg-white rounded-xl shadow-sm hover:shadow-md border border-gray-100 overflow-hidden relative group flex flex-col transition duration-200">
                            <a href="product-detail.php?id=<?= $product['id'] ?>" class="block relative aspect-square bg-gray-50 overflow-hidden">
                                <img src="<?= htmlspecialchars($imgSafe) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-500" loading="lazy">
                                <span class="absolute top-2 left-2 bg-black bg-opacity-70 backdrop-filter backdrop-blur-sm text-white text-[9px] uppercase font-bold tracking-wider px-2 py-1 rounded shadow-sm max-w-[85%] truncate">
                                    <?= $vendorName ?>
                                </span>
                            </a>
                            
                            <a href="product-detail.php?id=<?= $product['id'] ?>" class="p-3 md:p-4 flex-1 flex flex-col block">
                                <p class="text-[9px] md:text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1 truncate"><?= $catName ?></p>
                                <h3 class="font-bold text-gray-800 text-xs md:text-sm leading-snug mb-1 line-clamp-2 group-hover:text-theme transition">
                                    <?= $productName ?>
                                </h3>
                                
                                <div class="mt-auto pt-2 flex items-center gap-1.5 flex-wrap">
                                    <span class="font-black text-gray-900 text-sm md:text-base <?= $has_sale ? 'text-red-600' : '' ?>">
                                        ₦<?= number_format($display_price) ?>
                                    </span>
                                    <?php if ($has_sale): ?>
                                        <span class="text-[10px] md:text-xs text-gray-400 line-through">₦<?= number_format($product['price']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($categories)): ?>
            <h2 class="text-2xl md:text-3xl font-bold text-gray-900 tracking-tight mb-6 border-b border-gray-200 pb-2">Shop by Category</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4">
                <?php foreach ($categories as $category): 
                    $cat_img = getProductImage($category['sample_image']); 
                    $catNameRaw = html_entity_decode($category['category'] ?? 'Uncategorized', ENT_QUOTES, 'UTF-8');
                    $catName = htmlspecialchars($catNameRaw, ENT_QUOTES, 'UTF-8');
                ?>
                    <a href="products.php?category=<?= urlencode($catNameRaw) ?>" class="group block relative rounded-xl overflow-hidden aspect-video md:aspect-[4/3] shadow-sm hover:shadow-md transition">
                        <img src="<?= htmlspecialchars($cat_img ?? '') ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-700">
                        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent flex flex-col justify-end p-3 md:p-4 text-white">
                            <h3 class="font-bold text-sm md:text-lg drop-shadow-md group-hover:text-theme transition leading-tight"><?= $catName ?></h3>
                            <span class="text-[10px] md:text-xs font-medium opacity-80 mt-0.5"><?= $category['product_count'] ?> items</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php include 'footer.php'; ob_end_flush(); ?>