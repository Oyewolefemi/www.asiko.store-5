function addToCart(productId) {
    const button = event.target;
    const originalText = button.textContent;
    button.textContent = 'Adding...';
    button.disabled = true;
    
    fetch('add-to-cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `product_id=${productId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            button.textContent = 'Added!';
            setTimeout(() => {
                button.textContent = originalText;
                button.disabled = false;
            }, 2000);
            
            if (typeof updateCartCounter === 'function') {
                updateCartCounter();
            }
        } else {
            alert(data.message || "Failed to add to cart.");
            button.textContent = originalText;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error("Error adding to cart:", error);
        alert("Something went wrong. Please try again.");
        button.textContent = originalText;
        button.disabled = false;
    });
}

// Auto-apply filters with debounce
let searchTimeout;
document.querySelector('input[name="search"]')?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        if (this.value.length > 2 || this.value.length === 0) {
            this.form.submit();
        }
    }, 500);
});