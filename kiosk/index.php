<?php
// kiosk/index.php
// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

ob_start();
include 'header.php'; 

// --- FETCH CONTENT ---
$hero_title = htmlspecialchars(html_entity_decode(EnvLoader::get('HERO_TITLE', 'Welcome to the Mall'), ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
$hero_subtitle = htmlspecialchars(html_entity_decode(EnvLoader::get('HERO_SUBTITLE', 'Explore independent stores and unique finds.'), ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');

try {
    // 1. Fetch Categories
    $cat_sql = "SELECT p.category, COUNT(*) as product_count, 
                (SELECT image_path FROM products p2 WHERE p2.category = p.category AND p2.image_path IS NOT NULL LIMIT 1) as sample_image
                FROM products p 
                WHERE p.category IS NOT NULL AND p.category != '' 
                GROUP BY p.category 
                ORDER BY product_count DESC LIMIT 8";
    $categories = $pdo->query($cat_sql)->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Featured Stores
    $store_sql = "SELECT id, store_name, store_slug, store_logo FROM admins 
                  WHERE store_name IS NOT NULL AND store_slug IS NOT NULL 
                  ORDER BY created_at DESC LIMIT 4";
    $featured_stores = $pdo->query($store_sql)->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Recent Products with Vendor Info
    $prod_sql = "SELECT p.*, a.store_name, a.id as vendor_id 
                 FROM products p 
                 JOIN admins a ON p.admin_id = a.id 
                 ORDER BY p.created_at DESC LIMIT 8";
    $recent_products = $pdo->query($prod_sql)->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Index error: " . $e->getMessage());
    $categories = []; $featured_stores = []; $recent_products = [];
}
?>

<main>
    <div class="hero bg-gray-900 text-white py-20 text-center" style="background-image: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('assets/img/mall-bg.jpg'); background-size: cover; background-position: center;">
        <div class="container mx-auto px-4">
            <h1 class="text-4xl md:text-6xl font-bold mb-4"><?= $hero_title ?></h1>
            <p class="text-xl text-gray-300 mb-8"><?= $hero_subtitle ?></p>
            <div class="flex justify-center gap-4">
                <a href="kiosks.php" class="bg-white text-gray-900 px-8 py-3 rounded-full font-bold hover:bg-gray-100 transition">Browse Stores</a>
                <a href="products.php" class="bg-transparent border-2 border-white text-white px-8 py-3 rounded-full font-bold hover:bg-white hover:text-gray-900 transition">Shop All</a>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 py-12">
        
        <?php if (!empty($featured_stores)): ?>
            <div class="mb-16">
                <div class="flex justify-between items-end mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">Featured Stores</h2>
                    <a href="kiosks.php" class="text-blue-600 hover:underline font-semibold">View All Stores →</a>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php foreach ($featured_stores as $store): 
                        $logo = !empty($store['store_logo']) ? get_logo_url($store['store_logo']) : 'kiosk/uploads/logos/default_logo.png';
                        // FIX: Decode old HTML entities and make safe for display
                        $sName = htmlspecialchars(html_entity_decode($store['store_name'] ?? 'Store', ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
                    ?>
                        <a href="store.php?vendor_id=<?= $store['id'] ?>" class="group block bg-white rounded-lg shadow-sm hover:shadow-md border border-gray-100 overflow-hidden transition">
                            <div class="p-6 flex items-center space-x-4">
                                <img src="<?= htmlspecialchars($logo) ?>" alt="<?= $sName ?>" class="w-16 h-16 rounded-full object-cover border group-hover:scale-105 transition bg-white shadow-sm">
                                <div>
                                    <h3 class="font-bold text-gray-800 group-hover:text-blue-600 transition"><?= $sName ?></h3>
                                    <span class="text-xs text-green-600 font-medium">● Open Now</span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($recent_products)): ?>
            <div class="mb-16">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Fresh from the Mall</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    <?php foreach ($recent_products as $product): 
                        $img = getProductImage($product['image_path']);
                        $imgSafe = $img ?? 'assets/img/no-image.png';
                        
                        // FIX: Clean up vendor, product, and category names
                        $vendorName = htmlspecialchars(html_entity_decode($product['store_name'] ?? 'Asiko Mall', ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
                        $productName = htmlspecialchars(html_entity_decode($product['name'] ?? '', ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
                        $catName = htmlspecialchars(html_entity_decode($product['category'] ?? 'General', ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
                    ?>
                        <article class="bg-white rounded-lg shadow-sm overflow-hidden hover:shadow-md transition border border-gray-100 flex flex-col">
                            <a href="product-detail.php?id=<?= $product['id'] ?>" class="block relative h-48 overflow-hidden bg-gray-100">
                                <img src="<?= htmlspecialchars($imgSafe) ?>" class="w-full h-full object-cover" loading="lazy">
                                <span class="absolute bottom-0 left-0 bg-black bg-opacity-60 text-white text-xs px-2 py-1 truncate max-w-full">
                                    Sold by: <?= $vendorName ?>
                                </span>
                            </a>
                            <div class="p-4 flex-1 flex flex-col">
                                <p class="text-xs text-gray-500 mb-1"><?= $catName ?></p>
                                <h3 class="font-bold text-gray-900 mb-2 truncate">
                                    <a href="product-detail.php?id=<?= $product['id'] ?>"><?= $productName ?></a>
                                </h3>
                                <div class="mt-auto flex justify-between items-center">
                                    <span class="font-bold text-lg">₦<?= number_format($product['price']) ?></span>
                                    <a href="product-detail.php?id=<?= $product['id'] ?>" class="text-blue-600 text-sm hover:underline">View</a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($categories)): ?>
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Shop by Category</h2>
            <div class="categories-grid grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php foreach ($categories as $category): 
                    $cat_img = getProductImage($category['sample_image']); 
                    // FIX: Clean category names
                    $catNameRaw = html_entity_decode($category['category'] ?? 'Uncategorized', ENT_QUOTES, 'UTF-8');
                    $catName = htmlspecialchars($catNameRaw, ENT_QUOTES, 'UTF-8');
                ?>
                    <a href="products.php?category=<?= urlencode($catNameRaw) ?>" class="group block relative rounded-lg overflow-hidden h-32 md:h-40">
                        <img src="<?= htmlspecialchars($cat_img ?? '') ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                        <div class="absolute inset-0 bg-black bg-opacity-40 flex flex-col justify-center items-center text-white p-2 text-center">
                            <h3 class="font-bold text-lg"><?= $catName ?></h3>
                            <span class="text-xs bg-white bg-opacity-20 px-2 py-1 rounded mt-1"><?= $category['product_count'] ?> items</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php include 'footer.php'; ob_end_flush(); ?>