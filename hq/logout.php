<?php
// admin/logout.php
session_start();

// Destroy only the Master Admin session variables
unset($_SESSION['master_admin_id']);
unset($_SESSION['master_admin_user']);
unset($_SESSION['active_app']);

// Optionally destroy the whole session if they shouldn't be logged into Kiosk either
// session_destroy(); 

header("Location: login.php");
exit;