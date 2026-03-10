<?php
// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load Env (Smart Path Loading for Security)
require_once __DIR__ . '/includes/EnvLoader.php';

$secureEnvPath = dirname(__DIR__) . '/.env'; // Ideal: Master file at root

if (file_exists($secureEnvPath)) {
    EnvLoader::load($secureEnvPath);
    $_SESSION['env_status'] = 'secure';
} else {
    die("Critical Error: Master configuration file missing.");
}

// Database Connection using SCRUMMY prefix
try {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $db   = getenv('DB_NAME_SCRUMMY');
    $user = getenv('DB_USER_SCRUMMY');
    $pass = getenv('DB_PASS_SCRUMMY');

    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Connection Error. Please check your configuration.");
}

// --- NEW: Load Settings ---
$settings = [
    'site_name' => 'Scrummy Nummy',
    'primary_color' => '#ec6d13',
    'logo_url' => ''
];
try {
    $stmt = $pdo->query("SELECT key_name, value FROM settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['key_name']] = $row['value'];
    }
} catch (Exception $e) { /* Fail silently if table doesn't exist yet */ }

// Helper Functions
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getBankDetails() {
    return [
        'bank' => getenv('BANK_NAME'),
        'number' => getenv('BANK_ACC_NO_MANUAL'),
        'name' => getenv('BANK_ACC_NAME_MANUAL')
    ];
}

// ============================================================
// MAIL EVENT HELPER
// Drop this function into each app's config.php (scrummy, cakes, etc.)
// Then call fire_mail_event() at the right moment in each app.
// That's all you ever need to do in the app itself.
// ============================================================

if (!function_exists('fire_mail_event')) {

    /**
     * Fire a named event to the central mailing microservice.
     *
     * @param string $event  The event name e.g. 'order.placed', 'user.registered'
     * @param array  $data   Key-value pairs used as {{placeholders}} in templates
     * @param string $app    The app identifier e.g. 'cakes', 'scrummy'
     * @return array         Response from the mailing API
     *
     * USAGE EXAMPLES:
     *
     * After a user registers:
     *   fire_mail_event('user.registered', [
     *       'customer_email' => $email,
     *       'customer_name'  => $name,
     *       'site_name'      => $settings['site_name'] ?? 'Our Store',
     *   ], 'cakes');
     *
     * After an order is placed (add this to checkout.php after $pdo->commit()):
     *   fire_mail_event('order.placed', [
     *       'customer_email'   => $guestEmail ?: $userEmail,
     *       'customer_name'    => $guestName  ?: $_SESSION['user_name'],
     *       'order_id'         => $newOrderId,
     *       'total_amount'     => number_format($serverCalculatedTotal),
     *       'payment_method'   => $paymentMethod === 'cod' ? 'Pay on Delivery' : 'Bank Transfer',
     *       'delivery_address' => $address,
     *       'site_name'        => $settings['site_name'] ?? 'Our Store',
     *   ], 'cakes');
     *
     * After admin updates order status (add this to admin/orders.php after the UPDATE query):
     *   fire_mail_event('order.status_changed', [
     *       'customer_email' => $customerEmail,
     *       'customer_name'  => $customerName,
     *       'order_id'       => $oid,
     *       'new_status'     => ucfirst(str_replace('_', ' ', $st)),
     *       'site_name'      => $settings['site_name'] ?? 'Our Store',
     *   ], 'cakes');
     */
    function fire_mail_event(string $event, array $data, string $app): array {
        $apiUrl = getenv('MAILING_API_URL') ?: ($_ENV['MAILING_API_URL'] ?? '');
        $apiKey = getenv('MAILING_API_KEY') ?: ($_ENV['MAILING_API_KEY'] ?? '');

        // Silently skip if not configured — never break the main app flow
        if (empty($apiUrl) || empty($apiKey)) {
            error_log("fire_mail_event: MAILING_API_URL or MAILING_API_KEY not set in .env");
            return ['status' => 'skipped', 'message' => 'Mailing not configured.'];
        }

        // Point to the event endpoint, not the generic send endpoint
        $eventUrl = rtrim($apiUrl, '/send.php');
        $eventUrl = rtrim($eventUrl, '/') . '/event.php';

        $payload = json_encode([
            'event' => $event,
            'app'   => $app,
            'data'  => $data,
        ]);

        $ch = curl_init($eventUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_CONNECTTIMEOUT => 2,   // Don't hang the user's request
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload),
                'Authorization: Bearer ' . $apiKey,
            ],
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("fire_mail_event [{$app}:{$event}] cURL error: {$curlError}");
            return ['status' => 'error', 'message' => $curlError];
        }

        $decoded = json_decode($response, true);
        return $decoded ?: ['status' => 'error', 'message' => 'Invalid response from mailing API.'];
    }
}
