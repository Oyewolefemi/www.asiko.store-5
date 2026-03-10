<?php
session_start();
require_once 'db.php';
require_once 'functions.php';

// Check if setup is already completed
function isSetupComplete() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'setup_completed'");
        $stmt->execute();
        return $stmt->fetchColumn() === 'true';
    } catch (PDOException $e) {
        // Assume incomplete if the table doesn't exist
        return false;
    }
}

$setupComplete = isSetupComplete();
$msg = '';
$error = '';
$showLogin = isset($_GET['login']);

// Handle login (for users logging in via setup.php?login)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($email && $password) {
        try {
            // UPDATED SELECT: Join with admins table to get admin_id
            $stmt = $pdo->prepare("
                SELECT u.id, u.name, u.email, u.password, u.role, u.is_active, a.id as admin_id 
                FROM users u 
                LEFT JOIN admins a ON u.id = a.user_id 
                WHERE u.email = ? AND u.is_active = 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                
                // Set admin_id if user is admin
                if ($user['role'] === 'admin' && $user['admin_id']) {
                    $_SESSION['admin_id'] = $user['admin_id'];
                }
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            $error = "Login failed: " . $e->getMessage();
        }
    } else {
        $error = "Please enter both email and password.";
    }
}

// Handle setup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup'])) {
    try {
        $pdo->beginTransaction();

        // Default system settings from form or defaults
        $defaultSettings = [
            'business_name'       => $_POST['business_name'] ?? 'E-commerce Store',
            'business_email'      => $_POST['business_email'] ?? 'admin@example.com',
            'business_sector'     => $_POST['business_sector'] ?? 'retail_ecommerce', // <<-- NEW FIELD ADDED
            'currency_symbol'     => $_POST['currency_symbol'] ?? '₦',
            'currency_code'       => $_POST['currency_code'] ?? 'NGN',
            'low_stock_threshold' => (int)($_POST['low_stock_threshold'] ?? 5),
            'default_tax_rate'    => (float)($_POST['default_tax_rate'] ?? 0),
            'debug_mode'          => 'false',
            'setup_completed'     => 'true'
        ];

        $setupUserId = 1;

        foreach ($defaultSettings as $key => $value) {
            if (!updateSystemSetting($key, (string)$value)) { // Cast value to string for storage
                error_log("Failed to set system setting: {$key}");
            }
        }

        // Create admin user if not exists
        $userId = null;
        if (!empty($_POST['admin_email']) && !empty($_POST['admin_password'])) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$_POST['admin_email']]);
            $existingUserId = $stmt->fetchColumn();

            if (!$existingUserId) {
                $hashedPassword = password_hash($_POST['admin_password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, is_active) VALUES (?, ?, ?, 'admin', 1)");
                $stmt->execute([$_POST['admin_name'], $_POST['admin_email'], $hashedPassword]);
                $userId = $pdo->lastInsertId();
            } else {
                $userId = $existingUserId;
            }
            
            if ($userId) {
                $permissions = json_encode([
                    'manage_users' => true,
                    'manage_products' => true,
                    'manage_orders' => true,
                    'manage_finance' => true,
                    'manage_settings' => true,
                    'view_reports' => true
                ]);

                // Insert into admins table only if not already present
                $stmtCheckAdmin = $pdo->prepare("SELECT id FROM admins WHERE user_id = ?");
                $stmtCheckAdmin->execute([$userId]);
                if (!$stmtCheckAdmin->fetch()) {
                    $stmt = $pdo->prepare("INSERT INTO admins (user_id, admin_level, permissions) VALUES (?, 'super_admin', ?)");
                    $stmt->execute([$userId, $permissions]);
                }

                $stmtCheckWallet = $pdo->prepare("SELECT user_id FROM wallet WHERE user_id = ?");
                $stmtCheckWallet->execute([$userId]);
                if (!$stmtCheckWallet->fetch()) {
                    $stmt = $pdo->prepare("INSERT INTO wallet (user_id, balance) VALUES (?, 0.00)");
                    $stmt->execute([$userId]);
                }
            }
        }
        
        // Insert default categories if missing (rest of categories insertion logic unchanged)
        $defaultCategories = [
            ['Electronics', 'Electronic items and gadgets'],
            ['Clothing', 'Clothing and fashion items'],
            ['Home & Garden', 'Home and garden products'],
            ['Sports & Outdoors', 'Sports and outdoor equipment'],
            ['Books & Media', 'Books, movies, and media']
        ];

        $stmtCheckCat = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ?");
        $stmtInsertCat = $pdo->prepare("INSERT INTO categories (name, description, is_active) VALUES (?, ?, 1)");

        foreach ($defaultCategories as $cat) {
            $stmtCheckCat->execute([$cat[0]]);
            if ($stmtCheckCat->fetchColumn() == 0) {
                $stmtInsertCat->execute([$cat[0], $cat[1]]);
            }
        }

        // Insert default finance categories if missing
        $financeCategories = [
            ['Product Sales', 'income', 'Revenue from product sales'],
            ['Service Income', 'income', 'Revenue from services'],
            ['Other Income', 'income', 'Other sources of income'],
            ['Product Purchases', 'expense', 'Cost of goods purchased'],
            ['Marketing', 'expense', 'Marketing and advertising expenses'],
            ['Operating Expenses', 'expense', 'General operating expenses'],
            ['Delivery Costs', 'expense', 'Shipping and delivery expenses']
        ];

        $stmtCheckFin = $pdo->prepare("SELECT COUNT(*) FROM finance_categories WHERE name = ?");
        $stmtInsertFin = $pdo->prepare("INSERT INTO finance_categories (name, type, description, is_active) VALUES (?, ?, ?, 1)");

        foreach ($financeCategories as $fc) {
            $stmtCheckFin->execute([$fc[0]]);
            if ($stmtCheckFin->fetchColumn() == 0) {
                $stmtInsertFin->execute([$fc[0], $fc[1], $fc[2]]);
            }
        }

        // Insert default delivery fees if missing
        $defaultDeliveryFees = [
            ['Local Delivery', 500.00],
            ['State Wide', 1000.00],
            ['National', 1500.00]
        ];

        $stmtCheckDel = $pdo->prepare("SELECT COUNT(*) FROM delivery_fees WHERE location_name = ?");
        $stmtInsertDel = $pdo->prepare("INSERT INTO delivery_fees (location_name, fee_amount, description, is_active) VALUES (?, ?, ?, 1)");

        foreach ($defaultDeliveryFees as $delivery) {
            $stmtCheckDel->execute([$delivery[0]]);
            if ($stmtCheckDel->fetchColumn() == 0) {
                $stmtInsertDel->execute([$delivery[0], $delivery[1], $delivery[0] . ' delivery fee']);
            }
        }

        $pdo->commit();
        $msg = "Setup completed successfully! You can now log in to your dashboard.";

        if (!empty($_POST['admin_email'])) {
            // AFTER SUCCESSFUL SETUP, LOG IN THE NEW ADMIN (Unified logic)
            $stmt = $pdo->prepare("
                SELECT u.id, u.name, u.email, u.role, a.id as admin_id 
                FROM users u 
                LEFT JOIN admins a ON u.id = a.user_id 
                WHERE u.email = ?
            ");
            $stmt->execute([$_POST['admin_email']]);
            $newUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($newUser) {
                $_SESSION['user_id'] = $newUser['id'];
                $_SESSION['user_name'] = $newUser['name'];
                $_SESSION['user_email'] = $newUser['email'];
                $_SESSION['user_role'] = $newUser['role'];
                $_SESSION['admin_id'] = $newUser['admin_id']; // Ensure admin_id is set
            } else {
                 $_SESSION['user_id'] = $userId ?? 1;
                 $_SESSION['user_role'] = 'admin';
            }
            
            header('Location: dashboard.php');
            exit;
        }

    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Setup failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $showLogin ? 'Login' : 'Initial Setup' ?> - Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Optional custom style for the required asterisk */
        .required {
            color: #ef4444; /* Red-600 */
        }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
    <div class="w-full max-w-lg lg:max-w-4xl bg-white rounded-xl shadow-2xl overflow-hidden">
        
        <div class="bg-blue-600 text-white p-8 text-center relative">
            <?php if ($showLogin): ?>
                <h1 class="text-3xl font-extrabold mb-1">🔐 System Login</h1>
                <p class="text-lg opacity-90">Access your dashboard</p>
                <a href="setup.php" class="absolute top-4 right-4 text-sm font-semibold opacity-90 hover:opacity-100 bg-white/20 px-3 py-1 rounded-full transition">
                    ← Setup
                </a>
            <?php else: ?>
                <h1 class="text-3xl font-extrabold mb-1">🚀 Initial System Setup</h1>
                <p class="text-lg opacity-90">Configure your business details and create the first administrator account.</p>
                <?php if ($setupComplete): ?>
                    <a href="?login" class="absolute top-4 right-4 text-sm font-semibold opacity-90 hover:opacity-100 bg-white/20 px-3 py-1 rounded-full transition">
                        Go to Login →
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <div class="p-8">
            <?php if ($msg): ?>
                <div class="bg-green-100 text-green-800 p-4 rounded-lg mb-6 text-center font-medium border border-green-300">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="bg-red-100 text-red-800 p-4 rounded-lg mb-6 text-center font-medium border border-red-300">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($showLogin): ?>
                <div class="space-y-6">
                    <form method="post">
                        <input type="hidden" name="login" value="1">
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Email Address <span class="required">*</span></label>
                                <input type="email" name="email" required placeholder="Enter your email address" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                       class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-3 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Password <span class="required">*</span></label>
                                <input type="password" name="password" required placeholder="Enter your password"
                                       class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-3 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        
                        <button type="submit" class="w-full mt-6 bg-blue-600 text-white font-semibold rounded-lg px-4 py-3 hover:bg-blue-700 transition">
                            Login to Dashboard
                        </button>
                    </form>
                    
                    <div class="text-center text-sm">
                        <a href="#" onclick="alert('Please contact your administrator for password reset.'); return false;" class="text-blue-600 hover:text-blue-800">Forgot Password?</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <form method="post">
                        <input type="hidden" name="setup" value="1">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            
                            <div class="space-y-6">
                                <div class="p-5 border border-gray-200 rounded-lg bg-gray-50">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">Business Information</h3>
                                    
                                    <div class="space-y-3">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Business Name <span class="required">*</span></label>
                                            <input type="text" name="business_name" required placeholder="E.g., Asiko Store Ltd" class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Business Sector <span class="required">*</span></label>
                                            <select name="business_sector" required class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-3 focus:ring-blue-500 focus:border-blue-500">
                                                <option value="" disabled selected>Select Your Industry</option>
                                                <option value="retail_ecommerce">Retail / E-commerce</option>
                                                <option value="agribusiness_farming">Agribusiness / Farming</option>
                                                <option value="manufacturing_production">Manufacturing / Production</option>
                                                <option value="logistics_fleet">Logistics / Fleet</option>
                                            </select>
                                            <p class="text-xs text-gray-500 mt-1">This sets the terminology for the entire system.</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Business Email <span class="required">*</span></label>
                                            <input type="email" name="business_email" required placeholder="business@example.com" class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Business Phone</label>
                                            <input type="tel" name="business_phone" placeholder="+234 xxx xxx xxxx" class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="p-5 border border-gray-200 rounded-lg bg-gray-50">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">Financial Settings</h3>
                                    
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Currency Symbol <span class="required">*</span></label>
                                            <input type="text" name="currency_symbol" value="₦" required maxlength="3" class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Currency Code <span class="required">*</span></label>
                                            <input type="text" name="currency_code" value="NGN" required maxlength="3" class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Default Tax Rate (%)</label>
                                            <input type="number" name="default_tax_rate" value="0" min="0" max="100" step="0.01" class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Low Stock Threshold <span class="required">*</span></label>
                                            <input type="number" name="low_stock_threshold" value="5" required min="1" class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="space-y-6">
                                <div class="p-5 border border-gray-200 rounded-lg bg-gray-50">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">Admin Account</h3>
                                    
                                    <div class="space-y-3">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Admin Name <span class="required">*</span></label>
                                            <input type="text" name="admin_name" required placeholder="Administrator Name" class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Admin Email <span class="required">*</span></label>
                                            <input type="email" name="admin_email" required placeholder="admin@example.com" class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Admin Password <span class="required">*</span></label>
                                            <input type="password" name="admin_password" required minlength="6" placeholder="Minimum 6 characters" class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="p-5 border border-gray-200 rounded-lg bg-gray-50">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">System Settings</h3>
                                    
                                    <div class="space-y-3">
                                        <div class="flex items-start">
                                            <input type="checkbox" id="enable_reviews" name="enable_reviews" checked class="mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                            <label for="enable_reviews" class="ml-2 text-sm text-gray-700">Enable Product Reviews</label>
                                        </div>
                                        
                                        <div class="flex items-start">
                                            <input type="checkbox" id="require_payment_confirmation" name="require_payment_confirmation" checked class="mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                            <label for="require_payment_confirmation" class="ml-2 text-sm text-gray-700">Require Payment Confirmation</label>
                                        </div>
                                        
                                        <div class="flex items-start">
                                            <input type="checkbox" id="auto_approve_orders" name="auto_approve_orders" class="mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                            <label for="auto_approve_orders" class="ml-2 text-sm text-gray-700">Auto-approve Orders</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="w-full mt-8 bg-green-600 text-white font-extrabold rounded-lg px-4 py-3 text-lg hover:bg-green-700 transition">
                            Complete Setup and Launch System
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>