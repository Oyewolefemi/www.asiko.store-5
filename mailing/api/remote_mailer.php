<?php
// mailing/remote_mailer.php
// Helper function to trigger the central mailing microservice.

if (!function_exists('trigger_remote_email')) {
    /**
     * Send an email via the centralized API.
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body Email HTML body
     * @param string $app_source Name of the app sending the email (e.g., 'Kiosk', 'Cakes')
     * @param string $to_name Optional recipient name
     * @param string $alt_body Optional plain text fallback
     * @return array Status of the request ['status' => 'success'/'error', 'message' => '...']
     */
    function trigger_remote_email($to, $subject, $body, $app_source, $to_name = '', $alt_body = '') {
        // Retrieve configs from currently active .env
        $apiUrl = $_ENV['MAILING_API_URL'] ?? '';
        $apiKey = $_ENV['MAILING_API_KEY'] ?? '';

        if (empty($apiUrl) || empty($apiKey)) {
            error_log("Remote Mailer Error: Missing API URL or Key in environment variables.");
            return ['status' => 'error', 'message' => 'Configuration missing.'];
        }

        // Prepare JSON Payload
        $payload = json_encode([
            'app_source'      => $app_source,
            'recipient_email' => $to,
            'recipient_name'  => $to_name,
            'subject'         => $subject,
            'body'            => $body,
            'alt_body'        => $alt_body
        ]);

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        
        // Non-blocking timeout limits
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); 

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload),
            'Authorization: Bearer ' . $apiKey
        ]);

        // Uncomment line below if you face local SSL issues on localhost:
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("Remote Mailer cURL Error [$app_source]: " . $curlError);
            return ['status' => 'error', 'message' => 'Connection failed.'];
        }

        if ($httpCode !== 200) {
            error_log("Remote Mailer API Error [$app_source] ($httpCode): " . $response);
            return ['status' => 'error', 'message' => 'API returned an error.'];
        }

        $responseData = json_decode($response, true);
        return $responseData ?: ['status' => 'error', 'message' => 'Invalid API response.'];
    }
}
?>