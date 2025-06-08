<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for policy holders (customers)
require_role('PolicyHolder');

$user_id = $_SESSION['user_id'];

// Get dashboard statistics
$stats = [
    'active_policies' => 0,
    'pending_claims' => 0,
    'total_claims' => 0,
    'vehicles' => 0,
    'next_payment' => null,
    'expiring_policies' => 0
];

// Active policies count
$result = $conn->query("SELECT COUNT(*) as count FROM policy WHERE user_id = $user_id AND status = 'Active' AND end_date > CURDATE()");
if ($row = $result->fetch_assoc()) {
    $stats['active_policies'] = $row['count'];
}

// Pending claims count
$result = $conn->query("SELECT COUNT(*) as count FROM accident_report WHERE user_id = $user_id AND status IN ('Pending', 'UnderReview', 'Assigned')");
if ($row = $result->fetch_assoc()) {
    $stats['pending_claims'] = $row['count'];
}

// Total claims count
$result = $conn->query("SELECT COUNT(*) as count FROM accident_report WHERE user_id = $user_id");
if ($row = $result->fetch_assoc()) {
    $stats['total_claims'] = $row['count'];
}

// Vehicles count
$result = $conn->query("SELECT COUNT(*) as count FROM car WHERE user_id = $user_id");
if ($row = $result->fetch_assoc()) {
    $stats['vehicles'] = $row['count'];
}

// Expiring policies (within 30 days)
$result = $conn->query("SELECT COUNT(*) as count FROM policy WHERE user_id = $user_id AND status = 'Active' AND DATEDIFF(end_date, CURDATE()) <= 30 AND DATEDIFF(end_date, CURDATE()) > 0");
if ($row = $result->fetch_assoc()) {
    $stats['expiring_policies'] = $row['count'];
}

// Get recent activities
$recent_activities = $conn->query("SELECT * FROM user_activity WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 10");

// Get recent claims
$recent_claims = $conn->query("SELECT ar.*, c.make, c.model, c.number_plate 
                              FROM accident_report ar 
                              JOIN car c ON ar.car_id = c.Id 
                              WHERE ar.user_id = $user_id 
                              ORDER BY ar.created_at DESC 
                              LIMIT 5");

// Get active policies
$active_policies = $conn->query("SELECT p.*, pt.name as policy_type_name, c.make, c.model, c.number_plate,
                                DATEDIFF(p.end_date, CURDATE()) as days_until_expiry
                                FROM policy p 
                                JOIN policy_type pt ON p.policy_type = pt.Id 
                                JOIN car c ON p.car_id = c.Id 
                                WHERE p.user_id = $user_id AND p.status = 'Active' 
                                ORDER BY p.end_date ASC 
                                LIMIT 5");

// Get notifications
$notifications = $conn->query("SELECT * FROM notifications WHERE user_id = $user_id AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Customer Portal - Apex Assurance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
        
        .welcome-section {
            background: linear-gradient(135deg, var(--customer-color), #0056b3);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.2);
        }
        
        .welcome-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .welcome-text h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .welcome-text p {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .quick-actions {
            display: flex;
            gap: 15px;
        }
        
        .quick-action-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            backdrop-filter: blur(10px);
        }
        
        .quick-action-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
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
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
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
        
        .stat-icon.policies { background-color: var(--customer-color); }
        .stat-icon.claims { background-color: var(--info-color); }
        .stat-icon.vehicles { background-color: var(--success-color); }
        .stat-icon.pending { background-color: var(--warning-color); }
        .stat-icon.expiring { background-color: var(--danger-color); }
        
        .stat-details h3 {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-details p {
            color: #777;
            font-size: 0.85rem;
        }
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .content-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        
        .card-header {
            background-color: var(--customer-color);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h2 {
            margin: 0;
            font-size: 1.2rem;
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* Recent Claims */
        .claim-item {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .claim-item:last-child {
            border-bottom: none;
        }
        
        .claim-info h4 {
            margin-bottom: 5px;
            color: var(--customer-color);
        }
        
        .claim-info p {
            color: #777;
            font-size: 0.9rem;
        }
        
        .claim-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }
        
        .status-underreview {
            background-color: rgba(23, 162, 184, 0.1);
            color: var(--info-color);
        }
        
        .status-approved {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .status-rejected {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        /* Policies List */
        .policy-item {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        
        .policy-item:last-child {
            border-bottom: none;
        }
        
        .policy-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .policy-type {
            font-weight: 600;
            color: var(--customer-color);
        }
        
        .policy-expiry {
            font-size: 0.8rem;
            color: #777;
        }
        
        .policy-vehicle {
            color: #555;
            font-size: 0.9rem;
        }
        
        /* Notifications */
        .notification-item {
            padding: 12px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: flex-start;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: var(--customer-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 0.8rem;
            flex-shrink: 0;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-weight: 500;
            margin-bottom: 3px;
            font-size: 0.9rem;
        }
        
        .notification-message {
            color: #777;
            font-size: 0.8rem;
            line-height: 1.4;
        }
        
        .notification-time {
            color: #999;
            font-size: 0.75rem;
            margin-top: 5px;
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
        
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background-color: var(--customer-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background-color: #0056b3;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--customer-color);
            color: var(--customer-color);
        }
        
        .btn-outline:hover {
            background-color: var(--customer-color);
            color: white;
        }
        
        /* Alert for expiring policies */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            border-left: 4px solid;
        }
        
        .alert-warning {
            background-color: rgba(255, 193, 7, 0.1);
            border-color: var(--warning-color);
            color: #856404;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .main-content {
                margin-left: 70px;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .welcome-content {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .quick-actions {
                flex-direction: column;
                width: 100%;
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
            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="welcome-content">
                    <div class="welcome-text">
                        <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h1>
                        <p>Manage your insurance policies and claims with ease</p>
                    </div>
                    <div class="quick-actions">
                        <a href="claims.php" class="quick-action-btn">
                            <i class="fas fa-file-alt"></i> File Claim
                        </a>
                        <a href="policies.php" class="quick-action-btn">
                            <i class="fas fa-shield-alt"></i> View Policies
                        </a>
                        <a href="make-payment.php" class="quick-action-btn">
                            <i class="fas fa-credit-card"></i> Make Payment
                        </a>
                    </div>
                </div>
            </div>

            <!-- Alert for expiring policies -->
            <?php if ($stats['expiring_policies'] > 0): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Policy Expiring Soon!</strong> You have <?php echo $stats['expiring_policies']; ?> 
                    <?php echo $stats['expiring_policies'] == 1 ? 'policy' : 'policies'; ?> expiring within 30 days. 
                    <a href="policies.php">Review and renew now</a>.
                </div>
            <?php endif; ?>

            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon policies">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $stats['active_policies']; ?></h3>
                        <p>Active Policies</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon claims">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $stats['total_claims']; ?></h3>
                        <p>Total Claims</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon vehicles">
                        <i class="fas fa-car"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $stats['vehicles']; ?></h3>
                        <p>Registered Vehicles</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $stats['pending_claims']; ?></h3>
                        <p>Pending Claims</p>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Recent Claims -->
                <div class="content-card">
                    <div class="card-header">
                        <h2>Recent Claims</h2>
                        <a href="claims.php" class="btn-outline" style="color: white; border-color: white;">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if ($recent_claims->num_rows > 0): ?>
                            <?php while ($claim = $recent_claims->fetch_assoc()): ?>
                                <div class="claim-item">
                                    <div class="claim-info">
                                        <h4><?php echo htmlspecialchars($claim['accident_report_number']); ?></h4>
                                        <p><?php echo htmlspecialchars($claim['make'] . ' ' . $claim['model'] . ' - ' . $claim['number_plate']); ?></p>
                                        <p><?php echo date('M d, Y', strtotime($claim['created_at'])); ?></p>
                                    </div>
                                    <div class="claim-status status-<?php echo strtolower(str_replace(' ', '', $claim['status'])); ?>">
                                        <?php echo $claim['status']; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-file-alt"></i>
                                <h4>No Claims Filed</h4>
                                <p>You haven't filed any claims yet.</p>
                                <a href="report-accident.php" class="btn">File Your First Claim</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Notifications -->
                <div class="content-card">
                    <div class="card-header">
                        <h2>Recent Notifications</h2>
                        <a href="notifications.php" class="btn-outline" style="color: white; border-color: white;">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if ($notifications->num_rows > 0): ?>
                            <?php while ($notification = $notifications->fetch_assoc()): ?>
                                <div class="notification-item">
                                    <div class="notification-icon">
                                        <i class="fas fa-bell"></i>
                                    </div>
                                    <div class="notification-content">
                                        <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                        <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                                        <div class="notification-time"><?php echo time_ago($notification['created_at']); ?></div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-bell"></i>
                                <h4>No New Notifications</h4>
                                <p>You're all caught up!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Active Policies -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Active Policies</h2>
                    <a href="policies.php" class="btn-outline" style="color: white; border-color: white;">Manage All</a>
                </div>
                <div class="card-body">
                    <?php if ($active_policies->num_rows > 0): ?>
                        <?php while ($policy = $active_policies->fetch_assoc()): ?>
                            <div class="policy-item">
                                <div class="policy-header">
                                    <div class="policy-type"><?php echo htmlspecialchars($policy['policy_type_name']); ?></div>
                                    <div class="policy-expiry">
                                        <?php if ($policy['days_until_expiry'] <= 30): ?>
                                            <span style="color: var(--danger-color);">
                                                Expires in <?php echo $policy['days_until_expiry']; ?> days
                                            </span>
                                        <?php else: ?>
                                            Expires <?php echo date('M d, Y', strtotime($policy['end_date'])); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="policy-vehicle">
                                    <?php echo htmlspecialchars($policy['make'] . ' ' . $policy['model'] . ' - ' . $policy['number_plate']); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shield-alt"></i>
                            <h4>No Active Policies</h4>
                            <p>Get protected today with our insurance coverage.</p>
                            <a href="../create-policy.php" class="btn">Get Quote</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Auto-refresh notifications every 5 minutes
        setInterval(function() {
            fetch('get-notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.count > 0) {
                        // Update notification badge or content if needed
                        location.reload();
                    }
                })
                .catch(error => console.log('Error fetching notifications:', error));
        }, 300000);

        // Smooth animations on load
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .content-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>
