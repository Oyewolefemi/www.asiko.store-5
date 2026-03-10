<?php
// kiosk/Red/customers.php
include 'header.php';

// Only Super Admin should see full customer data for privacy, 
// OR Vendors see only customers who bought from them.
// For "Data Bank" mode, we'll allow Super Admin full access.
$is_super = ($_SESSION['admin_role'] ?? '') === 'superadmin';
$admin_id = $_SESSION['admin_id'];

if (!$is_super) {
    // Vendors: Show only users who ordered their products
    $sql = "SELECT DISTINCT u.id, u.name, u.email, u.phone, u.created_at,
            (SELECT MAX(o.order_date) FROM orders o 
             JOIN order_details od ON o.id = od.order_id 
             JOIN products p ON od.product_id = p.id 
             WHERE o.user_id = u.id AND p.admin_id = ?) as last_active,
             
            (SELECT SUM(od.price_at_purchase * od.quantity) FROM order_details od
             JOIN orders o ON od.order_id = o.id
             JOIN products p ON od.product_id = p.id
             WHERE o.user_id = u.id AND p.admin_id = ? AND o.status = 'active') as total_spent
            
            FROM users u
            JOIN orders o ON u.id = o.user_id
            JOIN order_details od ON o.id = od.order_id
            JOIN products p ON od.product_id = p.id
            WHERE p.admin_id = ?
            ORDER BY last_active DESC";
    $params = [$admin_id, $admin_id, $admin_id];
} else {
    // Super Admin: Show ALL users
    $sql = "SELECT u.id, u.name, u.email, u.phone, u.created_at,
            (SELECT MAX(order_date) FROM orders WHERE user_id = u.id) as last_active,
            (SELECT SUM(total_amount) FROM orders WHERE user_id = u.id AND status = 'active') as total_spent
            FROM users u
            ORDER BY u.created_at DESC";
    $params = [];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Customer Data Bank</h1>
        <p class="text-gray-500">Manage user contacts and view lifetime value.</p>
    </div>
    <button onclick="exportTableToCSV('customers.csv')" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded flex items-center">
        <span class="mr-2">📂</span> Export CSV
    </button>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left" id="customerTable">
            <thead class="bg-gray-100 text-sm text-gray-500 uppercase border-b">
                <tr>
                    <th class="px-6 py-4">Customer</th>
                    <th class="px-6 py-4">Contact Info</th>
                    <th class="px-6 py-4">Joined</th>
                    <th class="px-6 py-4">Last Active</th>
                    <th class="px-6 py-4 text-right">Lifetime Spent</th>
                    <th class="px-6 py-4 text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm">
                <?php foreach ($customers as $c): ?>
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-6 py-4 font-bold text-gray-800">
                        <?= htmlspecialchars($c['name']) ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex flex-col">
                            <a href="mailto:<?= htmlspecialchars($c['email']) ?>" class="text-blue-600 hover:underline flex items-center mb-1">
                                <span class="w-4 h-4 mr-1">✉️</span> <?= htmlspecialchars($c['email']) ?>
                            </a>
                            <?php if(!empty($c['phone'])): ?>
                                <a href="tel:<?= htmlspecialchars($c['phone']) ?>" class="text-gray-600 hover:text-green-600 flex items-center">
                                    <span class="w-4 h-4 mr-1">📞</span> <?= htmlspecialchars($c['phone']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-gray-400 text-xs italic">No phone</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-gray-500">
                        <?= date('M d, Y', strtotime($c['created_at'])) ?>
                    </td>
                    <td class="px-6 py-4 text-gray-500">
                        <?= $c['last_active'] ? date('M d, Y', strtotime($c['last_active'])) : 'Never' ?>
                    </td>
                    <td class="px-6 py-4 text-right font-mono font-bold text-green-600">
                        ₦<?= number_format($c['total_spent'] ?? 0, 2) ?>
                    </td>
                    <td class="px-6 py-4 text-center">
                         <a href="mailto:<?= htmlspecialchars($c['email']) ?>?subject=Special Offer from Asiko Mall" class="bg-blue-100 text-blue-700 px-3 py-1 rounded text-xs font-bold hover:bg-blue-200">
                            Reach Out
                         </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if(empty($customers)): ?>
        <div class="p-8 text-center text-gray-500">No customer data available yet.</div>
    <?php endif; ?>
</div>

<script>
function exportTableToCSV(filename) {
    var csv = [];
    var rows = document.querySelectorAll("table tr");
    for (var i = 0; i < rows.length; i++) {
        var row = [], cols = rows[i].querySelectorAll("td, th");
        for (var j = 0; j < cols.length; j++) 
            row.push('"' + cols[j].innerText.replace(/(\r\n|\n|\r)/gm, "").trim() + '"');
        csv.push(row.join(","));
    }
    var csvFile = new Blob([csv.join("\n")], {type: "text/csv"});
    var downloadLink = document.createElement("a");
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
}
</script>

<?php echo "</main></div></body></html>"; ?>