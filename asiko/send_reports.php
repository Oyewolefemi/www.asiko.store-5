<?php
require 'db.php';
// Compose your report (query stats, attach/export CSV, etc.)
$report = "Daily Business Report\n";
$report .= "Total products: ".$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn()."\n";
// ... add more as needed

// Use mail() or a mail library to send email
mail("youremail@example.com", "Daily Business Report", $report);
?>
