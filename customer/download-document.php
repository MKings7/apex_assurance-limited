<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for policy holders
require_role('PolicyHolder');

$user_id = $_SESSION['user_id'];
$document_id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);

if (!$document_id) {
    $_SESSION['error'] = "Invalid document ID.";
    header("Location: documents.php");
    exit;
}

// Get document details and verify ownership
$stmt = $conn->prepare("SELECT * FROM policy_documents WHERE Id = ? AND user_id = ?");
$stmt->bind_param("ii", $document_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Document not found or access denied.";
    header("Location: documents.php");
    exit;
}

$document = $result->fetch_assoc();
$file_path = $document['file_path'];

// Check if file exists
if (!file_exists($file_path)) {
    $_SESSION['error'] = "Document file not found on server.";
    header("Location: documents.php");
    exit;
}

// Log the download activity
log_activity($user_id, "Document Downloaded", "User downloaded document: " . $document['document_name']);

// Get file info
$file_name = $document['document_name'];
$file_size = filesize($file_path);
$file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

// Set appropriate content type
$content_types = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
];

$content_type = $content_types[$file_extension] ?? 'application/octet-stream';

// Set headers for download
header('Content-Type: ' . $content_type);
header('Content-Disposition: attachment; filename="' . $file_name . '"');
header('Content-Length: ' . $file_size);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Clear output buffer
if (ob_get_level()) {
    ob_end_clean();
}

// Output file
readfile($file_path);
exit;
?>
