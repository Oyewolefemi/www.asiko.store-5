<?php
// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include 'header.php'; 

// Fetch Hero Content
$hero_title = htmlspecialchars(EnvLoader::get('HERO_TITLE', 'Shop Our Collection'));
$hero_subtitle = htmlspecialchars(EnvLoader::get('HERO_SUBTITLE', 'Discover the best products at amazing prices.'));

// User Greeting
$user_logged_in = isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
$user_name = '';
if ($user_logged_in && isset($_SESSION['user_name'])) {
    $user_name = htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]);
}

// Filtering, Sorting & Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20; // Increased per page since cards are smaller
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
if (!array_key_exists($sort_key, $sort_options)) {
    $sort_key = 'name_asc';
}
$order_by = $sort_options[$sort_key];

$where_conditions = [];
$params = [];

if ($category_filter !== '') {
    $where_conditions[] = "category = ?";
    $params[] = $category_filter;
}
if ($search_query !== '') {
    $where_conditions[] = "MATCH(name, description) AGAINST(? IN BOOLEAN MODE)";
    $params[] = '*' . $search_query . '*';
}

$where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

try {
    // Count Total
    $count_sql = "SELECT COUNT(*) FROM products $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_products = (int)$count_stmt->fetchColumn();
    $total_pages = (int)ceil($total_products / $per_page);

    // Fetch Products
    $sql = "SELECT * FROM products $where_clause ORDER BY $order_by LIMIT ? OFFSET ?"; 
    $stmt = $pdo->prepare($sql);
    $param_index = 1; 
    foreach ($params as $value) {
        $stmt->bindValue($param_index++, $value);
    }
    $stmt->bindValue($param_index++, $per_page, PDO::PARAM_INT);
    $stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Categories
    $cat_stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    printError("Error fetching products: " . $e->getMessage());
    $products = [];
    $categories = [];
}
?>

<link rel="stylesheet" href="assets/css/products.css?v=<?= time() ?>"> 

<main>
    <div class="hero">
        <div class="container">
            <h1 id="hero-title">
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
            <p id="hero-subtitle"><?= $hero_subtitle ?></p>
        </div>
    </div>

    <div class="container">
        <div class="main-content-box"> 
            
            <div class="filters my-6">
                <h2 class="section-title">
                    <?= $category_filter ? htmlspecialchars($category_filter) : 'All Products' ?>
                </h2>
                <form method="GET" action="products.php" id="filter-form">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label for="search-input">Search</label>
                            <input type="text" name="search" id="search-input" placeholder="Search..." value="<?= htmlspecialchars($search_query) ?>" class="filter-input">
                        </div>
                        <div class="filter-group">
                            <label for="category-select">Category</label>
                            <select name="category" id="category-select" class="filter-select" onchange="this.form.submit()">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $category_filter === $cat['category'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(ucfirst($cat['category'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="sort-select">Sort By</label>
                            <select name="sort" id="sort-select" class="filter-select" onchange="this.form.submit()">
                                <option value="name_asc" <?= $sort_key === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                                <option value="price_asc" <?= $sort_key === 'price_asc' ? 'selected' : '' ?>>Price (Low to High)</option>
                                <option value="price_desc" <?= $sort_key === 'price_desc' ? 'selected' : '' ?>>Price (High to Low)</option>
                                <option value="created_at_desc" <?= $sort_key === 'created_at_desc' ? 'selected' : '' ?>>Newest</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>

            <div class="results-info mb-4 text-gray-500 text-sm">
                <span>Showing <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_products) ?> of <?= $total_products ?> results</span>
            </div>

            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <h3>No Products Found</h3>
                    <p>We couldn't find any products matching your criteria.</p>
                </div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
                        <?php 
                            $product_image = getProductImage($product['image_path']);
                            $has_sale = !empty($product['sale_price']) && $product['sale_price'] > 0;
                            $display_price = $has_sale ? $product['sale_price'] : $product['price'];
                            $has_options = !empty($product['options']) && $product['options'] != '[]';
                        ?>
                        <article class="card">
                            <a href="product-detail.php?id=<?= $product['id'] ?>" class="card-link">
                                
                                <div class="card-body">
                                    <p class="category"><?= htmlspecialchars($product['category']) ?></p>
                                    <h3 class="card-title"><?= htmlspecialchars($product['name']) ?></h3>
                                    <div class="price">
                                        <span class="<?= $has_sale ? 'text-red-600' : 'text-luxury-black' ?>">
                                            <?= formatCurrency($display_price) ?>
                                        </span>
                                        <?php if ($has_sale): ?>
                                            <span class="text-xs text-gray-500 line-through ml-1"><?= formatCurrency($product['price']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if($product['stock_quantity'] > 0): ?>
                                        <p class="text-xs text-green-600 mt-1 list-view-only">In stock</p>
                                    <?php else: ?>
                                        <p class="text-xs text-red-600 mt-1 list-view-only">Out of stock</p>
                                    <?php endif; ?>
                                </div>

                                <div class="card-img">
                                    <?php if ($product_image && $product_image !== 'https://placehold.co/100x100/f8f9fa/ccc?text=No+Image'): ?>
                                        <img src="<?= htmlspecialchars($product_image) ?>" alt="<?= htmlspecialchars($product['name']) ?>" loading="lazy">
                                    <?php else: ?>
                                        <div class="placeholder"><span>📦</span></div>
                                    <?php endif; ?>
                                </div>
                            </a>

                            <div class="card-actions" onclick="event.stopPropagation();">
                                <?php if ($has_options): ?>
                                    <a href="product-detail.php?id=<?= $product['id'] ?>" class="btn btn-primary btn-sm w-full">Options</a>
                                <?php else: ?>
                                    <div class="flex gap-2 w-full">
                                        <button class="btn btn-primary btn-sm flex-grow" onclick="addToCart(<?= $product['id'] ?>, null, this)">Add</button>
                                        <a href="product-detail.php?id=<?= $product['id'] ?>" class="btn btn-secondary btn-sm">View</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php
                    $query_params = $_GET;
                    if ($page > 1) {
                        $query_params['page'] = $page - 1;
                        echo '<a href="?' . http_build_query($query_params) . '">&laquo; Prev</a>';
                    }
                    
                    // Simplified pagination to avoid overflow
                    $start = max(1, $page - 1);
                    $end = min($total_pages, $page + 1);
                    
                    if($start > 1) echo '<span class="dots">...</span>';

                    for ($i = $start; $i <= $end; $i++) {
                        $query_params['page'] = $i;
                        if ($i == $page) {
                            echo '<span class="current">' . $i . '</span>';
                        } else {
                            echo '<a href="?' . http_build_query($query_params) . '">' . $i . '</a>';
                        }
                    }
                    
                    if($end < $total_pages) echo '<span class="dots">...</span>';

                    if ($page < $total_pages) {
                        $query_params['page'] = $page + 1;
                        echo '<a href="?' . http_build_query($query_params) . '">Next &raquo;</a>';
                    }
                    ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
function addToCart(productId, options, buttonElement) {
    const originalText = buttonElement.textContent;
    buttonElement.textContent = '...';
    buttonElement.disabled = true;

    fetch('ajax_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            product_id: productId,
            quantity: 1,
            options: options
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            buttonElement.textContent = '✔';
            const cartBadge = document.getElementById('cart-count');
            if (cartBadge) {
                cartBadge.textContent = data.cart_count;
                cartBadge.classList.remove('hidden');
            }
        } else {
            alert(data.message || 'Failed.');
            buttonElement.textContent = originalText;
        }
        setTimeout(() => { 
            buttonElement.textContent = originalText; 
            buttonElement.disabled = false; 
        }, 2000);
    })
    .catch(err => {
        console.error("Cart Error:", err);
        alert("Error.");
        buttonElement.textContent = originalText;
        buttonElement.disabled = false;
    });
}
</script>

<?php include 'footer.php'; ?>