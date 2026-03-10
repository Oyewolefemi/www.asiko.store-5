<?php
/**
 * Merged Functions File
 * Contains utility functions, security functions, database helpers, and system settings
 */

// Include database connection
require_once __DIR__ . '/db.php';

// =============================================================================
// SECTOR TERMINOLOGY MAP (The Translation Engine)
// =============================================================================

// Define the core translations for all target sectors.
// Keys are generic terms used in the codebase. Values are arrays of translations.
$TERM_MAP = [
    'retail_ecommerce' => [
        'main_title' => 'Retail Management',
        'inventory_link' => 'Products',
        'inventory_title' => 'Product Inventory',
        'product_name' => 'Product Name',
        'supplier_label' => 'Supplier / Vendor',
        'category_label' => 'Category',
        'quantity_label' => 'Quantity in Stock',
        'sales_title' => 'Sales & Revenue',
        'purchase_title' => 'Acquisitions',
        'unit_type' => 'Pcs/Unit',
        'cogs_label' => 'Cost of Goods Sold (COGS)',
        'gross_profit_label' => 'Gross Profit',
        'low_stock_task' => 'Restock Alert',
        'orders_title' => 'Customer Orders',
    ],
    'agribusiness_farming' => [
        'main_title' => 'Farm & Asset Management',
        'inventory_link' => 'Assets/Crops',
        'inventory_title' => 'Asset & Crop Register',
        'product_name' => 'Asset / Crop Name',
        'supplier_label' => 'Source / Acquisition',
        'category_label' => 'Asset Type / Crop',
        'quantity_label' => 'Quantity / Harvest',
        'sales_title' => 'Sales & Harvest Yield',
        'purchase_title' => 'Input Acquisitions',
        'unit_type' => 'Hectares/Bags',
        'cogs_label' => 'Cost of Input (COI)',
        'gross_profit_label' => 'Yield Profit',
        'low_stock_task' => 'Input Restock Task',
        'orders_title' => 'Yield Orders',
    ],
    'manufacturing_production' => [
        'main_title' => 'Production & Material SCM',
        'inventory_link' => 'Materials/Parts',
        'inventory_title' => 'Raw Material Stock',
        'product_name' => 'Material / Component',
        'supplier_label' => 'Material Vendor',
        'category_label' => 'Material Type',
        'quantity_label' => 'Material Quantity',
        'sales_title' => 'Finished Goods Sales',
        'purchase_title' => 'Raw Material Purchases',
        'unit_type' => 'Kgs/Liters',
        'cogs_label' => 'Cost of Materials (COM)',
        'gross_profit_label' => 'Production Margin',
        'low_stock_task' => 'Material Restock Order',
        'orders_title' => 'Production Orders',
    ],
    'logistics_fleet' => [
        'main_title' => 'Fleet & Spares Management',
        'inventory_link' => 'Vehicles/Spares',
        'inventory_title' => 'Fleet & Spare Parts Register',
        'product_name' => 'Vehicle / Spare Part',
        'supplier_label' => 'Parts Vendor / Garage',
        'category_label' => 'Asset Class',
        'quantity_label' => 'Quantity of Spares',
        'sales_title' => 'Maintenance & Parts Sales',
        'purchase_title' => 'Spare Parts Acquisition',
        'unit_type' => 'Pcs/Liters',
        'cogs_label' => 'Part Replacement Cost',
        'gross_profit_label' => 'Parts Margin',
        'low_stock_task' => 'Maintenance/Parts Order',
        'orders_title' => 'Maintenance Requests',
    ],
];

// =============================================================================
// CORE TRANSLATION FUNCTIONS (The Brain)
// =============================================================================

/**
 * Get the sector-specific term for a generic key.
 *
 * @param string $genericKey The key to translate (e.g., 'inventory_title').
 * @param string $defaultFallback The fallback text if no translation is found.
 * @return string The translated term.
 */
function getSectorTerm(string $genericKey, string $defaultFallback): string {
    global $TERM_MAP;
    
    // 1. Get the stored sector (defaults to retail for safety)
    $sector = getSystemSetting('business_sector', 'retail_ecommerce');
    
    // 2. Check the map for the specific sector and key
    if (isset($TERM_MAP[$sector][$genericKey])) {
        return $TERM_MAP[$sector][$genericKey];
    }
    
    // 3. Fallback to default
    return $defaultFallback;
}

/**
 * Suggests common units of measure based on the selected sector.
 * Used for pre-populating or guiding input fields.
 *
 * @return array Array of suggested unit strings.
 */
function suggestUnits(): array {
    $sector = getSystemSetting('business_sector', 'retail_ecommerce');
    
    return match ($sector) {
        'agribusiness_farming' => ['Bags', 'Kgs', 'Liters', 'Hectares', 'Sacks', 'Crates'],
        'manufacturing_production' => ['Kgs', 'Liters', 'Meters', 'Pcs', 'Rolls', 'Pallets'],
        'logistics_fleet' => ['Liters', 'Pcs', 'Gallons', 'Tires', 'Filters', 'Vehicles'],
        default => ['Pcs', 'Units', 'Kgs', 'Liters', 'Packs', 'Each'], // retail_ecommerce or default
    };
}


// =============================================================================
// ERROR HANDLING & DISPLAY FUNCTIONS
// ... (REST OF FILE UNCHANGED)
// =============================================================================

/**
 * Print an error message in a consistent format and halt execution.
 *
 * @param string $errorMsg The error message to display.
 */
function printError($errorMsg) {
    echo "<div style='padding: 10px; background-color: #f8d7da; color: #721c24; border-radius: 5px; margin: 10px 0;'>";
    echo "Error: " . htmlspecialchars($errorMsg);
    echo "</div>";
    exit();
}

// =============================================================================
// INPUT SANITIZATION & SECURITY FUNCTIONS
// =============================================================================

/**
 * Sanitize input data to prevent XSS and injection attacks.
 *
 * @param string $data The data to sanitize.
 * @return string The sanitized data.
 */
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a CSRF token and store it in the session.
 *
 * @return string The generated CSRF token.
 */
function generateCsrfToken() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

/**
 * Verify a provided CSRF token against the session token.
 *
 * @param string $token The token to verify.
 * @return bool True if valid, false otherwise.
 */
function verifyCsrfToken($token) {
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        unset($_SESSION['csrf_token']); // One-time token use
        return true;
    }
    return false;
}

// =============================================================================
// FORMATTING FUNCTIONS
// =============================================================================

/**
 * Format an amount as Nigerian Naira currency.
 *
 * @param float $amount
 * @return string
 */
function formatCurrency($amount) {
    return '₦' . number_format((float)$amount, 2);
}

// =============================================================================
// CATEGORY DATABASE FUNCTIONS
// =============================================================================

/**
 * Fetch all active categories from the database.
 *
 * @param PDO $pdo
 * @return array
 */
function getCategories(PDO $pdo) {
    try {
        $stmt = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Fetch category name by ID.
 *
 * @param PDO $pdo
 * @param int $categoryId
 * @return string|null
 */
function getCategoryNameById(PDO $pdo, $categoryId) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        return $stmt->fetchColumn() ?: null;
    } catch (Exception $e) {
        return null;
    }
}

// =============================================================================
// SYSTEM SETTINGS FUNCTIONS
// =============================================================================

/**
 * Get a system setting by key, or return default if not found.
 * Uses `system_settings` table schema from asikofull DB.
 *
 * @param string $key The setting key to retrieve
 * @param mixed $default Default value if setting not found
 * @return mixed The setting value or default
 */
function getSystemSetting(string $key, $default = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : $default;
    } catch (PDOException $e) {
        error_log("Error getting system setting '$key': " . $e->getMessage());
        return $default;
    }
}

/**
 * Update or insert a system setting.
 *
 * @param string $key The setting key
 * @param string $value The setting value
 * @return bool True on success, false on failure
 */
function updateSystemSetting(string $key, string $value): bool {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        return $stmt->execute([$key, $value]);
    } catch (PDOException $e) {
        error_log("Error updating system setting '$key': " . $e->getMessage());
        return false;
    }
}

/**
 * Get global low stock threshold from DB (fallback to 5 if not set).
 *
 * @return int The low stock threshold value
 */
function getLowStockThreshold(): int {
    return (int) getSystemSetting('low_stock_threshold', 5);
}

// =============================================================================
// SESSION & AUTHENTICATION FUNCTIONS
// =============================================================================

/**
 * Check if a user is logged in.
 *
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

// =============================================================================
// UTILITY FUNCTIONS
// =============================================================================

/**
 * Redirect helper function.
 *
 * @param string $url The URL to redirect to
 */
function redirect(string $url) {
    header("Location: inventory.php");
    exit;
}

?>