<?php
session_start();
if (!file_exists('setup_done.txt')) { header('Location: setup.php'); exit; }
require 'db.php';
include 'header.php';
$settingsFile = 'settings.json';
$settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [
    'business_name' => '',
    'currency' => '₦',
    'low_stock_threshold' => 5
];

$products = $pdo->query("SELECT * FROM products ORDER BY name")->fetchAll();
$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name")->fetchAll();

function getOrCreateId($pdo, $table, $name) {
    $stmt = $pdo->prepare("SELECT id FROM $table WHERE name=?");
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    if ($row) return $row['id'];
    $pdo->prepare("INSERT INTO $table (name) VALUES (?)")->execute([$name]);
    return $pdo->lastInsertId();
}

// Add purchase
if (isset($_POST['add'])) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE name=?");
    $stmt->execute([$_POST['product_name']]);
    $product = $stmt->fetch();
    
    if (!$product) {
        $error = "Product not found.";
    } else {
        try {
            $pdo->beginTransaction();
            
            $supplier_id = getOrCreateId($pdo, 'suppliers', trim($_POST['supplier_name']));
            $quantity = floatval($_POST['quantity']);
            $unit = $product['unit'];
            $price_per_unit = floatval($_POST['price_per_unit']);
            $total_price = $quantity * $price_per_unit;
            $date = $_POST['date'];
            $product_id = $product['id'];

            // 1. Insert the purchase record
            $stmt = $pdo->prepare("INSERT INTO purchases (product_id, supplier_id, quantity, unit, price_per_unit, total_price, date) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$product_id, $supplier_id, $quantity, $unit, $price_per_unit, $total_price, $date]);

            // 2. Update product stock quantity
            $pdo->prepare("UPDATE products SET quantity_in_stock=quantity_in_stock+? WHERE id=?")->execute([$quantity, $product_id]);
            
            // 3. Check for and complete associated Restock Task
            $stmtTask = $pdo->prepare("
                SELECT id 
                FROM tasks 
                WHERE reference_type = 'product' 
                AND reference_id = ? 
                AND status IN ('pending', 'in_progress')
                ORDER BY created_at ASC
                LIMIT 1
            ");
            $stmtTask->execute([$product_id]);
            $taskToComplete = $stmtTask->fetchColumn();

            if ($taskToComplete) {
                $pdo->prepare("UPDATE tasks SET status='completed', description=CONCAT(description, ' | FULFILLED by purchase on ', ?) WHERE id=?")
                    ->execute([date('Y-m-d H:i:s'), $taskToComplete]);
            }

            $pdo->commit();
            header("Location: purchases.php");
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// Display
$purchases = $pdo->query("SELECT pu.*, pr.name AS product_name, s.name AS supplier_name FROM purchases pu
    LEFT JOIN products pr ON pu.product_id=pr.id LEFT JOIN suppliers s ON pu.supplier_id=s.id ORDER BY pu.date DESC, pu.id DESC")->fetchAll();
?>

<h2 class="text-2xl font-bold mb-4">Purchase Records</h2>
<form method="post" class="flex flex-wrap gap-2 items-end mb-6">
    <input name="product_name" required list="productlist" placeholder="Product name" class="border p-2 rounded">
    <datalist id="productlist">
        <?php foreach ($products as $p): ?>
            <option value="<?=htmlspecialchars($p['name'])?>">
        <?php endforeach; ?>
    </datalist>
    <input name="supplier_name" required list="supplierlist" placeholder="Supplier name" class="border p-2 rounded">
    <datalist id="supplierlist">
        <?php foreach ($suppliers as $s): ?>
            <option value="<?=htmlspecialchars($s['name'])?>">
        <?php endforeach; ?>
    </datalist>
    <input name="quantity" type="number" min="0" step="0.01" required placeholder="Quantity" class="border p-2 rounded w-20">
    <input name="price_per_unit" type="number" min="0" step="0.01" required placeholder="Price per unit" class="border p-2 rounded w-28">
    <input name="date" type="date" required value="<?=date('Y-m-d')?>" class="border p-2 rounded w-32">
    <button name="add" class="bg-green-600 text-white px-4 py-2 rounded">Add Purchase</button>
</form>
<?php if (!empty($error)): ?>
    <div class="bg-red-100 text-red-800 p-2 rounded mb-4"><?=htmlspecialchars($error)?></div>
<?php endif; ?>
<table class="min-w-full bg-white shadow rounded">
    <thead class="bg-gray-200">
        <tr>
            <th class="p-2">Date</th>
            <th class="p-2">Product</th>
            <th class="p-2">Supplier</th>
            <th class="p-2">Quantity</th>
            <th class="p-2">Unit</th>
            <th class="p-2">Price/Unit</th>
            <th class="p-2">Total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($purchases as $row): ?>
        <tr>
            <td class="p-2"><?=htmlspecialchars($row['date'])?></td>
            <td class="p-2"><?=htmlspecialchars($row['product_name'])?></td>
            <td class="p-2"><?=htmlspecialchars($row['supplier_name'])?></td>
            <td class="p-2"><?=number_format($row['quantity'],2)?></td>
            <td class="p-2"><?=htmlspecialchars($row['unit'])?></td>
            <td class="p-2"><?=number_format($row['price_per_unit'],2)?></td>
            <td class="p-2"><?=number_format($row['total_price'],2)?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php include 'footer.php'; ?>