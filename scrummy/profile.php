<?php
require 'config.php';
requireLogin();
$u = $pdo->prepare("SELECT * FROM users WHERE id=?");
$u->execute([$_SESSION['user_id']]);
$user = $u->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@400;600;700&family=Material+Symbols+Outlined" rel="stylesheet"/>
</head>
<body class="bg-gray-50 p-6 font-[Work Sans]">
    <h1 class="text-2xl font-bold mb-6">Profile</h1>
    <div class="bg-white p-6 rounded-2xl shadow-sm space-y-4">
        <div>
            <label class="text-xs text-gray-400 uppercase">Name</label>
            <p class="font-bold"><?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div>
            <label class="text-xs text-gray-400 uppercase">Email</label>
            <p class="font-bold"><?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <a href="logout.php" class="block w-full text-center bg-red-50 text-red-500 font-bold py-3 rounded-xl mt-4 hover:bg-red-100 transition">Logout</a>
    </div>
    <?php include 'includes/bottom_nav.php'; ?>
</body>
</html>