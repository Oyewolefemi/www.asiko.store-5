<?php
require 'config.php';

// If already logged in, go to home
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    if ($action === 'login') {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Verify Hash (Matches Database Seed)
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_address'] = $user['default_address'];
            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid email or password.";
        }
    } 
    elseif ($action === 'register') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $password = $_POST['password'];

        // Check Email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Email already registered.";
        } else {
            // Hash Password
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (name, email, phone, password, created_at) VALUES (?, ?, ?, ?, NOW())";
            if($pdo->prepare($sql)->execute([$name, $email, $phone, $hash])) {
                $success = "Account created! You can now login.";
            } else {
                $error = "Registration failed. Try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars($settings['site_name'] ?? 'Scrummy') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@400;600;700&family=Material+Symbols+Outlined" rel="stylesheet"/>
    <script> tailwind.config = { theme: { extend: { colors: { primary: '#ec6d13' }, fontFamily: { sans: ['Work Sans'] } } } } </script>
    <script>
        function toggleForms() {
            const login = document.getElementById('login-form');
            const register = document.getElementById('register-form');
            const title = document.getElementById('form-title');
            
            if (login.classList.contains('hidden')) {
                login.classList.remove('hidden');
                register.classList.add('hidden');
                title.innerText = 'Welcome Back';
            } else {
                login.classList.add('hidden');
                register.classList.remove('hidden');
                title.innerText = 'Create Account';
            }
        }
    </script>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen p-6 font-sans">
    
    <div class="w-full max-w-md bg-white p-8 rounded-2xl shadow-xl">
        <div class="flex justify-center mb-6">
            <div class="size-12 bg-primary text-white rounded-xl flex items-center justify-center shadow-lg shadow-orange-500/30">
                <span class="material-symbols-outlined text-2xl">lunch_dining</span>
            </div>
        </div>

        <h2 id="form-title" class="text-2xl font-bold text-center mb-2 text-gray-900">Welcome Back</h2>
        <p class="text-center text-gray-500 text-sm mb-6">Order your favorite meals in seconds.</p>

        <?php if($error): ?>
            <div class="bg-red-50 text-red-600 p-3 rounded-lg mb-4 text-sm font-bold text-center border border-red-100 flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-sm">error</span> <?= $error ?>
            </div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="bg-green-50 text-green-600 p-3 rounded-lg mb-4 text-sm font-bold text-center border border-green-100 flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-sm">check_circle</span> <?= $success ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="login-form" class="space-y-4">
            <input type="hidden" name="action" value="login">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Email Address</label>
                <input type="email" name="email" required class="w-full border border-gray-200 p-3 rounded-xl focus:border-primary outline-none transition bg-gray-50 focus:bg-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Password</label>
                <input type="password" name="password" required class="w-full border border-gray-200 p-3 rounded-xl focus:border-primary outline-none transition bg-gray-50 focus:bg-white">
            </div>
            <button type="submit" class="w-full bg-gray-900 text-white font-bold py-3.5 rounded-xl shadow-lg hover:bg-black transition mt-2">Sign In</button>
            
            <p class="mt-6 text-center text-sm text-gray-500 cursor-pointer hover:text-primary transition" onclick="toggleForms()">
                No account? <span class="font-bold underline">Register here</span>
            </p>
        </form>

        <form method="POST" id="register-form" class="hidden space-y-4">
            <input type="hidden" name="action" value="register">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Full Name</label>
                <input type="text" name="name" required class="w-full border border-gray-200 p-3 rounded-xl focus:border-primary outline-none transition bg-gray-50 focus:bg-white">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Email</label>
                    <input type="email" name="email" required class="w-full border border-gray-200 p-3 rounded-xl focus:border-primary outline-none transition bg-gray-50 focus:bg-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Phone</label>
                    <input type="tel" name="phone" required class="w-full border border-gray-200 p-3 rounded-xl focus:border-primary outline-none transition bg-gray-50 focus:bg-white">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Password</label>
                <input type="password" name="password" required class="w-full border border-gray-200 p-3 rounded-xl focus:border-primary outline-none transition bg-gray-50 focus:bg-white">
            </div>
            <button type="submit" class="w-full bg-primary text-white font-bold py-3.5 rounded-xl shadow-lg shadow-orange-500/30 hover:bg-opacity-90 transition mt-2">Create Account</button>
            
            <p class="mt-6 text-center text-sm text-gray-500 cursor-pointer hover:text-gray-900 transition" onclick="toggleForms()">
                Already have an account? <span class="font-bold underline">Login</span>
            </p>
        </form>
    </div>
</body>
</html>