<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'apex');
define('DB_CHARSET', 'utf8mb4');

// Application configuration
define('APP_NAME', 'Apex Assurance');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/apex_assurance');

// File upload configuration
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'mp4', 'mov']);

// Security configuration
define('SESSION_TIMEOUT', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 8);
define('LOGIN_ATTEMPTS_LIMIT', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Email configuration (for notifications)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'noreply@apexassurance.com');
define('SMTP_PASSWORD', 'your_app_password');

// Timezone
date_default_timezone_set('Africa/Nairobi');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
