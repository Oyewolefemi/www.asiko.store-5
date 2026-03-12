<?php
// kiosk/Red/edit_product.php
include 'header.php';

$is_super = ($_SESSION['admin_role'] ?? '') === 'superadmin';
$current_admin_id = $_SESSION['admin_id'];
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$success = '';
$error = '';

// --- 1. HANDLE UPDATES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $name = sanitize($_POST['name']);
    $raw_slug = !empty($_POST['slug']) ? sanitize($_POST['slug']) : $name;
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $raw_slug)));
    
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock_quantity']); 
    $category = sanitize($_POST['category']);
    $desc = sanitize($_POST['description']);
    $min_order = intval($_POST['min_order'] ?? 1);
    $bulk_price = !empty($_POST['bulk_price']) ? floatval($_POST['bulk_price']) : null;
    $status = in_array($_POST['status'], ['published', 'draft', 'archived']) ? $_POST['status'] : 'published';
    $weight_kg = floatval($_POST['weight_kg'] ?? 0);

    try {
        $pdo->beginTransaction();
        $checkOwner = $is_super ? "1=1" : "admin_id = $current_admin_id";
        
        $sql = "UPDATE products SET name=?, slug=?, price=?, stock_quantity=?, category=?, description=?, min_order_quantity=?, sale_price=?, status=?, weight_kg=? WHERE id=? AND $checkOwner";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $slug, $price, $stock, $category, $desc, $min_order, $bulk_price, $status, $weight_kg, $product_id]);
        
        // Handle New Gallery Images
        if (isset($_FILES['gallery']) && !empty($_FILES['gallery']['name'][0])) {
            $upload_dir = __DIR__ . '/../uploads/gallery/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            
            foreach ($_FILES['gallery']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['gallery']['error'][$key] === 0) {
                    $ext = strtolower(pathinfo($_FILES['gallery']['name'][$key], PATHINFO_EXTENSION));
                    if (in_array($ext, $allowed)) {
                        $filename = 'gal_' . uniqid() . '_' . time() . '.' . $ext;
                        if (move_uploaded_file($tmp_name, $upload_dir . $filename)) {
                            $rel_path = "kiosk/uploads/gallery/$filename";
                            $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, 0)")->execute([$product_id, $rel_path]);
                        }
                    }
                }
            }
        }

        // Handle Variants (Wipe old and insert new to prevent orphaned rows)
        $pdo->prepare("DELETE FROM product_variants WHERE product_id = ?")->execute([$product_id]);
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
        $success = "Product updated successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error updating product: " . $e->getMessage();
    }
}

// --- 2. FETCH EXISTING DATA ---
$checkOwner = $is_super ? "1=1" : "admin_id = $current_admin_id";
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND $checkOwner");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("<div class='p-8 text-center text-red-600 font-bold'>Product not found or access denied.</div>");
}

// Fetch Categories for Datalist
$cat_stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != ''");
$existing_categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch Variants & format for Alpine.js
$var_stmt = $pdo->prepare("SELECT sku, variant_name as name, price_adjustment as price, stock_quantity as stock FROM product_variants WHERE product_id = ?");
$var_stmt->execute([$product_id]);
$variants = $var_stmt->fetchAll(PDO::FETCH_ASSOC);
$variants_json = json_encode($variants ?: []);

// Fetch Gallery
$gal_stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC");
$gal_stmt->execute([$product_id]);
$gallery = $gal_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Edit Product</h1>
        <p class="text-sm text-gray-500">Modify inventory, variants, and pricing.</p>
    </div>
    <a href="admin_products.php" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold py-2 px-4 rounded transition flex items-center gap-2">
        <span class="material-symbols-outlined text-sm">arrow_back</span> Back to Inventory
    </a>
</div>

<?php if ($success): ?><div class="bg-green-100 text-green-700 p-4 mb-6 rounded shadow-sm font-bold flex items-center gap-2"><span class="material-symbols-outlined">check_circle</span> <?= $success ?></div><?php endif; ?>
<?php if ($error): ?><div class="bg-red-100 text-red-700 p-4 mb-6 rounded shadow-sm font-bold flex items-center gap-2"><span class="material-symbols-outlined">error</span> <?= $error ?></div><?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-8">
    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
        <h3 class="font-bold text-lg text-gray-800">Editing: <?= htmlspecialchars($product['name']) ?></h3>
    </div>
    
    <div class="p-6 grid grid-cols-1 xl:grid-cols-3 gap-8">
        <input type="hidden" name="update_product" value="1">
        
        <div class="xl:col-span-2 space-y-6">
            <div class="bg-white border border-gray-200 rounded-lg p-5 shadow-sm">
                <h4 class="font-bold text-gray-800 mb-4 border-b pb-2">Basic Information</h4>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Product Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($product['name']) ?>" class="w-full border border-gray-300 p-2.5 rounded focus:ring-2 focus:ring-blue-500 outline-none" required>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">SEO URL Slug</label>
                            <input type="text" name="slug" value="<?= htmlspecialchars($product['slug'] ?? '') ?>" class="w-full border border-gray-300 p-2.5 rounded focus:ring-2 focus:ring-blue-500 outline-none text-sm text-gray-500">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Category</label>
                            <input type="text" name="category" list="category-options" value="<?= htmlspecialchars($product['category']) ?>" class="w-full border border-gray-300 p-2.5 rounded focus:ring-2 focus:ring-blue-500 outline-none" required>
                            <datalist id="category-options">
                                <?php foreach($existing_categories as $c): ?><option value="<?= htmlspecialchars($c) ?>"></option><?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Detailed Description</label>
                        <textarea name="description" class="w-full border border-gray-300 p-2.5 rounded focus:ring-2 focus:ring-blue-500 outline-none h-32"><?= htmlspecialchars($product['description']) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-lg p-5 shadow-sm" x-data="{ variants: <?= htmlspecialchars($variants_json) ?> }">
                <div class="flex justify-between items-center border-b pb-2 mb-4">
                    <h4 class="font-bold text-gray-800">Product Variants & SKUs</h4>
                    <button type="button" @click="variants.push({sku: '', name: '', price: 0, stock: 10})" class="text-sm bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold py-1.5 px-3 rounded transition flex items-center gap-1">
                        <span class="material-symbols-outlined text-[16px]">add</span> Add Variant
                    </button>
                </div>
                
                <div class="space-y-3">
                    <template x-for="(v, index) in variants" :key="index">
                        <div class="flex flex-wrap md:flex-nowrap items-end gap-3 bg-gray-50 p-3 rounded border border-gray-200">
                            <div class="w-full md:w-1/4">
                                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wide mb-1">Variant Name</label>
                                <input type="text" name="variant_name[]" x-model="v.name" class="w-full border border-gray-300 p-2 rounded text-sm outline-none focus:ring-1 focus:ring-blue-500" required>
                            </div>
                            <div class="w-full md:w-1/4">
                                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wide mb-1">SKU</label>
                                <input type="text" name="variant_sku[]" x-model="v.sku" class="w-full border border-gray-300 p-2 rounded text-sm outline-none focus:ring-1 focus:ring-blue-500">
                            </div>
                            <div class="w-1/2 md:w-1/6">
                                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wide mb-1">Price Adj (+₦)</label>
                                <input type="number" name="variant_price[]" x-model="v.price" class="w-full border border-gray-300 p-2 rounded text-sm outline-none focus:ring-1 focus:ring-blue-500">
                            </div>
                            <div class="w-1/2 md:w-1/6">
                                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wide mb-1">Stock</label>
                                <input type="number" name="variant_stock[]" x-model="v.stock" class="w-full border border-gray-300 p-2 rounded text-sm outline-none focus:ring-1 focus:ring-blue-500" required>
                            </div>
                            <div class="w-full md:w-auto mt-2 md:mt-0">
                                <button type="button" @click="variants.splice(index, 1)" class="w-full md:w-auto bg-red-100 text-red-600 p-2 rounded flex items-center justify-center hover:bg-red-200 transition">
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
                        <label class="block text-sm font-bold text-gray-700 mb-1">Base Price (₦)</label>
                        <input type="number" name="price" value="<?= $product['price'] ?>" step="0.01" class="w-full border border-gray-300 p-2.5 rounded focus:ring-2 focus:ring-blue-500 outline-none text-lg font-bold" required>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Sale Price</label>
                            <input type="number" name="bulk_price" value="<?= $product['sale_price'] ?>" step="0.01" class="w-full border border-gray-300 p-2.5 rounded outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Global Stock</label>
                            <input type="number" name="stock_quantity" value="<?= $product['stock_quantity'] ?>" class="w-full border border-gray-300 p-2.5 rounded outline-none" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-lg p-5 shadow-sm">
                <h4 class="font-bold text-gray-800 mb-4 border-b pb-2">Image Gallery</h4>
                
                <?php if(!empty($gallery)): ?>
                    <div class="flex gap-2 mb-4 overflow-x-auto pb-2">
                        <?php foreach($gallery as $img): ?>
                            <div class="w-16 h-16 shrink-0 rounded border border-gray-200 overflow-hidden relative group bg-gray-50">
                                <img src="../<?= htmlspecialchars($img['image_path']) ?>" class="w-full h-full object-cover">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Add More Images</label>
                    <input type="file" name="gallery[]" multiple accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 border border-gray-300 rounded p-1">
                </div>
            </div>

            <div class="bg-gray-50 border border-gray-200 rounded-lg p-5 shadow-sm">
                <label class="block text-sm font-bold text-gray-700 mb-2">Publish Status</label>
                <select name="status" class="w-full border border-gray-300 p-2.5 rounded bg-white focus:ring-2 focus:ring-blue-500 outline-none font-bold mb-4">
                    <option value="published" <?= $product['status'] == 'published' ? 'selected' : '' ?>>🟢 Published (Live)</option>
                    <option value="draft" <?= $product['status'] == 'draft' ? 'selected' : '' ?>>🟡 Draft (Hidden)</option>
                    <option value="archived" <?= $product['status'] == 'archived' ? 'selected' : '' ?>>🔴 Archived</option>
                </select>
                
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-lg shadow-md transition text-lg flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">save</span> Update Product
                </button>
            </div>
        </div>
    </div>
</form>

<?php echo "</main></div></body></html>"; ?>