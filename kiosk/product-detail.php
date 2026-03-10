<?php
// kiosk/product-detail.php
include 'header.php';
include 'config.php';
include 'functions.php';

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['user_id'] ?? 0;

if ($product_id <= 0) {
    printError("Invalid product ID.");
    include 'footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    if ($user_id > 0) {
        $rating = intval($_POST['rating'] ?? 0);
        $review_text = sanitize($_POST['review_text'] ?? '');

        if ($rating >= 1 && $rating <= 5) {
            try {
                $stmt_check = $pdo->prepare("SELECT id FROM reviews WHERE product_id = ? AND user_id = ?");
                $stmt_check->execute([$product_id, $user_id]);
                
                if ($stmt_check->fetch()) {
                    printError("You have already submitted a review for this product.");
                } else {
                    $stmt = $pdo->prepare("INSERT INTO reviews (product_id, user_id, rating, review_text, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$product_id, $user_id, $rating, $review_text]);
                    printSuccess("Thank you for your review!");
                }
            } catch (Exception $e) { printError("Error submitting your review: " . $e->getMessage()); }
        } else {
            printError("Please select a valid rating.");
        }
    } else {
        printError("You must be logged in to submit a review.");
    }
}

try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        printError("Product not found.");
        include 'footer.php';
        exit;
    }

    $stmt_discounts = $pdo->prepare("SELECT min_quantity, discount_percentage FROM product_bulk_discounts WHERE product_id = ? ORDER BY min_quantity ASC");
    $stmt_discounts->execute([$product_id]);
    $bulk_discounts = $stmt_discounts->fetchAll(PDO::FETCH_ASSOC);

    $review_stmt = $pdo->prepare("
        SELECT r.*, u.name as user_name 
        FROM reviews r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.product_id = ? 
        ORDER BY r.created_at DESC
    ");
    $review_stmt->execute([$product_id]);
    $reviews = $review_stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_reviews = count($reviews);
    $average_rating = 0;
    if ($total_reviews > 0) {
        $total_rating = array_sum(array_column($reviews, 'rating'));
        $average_rating = round($total_rating / $total_reviews, 1);
    }

    $user_has_reviewed = false;
    if ($user_id > 0) {
        foreach ($reviews as $review) {
            if ($review['user_id'] == $user_id) { $user_has_reviewed = true; break; }
        }
    }

    $related_products = [];
    if (!empty($product['category'])) {
        $related_stmt = $pdo->prepare("SELECT id, name, price, sale_price, image_path FROM products WHERE category = ? AND id != ? LIMIT 4");
        $related_stmt->execute([$product['category'], $product_id]);
        $related_products = $related_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $options = $product['options'] ? json_decode($product['options'], true) : [];
    $has_sale_price = !empty($product['sale_price']) && $product['sale_price'] > 0;
    $display_price = $has_sale_price ? $product['sale_price'] : $product['price'];

} catch (Exception $e) {
    printError("Could not load product details.");
    $product = null;
}

// FIX: Clean product details for display
$cleanProductName = htmlspecialchars(html_entity_decode($product['name'] ?? '', ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
$cleanCategory = htmlspecialchars(html_entity_decode($product['category'] ?? '', ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
$cleanDesc = html_entity_decode($product['description'] ?? '', ENT_QUOTES, 'UTF-8'); // Allow raw string to be nl2br'd safely below
?>

<style>
    /* Button styles to match the header */
    .btn { padding: 8px 20px; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; border: 1px solid transparent; }
    .btn-primary { background: var(--primary-color); color: white; border-color: var(--primary-color); }
    .btn-primary:hover { background: var(--primary-color-hover); border-color: var(--primary-color-hover); transform: translateY(-1px); }
    .btn-primary:disabled { background: #ccc; border-color: #ccc; cursor: not-allowed; transform: none; }
    .quantity-selector { display: flex; align-items: center; }
    .quantity-btn { width: 36px; height: 36px; border: 1px solid var(--border-color); background: var(--luxury-light-gray); cursor: pointer; font-size: 1.1rem; }
    .quantity-input { width: 50px; height: 36px; text-align: center; border: 1px solid var(--border-color); border-left: none; border-right: none; }
    .star-rating { display: flex; align-items: center; gap: 0.25rem; }
    .stars { display: inline-block; font-size: 1.1rem; color: #d1d5db; position: relative; }
    .stars-inner { position: absolute; top: 0; left: 0; white-space: nowrap; overflow: hidden; }
    .stars-inner::before { content: '★★★★★'; color: #fbbf24; }
    .stars-outer::before { content: '☆☆☆☆☆'; }
    .rating-form .rating-stars { display: flex; flex-direction: row-reverse; justify-content: flex-end; }
    .rating-form .rating-stars input { display: none; }
    .rating-form .rating-stars label { font-size: 1.5rem; color: #d1d5db; cursor: pointer; transition: color 0.2s; }
    .rating-form .rating-stars input:checked ~ label,
    .rating-form .rating-stars label:hover,
    .rating-form .rating-stars label:hover ~ label { color: var(--primary-color); }
    .price-original { text-decoration: line-through; color: #9ca3af; font-size: 1rem; margin-right: 0.5rem; }
    .price-current { font-size: 1.75rem; font-weight: 700; color: var(--primary-color); }
    .price-discounted { font-size: 1.75rem; font-weight: 700; color: #dc2626; }
</style>

<main class="container mx-auto py-8 px-4 md:px-8">
    <?php if ($product): ?>
        <div class="grid grid-cols-1 md:grid-cols-12 gap-8">
            <div class="md:col-span-5">
                <?php $product_image = getProductImage($product['image_path']); ?>
                <div class="bg-white rounded-lg p-4 border border-gray-100 flex items-center justify-center">
                    <?php if ($product_image): ?>
                        <img src="<?= htmlspecialchars($product_image) ?>" alt="<?= $cleanProductName ?>" class="w-full h-auto max-h-96 object-contain rounded-md" loading="lazy">
                    <?php else: ?>
                        <div class="w-full h-64 bg-gray-50 flex items-center justify-center rounded-lg">
                            <span class="text-gray-400 text-sm">No Image</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="md:col-span-7">
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
                    <?php if(!empty($cleanCategory)): ?>
                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-1"><?= $cleanCategory ?></p>
                    <?php endif; ?>
                    
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2 leading-tight"><?= $cleanProductName ?></h1>
                    
                    <div class="flex items-center gap-3 mb-4">
                        <div class="star-rating">
                            <div class="stars">
                                <div class="stars-outer"></div>
                                <div class="stars-inner" style="width: <?= ($average_rating / 5) * 100 ?>%;"></div>
                            </div>
                        </div>
                        <span class="text-sm text-blue-600 hover:underline cursor-pointer">
                            <?= $total_reviews ?> review<?= $total_reviews !== 1 ? 's' : '' ?>
                        </span>
                    </div>

                    <div class="mb-5 pb-5 border-b border-gray-100">
                        <div class="flex items-baseline">
                            <?php if ($has_sale_price): ?>
                                <span class="price-discounted" id="display-price">₦<?= number_format($product['sale_price'], 2) ?></span>
                                <span class="price-original ml-3">₦<?= number_format($product['price'], 2) ?></span>
                            <?php else: ?>
                                <span class="price-current" id="display-price">₦<?= number_format($product['price'], 2) ?></span>
                            <?php endif; ?>
                        </div>
                        <div id="bulk-discount-info" class="text-sm font-medium text-green-600 mt-1"></div>
                    </div>
                    
                    <div class="prose prose-sm max-w-none text-gray-600 mb-6">
                        <p><?= nl2br(htmlspecialchars($cleanDesc, ENT_QUOTES, 'UTF-8')) ?></p>
                    </div>

                    <form id="add-to-cart-form">
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                        
                        <?php if (!empty($options)): ?>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                                <?php foreach ($options as $option_name => $option_values): ?>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars(html_entity_decode($option_name, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></label>
                                        <select name="options[<?= htmlspecialchars($option_name) ?>]" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md border">
                                            <option value="">Choose...</option>
                                            <?php foreach ($option_values as $value): ?>
                                                <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="flex items-center gap-4 mt-4">
                            <div class="quantity-selector">
                                <button type="button" class="quantity-btn rounded-l-md hover:bg-gray-200" id="qty-minus">-</button>
                                <input type="number" id="quantity" name="quantity" value="<?= $product['min_order_quantity'] ?>" min="<?= $product['min_order_quantity'] ?>" class="quantity-input focus:ring-0" aria-label="Quantity">
                                <button type="button" class="quantity-btn rounded-r-md hover:bg-gray-200" id="qty-plus">+</button>
                            </div>
                            <button type="submit" id="add-to-cart-btn" class="btn btn-primary flex-grow h-[38px]">Add to Cart</button>
                        </div>
                        
                        <?php if ($product['min_order_quantity'] > 1): ?>
                            <p class="text-xs text-gray-500 mt-2">Min order: <?= $product['min_order_quantity'] ?> units.</p>
                        <?php endif; ?>
                        
                        <div id="add-to-cart-message" class="mt-2 text-sm font-medium"></div>
                    </form>
                </div>
            </div>
        </div>

        <div class="mt-12 max-w-4xl">
            <h2 class="text-xl font-bold text-gray-900 mb-4 border-b pb-2">Customer Reviews</h2>
            <div class="space-y-4">
                <?php if (empty($reviews)): ?>
                    <p class="text-gray-500 text-sm">No reviews yet.</p>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="pb-4 border-b border-gray-100">
                            <div class="flex items-center mb-1">
                                <div class="stars text-sm mr-2">
                                    <div class="stars-outer"></div>
                                    <div class="stars-inner" style="width: <?= ($review['rating'] / 5) * 100 ?>%;"></div>
                                </div>
                                <span class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($review['user_name']) ?></span>
                            </div>
                            <p class="text-xs text-gray-400 mb-2"><?= date('M j, Y', strtotime($review['created_at'])) ?></p>
                            <p class="text-sm text-gray-700"><?= nl2br(htmlspecialchars(html_entity_decode($review['review_text'], ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8')) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($related_products)): ?>
            <div class="mt-12 border-t pt-8">
                <h2 class="text-xl font-bold text-gray-900 mb-6">You Might Also Like</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <?php foreach ($related_products as $related_product): 
                        $relProductName = htmlspecialchars(html_entity_decode($related_product['name'], ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
                    ?>
                        <a href="product-detail.php?id=<?= $related_product['id'] ?>" class="block bg-white border border-gray-100 rounded-lg overflow-hidden hover:shadow-md transition-all group">
                            <div class="w-full h-40 bg-gray-50 overflow-hidden relative">
                                <?php $related_image = getProductImage($related_product['image_path']); ?>
                                <img src="<?= htmlspecialchars($related_image) ?>" alt="<?= $relProductName ?>" class="w-full h-full object-contain p-2 group-hover:scale-105 transition-transform duration-300" loading="lazy">
                            </div>
                            <div class="p-3">
                                <h3 class="font-medium text-gray-900 text-sm truncate mb-1"><?= $relProductName ?></h3>
                                <?php 
                                $rel_has_sale = !empty($related_product['sale_price']) && $related_product['sale_price'] > 0;
                                $rel_price = $rel_has_sale ? $related_product['sale_price'] : $related_product['price'];
                                ?>
                                <div>
                                    <span class="text-red-600 font-bold text-sm">₦<?= number_format($rel_price, 2) ?></span>
                                    <?php if($rel_has_sale): ?>
                                        <span class="line-through text-gray-400 text-xs ml-1">₦<?= number_format($related_product['price'], 2) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</main>

<script>
const productData = {
    basePrice: <?= (float)$product['price'] ?>,
    salePrice: <?= $has_sale_price ? (float)$product['sale_price'] : 'null' ?>,
    minQuantity: <?= (int)$product['min_order_quantity'] ?>,
    bulkDiscounts: <?= json_encode($bulk_discounts) ?>
};

document.addEventListener('DOMContentLoaded', () => {
    // JavaScript Logic remains exactly identical
    const quantityInput = document.getElementById('quantity');
    const priceDisplay = document.getElementById('display-price');
    const discountInfo = document.getElementById('bulk-discount-info');
    const addToCartBtn = document.getElementById('add-to-cart-btn');
    const cartForm = document.getElementById('add-to-cart-form');

    document.getElementById('qty-minus').addEventListener('click', () => {
        let currentVal = parseInt(quantityInput.value, 10);
        if (currentVal > productData.minQuantity) { quantityInput.value = currentVal - 1; updatePriceDisplay(); }
    });

    document.getElementById('qty-plus').addEventListener('click', () => {
        let currentVal = parseInt(quantityInput.value, 10);
        quantityInput.value = currentVal + 1; updatePriceDisplay();
    });

    quantityInput.addEventListener('change', updatePriceDisplay);

    function updatePriceDisplay() {
        let quantity = parseInt(quantityInput.value, 10);
        if (isNaN(quantity) || quantity < productData.minQuantity) { quantity = productData.minQuantity; quantityInput.value = quantity; }

        const basePrice = productData.salePrice !== null ? productData.salePrice : productData.basePrice;
        let applicableDiscount = 0;

        for (const tier of productData.bulkDiscounts) {
            if (quantity >= tier.min_quantity) { applicableDiscount = parseFloat(tier.discount_percentage); }
        }
        
        let finalPrice = basePrice;

        if (applicableDiscount > 0) {
            finalPrice = basePrice * (1 - (applicableDiscount / 100));
            let totalSaving = (productData.basePrice - finalPrice) * quantity;
            discountInfo.textContent = `Bulk Savings: ₦${new Intl.NumberFormat('en-NG').format(totalSaving)} (${applicableDiscount}% off)`;
        } else if (productData.salePrice !== null) { discountInfo.textContent = 'On Sale!'; } else { discountInfo.textContent = ''; }
        
        let priceToShow = productData.salePrice !== null ? productData.salePrice : productData.basePrice;
        let originalPrice = productData.basePrice;

        if(applicableDiscount > 0) { priceToShow = priceToShow * (1 - (applicableDiscount / 100)); }

        let priceHtml = `<span class="${applicableDiscount > 0 || productData.salePrice !== null ? 'price-discounted' : 'price-current'}">₦${new Intl.NumberFormat('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(priceToShow)}</span>`;

        if (applicableDiscount > 0 || productData.salePrice !== null) {
            priceHtml += `<span class="price-original ml-3">₦${new Intl.NumberFormat('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(originalPrice)}</span> `;
        }
        priceDisplay.innerHTML = priceHtml;
    }

    cartForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const selects = cartForm.querySelectorAll('select[name^="options["]');
        let allOptionsSelected = true;
        let selectedOptions = {};

        selects.forEach(select => {
            if (!select.value) {
                allOptionsSelected = false; select.classList.add('border-red-500');
            } else {
                select.classList.remove('border-red-500');
                let name = select.name.match(/\[(.*?)\]/)[1];
                selectedOptions[name] = select.value;
            }
        });

        if (!allOptionsSelected) { showCartMessage('Please select all product options.', 'error'); return; }
        
        const formData = new FormData(cartForm);
        const data = { product_id: formData.get('product_id'), quantity: formData.get('quantity'), options: selectedOptions };

        const originalText = addToCartBtn.textContent;
        addToCartBtn.textContent = 'Adding...'; addToCartBtn.disabled = true;

        fetch('ajax_cart.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
        .then(res => res.json())
        .then(resData => {
            if (resData.success) {
                showCartMessage('Added to cart!', 'success');
                addToCartBtn.textContent = 'Added!';
                if (document.getElementById('cart-count')) {
                     const badge = document.getElementById('cart-count');
                     badge.textContent = resData.cart_count; badge.classList.remove('hidden');
                }
                setTimeout(() => { addToCartBtn.textContent = originalText; addToCartBtn.disabled = false; }, 2000);
            } else {
                showCartMessage(resData.message || 'Failed to add item.', 'error');
                addToCartBtn.textContent = originalText; addToCartBtn.disabled = false;
            }
        })
        .catch(err => {
            showCartMessage('A network error occurred.', 'error');
            addToCartBtn.textContent = originalText; addToCartBtn.disabled = false;
        });
    });

    function showCartMessage(message, type) {
        const msgEl = document.getElementById('add-to-cart-message');
        msgEl.textContent = message;
        msgEl.className = type === 'success' ? 'mt-2 text-sm text-green-600' : 'mt-2 text-sm text-red-600';
    }

    updatePriceDisplay();
});
</script>

<?php include 'footer.php'; ?>