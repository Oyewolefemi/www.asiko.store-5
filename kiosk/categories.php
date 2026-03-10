<?php

include 'header.php';
include 'config.php';
include 'functions.php';

try {
    // Get all categories with product counts and sample images
    $sql = "SELECT 
                p.category,
                COUNT(*) as product_count,
                MIN(p.price) as min_price,
                MAX(p.price) as max_price,
                AVG(p.price) as avg_price,
                (SELECT image_path FROM products p2 WHERE p2.category = p.category AND p2.image_path IS NOT NULL AND p2.image_path != '' LIMIT 1) as sample_image,
                MAX(p.created_at) as latest_product
            FROM products p 
            WHERE p.category IS NOT NULL 
            AND p.category != '' 
            GROUP BY p.category 
            ORDER BY product_count DESC, p.category ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Categories page error: " . $e->getMessage());
    $categories = [];
}

function getCategoryCurrency($amount) {
    return '₦' . number_format($amount, 2);
}

function getCategoryImage($imagePath) {
    if (empty($imagePath)) return null;
    
    $filename = basename($imagePath);
    $webPath = "/kiosk/Red/uploads/" . $filename;
    $serverPath = $_SERVER['DOCUMENT_ROOT'] . $webPath;
    
    if (file_exists($serverPath)) {
        return $webPath;
    }
    
    // FIX applied here for Bug #8: Reverse str_replace arguments to fix the typo instead of causing it.
    $dbPath = str_replace('/kios/', '/kiosk/', $imagePath);
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . $dbPath)) {
        return $dbPath;
    }
    
    return null;
}

function getCategoryIcon($category) {
    // You can customize these icons based on your categories
    $icons = [
        'clothing' => '👕',
        'accessories' => '👜',
        'shoes' => '👠',
        'bags' => '🎒',
        'jewelry' => '💍',
        'electronics' => '📱',
        'home' => '🏠',
        'beauty' => '💄',
        'sports' => '⚽',
        'books' => '📚',
        'default' => '📦'
    ];
    
    $categoryLower = strtolower($category);
    return $icons[$categoryLower] ?? $icons['default'];
}
?>

<style>
/* CSS Variables from your design system */
:root {
    --primary-cyan: rgb(219, 203, 54);
    --primary-cyan-hover: rgb(217, 208, 77);
    --luxury-black: #1a1a1a;
    --luxury-gray: #666666;
    --luxury-light-gray: #f8f8f8;
    --luxury-border: #e8e8e8;
}

/* Reset and Base */
* { box-sizing: border-box; margin: 0; padding: 0; }
body { 
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; 
    line-height: 1.6; 
    color: var(--luxury-black); 
    background: #fefefe;
}

/* Container */
.container { max-width: 1200px; margin: 0 auto; padding: 0 1rem; }

/* Hero Section */
.hero {
    background: linear-gradient(135deg, var(--primary-cyan) 0%, var(--primary-cyan-hover) 100%);
    color: white;
    text-align: center;
    padding: 4rem 1rem;
    margin-bottom: 3rem;
    position: relative;
    overflow: hidden;
}
.hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="20" cy="20" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="80" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="60" r="1" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
    opacity: 0.3;
}
.hero-content {
    position: relative;
    z-index: 1;
}
.hero h1 { 
    font-size: clamp(2.5rem, 6vw, 4rem); 
    font-weight: 300; 
    margin-bottom: 1rem; 
    letter-spacing: -0.02em;
}
.hero p { 
    font-size: 1.2rem; 
    opacity: 0.95; 
    font-weight: 300; 
    max-width: 600px;
    margin: 0 auto;
}

/* Stats Section */
.stats {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 3rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    text-align: center;
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 2rem;
}
.stat {
    padding: 1rem;
}
.stat-number {
    font-size: 2.5rem;
    font-weight: 300;
    color: var(--primary-cyan);
    margin-bottom: 0.5rem;
}
.stat-label {
    font-size: 0.9rem;
    color: var(--luxury-gray);
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 500;
}

/* Categories Grid */
.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 2rem;
    margin-bottom: 3rem;
}

/* Category Card */
.category-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    transition: all 0.4s ease;
    cursor: pointer;
    position: relative;
    border: 1px solid var(--luxury-border);
}
.category-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
}

.category-image {
    position: relative;
    width: 100%;
    height: 200px;
    overflow: hidden;
    background: linear-gradient(135deg, var(--luxury-light-gray), #f0f0f0);
}
.category-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
}
.category-card:hover .category-image img {
    transform: scale(1.1);
}
.category-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    flex-direction: column;
    gap: 1rem;
    background: linear-gradient(135deg, var(--luxury-light-gray), #f0f0f0);
}
.category-icon {
    font-size: 3rem;
    opacity: 0.7;
}
.category-placeholder-text {
    color: var(--luxury-gray);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 500;
}

.category-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, rgba(219, 203, 54, 0.8), rgba(217, 208, 77, 0.8));
    opacity: 0;
    transition: opacity 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}
.category-card:hover .category-overlay {
    opacity: 1;
}
.view-products-btn {
    background: white;
    color: var(--luxury-black);
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 500;
    text-decoration: none;
    transform: translateY(20px);
    transition: transform 0.3s ease;
}
.category-card:hover .view-products-btn {
    transform: translateY(0);
}

.category-info {
    padding: 1.5rem;
}
.category-name {
    font-size: 1.4rem;
    font-weight: 400;
    color: var(--luxury-black);
    margin-bottom: 0.5rem;
    text-transform: capitalize;
}
.category-details {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}
.product-count {
    font-size: 0.9rem;
    color: var(--luxury-gray);
    font-weight: 500;
}
.price-range {
    font-size: 0.85rem;
    color: var(--primary-cyan);
    font-weight: 500;
}
.category-description {
    font-size: 0.9rem;
    color: var(--luxury-gray);
    line-height: 1.5;
}

/* Browse All Section */
.browse-all {
    text-align: center;
    padding: 3rem 1rem;
    background: var(--luxury-light-gray);
    border-radius: 16px;
    margin-bottom: 2rem;
}
.browse-all h3 {
    font-size: 1.5rem;
    font-weight: 300;
    color: var(--luxury-black);
    margin-bottom: 1rem;
}
.browse-all p {
    color: var(--luxury-gray);
    margin-bottom: 2rem;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}
.btn {
    padding: 1rem 2rem;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
}
.btn-primary {
    background: var(--primary-cyan);
    color: white;
}
.btn-primary:hover {
    background: var(--primary-cyan-hover);
    transform: translateY(-2px);
}

/* Empty State */
.empty {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--luxury-gray);
}
.empty h3 {
    font-size: 2rem;
    color: var(--luxury-black);
    font-weight: 300;
    margin-bottom: 1rem;
}
.empty p {
    font-size: 1.1rem;
    margin-bottom: 2rem;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

/* Mobile Optimizations */
@media (max-width: 768px) {
    .container { padding: 0 0.75rem; }
    .hero { padding: 3rem 1rem; }
    .stats { padding: 1.5rem; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 1rem; }
    .categories-grid { 
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
    }
    .category-info { padding: 1.25rem; }
    .browse-all { padding: 2rem 1rem; }
}

@media (max-width: 480px) {
    .hero h1 { font-size: 2rem; }
    .categories-grid { grid-template-columns: 1fr; }
    .stats-grid { grid-template-columns: 1fr; }
    .category-image { height: 160px; }
}

/* Performance optimizations */
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
</style>

<main>
    <div class="hero">
        <div class="hero-content">
            <div class="container">
                <h1>Shop by Category</h1>
                <p>Explore our diverse collection organized by category. Find exactly what you're looking for with ease.</p>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (!empty($categories)): ?>
            <div class="stats">
                <div class="stats-grid">
                    <div class="stat">
                        <div class="stat-number"><?= count($categories) ?></div>
                        <div class="stat-label">Categories</div>
                    </div>
                    <div class="stat">
                        <div class="stat-number"><?= array_sum(array_column($categories, 'product_count')) ?></div>
                        <div class="stat-label">Total Products</div>
                    </div>
                    
                </div>
            </div>

            <div class="categories-grid">
                <?php foreach ($categories as $category): 
                    $category_image = getCategoryImage($category['sample_image']);
                    $category_icon = getCategoryIcon($category['category']);
                ?>
                    <article class="category-card" onclick="window.location.href='products.php?category=<?= urlencode($category['category']) ?>'">
                        <div class="category-image">
                            <?php if ($category_image): ?>
                                <img src="<?= htmlspecialchars($category_image) ?>" 
                                     alt="<?= htmlspecialchars($category['category']) ?>" 
                                     loading="lazy">
                            <?php else: ?>
                                <div class="category-placeholder">
                                    <span class="category-icon"><?= $category_icon ?></span>
                                    <span class="category-placeholder-text">No Preview</span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="category-overlay">
                                <a href="products.php?category=<?= urlencode($category['category']) ?>" class="view-products-btn">
                                    View Products
                                </a>
                            </div>
                        </div>
                        
                        <div class="category-info">
                            <h3 class="category-name"><?= htmlspecialchars($category['category']) ?></h3>
                            
                            <div class="category-details">
                                <span class="product-count">
                                    <?= $category['product_count'] ?> 
                                    <?= $category['product_count'] == 1 ? 'Product' : 'Products' ?>
                                </span>
                                <span class="price-range">
                                    <?= formatCurrency($category['min_price']) ?> - <?= formatCurrency($category['max_price']) ?>
                                </span>
                            </div>
                            
                            <p class="category-description">
                                Discover <?= $category['product_count'] ?> carefully selected 
                                <?= strtolower($category['category']) ?> items starting from 
                                <?= formatCurrency($category['min_price']) ?>.
                            </p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="browse-all">
                <h3>Want to See Everything?</h3>
                <p>Browse our complete collection of products across all categories in one place.</p>
                <a href="products.php" class="btn btn-primary">View All Products</a>
            </div>

        <?php else: ?>
            <div class="empty">
                <h3>No Categories Available</h3>
                <p>We're currently organizing our products. Please check back soon or browse our complete collection.</p>
                <a href="products.php" class="btn btn-primary">View All Products</a>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Add loading states for better UX
document.addEventListener('DOMContentLoaded', function() {
    const categoryCards = document.querySelectorAll('.category-card');
    
    categoryCards.forEach(card => {
        card.addEventListener('click', function(e) {
            // Don't navigate if clicking on overlay button
            if (e.target.classList.contains('view-products-btn')) {
                return;
            }
            
            // Add loading state
            const originalContent = card.innerHTML;
            card.style.opacity = '0.7';
            card.style.pointerEvents = 'none';
            
            // Small delay to show loading state
            setTimeout(() => {
                window.location.href = card.getAttribute('onclick').match(/href='([^']+)'/)[1];
            }, 200);
        });
    });
    
    // Lazy load images for better performance
    const images = document.querySelectorAll('.category-image img');
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.src; // Trigger load
                observer.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
});
</script>

<?php include 'footer.php'; ?>