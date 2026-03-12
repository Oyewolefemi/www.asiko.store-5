<?php
// config.php
// Securely load DB config from .env file (no external library)
if (!function_exists('loadEnv')) {
    function loadEnv($path) {
        if (!file_exists($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (substr(trim($line), 0, 1) === '#' || strpos($line, '=') === false) { 
                continue;
            }
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, "'\" ");
            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }
}

// Load master .env file from root
loadEnv(__DIR__ . '/../.env');

// Database config from .env using ASIKO prefix
$host    = $_ENV['DB_HOST'] ?? '127.0.0.1';
$db      = $_ENV['DB_NAME_ASIKO'] ?? '';
$user    = $_ENV['DB_USER_ASIKO'] ?? '';
$pass    = $_ENV['DB_PASS_ASIKO'] ?? '';
$charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    error_log('Kiosk database connection failed: ' . $e->getMessage());
    die('System unavailable. Please try again later.');
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
