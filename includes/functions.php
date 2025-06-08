<?php
require_once 'config.php';

// Authentication functions
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        $_SESSION['error'] = "Please log in to access this page.";
        header("Location: " . dirname($_SERVER['PHP_SELF']) . "/login.php");
        exit;
    }
}

function check_role($required_role) {
    if (!is_logged_in()) {
        return false;
    }
    return $_SESSION['user_type'] === $required_role;
}

function require_role($required_role) {
    if (!check_role($required_role)) {
        $_SESSION['error'] = "You don't have permission to access this page.";
        header("Location: " . dirname($_SERVER['PHP_SELF']) . "/dashboard.php");
        exit;
    }
}

// Input sanitization
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Password functions
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// User functions
function get_user_data($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM user WHERE Id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function get_user_full_name($user_id) {
    $user = get_user_data($user_id);
    if ($user) {
        return trim($user['first_name'] . ' ' . $user['second_name'] . ' ' . $user['last_name']);
    }
    return 'Unknown User';
}

// Policy functions
function generate_policy_number() {
    return 'APX-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
}

function get_policy_types() {
    global $conn;
    $result = $conn->query("SELECT * FROM policy_type WHERE is_active = 1");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Notification functions
function create_notification($user_id, $related_id, $related_type, $title, $message) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO notification (user_id, related_id, related_type, title, message) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $user_id, $related_id, $related_type, $title, $message);
    return $stmt->execute();
}

function get_unread_notification_count($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notification WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}

function mark_notification_read($notification_id) {
    global $conn;
    $stmt = $conn->prepare("UPDATE notification SET is_read = 1, read_at = NOW() WHERE Id = ?");
    $stmt->bind_param("i", $notification_id);
    return $stmt->execute();
}

// Dashboard functions
function get_policyholder_dashboard_summary($user_id) {
    global $conn;
    
    $summary = [
        'total_policies' => 0,
        'active_policies' => 0,
        'total_claims' => 0,
        'pending_claims' => 0,
        'approved_claims' => 0,
        'total_vehicles' => 0
    ];
    
    // Get policy counts
    $stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active FROM policy WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $policy_data = $result->fetch_assoc();
    $summary['total_policies'] = $policy_data['total'];
    $summary['active_policies'] = $policy_data['active'];
    
    // Get claim counts
    $stmt = $conn->prepare("SELECT COUNT(*) as total, 
                           SUM(CASE WHEN status IN ('Reported', 'Assigned', 'UnderReview') THEN 1 ELSE 0 END) as pending,
                           SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved
                           FROM accident_report WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $claim_data = $result->fetch_assoc();
    $summary['total_claims'] = $claim_data['total'];
    $summary['pending_claims'] = $claim_data['pending'];
    $summary['approved_claims'] = $claim_data['approved'];
    
    // Get vehicle count
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM car WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $vehicle_data = $result->fetch_assoc();
    $summary['total_vehicles'] = $vehicle_data['total'];
    
    return $summary;
}

// Logging functions
function log_activity($user_id, $action, $details = '') {
    global $conn;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $conn->prepare("INSERT INTO system_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $action, $details, $ip_address);
    return $stmt->execute();
}

// Utility functions
function format_currency($amount) {
    return 'KSh ' . number_format($amount, 2);
}

function format_date($date, $format = 'M d, Y') {
    return date($format, strtotime($date));
}

function format_datetime($datetime, $format = 'M d, Y H:i') {
    return date($format, strtotime($datetime));
}

function time_ago($datetime) {
    $time = time() - strtotime($datetime);
    $time = ($time < 1) ? 1 : $time;
    $tokens = [
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute',
        1 => 'second'
    ];
    
    foreach ($tokens as $unit => $text) {
        if ($time < $unit) continue;
        $numberOfUnits = floor($time / $unit);
        return $numberOfUnits . ' ' . $text . (($numberOfUnits > 1) ? 's' : '') . ' ago';
    }
}

// File upload functions
function upload_file($file, $directory = 'uploads/') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $filename = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    // Check file size
    if ($file_size > MAX_FILE_SIZE) {
        return false;
    }
    
    // Check file extension
    if (!in_array($file_ext, ALLOWED_FILE_TYPES)) {
        return false;
    }
    
    // Create directory if it doesn't exist
    if (!file_exists($directory)) {
        mkdir($directory, 0777, true);
    }
    
    // Generate unique filename
    $new_filename = uniqid() . '.' . $file_ext;
    $destination = $directory . $new_filename;
    
    if (move_uploaded_file($file_tmp, $destination)) {
        return $new_filename;
    }
    
    return false;
}

// Email functions (basic)
function send_notification_email($to, $subject, $message) {
    // Basic email function - can be enhanced with PHPMailer
    $headers = "From: " . SMTP_USERNAME . "\r\n";
    $headers .= "Reply-To: " . SMTP_USERNAME . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// Search and filter functions
function search_users($search_term, $user_type = null) {
    global $conn;
    $sql = "SELECT * FROM user WHERE (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $params = ["%$search_term%", "%$search_term%", "%$search_term%"];
    $types = "sss";
    
    if ($user_type) {
        $sql .= " AND user_type = ?";
        $params[] = $user_type;
        $types .= "s";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

// Validation functions
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_phone($phone) {
    // Kenyan phone number format validation
    $pattern = '/^(\+254|0)(7|1)\d{8}$/';
    return preg_match($pattern, $phone);
}

function validate_national_id($id) {
    // Basic Kenyan national ID validation
    return preg_match('/^\d{8}$/', $id);
}

function validate_license_plate($plate) {
    // Kenyan license plate format validation
    $pattern = '/^K[A-Z]{2}\s?\d{3}[A-Z]$/i';
    return preg_match($pattern, $plate);
}

// Security functions
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function rate_limit_check($identifier, $max_attempts = 5, $time_window = 900) {
    // Simple rate limiting - can be enhanced with Redis/Memcached
    $key = 'rate_limit_' . $identifier;
    $attempts = $_SESSION[$key] ?? 0;
    $time_key = 'rate_limit_time_' . $identifier;
    $first_attempt = $_SESSION[$time_key] ?? time();
    
    if (time() - $first_attempt > $time_window) {
        // Reset counter
        $_SESSION[$key] = 1;
        $_SESSION[$time_key] = time();
        return true;
    }
    
    if ($attempts >= $max_attempts) {
        return false;
    }
    
    $_SESSION[$key] = $attempts + 1;
    return true;
}
?>
