<?php
require_once '../config.php';

// Redirect to dashboard if already logged in
if (isset($_SESSION['admin_id'])) { header("Location: index.php"); exit; }

// Generate CSRF Token for the session if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = ''; $success = '';

// Check if system is in "First-Run" mode (0 admins exist)
$adminCount = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
$isFirstRun = ($adminCount == 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF Validation Check
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Security check failed. Please refresh the page and try again.");
    }

    $action = $_POST['action'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($action === 'login') {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            // Regenerate session ID to prevent session fixation attacks
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['username'];
            header("Location: index.php"); 
            exit;
        } else {
            $error = "Invalid credentials.";
        }
    } 
    elseif ($action === 'register') {
        // Double-check lock on the backend
        if (!$isFirstRun) {
            $error = "Registration is locked. Contact the Superadmin.";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            
            if ($stmt->fetch()) {
                $error = "Username taken.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
                
                if ($stmt->execute([$username, $hash])) {
                    $success = "Superadmin created! Please login.";
                    $isFirstRun = false; // Immediately trigger lock for the UI
                } else {
                    $error = "Registration failed.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Access</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@400;600;700&family=Material+Symbols+Outlined" rel="stylesheet"/>
    <?php if($isFirstRun): ?>
    <script>
        function toggleForms() {
            document.getElementById('login-form').classList.toggle('hidden');
            document.getElementById('register-form').classList.toggle('hidden');
            const title = document.getElementById('form-title');
            title.innerText = document.getElementById('login-form').classList.contains('hidden') ? 'System Setup' : 'Admin Login';
        }
    </script>
    <?php endif; ?>
</head>
<body class="bg-gray-100 h-screen flex items-center justify-center font-[Work Sans]">
    <div class="w-full max-w-md bg-white p-8 rounded-2xl shadow-xl">
        <div class="flex justify-center mb-6">
            <div class="size-12 bg-gray-900 text-white rounded-xl flex items-center justify-center shadow-md">
                <span class="material-symbols-outlined text-2xl"><?= $isFirstRun ? 'admin_panel_settings' : 'shield_person' ?></span>
            </div>
        </div>
        
        <h2 id="form-title" class="text-2xl font-bold text-center mb-6 text-gray-800">
            <?= $isFirstRun && !$success ? 'System Setup' : 'Admin Login' ?>
        </h2>

        <?php if($isFirstRun && !$success): ?>
            <div class="bg-blue-50 border border-blue-200 text-blue-800 p-4 rounded-xl mb-6 text-sm">
                <strong>Welcome to Asiko+</strong><br>
                <span class="text-blue-600">No admins found. Create your initial Superadmin account below. This registration page will permanently lock once created.</span>
            </div>
        <?php endif; ?>

        <?php if($error) echo "<div class='bg-red-50 border border-red-200 text-red-600 p-3 rounded-xl mb-4 text-center text-sm font-bold'>$error</div>"; ?>
        <?php if($success) echo "<div class='bg-green-50 border border-green-200 text-green-600 p-3 rounded-xl mb-4 text-center text-sm font-bold'>$success</div>"; ?>

        <form method="POST" id="login-form" class="<?= $isFirstRun && !$success ? 'hidden' : 'space-y-4' ?>">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="login">
            
            <input type="text" name="username" placeholder="Username" required class="w-full border p-3 rounded-xl outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition">
            <input type="password" name="password" placeholder="Password" required class="w-full border p-3 rounded-xl outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition">
            
            <button class="w-full bg-gray-900 text-white font-bold py-4 rounded-xl hover:bg-black transition shadow-lg">Enter Dashboard</button>
            
            <?php if($isFirstRun && !$success): ?>
                <p class="text-center text-sm text-gray-500 mt-4 cursor-pointer hover:text-gray-900 font-medium" onclick="toggleForms()">Create Superadmin Instead</p>
            <?php endif; ?>
        </form>

        <?php if($isFirstRun && !$success): ?>
        <form method="POST" id="register-form" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="register">
            
            <input type="text" name="username" placeholder="Superadmin Username" required class="w-full border p-3 rounded-xl outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition">
            <input type="password" name="password" placeholder="Secure Password" required class="w-full border p-3 rounded-xl outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition">
            
            <button class="w-full bg-blue-600 text-white font-bold py-4 rounded-xl hover:bg-blue-700 transition shadow-lg">Create Superadmin</button>
            
            <p class="text-center text-sm text-gray-500 mt-4 cursor-pointer hover:text-gray-900 font-medium" onclick="toggleForms()">I already have an account</p>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>