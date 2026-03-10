<?php
session_start();
require 'db.php';
require 'functions.php';

// Create uploads directory if it doesn't exist
if (!is_dir('uploads/products')) {
    mkdir('uploads/products', 0755, true);
}

// Image handling functions
function resizeImage($source, $destination, $maxWidth = 800, $maxHeight = 600, $quality = 85) {
    list($width, $height, $type) = getimagesize($source);
    
    // Calculate new dimensions
    $ratio = min($maxWidth / $width, $maxHeight / $height);
    $newWidth = (int)($width * $ratio);
    $newHeight = (int)($height * $ratio);
    
    // Create new image
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($source);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($source);
            // Preserve transparency
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            break;
        case IMAGETYPE_WEBP:
            $sourceImage = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }
    
    imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // Save image
    switch ($type) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($newImage, $destination, $quality);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($newImage, $destination, 9);
            break;
        case IMAGETYPE_WEBP:
            $result = imagewebp($newImage, $destination, $quality);
            break;
        default:
            $result = false;
    }
    
    imagedestroy($sourceImage);
    imagedestroy($newImage);
    
    return $result;
}

function uploadProductImages($files, $productId) {
    $uploadedImages = [];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    $maxImages = 5;
    
    if (!is_array($files['tmp_name'])) {
        $files = [
            'tmp_name' => [$files['tmp_name']],
            'name' => [$files['name']],
            'size' => [$files['size']],
            'type' => [$files['type']],
            'error' => [$files['error']]
        ];
    }
    
    $imageCount = 0;
    foreach ($files['tmp_name'] as $index => $tmpName) {
        if ($imageCount >= $maxImages) break;
        if ($files['error'][$index] !== UPLOAD_ERR_OK) continue;
        if (empty($tmpName)) continue;
        
        // Validate file
        if ($files['size'][$index] > $maxFileSize) {
            $_SESSION['error'] = 'File too large: ' . $files['name'][$index];
            continue;
        }
        
        $fileType = mime_content_type($tmpName);
        if (!in_array($fileType, $allowedTypes)) {
            $_SESSION['error'] = 'Invalid file type: ' . $files['name'][$index];
            continue;
        }
        
        // Generate unique filename
        $extension = pathinfo($files['name'][$index], PATHINFO_EXTENSION);
        $filename = 'product_' . $productId . '_' . time() . '_' . $index . '.' . $extension;
        $destination = 'uploads/products/' . $filename;
        
        // Resize and save image
        if (resizeImage($tmpName, $destination)) {
            // Create thumbnail
            $thumbnailPath = 'uploads/products/thumb_' . $filename;
            resizeImage($tmpName, $thumbnailPath, 200, 200);
            
            $uploadedImages[] = $filename;
            $imageCount++;
        }
    }
    
    return $uploadedImages;
}

function deleteProductImage($filename) {
    $imagePath = 'uploads/products/' . $filename;
    $thumbnailPath = 'uploads/products/thumb_' . $filename;
    
    if (file_exists($imagePath)) unlink($imagePath);
    if (file_exists($thumbnailPath)) unlink($thumbnailPath);
}

// Pagination
$perPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

// Fetch categories & suppliers
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$suppliers  = $pdo->query("SELECT * FROM suppliers ORDER BY name")->fetchAll();

// Helper: get/create ID by name
function getOrCreateId($pdo, $table, $name) {
    $stmt = $pdo->prepare("SELECT id FROM $table WHERE name=?");
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    if ($row) return $row['id'];
    $pdo->prepare("INSERT INTO $table (name) VALUES (?)")->execute([$name]);
    return $pdo->lastInsertId();
}

// Handle image deletion
if (isset($_GET['delete_image'])) {
    $imageId = intval($_GET['delete_image']);
    $stmt = $pdo->prepare("SELECT filename FROM product_images WHERE id = ?");
    $stmt->execute([$imageId]);
    $image = $stmt->fetch();
    
    if ($image) {
        deleteProductImage($image['filename']);
        $pdo->prepare("DELETE FROM product_images WHERE id = ?")->execute([$imageId]);
        $_SESSION['success'] = 'Image deleted successfully';
    }
    
    redirect('products.php');
}

// Add product
if (isset($_POST['add'])) {
    try {
        $pdo->beginTransaction();
        
        $cat_id = getOrCreateId($pdo, 'categories', trim($_POST['category_name']));
        $sup_id = getOrCreateId($pdo, 'suppliers', trim($_POST['supplier_name']));
        
        // UPDATED: Added cost_price field to INSERT
        $stmt = $pdo->prepare("
            INSERT INTO products (name, sku, category_id, supplier_id, quantity_in_stock, unit, selling_price, cost_price, minimum_stock_level, description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['name'],
            $_POST['sku'],
            $cat_id,
            $sup_id,
            $_POST['quantity_in_stock'],
            $_POST['unit'],
            $_POST['selling_price'],
            $_POST['cost_price'] ?? 0, // NEW PARAMETER
            $_POST['minimum_stock_level'] ?? 5,
            $_POST['description'] ?? ''
        ]);
        
        $productId = $pdo->lastInsertId();
        
        // Handle image uploads
        if (!empty($_FILES['images']['tmp_name'][0])) {
            $uploadedImages = uploadProductImages($_FILES['images'], $productId);
            
            foreach ($uploadedImages as $filename) {
                $stmt = $pdo->prepare("INSERT INTO product_images (product_id, filename) VALUES (?, ?)");
                $stmt->execute([$productId, $filename]);
            }
        }
        
        $pdo->commit();
        $_SESSION['success'] = 'Product added successfully';
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Error adding product: ' . $e->getMessage();
    }
    
    redirect('products.php');
}

// Delete product
if (isset($_GET['delete'])) {
    try {
        $pdo->beginTransaction();
        
        // Delete associated images
        $stmt = $pdo->prepare("SELECT filename FROM product_images WHERE product_id = ?");
        $stmt->execute([$_GET['delete']]);
        $images = $stmt->fetchAll();
        
        foreach ($images as $image) {
            deleteProductImage($image['filename']);
        }
        
        $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$_GET['delete']]);
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$_GET['delete']]);
        
        $pdo->commit();
        $_SESSION['success'] = 'Product deleted successfully';
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Error deleting product';
    }
    
    redirect('products.php');
}

// Edit mode
$editMode = false;
$editProduct = null;
$productImages = [];
if (isset($_GET['edit'])) {
    $editMode = true;
    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name, s.name AS supplier_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        WHERE p.id = ?
    ");
    $stmt->execute([$_GET['edit']]);
    $editProduct = $stmt->fetch();
    
    if ($editProduct) {
        // Fetch product images
        $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY id");
        $stmt->execute([$editProduct['id']]);
        $productImages = $stmt->fetchAll();
    } else {
        $editMode = false;
    }
}

// Update product
if (isset($_POST['update'])) {
    try {
        $pdo->beginTransaction();
        
        $cat_id = getOrCreateId($pdo, 'categories', trim($_POST['category_name']));
        $sup_id = getOrCreateId($pdo, 'suppliers', trim($_POST['supplier_name']));
        
        // UPDATED: Added cost_price field to UPDATE
        $stmt = $pdo->prepare("
            UPDATE products
            SET name=?, sku=?, category_id=?, supplier_id=?, quantity_in_stock=?, unit=?, selling_price=?, cost_price=?, minimum_stock_level=?, description=?
            WHERE id=?
        ");
        $stmt->execute([
            $_POST['name'],
            $_POST['sku'],
            $cat_id,
            $sup_id,
            $_POST['quantity_in_stock'],
            $_POST['unit'],
            $_POST['selling_price'],
            $_POST['cost_price'] ?? 0, // NEW PARAMETER
            $_POST['minimum_stock_level'] ?? 5,
            $_POST['description'] ?? '',
            $_POST['id']
        ]);
        
        // Handle new image uploads
        if (!empty($_FILES['images']['tmp_name'][0])) {
            $uploadedImages = uploadProductImages($_FILES['images'], $_POST['id']);
            
            foreach ($uploadedImages as $filename) {
                $stmt = $pdo->prepare("INSERT INTO product_images (product_id, filename) VALUES (?, ?)");
                $stmt->execute([$_POST['id'], $filename]);
            }
        }
        
        $pdo->commit();
        $_SESSION['success'] = 'Product updated successfully';
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Error updating product';
    }
    
    redirect('products.php');
}

// Count total
$totalRows = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

// Fetch products with image count
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, s.name AS supplier_name,
           COUNT(pi.id) as image_count
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    LEFT JOIN product_images pi ON p.id = pi.product_id
    GROUP BY p.id
    ORDER BY p.id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute();
$products = $stmt->fetchAll();

include 'header.php';
?>

<style>
.image-preview {
    position: relative;
    display: inline-block;
    margin: 5px;
}
.image-preview img {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
}
.image-preview .delete-btn {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ef4444;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    text-decoration: none;
}
.image-preview .delete-btn:hover {
    background: #dc2626;
}
</style>

<?php if (isset($_SESSION['success'])): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4 gap-2">
    <h2 class="text-xl font-bold">Products</h2>
    <a href="export.php?table=products" class="bg-green-600 text-white px-3 py-2 rounded shadow hover:bg-green-700 transition">Export Products</a>
</div>

<!-- Add/Edit Product Form -->
<div class="bg-white shadow rounded p-4 mb-6">
<?php if ($editMode && $editProduct): ?>
    <form method="post" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="id" value="<?=$editProduct['id']?>">
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <input name="name" required placeholder="Product name" value="<?=htmlspecialchars($editProduct['name'])?>" class="border p-2 rounded">
            <input name="sku" required placeholder="SKU" value="<?=htmlspecialchars($editProduct['sku'])?>" class="border p-2 rounded">
            <input name="category_name" required list="categorylist" placeholder="Category" value="<?=htmlspecialchars($editProduct['category_name'])?>" class="border p-2 rounded">
            <input name="supplier_name" required list="supplierlist" placeholder="Supplier" value="<?=htmlspecialchars($editProduct['supplier_name'])?>" class="border p-2 rounded">
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <input name="quantity_in_stock" type="number" min="0" required placeholder="Quantity" value="<?=$editProduct['quantity_in_stock']?>" class="border p-2 rounded">
            <input name="unit" required placeholder="Unit" value="<?=htmlspecialchars($editProduct['unit'])?>" class="border p-2 rounded">
            
            <input name="selling_price" type="number" min="0" step="0.01" required placeholder="Selling Price" value="<?=$editProduct['selling_price']?>" class="border p-2 rounded">
            <!-- NEW FIELD: Cost Price -->
            <input name="cost_price" type="number" min="0" step="0.01" placeholder="Cost Price" value="<?=$editProduct['cost_price'] ?? 0?>" class="border p-2 rounded">
            <!-- END NEW FIELD -->
            
            <input name="minimum_stock_level" type="number" min="0" required placeholder="Min Stock" value="<?=$editProduct['minimum_stock_level']?>" class="border p-2 rounded">
        </div>
        
        <textarea name="description" placeholder="Product description (optional)" rows="3" class="w-full border p-2 rounded"><?=htmlspecialchars($editProduct['description'] ?? '')?></textarea>
        
        <!-- Current Images -->
        <?php if ($productImages): ?>
        <div class="border rounded p-3">
            <h4 class="font-semibold mb-2">Current Images:</h4>
            <div class="flex flex-wrap">
                <?php foreach ($productImages as $img): ?>
                <div class="image-preview">
                    <img src="uploads/products/thumb_<?=$img['filename']?>" alt="Product image">
                    <a href="?delete_image=<?=$img['id']?>" class="delete-btn" onclick="return confirm('Delete this image?')">&times;</a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Image Upload -->
        <div class="border rounded p-3">
            <label class="block font-semibold mb-2">Add New Images (Max 5, 5MB each):</label>
            <input type="file" name="images[]" multiple accept="image/*" class="border p-2 rounded w-full">
            <p class="text-sm text-gray-600 mt-1">Supported formats: JPG, PNG, WebP</p>
        </div>
        
        <div class="flex gap-2">
            <button name="update" class="bg-yellow-600 text-white px-4 py-2 rounded shadow hover:bg-yellow-700 transition">Update Product</button>
            <a href="products.php" class="bg-gray-500 text-white px-4 py-2 rounded shadow hover:bg-gray-600 transition">Cancel</a>
        </div>
    </form>
<?php else: ?>
    <form method="post" enctype="multipart/form-data" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <input name="name" required placeholder="Product name" class="border p-2 rounded">
            <input name="sku" required placeholder="SKU" class="border p-2 rounded">
            <input name="category_name" required list="categorylist" placeholder="Category" class="border p-2 rounded">
            <input name="supplier_name" required list="supplierlist" placeholder="Supplier" class="border p-2 rounded">
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <input name="quantity_in_stock" type="number" min="0" required placeholder="Quantity" class="border p-2 rounded">
            <input name="unit" required placeholder="Unit" class="border p-2 rounded">
            
            <input name="selling_price" type="number" min="0" step="0.01" required placeholder="Selling Price" class="border p-2 rounded">
            <!-- NEW FIELD: Cost Price -->
            <input name="cost_price" type="number" min="0" step="0.01" placeholder="Cost Price" class="border p-2 rounded">
            <!-- END NEW FIELD -->
            
            <input name="minimum_stock_level" type="number" min="0" required placeholder="Min Stock" value="5" class="border p-2 rounded">
        </div>
        
        <textarea name="description" placeholder="Product description (optional)" rows="3" class="w-full border p-2 rounded"></textarea>
        
        <!-- Image Upload -->
        <div class="border rounded p-3">
            <label class="block font-semibold mb-2">Product Images (Max 5, 5MB each):</label>
            <input type="file" name="images[]" multiple accept="image/*" class="border p-2 rounded w-full">
            <p class="text-sm text-gray-600 mt-1">Supported formats: JPG, PNG, WebP</p>
        </div>
        
        <button name="add" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 transition">Add Product</button>
    </form>
<?php endif; ?>
</div>

<datalist id="categorylist">
    <?php foreach ($categories as $cat): ?>
        <option value="<?=htmlspecialchars($cat['name'])?>"></option>
    <?php endforeach; ?>
</datalist>
<datalist id="supplierlist">
    <?php foreach ($suppliers as $sup): ?>
        <option value="<?=htmlspecialchars($sup['name'])?>"></option>
    <?php endforeach; ?>
</datalist>

<!-- Products Table -->
<div class="overflow-x-auto">
<table class="min-w-full bg-white shadow rounded mt-4 text-sm">
    <thead class="bg-gray-200">
        <tr>
            <th class="p-2 text-left">Images</th>
            <th class="p-2 text-left">Name</th>
            <th class="p-2 text-left">SKU</th>
            <th class="p-2 text-left">Category</th>
            <th class="p-2 text-left">Supplier</th>
            <th class="p-2 text-left">Qty</th>
            <th class="p-2 text-left">Unit</th>
            <th class="p-2 text-left">Price</th>
            <th class="p-2 text-left">Min Stock</th>
            <th class="p-2 text-left">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($products as $row): ?>
        <?php
        // Get first image for display
        $stmt = $pdo->prepare("SELECT filename FROM product_images WHERE product_id = ? LIMIT 1");
        $stmt->execute([$row['id']]);
        $firstImage = $stmt->fetch();
        ?>
        <tr class="<?= $row['quantity_in_stock'] <= $row['minimum_stock_level'] ? 'bg-red-50' : '' ?>">
            <td class="p-2">
                <?php if ($firstImage): ?>
                    <img src="uploads/products/thumb_<?=$firstImage['filename']?>" alt="Product" class="w-12 h-12 object-cover rounded">
                    <?php if ($row['image_count'] > 1): ?>
                        <small class="text-gray-500">+<?=$row['image_count']-1?> more</small>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="w-12 h-12 bg-gray-200 rounded flex items-center justify-center text-gray-400 text-xs">No Image</div>
                <?php endif; ?>
            </td>
            <td class="p-2">
                <div class="font-medium"><?=htmlspecialchars($row['name'])?></div>
                <?php if ($row['description']): ?>
                    <div class="text-xs text-gray-500"><?=htmlspecialchars(substr($row['description'], 0, 50))?><?=strlen($row['description']) > 50 ? '...' : ''?></div>
                <?php endif; ?>
            </td>
            <td class="p-2"><?=htmlspecialchars($row['sku'])?></td>
            <td class="p-2"><?=htmlspecialchars($row['category_name'])?></td>
            <td class="p-2"><?=htmlspecialchars($row['supplier_name'])?></td>
            <td class="p-2 <?= $row['quantity_in_stock'] <= $row['minimum_stock_level'] ? 'text-red-600 font-bold' : '' ?>">
                <?=$row['quantity_in_stock']?>
            </td>
            <td class="p-2"><?=htmlspecialchars($row['unit'])?></td>
            <td class="p-2">₦<?=number_format($row['selling_price'],2)?></td>
            <td class="p-2"><?=$row['minimum_stock_level']?></td>
            <td class="p-2">
                <a href="products.php?edit=<?=$row['id']?>" class="text-blue-600 hover:underline mr-2">Edit</a>
                <a href="products.php?delete=<?=$row['id']?>" class="text-red-600 hover:underline" onclick="return confirm('Delete this product and all its images?')">Delete</a>
            </td>
        </tr>
        <?php endforeach ?>
    </tbody>
</table>
</div>

<!-- Pagination Controls -->
<div class="flex gap-2 justify-center my-6">
    <?php if ($page > 1): ?>
        <a href="?page=<?=($page-1)?>" class="px-3 py-1 rounded bg-gray-200">Prev</a>
    <?php endif; ?>
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?=$i?>" class="px-3 py-1 rounded <?=($i==$page ? 'bg-blue-600 text-white' : 'bg-gray-100')?>"><?=$i?></a>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
        <a href="?page=<?=($page+1)?>" class="px-3 py-1 rounded bg-gray-200">Next</a>
    <?php endif; ?>
</div>

<script>
// Preview images before upload
document.addEventListener('DOMContentLoaded', function() {
    const imageInputs = document.querySelectorAll('input[type="file"][name="images[]"]');
    
    imageInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const files = e.target.files;
            let preview = input.parentNode.querySelector('.image-preview-container');
            
            if (!preview) {
                preview = document.createElement('div');
                preview.className = 'image-preview-container mt-2 flex flex-wrap gap-2';
                input.parentNode.appendChild(preview);
            }
            
            preview.innerHTML = '';
            
            Array.from(files).slice(0, 5).forEach((file, index) => {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'w-16 h-16 object-cover border rounded';
                        preview.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                }
            });
            
            if (files.length > 5) {
                alert('Maximum 5 images allowed. Only first 5 will be processed.');
            }
        });
    });
});
</script>

<?php include 'footer.php'; ?>
