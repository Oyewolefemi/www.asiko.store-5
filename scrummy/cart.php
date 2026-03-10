<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Cart - Scrummy Nummy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@400;600;700&family=Material+Symbols+Outlined" rel="stylesheet"/>
    <script> tailwind.config = { theme: { extend: { colors: { primary: '#ec6d13' }, fontFamily: { sans: ['Work Sans'] } } } } </script>
</head>
<body class="bg-gray-50 pb-32 font-sans text-gray-900">

    <?php include 'includes/header.php'; ?>

    <div class="p-4">
        <h2 class="font-bold text-lg mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">shopping_cart</span>
            Current Order
        </h2>
        <div id="cart-items" class="space-y-3">
            </div>
    </div>

    <div id="cart-footer" class="hidden fixed bottom-0 w-full bg-white border-t border-gray-100 p-4 pb-8 z-20">
        <div class="flex justify-between items-center mb-4">
            <span class="text-gray-500 font-medium">Total</span>
            <span id="cart-total" class="text-2xl font-bold text-gray-900">₦0</span>
        </div>
        <a href="checkout.php" class="flex justify-center items-center w-full bg-primary text-white font-bold h-14 rounded-xl shadow-lg shadow-primary/30 active:scale-[0.98] transition">
            Proceed to Checkout
        </a>
    </div>

    <div id="empty-state" class="hidden flex flex-col items-center justify-center py-20 text-center px-6">
        <div class="size-20 bg-gray-100 rounded-full flex items-center justify-center mb-4 text-gray-400">
            <span class="material-symbols-outlined text-4xl">remove_shopping_cart</span>
        </div>
        <p class="text-gray-500">Your cart is currently empty.</p>
        <a href="menu.php" class="mt-6 px-6 py-3 bg-white border border-gray-200 rounded-xl font-bold text-primary shadow-sm hover:bg-gray-50">Start Ordering</a>
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        // Render Cart items from LocalStorage
        const items = cart.get();
        const container = document.getElementById('cart-items');
        const footer = document.getElementById('cart-footer');
        const emptyState = document.getElementById('empty-state');

        if(items.length === 0) {
            container.innerHTML = '';
            footer.classList.add('hidden');
            emptyState.classList.remove('hidden');
        } else {
            emptyState.classList.add('hidden');
            footer.classList.remove('hidden');
            
            let html = '';
            let total = 0;

            items.forEach(i => {
                total += i.price * i.quantity;
                html += `
                <div class="bg-white p-3 rounded-xl shadow-sm border border-gray-100 flex gap-3">
                    <img src="${i.image}" class="size-20 rounded-lg bg-gray-100 object-cover">
                    <div class="flex-1 flex flex-col justify-between">
                        <div class="flex justify-between items-start">
                            <h4 class="font-bold line-clamp-1">${i.name}</h4>
                            <button onclick="cart.remove(${i.id})" class="text-gray-300 hover:text-red-500 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">delete</span>
                            </button>
                        </div>
                        ${i.notes ? `<p class="text-xs text-orange-600 italic line-clamp-1">"${i.notes}"</p>` : ''}
                        <div class="flex justify-between items-end mt-2">
                            <span class="font-bold text-primary">₦${i.price.toLocaleString()}</span>
                            <span class="text-sm font-bold text-gray-500 bg-gray-100 px-2 py-1 rounded">x${i.quantity}</span>
                        </div>
                    </div>
                </div>`;
            });
            
            container.innerHTML = html;
            document.getElementById('cart-total').innerText = '₦' + total.toLocaleString();
        }
    </script>
</body>
</html>