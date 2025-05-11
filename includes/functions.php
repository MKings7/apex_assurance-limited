<?php
require_once 'db_connect.php';

// Function to check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Function to redirect to login if not authenticated
function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
}

// Function to check if user has specific role
function check_role($allowed_roles) {
    if (!is_logged_in()) {
        return false;
    }
    
    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    
    return in_array($_SESSION['user_type'], $allowed_roles);
}

// Function to require specific role, otherwise redirect
function require_role($allowed_roles) {
    if (!check_role($allowed_roles)) {
        $_SESSION['error'] = "You don't have permission to access this page.";
        header("Location: index.php");
        exit;
    }
}

// Function to get user data by ID
function get_user_data($user_id) {
    global $conn;
    
    $sql = "SELECT * FROM user WHERE Id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    } else {
        return false;
    }
}

// Function to get unread notification count for a user
function get_unread_notification_count($user_id) {
    global $conn;
    
    $sql = "SELECT COUNT(*) as count FROM notification WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'];
}

// Function to generate a unique policy number
function generate_policy_number() {
    $prefix = 'POL';
    $random = mt_rand(10000, 99999);
    $date = date('ymd');
    
    return $prefix . '-' . $date . $random;
}

// Function to get dashboard summary for policyholders
function get_policyholder_dashboard_summary($user_id) {
    global $conn;
    
    $summary = [
        'total_policies' => 0,
        'active_policies' => 0,
        'total_claims' => 0,
        'pending_claims' => 0,
        'approved_claims' => 0,
        'rejected_claims' => 0,
        'total_vehicles' => 0
    ];
    
    // Get policy counts
    $sql = "SELECT 
                COUNT(*) as total_policies, 
                SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_policies 
            FROM policy 
            WHERE user_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $summary['total_policies'] = $row['total_policies'];
        $summary['active_policies'] = $row['active_policies'];
    }
    
    // Get claim counts
    $sql = "SELECT 
                COUNT(*) as total_claims,
                SUM(CASE WHEN status IN ('Reported', 'Assigned', 'UnderReview') THEN 1 ELSE 0 END) as pending_claims,
                SUM(CASE WHEN status = 'Approved' OR status = 'Paid' THEN 1 ELSE 0 END) as approved_claims,
                SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected_claims
            FROM accident_report
            WHERE user_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $summary['total_claims'] = $row['total_claims'];
        $summary['pending_claims'] = $row['pending_claims'];
        $summary['approved_claims'] = $row['approved_claims'];
        $summary['rejected_claims'] = $row['rejected_claims'];
    }
    
    // Get vehicle count
    $sql = "SELECT COUNT(*) as total_vehicles FROM car WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $summary['total_vehicles'] = $row['total_vehicles'];
    }
    
    return $summary;
}

// Function to upload files securely
function upload_file($file, $destination_path, $allowed_types = ['image/jpeg', 'image/png', 'image/gif']) {
    // Check if the file was uploaded without errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload failed with error code: ' . $file['error']];
    }
    
    // Verify the file type
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'error' => 'File type not allowed'];
    }
    
    // Create destination directory if it doesn't exist
    $directory = dirname($destination_path);
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    
    // Generate a unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $destination = $directory . '/' . $filename;
    
    // Move the uploaded file to the destination
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'filename' => $filename, 'path' => $destination];
    } else {
        return ['success' => false, 'error' => 'Failed to move uploaded file'];
    }
}

// Format date for display
function format_date($date_string, $format = 'd M, Y') {
    $date = new DateTime($date_string);
    return $date->format($format);
}

// Format currency for display
function format_currency($amount) {
    return 'KES ' . number_format($amount, 2);
}
?>
