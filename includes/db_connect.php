<?php
require_once 'config.php';

// Create database connection
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to sanitize user inputs
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    $data = $conn->real_escape_string($data);
    return $data;
}

// Function to log system activities
function log_activity($user_id, $action, $details = null, $ip = null) {
    global $conn;
    
    if ($ip === null) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    $sql = "INSERT INTO system_log (user_id, action, details, ip_address) 
            VALUES (?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $user_id, $action, $details, $ip);
    $stmt->execute();
    $stmt->close();
}

// Function to create a notification
function create_notification($user_id, $related_id, $related_type, $title, $message) {
    global $conn;
    
    $sql = "INSERT INTO notification (user_id, related_id, related_type, title, message) 
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisss", $user_id, $related_id, $related_type, $title, $message);
    $stmt->execute();
    $stmt->close();
}
?>
