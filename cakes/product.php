<?php 
require_once 'config.php';

$id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
$stmt->execute([$id]);
$product = $stmt->fetch();

if(!$product) {
    header("Location: menu.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?= htmlspecialchars($product['name']) ?> - Scrummy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@400;600;700&family=Material+Symbols+Outlined" rel="stylesheet"/>
    <script> tailwind.config = { theme: { extend: { colors: { primary: '#ec6d13' }, fontFamily: { sans: ['Work Sans'] } } } } </script>
</head>
<body class="bg-gray-50 pb-24 font-sans text-gray-900">

    <div class="relative w-full h-[320px]">
        <img src="<?= htmlspecialchars($product['image_url']) ?>" class="w-full h-full object-cover">
        <a href="menu.php" class="absolute top-4 left-4 size-10 bg-black/20 backdrop-blur rounded-full flex items-center justify-center text-white hover:bg-black/40 transition">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
    </div>

    <div class="relative -mt-6 bg-gray-50 rounded-t-3xl px-6 pt-8">
        <div class="flex justify-between items-start mb-2">
            <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($product['name']) ?></h1>
            <span class="text-2xl font-bold text-primary">₦<?= number_format($product['price']) ?></span>
        </div>
        <p class="text-gray-500 leading-relaxed mb-6"><?= htmlspecialchars($product['description']) ?></p>
        
        <div class="h-px bg-gray-200 w-full mb-6"></div>
        
        <h3 class="font-bold mb-2">Special Instructions</h3>
        <textarea id="notes" class="w-full bg-white border border-gray-200 rounded-xl p-3 h-24 resize-none mb-6 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition" placeholder="E.g. No onions, extra spicy..."></textarea>
    </div>

    <div class="fixed bottom-0 w-full bg-white border-t border-gray-100 p-4 pb-8 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)]">
        <div class="flex gap-4 max-w-md mx-auto">
            <div class="flex items-center gap-3 bg-gray-100 rounded-lg p-1">
                <button onclick="updateQty(-1)" class="size-10 flex items-center justify-center rounded bg-white shadow-sm active:bg-gray-50"><span class="material-symbols-outlined">remove</span></button>
                <span id="qty-display" class="font-bold text-lg w-6 text-center">1</span>
                <button onclick="updateQty(1)" class="size-10 flex items-center justify-center rounded bg-white shadow-sm active:bg-gray-50"><span class="material-symbols-outlined">add</span></button>
            </div>
            <button onclick="addToCart()" class="flex-1 bg-primary text-white font-bold rounded-lg h-12 shadow-lg shadow-primary/30 active:scale-95 transition flex items-center justify-center gap-2">
                <span>Add to Order</span>
                <span class="material-symbols-outlined text-[20px]">shopping_bag</span>
            </button>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        let qty = 1;

        function updateQty(change) {
            if (qty + change >= 1) {
                qty += change;
                document.getElementById('qty-display').innerText = qty;
            }
        }

        function addToCart() {
            // Create a secure product object to pass to JS
            const productData = {
                id: <?= $product['id'] ?>,
                name: "<?= htmlspecialchars($product['name']) ?>",
                price: <?= $product['price'] ?>,
                image: "<?= htmlspecialchars($product['image_url']) ?>"
            };
            
            const notes = document.getElementById('notes').value;
            cart.add(productData, qty, notes);
            
            // Redirect logic
            window.location.href = 'menu.php';
        }
    </script>
</body>
</html>