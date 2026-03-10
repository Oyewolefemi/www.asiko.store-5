<?php
require 'db.php';
include 'header.php';

// Fetch all categories with product counts and details
$categoriesQuery = "
    SELECT 
        c.*,
        parent.name as parent_name,
        COUNT(p.id) as product_count,
        (SELECT COUNT(*) FROM categories sub WHERE sub.parent_id = c.id) as subcategory_count
    FROM categories c
    LEFT JOIN categories parent ON c.parent_id = parent.id
    LEFT JOIN products p ON c.id = p.category_id
    GROUP BY c.id, c.name, c.description, c.parent_id, c.is_active, c.created_at, parent.name
    ORDER BY c.name
";
$categories = $pdo->query($categoriesQuery)->fetchAll();

// Get recently added categories (last 7 days)
$recentQuery = "
    SELECT c.*, COUNT(p.id) as product_count
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id
    WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY c.id
    ORDER BY c.created_at DESC
    LIMIT 5
";
$recentCategories = $pdo->query($recentQuery)->fetchAll();
?>

<div class="container mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-3xl font-bold text-gray-800">Categories Overview</h2>
            <p class="text-gray-600 mt-1">View all product categories in your system</p>
        </div>
        <div class="text-right">
            <div class="text-2xl font-bold text-blue-600"><?= count($categories) ?></div>
            <div class="text-sm text-gray-500">Total Categories</div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <?php
        $totalCategories = count($categories);
        $activeCategories = count(array_filter($categories, function($cat) { return $cat['is_active']; }));
        $mainCategories = count(array_filter($categories, function($cat) { return !$cat['parent_id']; }));
        $totalProducts = array_sum(array_column($categories, 'product_count'));
        ?>
        
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-2xl font-semibold text-gray-900"><?= $totalCategories ?></p>
                    <p class="text-sm text-gray-600">All Categories</p>
                </div>
                <div class="p-2 bg-blue-100 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-2xl font-semibold text-green-600"><?= $activeCategories ?></p>
                    <p class="text-sm text-gray-600">Active</p>
                </div>
                <div class="p-2 bg-green-100 rounded-lg">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-2xl font-semibold text-purple-600"><?= $mainCategories ?></p>
                    <p class="text-sm text-gray-600">Main Categories</p>
                </div>
                <div class="p-2 bg-purple-100 rounded-lg">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14-7l2 2-2 2m2 2l2 2-2 2M5 11l2-2-2-2m0 4l2 2-2-2"/>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-2xl font-semibold text-orange-600"><?= $totalProducts ?></p>
                    <p class="text-sm text-gray-600">Total Products</p>
                </div>
                <div class="p-2 bg-orange-100 rounded-lg">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Recently Added Categories -->
    <?php if (!empty($recentCategories)): ?>
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 bg-green-50 border-b border-green-200">
            <h3 class="text-lg font-semibold text-green-800 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Recently Added Categories (Last 7 Days)
            </h3>
        </div>
        <div class="p-4">
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($recentCategories as $recent): ?>
                <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                    <div class="flex justify-between items-start">
                        <div>
                            <h4 class="font-medium text-green-900"><?= htmlspecialchars($recent['name']) ?></h4>
                            <p class="text-sm text-green-600 mt-1"><?= $recent['product_count'] ?> products</p>
                            <p class="text-xs text-green-500 mt-1">
                                Added: <?= date('M j, Y', strtotime($recent['created_at'])) ?>
                            </p>
                        </div>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            New
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- All Categories Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v8a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                All Categories in System
            </h3>
        </div>
        
        <?php if (empty($categories)): ?>
            <div class="p-8 text-center">
                <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No Categories Yet</h3>
                <p class="text-gray-600">Categories will appear here when you add products with new categories.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Parent</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Products</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($categories as $category): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <?php if ($category['parent_id']): ?>
                                            <span class="text-gray-400 mr-2 text-sm">└─</span>
                                        <?php endif; ?>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($category['name']) ?>
                                            </div>
                                            <div class="text-xs text-gray-500">ID: <?= $category['id'] ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 max-w-xs">
                                        <?= $category['description'] ? htmlspecialchars($category['description']) : '-' ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $category['parent_name'] ? htmlspecialchars($category['parent_name']) : '-' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <?= $category['product_count'] ?>
                                        </span>
                                        <?php if ($category['subcategory_count'] > 0): ?>
                                            <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                <?= $category['subcategory_count'] ?> sub
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $category['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= $category['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div class="text-sm text-gray-900"><?= date('M j, Y', strtotime($category['created_at'])) ?></div>
                                    <div class="text-xs text-gray-500"><?= date('g:i A', strtotime($category['created_at'])) ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Category Summary by Status -->
    <div class="mt-6 grid md:grid-cols-2 gap-6">
        <!-- Active Categories -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 bg-green-50 border-b">
                <h3 class="text-lg font-semibold text-green-800">Active Categories (<?= $activeCategories ?>)</h3>
            </div>
            <div class="p-4 max-h-64 overflow-y-auto">
                <?php 
                $activeCats = array_filter($categories, function($cat) { return $cat['is_active']; });
                if (empty($activeCats)): ?>
                    <p class="text-gray-500 text-center py-4">No active categories</p>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach ($activeCats as $cat): ?>
                            <div class="flex justify-between items-center p-2 bg-green-50 rounded">
                                <span class="text-sm font-medium text-green-900">
                                    <?= htmlspecialchars($cat['name']) ?>
                                </span>
                                <span class="text-xs text-green-600">
                                    <?= $cat['product_count'] ?> products
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Category Usage -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 bg-blue-50 border-b">
                <h3 class="text-lg font-semibold text-blue-800">Most Used Categories</h3>
            </div>
            <div class="p-4">
                <?php 
                $sortedByProducts = $categories;
                usort($sortedByProducts, function($a, $b) { return $b['product_count'] - $a['product_count']; });
                $topCategories = array_slice($sortedByProducts, 0, 5);
                
                if (empty($topCategories)): ?>
                    <p class="text-gray-500 text-center py-4">No categories with products yet</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($topCategories as $top): ?>
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($top['name']) ?>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                                        <div class="bg-blue-600 h-2 rounded-full" 
                                             style="width: <?= $totalProducts > 0 ? ($top['product_count'] / $totalProducts * 100) : 0 ?>%"></div>
                                    </div>
                                </div>
                                <span class="ml-3 text-sm font-medium text-blue-600">
                                    <?= $top['product_count'] ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer Info -->
    <div class="mt-6 text-center text-sm text-gray-500">
        <p>Categories are automatically created when adding products. This page provides an overview of all categories in your system.</p>
    </div>
</div>

<?php include 'footer.php'; ?>