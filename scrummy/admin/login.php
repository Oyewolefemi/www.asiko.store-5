<?php
// scrummy/admin/login.php
session_start();
require_once '../config.php'; 

$brandColor = '#ec6d13'; // Your specific brand color

if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'] ?? 'manager';
            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid manager credentials.";
        }
    } catch (PDOException $e) {
        $error = "Database error.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asiko+ Manager Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
</head>
<body class="bg-gray-900 h-screen flex items-center justify-center font-sans">
    <div class="bg-white p-8 rounded-2xl shadow-2xl max-w-md w-full border border-gray-100">
        <div class="text-center mb-8">
            <div class="h-16 w-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background-color: <?= $brandColor ?>20; color: <?= $brandColor ?>;">
                <span class="material-symbols-outlined text-3xl">shield_person</span>
            </div>
            <h1 class="text-2xl font-black text-gray-800">Asiko<span style="color: <?= $brandColor ?>;">+</span> Login</h1>
            <p class="text-sm text-gray-500 mt-1">Management Portal Access</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 text-red-600 p-3 rounded-lg mb-6 text-sm text-center border border-red-200 font-bold">
                <?= htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-5">
                <label class="block text-gray-700 text-xs font-bold uppercase tracking-widest mb-2">Username</label>
                <input type="text" name="username" required class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-opacity-50 transition bg-gray-50" style="--tw-ring-color: <?= $brandColor ?>;">
            </div>
            <div class="mb-6">
                <label class="block text-gray-700 text-xs font-bold uppercase tracking-widest mb-2">Password</label>
                <input type="password" name="password" required class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-opacity-50 transition bg-gray-50" style="--tw-ring-color: <?= $brandColor ?>;">
            </div>
            <button type="submit" class="w-full text-white font-bold py-4 px-4 rounded-xl shadow-lg transition transform hover:-translate-y-0.5 active:scale-95" style="background-color: <?= $brandColor ?>;">
                Enter Dashboard
            </button>
        </form>
    </div>
</body>
</html>