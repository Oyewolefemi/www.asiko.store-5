<?php
// kiosk/product-detail.php
include 'header.php';
include 'config.php';
include 'functions.php';

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['user_id'] ?? 0;

if ($product_id <= 0) {
    echo "<div class='container mx-auto py-12 px-4 text-center'><h2 class='text-2xl font-bold text-gray-800'>Invalid product ID.</h2></div>";
    include 'footer.php';
    exit;
}

// --- HANDLE REVIEW SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    if ($user_id > 0) {
        $rating = intval($_POST['rating'] ?? 0);
        $review_text = sanitize($_POST['review_text'] ?? '');

        if ($rating >= 1 && $rating <= 5) {
            try {
                $stmt_check = $pdo->prepare("SELECT id FROM reviews WHERE product_id = ? AND user_id = ?");
                $stmt_check->execute([$product_id, $user_id]);
                
                if ($stmt_check->fetch()) {
                    $error_msg = "You have already submitted a review for this product.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO reviews (product_id, user_id, rating, review_text, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$product_id, $user_id, $rating, $review_text]);
                    $success_msg = "Thank you for your review!";
                }
            } catch (Exception $e) { $error_msg = "Error submitting review: " . $e->getMessage(); }
        } else {
            $error_msg = "Please select a valid rating.";
        }
    } else {
        $error_msg = "You must be logged in to submit a review.";
    }
}

try {
    $stmt = $pdo->prepare("
        SELECT p.*, a.store_name, a.role 
        FROM products p 
        LEFT JOIN admins a ON p.admin_id = a.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo "<div class='container mx-auto py-12 px-4 text-center'><h2 class='text-2xl font-bold text-gray-800'>Product not found.</h2></div>";
        include 'footer.php';
        exit;
    }

    $vendorName = 'Unknown Vendor';
    if ($product['role'] === 'superadmin') {
        $vendorName = 'Asiko'; 
    } elseif (!empty($product['store_name'])) {
        $vendorName = $product['store_name'];
    }
    $cleanVendorName = htmlspecialchars(html_entity_decode($vendorName, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');

    $gal_stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC");
    $gal_stmt->execute([$product_id]);
    $gallery = $gal_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($gallery) && !empty($product['image_path'])) {
        $gallery[] = ['image_path' => $product['image_path']];
    }

    $var_stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY id ASC");
    $var_stmt->execute([$product_id]);
    $variants = $var_stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt_discounts = $pdo->prepare("SELECT min_quantity, discount_percentage FROM product_bulk_discounts WHERE product_id = ? ORDER BY min_quantity ASC");
    $stmt_discounts->execute([$product_id]);
    $bulk_discounts = $stmt_discounts->fetchAll(PDO::FETCH_ASSOC);

    $review_stmt = $pdo->prepare("SELECT r.*, u.name as user_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? ORDER BY r.created_at DESC");
    $review_stmt->execute([$product_id]);
    $reviews = $review_stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_reviews = count($reviews);
    $average_rating = 0;
    if ($total_reviews > 0) {
        $average_rating = round(array_sum(array_column($reviews, 'rating')) / $total_reviews, 1);
    }

    $related_products = [];
    if (!empty($product['category'])) {
        $related_stmt = $pdo->prepare("SELECT id, name, price, sale_price, image_path FROM products WHERE category = ? AND id != ? AND status='published' LIMIT 4");
        $related_stmt->execute([$product['category'], $product_id]);
        $related_products = $related_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $has_sale_price = !empty($product['sale_price']) && $product['sale_price'] > 0;
    $base_display_price = $has_sale_price ? $product['sale_price'] : $product['price'];

} catch (Exception $e) {
    echo "Error loading product.";
    exit;
}

$cleanProductName = htmlspecialchars(html_entity_decode($product['name'] ?? '', ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
$cleanCategory = htmlspecialchars(html_entity_decode($product['category'] ?? '', ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
$cleanDesc = html_entity_decode($product['description'] ?? '', ENT_QUOTES, 'UTF-8'); 
?>

<style>
    .star-rating { display: flex; align-items: center; gap: 0.25rem; }
    .stars { display: inline-block; font-size: 1.1rem; color: #d1d5db; position: relative; }
    .stars-inner { position: absolute; top: 0; left: 0; white-space: nowrap; overflow: hidden; }
    .stars-inner::before { content: '★★★★★'; color: #fbbf24; }
    .stars-outer::before { content: '☆☆☆☆☆'; }
    .gallery-thumb-container { transition: all 0.2s ease-in-out; }
    .gallery-thumb-container.active { border-color: var(--theme-color); opacity: 1; box-shadow: 0 0 0 1px var(--theme-color); }
</style>

<main class="container mx-auto py-8 px-4 md:px-8">
    
    <?php if (isset($success_msg)): ?>
        <div class="bg-green-100 text-green-700 p-4 rounded-lg mb-6 font-bold"><?= $success_msg ?></div>
    <?php endif; ?>
    <?php if (isset($error_msg)): ?>
        <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-6 font-bold"><?= $error_msg ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 md:gap-10">
        
        <div class="lg:col-span-5">
            <div class="flex flex-col gap-4 md:sticky md:top-24">
                <div class="w-full aspect-square flex items-center justify-center bg-gray-50 border border-gray-200 rounded-xl overflow-hidden relative shadow-sm" id="main-image-container">
                    <?php if (!empty($gallery)): ?>
                        <img src="../<?= htmlspecialchars($gallery[0]['image_path']) ?>" id="main-product-image" class="w-full h-full object-contain p-2" alt="<?= $cleanProductName ?>">
                    <?php else: ?>
                        <span class="text-gray-400 text-sm">No Image</span>
                    <?php endif; ?>
                </div>
                
                <?php if (count($gallery) > 1): ?>
                    <div class="grid grid-cols-5 gap-2 md:gap-3 w-full">
                        <?php foreach ($gallery as $index => $img): ?>
                            <div class="aspect-square rounded-lg overflow-hidden border-2 cursor-pointer bg-gray-50 gallery-thumb-container <?= $index === 0 ? 'active border-theme' : 'border-transparent hover:border-gray-300 opacity-70 hover:opacity-100' ?>" 
                                 onclick="updateMainImage('../<?= htmlspecialchars($img['image_path']) ?>', this)">
                                <img src="../<?= htmlspecialchars($img['image_path']) ?>" class="w-full h-full object-cover" alt="Thumbnail">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="lg:col-span-7">
            <div class="bg-white p-6 md:p-8 rounded-xl shadow-sm border border-gray-100">
                
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <?php if(!empty($cleanCategory)): ?>
                        <a href="products.php?category=<?= urlencode($product['category']) ?>" class="text-[10px] md:text-xs font-bold text-theme uppercase tracking-widest hover:underline"><?= $cleanCategory ?></a>
                    <?php endif; ?>
                    <span class="text-gray-300 text-xs">|</span>
                    <span class="text-[10px] md:text-xs font-bold text-gray-500 uppercase tracking-widest">
                        Sold by: <span class="text-gray-800"><?= $cleanVendorName ?></span>
                    </span>
                    <?php if($product['role'] === 'superadmin'): ?>
                        <svg class="w-4 h-4 text-blue-500 inline -mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                    <?php endif; ?>
                </div>
                
                <h1 class="text-2xl md:text-4xl font-bold text-gray-900 mb-3 leading-tight"><?= $cleanProductName ?></h1>
                
                <div class="flex items-center gap-4 mb-6">
                    <div class="star-rating">
                        <div class="stars">
                            <div class="stars-outer"></div>
                            <div class="stars-inner" style="width: <?= ($average_rating / 5) * 100 ?>%;"></div>
                        </div>
                    </div>
                    <span class="text-sm text-gray-500">
                        <?= $total_reviews ?> Review<?= $total_reviews !== 1 ? 's' : '' ?>
                    </span>
                </div>

                <div class="mb-6 pb-6 border-b border-gray-100">
                    <div class="flex items-baseline gap-3">
                        <span class="text-3xl md:text-4xl font-black text-gray-900" id="display-price">₦<?= number_format($base_display_price, 2) ?></span>
                        <?php if ($has_sale_price): ?>
                            <span class="text-lg md:text-xl text-gray-400 line-through">₦<?= number_format($product['price'], 2) ?></span>
                            <span class="bg-red-100 text-red-700 text-[10px] md:text-xs font-bold uppercase tracking-wider px-2 py-1 rounded">Sale</span>
                        <?php endif; ?>
                    </div>
                    <div id="bulk-discount-info" class="text-sm font-bold text-theme mt-2"></div>
                </div>
                
                <div class="prose prose-sm max-w-none text-gray-600 mb-8 leading-relaxed">
                    <p><?= nl2br(htmlspecialchars($cleanDesc, ENT_QUOTES, 'UTF-8')) ?></p>
                </div>

                <form id="add-to-cart-form" class="bg-gray-50 p-4 md:p-6 rounded-xl border border-gray-100">
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    
                    <?php if (!empty($variants)): ?>
                        <div class="mb-6">
                            <label class="block text-sm font-bold text-gray-800 mb-3">Select Variant</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <?php foreach ($variants as $index => $var): ?>
                                    <label class="relative border rounded-lg p-3 cursor-pointer hover:border-theme transition flex flex-col variant-label bg-white" data-price-adj="<?= $var['price_adjustment'] ?>" data-stock="<?= $var['stock_quantity'] ?>">
                                        <input type="radio" name="selected_variant_id" value="<?= $var['id'] ?>" class="peer sr-only" <?= $index === 0 ? 'checked' : '' ?> data-variant-name="<?= htmlspecialchars($var['variant_name']) ?>">
                                        <div class="font-bold text-gray-800 text-sm peer-checked:text-theme mb-1"><?= htmlspecialchars($var['variant_name']) ?></div>
                                        
                                        <div class="flex justify-between items-end mt-auto">
                                            <span class="text-[10px] text-gray-500 font-mono"><?= htmlspecialchars($var['sku']) ?></span>
                                            <?php if ($var['price_adjustment'] > 0): ?>
                                                <span class="text-[10px] font-bold text-gray-500">+₦<?= number_format($var['price_adjustment']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="absolute inset-0 border-2 border-transparent peer-checked:border-theme peer-checked:shadow-sm rounded-lg pointer-events-none transition"></div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="flex flex-wrap sm:flex-nowrap items-center gap-3 md:gap-4">
                        <div class="flex items-center bg-white border border-gray-300 rounded-lg overflow-hidden h-12 w-full sm:w-auto">
                            <button type="button" class="px-4 py-2 hover:bg-gray-100 font-bold text-gray-600 transition w-full sm:w-auto" id="qty-minus">-</button>
                            <input type="number" id="quantity" name="quantity" value="<?= $product['min_order_quantity'] ?>" min="<?= $product['min_order_quantity'] ?>" class="w-16 text-center font-bold focus:outline-none border-x border-gray-200" aria-label="Quantity">
                            <button type="button" class="px-4 py-2 hover:bg-gray-100 font-bold text-gray-600 transition w-full sm:w-auto" id="qty-plus">+</button>
                        </div>
                        
                        <button type="submit" id="add-to-cart-btn" class="flex-1 w-full sm:w-auto bg-theme bg-theme-hover text-white font-bold py-3 px-6 rounded-lg shadow-md transition transform hover:-translate-y-0.5 h-12 flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            Add to Cart
                        </button>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row justify-between sm:items-center mt-4 gap-2">
                        <p id="stock-status" class="text-xs font-bold text-green-600 flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            In Stock
                        </p>
                        <?php if ($product['min_order_quantity'] > 1): ?>
                            <p class="text-[10px] md:text-xs text-gray-500 font-bold">Min order: <?= $product['min_order_quantity'] ?> units.</p>
                        <?php endif; ?>
                    </div>
                    
                    <div id="add-to-cart-message" class="hidden mt-4 p-3 rounded-lg text-sm font-bold text-center"></div>
                </form>
            </div>
        </div>
    </div>

</main>

<script>
function updateMainImage(src, thumbElement) {
    document.getElementById('main-product-image').src = src;
    document.querySelectorAll('.gallery-thumb-container').forEach(el => {
        el.classList.remove('active', 'border-theme');
        el.classList.add('border-transparent', 'opacity-70');
    });
    thumbElement.classList.remove('border-transparent', 'opacity-70');
    thumbElement.classList.add('active', 'border-theme');
}

const productData = {
    basePrice: <?= (float)$base_display_price ?>,
    hasSale: <?= $has_sale_price ? 'true' : 'false' ?>,
    minQuantity: <?= (int)$product['min_order_quantity'] ?>,
    bulkDiscounts: <?= json_encode($bulk_discounts) ?>,
    globalStock: <?= (int)$product['stock_quantity'] ?>
};

document.addEventListener('DOMContentLoaded', () => {
    const quantityInput = document.getElementById('quantity');
    const priceDisplay = document.getElementById('display-price');
    const discountInfo = document.getElementById('bulk-discount-info');
    const addToCartBtn = document.getElementById('add-to-cart-btn');
    const stockStatus = document.getElementById('stock-status');
    const variantRadios = document.querySelectorAll('input[name="selected_variant_id"]');

    // SVG STRINGS FOR JAVASCRIPT
    const iconCart = `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>`;
    const iconCheck = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>`;
    const iconError = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
    const iconSpinner = `<svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>`;

    function updateDisplay() {
        let quantity = parseInt(quantityInput.value, 10) || productData.minQuantity;
        if (quantity < productData.minQuantity) quantity = productData.minQuantity;
        
        let variantPriceAdj = 0;
        let activeStock = productData.globalStock;
        
        const activeVariant = document.querySelector('input[name="selected_variant_id"]:checked');
        if (activeVariant) {
            const label = activeVariant.closest('.variant-label');
            variantPriceAdj = parseFloat(label.dataset.priceAdj) || 0;
            activeStock = parseInt(label.dataset.stock) || 0;
        }

        const activeBasePrice = productData.basePrice + variantPriceAdj;
        let finalPrice = activeBasePrice;
        let applicableDiscount = 0;

        for (const tier of productData.bulkDiscounts) {
            if (quantity >= tier.min_quantity) applicableDiscount = parseFloat(tier.discount_percentage);
        }

        if (applicableDiscount > 0) {
            finalPrice = activeBasePrice * (1 - (applicableDiscount / 100));
        }
        
        priceDisplay.innerHTML = `₦${new Intl.NumberFormat('en-NG', { minimumFractionDigits: 2 }).format(finalPrice)}`;

        // Render Stock using pure SVGs
        if (activeStock <= 0) {
            stockStatus.innerHTML = `${iconError} Out of Stock`;
            stockStatus.className = "text-xs font-bold text-red-600 flex items-center gap-1";
            addToCartBtn.disabled = true;
            addToCartBtn.classList.add('opacity-50', 'cursor-not-allowed');
            addToCartBtn.innerHTML = "Out of Stock";
        } else {
            stockStatus.innerHTML = `${iconCheck} ${activeStock} in stock`;
            stockStatus.className = "text-xs font-bold text-green-600 flex items-center gap-1";
            addToCartBtn.disabled = false;
            addToCartBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            addToCartBtn.innerHTML = `${iconCart} Add to Cart`;
        }
    }

    if (variantRadios.length > 0) variantRadios.forEach(radio => radio.addEventListener('change', updateDisplay));
    
    document.getElementById('qty-minus').addEventListener('click', () => {
        let val = parseInt(quantityInput.value, 10);
        if (val > productData.minQuantity) { quantityInput.value = val - 1; updateDisplay(); }
    });

    document.getElementById('qty-plus').addEventListener('click', () => {
        quantityInput.value = parseInt(quantityInput.value, 10) + 1; updateDisplay();
    });

    quantityInput.addEventListener('change', updateDisplay);
    updateDisplay();

    document.getElementById('add-to-cart-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        let optionsPayload = {};
        const activeVariant = document.querySelector('input[name="selected_variant_id"]:checked');
        if (activeVariant) {
            optionsPayload = { "Variant": activeVariant.dataset.variantName };
        }

        const data = { 
            product_id: this.product_id.value, 
            quantity: quantityInput.value, 
            options: optionsPayload 
        };

        addToCartBtn.innerHTML = `${iconSpinner} Adding...`; 
        addToCartBtn.disabled = true;

        fetch('ajax_cart.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
        .then(res => res.json())
        .then(resData => {
            const msgEl = document.getElementById('add-to-cart-message');
            msgEl.classList.remove('hidden', 'bg-red-50', 'text-red-700', 'bg-green-50', 'text-green-700');
            
            if (resData.success) {
                msgEl.textContent = 'Successfully added to cart!';
                msgEl.classList.add('bg-green-50', 'text-green-700');
                if (document.getElementById('cart-count')) {
                     document.getElementById('cart-count').textContent = resData.cart_count;
                }
            } else {
                msgEl.textContent = resData.message || 'Failed to add item.';
                msgEl.classList.add('bg-red-50', 'text-red-700');
            }
            
            setTimeout(() => { 
                msgEl.classList.add('hidden');
                updateDisplay(); 
            }, 3000);
        })
        .catch(err => {
            alert('Network error occurred.');
            updateDisplay();
        });
    });
});
</script>

<?php include 'footer.php'; ?>