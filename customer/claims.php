<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for policy holders
require_role('PolicyHolder');

$user_id = $_SESSION['user_id'];

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$policy_filter = isset($_GET['policy']) ? $_GET['policy'] : '';

// Build query for claims
$sql = "SELECT ar.*, 
        c.make, c.model, c.number_plate, c.year,
        p.policy_number, pt.name as policy_type_name,
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
        WHERE ar.user_id = ?";

$params = [$user_id];
$types = "i";

if ($status_filter) {
    $sql .= " AND ar.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($policy_filter) {
    $sql .= " AND ar.policy_id = ?";
    $params[] = $policy_filter;
    $types .= "i";
}

$sql .= " ORDER BY ar.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$claims = $stmt->get_result();

// Get user's policies for filter dropdown
$user_policies = $conn->query("SELECT p.Id, p.policy_number, pt.name as policy_type_name 
                              FROM policy p 
                              JOIN policy_type pt ON p.policy_type = pt.Id 
                              WHERE p.user_id = $user_id 
                              ORDER BY p.created_at DESC");

// Get claim statistics
$claim_stats = [];
$result = $conn->query("SELECT status, COUNT(*) as count FROM accident_report WHERE user_id = $user_id GROUP BY status");
while ($row = $result->fetch_assoc()) {
    $claim_stats[$row['status']] = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Claims - Customer Portal - Apex Assurance</title>
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
        
        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .filter-form {
            display: flex;
            gap: 20px;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .filter-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-badge {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
            border-left: 4px solid var(--customer-color);
        }
        
        .stat-badge.pending { border-left-color: var(--warning-color); }
        .stat-badge.underreview { border-left-color: var(--info-color); }
        .stat-badge.approved { border-left-color: var(--success-color); }
        .stat-badge.rejected { border-left-color: var(--danger-color); }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #777;
            font-size: 0.85rem;
        }
        
        /* Claims Container */
        .claims-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .container-header {
            background-color: var(--customer-color);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .claims-list {
            padding: 0;
        }
        
        .claim-item {
            padding: 25px;
            border-bottom: 1px solid #eee;
            transition: all 0.3s;
        }
        
        .claim-item:last-child {
            border-bottom: none;
        }
        
        .claim-item:hover {
            background-color: rgba(0, 123, 255, 0.02);
        }
        
        .claim-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .claim-number {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--customer-color);
        }
        
        .claim-status {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
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
        
        .claim-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .detail-group {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.8rem;
            color: #777;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-weight: 500;
            color: #333;
        }
        
        .claim-description {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        .claim-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
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
        
        /* Responsive */
        @media (max-width: 991px) {
            .main-content {
                margin-left: 70px;
            }
        }
        
        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column;
                gap: 15px;
            }
            
            .claim-details {
                grid-template-columns: 1fr;
            }
            
            .claim-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .claim-actions {
                justify-content: flex-start;
                flex-wrap: wrap;
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
                <h1>My Insurance Claims</h1>
                <p>Track and manage your insurance claims</p>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form class="filter-form" method="GET">
                    <div class="filter-group">
                        <label for="status">Filter by Status</label>
                        <select id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Submitted</option>
                            <option value="UnderReview" <?php echo $status_filter === 'UnderReview' ? 'selected' : ''; ?>>Under Review</option>
                            <option value="Assigned" <?php echo $status_filter === 'Assigned' ? 'selected' : ''; ?>>Processing</option>
                            <option value="Approved" <?php echo $status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="policy">Filter by Policy</label>
                        <select id="policy" name="policy">
                            <option value="">All Policies</option>
                            <?php while ($policy = $user_policies->fetch_assoc()): ?>
                                <option value="<?php echo $policy['Id']; ?>" <?php echo $policy_filter == $policy['Id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($policy['policy_number'] . ' - ' . $policy['policy_type_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn btn-customer">Apply Filters</button>
                    </div>
                </form>
            </div>

            <!-- Claim Statistics -->
            <div class="stats-grid">
                <div class="stat-badge pending">
                    <div class="stat-number"><?php echo $claim_stats['Pending'] ?? 0; ?></div>
                    <div class="stat-label">Submitted</div>
                </div>
                <div class="stat-badge underreview">
                    <div class="stat-number"><?php echo $claim_stats['UnderReview'] ?? 0; ?></div>
                    <div class="stat-label">Under Review</div>
                </div>
                <div class="stat-badge approved">
                    <div class="stat-number"><?php echo $claim_stats['Approved'] ?? 0; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-badge rejected">
                    <div class="stat-number"><?php echo $claim_stats['Rejected'] ?? 0; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>

            <!-- Claims Container -->
            <div class="claims-container">
                <div class="container-header">
                    <h2>Your Claims (<?php echo $claims->num_rows; ?>)</h2>
                    <a href="../report-accident.php" class="btn" style="background: rgba(255,255,255,0.2); color: white;">
                        <i class="fas fa-plus"></i> File New Claim
                    </a>
                </div>

                <?php if ($claims->num_rows > 0): ?>
                    <div class="claims-list">
                        <?php while ($claim = $claims->fetch_assoc()): ?>
                            <div class="claim-item">
                                <div class="claim-header">
                                    <div class="claim-number"><?php echo htmlspecialchars($claim['accident_report_number']); ?></div>
                                    <div class="claim-status status-<?php echo strtolower(str_replace(' ', '', $claim['display_status'])); ?>">
                                        <?php echo $claim['display_status']; ?>
                                    </div>
                                </div>

                                <div class="claim-details">
                                    <div class="detail-group">
                                        <div class="detail-label">Vehicle</div>
                                        <div class="detail-value">
                                            <?php echo htmlspecialchars($claim['year'] . ' ' . $claim['make'] . ' ' . $claim['model']); ?>
                                        </div>
                                    </div>
                                    <div class="detail-group">
                                        <div class="detail-label">License Plate</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($claim['number_plate']); ?></div>
                                    </div>
                                    <div class="detail-group">
                                        <div class="detail-label">Policy</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($claim['policy_number']); ?></div>
                                    </div>
                                    <div class="detail-group">
                                        <div class="detail-label">Accident Date</div>
                                        <div class="detail-value"><?php echo date('M d, Y', strtotime($claim['accident_date'])); ?></div>
                                    </div>
                                    <div class="detail-group">
                                        <div class="detail-label">Location</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($claim['accident_location']); ?></div>
                                    </div>
                                    <div class="detail-group">
                                        <div class="detail-label">Filed Date</div>
                                        <div class="detail-value"><?php echo date('M d, Y', strtotime($claim['created_at'])); ?></div>
                                    </div>
                                </div>

                                <div class="claim-description">
                                    <strong>Description:</strong> <?php echo nl2br(htmlspecialchars($claim['description'])); ?>
                                </div>

                                <div class="claim-actions">
                                    <a href="view-claim.php?id=<?php echo $claim['Id']; ?>" class="btn btn-customer btn-sm">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                    <?php if (in_array($claim['status'], ['Pending', 'UnderReview'])): ?>
                                        <a href="edit-claim.php?id=<?php echo $claim['Id']; ?>" class="btn btn-outline btn-sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    <?php endif; ?>
                                    <a href="claim-documents.php?id=<?php echo $claim['Id']; ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-file"></i> Documents
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt"></i>
                        <h3>No Claims Found</h3>
                        <p>You haven't filed any insurance claims yet.</p>
                        <a href="../report-accident.php" class="btn btn-customer">
                            <i class="fas fa-plus"></i> File Your First Claim
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Auto-refresh every 2 minutes to check for status updates
        setInterval(function() {
            location.reload();
        }, 120000);

        // Smooth animations on load
        document.addEventListener('DOMContentLoaded', function() {
            const claimItems = document.querySelectorAll('.claim-item');
            claimItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    item.style.transition = 'all 0.5s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>
