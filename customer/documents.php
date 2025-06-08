<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for policy holders
require_role('PolicyHolder');

$user_id = $_SESSION['user_id'];

// Get user's documents with related information
$documents = $conn->query("SELECT pd.*, p.policy_number, pt.name as policy_type_name,
                          CASE 
                              WHEN pd.document_type = 'policy_certificate' THEN 'Policy Certificate'
                              WHEN pd.document_type = 'claim_document' THEN 'Claim Document'
                              WHEN pd.document_type = 'payment_receipt' THEN 'Payment Receipt'
                              WHEN pd.document_type = 'id_document' THEN 'ID Document'
                              ELSE pd.document_type
                          END as display_type
                          FROM policy_documents pd
                          LEFT JOIN policy p ON pd.policy_id = p.Id
                          LEFT JOIN policy_type pt ON p.policy_type = pt.Id
                          WHERE pd.user_id = $user_id 
                          ORDER BY pd.uploaded_at DESC");

// Get document statistics
$doc_stats = [
    'total_documents' => 0,
    'policy_certificates' => 0,
    'claim_documents' => 0,
    'payment_receipts' => 0
];

$result = $conn->query("SELECT 
                       COUNT(*) as total_documents,
                       SUM(CASE WHEN document_type = 'policy_certificate' THEN 1 ELSE 0 END) as policy_certificates,
                       SUM(CASE WHEN document_type = 'claim_document' THEN 1 ELSE 0 END) as claim_documents,
                       SUM(CASE WHEN document_type = 'payment_receipt' THEN 1 ELSE 0 END) as payment_receipts
                       FROM policy_documents WHERE user_id = $user_id");

if ($row = $result->fetch_assoc()) {
    $doc_stats = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents - Customer Portal - Apex Assurance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        // ...existing customer portal styles...
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --customer-color: #007bff;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
        }
        
        body {
            line-height: 1.6;
            background-color: #f8f9fa;
            color: #333;
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
        
        .page-header {
            background: linear-gradient(135deg, var(--customer-color), #0056b3);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.2rem;
            color: white;
        }
        
        .stat-icon.total { background-color: var(--customer-color); }
        .stat-icon.certificates { background-color: var(--success-color); }
        .stat-icon.claims { background-color: var(--info-color); }
        .stat-icon.receipts { background-color: var(--warning-color); }
        
        .stat-details h3 {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-details p {
            color: #777;
            font-size: 0.8rem;
        }
        
        /* Documents Container */
        .documents-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        
        .container-header {
            background-color: var(--customer-color);
            color: white;
            padding: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding: 25px;
        }
        
        .document-card {
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 20px;
            transition: all 0.3s;
            position: relative;
        }
        
        .document-card:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .document-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .document-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.5rem;
            color: white;
        }
        
        .doc-pdf { background-color: #dc3545; }
        .doc-image { background-color: var(--success-color); }
        .doc-word { background-color: var(--customer-color); }
        .doc-default { background-color: #6c757d; }
        
        .document-info h3 {
            font-size: 1.1rem;
            margin-bottom: 5px;
            color: #333;
        }
        
        .document-type {
            background: rgba(0, 123, 255, 0.1);
            color: var(--customer-color);
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .document-meta {
            margin: 15px 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .meta-item i {
            width: 16px;
            margin-right: 8px;
            color: #999;
        }
        
        .document-actions {
            display: flex;
            gap: 10px;
            justify-content: space-between;
            margin-top: 15px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-size: 0.85rem;
        }
        
        .btn-customer {
            background-color: var(--customer-color);
            color: white;
        }
        
        .btn-customer:hover {
            background-color: #0056b3;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #ddd;
            color: #555;
        }
        
        .btn-outline:hover {
            background-color: #f8f9fa;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.75rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #777;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        /* File size formatting */
        .file-size {
            color: #999;
            font-size: 0.8rem;
        }
        
        /* Recent badge */
        .recent-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--success-color);
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .main-content {
                margin-left: 70px;
            }
        }
        
        @media (max-width: 768px) {
            .documents-grid {
                grid-template-columns: 1fr;
            }
            
            .container-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
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
            <!-- Page Header -->
            <div class="page-header">
                <h1>My Documents</h1>
                <p>Access and manage all your insurance documents</p>
            </div>

            <!-- Document Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon total">
                        <i class="fas fa-file"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($doc_stats['total_documents']); ?></h3>
                        <p>Total Documents</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon certificates">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($doc_stats['policy_certificates']); ?></h3>
                        <p>Policy Certificates</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon claims">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($doc_stats['claim_documents']); ?></h3>
                        <p>Claim Documents</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon receipts">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($doc_stats['payment_receipts']); ?></h3>
                        <p>Payment Receipts</p>
                    </div>
                </div>
            </div>

            <!-- Documents Container -->
            <div class="documents-container">
                <div class="container-header">
                    <h2>Your Documents (<?php echo $documents->num_rows; ?>)</h2>
                    <a href="policies.php" class="btn" style="background: rgba(255,255,255,0.2); color: white;">
                        <i class="fas fa-plus"></i> View Policies
                    </a>
                </div>

                <?php if ($documents->num_rows > 0): ?>
                    <div class="documents-grid">
                        <?php while ($document = $documents->fetch_assoc()): ?>
                            <?php
                            // Determine file type and icon
                            $file_extension = strtolower(pathinfo($document['file_path'], PATHINFO_EXTENSION));
                            $icon_class = 'doc-default';
                            $icon = 'file';
                            
                            switch ($file_extension) {
                                case 'pdf':
                                    $icon_class = 'doc-pdf';
                                    $icon = 'file-pdf';
                                    break;
                                case 'jpg':
                                case 'jpeg':
                                case 'png':
                                case 'gif':
                                    $icon_class = 'doc-image';
                                    $icon = 'file-image';
                                    break;
                                case 'doc':
                                case 'docx':
                                    $icon_class = 'doc-word';
                                    $icon = 'file-word';
                                    break;
                            }
                            
                            // Check if document is recent (within 7 days)
                            $is_recent = (strtotime($document['uploaded_at']) > strtotime('-7 days'));
                            ?>
                            
                            <div class="document-card">
                                <?php if ($is_recent): ?>
                                    <div class="recent-badge">New</div>
                                <?php endif; ?>
                                
                                <div class="document-header">
                                    <div class="document-icon <?php echo $icon_class; ?>">
                                        <i class="fas fa-<?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="document-info">
                                        <h3><?php echo htmlspecialchars($document['document_name']); ?></h3>
                                        <div class="document-type">
                                            <?php echo $document['display_type']; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="document-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-calendar"></i>
                                        Uploaded: <?php echo date('M j, Y', strtotime($document['uploaded_at'])); ?>
                                    </div>
                                    <?php if ($document['policy_number']): ?>
                                        <div class="meta-item">
                                            <i class="fas fa-shield-alt"></i>
                                            Policy: <?php echo htmlspecialchars($document['policy_number']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($document['policy_type_name']): ?>
                                        <div class="meta-item">
                                            <i class="fas fa-tag"></i>
                                            Type: <?php echo htmlspecialchars($document['policy_type_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (file_exists($document['file_path'])): ?>
                                        <div class="meta-item">
                                            <i class="fas fa-hdd"></i>
                                            Size: <span class="file-size"><?php echo format_file_size(filesize($document['file_path'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="document-actions">
                                    <a href="<?php echo htmlspecialchars($document['file_path']); ?>" target="_blank" class="btn btn-customer btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="download-document.php?id=<?php echo $document['Id']; ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <h3>No Documents Found</h3>
                        <p>Your insurance documents will appear here once they become available.</p>
                        <a href="policies.php" class="btn btn-customer">
                            <i class="fas fa-shield-alt"></i> View Your Policies
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Smooth animations on load
        document.addEventListener('DOMContentLoaded', function() {
            const documentCards = document.querySelectorAll('.document-card');
            documentCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
        
        // Add click tracking for downloads
        document.querySelectorAll('a[href*="download-document.php"]').forEach(link => {
            link.addEventListener('click', function() {
                // You can add analytics tracking here
                console.log('Document download:', this.href);
            });
        });
    </script>
</body>
</html>
