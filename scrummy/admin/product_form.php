<?php
require_once '../config.php';
include '../includes/admin_header.php';

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$id = $_GET['id'] ?? null;
$product = ['name'=>'','price'=>'','sale_price'=>'','description'=>'','image_url'=>'','category_id'=>1];
$existingIngredients = [];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    
    $stmtInv = $pdo->prepare("SELECT * FROM product_ingredients WHERE product_id = ?");
    $stmtInv->execute([$id]);
    $existingIngredients = $stmtInv->fetchAll(PDO::FETCH_ASSOC);
}

$inventoryItems = $pdo->query("SELECT * FROM inventory ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF Validation Check
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Security Check Failed: Invalid CSRF Token.");
    }

    $name = $_POST['name'];
    $price = $_POST['price'];
    $sale = !empty($_POST['sale_price']) ? $_POST['sale_price'] : null;
    $desc = $_POST['description'];
    
    // STRICT IMAGE UPLOAD LOGIC
    $img = $product['image_url']; 
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $maxSize = 2 * 1024 * 1024; // 2MB Limit
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        
        if ($_FILES['image']['size'] > $maxSize) {
            die("Upload Error: File exceeds the 2MB limit.");
        }
        
        // Verify true MIME type (ignoring extension spoofing)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $trueMime = finfo_file($finfo, $_FILES['image']['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($trueMime, $allowedMimes)) {
            die("Upload Error: Invalid file format. Only JPG, PNG, and WEBP are allowed.");
        }

        $targetDir = "../assets/uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        
        // Sanitize the original filename to prevent directory traversal
        $safeFileName = preg_replace('/[^a-zA-Z0-9.\-_]/', '', basename($_FILES['image']['name']));
        $fileName = time() . '_' . $safeFileName;
        $targetFile = $targetDir . $fileName;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            $img = 'assets/uploads/' . $fileName; 
        } else {
            die("Upload Error: Failed to move file to destination.");
        }
    }

    try {
        $pdo->beginTransaction();

        if ($id) {
            $sql = "UPDATE products SET name=?, price=?, sale_price=?, description=?, image_url=? WHERE id=?";
            $pdo->prepare($sql)->execute([$name, $price, $sale, $desc, $img, $id]);
            $pid = $id;
            
            $pdo->prepare("DELETE FROM product_ingredients WHERE product_id = ?")->execute([$pid]);
        } else {
            $sql = "INSERT INTO products (name, price, sale_price, description, image_url, category_id, is_active) VALUES (?, ?, ?, ?, ?, 1, 1)";
            $pdo->prepare($sql)->execute([$name, $price, $sale, $desc, $img]);
            $pid = $pdo->lastInsertId();
        }

        if (isset($_POST['ing_id'])) {
            $stmtIng = $pdo->prepare("INSERT INTO product_ingredients (product_id, inventory_id, quantity_required) VALUES (?, ?, ?)");
            foreach ($_POST['ing_id'] as $k => $invId) {
                $qty = $_POST['ing_qty'][$k];
                if ($qty > 0) {
                    $stmtIng->execute([$pid, $invId, $qty]);
                }
            }
        }

        $pdo->commit();
        echo "<script>window.location.href='products.php';</script>";
        exit;
    } catch(Exception $e) {
        $pdo->rollBack();
        die("Database Error: " . $e->getMessage());
    }
}
?>

<div class="max-w-4xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="products.php" class="p-2 rounded-full hover:bg-gray-100"><span class="material-symbols-outlined">arrow_back</span></a>
        <h1 class="text-2xl font-bold text-gray-900"><?= $id ? 'Edit Menu Item' : 'New Menu Item' ?></h1>
    </div>

    <form method="post" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <h3 class="font-bold text-gray-900 mb-4 border-b pb-2">Front of House (Customer View)</h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="label">Product Name</label>
                        <input name="name" value="<?= htmlspecialchars($product['name']) ?>" required class="input-field">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">Selling Price (₦)</label>
                            <input type="number" name="price" value="<?= $product['price'] ?>" required class="input-field font-bold">
                        </div>
                        <div>
                            <label class="label text-red-500">Sale Price (Optional)</label>
                            <input type="number" name="sale_price" value="<?= $product['sale_price'] ?>" class="input-field text-red-600">
                        </div>
                    </div>

                    <div>
                        <label class="label">Product Image</label>
                        <div class="border-2 border-dashed border-gray-200 rounded-xl p-4 text-center hover:bg-gray-50 transition relative">
                            <input type="file" name="image" accept="image/jpeg, image/png, image/webp" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                            <div class="space-y-2">
                                <span class="material-symbols-outlined text-3xl text-gray-300">cloud_upload</span>
                                <p class="text-xs text-gray-500 font-medium">Click to Upload New Image (JPG, PNG, WEBP - Max 2MB)</p>
                            </div>
                        </div>
                        
                        <?php if(!empty($product['image_url'])): ?>
                            <div class="mt-3 flex items-center gap-3 p-2 bg-gray-50 rounded-lg border border-gray-100">
                                <img src="../<?= htmlspecialchars($product['image_url']) ?>" class="size-12 rounded object-cover">
                                <span class="text-xs text-gray-400 font-bold uppercase">Current Image</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="label">Description</label>
                        <textarea name="description" class="input-field h-24"><?= htmlspecialchars($product['description']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="lg:col-span-1">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 h-full">
                <div class="flex justify-between items-center mb-4 border-b pb-2">
                    <h3 class="font-bold text-gray-900">Recipe (Back of House)</h3>
                    <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">Auto-Deduct</span>
                </div>
                
                <p class="text-xs text-gray-500 mb-4">Select items from Inventory that are consumed when this product is sold.</p>

                <div id="ingredient-list" class="space-y-3">
                    <?php 
                    foreach ($existingIngredients as $ex) {
                        echo renderIngredientRow($inventoryItems, $ex['inventory_id'], $ex['quantity_required']);
                    }
                    ?>
                </div>

                <button type="button" onclick="addIngredientRow()" class="mt-4 w-full py-2 border-2 border-dashed border-gray-200 text-gray-400 font-bold rounded-xl hover:border-primary hover:text-primary transition">
                    + Add Ingredient
                </button>
            </div>
        </div>

        <div class="lg:col-span-3 sticky bottom-4">
            <button class="w-full bg-gray-900 text-white font-bold py-4 rounded-xl shadow-xl hover:bg-black transition text-lg">
                Save Product & Recipe
            </button>
        </div>
    </form>
</div>

<style>
    .label { display: block; font-size: 0.75rem; font-weight: 700; color: #6b7280; text-transform: uppercase; margin-bottom: 0.25rem; }
    .input-field { width: 100%; border: 1px solid #e5e7eb; padding: 0.75rem; border-radius: 0.75rem; background: #f9fafb; outline: none; transition: all 0.2s; }
    .input-field:focus { background: white; border-color: #ec6d13; box-shadow: 0 0 0 1px #ec6d13; }
</style>

<script>
    const inventory = <?= json_encode($inventoryItems) ?>;
    
    function addIngredientRow() {
        const div = document.createElement('div');
        div.className = "flex gap-2 items-center bg-gray-50 p-2 rounded-lg";
        
        let options = `<option value="">Select Item...</option>`;
        inventory.forEach(i => {
            options += `<option value="${i.id}">${i.name} (${i.unit})</option>`;
        });

        div.innerHTML = `
            <select name="ing_id[]" class="flex-1 bg-white border border-gray-200 text-sm rounded p-1 outline-none font-medium">${options}</select>
            <input type="number" step="0.01" name="ing_qty[]" placeholder="Qty" class="w-16 bg-white border border-gray-200 text-sm rounded p-1 outline-none text-center font-bold">
            <button type="button" onclick="this.parentElement.remove()" class="text-gray-400 hover:text-red-500 material-symbols-outlined text-sm">close</button>
        `;
        document.getElementById('ingredient-list').appendChild(div);
    }
</script>

<?php
function renderIngredientRow($inventory, $selectedId, $qty) {
    $options = '';
    foreach ($inventory as $i) {
        $sel = ($i['id'] == $selectedId) ? 'selected' : '';
        $options .= "<option value='{$i['id']}' $sel>{$i['name']} ({$i['unit']})</option>";
    }
    return "
    <div class='flex gap-2 items-center bg-gray-50 p-2 rounded-lg'>
        <select name='ing_id[]' class='flex-1 bg-white border border-gray-200 text-sm rounded p-1 outline-none font-medium'>$options</select>
        <input type='number' step='0.01' name='ing_qty[]' value='$qty' class='w-16 bg-white border border-gray-200 text-sm rounded p-1 outline-none text-center font-bold'>
        <button type='button' onclick='this.parentElement.remove()' class='text-gray-400 hover:text-red-500 material-symbols-outlined text-sm'>close</button>
    </div>";
}
?>
</body>
</html>