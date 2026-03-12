<?php
// kiosk/Red/admin_products.php
include 'header.php';

// Role Checks
$is_super = ($_SESSION['admin_role'] ?? '') === 'superadmin';
$current_admin_id = $_SESSION['admin_id'];

// CSRF & Messages
$success = '';
$error = '';
$csrf_token = generateCsrfToken();

// --- 1. HANDLE FORM SUBMISSION (Add / Delete) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Session expired. Please refresh.";
    } else {
        
        // --- 1A. HANDLE DELETION ---
        if (isset($_POST['delete_id'])) {
            $del_id = intval($_POST['delete_id']);
            $checkOwner = $is_super ? "1=1" : "admin_id = $current_admin_id";
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND $checkOwner");
            $stmt->execute([$del_id]);
            $success = "Product deleted successfully.";
        } 
        
        // --- 1B. HANDLE CREATE ---
        elseif (isset($_POST['create_product'])) {
            $name = sanitize($_POST['name']);
            
            // Generate SEO Slug safely
            $raw_slug = !empty($_POST['slug']) ? sanitize($_POST['slug']) : $name;
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $raw_slug)));
            
            $price = floatval($_POST['price']);
            $stock = intval($_POST['stock_quantity']); 
            $category = sanitize($_POST['category']); // Handles dynamically typed categories
            $desc = sanitize($_POST['description']);
            $min_order = intval($_POST['min_order'] ?? 1);
            $bulk_price = !empty($_POST['bulk_price']) ? floatval($_POST['bulk_price']) : null;
            $status = in_array($_POST['status'], ['published', 'draft', 'archived']) ? $_POST['status'] : 'published';
            $weight_kg = floatval($_POST['weight_kg'] ?? 0);

            try {
                $pdo->beginTransaction();

                // Ensure unique slug for new products
                $slug .= '-' . rand(1000, 9999);
                
                $sql = "INSERT INTO products (name, slug, price, stock_quantity, category, description, min_order_quantity, sale_price, status, weight_kg, admin_id, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$name, $slug, $price, $stock, $category, $desc, $min_order, $bulk_price, $status, $weight_kg, $current_admin_id]);
                $product_id = $pdo->lastInsertId();
                $success = "Product published to your store!";

                // --- 2. PROCESS IMAGE GALLERY UPLOADS ---
                if (isset($_FILES['gallery']) && !empty($_FILES['gallery']['name'][0])) {
                    $upload_dir = __DIR__ . '/../uploads/gallery/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    
                    $first_image = null;
                    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                    
                    foreach ($_FILES['gallery']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['gallery']['error'][$key] === 0) {
                            $ext = strtolower(pathinfo($_FILES['gallery']['name'][$key], PATHINFO_EXTENSION));
                            if (in_array($ext, $allowed)) {
                                $filename = 'gal_' . uniqid() . '_' . time() . '.' . $ext;
                                if (move_uploaded_file($tmp_name, $upload_dir . $filename)) {
                                    $rel_path = "kiosk/uploads/gallery/$filename";
                                    $is_prim = ($first_image === null) ? 1 : 0;
                                    
                                    $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, ?)")
                                        ->execute([$product_id, $rel_path, $is_prim]);
                                        
                                    if (!$first_image) $first_image = $rel_path;
                                }
                            }
                        }
                    }
                    
                    // Fallback to update legacy image_path on main table
                    if ($first_image) {
                        $pdo->prepare("UPDATE products SET image_path = ? WHERE id = ?")->execute([$first_image, $product_id]);
                    }
                }

                // --- 3. PROCESS VARIANTS (SKUs) ---
                if (!empty($_POST['variant_name'])) {
                    foreach ($_POST['variant_name'] as $index => $v_name) {
                        $v_name = trim($v_name);
                        if (!empty($v_name)) {
                            $v_sku = sanitize($_POST['variant_sku'][$index] ?? '');
                            $v_price = floatval($_POST['variant_price'][$index] ?? 0);
                            $v_stock = intval($_POST['variant_stock'][$index] ?? 0);
                            
                            $pdo->prepare("INSERT INTO product_variants (product_id, sku, variant_name, price_adjustment, stock_quantity) VALUES (?, ?, ?, ?, ?)")
                                ->execute([$product_id, $v_sku, $v_name, $v_price, $v_stock]);
                        }
                    }
                }

                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                if ($e->getCode() == 23000) {
                    $error = "Error: A product with that SEO Slug already exists. Please choose a unique name/slug.";
                } else {
                    $error = "Database Error: " . $e->getMessage();
                }
            }
        }
    }
}

// --- FETCH PRODUCTS & CATEGORIES ---
if ($is_super) {
    $stmt = $pdo->query("SELECT p.*, a.store_name FROM products p LEFT JOIN admins a ON p.admin_id = a.id ORDER BY p.created_at DESC");
} else {
    $stmt = $pdo->prepare("SELECT *, 'My Store' as store_name FROM products WHERE admin_id = ? ORDER BY created_at DESC");
    $stmt->execute([$current_admin_id]);
}
$products = $stmt->fetchAll();

// Dynamic Category Fetcher
$cat_stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
$existing_categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Inventory Management</h1>
        <p class="text-sm text-gray-500">
            <?= $is_super ? 'Manage enterprise inventory across all vendors' : 'Manage your storefront inventory, SKUs, and variants.' ?>
        </p>
    </div>
    <button onclick="document.getElementById('productFormWrapper').classList.toggle('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-6 rounded-lg shadow-sm transition flex items-center gap-2">
        <span class="material-symbols-outlined text-sm">add</span> Add New Product
    </button>
</div>

<?php if ($success): ?><div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm font-bold flex items-center gap-2"><span class="material-symbols-outlined">check_circle</span> <?= $success ?></div><?php endif; ?>
<?php if ($error): ?><div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm font-bold flex items-center gap-2"><span class="material-symbols-outlined">error</span> <?= $error ?></div><?php endif; ?>

<div id="productFormWrapper" class="hidden mb-8">
    <form method="POST" enctype="multipart/form-data" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="font-bold text-lg text-gray-800">Create New Product Listing</h3>
            <button type="button" onclick="document.getElementById('productFormWrapper').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        
        <div class="p-6 grid grid-cols-1 xl:grid-cols-3 gap-8">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="create_product" value="1">
            
            <div class="xl:col-span-2 space-y-6">
                <div class="bg-white border border-gray-200 rounded-lg p-5 shadow-sm">
                    <h4 class="font-bold text-gray-800 mb-4 border-b pb-2">Basic Information</h4>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Product Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" class="w-full border border-gray-300 p-2.5 rounded focus:ring-2 focus:ring-blue-500 outline-none" placeholder="e.g., Nike Air Max Pro" required>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">SEO URL Slug</label>
                                <input type="text" name="slug" class="w-full border border-gray-300 p-2.5 rounded focus:ring-2 focus:ring-blue-500 outline-none text-sm text-gray-500" placeholder="Auto-generated if left blank">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Category <span class="text-red-500">*</span></label>
                                <input type="text" name="category" list="category-options" class="w-full border border-gray-300 p-2.5 rounded focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Select or type to create new..." required>
                                <datalist id="category-options">
                                    <?php foreach($existing_categories as $c): ?>
                                        <option value="<?= htmlspecialchars($c) ?>"></option>
                                    <?php endforeach; ?>
                                    <option value="Electronics"></option>
                                    <option value="Fashion"></option>
                                    <option value="Home & Office"></option>
                                    <option value="Health & Beauty"></option>
                                    <option value="Food & Groceries"></option>
                                </datalist>
                                <p class="text-[10px] text-gray-400 mt-1">Pick an existing category or type a new one to add it.</p>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Detailed Description</label>
                            <textarea name="description" class="w-full border border-gray-300 p-2.5 rounded focus:ring-2 focus:ring-blue-500 outline-none h-32" placeholder="List the features, materials, and benefits..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-lg p-5 shadow-sm" x-data="{ variants: [] }">
                    <div class="flex justify-between items-center border-b pb-2 mb-4">
                        <h4 class="font-bold text-gray-800">Product Variants & SKUs</h4>
                        <button type="button" @click="variants.push({sku: '', name: '', price: 0, stock: 10})" class="text-sm bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold py-1.5 px-3 rounded transition flex items-center gap-1">
                            <span class="material-symbols-outlined text-[16px]">add</span> Add Variant
                        </button>
                    </div>
                    
                    <p class="text-xs text-gray-500 mb-4" x-show="variants.length === 0">Does this product come in different sizes, colors, or materials? Add variants here to track individual SKUs and inventory.</p>
                    
                    <div class="space-y-3">
                        <template x-for="(v, index) in variants" :key="index">
                            <div class="flex flex-wrap md:flex-nowrap items-end gap-3 bg-gray-50 p-3 rounded border border-gray-200">
                                <div class="w-full md:w-1/4">
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wide mb-1">Variant Name</label>
                                    <input type="text" name="variant_name[]" x-model="v.name" placeholder="e.g. Size M - Red" class="w-full border border-gray-300 p-2 rounded text-sm outline-none focus:ring-1 focus:ring-blue-500" required>
                                </div>
                                <div class="w-full md:w-1/4">
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wide mb-1">SKU (Barcode)</label>
                                    <input type="text" name="variant_sku[]" x-model="v.sku" placeholder="NK-AM-M-RED" class="w-full border border-gray-300 p-2 rounded text-sm outline-none focus:ring-1 focus:ring-blue-500">
                                </div>
                                <div class="w-1/2 md:w-1/6">
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wide mb-1">Price Adj. (₦)</label>
                                    <input type="number" name="variant_price[]" x-model="v.price" class="w-full border border-gray-300 p-2 rounded text-sm outline-none focus:ring-1 focus:ring-blue-500">
                                </div>
                                <div class="w-1/2 md:w-1/6">
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wide mb-1">Stock</label>
                                    <input type="number" name="variant_stock[]" x-model="v.stock" class="w-full border border-gray-300 p-2 rounded text-sm outline-none focus:ring-1 focus:ring-blue-500" required>
                                </div>
                                <div class="w-full md:w-auto mt-2 md:mt-0">
                                    <button type="button" @click="variants.splice(index, 1)" class="w-full md:w-auto bg-red-100 hover:bg-red-200 text-red-600 p-2 rounded flex items-center justify-center transition" title="Remove Variant">
                                        <span class="material-symbols-outlined text-sm">delete</span>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white border border-gray-200 rounded-lg p-5 shadow-sm">
                    <h4 class="font-bold text-gray-800 mb-4 border-b pb-2">Pricing & Logistics</h4>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Base Price (₦) <span class="text-red-500">*</span></label>
                            <input type="number" name="price" step="0.01" class="w-full border border-gray-300 p-2.5 rounded focus:ring-2 focus:ring-blue-500 outline-none text-lg font-bold text-gray-800" required>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Sale Price</label>
                                <input type="number" name="bulk_price" step="0.01" class="w-full border border-gray-300 p-2.5 rounded focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Global Stock</label>
                                <input type="number" name="stock_quantity" value="10" class="w-full border border-gray-300 p-2.5 rounded focus:ring-2 focus:ring-blue-500 outline-none" required>
                                <p class="text-[10px] text-gray-400 mt-1">Leave at 0 if tracking via variants.</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Weight (kg)</label>
                                <input type="number" name="weight_kg" step="0.01" placeholder="0.5" class="w-full border border-gray-300 p-2.5 rounded focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Min Order</label>
                                <input type="number" name="min_order" value="1" class="w-full border border-gray-300 p-2.5 rounded focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-lg p-5 shadow-sm">
                    <h4 class="font-bold text-gray-800 mb-4 border-b pb-2">Image Gallery</h4>
                    <div>
                        <input type="file" name="gallery[]" multiple accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2.5 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-gray-300 rounded p-1">
                        <p class="text-xs text-gray-400 mt-2 leading-relaxed">Select multiple images at once to create a gallery. The first image will be the primary thumbnail.</p>
                    </div>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-lg p-5 shadow-sm">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Publish Status</label>
                    <select name="status" class="w-full border border-gray-300 p-2.5 rounded bg-white focus:ring-2 focus:ring-blue-500 outline-none font-bold mb-4">
                        <option value="published">🟢 Published (Live)</option>
                        <option value="draft">🟡 Draft (Hidden)</option>
                        <option value="archived">🔴 Archived</option>
                    </select>
                    
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-lg shadow-md transition transform hover:-translate-y-0.5 text-lg flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined">cloud_upload</span> Save & Publish
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="p-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
        <h3 class="font-bold text-gray-800">Your Inventory</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-white text-xs text-gray-500 uppercase tracking-wider border-b border-gray-200">
                <tr>
                    <th class="px-6 py-4 font-bold">Product Details</th>
                    <th class="px-6 py-4 font-bold">Status</th>
                    <th class="px-6 py-4 font-bold">Base Price</th>
                    <th class="px-6 py-4 font-bold">Inventory</th>
                    <?php if($is_super): ?><th class="px-6 py-4 font-bold">Vendor</th><?php endif; ?>
                    <th class="px-6 py-4 font-bold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm">
                <?php if (empty($products)): ?>
                    <tr><td colspan="6" class="p-8 text-center text-gray-500 italic">No products found. Start by adding one above.</td></tr>
                <?php endif; ?>
                
                <?php foreach ($products as $p): 
                    $imgSrc = !empty($p['image_path']) ? '../' . htmlspecialchars($p['image_path']) : '../kiosk/uploads/default_product.png';
                    
                    // Count variants for this product
                    $v_stmt = $pdo->prepare("SELECT SUM(stock_quantity) as v_stock, COUNT(*) as v_count FROM product_variants WHERE product_id = ?");
                    $v_stmt->execute([$p['id']]);
                    $v_data = $v_stmt->fetch();
                    $has_variants = $v_data['v_count'] > 0;
                    $total_stock = $has_variants ? $v_data['v_stock'] : ($p['stock_quantity'] ?? 0);
                ?>
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-6 py-4">
                        <div class="flex items-center">
                            <img src="<?= $imgSrc ?>" class="w-12 h-12 rounded object-cover mr-4 border border-gray-200 shadow-sm bg-white" alt="Image">
                            <div>
                                <div class="font-bold text-gray-900"><?= htmlspecialchars($p['name']) ?></div>
                                <div class="text-[10px] text-gray-400 font-mono mt-0.5">Slug: <?= htmlspecialchars($p['slug'] ?? '') ?></div>
                                <div class="text-xs text-gray-500 font-medium mt-0.5"><?= htmlspecialchars($p['category']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <?php if(($p['status'] ?? 'published') == 'published'): ?>
                            <span class="bg-green-100 text-green-700 text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded">Live</span>
                        <?php elseif(($p['status'] ?? '') == 'draft'): ?>
                            <span class="bg-yellow-100 text-yellow-700 text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded">Draft</span>
                        <?php else: ?>
                            <span class="bg-gray-100 text-gray-600 text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded">Archived</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="font-bold text-gray-800">₦<?= number_format($p['price']) ?></div>
                        <?php if(!empty($p['sale_price'])): ?>
                            <div class="text-xs text-red-500 line-through">₦<?= number_format($p['sale_price']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <?php if($has_variants): ?>
                            <div class="text-xs text-blue-600 font-bold mb-1 border border-blue-200 bg-blue-50 inline-block px-1.5 py-0.5 rounded">
                                <?= $v_data['v_count'] ?> SKUs
                            </div><br>
                        <?php endif; ?>
                        
                        <?php if($total_stock <= 0): ?>
                            <span class="text-red-600 font-bold text-xs bg-red-50 px-2 py-1 rounded border border-red-100">Out of Stock</span>
                        <?php elseif($total_stock < 5): ?>
                            <span class="text-yellow-600 font-bold text-xs"><?= $total_stock ?> (Low)</span>
                        <?php else: ?>
                            <span class="text-green-600 font-bold text-xs"><?= $total_stock ?> in stock</span>
                        <?php endif; ?>
                    </td>
                    <?php if($is_super): ?>
                        <td class="px-6 py-4">
                            <span class="text-xs font-bold text-gray-600 border border-gray-200 bg-white px-2 py-1 rounded shadow-sm">
                                <?= htmlspecialchars($p['store_name'] ?? 'Unknown') ?>
                            </span>
                        </td>
                    <?php endif; ?>
                    <td class="px-6 py-4 text-right">
                        <div class="flex justify-end gap-2">
                            <a href="edit_product.php?id=<?= $p['id'] ?>" class="text-blue-500 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 p-1.5 rounded transition" title="Edit Product">
                                <span class="material-symbols-outlined text-sm">edit</span>
                            </a>
                            
                            <form method="POST" onsubmit="return confirm('WARNING: Are you sure you want to permanently delete this product, its variants, and its images?');">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <input type="hidden" name="delete_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 p-1.5 rounded transition" title="Delete Product">
                                    <span class="material-symbols-outlined text-sm">delete</span>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php echo "</main></div></body></html>"; ?>