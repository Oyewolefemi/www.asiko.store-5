<?php
// kiosk/Red/analytics.php
include 'header.php';

$is_super = ($_SESSION['admin_role'] ?? '') === 'superadmin';
$admin_id = $_SESSION['admin_id'];

// --- 1. DATA FETCHING ---

// A. Sales Last 30 Days (Daily)
$dates = [];
$sales_data = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dates[] = date('M d', strtotime($date));
    
    if ($is_super) {
        $sql = "SELECT SUM(total_amount) FROM orders WHERE DATE(order_date) = ? AND status = 'active'";
        $params = [$date];
    } else {
        $sql = "SELECT SUM(od.price_at_purchase * od.quantity) 
                FROM order_details od 
                JOIN orders o ON od.order_id = o.id 
                JOIN products p ON od.product_id = p.id
                WHERE DATE(o.order_date) = ? AND o.status = 'active' AND p.admin_id = ?";
        $params = [$date, $admin_id];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sales_data[] = $stmt->fetchColumn() ?: 0;
}

// B. Top Selling Products
if ($is_super) {
    $top_prod_sql = "SELECT p.name, SUM(od.quantity) as qty 
                     FROM order_details od JOIN products p ON od.product_id = p.id 
                     JOIN orders o ON od.order_id = o.id WHERE o.status = 'active'
                     GROUP BY p.id ORDER BY qty DESC LIMIT 5";
    $top_prods = $pdo->query($top_prod_sql)->fetchAll(PDO::FETCH_ASSOC);
} else {
    $top_prod_sql = "SELECT p.name, SUM(od.quantity) as qty 
                     FROM order_details od JOIN products p ON od.product_id = p.id 
                     JOIN orders o ON od.order_id = o.id WHERE o.status = 'active' AND p.admin_id = ?
                     GROUP BY p.id ORDER BY qty DESC LIMIT 5";
    $stmt = $pdo->prepare($top_prod_sql);
    $stmt->execute([$admin_id]);
    $top_prods = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// C. Order Status Distribution
if ($is_super) {
    $status_sql = "SELECT status, COUNT(*) as count FROM orders GROUP BY status";
    $status_res = $pdo->query($status_sql)->fetchAll(PDO::FETCH_KEY_PAIR);
} else {
    // For vendor, count distinct orders they are involved in
    $status_sql = "SELECT o.status, COUNT(DISTINCT o.id) as count 
                   FROM orders o JOIN order_details od ON o.id = od.order_id
                   JOIN products p ON od.product_id = p.id WHERE p.admin_id = ?
                   GROUP BY o.status";
    $stmt = $pdo->prepare($status_sql);
    $stmt->execute([$admin_id]);
    $status_res = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Business Analytics</h1>
    <p class="text-gray-500">Detailed insights into your store's performance.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white p-6 rounded-lg shadow border-l-4 border-indigo-500">
        <h3 class="text-sm font-bold text-gray-400 uppercase">30-Day Revenue</h3>
        <p class="text-2xl font-bold text-indigo-700">₦<?= number_format(array_sum($sales_data), 2) ?></p>
    </div>
    </div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
    
    <div class="bg-white p-6 rounded-lg shadow">
        <h3 class="font-bold text-gray-700 mb-4">Sales Trend (Last 30 Days)</h3>
        <canvas id="salesChart"></canvas>
    </div>

    <div class="space-y-8">
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="font-bold text-gray-700 mb-4">Top Selling Products</h3>
            <?php if(empty($top_prods)): ?>
                <p class="text-gray-400 text-sm">No sales data yet.</p>
            <?php else: ?>
                <ul class="space-y-3">
                    <?php foreach($top_prods as $tp): ?>
                        <li class="flex justify-between items-center text-sm">
                            <span class="text-gray-600"><?= htmlspecialchars($tp['name']) ?></span>
                            <span class="font-bold bg-green-100 text-green-700 px-2 py-1 rounded"><?= $tp['qty'] ?> sold</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="font-bold text-gray-700 mb-4">Order Status Distribution</h3>
            <canvas id="statusChart" height="150"></canvas>
        </div>
    </div>
</div>

<script>
    // --- SALES CHART ---
    const ctxSales = document.getElementById('salesChart').getContext('2d');
    new Chart(ctxSales, {
        type: 'line',
        data: {
            labels: <?= json_encode($dates) ?>,
            datasets: [{
                label: 'Revenue (₦)',
                data: <?= json_encode($sales_data) ?>,
                borderColor: '#4f46e5',
                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true } }
        }
    });

    // --- STATUS CHART ---
    const ctxStatus = document.getElementById('statusChart').getContext('2d');
    const statusData = <?= json_encode(array_values($status_res)) ?>;
    const statusLabels = <?= json_encode(array_keys($status_res)) ?>;
    
    new Chart(ctxStatus, {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusData,
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#3b82f6', '#6b7280']
            }]
        },
        options: { responsive: true }
    });
</script>

<?php echo "</main></div></body></html>"; ?>