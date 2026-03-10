<?php 
require_once 'config.php';
// REMOVED: requireLogin(); -> Open to guests

$bankDetails = getBankDetails();
$orderSuccess = false;
$redirectUrl = '';
$newOrderId = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cartJson = $_POST['cart_data'];
    $address = trim($_POST['address']);
    $paymentMethod = $_POST['payment_method'];
    $cartData = json_decode($cartJson, true);

    // Guest Handling
    $userId = $_SESSION['user_id'] ?? null;
    $guestName = trim($_POST['guest_name'] ?? '');
    $guestEmail = trim($_POST['guest_email'] ?? '');
    $guestPhone = trim($_POST['guest_phone'] ?? '');

    if ($cartData && !empty($address)) {
        // Validate Guest Info if not logged in
        if (!$userId && (empty($guestName) || empty($guestPhone))) {
            $error = "Please provide your name and phone number.";
        } else {
            
            // --- SECURITY: SERVER-SIDE PRICE VALIDATION ---
            // Extract all requested product IDs from the client's cart
            $productIds = array_map(function($item) { return (int)$item['id']; }, $cartData);
            
            if (!empty($productIds)) {
                // Prepare a dynamic IN clause based on the number of items
                $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
                
                // Fetch the actual prices directly from the database
                $stmt = $pdo->prepare("SELECT id, price, sale_price, is_active FROM products WHERE id IN ($placeholders)");
                $stmt->execute($productIds);
                $dbProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Create a secure lookup array [product_id => true_price]
                $securePrices = [];
                foreach ($dbProducts as $dbP) {
                    if ($dbP['is_active']) {
                        // Use sale price if it exists and is greater than 0, otherwise standard price
                        $securePrices[$dbP['id']] = ($dbP['sale_price'] > 0) ? $dbP['sale_price'] : $dbP['price'];
                    }
                }

                $serverCalculatedTotal = 0;
                $validatedCart = [];

                // Rebuild the cart securely using database prices
                foreach ($cartData as $item) {
                    $pid = (int)$item['id'];
                    
                    // Check if the item actually exists and is active in the database
                    if (isset($securePrices[$pid])) {
                        $qty = max(1, (int)$item['quantity']); // Prevent negative or zero quantities
                        $truePrice = $securePrices[$pid];
                        
                        $serverCalculatedTotal += ($truePrice * $qty);
                        
                        $validatedCart[] = [
                            'id' => $pid,
                            'quantity' => $qty,
                            'price_at_time' => $truePrice,
                            'notes' => htmlspecialchars($item['notes'] ?? '', ENT_QUOTES, 'UTF-8') // Sanitize notes
                        ];
                    } else {
                        $error = "One or more items in your cart are invalid or no longer available.";
                        break; // Halt processing
                    }
                }

                // Proceed only if there are no errors and the total is valid
                if (!isset($error) && $serverCalculatedTotal > 0) {
                    
                    $status = ($paymentMethod === 'transfer') ? 'pending_verification' : 'pending';

                    try {
                        $pdo->beginTransaction();

                        // Insert Order using the SERVER calculated total
                        $stmt = $pdo->prepare("INSERT INTO orders (user_id, guest_name, guest_email, guest_phone, total_amount, delivery_address, payment_method, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                        $stmt->execute([$userId, $guestName, $guestEmail, $guestPhone, $serverCalculatedTotal, $address, $paymentMethod, $status]);
                        $newOrderId = $pdo->lastInsertId();

                        // Insert Items using the SERVER validated prices
                        $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price_at_time, notes) VALUES (?, ?, ?, ?, ?)");
                        foreach ($validatedCart as $vItem) {
                            $itemStmt->execute([$newOrderId, $vItem['id'], $vItem['quantity'], $vItem['price_at_time'], $vItem['notes']]);
                        }
                        
                        $pdo->commit();

                        // Set Success Flags for the Popup
                        $orderSuccess = true;
                        $redirectUrl = $userId ? 'orders.php?new_order=1' : 'menu.php?guest_success=1';

                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = "Failed to process order. Please try again.";
                    }
                }
            } else {
                $error = "Your cart data is invalid.";
            }
        }
    } else {
        $error = "Please check your delivery address and cart.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Checkout - <?= htmlspecialchars($settings['site_name'] ?? 'Scrummy') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@400;600;700&family=Material+Symbols+Outlined" rel="stylesheet"/>
    <script> tailwind.config = { theme: { extend: { colors: { primary: '#ec6d13' }, fontFamily: { sans: ['Work Sans'] } } } } </script>
</head>
<body class="bg-gray-50 pb-20 font-sans text-gray-900">

    <?php include 'includes/header.php'; ?>

    <div class="sticky top-[64px] bg-gray-50/95 backdrop-blur z-30 px-4 py-3 flex items-center gap-2">
        <a href="cart.php" class="hover:bg-gray-200 rounded-full p-1 transition"><span class="material-symbols-outlined">arrow_back</span></a>
        <h2 class="font-bold text-lg">Checkout</h2>
    </div>

    <form method="POST" onsubmit="prepareSubmission(event)" class="p-4 space-y-6 max-w-lg mx-auto">
        
        <?php if(isset($error)): ?>
            <div class="bg-red-100 text-red-600 p-3 rounded-xl text-sm font-bold border border-red-200 flex items-center gap-3 shadow-sm">
                <span class="material-symbols-outlined text-red-500">error</span> 
                <span><?= $error ?></span>
            </div>
        <?php endif; ?>

        <?php if(!isLoggedIn()): ?>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 border-l-4 border-l-primary relative overflow-hidden">
            <h3 class="font-bold mb-4 flex items-center gap-2 text-gray-800">
                <span class="material-symbols-outlined text-primary">person</span> 
                Your Details (Guest)
            </h3>
            <div class="space-y-4 relative z-10">
                <input type="text" name="guest_name" required placeholder="Full Name" value="<?= htmlspecialchars($_POST['guest_name'] ?? '') ?>" class="w-full h-12 px-4 rounded-xl bg-gray-50 border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition">
                <input type="tel" name="guest_phone" required placeholder="Phone Number" value="<?= htmlspecialchars($_POST['guest_phone'] ?? '') ?>" class="w-full h-12 px-4 rounded-xl bg-gray-50 border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition">
                <input type="email" name="guest_email" placeholder="Email (Optional)" value="<?= htmlspecialchars($_POST['guest_email'] ?? '') ?>" class="w-full h-12 px-4 rounded-xl bg-gray-50 border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition">
                <div class="text-sm text-gray-500 bg-gray-50 p-3 rounded-xl border border-gray-100">
                    Already have an account? <a href="login.php" class="text-primary font-bold hover:underline">Login here</a> for faster checkout.
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100">
            <h3 class="font-bold mb-4 flex items-center gap-2 text-gray-800">
                <span class="material-symbols-outlined text-primary">local_shipping</span> 
                Delivery Address
            </h3>
            <textarea name="address" required placeholder="Hostel Block, Room Number, Street Name, etc..." class="w-full h-28 p-4 mt-1 rounded-xl bg-gray-50 border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none resize-none transition-all"><?= htmlspecialchars($_POST['address'] ?? (isLoggedIn() ? ($_SESSION['user_address'] ?? '') : '')) ?></textarea>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100">
            <h3 class="font-bold mb-4 flex items-center gap-2 text-gray-800">
                <span class="material-symbols-outlined text-primary">payments</span> 
                Payment Method
            </h3>
            
            <div class="space-y-3">
                <label class="cursor-pointer block">
                    <input type="radio" name="payment_method" value="cod" class="peer sr-only" <?= (empty($_POST['payment_method']) || $_POST['payment_method'] === 'cod') ? 'checked' : '' ?> onchange="togglePayment('cod')">
                    <div class="p-4 rounded-xl border-2 border-transparent bg-gray-50 peer-checked:border-primary peer-checked:bg-orange-50 transition-all flex items-center gap-4 hover:bg-gray-100/80">
                        <div class="size-10 rounded-full bg-white text-green-600 shadow-sm flex items-center justify-center border border-gray-100">
                            <span class="material-symbols-outlined">money</span>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-900">Pay on Delivery</h4>
                            <p class="text-xs text-gray-500">Cash or Transfer to Rider</p>
                        </div>
                        <span class="material-symbols-outlined text-primary ml-auto opacity-0 peer-checked:opacity-100 scale-50 peer-checked:scale-100 transition-all duration-300">check_circle</span>
                    </div>
                </label>

                <label class="cursor-pointer block">
                    <input type="radio" name="payment_method" value="transfer" class="peer sr-only" <?= (isset($_POST['payment_method']) && $_POST['payment_method'] === 'transfer') ? 'checked' : '' ?> onchange="togglePayment('transfer')">
                    <div class="p-4 rounded-xl border-2 border-transparent bg-gray-50 peer-checked:border-primary peer-checked:bg-orange-50 transition-all flex items-center gap-4 hover:bg-gray-100/80">
                        <div class="size-10 rounded-full bg-white text-purple-600 shadow-sm flex items-center justify-center border border-gray-100">
                            <span class="material-symbols-outlined">account_balance</span>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-900">Bank Transfer</h4>
                            <p class="text-xs text-gray-500">Pay now to confirm order</p>
                        </div>
                        <span class="material-symbols-outlined text-primary ml-auto opacity-0 peer-checked:opacity-100 scale-50 peer-checked:scale-100 transition-all duration-300">check_circle</span>
                    </div>
                </label>
            </div>

            <div id="bank-info" class="<?= (isset($_POST['payment_method']) && $_POST['payment_method'] === 'transfer') ? '' : 'hidden' ?> mt-4 p-5 bg-gray-900 text-gray-300 rounded-xl text-sm shadow-inner relative overflow-hidden">
                <div class="absolute top-0 right-0 p-4 opacity-10">
                    <span class="material-symbols-outlined text-6xl">account_balance</span>
                </div>
                <div class="flex justify-between items-center mb-3 pb-3 border-b border-gray-700 relative z-10">
                    <span class="text-gray-400 font-medium">Bank Name</span>
                    <span class="font-bold text-white bg-gray-800 px-3 py-1 rounded-lg"><?= htmlspecialchars($bankDetails['bank']) ?></span>
                </div>
                <div class="flex justify-between items-center mb-2 relative z-10">
                    <span class="text-gray-400 font-medium">Account No</span>
                    <span class="font-bold text-white font-mono text-xl tracking-wider select-all"><?= htmlspecialchars($bankDetails['number']) ?></span>
                </div>
                <div class="mt-4 p-3 bg-orange-500/10 border border-orange-500/20 rounded-lg text-xs text-orange-400 font-bold flex gap-2 items-start relative z-10">
                    <span class="material-symbols-outlined text-[16px]">info</span>
                    <p>Please transfer the exact total amount shown in your cart to avoid delays.</p>
                </div>
            </div>
        </div>

        <input type="hidden" name="cart_data" id="cart_data">
        
        <div class="bg-gray-900 p-4 rounded-2xl shadow-xl flex items-center justify-between sticky bottom-4 z-40">
            <div>
                <p class="text-xs text-gray-400 font-bold uppercase tracking-wider mb-0.5">Order Total</p>
                <p class="text-white font-bold text-xl" id="checkout-total-display">Calculating...</p>
            </div>
            <button type="submit" class="bg-primary hover:bg-[#d55e0d] text-white font-bold h-12 px-6 rounded-xl shadow-lg shadow-primary/40 flex items-center gap-2 transition-all active:scale-[0.98]">
                <span>Complete Order</span>
                <span class="material-symbols-outlined text-sm">arrow_forward</span>
            </button>
        </div>
    </form>

    <?php if ($orderSuccess): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center px-4 bg-gray-900/70 backdrop-blur-md animate-fade-in">
        <div class="bg-white w-full max-w-sm rounded-3xl p-8 shadow-2xl text-center transform transition-all scale-100 animate-bounce-in relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-32 bg-gradient-to-b from-green-50 to-white"></div>
            
            <div class="relative z-10">
                <div class="size-24 bg-green-100 text-green-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner ring-8 ring-green-50">
                    <span class="material-symbols-outlined text-6xl">check_circle</span>
                </div>
                
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Order Successful!</h2>
                
                <div class="bg-gray-50 rounded-xl p-4 mb-6 border border-gray-100">
                    <p class="text-sm text-gray-500 mb-1">Order Reference</p>
                    <p class="font-bold font-mono text-gray-900 text-lg">#<?= str_pad($newOrderId, 6, '0', STR_PAD_LEFT) ?></p>
                </div>

                <p class="text-gray-500 mb-8 leading-relaxed">
                    <?= $paymentMethod == 'cod' ? 'Your order has been sent to the kitchen. Get ready to eat!' : 'We have received your order. Please ensure your transfer is completed.' ?>
                </p>

                <button onclick="finishOrder('<?= $redirectUrl ?>')" class="w-full bg-gray-900 text-white font-bold py-4 rounded-xl hover:bg-black transition-all shadow-lg shadow-gray-900/20 active:scale-95 flex items-center justify-center gap-2">
                    <span>Continue Shopping</span>
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="assets/js/app.js"></script>
    <script>
        // Update the visual total on page load so the user sees their cart total safely
        document.addEventListener('DOMContentLoaded', () => {
            const data = localStorage.getItem('scrummy_cart');
            if (data) {
                const items = JSON.parse(data);
                let visualTotal = 0;
                items.forEach(i => visualTotal += (i.price * i.quantity));
                document.getElementById('checkout-total-display').innerText = '₦' + visualTotal.toLocaleString();
            } else {
                document.getElementById('checkout-total-display').innerText = '₦0';
            }
        });

        function togglePayment(method) {
            const bankInfo = document.getElementById('bank-info');
            if (method === 'transfer') {
                bankInfo.classList.remove('hidden');
            } else {
                bankInfo.classList.add('hidden');
            }
        }

        function prepareSubmission(e) {
            const data = localStorage.getItem('scrummy_cart');
            if(!data || JSON.parse(data).length === 0) {
                e.preventDefault();
                alert("Your cart is empty!");
                return;
            }
            document.getElementById('cart_data').value = data;
        }

        function finishOrder(url) {
            // Clear cart from LocalStorage securely before redirecting
            localStorage.removeItem('scrummy_cart');
            window.location.href = url;
        }
    </script>
    
    <style>
        @keyframes fade-in { from { opacity: 0; backdrop-filter: blur(0px); } to { opacity: 1; backdrop-filter: blur(12px); } }
        @keyframes bounce-in { 0% { transform: scale(0.85) translateY(20px); opacity: 0; } 60% { transform: scale(1.02) translateY(-5px); } 100% { transform: scale(1) translateY(0); opacity: 1; } }
        .animate-fade-in { animation: fade-in 0.4s ease-out forwards; }
        .animate-bounce-in { animation: bounce-in 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; }
    </style>
</body>
</html>