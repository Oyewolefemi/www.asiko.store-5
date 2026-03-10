<?php
// kiosk/checkout.php
ob_start();

include 'header.php'; // Ensure this includes config.php and functions.php

// Ensure User is Logged In
$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    echo "<div class='container mx-auto py-20 text-center'><h2 class='text-2xl font-bold mb-4'>Please Log In</h2><p>You need to be logged in to complete your purchase.</p><a href='auth.php' class='inline-block mt-4 px-6 py-2 bg-black text-white rounded'>Login / Register</a></div>";
    include 'footer.php';
    exit;
}

// Fetch User Email for Receipt
$stmtUser = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
$stmtUser->execute([$user_id]);
$currentUser = $stmtUser->fetch();
$userEmail = $currentUser['email'];
$userName = $currentUser['name'];

// --- FETCH CART ITEMS (Now including Admin/Vendor ID) ---
$stmt = $pdo->prepare("
    SELECT c.id as cart_id, p.id as product_id, p.name, p.price, p.sale_price, p.image_path, p.stock_quantity, p.admin_id, c.quantity, c.selected_options
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = ?
");
$stmt->execute([$user_id]);
$cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($cartItems)) {
    echo "<main class='container mx-auto py-20 text-center'><h2 class='text-2xl font-bold mb-4'>Your cart is empty</h2><a href='products.php' class='text-blue-600 underline font-bold'>Continue Shopping</a></main>";
    include 'footer.php';
    exit;
}

// Fetch bulk discounts...
$product_ids = array_column($cartItems, 'product_id');
$bulk_discounts = [];
if (!empty($product_ids)) {
    $in_query = implode(',', array_fill(0, count($product_ids), '?'));
    $stmt_discounts = $pdo->prepare("SELECT * FROM product_bulk_discounts WHERE product_id IN ($in_query) ORDER BY product_id, min_quantity DESC");
    $stmt_discounts->execute($product_ids);
    while ($row = $stmt_discounts->fetch()) {
        $bulk_discounts[$row['product_id']][] = $row;
    }
}

// Calculate product total
$subtotal = 0;
$total_savings = 0;
foreach ($cartItems as $item) {
    $original_price = (float)$item['price'];
    $effective_unit_price = getApplicablePrice($item, $bulk_discounts);
    $subtotal += $effective_unit_price * $item['quantity'];
    $total_savings += ($original_price - $effective_unit_price) * $item['quantity'];
}

// Fetch saved addresses...
$stmt = $pdo->prepare("SELECT id, full_name, address_line1, city, state FROM addresses WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$savedAddresses = $stmt->fetchAll();

// Payment Config
$paymentConfig = [
    'bank_name' => EnvLoader::get('BANK_NAME'),
    'account_number' => EnvLoader::get('BANK_ACCOUNT_NUMBER'),
    'account_name' => EnvLoader::get('BANK_ACCOUNT_NAME'),
];
$paymentConfigured = !empty($paymentConfig['bank_name']);

// --- HANDLE CHECKOUT POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'checkout') {
    $errors = [];
    
    // Validate
    if (!$paymentConfigured) $errors[] = "Payment system is currently under maintenance.";
    if (empty($_POST['address_option'])) $errors[] = "Please select a delivery address.";
    
    // Address Logic & Delivery Fee Inclusion
    $address_id = 0;
    $shipping_address_text = '';
    $destination_city = '';

    if (empty($errors)) {
        require_once 'get_delivery_fee.php'; // Include delivery fee calculator

        if ($_POST['address_option'] === 'new') {
            if (empty(trim($_POST['new_address_line1'] ?? ''))) {
                $errors[] = "Please fill in your new address details.";
            } else {
                try {
                    $destination_city = sanitize($_POST['new_city']); // Capture city
                    $stmtIns = $pdo->prepare("INSERT INTO addresses (user_id, full_name, address_line1, city, state, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmtIns->execute([
                        $user_id, 
                        sanitize($_POST['new_full_name']), 
                        sanitize($_POST['new_address_line1']), 
                        $destination_city, 
                        sanitize($_POST['new_state'])
                    ]);
                    $address_id = $pdo->lastInsertId();
                    $shipping_address_text = implode(', ', [sanitize($_POST['new_full_name']), sanitize($_POST['new_address_line1']), $destination_city]);
                } catch (Exception $e) { $errors[] = "Address Error: " . $e->getMessage(); }
            }
        } else {
            $address_id = intval($_POST['address_option']);
            $stmt = $pdo->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ?");
            $stmt->execute([$address_id, $user_id]);
            $addr = $stmt->fetch();
            if ($addr) {
                $destination_city = $addr['city']; // Capture city
                $shipping_address_text = $addr['full_name'] . ', ' . $addr['address_line1'] . ', ' . $destination_city;
            } else {
                $errors[] = "Invalid address selected.";
            }
        }

        // Calculate actual delivery fee based on city
        $delivery_fee = getShippingFeeFromApi($destination_city);
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // --- 1. GENERATE ORDER NUMBER ---
            $order_number = '';
            $retries = 5;
            do {
                $order_number = generateLuhnOrderNumber(8); 
                $chk = $pdo->prepare("SELECT id FROM orders WHERE order_number = ?");
                $chk->execute([$order_number]);
            } while ($chk->fetch() && $retries-- > 0);

            if ($retries <= 0) throw new Exception("System busy. Please try again.");

            // --- 2. LOCK PRODUCTS & VALIDATE STOCK ---
            $order_total = 0;
            $order_items_data = [];

            foreach ($cartItems as $item) {
                // LOCK ROW & FETCH LATEST VENDOR ID
                $stmtCheck = $pdo->prepare("SELECT stock_quantity, price, sale_price, admin_id FROM products WHERE id = ? FOR UPDATE");
                $stmtCheck->execute([$item['product_id']]);
                $product_db = $stmtCheck->fetch();

                if (!$product_db || $item['quantity'] > $product_db['stock_quantity']) {
                    throw new Exception("Insufficient stock for item: " . htmlspecialchars($item['name']));
                }
                
                // Use DB price for security
                $item['price'] = $product_db['price'];
                $item['sale_price'] = $product_db['sale_price'];
                
                $price_at_purchase = getApplicablePrice($item, $bulk_discounts);
                $line_total = $price_at_purchase * $item['quantity'];
                $order_total += $line_total;

                // PREPARE DATA FOR INSERTION
                $order_items_data[] = [
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                    'price'      => $price_at_purchase,
                    'vendor_id'  => $product_db['admin_id'], // CRITICAL: Save the vendor ID
                    'name'       => $item['name'],
                    'options'    => $item['selected_options']
                ];
            }
            
            // Calculate final order total (Subtotal + Dynamic Delivery Fee)
            $final_order_total = $order_total + $delivery_fee;

            // --- 3. CREATE ORDER RECORD ---
            $stmtOrder = $pdo->prepare("INSERT INTO orders (user_id, order_number, total_amount, delivery_fee, status, address_id, payment_method, delivery_option, shipping_address, created_at, order_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            
            // Replaced hardcoded 0 with dynamic $delivery_fee and $final_order_total
            $stmtOrder->execute([$user_id, $order_number, $final_order_total, $delivery_fee, 'payment_submitted', $address_id, 'manual', 'standard', $shipping_address_text]);
            $order_id = $pdo->lastInsertId();

            // --- 4. CREATE ORDER DETAILS (WITH VENDOR ID) ---
            $stmtDet = $pdo->prepare("INSERT INTO order_details (order_id, product_id, vendor_id, quantity, price_at_purchase, selected_options) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtStock = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
            
            foreach ($order_items_data as $data) {
                $stmtDet->execute([
                    $order_id, 
                    $data['product_id'], 
                    $data['vendor_id'], 
                    $data['quantity'], 
                    $data['price'], 
                    $data['options']
                ]);
                // Deduct Stock
                $stmtStock->execute([$data['quantity'], $data['product_id']]);
            }
            
            // Clear Cart
            $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$user_id]);
            
            $pdo->commit();

            // --- 5. SEND EMAIL ---
            if (!empty($userEmail)) {
                // Include the central mailer system
                include_once __DIR__ . '/../mailing/mailer.php';
                
                // Build the order items table
                $itemsHtml = "<table style='width:100%; border-collapse: collapse; margin-top:10px;'>";
                foreach ($order_items_data as $it) {
                    $itemsHtml .= "<tr>
                        <td style='padding:8px; border-bottom:1px solid #eee;'>{$it['name']} <small>(x{$it['quantity']})</small></td>
                        <td style='padding:8px; border-bottom:1px solid #eee; text-align:right;'>₦" . number_format($it['price'] * $it['quantity'], 2) . "</td>
                    </tr>";
                }
                $itemsHtml .= "</table>";

                // Build the specific message body for checkout
                $messageBody = "
                    <h2 style='color: #1a1a1a;'>Order Received!</h2>
                    <p>Hi {$userName},</p>
                    <p>We have successfully received your order <strong>#{$order_number}</strong>.</p>
                    
                    <h3>Order Summary</h3>
                    {$itemsHtml}
                    <p style='text-align:right; font-weight:bold; font-size:18px; margin-top:10px;'>Total: ₦" . number_format($final_order_total, 2) . "</p>
                    
                    <div style='background: #f0f9ff; padding: 15px; border-radius: 5px; margin-top: 20px; border-left: 4px solid #0284c7;'>
                        <strong>Payment Instructions (Bank Transfer):</strong><br>
                        Bank: {$paymentConfig['bank_name']}<br>
                        Account: {$paymentConfig['account_number']}<br>
                        Name: {$paymentConfig['account_name']}<br>
                        <small>Please include your Order # as the reference.</small>
                    </div>
                ";

                // Send using the central function
                sendAsikoMail($userEmail, $userName, "Order Received - #{$order_number}", $messageBody);
            }
            
            // Redirect to Confirmation
            header("Location: confirm_payment.php?order_id=" . $order_id);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Transaction failed: " . $e->getMessage();
        }
    }
}

// Initial calculation for display (before form submit)
$grandTotal = $subtotal;
?>

<main class="checkout-container container mx-auto py-10 px-4 md:px-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-8 text-gray-800">Checkout</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p class="font-bold">Please correct the following:</p>
                <ul class="list-disc ml-5">
                    <?php foreach ($errors as $e) echo "<li>$e</li>"; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form id="checkoutForm" method="POST" action="">
            <input type="hidden" name="action" value="checkout">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                
                <div class="space-y-8">
                    <section class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                        <h2 class="text-xl font-bold mb-4 text-gray-700">1. Shipping Address</h2>
                        <div class="space-y-3">
                            <?php foreach ($savedAddresses as $address): ?>
                                <label class="flex items-start p-3 border rounded hover:bg-gray-50 cursor-pointer">
                                    <input type="radio" name="address_option" value="<?= $address['id'] ?>" class="mt-1 mr-3 text-blue-600 focus:ring-blue-500">
                                    <span class="text-sm text-gray-700">
                                        <span class="font-bold block"><?= htmlspecialchars($address['full_name']) ?></span>
                                        <?= htmlspecialchars($address['address_line1'] . ', ' . $address['city'] . ', ' . $address['state']) ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                            
                            <label class="flex items-center p-3 border rounded hover:bg-gray-50 cursor-pointer">
                                <input type="radio" name="address_option" value="new" class="mr-3 text-blue-600 focus:ring-blue-500" <?= empty($savedAddresses) ? 'checked' : '' ?>>
                                <span class="font-bold text-sm text-gray-700">+ Add New Address</span>
                            </label>
                        </div>

                        <div id="newAddressForm" class="mt-4 pt-4 border-t space-y-3 <?= empty($savedAddresses) ? '' : 'hidden' ?>">
                            <input type="text" name="new_full_name" placeholder="Full Name" class="w-full border p-2 rounded text-sm">
                            <input type="text" name="new_address_line1" placeholder="Street Address" class="w-full border p-2 rounded text-sm">
                            <div class="grid grid-cols-2 gap-2">
                                <input type="text" name="new_city" placeholder="City" class="w-full border p-2 rounded text-sm">
                                <input type="text" name="new_state" placeholder="State" class="w-full border p-2 rounded text-sm">
                            </div>
                        </div>
                    </section>

                    <section class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                        <h2 class="text-xl font-bold mb-4 text-gray-700">2. Payment Method</h2>
                        <div class="p-4 bg-blue-50 border border-blue-100 rounded text-blue-800 text-sm">
                            <p class="font-bold mb-1">Bank Transfer</p>
                            <p>You will receive our bank details after you place the order.</p>
                        </div>
                    </section>
                </div>

                <div>
                    <section class="bg-gray-50 p-6 rounded-lg border border-gray-200 sticky top-4">
                        <h2 class="text-xl font-bold mb-4 text-gray-700">Order Summary</h2>
                        <div class="space-y-4 mb-6">
                            <?php foreach ($cartItems as $item): 
                                $effective_unit_price = getApplicablePrice($item, $bulk_discounts);
                            ?>
                                <div class="flex justify-between text-sm">
                                    <div>
                                        <p class="font-semibold text-gray-800"><?= htmlspecialchars($item['name']) ?></p>
                                        <p class="text-gray-500">Qty: <?= intval($item['quantity']) ?></p>
                                    </div>
                                    <p class="font-semibold text-gray-800">₦<?= number_format($effective_unit_price * $item['quantity'], 2) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="border-t pt-4 space-y-2">
                            <div class="flex justify-between text-sm text-gray-600">
                                <span>Subtotal</span>
                                <span>₦<?= number_format($subtotal, 2) ?></span>
                            </div>
                            <div class="flex justify-between text-sm text-gray-600">
                                <span>Delivery Fee</span>
                                <span>Calculated Later</span>
                            </div>
                            <div class="flex justify-between text-xl font-bold text-gray-900 pt-2 border-t mt-2">
                                <span>Total (Estimate)</span>
                                <span>₦<?= number_format($grandTotal, 2) ?></span>
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-black text-white font-bold py-3 px-4 rounded hover:bg-gray-800 transition mt-6 disabled:opacity-50" <?= !$paymentConfigured ? 'disabled' : '' ?>>
                            <?= $paymentConfigured ? 'Place Order' : 'System Offline' ?>
                        </button>
                    </section>
                </div>
            </div>
        </form>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Toggle New Address Form
    const opts = document.querySelectorAll('input[name="address_option"]');
    const form = document.getElementById('newAddressForm');
    const inputs = form.querySelectorAll('input');

    function toggleForm() {
        const isNew = document.querySelector('input[name="address_option"]:checked')?.value === 'new';
        form.classList.toggle('hidden', !isNew);
        inputs.forEach(i => i.required = isNew);
    }

    opts.forEach(opt => opt.addEventListener('change', toggleForm));
    toggleForm(); // Init
});
</script>

<?php include 'footer.php'; ob_end_flush(); ?>