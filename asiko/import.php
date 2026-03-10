<?php
session_start();
require 'db.php';
include 'header.php';

$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
    $file = $_FILES['csv']['tmp_name'];
    $f = fopen($file, 'r');
    $header = fgetcsv($f);
    while ($row = fgetcsv($f)) {
        // expects: name, category, supplier, quantity, unit, price
        list($name, $category, $supplier, $quantity, $unit, $price) = $row;
        // Ensure category
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE name=?");
        $stmt->execute([$category]);
        $cat_id = $stmt->fetchColumn();
        if (!$cat_id) {
            $pdo->prepare("INSERT INTO categories (name) VALUES (?)")->execute([$category]);
            $cat_id = $pdo->lastInsertId();
        }
        // Ensure supplier
        $stmt = $pdo->prepare("SELECT id FROM suppliers WHERE name=?");
        $stmt->execute([$supplier]);
        $sup_id = $stmt->fetchColumn();
        if (!$sup_id) {
            $pdo->prepare("INSERT INTO suppliers (name) VALUES (?)")->execute([$supplier]);
            $sup_id = $pdo->lastInsertId();
        }
        // Insert product
        $pdo->prepare("INSERT INTO products (name, category_id, supplier_id, quantity, unit, price) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$name, $cat_id, $sup_id, $quantity, $unit, $price]);
    }
    fclose($f);
    $msg = "Import complete!";
}
?>
<h2 class="text-2xl font-bold mb-4">Import Products from CSV</h2>
<?php if ($msg): ?><div class="bg-green-100 text-green-800 p-2 rounded mb-2"><?=$msg?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="flex gap-2 items-center">
    <input type="file" name="csv" accept=".csv" class="border p-2 rounded" required>
    <button class="bg-blue-600 text-white px-4 py-2 rounded">Import</button>
</form>
<p class="mt-4 text-sm text-gray-600">CSV columns: name, category, supplier, quantity, unit, price</p>
<?php include 'footer.php'; ?>
