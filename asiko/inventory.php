<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require 'db.php';
require 'functions.php';

// --- PHASE 3: DYNAMIC TERMS FETCH ---
$inventoryTitle = getSectorTerm('inventory_title', 'Inventory Management');
$productNameLabel = getSectorTerm('product_name', 'Product Name');
$categoryLabel = getSectorTerm('category_label', 'Category');
$supplierLabel = getSectorTerm('supplier_label', 'Supplier');
$quantityLabel = getSectorTerm('quantity_label', 'Quantity');
$unitLabel = getSectorTerm('unit_type', 'Unit');
$lowStockTask = getSectorTerm('low_stock_task', 'Low Stock Task');
// --- END DYNAMIC TERMS ---

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    generateCsrfToken();
}

// Helper: get/create ID by name (with whitelist for security)
function getOrCreateId($pdo, $table, $name) {
    // Whitelist allowed tables
    $allowedTables = ['categories', 'suppliers'];
    if (!in_array($table, $allowedTables)) {
        throw new Exception("Invalid table name");
    }
    
    $name = trim($name);
    // FIX: Check if $name is a valid string before passing to ucfirst and preparing statement
    if (empty($name) || !is_string($name)) {
        throw new Exception(ucfirst($allowedTables[0]) . " name cannot be empty");
    }
    
    $stmt = $pdo->prepare("SELECT id FROM $table WHERE name = ?");
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    
    if ($row) {
        return $row['id'];
    }
    
    $stmt = $pdo->prepare("INSERT INTO $table (name) VALUES (?)");
    $stmt->execute([$name]);
    return $pdo->lastInsertId();
}

// Helper function for pagination
function buildPageUrl($p) {
    $params = $_GET;
    $params['page'] = $p;
    return '?' . http_build_query($params);
}


// Pagination
$perPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

// Fetch categories & suppliers using function
$categories = getCategories($pdo);
$suppliers  = $pdo->query("SELECT * FROM suppliers ORDER BY name")->fetchAll();

// Get low stock threshold from system settings
$lowStockThreshold = getLowStockThreshold();

// Add product
if (isset($_POST['add'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        redirect('inventory.php');
    }
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Validate required fields
        $errors = [];
        
        $name = sanitizeInput($_POST['name'] ?? '');
        $categoryName = sanitizeInput($_POST['category_name'] ?? '');
        $supplierName = sanitizeInput($_POST['supplier_name'] ?? '');
        $unit = sanitizeInput($_POST['unit'] ?? '');
        
        if (empty($name)) {
            $errors[] = "$productNameLabel is required.";
        }
        if (empty($categoryName)) {
            $errors[] = "$categoryLabel is required.";
        }
        if (empty($supplierName)) {
            $errors[] = "$supplierLabel is required.";
        }
        if (empty($unit)) {
            $errors[] = "$unitLabel is required.";
        }
        
        // Validate numeric fields
        $quantity = filter_var($_POST['quantity'] ?? 0, FILTER_VALIDATE_INT);
        $price = filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT);
        $minStock = filter_var($_POST['minimum_stock_level'] ?? $lowStockThreshold, FILTER_VALIDATE_INT);
        $costPrice = filter_var($_POST['cost_price'] ?? 0, FILTER_VALIDATE_FLOAT); // NEW
        
        if ($quantity === false || $quantity < 0) {
            $errors[] = "$quantityLabel must be a valid non-negative number.";
        }
        if ($price === false || $price < 0) {
            $errors[] = "Selling Price must be a valid non-negative number.";
        }
        // VALIDATION FOR COST PRICE
        if ($costPrice === false || $costPrice < 0) {
            $errors[] = "Cost Price must be a valid non-negative number.";
        }
        if ($minStock === false || $minStock < 0) {
            $errors[] = "Minimum stock level must be a valid non-negative number.";
        }
        
        // Check for duplicate SKU if SKU is provided
        $sku = sanitizeInput($_POST['sku'] ?? '');
        if (!empty($sku)) {
            $checkSku = $pdo->prepare("SELECT id FROM products WHERE sku = ?");
            $checkSku->execute([$sku]);
            if ($checkSku->fetch()) {
                $errors[] = "SKU '" . htmlspecialchars($sku) . "' already exists. Please use a different SKU.";
            }
        }
        
        if (!empty($errors)) {
            $_SESSION['error'] = implode('<br>', $errors);
            $pdo->rollBack();
            redirect('inventory.php');
        }
        
        // Get or create category and supplier
        $cat_id = getOrCreateId($pdo, 'categories', $categoryName);
        $sup_id = getOrCreateId($pdo, 'suppliers', $supplierName);
        
        // Insert product (UPDATED: Added cost_price)
        $stmt = $pdo->prepare("
            INSERT INTO products (name, sku, category_id, supplier_id, quantity_in_stock, unit, selling_price, cost_price, minimum_stock_level)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $name,
            !empty($sku) ? $sku : null,
            $cat_id,
            $sup_id,
            $quantity,
            $unit,
            $price,
            $costPrice, // NEW PARAMETER
            $minStock
        ]);
        
        $pdo->commit();
        $_SESSION['success'] = "Product added successfully!";
        redirect('inventory.php');
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        // Check if it's a duplicate entry error
        if ($e->getCode() == 23000) {
            $_SESSION['error'] = "Duplicate entry detected. Please check SKU or product name.";
        } else {
            $_SESSION['error'] = "Database Error: " . $e->getMessage();
        }
        redirect('inventory.php');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = "Error: " . $e->getMessage();
        redirect('inventory.php');
    }
}

// Delete
if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([intval($_GET['delete'])]);
        $_SESSION['success'] = "Product deleted successfully!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting product: " . $e->getMessage();
    }
    redirect('inventory.php');
}

// Edit mode
$editMode = false;
$editProduct = null;
if (isset($_GET['edit'])) {
    $editMode = true;
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.sku, p.category_id, p.supplier_id, 
               p.quantity_in_stock, p.unit, p.selling_price, p.cost_price, p.minimum_stock_level,
               c.name AS category_name, s.name AS supplier_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        WHERE p.id = ?
    ");
    $stmt->execute([intval($_GET['edit'])]);
    $editProduct = $stmt->fetch();
    if (!$editProduct) {
        $editMode = false;
        $_SESSION['error'] = "Product not found.";
        redirect('inventory.php');
    }
}

// Update
if (isset($_POST['update'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        redirect('inventory.php');
    }
    
    try {
        $pdo->beginTransaction();
        
        // Validate required fields
        $errors = [];
        
        $name = sanitizeInput($_POST['name'] ?? '');
        $categoryName = sanitizeInput($_POST['category_name'] ?? '');
        $supplierName = sanitizeInput($_POST['supplier_name'] ?? '');
        $unit = sanitizeInput($_POST['unit'] ?? '');
        
        if (empty($name)) {
            $errors[] = "$productNameLabel is required.";
        }
        if (empty($categoryName)) {
            $errors[] = "$categoryLabel is required.";
        }
        if (empty($supplierName)) {
            $errors[] = "$supplierLabel is required.";
        }
        if (empty($unit)) {
            $errors[] = "$unitLabel is required.";
        }
        
        // Validate numeric fields
        $quantity = filter_var($_POST['quantity'] ?? 0, FILTER_VALIDATE_INT);
        $price = filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT);
        $minStock = filter_var($_POST['minimum_stock_level'] ?? $lowStockThreshold, FILTER_VALIDATE_INT);
        $costPrice = filter_var($_POST['cost_price'] ?? 0, FILTER_VALIDATE_FLOAT); // NEW
        
        if ($quantity === false || $quantity < 0) {
            $errors[] = "$quantityLabel must be a valid non-negative number.";
        }
        if ($price === false || $price < 0) {
            $errors[] = "Selling Price must be a valid non-negative number.";
        }
        // VALIDATION FOR COST PRICE
        if ($costPrice === false || $costPrice < 0) {
            $errors[] = "Cost Price must be a valid non-negative number.";
        }
        if ($minStock === false || $minStock < 0) {
            $errors[] = "Minimum stock level must be a valid non-negative number.";
        }
        
        // Check for duplicate SKU (excluding current product)
        $sku = sanitizeInput($_POST['sku'] ?? '');
        if (!empty($sku)) {
            $checkSku = $pdo->prepare("SELECT id FROM products WHERE sku = ? AND id != ?");
            $checkSku->execute([$sku, intval($_POST['id'])]);
            if ($checkSku->fetch()) {
                $errors[] = "SKU '" . htmlspecialchars($sku) . "' already exists. Please use a different SKU.";
            }
        }
        
        if (!empty($errors)) {
            $_SESSION['error'] = implode('<br>', $errors);
            $pdo->rollBack();
            redirect("inventory.php?edit=" . intval($_POST['id']));
        }
        
        $cat_id = getOrCreateId($pdo, 'categories', $categoryName);
        $sup_id = getOrCreateId($pdo, 'suppliers', $supplierName);
        
        // Update product (UPDATED: Added cost_price)
        $stmt = $pdo->prepare("
            UPDATE products
            SET name = ?, sku = ?, category_id = ?, supplier_id = ?, quantity_in_stock = ?, unit = ?, selling_price = ?, cost_price = ?, minimum_stock_level = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $name,
            !empty($sku) ? $sku : null,
            $cat_id,
            $sup_id,
            $quantity,
            $unit,
            $price,
            $costPrice, // NEW PARAMETER
            $minStock,
            intval($_POST['id'])
        ]);
        
        $pdo->commit();
        $_SESSION['success'] = "Product updated successfully!";
        redirect('inventory.php');
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Database Error: " . $e->getMessage();
        redirect("inventory.php?edit=" . intval($_POST['id']));
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = "Error: " . $e->getMessage();
        redirect("inventory.php?edit=" . intval($_POST['id']));
    }
}

// Filtering
$where = [];
$params = [];
if (!empty($_GET['q'])) {
    $where[] = 'p.name LIKE ?';
    $params[] = '%' . sanitizeInput($_GET['q']) . '%';
}
if (!empty($_GET['category'])) {
    $where[] = 'c.name = ?';
    $params[] = sanitizeInput($_GET['category']);
}
if (!empty($_GET['supplier'])) {
    $where[] = 's.name = ?';
    $params[] = sanitizeInput($_GET['supplier']);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count total
$totalRows = $pdo->prepare("
    SELECT COUNT(*)
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    $whereSql
");
$totalRows->execute($params);
$total = $totalRows->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

include 'header.php';

// Fetch products
$sql = "
    SELECT p.id, p.name, p.sku, p.quantity_in_stock as quantity, p.unit, p.selling_price as price, 
           p.minimum_stock_level, c.name AS category_name, s.name AS supplier_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    $whereSql
    ORDER BY p.id DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get currency symbol from system settings
$currency = getSystemSetting('currency_symbol', '₦');

// --- PHASE 3: GET SUGGESTED UNITS ---
$suggestedUnits = suggestUnits();
?>

<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4"><?= htmlspecialchars($inventoryTitle) ?></h1>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 relative">
            <?php echo htmlspecialchars($_SESSION['success']); ?>
            <button onclick="this.parentElement.remove()" class="absolute top-0 right-0 px-4 py-3">
                <span class="text-2xl">&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 relative">
            <?php echo $_SESSION['error']; ?>
            <button onclick="this.parentElement.remove()" class="absolute top-0 right-0 px-4 py-3">
                <span class="text-2xl">&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4 gap-2">
        <h2 class="text-xl font-bold">Product Inventory</h2>
        <a href="export.php?table=products" class="bg-green-600 text-white px-3 py-2 rounded shadow hover:bg-green-700 transition">Export Inventory</a>
    </div>

    <form method="get" class="flex flex-wrap gap-2 mb-4">
        <input name="q" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>" placeholder="Search name" class="border p-2 rounded flex-1 min-w-[120px]">
        <select name="category" class="border p-2 rounded">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat['name']); ?>" <?php echo (@$_GET['category']==$cat['name']?'selected':''); ?>><?php echo htmlspecialchars($cat['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="supplier" class="border p-2 rounded">
            <option value="">All Suppliers</option>
            <?php foreach ($suppliers as $sup): ?>
                <option value="<?php echo htmlspecialchars($sup['name']); ?>" <?php echo (@$_GET['supplier']==$sup['name']?'selected':''); ?>><?php echo htmlspecialchars($sup['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 transition">Filter</button>
        <?php if (!empty($_GET['q']) || !empty($_GET['category']) || !empty($_GET['supplier'])): ?>
            <a href="inventory.php" class="bg-gray-500 text-white px-4 py-2 rounded shadow hover:bg-gray-600 transition">Clear</a>
        <?php endif; ?>
    </form>

    <div class="bg-white shadow rounded p-4 mb-6">
    <?php if ($editMode && $editProduct): ?>
        <h3 class="text-lg font-semibold mb-3">Edit <?= htmlspecialchars($productNameLabel) ?></h3>
        <form method="post" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="id" value="<?php echo intval($editProduct['id']); ?>">
            
            <div>
                <label class="block text-sm font-medium mb-1"><?= htmlspecialchars($productNameLabel) ?> *</label>
                <input name="name" required placeholder="<?= htmlspecialchars($productNameLabel) ?>" value="<?php echo htmlspecialchars($editProduct['name']); ?>" class="border p-2 rounded w-full">
            </div>
            
            <div>
                <label class="block text-sm font-medium mb-1">SKU</label>
                <input name="sku" placeholder="SKU (optional)" value="<?php echo htmlspecialchars($editProduct['sku'] ?? ''); ?>" class="border p-2 rounded w-full">
            </div>
            
            <div>
                <label class="block text-sm font-medium mb-1"><?= htmlspecialchars($categoryLabel) ?> *</label>
                <input name="category_name" required list="categorylist" placeholder="<?= htmlspecialchars($categoryLabel) ?> name" value="<?php echo htmlspecialchars($editProduct['category_name']); ?>" class="border p-2 rounded w-full">
            </div>
            
            <div>
                <label class="block text-sm font-medium mb-1"><?= htmlspecialchars($supplierLabel) ?> *</label>
                <input name="supplier_name" required list="supplierlist" placeholder="<?= htmlspecialchars($supplierLabel) ?> name" value="<?php echo htmlspecialchars($editProduct['supplier_name']); ?>" class="border p-2 rounded w-full">
            </div>
            
            <div>
                <label class="block text-sm font-medium mb-1"><?= htmlspecialchars($quantityLabel) ?> *</label>
                <input name="quantity" type="number" min="0" required placeholder="<?= htmlspecialchars($quantityLabel) ?>" value="<?php echo isset($editProduct['quantity']) ? intval($editProduct['quantity']) : 0; ?>" class="border p-2 rounded w-full">
            </div>
            
            <div>
                <label class="block text-sm font-medium mb-1"><?= htmlspecialchars($unitLabel) ?> *</label>
                <input name="unit" required list="unitlist" placeholder="e.g. pcs, kg" value="<?php echo isset($editProduct['unit']) ? htmlspecialchars($editProduct['unit']) : ''; ?>" class="border p-2 rounded w-full">
            </div>
            
            <div>
                <label class="block text-sm font-medium mb-1">Selling Price *</label>
                <input name="price" type="number" min="0" step="0.01" required placeholder="Price" value="<?php echo isset($editProduct['price']) ? number_format($editProduct['price'], 2, '.', '') : '0.00'; ?>" class="border p-2 rounded w-full">
            </div>
            
            <div>
                <label class="block text-sm font-medium mb-1">Cost Price</label>
                <input name="cost_price" type="number" min="0" step="0.01" placeholder="Cost Price" value="<?php echo isset($editProduct['cost_price']) ? number_format($editProduct['cost_price'], 2, '.', '') : '0.00'; ?>" class="border p-2 rounded w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Min Stock Level</label>
                <input name="minimum_stock_level" type="number" min="0" placeholder="Min Stock" value="<?php echo intval($editProduct['minimum_stock_level'] ?? $lowStockThreshold); ?>" class="border p-2 rounded w-full">
            </div>
            
            <div class="md:col-span-2 lg:col-span-4 flex gap-2">
                <button name="update" class="bg-yellow-600 text-white px-6 py-2 rounded shadow hover:bg-yellow-700 transition">Update <?= htmlspecialchars($productNameLabel) ?></button>
                <a href="inventory.php" class="bg-gray-500 text-white px-6 py-2 rounded shadow hover:bg-gray-600 transition inline-block">Cancel</a>
            </div>
        </form>
    <?php else: ?>
        <h3 class="text-lg font-semibold mb-3">Add New <?= htmlspecialchars($productNameLabel) ?></h3>
        <form method="post" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div>
                <label class="block text-sm font-medium mb-1"><?= htmlspecialchars($productNameLabel) ?> *</label>
                <input name="name" required placeholder="<?= htmlspecialchars($productNameLabel) ?>" class="border p-2 rounded w-full">
            </div>
            
            <div>
                <label class="block text-sm font-medium mb-1">SKU</label>
                <input name="sku" placeholder="SKU (optional)" class="border p-2 rounded w-full">
            </div>
            
            <div>
                <label class="block text-sm font-medium mb-1"><?= htmlspecialchars($categoryLabel) ?> *</label>
                <input name="category_name" required list="categorylist" placeholder="<?= htmlspecialchars($categoryLabel) ?> name" class="border p-2 rounded w-full">
            </div>
            
            <div>
                <label class="block text-sm font-medium mb-1"><?= htmlspecialchars($supplierLabel) ?> *</label>
                <input name="supplier_name" required list="supplierlist" placeholder="<?= htmlspecialchars($supplierLabel) ?> name" class="border p-2 rounded w-full">
            </div>
            
            <div>
                <label class="block text-sm font-medium mb-1"><?= htmlspecialchars($quantityLabel) ?> *</label>
                <input name="quantity" type="number" min="0" required placeholder="<?= htmlspecialchars($quantityLabel) ?>" value="0" class="border p-2 rounded w-full">
            </div>
            
            <div>
                <label class="block text-sm font-medium mb-1"><?= htmlspecialchars($unitLabel) ?> *</label>
                <input name="unit" required list="unitlist" placeholder="e.g. pcs, kg" class="border p-2 rounded w-full">
            </div>
            
            <div>
                <label class="block text-sm font-medium mb-1">Selling Price *</label>
                <input name="price" type="number" min="0" step="0.01" required placeholder="Price" value="0" class="border p-2 rounded w-full">
            </div>
            
            <div>
                <label class="block text-sm font-medium mb-1">Cost Price</label>
                <input name="cost_price" type="number" min="0" step="0.01" placeholder="Cost Price" value="0" class="border p-2 rounded w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Min Stock Level</label>
                <input name="minimum_stock_level" type="number" min="0" placeholder="Min Stock" value="<?php echo intval($lowStockThreshold); ?>" class="border p-2 rounded w-full">
            </div>
            
            <div class="md:col-span-2 lg:col-span-4">
                <button name="add" class="bg-blue-600 text-white px-6 py-2 rounded shadow hover:bg-blue-700 transition">Add <?= htmlspecialchars($productNameLabel) ?></button>
            </div>
        </form>
    <?php endif; ?>
    </div>

    <datalist id="categorylist">
        <?php foreach ($categories as $cat): ?>
            <option value="<?php echo htmlspecialchars($cat['name']); ?>">
        <?php endforeach; ?>
    </datalist>
    <datalist id="supplierlist">
        <?php foreach ($suppliers as $sup): ?>
            <option value="<?php echo htmlspecialchars($sup['name']); ?>">
        <?php endforeach; ?>
    </datalist>
    
    <datalist id="unitlist">
        <?php foreach (suggestUnits() as $unitOption): ?>
            <option value="<?php echo htmlspecialchars($unitOption); ?>">
        <?php endforeach; ?>
    </datalist>
    
    <div class="flex gap-4 mb-4">
        <a href="categories.php" class="text-blue-600 underline hover:text-blue-800">Manage <?= htmlspecialchars($categoryLabel) ?></a>
        <a href="suppliers.php" class="text-blue-600 underline hover:text-blue-800">Manage <?= htmlspecialchars($supplierLabel) ?>s</a>
    </div>

    <?php if (empty($products)): ?>
        <div class="bg-gray-100 border border-gray-300 text-gray-700 px-4 py-8 rounded text-center">
            <p class="text-lg">No <?= htmlspecialchars($productNameLabel) ?>s found.</p>
            <?php if (!empty($_GET['q']) || !empty($_GET['category']) || !empty($_GET['supplier'])): ?>
                <p class="mt-2">Try adjusting your filters or <a href="inventory.php" class="text-blue-600 underline">clear all filters</a>.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
    <div class="overflow-x-auto">
    <table class="min-w-full bg-white shadow rounded mt-4 text-sm">
        <thead class="bg-gray-200">
            <tr>
                <th class="p-2 text-left"><?= htmlspecialchars($productNameLabel) ?></th>
                <th class="p-2 text-left">SKU</th>
                <th class="p-2 text-left"><?= htmlspecialchars($categoryLabel) ?></th>
                <th class="p-2 text-left"><?= htmlspecialchars($supplierLabel) ?></th>
                <th class="p-2 text-left"><?= htmlspecialchars($quantityLabel) ?> & Unit</th>
                <th class="p-2 text-left">Price</th>
                <th class="p-2 text-left">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $row): ?>
            <?php 
                $isLowStock = $row['quantity'] <= ($row['minimum_stock_level'] ?? $lowStockThreshold);
            ?>
            <tr class="<?php echo ($isLowStock ? 'bg-yellow-100 font-semibold' : ''); ?>">
                <td class="p-2"><?php echo htmlspecialchars($row['name']); ?></td>
                <td class="p-2 text-gray-600"><?php echo htmlspecialchars($row['sku'] ?? 'N/A'); ?></td>
                
                <td class="p-2"><?php echo htmlspecialchars($row['category_name'] ?? 'Uncategorized'); ?></td>
                <td class="p-2"><?php echo htmlspecialchars($row['supplier_name'] ?? 'Unknown'); ?></td>
                
                <td class="p-2">
                    <?php echo $row['quantity']; ?> <?php echo htmlspecialchars($row['unit'] ?? 'pcs'); ?>
                    <?php if ($isLowStock): ?>
                        <span class="text-red-600 ml-1 text-xs font-bold">LOW STOCK</span>
                    <?php endif; ?>
                </td>
                <td class="p-2"><?php echo formatCurrency($row['price']); ?></td>
                <td class="p-2">
                    <a href="inventory.php?edit=<?php echo $row['id']; ?>" class="text-blue-600 hover:underline mr-2">Edit</a>
                    <a href="inventory.php?delete=<?php echo $row['id']; ?>" class="text-red-600 hover:underline" onclick="return confirm('Are you sure you want to delete this product? This action cannot be undone.')">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
    <div class="flex gap-2 justify-center my-6 flex-wrap">
        <?php if ($page > 1): ?>
            <a href="<?php echo buildPageUrl(1); ?>" class="px-3 py-1 rounded bg-gray-200 hover:bg-gray-300">First</a>
            <a href="<?php echo buildPageUrl($page-1); ?>" class="px-3 py-1 rounded bg-gray-200 hover:bg-gray-300">Prev</a>
        <?php endif; ?>
        
        <?php 
        // Show max 7 page numbers
        $startPage = max(1, $page - 3);
        $endPage = min($totalPages, $page + 3);
        
        for ($i = $startPage; $i <= $endPage; $i++): 
        ?>
            <a href="<?php echo buildPageUrl($i); ?>" class="px-3 py-1 rounded <?php echo ($i==$page ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200'); ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        
        <?php if ($page < $totalPages): ?>
            <a href="<?php echo buildPageUrl($page+1); ?>" class="px-3 py-1 rounded bg-gray-200 hover:bg-gray-300">Next</a>
            <a href="<?php echo buildPageUrl($totalPages); ?>" class="px-3 py-1 rounded bg-gray-200 hover:bg-gray-300">Last</a>
        <?php endif; ?>
    </div>
    <div class="text-center text-gray-600 text-sm">
        Page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo $total; ?> total <?= htmlspecialchars($productNameLabel) ?>s)
    </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>