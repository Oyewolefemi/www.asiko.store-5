<?php
// Report all errors to see if PHP is blocking it
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. Define Recipient (Your Email)
$to = "leviathanurban@gmail.com";

// 2. Define Sender (Dynamically based on server domain)
// This effectively creates "no-reply@your-ip-address" or "no-reply@your-domain"
$domain = $_SERVER['SERVER_NAME'];
if (empty($domain) || $domain == '_') {
    $domain = 'ec2-user.local'; // Fallback for raw EC2
}
$fromEmail = "no-reply@" . str_replace('www.', '', $domain);

$subject = "EC2 Server Test - " . date("H:i:s");
$message = "If you are reading this, your EC2 server can send emails successfully!\n\nSent from: $fromEmail";

// 3. Set Headers
$headers = "From: Server Test <$fromEmail>\r\n";
$headers .= "Reply-To: $fromEmail\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

echo "<h3>Email Test Script</h3>";
echo "<p><strong>Attempting to send to:</strong> $to</p>";
echo "<p><strong>Sending from:</strong> $fromEmail</p>";

// 4. Send Email using the critical -f flag
// The '-f' flag tells the server "I am definitely this sender", which stops many blocks.
$sent = mail($to, $subject, $message, $headers, "-f" . $fromEmail);

if ($sent) {
    echo "<h2 style='color:green'>SUCCESS: PHP accepted the email for delivery.</h2>";
    echo "<p>Check your Spam/Junk folder in <strong>leviathanurban@gmail.com</strong> now.</p>";
    echo "<p><small>(Note: If it's not there in 2 minutes, AWS might be blocking Port 25. Check the note below.)</small></p>";
} else {
    echo "<h2 style='color:red'>FAILED: PHP could not hand off the email.</h2>";
    echo "<p>This usually means 'sendmail' is not installed or configured.</p>";
}
?>