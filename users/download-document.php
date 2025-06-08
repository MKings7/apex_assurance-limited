<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for policyholders
require_role('Policyholder');

$user_id = $_SESSION['user_id'];
$doc_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

if (!$doc_id) {
    $_SESSION['error'] = "Invalid document ID.";
    header("Location: documents.php");
    exit;
}

// Get document details
$stmt = $conn->prepare("SELECT * FROM documents WHERE Id = ? AND user_id = ?");
$stmt->bind_param("ii", $doc_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Document not found.";
    header("Location: documents.php");
    exit;
}

$document = $result->fetch_assoc();

// Check if file exists
if (!file_exists($document['file_path'])) {
    $_SESSION['error'] = "File not found on server.";
    header("Location: documents.php");
    exit;
}

// Log download activity
log_activity($user_id, "Document Downloaded", "User downloaded document: " . $document['original_name']);

// Set headers for file download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $document['original_name'] . '"');
header('Content-Length: ' . filesize($document['file_path']));
header('Cache-Control: must-revalidate');
header('Pragma: public');

// Output file
readfile($document['file_path']);
exit;
?>
