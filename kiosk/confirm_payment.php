<?php
// kiosk/confirm_payment.php
include 'header.php';

// Ensure User is Logged In
$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    header("Location: auth.php");
    exit;
}

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

// Fetch Order Details
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo "<div class='container mx-auto py-20 text-center text-red-600 font-bold'>Order not found.</div>";
    include 'footer.php';
    exit;
}

// Bank Details (Ideally fetch from Env or Settings)
$bank_name = EnvLoader::get('BANK_NAME');
$acct_num = EnvLoader::get('BANK_ACCOUNT_NUMBER');
$acct_name = EnvLoader::get('BANK_ACCOUNT_NAME');
?>

<main class="container mx-auto py-16 px-4">
    <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-lg overflow-hidden border border-gray-100">
        
        <div class="bg-green-600 text-white text-center py-8 px-4">
            <div class="mb-4">
                <svg class="w-16 h-16 mx-auto text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <h1 class="text-3xl font-bold mb-2">Order Placed Successfully!</h1>
            <p class="text-green-100 text-lg">Order #<?= htmlspecialchars($order['order_number']) ?></p>
        </div>

        <div class="p-8 text-center">
            <div class="inline-block bg-yellow-100 text-yellow-800 px-4 py-2 rounded-full font-bold text-sm mb-6">
                Status: Pending Payment Confirmation
            </div>
            
            <p class="text-gray-600 mb-8">
                Thank you for your order. Your items have been reserved. <br>
                Please make a transfer to the account below to complete your purchase.
            </p>

            <div class="bg-gray-50 border-l-4 border-blue-500 p-6 text-left rounded mb-8">
                <h3 class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-2">Bank Transfer Details</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Bank Name</p>
                        <p class="font-bold text-gray-800"><?= htmlspecialchars($bank_name) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Account Number</p>
                        <p class="font-bold text-gray-800 text-xl tracking-widest"><?= htmlspecialchars($acct_num) ?></p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-sm text-gray-500">Account Name</p>
                        <p class="font-bold text-gray-800"><?= htmlspecialchars($acct_name) ?></p>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-200 text-sm text-blue-600">
                    <strong>Note:</strong> Use Order #<?= htmlspecialchars($order['order_number']) ?> as your transfer remark/reference.
                </div>
            </div>

            <div class="flex flex-col md:flex-row gap-4 justify-center">
                <a href="order-history.php" class="px-6 py-3 bg-gray-800 text-white rounded font-bold hover:bg-black transition">
                    View Order Status
                </a>
                <a href="products.php" class="px-6 py-3 border border-gray-300 text-gray-700 rounded font-bold hover:bg-gray-50 transition">
                    Continue Shopping
                </a>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>