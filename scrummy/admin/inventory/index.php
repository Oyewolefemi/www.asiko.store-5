<?php
// FILE: admin/inventory/index.php
require_once '../../config.php';
// Include the header (which handles the HTML start)
include '../../includes/admin_header.php';

// Handle Add/Update Supply
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $qty = $_POST['quantity'];
    $unit = $_POST['unit'];
    $cost = $_POST['cost_price'];

    if(isset($_POST['id']) && !empty($_POST['id'])) {
        // Update Existing
        $stmt = $pdo->prepare("UPDATE inventory SET name=?, quantity=?, unit=?, cost_price=? WHERE id=?");
        $stmt->execute([$name, $qty, $unit, $cost, $_POST['id']]);
    } else {
        // Create New
        $stmt = $pdo->prepare("INSERT INTO inventory (name, quantity, unit, cost_price) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $qty, $unit, $cost]);
    }
    echo "<script>window.location.href='index.php';</script>";
}

// Handle Delete
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM inventory WHERE id = ?")->execute([$_GET['delete']]);
    echo "<script>window.location.href='index.php';</script>";
}

// Fetch Supplies (No suppliers join needed anymore)
$items = $pdo->query("SELECT * FROM inventory ORDER BY name")->fetchAll();
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Supplies & Ingredients</h1>
        <p class="text-sm text-gray-500">Keep your stock levels up to date</p>
    </div>
    <button onclick="openModal()" class="bg-primary hover:bg-orange-700 text-white px-4 py-2 rounded-lg font-bold flex items-center gap-2 shadow-sm transition">
        <span class="material-symbols-outlined">add</span> Add Supply
    </button>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-left border-collapse">
        <thead class="bg-gray-50 text-xs font-bold text-gray-500 uppercase border-b border-gray-100">
            <tr>
                <th class="p-4">Item Name</th>
                <th class="p-4">Stock Level</th>
                <th class="p-4">Cost (Avg)</th>
                <th class="p-4 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 text-sm">
            <?php foreach($items as $i): 
                $statusColor = $i['quantity'] <= $i['min_level'] ? 'text-red-600 bg-red-50' : 'text-gray-900';
            ?>
            <tr class="hover:bg-gray-50/50">
                <td class="p-4 font-bold text-gray-800"><?= htmlspecialchars($i['name']) ?></td>
                <td class="p-4">
                    <span class="px-2 py-1 rounded font-bold <?= $statusColor ?>">
                        <?= $i['quantity'] ?> <span class="text-xs font-normal text-gray-500"><?= $i['unit'] ?></span>
                    </span>
                    <?php if($i['quantity'] <= $i['min_level']): ?>
                        <span class="ml-2 text-[10px] font-bold text-red-500 uppercase tracking-wide">Low Stock</span>
                    <?php endif; ?>
                </td>
                <td class="p-4 font-mono">₦<?= number_format($i['cost_price']) ?> <span class="text-gray-400 text-xs">/<?= $i['unit'] ?></span></td>
                <td class="p-4 text-right">
                    <button onclick='editItem(<?= json_encode($i) ?>)' class="text-blue-600 font-bold hover:underline">Edit</button>
                    <a href="?delete=<?= $i['id'] ?>" onclick="return confirm('Delete this item?')" class="text-red-400 hover:text-red-600 ml-3 material-symbols-outlined align-middle text-lg">delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="modal" class="hidden fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
    <form method="POST" class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
        <h3 id="modal-title" class="text-lg font-bold mb-4">Add Supply</h3>
        <input type="hidden" name="id" id="item_id">
        
        <div class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Item Name</label>
                <input name="name" id="name" required placeholder="e.g. Rice, Oil, Beef" class="w-full border p-2.5 rounded-lg bg-gray-50 focus:bg-white transition outline-none border-gray-200 focus:border-primary">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Quantity</label>
                    <input type="number" step="0.01" name="quantity" id="quantity" required class="w-full border p-2.5 rounded-lg bg-gray-50 border-gray-200">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Unit</label>
                    <input name="unit" id="unit" placeholder="kg, pcs, liters" required class="w-full border p-2.5 rounded-lg bg-gray-50 border-gray-200">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Cost Price (Per Unit)</label>
                <input type="number" step="0.01" name="cost_price" id="cost_price" required class="w-full border p-2.5 rounded-lg bg-gray-50 border-gray-200">
                <p class="text-[10px] text-gray-400 mt-1">Used to calculate profit margins.</p>
            </div>
        </div>
        <div class="mt-6 flex gap-3">
            <button type="button" onclick="document.getElementById('modal').classList.add('hidden')" class="flex-1 py-3 font-bold text-gray-500 hover:bg-gray-100 rounded-xl">Cancel</button>
            <button type="submit" class="flex-1 py-3 bg-primary text-white font-bold rounded-xl shadow-lg shadow-orange-500/20">Save Item</button>
        </div>
    </form>
</div>

<script>
    function openModal() {
        document.getElementById('modal').classList.remove('hidden');
        document.getElementById('modal-title').innerText = "Add Supply";
        document.getElementById('item_id').value = "";
        document.forms[0].reset();
    }
    
    function editItem(data) {
        openModal();
        document.getElementById('modal-title').innerText = "Edit Supply";
        document.getElementById('item_id').value = data.id;
        document.getElementById('name').value = data.name;
        document.getElementById('quantity').value = data.quantity;
        document.getElementById('unit').value = data.unit;
        document.getElementById('cost_price').value = data.cost_price;
    }
</script>

</main></div></body></html>