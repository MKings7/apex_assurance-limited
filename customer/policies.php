<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for policy holders
require_role('PolicyHolder');

$user_id = $_SESSION['user_id'];

// Get user's policies with related information
$policies = $conn->query("SELECT p.*, pt.name as policy_type_name, pt.description as policy_description,
                         c.make, c.model, c.year, c.number_plate,
                         DATEDIFF(p.end_date, CURDATE()) as days_until_expiry,
                         (SELECT COUNT(*) FROM accident_report WHERE policy_id = p.Id) as claim_count
                         FROM policy p 
                         JOIN policy_type pt ON p.policy_type = pt.Id 
                         JOIN car c ON p.car_id = c.Id 
                         WHERE p.user_id = $user_id 
                         ORDER BY p.created_at DESC");

// Get policy statistics
$policy_stats = [
    'active' => 0,
    'expired' => 0,
    'expiring_soon' => 0,
    'total_premium' => 0
];

$result = $conn->query("SELECT 
                       COUNT(*) as total_policies,
                       SUM(CASE WHEN status = 'Active' AND end_date > CURDATE() THEN 1 ELSE 0 END) as active,
                       SUM(CASE WHEN status = 'Expired' OR end_date <= CURDATE() THEN 1 ELSE 0 END) as expired,
                       SUM(CASE WHEN status = 'Active' AND DATEDIFF(end_date, CURDATE()) <= 30 AND DATEDIFF(end_date, CURDATE()) > 0 THEN 1 ELSE 0 END) as expiring_soon,
                       SUM(CASE WHEN status = 'Active' THEN premium_amount ELSE 0 END) as total_premium
                       FROM policy WHERE user_id = $user_id");

if ($row = $result->fetch_assoc()) {
    $policy_stats = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Policies - Customer Portal - Apex Assurance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        // ...existing customer styles...
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --primary-color: #0056b3;
            --customer-color: #007bff;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
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
        
        .page-header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        /* Stats Section */
        .policy-stats {
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
        
        .stat-icon.active { background-color: var(--success-color); }
        .stat-icon.expired { background-color: var(--danger-color); }
        .stat-icon.expiring { background-color: var(--warning-color); }
        .stat-icon.premium { background-color: var(--customer-color); }
        
        .stat-details h3 {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-details p {
            color: #777;
            font-size: 0.8rem;
        }
        
        /* Actions Bar */
        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background-color: var(--customer-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.3);
        }
        
        /* Policies Grid */
        .policies-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 25px;
        }
        
        .policy-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
        }
        
        .policy-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .policy-header {
            background: linear-gradient(135deg, var(--customer-color), #0056b3);
            color: white;
            padding: 20px;
            position: relative;
        }
        
        .policy-type {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .policy-number {
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            display: inline-block;
        }
        
        .policy-status {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-active {
            background-color: rgba(40, 167, 69, 0.2);
            color: var(--success-color);
        }
        
        .status-expired {
            background-color: rgba(220, 53, 69, 0.2);
            color: var(--danger-color);
        }
        
        .status-expiring {
            background-color: rgba(255, 193, 7, 0.2);
            color: var(--warning-color);
        }
        
        .policy-body {
            padding: 25px;
        }
        
        .vehicle-info {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        
        .vehicle-title {
            font-weight: bold;
            color: var(--customer-color);
            margin-bottom: 5px;
        }
        
        .vehicle-details {
            color: #666;
            font-size: 0.9rem;
        }
        
        .policy-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .detail-item {
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
        
        .policy-actions {
            display: flex;
            gap: 10px;
            justify-content: space-between;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #ddd;
            color: #333;
        }
        
        .btn-outline:hover {
            background-color: #f8f9fa;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
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
            .policies-grid {
                grid-template-columns: 1fr;
            }
            
            .policy-details {
                grid-template-columns: 1fr;
            }
            
            .actions-bar {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
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
                <h1>My Insurance Policies</h1>
                <p>Manage your insurance coverage and stay protected</p>
            </div>

            <!-- Policy Statistics -->
            <div class="policy-stats">
                <div class="stat-card">
                    <div class="stat-icon active">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($policy_stats['active']); ?></h3>
                        <p>Active Policies</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon expired">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($policy_stats['expired']); ?></h3>
                        <p>Expired Policies</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon expiring">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($policy_stats['expiring_soon']); ?></h3>
                        <p>Expiring Soon</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon premium">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo format_currency($policy_stats['total_premium']); ?></h3>
                        <p>Annual Premium</p>
                    </div>
                </div>
            </div>

            <!-- Actions Bar -->
            <div class="actions-bar">
                <h2>Your Policies (<?php echo $policies->num_rows; ?>)</h2>
                <a href="../create-policy.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Get New Policy
                </a>
            </div>

            <!-- Policies Grid -->
            <?php if ($policies->num_rows > 0): ?>
                <div class="policies-grid">
                    <?php while ($policy = $policies->fetch_assoc()): ?>
                        <?php
                        $status_class = 'active';
                        $status_text = 'Active';
                        
                        if ($policy['status'] === 'Expired' || $policy['days_until_expiry'] < 0) {
                            $status_class = 'expired';
                            $status_text = 'Expired';
                        } elseif ($policy['days_until_expiry'] <= 30) {
                            $status_class = 'expiring';
                            $status_text = 'Expiring Soon';
                        }
                        ?>
                        
                        <div class="policy-card">
                            <div class="policy-header">
                                <div class="policy-type">
                                    <?php echo htmlspecialchars($policy['policy_type_name']); ?>
                                </div>
                                <div class="policy-number">
                                    <?php echo htmlspecialchars($policy['policy_number']); ?>
                                </div>
                                <div class="policy-status status-<?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </div>
                            </div>
                            
                            <div class="policy-body">
                                <div class="vehicle-info">
                                    <div class="vehicle-title">
                                        <?php echo htmlspecialchars($policy['year'] . ' ' . $policy['make'] . ' ' . $policy['model']); ?>
                                    </div>
                                    <div class="vehicle-details">
                                        License Plate: <?php echo htmlspecialchars($policy['number_plate']); ?>
                                    </div>
                                </div>
                                
                                <div class="policy-details">
                                    <div class="detail-item">
                                        <div class="detail-label">Coverage Period</div>
                                        <div class="detail-value">
                                            <?php echo date('M d, Y', strtotime($policy['start_date'])); ?> - 
                                            <?php echo date('M d, Y', strtotime($policy['end_date'])); ?>
                                        </div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Premium</div>
                                        <div class="detail-value"><?php echo format_currency($policy['premium_amount']); ?>/year</div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Deductible</div>
                                        <div class="detail-value"><?php echo format_currency($policy['deductible']); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Claims Filed</div>
                                        <div class="detail-value"><?php echo $policy['claim_count']; ?> claims</div>
                                    </div>
                                </div>
                                
                                <?php if ($policy['days_until_expiry'] > 0 && $policy['days_until_expiry'] <= 30): ?>
                                    <div style="background-color: rgba(255, 193, 7, 0.1); color: var(--warning-color); padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 0.9rem;">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Expires in <?php echo $policy['days_until_expiry']; ?> day(s)
                                    </div>
                                <?php elseif ($policy['days_until_expiry'] < 0): ?>
                                    <div style="background-color: rgba(220, 53, 69, 0.1); color: var(--danger-color); padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 0.9rem;">
                                        <i class="fas fa-times-circle"></i>
                                        Policy expired <?php echo abs($policy['days_until_expiry']); ?> day(s) ago
                                    </div>
                                <?php endif; ?>
                                
                                <div class="policy-actions">
                                    <a href="view-policy.php?id=<?php echo $policy['Id']; ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                    <?php if ($status_class === 'expiring' || $status_class === 'expired'): ?>
                                        <a href="../create-policy.php?renew=<?php echo $policy['Id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-refresh"></i> Renew
                                        </a>
                                    <?php endif; ?>
                                    <a href="claims.php?policy_id=<?php echo $policy['Id']; ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-file-alt"></i> File Claim
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-shield-alt"></i>
                    <h3>No Insurance Policies</h3>
                    <p>You don't have any insurance policies yet. Get protected today with our comprehensive coverage options.</p>
                    <a href="../create-policy.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Get Your First Policy
                    </a>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Auto-refresh page every 5 minutes to update expiry information
        setInterval(function() {
            location.reload();
        }, 300000);

        // Highlight expiring policies
        document.addEventListener('DOMContentLoaded', function() {
            const expiringCards = document.querySelectorAll('.policy-card .status-expiring');
            expiringCards.forEach(function(card) {
                const policyCard = card.closest('.policy-card');
                policyCard.style.border = '2px solid var(--warning-color)';
            });

            const expiredCards = document.querySelectorAll('.policy-card .status-expired');
            expiredCards.forEach(function(card) {
                const policyCard = card.closest('.policy-card');
                policyCard.style.border = '2px solid var(--danger-color)';
                policyCard.style.opacity = '0.8';
            });
        });
    </script>
</body>
</html>
