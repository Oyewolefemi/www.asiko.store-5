<?php
// Quick test script to simulate an app sending an event
$ch = curl_init('https://asiko.store/mailing/api/event.php'); // Or use https://www.asiko.store... if that is your main URL

$payload = json_encode([
    "app_slug" => "scrummy",
    "event_type" => "receipt",
    "recipient_email" => "test@example.com",
    "payload" => [
        "customer_name" => "O.F Oyewole",
        "order_number" => "ORD-8923",
        "total_amount" => "₦ 12,500"
    ]
]);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST"); // Force POST
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($payload)
]);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_POSTREDIR, 3); // 3 tells cURL to keep POST data during 301/302 redirects

$response = curl_exec($ch);

if(curl_errno($ch)){
    echo 'Curl error: ' . curl_error($ch);
} else {
    echo "Response from API: " . $response;
}

curl_close($ch);
?>