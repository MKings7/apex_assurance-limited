<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for policy holders
require_role('PolicyHolder');

$user_id = $_SESSION['user_id'];
$claim_id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);

// Get claim details
$stmt = $conn->prepare("SELECT ar.*, 
                       c.make, c.model, c.number_plate, c.year, c.color,
                       p.policy_number, p.start_date, p.end_date, p.premium_amount, p.deductible,
                       pt.name as policy_type_name,
                       CASE 
                           WHEN ar.status = 'Pending' THEN 'Submitted'
                           WHEN ar.status = 'UnderReview' THEN 'Under Review'
                           WHEN ar.status = 'Assigned' THEN 'Processing'
                           WHEN ar.status = 'Approved' THEN 'Approved'
                           WHEN ar.status = 'Rejected' THEN 'Rejected'
                           ELSE ar.status
                       END as display_status
                       FROM accident_report ar 
                       JOIN car c ON ar.car_id = c.Id 
                       JOIN policy p ON ar.policy_id = p.Id
                       JOIN policy_type pt ON p.policy_type = pt.Id
                       WHERE ar.Id = ? AND ar.user_id = ?");
$stmt->bind_param("ii", $claim_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Claim not found.";
    header("Location: claims.php");
    exit;
}

$claim = $result->fetch_assoc();

// Get claim photos
$photos = $conn->query("SELECT * FROM accident_photos WHERE claim_id = $claim_id");

// Get claim documents
$documents = $conn->query("SELECT * FROM claim_documents WHERE claim_id = $claim_id");

// Get claim timeline/updates
$timeline = $conn->query("SELECT cn.*, u.first_name, u.last_name, u.role,
                         CASE 
                             WHEN u.role = 'Admin' THEN 'Administrator'
                             WHEN u.role = 'Adjuster' THEN 'Insurance Adjuster'
                             WHEN u.role = 'Employee' THEN 'Employee'
                             WHEN u.role = 'RepairCenter' THEN 'Repair Center'
                             ELSE 'Customer Service'
                         END as role_display
                         FROM claim_notes cn 
                         JOIN user u ON cn.user_id = u.Id 
                         WHERE cn.claim_id = $claim_id 
                         ORDER BY cn.created_at DESC");

// Get repair estimates if any
$estimates = $conn->query("SELECT re.*, u.first_name, u.last_name 
                          FROM repair_estimates re 
                          JOIN user u ON re.repair_center_id = u.Id 
                          WHERE re.claim_id = $claim_id 
                          ORDER BY re.created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claim Details - Customer Portal - Apex Assurance</title>
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
        
        .claim-header {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .claim-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background-color: var(--customer-color);
        }
        
        .claim-status-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .status-submitted {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }
        
        .status-underreview {
            background-color: rgba(23, 162, 184, 0.1);
            color: var(--info-color);
        }
        
        .status-processing {
            background-color: rgba(0, 123, 255, 0.1);
            color: var(--customer-color);
        }
        
        .status-approved {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .status-rejected {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .claim-title h1 {
            font-size: 1.8rem;
            margin-bottom: 8px;
            color: var(--customer-color);
        }
        
        .claim-date {
            color: #777;
            display: flex;
            align-items: center;
            font-size: 0.95rem;
        }
        
        .claim-date i {
            margin-right: 5px;
        }
        
        /* Tabs */
        .tabs-container {
            margin-bottom: 30px;
        }
        
        .tabs-nav {
            display: flex;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .tab {
            flex: 1;
            text-align: center;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            color: #777;
            border-bottom: 2px solid transparent;
        }
        
        .tab.active {
            color: var(--customer-color);
            border-bottom-color: var(--customer-color);
        }
        
        .tab-content {
            background-color: white;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 25px;
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .info-section {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 25px;
        }
        
        .info-section h2 {
            margin-bottom: 15px;
            font-size: 1.2rem;
            color: var(--customer-color);
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .info-item {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-weight: 500;
            color: #777;
            font-size: 0.9rem;
        }
        
        .info-value {
            font-size: 1rem;
            color: #333;
            margin-top: 3px;
        }
        
        /* Photos Grid */
        .photos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .photo-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .photo-item:hover {
            transform: scale(1.05);
        }
        
        .photo-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        
        .photo-caption {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.7));
            color: white;
            padding: 10px;
            font-size: 0.85rem;
        }
        
        /* Documents List */
        .documents-list {
            list-style: none;
            padding: 0;
        }
        
        .document-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        
        .document-item:hover {
            background-color: #f8f9fa;
        }
        
        .document-icon {
            width: 40px;
            height: 40px;
            background-color: var(--customer-color);
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .document-info {
            flex: 1;
        }
        
        .document-name {
            font-weight: 500;
            color: #333;
            margin-bottom: 3px;
        }
        
        .document-meta {
            font-size: 0.85rem;
            color: #777;
        }
        
        .document-actions {
            display: flex;
            gap: 10px;
        }
        
        /* Timeline */
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: #ddd;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 25px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -25px;
            top: 25px;
            width: 12px;
            height: 12px;
            background-color: var(--customer-color);
            border-radius: 50%;
            border: 3px solid white;
        }
        
        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .timeline-user {
            font-weight: 500;
            color: var(--customer-color);
        }
        
        .timeline-role {
            font-size: 0.8rem;
            color: #777;
            background-color: white;
            padding: 2px 8px;
            border-radius: 10px;
        }
        
        .timeline-date {
            font-size: 0.85rem;
            color: #999;
        }
        
        .timeline-content {
            line-height: 1.5;
            color: #555;
        }
        
        /* Estimates */
        .estimate-item {
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .estimate-header {
            background-color: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .estimate-center {
            font-weight: 500;
        }
        
        .estimate-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .estimate-body {
            padding: 15px;
        }
        
        .estimate-breakdown {
            margin-bottom: 15px;
        }
        
        .breakdown-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .breakdown-total {
            border-top: 1px solid #ddd;
            padding-top: 10px;
            font-weight: bold;
            color: var(--customer-color);
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
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #ddd;
            color: #555;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.75rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #777;
        }
        
        .empty-state i {
            font-size: 2.5rem;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        /* Modal for image viewing */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
        }
        
        .modal-content-img {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 90%;
            margin-top: 5%;
        }
        
        .close-modal {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .main-content {
                margin-left: 70px;
            }
        }
        
        @media (max-width: 768px) {
            .tabs-nav {
                flex-direction: column;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .photos-grid {
                grid-template-columns: repeat(2, 1fr);
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
            <!-- Back Button -->
            <div style="margin-bottom: 20px;">
                <a href="claims.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Claims
                </a>
            </div>

            <!-- Claim Header -->
            <div class="claim-header">
                <div class="claim-status-badge status-<?php echo strtolower(str_replace(' ', '', $claim['display_status'])); ?>">
                    <?php echo $claim['display_status']; ?>
                </div>
                <div class="claim-title">
                    <h1><?php echo htmlspecialchars($claim['accident_report_number']); ?></h1>
                    <div class="claim-date">
                        <i class="fas fa-calendar"></i>
                        Accident Date: <?php echo date('F j, Y', strtotime($claim['accident_date'])); ?>
                    </div>
                </div>
            </div>

            <!-- Main Info Grid -->
            <div class="info-grid">
                <!-- Accident Details -->
                <div class="info-section">
                    <h2><i class="fas fa-exclamation-triangle"></i> Accident Details</h2>
                    <div class="info-item">
                        <div class="info-label">Location</div>
                        <div class="info-value"><?php echo htmlspecialchars($claim['accident_location']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Date & Time</div>
                        <div class="info-value"><?php echo date('F j, Y \a\t g:i A', strtotime($claim['accident_date'] . ' ' . ($claim['accident_time'] ?? '00:00:00'))); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Weather Conditions</div>
                        <div class="info-value"><?php echo htmlspecialchars($claim['weather_conditions'] ?? 'Not specified'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Description</div>
                        <div class="info-value"><?php echo nl2br(htmlspecialchars($claim['description'])); ?></div>
                    </div>
                </div>

                <!-- Vehicle Information -->
                <div class="info-section">
                    <h2><i class="fas fa-car"></i> Vehicle Information</h2>
                    <div class="info-item">
                        <div class="info-label">Vehicle</div>
                        <div class="info-value"><?php echo htmlspecialchars($claim['year'] . ' ' . $claim['make'] . ' ' . $claim['model']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">License Plate</div>
                        <div class="info-value"><?php echo htmlspecialchars($claim['number_plate']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Color</div>
                        <div class="info-value"><?php echo htmlspecialchars($claim['color']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Damage Description</div>
                        <div class="info-value"><?php echo nl2br(htmlspecialchars($claim['damage_description'] ?? 'No damage description provided')); ?></div>
                    </div>
                </div>

                <!-- Policy Information -->
                <div class="info-section">
                    <h2><i class="fas fa-shield-alt"></i> Policy Information</h2>
                    <div class="info-item">
                        <div class="info-label">Policy Number</div>
                        <div class="info-value"><?php echo htmlspecialchars($claim['policy_number']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Policy Type</div>
                        <div class="info-value"><?php echo htmlspecialchars($claim['policy_type_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Coverage Period</div>
                        <div class="info-value">
                            <?php echo date('M j, Y', strtotime($claim['start_date'])); ?> - 
                            <?php echo date('M j, Y', strtotime($claim['end_date'])); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Deductible</div>
                        <div class="info-value"><?php echo format_currency($claim['deductible']); ?></div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="tabs-container">
                <div class="tabs-nav">
                    <div class="tab active" onclick="showTab('photos')">
                        <i class="fas fa-camera"></i> Photos
                    </div>
                    <div class="tab" onclick="showTab('documents')">
                        <i class="fas fa-file"></i> Documents
                    </div>
                    <div class="tab" onclick="showTab('estimates')">
                        <i class="fas fa-calculator"></i> Estimates
                    </div>
                    <div class="tab" onclick="showTab('timeline')">
                        <i class="fas fa-history"></i> Timeline
                    </div>
                </div>

                <!-- Photos Tab -->
                <div id="photos" class="tab-content active">
                    <?php if ($photos->num_rows > 0): ?>
                        <div class="photos-grid">
                            <?php while ($photo = $photos->fetch_assoc()): ?>
                                <div class="photo-item" onclick="openImageModal('<?php echo htmlspecialchars($photo['file_path']); ?>')">
                                    <img src="<?php echo htmlspecialchars($photo['file_path']); ?>" alt="Accident Photo">
                                    <div class="photo-caption">
                                        <?php echo htmlspecialchars($photo['description'] ?? 'Accident Photo'); ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-camera"></i>
                            <h4>No Photos</h4>
                            <p>No photos have been uploaded for this claim.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Documents Tab -->
                <div id="documents" class="tab-content">
                    <?php if ($documents->num_rows > 0): ?>
                        <ul class="documents-list">
                            <?php while ($document = $documents->fetch_assoc()): ?>
                                <li class="document-item">
                                    <div class="document-icon">
                                        <i class="fas fa-file-pdf"></i>
                                    </div>
                                    <div class="document-info">
                                        <div class="document-name"><?php echo htmlspecialchars($document['document_name']); ?></div>
                                        <div class="document-meta">
                                            Uploaded: <?php echo date('M j, Y', strtotime($document['uploaded_at'])); ?>
                                        </div>
                                    </div>
                                    <div class="document-actions">
                                        <a href="<?php echo htmlspecialchars($document['file_path']); ?>" target="_blank" class="btn btn-outline btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="<?php echo htmlspecialchars($document['file_path']); ?>" download class="btn btn-customer btn-sm">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file"></i>
                            <h4>No Documents</h4>
                            <p>No documents have been uploaded for this claim.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Estimates Tab -->
                <div id="estimates" class="tab-content">
                    <?php if ($estimates->num_rows > 0): ?>
                        <?php while ($estimate = $estimates->fetch_assoc()): ?>
                            <div class="estimate-item">
                                <div class="estimate-header">
                                    <div class="estimate-center">
                                        <?php echo htmlspecialchars($estimate['first_name'] . ' ' . $estimate['last_name']); ?>
                                    </div>
                                    <div class="estimate-status status-<?php echo strtolower($estimate['status']); ?>">
                                        <?php echo $estimate['status']; ?>
                                    </div>
                                </div>
                                <div class="estimate-body">
                                    <div class="estimate-breakdown">
                                        <div class="breakdown-item">
                                            <span>Parts Cost:</span>
                                            <span><?php echo format_currency($estimate['parts_cost'] ?? 0); ?></span>
                                        </div>
                                        <div class="breakdown-item">
                                            <span>Labor Cost:</span>
                                            <span><?php echo format_currency($estimate['labor_cost'] ?? 0); ?></span>
                                        </div>
                                        <div class="breakdown-item breakdown-total">
                                            <span>Total Cost:</span>
                                            <span><?php echo format_currency($estimate['total_cost']); ?></span>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Estimated Completion</div>
                                        <div class="info-value"><?php echo $estimate['estimated_days']; ?> days</div>
                                    </div>
                                    <?php if ($estimate['notes']): ?>
                                        <div class="info-item">
                                            <div class="info-label">Notes</div>
                                            <div class="info-value"><?php echo nl2br(htmlspecialchars($estimate['notes'])); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calculator"></i>
                            <h4>No Estimates</h4>
                            <p>No repair estimates have been provided yet.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Timeline Tab -->
                <div id="timeline" class="tab-content">
                    <?php if ($timeline->num_rows > 0): ?>
                        <div class="timeline">
                            <?php while ($note = $timeline->fetch_assoc()): ?>
                                <div class="timeline-item">
                                    <div class="timeline-header">
                                        <div>
                                            <div class="timeline-user">
                                                <?php echo htmlspecialchars($note['first_name'] . ' ' . $note['last_name']); ?>
                                            </div>
                                            <div class="timeline-role"><?php echo $note['role_display']; ?></div>
                                        </div>
                                        <div class="timeline-date">
                                            <?php echo date('M j, Y \a\t g:i A', strtotime($note['created_at'])); ?>
                                        </div>
                                    </div>
                                    <div class="timeline-content">
                                        <?php echo nl2br(htmlspecialchars($note['note'])); ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <h4>No Updates</h4>
                            <p>No updates have been posted for this claim yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="modal">
        <span class="close-modal" onclick="closeImageModal()">&times;</span>
        <img class="modal-content-img" id="modalImage">
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.closest('.tab').classList.add('active');
        }
        
        function openImageModal(imageSrc) {
            document.getElementById('imageModal').style.display = 'block';
            document.getElementById('modalImage').src = imageSrc;
        }
        
        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
        }
        
        // Close modal when clicking outside the image
        window.onclick = function(event) {
            const modal = document.getElementById('imageModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
