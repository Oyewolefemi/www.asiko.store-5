<?php
// mailing/event_mappings.php
require_once __DIR__ . '/db.php'; // Ensure DB connection is loaded

// ==========================================================
// 1. AUTO-INITIALIZE MAPPINGS TABLE
// ==========================================================
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS event_mappings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        app_name VARCHAR(50) NOT NULL,
        event_name VARCHAR(100) NOT NULL,
        template_file VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_mapping (app_name, event_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) {
    error_log('Failed to initialize event_mappings table: ' . $e->getMessage());
    die('Mailing service initialization failed.');
}

// ==========================================================
// 2. HELPER FUNCTIONS
// ==========================================================

/**
 * Fetch all registered mappings for the dashboard
 */
function get_all_mappings($pdo) {
    return $pdo->query("SELECT * FROM event_mappings ORDER BY app_name ASC, event_name ASC")->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * The CRON JOB uses this to figure out which template to load
 * e.g., get_template_for_event($pdo, 'scrummy', 'order.placed') -> 'scrummy_receipt.html'
 */
function get_template_for_event($pdo, $app_name, $event_name) {
    // Allows fallback to '*' (All Apps) if a specific app isn't defined
    $stmt = $pdo->prepare("SELECT template_file FROM event_mappings WHERE (app_name = ? OR app_name = '*') AND event_name = ? ORDER BY app_name DESC LIMIT 1");
    $stmt->execute([$app_name, $event_name]);
    return $stmt->fetchColumn();
}

/**
 * Add or update a template mapping
 */
function add_mapping($pdo, $app, $event, $template) {
    $stmt = $pdo->prepare("INSERT INTO event_mappings (app_name, event_name, template_file) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE template_file = ?");
    return $stmt->execute([$app, $event, $template, $template]);
}

/**
 * Remove a mapping
 */
function delete_mapping($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM event_mappings WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Scan the /templates folder for available .html files
 */
function get_available_templates() {
    $templates = [];
    $dir = __DIR__ . '/templates/';
    
    // Auto-create templates folder if it doesn't exist yet
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $files = scandir($dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'html') {
            $templates[] = $file;
        }
    }
    return $templates;
}
?>