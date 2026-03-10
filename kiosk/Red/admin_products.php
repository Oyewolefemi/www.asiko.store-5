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

// --- 1. HANDLE FORM SUBMISSION (Add / Edit / Delete) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Session expired. Please refresh.";
    } else {
        
        // --- 1A. HANDLE DELETION (Fix for Bug #12) ---
        if (isset($_POST['delete_id'])) {
            $del_id = intval($_POST['delete_id']);
            $checkOwner = $is_super ? "1=1" : "admin_id = $current_admin_id";
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND $checkOwner");
            $stmt->execute([$del_id]);
            $success = "Product deleted successfully.";
        } 
        
        // --- 1B. HANDLE CREATE / UPDATE ---
        elseif (isset($_POST['name'])) {
            $name = sanitize($_POST['name']);
            $price = floatval($_POST['price']);
            $stock = intval($_POST['stock_quantity']); 
            $category = sanitize($_POST['category']);
            $desc = sanitize($_POST['description']);
            $min_order = intval($_POST['min_order'] ?? 1);
            $bulk_price = !empty($_POST['bulk_price']) ? floatval($_POST['bulk_price']) : null;
            
            $imagePath = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowed)) {
                    $filename = uniqid('prod_') . '.' . $ext;
                    if (!is_dir("../uploads")) mkdir("../uploads", 0755, true);
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], "../uploads/$filename")) {
                        $imagePath = "kiosk/uploads/$filename";
                    }
                } else {
                    $error = "Invalid image format. Only JPG, PNG, WEBP allowed.";
                }
            }

            if (!$error) {
                if (isset($_POST['edit_id'])) {
                    $id = intval($_POST['edit_id']);
                    $checkOwner = $is_super ? "1=1" : "admin_id = $current_admin_id";
                    
                    $sql = "UPDATE products SET name=?, price=?, stock_quantity=?, category=?, description=?, min_order_quantity=?, sale_price=? ";
                    $params = [$name, $price, $stock, $category, $desc, $min_order, $bulk_price];
                    
                    if ($imagePath) {
                        $sql .= ", image_path=? ";
                        $params[] = $imagePath;
                    }
                    
                    $sql .= "WHERE id=? AND $checkOwner"; 
                    $params[] = $id;

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $success = "Product updated successfully.";
                } elseif (isset($_POST['create_product'])) {
                    if (!$imagePath) {
                        $error = "Product image is required.";
                    } else {
                        $sql = "INSERT INTO products (name, price, stock_quantity, category, description, image_path, min_order_quantity, sale_price, admin_id, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$name, $price, $stock, $category, $desc, $imagePath, $min_order, $bulk_price, $current_admin_id]);
                        $success = "Product added to your store!";
                    }
                }
            }
        }
    }
}

// --- 3. FETCH PRODUCTS ---
if ($is_super) {
    $stmt = $pdo->query("
        SELECT p.*, a.store_name 
        FROM products p 
        LEFT JOIN admins a ON p.admin_id = a.id 
        ORDER BY p.created_at DESC
    ");
} else {
    $stmt = $pdo->prepare("SELECT *, 'My Store' as store_name FROM products WHERE admin_id = ? ORDER BY created_at DESC");
    $stmt->execute([$current_admin_id]);
}
$products = $stmt->fetchAll();
?>

    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Product Management</h1>
            <p class="text-sm text-gray-500">
                <?= $is_super ? 'Manage inventory across all stores' : 'Manage your store inventory' ?>
            </p>
        </div>
        <button onclick="document.getElementById('productForm').classList.toggle('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition">
            + Add Product
        </button>
    </div>

    <?php if ($success): ?><div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?= $success ?></div><?php endif; ?>
    <?php if ($error): ?><div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?= $error ?></div><?php endif; ?>

    <div id="productForm" class="hidden bg-white p-6 rounded-lg shadow-md mb-8 border border-gray-200">
        <h3 class="font-bold text-lg mb-4 text-gray-700">Product Details</h3>
        <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="create_product" value="1">
            
            <div><label class="block text-sm font-bold text-gray-700">Product Name</label><input type="text" name="name" class="w-full border p-2 rounded" required></div>
            <div>
                <label class="block text-sm font-bold text-gray-700">Category</label>
                <select name="category" class="w-full border p-2 rounded">
                    <option>Electronics</option><option>Fashion</option><option>Home</option><option>Beauty</option><option>Food</option>
                </select>
            </div>
            <div><label class="block text-sm font-bold text-gray-700">Price (₦)</label><input type="number" name="price" step="0.01" class="w-full border p-2 rounded" required></div>
            
            <div><label class="block text-sm font-bold text-gray-700">Stock Qty</label><input type="number" name="stock_quantity" class="w-full border p-2 rounded" required></div>
            
            <div><label class="block text-sm font-bold text-gray-700">Min Order Qty</label><input type="number" name="min_order" value="1" class="w-full border p-2 rounded"></div>
            <div><label class="block text-sm font-bold text-gray-700">Sale Price (Optional)</label><input type="number" name="bulk_price" step="0.01" class="w-full border p-2 rounded"></div>
            
            <div class="md:col-span-2"><label class="block text-sm font-bold text-gray-700">Description</label><textarea name="description" class="w-full border p-2 rounded" rows="3"></textarea></div>
            <div class="md:col-span-2"><label class="block text-sm font-bold text-gray-700">Product Image</label><input type="file" name="image" class="w-full border p-2 rounded" accept="image/*"></div>

            <div class="md:col-span-2 pt-4">
                <button type="submit" class="w-full bg-green-600 text-white font-bold py-2 rounded hover:bg-green-700">Save Product</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-left">
            <thead class="bg-gray-100 text-sm text-gray-500 uppercase">
                <tr>
                    <th class="px-6 py-3">Product</th>
                    <th class="px-6 py-3">Price</th>
                    <th class="px-6 py-3">Stock</th>
                    <?php if($is_super): ?><th class="px-6 py-3">Store Owner</th><?php endif; ?>
                    <th class="px-6 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm">
                <?php foreach ($products as $p): 
                    $imgSrc = !empty($p['image_path']) ? '/' . htmlspecialchars($p['image_path']) : '/kiosk/uploads/default_product.png';
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 flex items-center">
                        <img src="<?= $imgSrc ?>" class="w-10 h-10 rounded object-cover mr-3 border" alt="Prod">
                        <div>
                            <div class="font-bold text-gray-800"><?= htmlspecialchars($p['name']) ?></div>
                            <div class="text-xs text-gray-500"><?= htmlspecialchars($p['category']) ?></div>
                        </div>
                    </td>
                    <td class="px-6 py-4 font-bold text-gray-700">₦<?= number_format($p['price']) ?></td>
                    <td class="px-6 py-4">
                        <?php $qty = $p['stock_quantity'] ?? 0; ?>
                        <?php if($qty < 5): ?>
                            <span class="text-red-600 font-bold"><?= $qty ?> (Low)</span>
                        <?php else: ?>
                            <span class="text-green-600"><?= $qty ?></span>
                        <?php endif; ?>
                    </td>
                    <?php if($is_super): ?>
                        <td class="px-6 py-4 text-blue-600 font-medium"><?= htmlspecialchars($p['store_name'] ?? 'Unknown') ?></td>
                    <?php endif; ?>
                    <td class="px-6 py-4 text-right space-x-2">
                        <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this product?');">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="delete_id" value="<?= $p['id'] ?>">
                            <button type="submit" class="text-red-500 hover:text-red-700 hover:underline">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php echo "</main></div></body></html>"; ?>