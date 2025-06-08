<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for policyholders
require_role('Policyholder');

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    $document_type = sanitize_input($_POST['document_type']);
    $policy_id = filter_var($_POST['policy_id'], FILTER_VALIDATE_INT);
    $description = sanitize_input($_POST['description']);
    
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/documents/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $filename = uniqid() . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['document']['tmp_name'], $file_path)) {
                $stmt = $conn->prepare("INSERT INTO documents (user_id, policy_id, document_type, filename, original_name, file_path, description, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("iisssss", $user_id, $policy_id, $document_type, $filename, $_FILES['document']['name'], $file_path, $description);
                
                if ($stmt->execute()) {
                    $success_message = "Document uploaded successfully.";
                    log_activity($user_id, "Document Uploaded", "User uploaded document: " . $_FILES['document']['name']);
                } else {
                    $error_message = "Failed to save document information.";
                    unlink($file_path);
                }
            } else {
                $error_message = "Failed to upload file.";
            }
        } else {
            $error_message = "Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX";
        }
    } else {
        $error_message = "Please select a file to upload.";
    }
}

// Handle document deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $doc_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("SELECT file_path FROM documents WHERE Id = ? AND user_id = ?");
    $stmt->bind_param("ii", $doc_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($doc = $result->fetch_assoc()) {
        $stmt = $conn->prepare("DELETE FROM documents WHERE Id = ? AND user_id = ?");
        $stmt->bind_param("ii", $doc_id, $user_id);
        
        if ($stmt->execute()) {
            if (file_exists($doc['file_path'])) {
                unlink($doc['file_path']);
            }
            $success_message = "Document deleted successfully.";
        } else {
            $error_message = "Failed to delete document.";
        }
    }
}

// Get user's documents
$stmt = $conn->prepare("SELECT d.*, p.policy_number 
                        FROM documents d 
                        LEFT JOIN policy p ON d.policy_id = p.Id 
                        WHERE d.user_id = ? 
                        ORDER BY d.uploaded_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$documents = $stmt->get_result();

// Get user's policies for dropdown
$policies = $conn->query("SELECT Id, policy_number FROM policy WHERE user_id = $user_id AND status = 'Active'");

// Get document counts by type
$doc_stats = [];
$result = $conn->query("SELECT document_type, COUNT(*) as count FROM documents WHERE user_id = $user_id GROUP BY document_type");
while ($row = $result->fetch_assoc()) {
    $doc_stats[$row['document_type']] = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents - Apex Assurance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        // ...existing user styles...
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --primary-color: #0056b3;
            --user-color: #007bff;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --dark-color: #333;
        }
        
        body {
            line-height: 1.6;
            background-color: #f8f9fa;
            color: var(--dark-color);
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        /* Upload Section */
        .upload-section {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            background-color: #f8f9fa;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        
        .upload-area:hover {
            border-color: var(--user-color);
            background-color: rgba(0, 123, 255, 0.02);
        }
        
        .upload-icon {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        /* Document Stats */
        .doc-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            text-align: center;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--user-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 1.2rem;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--user-color);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #777;
            font-size: 0.9rem;
        }
        
        /* Documents Grid */
        .documents-section {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 25px;
        }
        
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .document-card {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .document-card:hover {
            border-color: var(--user-color);
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.1);
        }
        
        .document-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .document-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.2rem;
            color: white;
        }
        
        .icon-pdf { background-color: #dc3545; }
        .icon-image { background-color: #28a745; }
        .icon-document { background-color: var(--user-color); }
        .icon-default { background-color: #6c757d; }
        
        .document-info h4 {
            margin-bottom: 5px;
            color: var(--dark-color);
            font-size: 1rem;
        }
        
        .document-info p {
            color: #777;
            font-size: 0.8rem;
            margin: 0;
        }
        
        .document-meta {
            margin-bottom: 15px;
        }
        
        .meta-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 0.85rem;
        }
        
        .meta-label {
            color: #777;
        }
        
        .meta-value {
            font-weight: 500;
        }
        
        .document-description {
            font-size: 0.9rem;
            color: #555;
            line-height: 1.4;
            margin-bottom: 15px;
        }
        
        .document-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn {
            display: inline-block;
            padding: 6px 12px;
            background-color: var(--user-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s;
            flex: 1;
            text-align: center;
        }
        
        .btn:hover {
            background-color: #0056b3;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #ddd;
            color: #777;
        }
        
        .btn-outline:hover {
            border-color: var(--user-color);
            color: var(--user-color);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: flex;
            align-items: center;
        }
        
        .alert i {
            margin-right: 10px;
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.2);
            color: var(--success-color);
        }
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.2);
            color: var(--danger-color);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .main-content {
                margin-left: 70px;
            }
            
            .form-row {
                flex-direction: column;
            }
            
            .documents-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <div class="content-title">
                    <h1>Document Management</h1>
                    <p>Upload and manage your insurance documents</p>
                </div>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Upload Section -->
            <div class="upload-section">
                <h2 style="margin-bottom: 20px;">Upload New Document</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload">
                    
                    <div class="upload-area">
                        <div class="upload-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <h3>Drop files here or click to browse</h3>
                        <p>Supported formats: PDF, JPG, PNG, DOC, DOCX (Max 10MB)</p>
                        <input type="file" name="document" id="document" required style="display: none;" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <button type="button" onclick="document.getElementById('document').click()" class="btn" style="margin-top: 15px;">
                            <i class="fas fa-folder-open"></i> Choose File
                        </button>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="document_type">Document Type</label>
                            <select id="document_type" name="document_type" required>
                                <option value="">Select document type...</option>
                                <option value="License">Driver's License</option>
                                <option value="Registration">Vehicle Registration</option>
                                <option value="Insurance">Insurance Certificate</option>
                                <option value="Claim">Claim Document</option>
                                <option value="Receipt">Payment Receipt</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="policy_id">Related Policy (Optional)</label>
                            <select id="policy_id" name="policy_id">
                                <option value="">Select policy...</option>
                                <?php while ($policy = $policies->fetch_assoc()): ?>
                                    <option value="<?php echo $policy['Id']; ?>">
                                        <?php echo htmlspecialchars($policy['policy_number']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" placeholder="Add a description for this document..." rows="3"></textarea>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-upload"></i> Upload Document
                    </button>
                </form>
            </div>

            <!-- Document Statistics -->
            <div class="doc-stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-value"><?php echo array_sum($doc_stats); ?></div>
                    <div class="stat-label">Total Documents</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <div class="stat-value"><?php echo $doc_stats['License'] ?? 0; ?></div>
                    <div class="stat-label">Licenses</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-car"></i>
                    </div>
                    <div class="stat-value"><?php echo $doc_stats['Registration'] ?? 0; ?></div>
                    <div class="stat-label">Registrations</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="stat-value"><?php echo $doc_stats['Insurance'] ?? 0; ?></div>
                    <div class="stat-label">Insurance Docs</div>
                </div>
            </div>

            <!-- Documents List -->
            <div class="documents-section">
                <h2 style="margin-bottom: 25px;">My Documents</h2>
                
                <?php if ($documents->num_rows > 0): ?>
                    <div class="documents-grid">
                        <?php while ($doc = $documents->fetch_assoc()): ?>
                            <?php
                            $file_extension = strtolower(pathinfo($doc['filename'], PATHINFO_EXTENSION));
                            $icon_class = 'icon-default';
                            $icon = 'fas fa-file';
                            
                            if ($file_extension === 'pdf') {
                                $icon_class = 'icon-pdf';
                                $icon = 'fas fa-file-pdf';
                            } elseif (in_array($file_extension, ['jpg', 'jpeg', 'png'])) {
                                $icon_class = 'icon-image';
                                $icon = 'fas fa-image';
                            } elseif (in_array($file_extension, ['doc', 'docx'])) {
                                $icon_class = 'icon-document';
                                $icon = 'fas fa-file-word';
                            }
                            ?>
                            <div class="document-card">
                                <div class="document-header">
                                    <div class="document-icon <?php echo $icon_class; ?>">
                                        <i class="<?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="document-info">
                                        <h4><?php echo htmlspecialchars($doc['original_name']); ?></h4>
                                        <p><?php echo htmlspecialchars($doc['document_type']); ?></p>
                                    </div>
                                </div>
                                
                                <div class="document-meta">
                                    <?php if ($doc['policy_number']): ?>
                                        <div class="meta-item">
                                            <span class="meta-label">Policy:</span>
                                            <span class="meta-value"><?php echo htmlspecialchars($doc['policy_number']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="meta-item">
                                        <span class="meta-label">Uploaded:</span>
                                        <span class="meta-value"><?php echo date('M d, Y', strtotime($doc['uploaded_at'])); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <span class="meta-label">Size:</span>
                                        <span class="meta-value"><?php echo file_exists($doc['file_path']) ? format_file_size(filesize($doc['file_path'])) : 'Unknown'; ?></span>
                                    </div>
                                </div>
                                
                                <?php if ($doc['description']): ?>
                                    <div class="document-description">
                                        <?php echo htmlspecialchars($doc['description']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="document-actions">
                                    <a href="download-document.php?id=<?php echo $doc['Id']; ?>" class="btn btn-outline">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                    <a href="?delete=<?php echo $doc['Id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this document?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <h3>No Documents Found</h3>
                        <p>Upload your first document to get started.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // File input change handler
        document.getElementById('document').addEventListener('change', function() {
            const fileName = this.files[0]?.name;
            if (fileName) {
                document.querySelector('.upload-area h3').textContent = fileName;
                document.querySelector('.upload-area p').textContent = 'Click to change file';
            }
        });
    </script>
</body>
</html>
