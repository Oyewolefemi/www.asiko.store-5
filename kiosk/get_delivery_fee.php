<?php
// weds/kiosk/get_delivery_fee.php

function getShippingFeeFromApi($destination) {
    // Normalizing input
    $destination = trim(strtolower($destination));
    
    // Dummy fee mapping
    $fees = [
        'island' => 2500,
        'mainland' => 2000,
        'abuja' => 4500,
        'abeokuta' => 4000,
        'lagos' => 2000,
        'pick-up' => 0
    ];
    
    return $fees[$destination] ?? 3000; // Default fallback fee of 3000
}

// Only echo JSON if this file is accessed directly via the browser/AJAX
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    header('Content-Type: application/json');
    $location = $_GET['location'] ?? '';
    
    // Simulate network delay for API dummy
    sleep(1); 
    
    echo json_encode(['fee_amount' => getShippingFeeFromApi($location)]);
}
?>