<?php
// Database credentials
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'apexdb');

// Application configuration
define('SITE_NAME', 'Apex Assurance');
define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . '/apex_assurance/uploads/');
define('BASE_URL', 'http://localhost/apex_assurance/');

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
?>
