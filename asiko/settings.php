<?php
session_start();
require 'db.php';
require 'functions.php';
include 'header.php';

$msg = "";
$error = "";

// Helper to get multiple settings from the DB (using the function defined in functions.php)
function loadSettings($pdo) {
    $settings = [];
    $keys = [
        'business_name', 
        'business_email',
        'currency_symbol', 
        'currency_code',
        'low_stock_threshold', 
        'default_tax_rate'
    ];
    
    foreach ($keys as $key) {
        // Fallback defaults if the setting doesn't exist yet (e.g., if setup wasn't fully run)
        $default = match($key) {
            'currency_symbol' => '₦',
            'currency_code' => 'NGN',
            'low_stock_threshold' => 5,
            'default_tax_rate' => 0.00,
            default => ''
        };
        $settings[$key] = getSystemSetting($key, $default);
    }
    return $settings;
}


// --- Save Settings ---
if (isset($_POST['save'])) {
    // Basic input validation
    if (empty($_POST['business_name']) || empty($_POST['currency_symbol']) || empty($_POST['low_stock_threshold'])) {
        $error = "Business Name, Currency Symbol, and Low Stock Threshold are required.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Collect and validate inputs
            $data = [
                'business_name' => sanitizeInput($_POST['business_name']),
                'business_email' => filter_var($_POST['business_email'], FILTER_VALIDATE_EMAIL) ? sanitizeInput($_POST['business_email']) : 'admin@example.com',
                'currency_symbol' => sanitizeInput($_POST['currency_symbol']),
                'currency_code' => sanitizeInput($_POST['currency_code']),
                'low_stock_threshold' => max(1, intval($_POST['low_stock_threshold'])),
                'default_tax_rate' => max(0.0, floatval($_POST['default_tax_rate'])),
            ];

            // Use the universal updateSystemSetting function (defined in functions.php)
            foreach ($data as $key => $value) {
                updateSystemSetting($key, $value);
            }
            
            $pdo->commit();
            $msg = "Settings saved successfully!";
            
            // To ensure header.php reloads new settings, we refresh the page cleanly
            header('Location: settings.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error saving settings: " . $e->getMessage();
        }
    }
}

// --- Load Settings from DB ---
$settings = loadSettings($pdo);

?>
<div class="container mx-auto px-4 py-6">
    <h2 class="text-3xl font-bold text-gray-800 mb-6">System Configuration & Settings</h2>

    <?php if ($msg): ?><div class="bg-green-100 text-green-800 p-4 rounded-lg mb-4 font-medium border border-green-300"><?=$msg?></div><?php endif; ?>
    <?php if ($error): ?><div class="bg-red-100 text-red-800 p-4 rounded-lg mb-4 font-medium border border-red-300"><?=$error?></div><?php endif; ?>
    
    <div class="bg-white p-6 rounded-xl shadow-lg">
        <form method="post" class="space-y-6">
            <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">General & Contact Information</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Business Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Business Name *</label>
                    <input name="business_name" value="<?=htmlspecialchars($settings['business_name'])?>" 
                           class="mt-1 block w-full border border-gray-300 rounded-lg p-3 focus:ring-blue-500 focus:border-blue-500" required>
                    <p class="text-xs text-gray-500 mt-1">This name appears in the system header and reports.</p>
                </div>
                
                <!-- Business Email -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Business Contact Email</label>
                    <input name="business_email" type="email" value="<?=htmlspecialchars($settings['business_email'])?>" 
                           class="mt-1 block w-full border border-gray-300 rounded-lg p-3 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Used for system alerts and external communication.</p>
                </div>
            </div>

            <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2 pt-4">Financial Configuration</h3>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
                
                <!-- Currency Symbol -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Currency Symbol *</label>
                    <input name="currency_symbol" value="<?=htmlspecialchars($settings['currency_symbol'])?>" maxlength="3"
                           class="mt-1 block w-full border border-gray-300 rounded-lg p-3 focus:ring-blue-500 focus:border-blue-500 text-center font-extrabold" required>
                    <p class="text-xs text-gray-500 mt-1">e.g., ₦, $</p>
                </div>

                <!-- Currency Code -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Currency Code</label>
                    <input name="currency_code" value="<?=htmlspecialchars($settings['currency_code'])?>" maxlength="5"
                           class="mt-1 block w-full border border-gray-300 rounded-lg p-3 focus:ring-blue-500 focus:border-blue-500 text-center font-mono">
                    <p class="text-xs text-gray-500 mt-1">e.g., NGN, USD</p>
                </div>
                
                <!-- Default Tax Rate -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Default Tax Rate (%)</label>
                    <input name="default_tax_rate" type="number" min="0" max="100" step="0.01" value="<?=number_format($settings['default_tax_rate'], 2, '.', '')?>"
                           class="mt-1 block w-full border border-gray-300 rounded-lg p-3 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Applied to sales automatically.</p>
                </div>
            </div>

            <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2 pt-4">Inventory Configuration</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Low Stock Threshold -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Low Stock Threshold *</label>
                    <input name="low_stock_threshold" type="number" min="1" value="<?=intval($settings['low_stock_threshold'])?>"
                           class="mt-1 block w-full border border-gray-300 rounded-lg p-3 focus:ring-blue-500 focus:border-blue-500" required>
                    <p class="text-xs text-gray-500 mt-1">Triggers low stock warnings and automated restock tasks.</p>
                </div>
            </div>

            <button name="save" class="w-full bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 text-lg font-semibold transition">
                Save All Settings
            </button>
        </form>
    </div>
</div>
<?php include 'footer.php'; ?>
