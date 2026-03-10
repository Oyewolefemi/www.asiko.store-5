<?php
// kiosk/cart.php
include 'header.php'; 
include 'config.php';
include 'functions.php';

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    ob_clean(); 
    header("Location: auth.php");
    exit;
}

echo '<link rel="stylesheet" href="assets/css/cart.css?v=' . time() . '">';

// Retrieve cart items
try {
    $stmt = $pdo->prepare("
        SELECT 
            c.id as cart_id, 
            p.id as product_id, 
            p.name, 
            p.price, 
            p.sale_price,
            p.image_path, 
            c.quantity,
            c.selected_options,
            p.stock_quantity,
            p.min_order_quantity
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $product_ids = array_column($cartItems, 'product_id');
    $bulk_discounts = [];
    if (!empty($product_ids)) {
        $in_query = implode(',', array_fill(0, count($product_ids), '?'));
        $stmt_discounts = $pdo->prepare("
            SELECT * FROM product_bulk_discounts 
            WHERE product_id IN ($in_query) 
            ORDER BY product_id, min_quantity DESC
        ");
        $stmt_discounts->execute($product_ids);
        while ($row = $stmt_discounts->fetch()) {
            $bulk_discounts[$row['product_id']][] = $row;
        }
    }

} catch (Exception $e) {
    error_log("Cart page error: " . $e->getMessage());
    $cartItems = [];
}

$total = 0;
$total_savings = 0;
foreach ($cartItems as $item) {
    $base_price = $item['price'];
    $effective_unit_price = getApplicablePrice($item, $bulk_discounts);
    
    $total += $effective_unit_price * $item['quantity'];
    $total_savings += ($base_price - $effective_unit_price) * $item['quantity'];
}

?>

<main class="container mx-auto py-10 px-4">
    <div class="main-content-box">
        <h1 class="section-title">Your Shopping Cart</h1>
        
        <?php if (!empty($cartItems)): ?>
            <div class="cart-items-container">
                <?php foreach ($cartItems as $item): ?>
                    <?php 
                        $imagePath = getProductImage($item['image_path']); 
                        $options = json_decode($item['selected_options'] ?? '', true);
                        
                        $effective_price = getApplicablePrice($item, $bulk_discounts);
                        $line_total = $effective_price * $item['quantity'];
                        $original_line_total = $item['price'] * $item['quantity'];
                        $has_discount = ($item['sale_price'] > 0 || $effective_price < $item['price']);
                    ?>
                    <div class="cart-item" data-cart-id="<?= $item['cart_id'] ?>" data-product-id="<?= $item['product_id'] ?>" data-stock="<?= $item['stock_quantity'] ?>" data-min-qty="<?= $item['min_order_quantity'] ?>">
                        
                        <div class="cart-item-image-wrapper">
                            <img src="<?= htmlspecialchars($imagePath); ?>" alt="<?= htmlspecialchars($item['name']); ?>" class="cart-item-image">
                        </div>
                        
                        <div class="cart-item-details">
                            <h2 class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($item['name']); ?></h2>
                            
                            <?php if (is_array($options) && !empty($options)): ?>
                                <div class="text-sm text-gray-500 options-text">
                                    <?php 
                                    $option_strings = [];
                                    foreach ($options as $key => $value) {
                                        $option_strings[] = htmlspecialchars($key) . ': ' . htmlspecialchars($value);
                                    }
                                    echo implode(', ', $option_strings);
                                    ?>
                                </div>
                            <?php endif; ?>

                            <div class="item-quantity-price-block">
                                <div class="cart-item-actions">
                                    <button data-action="decrease" class="decrease-btn quantity-btn">-</button>
                                    <input type="number" value="<?= $item['quantity']; ?>" class="quantity-input" data-min="<?= max(1, $item['min_order_quantity']) ?>" readonly>
                                    <button data-action="increase" class="increase-btn quantity-btn">+</button>
                                </div>
                                <div class="price-remove-block">
                                    <p class="text-xl font-bold price-main">
                                        ₦<?= number_format($line_total, 2); ?>
                                    </p>
                                    <?php if ($has_discount && $line_total < $original_line_total): ?>
                                        <p class="text-sm text-gray-500 line-through">
                                            ₦<?= number_format($original_line_total, 2) ?>
                                        </p>
                                    <?php endif; ?>
                                    <button data-action="remove" class="remove-btn text-red-600 hover:text-red-800 font-bold ml-4">Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="cart-summary bg-gray-50 p-6 rounded-lg mt-6 text-right">
                <?php if ($total_savings > 0): ?>
                    <p class="text-lg font-semibold text-green-600 mb-2">Total Savings: ₦<?= number_format($total_savings, 2); ?></p>
                <?php endif; ?>
                <p class="text-2xl font-bold mb-4">Subtotal (<?= count($cartItems) ?> item<?= count($cartItems) == 1 ? '' : 's' ?>): ₦<?= number_format($total, 2); ?></p>
                <a href="checkout.php" class="btn btn-primary inline-block bg-blue-600 text-white font-bold py-3 px-8 rounded hover:bg-blue-700 transition">
                    Proceed to Checkout
                </a>
            </div>

        <?php else: ?>
            <div class="empty-state text-center py-12">
                <h3 class="text-2xl text-gray-600 mb-6">Your cart is empty.</h3>
                <a href="products.php" class="btn btn-primary inline-block bg-blue-600 text-white font-bold py-3 px-8 rounded hover:bg-blue-700 transition">
                    Continue Shopping
                </a>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const updateCart = async (action, cartId, quantity = null) => {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('cart_id', cartId);
            if (quantity !== null) formData.append('quantity', quantity);
            
            try {
                const response = await fetch('ajax_cart.php', { method: 'POST', body: formData });
                if (response.ok) window.location.reload();
            } catch (error) {
                console.error('Failed to update cart', error);
                alert("Failed to update cart. Please try again.");
            }
        };

        document.querySelectorAll('.increase-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const item = e.target.closest('.cart-item');
                const input = item.querySelector('.quantity-input');
                const stock = parseInt(item.dataset.stock);
                let val = parseInt(input.value);
                if (val < stock) {
                    input.value = val + 1;
                    updateCart('update_quantity', item.dataset.cartId, input.value);
                } else {
                    alert("Maximum stock reached for this item.");
                }
            });
        });

        document.querySelectorAll('.decrease-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const item = e.target.closest('.cart-item');
                const input = item.querySelector('.quantity-input');
                const min = parseInt(input.dataset.min || 1);
                let val = parseInt(input.value);
                if (val > min) {
                    input.value = val - 1;
                    updateCart('update_quantity', item.dataset.cartId, input.value);
                }
            });
        });

        document.querySelectorAll('.remove-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                if (confirm("Are you sure you want to remove this item?")) {
                    const item = e.target.closest('.cart-item');
                    updateCart('remove', item.dataset.cartId);
                }
            });
        });
    });
</script>

<?php include 'footer.php'; ?>