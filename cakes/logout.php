<?php
require 'config.php';
unset($_SESSION['user_id']);
unset($_SESSION['user_name']);
// Only destroy if no admin logged in
if (!isset($_SESSION['admin_id'])) session_destroy();
header("Location: login.php");
exit;