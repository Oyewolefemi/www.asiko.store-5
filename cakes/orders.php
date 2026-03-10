<?php
require 'config.php';
requireLogin();
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@400;600;700&family=Material+Symbols+Outlined" rel="stylesheet"/>
</head>
<body class="bg-gray-50 pb-24 font-[Work Sans]">
    <div class="p-4 font-bold text-lg bg-white shadow-sm">My Orders</div>
    <div class="p-4 space-y-4">
        <?php foreach($orders as $o): ?>
        <div class="bg-white p-4 rounded-xl border shadow-sm">
            <div class="flex justify-between font-bold mb-2">
                <span>Order #<?= $o['id'] ?></span>
                <span class="text-orange-600">₦<?= number_format($o['total_amount']) ?></span>
            </div>
            <p class="text-sm text-gray-500"><?= $o['created_at'] ?></p>
            <span class="inline-block mt-2 px-2 py-1 bg-gray-100 rounded text-xs uppercase font-bold"><?= $o['status'] ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php include 'includes/bottom_nav.php'; ?>
</body>
</html>