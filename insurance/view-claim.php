<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for insurance adjusters
require_role('Adjuster');

$adjuster_id = $_SESSION['user_id'];
$claim_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

if (!$claim_id) {
    $_SESSION['error'] = "Invalid claim ID.";
    header("Location: assigned-claims.php");
    exit;
}

// Get claim details with all related information
$stmt = $conn->prepare("SELECT ar.*, 
                        u.first_name, u.last_name, u.email, u.phone_number, u.address,
                        c.make, c.model, c.number_plate, c.year, c.color, c.engine_number, c.chassis_number, c.value,
                        p.policy_number, p.premium_amount, p.coverage_amount, p.start_date, p.end_date,
                        pt.name as policy_type_name, pt.description as policy_type_description,
                        rc.first_name as repair_center_name, rc.phone_number as repair_center_phone
                        FROM accident_report ar 
                        JOIN user u ON ar.user_id = u.Id 
                        JOIN car c ON ar.car_id = c.Id 
                        JOIN policy p ON ar.policy_id = p.Id
                        JOIN policy_type pt ON p.policy_type = pt.Id
                        LEFT JOIN user rc ON ar.assigned_repair_center = rc.Id
                        WHERE ar.Id = ? AND ar.assigned_adjuster = ?");
$stmt->bind_param("ii", $claim_id, $adjuster_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Claim not found or not assigned to you.";
    header("Location: assigned-claims.php");
    exit;
}

$claim = $result->fetch_assoc();

// Get user initials for avatar
$user_initials = '';
$names = explode(' ', $claim['first_name'] . ' ' . $claim['last_name']);
foreach ($names as $name) {
    $user_initials .= strtoupper(substr($name, 0, 1));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claim Assessment - <?php echo htmlspecialchars($claim['accident_report_number']); ?> - Apex Assurance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        // ...existing insurance styles...
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --primary-color: #0056b3;
            --secondary-color: #00b359;
            --dark-color: #333;
            --light-color: #f4f4f4;
            --sidebar-color: #2c3e50;
            --danger-color: #dc3545;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --adjuster-color: #6610f2;
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
        
        .sidebar {
            width: 250px;
            background-color: var(--sidebar-color);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 100;
        }
        
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }

        /* Claim Overview */
        .claim-overview {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .claim-header {
            background: linear-gradient(to right, var(--adjuster-color), var(--primary-color));
            color: white;
            padding: 30px;
            position: relative;
        }
        
        .claim-info {
            display: flex;
            align-items: center;
        }
        
        .claim-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: bold;
            margin-right: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }
        
        .claim-details h1 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }
        
        .claim-details p {
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .claim-status {
            position: absolute;
            top: 30px;
            right: 30px;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 500;
            background-color: white;
        }
        
        .claim-status.assigned { color: var(--adjuster-color); }
        .claim-status.underreview { color: var(--warning-color); }
        .claim-status.approved { color: var(--success-color); }
        .claim-status.rejected { color: var(--danger-color); }
        
        .claim-body {
            padding: 30px;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .detail-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
        }
        
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--adjuster-color);
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 8px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .detail-label {
            color: #6c757d;
            font-weight: 500;
        }
        
        .detail-value {
            font-weight: 600;
            text-align: right;
        }
        
        /* Assessment Form */
        .assessment-form {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-top: 30px;
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
        
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background-color: #0045a2;
            transform: translateY(-2px);
        }
        
        .btn-adjuster {
            background-color: var(--adjuster-color);
        }
        
        .btn-adjuster:hover {
            background-color: #520dc2;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-outline:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            justify-content: center;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .main-content {
                margin-left: 70px;
            }
            
            .details-grid {
                grid-template-columns: 1fr;
            }
            
            .claim-header {
                padding: 20px;
            }
            
            .claim-info {
                flex-direction: column;
                text-align: center;
            }
            
            .claim-avatar {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .claim-status {
                position: static;
                margin-top: 15px;
                display: inline-block;
            }
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
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
                    <h1>Claim Assessment</h1>
                    <p>Review and assess insurance claim details</p>
                </div>
                <div class="header-actions">
                    <a href="assigned-claims.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Claims
                    </a>
                </div>
            </div>

            <!-- Claim Overview -->
            <div class="claim-overview">
                <div class="claim-header">
                    <div class="claim-info">
                        <div class="claim-avatar">
                            <?php echo $user_initials; ?>
                        </div>
                        <div class="claim-details">
                            <h1><?php echo htmlspecialchars($claim['accident_report_number']); ?></h1>
                            <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($claim['first_name'] . ' ' . $claim['last_name']); ?></p>
                            <p><i class="fas fa-car"></i> <?php echo htmlspecialchars($claim['year'] . ' ' . $claim['make'] . ' ' . $claim['model']); ?></p>
                            <p><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($claim['accident_date'])); ?></p>
                        </div>
                    </div>
                    <div class="claim-status <?php echo strtolower(str_replace(' ', '', $claim['status'])); ?>">
                        <?php echo $claim['status']; ?>
                    </div>
                </div>
                
                <div class="claim-body">
                    <div class="details-grid">
                        <div class="detail-section">
                            <h3 class="section-title">
                                <i class="fas fa-user"></i>
                                Claimant Information
                            </h3>
                            <div class="detail-row">
                                <span class="detail-label">Full Name</span>
                                <span class="detail-value"><?php echo htmlspecialchars($claim['first_name'] . ' ' . $claim['last_name']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Email</span>
                                <span class="detail-value"><?php echo htmlspecialchars($claim['email']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Phone</span>
                                <span class="detail-value"><?php echo htmlspecialchars($claim['phone_number']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Address</span>
                                <span class="detail-value"><?php echo htmlspecialchars($claim['address']); ?></span>
                            </div>
                        </div>

                        <div class="detail-section">
                            <h3 class="section-title">
                                <i class="fas fa-car"></i>
                                Vehicle Information
                            </h3>
                            <div class="detail-row">
                                <span class="detail-label">Vehicle</span>
                                <span class="detail-value"><?php echo htmlspecialchars($claim['year'] . ' ' . $claim['make'] . ' ' . $claim['model']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">License Plate</span>
                                <span class="detail-value"><?php echo htmlspecialchars($claim['number_plate']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Color</span>
                                <span class="detail-value"><?php echo htmlspecialchars($claim['color']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Vehicle Value</span>
                                <span class="detail-value"><?php echo format_currency($claim['value']); ?></span>
                            </div>
                        </div>

                        <div class="detail-section">
                            <h3 class="section-title">
                                <i class="fas fa-shield-alt"></i>
                                Policy Information
                            </h3>
                            <div class="detail-row">
                                <span class="detail-label">Policy Number</span>
                                <span class="detail-value"><?php echo htmlspecialchars($claim['policy_number']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Policy Type</span>
                                <span class="detail-value"><?php echo htmlspecialchars($claim['policy_type_name']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Coverage Amount</span>
                                <span class="detail-value"><?php echo format_currency($claim['coverage_amount']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Premium</span>
                                <span class="detail-value"><?php echo format_currency($claim['premium_amount']); ?></span>
                            </div>
                        </div>

                        <div class="detail-section">
                            <h3 class="section-title">
                                <i class="fas fa-exclamation-triangle"></i>
                                Accident Details
                            </h3>
                            <div class="detail-row">
                                <span class="detail-label">Date</span>
                                <span class="detail-value"><?php echo date('M d, Y', strtotime($claim['accident_date'])); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Time</span>
                                <span class="detail-value"><?php echo date('H:i', strtotime($claim['accident_time'])); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Location</span>
                                <span class="detail-value"><?php echo htmlspecialchars($claim['accident_location']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Priority</span>
                                <span class="detail-value">
                                    <span class="priority-badge <?php echo strtolower($claim['priority']); ?>">
                                        <?php echo $claim['priority']; ?>
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Accident Description -->
                    <div class="detail-section" style="margin-bottom: 30px;">
                        <h3 class="section-title">
                            <i class="fas fa-file-alt"></i>
                            Accident Description
                        </h3>
                        <p style="line-height: 1.6; color: #555;">
                            <?php echo nl2br(htmlspecialchars($claim['accident_description'])); ?>
                        </p>
                    </div>

                    <!-- Assessment Form -->
                    <?php if (in_array($claim['status'], ['Assigned', 'UnderReview'])): ?>
                        <div class="assessment-form">
                            <h3 class="section-title">
                                <i class="fas fa-gavel"></i>
                                Assessment Decision
                            </h3>
                            <form method="POST" action="assigned-claims.php">
                                <input type="hidden" name="claim_id" value="<?php echo $claim['Id']; ?>">
                                <input type="hidden" name="action" value="update_status">
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="new_status">Assessment Decision</label>
                                        <select id="new_status" name="new_status" required>
                                            <option value="UnderReview" <?php echo $claim['status'] === 'UnderReview' ? 'selected' : ''; ?>>Under Review</option>
                                            <option value="Approved">Approve Claim</option>
                                            <option value="Rejected">Reject Claim</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="approved_amount">Approved Amount</label>
                                        <input type="number" id="approved_amount" name="approved_amount" step="0.01" min="0" 
                                               placeholder="0.00" value="<?php echo $claim['approved_amount']; ?>">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="adjuster_notes">Assessment Notes</label>
                                    <textarea id="adjuster_notes" name="adjuster_notes" placeholder="Provide detailed assessment notes, including reasons for decision, damage evaluation, and any recommendations..." required><?php echo htmlspecialchars($claim['adjuster_notes']); ?></textarea>
                                </div>
                                
                                <div class="action-buttons">
                                    <button type="submit" class="btn btn-adjuster">
                                        <i class="fas fa-check"></i> Submit Assessment
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="assessment-form">
                            <h3 class="section-title">
                                <i class="fas fa-check-circle"></i>
                                Assessment Complete
                            </h3>
                            <div class="detail-section">
                                <div class="detail-row">
                                    <span class="detail-label">Final Status</span>
                                    <span class="detail-value">
                                        <span class="status-badge <?php echo strtolower(str_replace(' ', '', $claim['status'])); ?>">
                                            <?php echo $claim['status']; ?>
                                        </span>
                                    </span>
                                </div>
                                <?php if ($claim['approved_amount']): ?>
                                    <div class="detail-row">
                                        <span class="detail-label">Approved Amount</span>
                                        <span class="detail-value"><?php echo format_currency($claim['approved_amount']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="detail-row">
                                    <span class="detail-label">Assessment Date</span>
                                    <span class="detail-value"><?php echo date('M d, Y H:i', strtotime($claim['reviewed_at'])); ?></span>
                                </div>
                            </div>
                            <?php if ($claim['adjuster_notes']): ?>
                                <div style="margin-top: 20px;">
                                    <strong>Assessment Notes:</strong>
                                    <p style="margin-top: 10px; line-height: 1.6; color: #555;">
                                        <?php echo nl2br(htmlspecialchars($claim['adjuster_notes'])); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Show/hide approved amount field based on status
        document.getElementById('new_status').addEventListener('change', function() {
            const amountField = document.getElementById('approved_amount').parentElement;
            if (this.value === 'Approved') {
                amountField.style.display = 'block';
                document.getElementById('approved_amount').required = true;
            } else {
                amountField.style.display = 'none';
                document.getElementById('approved_amount').required = false;
            }
        });
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            const statusField = document.getElementById('new_status');
            if (statusField) {
                statusField.dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>
</html>
