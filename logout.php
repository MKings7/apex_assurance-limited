<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Log the logout if user is logged in
if (is_logged_in()) {
    log_activity($_SESSION['user_id'], "User Logout", "User logged out");
}

// Destroy session
session_destroy();

// Redirect to home page
header("Location: index.php");
exit;
?>
