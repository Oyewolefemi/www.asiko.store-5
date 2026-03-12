<?php
// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include 'header.php'; 

// Fetch Hero Content
$hero_title = htmlspecialchars((string)EnvLoader::get('HERO_TITLE', 'Shop Our Collection'), ENT_QUOTES, 'UTF-8');
$hero_subtitle = htmlspecialchars((string)EnvLoader::get('HERO_SUBTITLE', 'Discover the best products at amazing prices.'), ENT_QUOTES, 'UTF-8');

$user_logged_in = isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
$user_name = '';
if ($user_logged_in && isset($_SESSION['user_name'])) {
    $user_name = htmlspecialchars(explode(' ', $_SESSION['user_name'])[0], ENT_QUOTES, 'UTF-8');
}

// Filtering, Sorting & Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20; 
$offset = ($page - 1) * $per_page;

$category_filter = sanitize($_GET['category'] ?? '');
$search_query = sanitize(trim($_GET['search'] ?? ''));

$sort_options = [
    'name_asc' => 'name ASC',
    'name_desc' => 'name DESC',
    'price_asc' => 'COALESCE(sale_price, price) ASC',
    'price_desc' => 'COALESCE(sale_price, price) DESC',
    'created_at_desc' => 'created_at DESC'
];
$sort_key = $_GET['sort'] ?? 'name_asc';
if (!array_key_exists($sort_key, $sort_options)) $sort_key = 'name_asc';
$order_by = $sort_options[$sort_key];

$where_conditions = ["status = 'published'"];
$params = [];

if ($category_filter !== '') {
    $where_conditions[] = "category = ?";
    $params[] = $category_filter;
}
if ($search_query !== '') {
    $where_conditions[] = "MATCH(name, description) AGAINST(? IN BOOLEAN MODE)";
    $params[] = '*' . $search_query . '*';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

try {
    $count_sql = "SELECT COUNT(*) FROM products $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_products = (int)$count_stmt->fetchColumn();
    $total_pages = (int)ceil($total_products / $per_page);

    $sql = "SELECT * FROM products $where_clause ORDER BY $order_by LIMIT ? OFFSET ?"; 
    $stmt = $pdo->prepare($sql);
    $param_index = 1; 
    foreach ($params as $value) $stmt->bindValue($param_index++, $value);
    $stmt->bindValue($param_index++, $per_page, PDO::PARAM_INT);
    $stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $cat_stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' AND status='published' ORDER BY category");
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $products = [];
    $categories = [];
}
?>

<main class="pb-16 bg-gray-50 min-h-screen">
    <div class="bg-theme text-white py-12 md:py-16 text-center relative shadow-inner overflow-hidden">
        <div class="absolute inset-0 bg-black opacity-10"></div>
        <div class="container mx-auto px-4 relative z-10">
            <h1 class="text-2xl md:text-5xl font-bold mb-2 md:mb-3 tracking-tight">
                <?php if (!empty($user_name) && empty($search_query) && empty($category_filter)): ?>
                    <?php 
                    $hour = date('H');
                    $greeting = $hour < 12 ? "Good morning" : ($hour < 17 ? "Good afternoon" : "Good evening");
                    echo $greeting . ', ' . $user_name;
                    ?>
                <?php else: ?>
                    <?= $hero_title ?>
                <?php endif; ?>
            </h1>
            <p class="text-sm md:text-lg opacity-90 max-w-2xl mx-auto"><?= $hero_subtitle ?></p>
        </div>
    </div>

    <div class="container mx-auto px-4 mt-6 md:mt-8">
        
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <h2 class="text-lg md:text-xl font-bold text-gray-800 hidden md:block">
                    <?= $category_filter ? htmlspecialchars($category_filter) : 'All Products' ?>
                </h2>
                
                <form method="GET" action="products.php" id="filter-form" class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto">
                    <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search_query) ?>" class="w-full sm:w-48 border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-theme focus:ring-1 focus:ring-theme bg-gray-50">
                    
                    <div class="flex gap-3 w-full sm:w-auto">
                        <select name="category" class="w-1/2 sm:w-auto border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-theme bg-gray-50" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $category_filter === $cat['category'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(ucfirst($cat['category'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select name="sort" class="w-1/2 sm:w-auto border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-theme bg-gray-50" onchange="this.form.submit()">
                            <option value="name_asc" <?= $sort_key === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                            <option value="price_asc" <?= $sort_key === 'price_asc' ? 'selected' : '' ?>>Lowest Price</option>
                            <option value="price_desc" <?= $sort_key === 'price_desc' ? 'selected' : '' ?>>Highest Price</option>
                            <option value="created_at_desc" <?= $sort_key === 'created_at_desc' ? 'selected' : '' ?>>Newest</option>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <div class="mb-4 text-gray-500 text-xs md:text-sm font-medium px-1">
            Showing <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_products) ?> of <?= $total_products ?> results
        </div>

        <?php if (empty($products)): ?>
            <div class="bg-white p-12 rounded-xl border border-gray-100 text-center shadow-sm">
                <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                <h3 class="text-xl font-bold text-gray-800 mb-2">No Products Found</h3>
                <p class="text-sm text-gray-500">Try adjusting your filters or search query.</p>
                <a href="products.php" class="inline-block mt-4 text-theme font-bold hover:underline text-sm">Clear All Filters</a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-3 md:gap-5">
                <?php foreach ($products as $product): ?>
                    <?php 
                        $product_image = getProductImage($product['image_path']);
                        $imgSafe = ($product_image && $product_image !== 'https://placehold.co/100x100/f8f9fa/ccc?text=No+Image') ? $product_image : 'assets/img/no-image.png';
                        $has_sale = !empty($product['sale_price']) && $product['sale_price'] > 0;
                        $display_price = $has_sale ? $product['sale_price'] : $product['price'];
                    ?>
                    <article class="bg-white rounded-xl shadow-sm hover:shadow-md border border-gray-100 overflow-hidden relative group flex flex-col transition duration-200">
                        <a href="product-detail.php?id=<?= $product['id'] ?>" class="block relative aspect-square bg-gray-50 overflow-hidden">
                            <img src="<?= htmlspecialchars($imgSafe) ?>" alt="<?= htmlspecialchars($product['name']) ?>" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                            <?php if ($has_sale): ?>
                                <span class="absolute top-2 left-2 bg-red-500 text-white text-[9px] md:text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded shadow-sm">Sale</span>
                            <?php endif; ?>
                        </a>
                        
                        <button onclick="addToCart(<?= $product['id'] ?>, null, this)" class="absolute top-[calc(100%-4rem)] md:top-[calc(100%-4.5rem)] right-2 bg-theme bg-theme-hover text-white p-2 rounded-full shadow-md z-10 transition transform hover:scale-110 active:scale-95 flex items-center justify-center" aria-label="Add to cart">
                            <svg class="w-4 h-4 md:w-5 md:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        </button>
                        
                        <a href="product-detail.php?id=<?= $product['id'] ?>" class="p-3 md:p-4 flex-1 flex flex-col block">
                            <p class="text-[9px] md:text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1 truncate w-[85%]"><?= htmlspecialchars($product['category']) ?></p>
                            <h3 class="font-bold text-gray-800 text-xs md:text-sm leading-snug mb-1 line-clamp-2 group-hover:text-theme transition">
                                <?= htmlspecialchars($product['name']) ?>
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
            
            <?php if ($total_pages > 1): ?>
            <div class="flex justify-center mt-10 md:mt-12 gap-1.5 flex-wrap px-2">
                <?php
                $query_params = $_GET;
                if ($page > 1) {
                    $query_params['page'] = $page - 1;
                    echo '<a href="?' . http_build_query($query_params) . '" class="px-3 py-2 bg-white border border-gray-200 rounded-lg text-xs md:text-sm font-bold text-gray-600 hover:bg-gray-50 transition">&laquo; Prev</a>';
                }
                
                $start = max(1, $page - 1);
                $end = min($total_pages, $page + 1);
                
                if($start > 1) echo '<span class="px-2 py-2 text-gray-400">...</span>';

                for ($i = $start; $i <= $end; $i++) {
                    $query_params['page'] = $i;
                    if ($i == $page) {
                        echo '<span class="px-3 md:px-4 py-2 bg-theme text-white rounded-lg text-xs md:text-sm font-bold shadow-sm">' . $i . '</span>';
                    } else {
                        echo '<a href="?' . http_build_query($query_params) . '" class="px-3 md:px-4 py-2 bg-white border border-gray-200 rounded-lg text-xs md:text-sm font-bold text-gray-600 hover:bg-theme hover:text-white hover:border-transparent transition">' . $i . '</a>';
                    }
                }
                
                if($end < $total_pages) echo '<span class="px-2 py-2 text-gray-400">...</span>';

                if ($page < $total_pages) {
                    $query_params['page'] = $page + 1;
                    echo '<a href="?' . http_build_query($query_params) . '" class="px-3 py-2 bg-white border border-gray-200 rounded-lg text-xs md:text-sm font-bold text-gray-600 hover:bg-gray-50 transition">Next &raquo;</a>';
                }
                ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<script>
function addToCart(productId, options, buttonElement) {
    const originalContent = buttonElement.innerHTML;
    // Loading spinner SVG
    buttonElement.innerHTML = `<svg class="w-4 h-4 md:w-5 md:h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>`;
    buttonElement.disabled = true;

    fetch('ajax_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId, quantity: 1, options: options })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Checkmark SVG
            buttonElement.innerHTML = `<svg class="w-4 h-4 md:w-5 md:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>`;
            buttonElement.classList.add('bg-green-500');
            const cartBadge = document.getElementById('cart-count');
            if (cartBadge) {
                cartBadge.textContent = data.cart_count;
                cartBadge.classList.remove('hidden');
            }
        } else {
            alert(data.message || 'Failed to add to cart.');
            buttonElement.innerHTML = originalContent;
        }
        setTimeout(() => { 
            buttonElement.innerHTML = originalContent; 
            buttonElement.classList.remove('bg-green-500');
            buttonElement.disabled = false; 
        }, 1500);
    })
    .catch(err => {
        alert("An error occurred.");
        buttonElement.innerHTML = originalContent;
        buttonElement.disabled = false;
    });
}
</script>

<?php include 'footer.php'; ?>