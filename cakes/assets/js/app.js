const cart = {
    get: () => JSON.parse(localStorage.getItem('scrummy_cart')) || [],
    
    add: (product, qty = 1, notes = '') => {
        let items = cart.get();
        const existing = items.find(i => i.id === product.id);
        
        if (existing) {
            existing.quantity += qty;
            if(notes) existing.notes = notes;
        } else {
            items.push({ ...product, quantity: qty, notes: notes });
        }
        
        localStorage.setItem('scrummy_cart', JSON.stringify(items));
        
        // --- CHANGED: Replaced alert() with showToast() ---
        showToast("Added to cart successfully!");
        
        updateBadge();
    },
    
    remove: (id) => {
        let items = cart.get().filter(i => i.id !== id);
        localStorage.setItem('scrummy_cart', JSON.stringify(items));
        location.reload();
    },
    
    clear: () => {
        localStorage.removeItem('scrummy_cart');
        updateBadge();
    }
};

function updateBadge() {
    const badge = document.getElementById('cart-badge');
    const count = cart.get().length;
    
    if(badge) {
        if (count > 0) {
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }
}

// --- NEW FUNCTION: Modern Toast Notification ---
function showToast(message) {
    // 1. Create the container
    const toast = document.createElement('div');
    
    // 2. Add Tailwind Classes for "Modern Dark Mode" look
    // Fixed position, centered bottom, dark bg, white text, rounded, shadow
    toast.className = `
        fixed bottom-6 left-1/2 transform -translate-x-1/2 
        bg-gray-900 text-white px-6 py-4 rounded-2xl shadow-2xl 
        z-[100] flex items-center gap-3 font-bold text-sm
        transition-all duration-300 scale-90 opacity-0
    `;
    
    // 3. Add Content (Icon + Text)
    toast.innerHTML = `
        <div class="size-6 bg-green-500 rounded-full flex items-center justify-center text-white">
            <span class="material-symbols-outlined text-[16px]">check</span>
        </div>
        <span>${message}</span>
    `;
    
    // 4. Inject into body
    document.body.appendChild(toast);
    
    // 5. Animate In
    requestAnimationFrame(() => {
        toast.classList.remove('scale-90', 'opacity-0');
        toast.classList.add('scale-100', 'opacity-100', '-translate-y-2');
    });

    // 6. Remove automatically after 2.5 seconds
    setTimeout(() => {
        toast.classList.remove('scale-100', 'opacity-100', '-translate-y-2');
        toast.classList.add('scale-90', 'opacity-0', 'translate-y-4');
        setTimeout(() => toast.remove(), 300);
    }, 2500);
}

document.addEventListener('DOMContentLoaded', updateBadge);