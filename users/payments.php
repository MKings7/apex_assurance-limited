<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for policyholders
require_role('Policyholder');

$user_id = $_SESSION['user_id'];

// Get user's payments
$stmt = $conn->prepare("SELECT pay.*, p.policy_number, p.premium_amount
                        FROM payments pay
                        JOIN policy p ON pay.policy_id = p.Id
                        WHERE pay.user_id = ?
                        ORDER BY pay.payment_date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$payments = $stmt->get_result();

// Get payment statistics
$stats = [
    'total_paid' => 0,
    'pending_payments' => 0,
    'this_year_paid' => 0,
    'overdue_payments' => 0
];

// Total paid
$stmt = $conn->prepare("SELECT SUM(amount) as total FROM payments WHERE user_id = ? AND status = 'Completed'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $stats['total_paid'] = $row['total'] ?: 0;
}

// This year paid
$stmt = $conn->prepare("SELECT SUM(amount) as total FROM payments WHERE user_id = ? AND status = 'Completed' AND YEAR(payment_date) = YEAR(CURRENT_DATE())");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $stats['this_year_paid'] = $row['total'] ?: 0;
}

// Pending and overdue payments from active policies
$stmt = $conn->prepare("SELECT COUNT(*) as pending FROM policy WHERE user_id = ? AND status = 'Active' AND next_payment_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY)");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $stats['pending_payments'] = $row['pending'];
}

$stmt = $conn->prepare("SELECT COUNT(*) as overdue FROM policy WHERE user_id = ? AND status = 'Active' AND next_payment_date < CURRENT_DATE()");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $stats['overdue_payments'] = $row['overdue'];
}

// Get upcoming payments
$stmt = $conn->prepare("SELECT p.*, pt.name as policy_type_name, c.make, c.model 
                        FROM policy p 
                        JOIN policy_type pt ON p.policy_type = pt.Id
                        JOIN car c ON p.car_id = c.Id
                        WHERE p.user_id = ? AND p.status = 'Active' 
                        AND p.next_payment_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 60 DAY)
                        ORDER BY p.next_payment_date ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$upcoming_payments = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - Apex Assurance</title>
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
        
        /* Payment Stats */
        .payment-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 25px;
            display: flex;
            align-items: center;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-icon.success { background-color: var(--success-color); }
        .stat-icon.warning { background-color: var(--warning-color); }
        .stat-icon.danger { background-color: var(--danger-color); }
        .stat-icon.user { background-color: var(--user-color); }
        
        .stat-details h3 {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-details p {
            color: #777;
            font-size: 0.9rem;
            margin: 0;
        }
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        .section-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 25px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .section-header h2 {
            font-size: 1.3rem;
            color: var(--dark-color);
        }
        
        /* Payment History Table */
        .payment-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .payment-table th,
        .payment-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .payment-table th {
            font-weight: 600;
            color: #555;
            font-size: 0.9rem;
        }
        
        .payment-table tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-badge.completed {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .status-badge.pending {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }
        
        .status-badge.failed {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        /* Upcoming Payments */
        .upcoming-payment {
            padding: 15px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .upcoming-payment:hover {
            border-color: var(--user-color);
            box-shadow: 0 2px 8px rgba(0, 123, 255, 0.1);
        }
        
        .upcoming-payment.overdue {
            border-color: var(--danger-color);
            background-color: rgba(220, 53, 69, 0.02);
        }
        
        .upcoming-payment.due-soon {
            border-color: var(--warning-color);
            background-color: rgba(255, 193, 7, 0.02);
        }
        
        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .payment-policy {
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .payment-amount {
            font-size: 1.1rem;
            font-weight: bold;
            color: var(--user-color);
        }
        
        .payment-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .payment-vehicle {
            color: #777;
            font-size: 0.9rem;
        }
        
        .payment-date {
            font-size: 0.8rem;
        }
        
        .payment-date.overdue {
            color: var(--danger-color);
            font-weight: 600;
        }
        
        .payment-date.due-soon {
            color: var(--warning-color);
            font-weight: 600;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background-color: var(--user-color);
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
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }
        
        .btn-success {
            background-color: var(--success-color);
        }
        
        .btn-success:hover {
            background-color: #1e7e34;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .main-content {
                margin-left: 70px;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .payment-stats {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .payment-stats {
                grid-template-columns: 1fr;
            }
            
            .payment-table {
                font-size: 0.85rem;
            }
            
            .payment-table th:nth-child(4),
            .payment-table td:nth-child(4) {
                display: none;
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
                    <h1>Payment Management</h1>
                    <p>Track your premium payments and payment history</p>
                </div>
            </div>

            <!-- Payment Statistics -->
            <div class="payment-stats">
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo format_currency($stats['total_paid']); ?></h3>
                        <p>Total Paid</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon user">
                        <i class="fas fa-calendar-year"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo format_currency($stats['this_year_paid']); ?></h3>
                        <p>Paid This Year</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['pending_payments']); ?></h3>
                        <p>Upcoming Payments</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['overdue_payments']); ?></h3>
                        <p>Overdue Payments</p>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Payment History -->
                <div class="section-card">
                    <div class="section-header">
                        <h2>Payment History</h2>
                    </div>
                    <?php if ($payments->num_rows > 0): ?>
                        <table class="payment-table">
                            <thead>
                                <tr>
                                    <th>Payment Date</th>
                                    <th>Policy Number</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($payment = $payments->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($payment['policy_number']); ?></td>
                                        <td><?php echo format_currency($payment['amount']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower($payment['status']); ?>">
                                                <?php echo $payment['status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-credit-card"></i>
                            <h3>No Payment History</h3>
                            <p>You haven't made any payments yet.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Upcoming Payments -->
                <div class="section-card">
                    <div class="section-header">
                        <h2>Upcoming Payments</h2>
                    </div>
                    <?php if ($upcoming_payments->num_rows > 0): ?>
                        <?php while ($payment = $upcoming_payments->fetch_assoc()): ?>
                            <?php
                            $due_date = strtotime($payment['next_payment_date']);
                            $today = time();
                            $days_diff = ($due_date - $today) / (60 * 60 * 24);
                            
                            $class = '';
                            $date_class = '';
                            if ($days_diff < 0) {
                                $class = 'overdue';
                                $date_class = 'overdue';
                                $status_text = 'Overdue';
                            } elseif ($days_diff <= 7) {
                                $class = 'due-soon';
                                $date_class = 'due-soon';
                                $status_text = 'Due Soon';
                            } else {
                                $status_text = 'Upcoming';
                            }
                            ?>
                            <div class="upcoming-payment <?php echo $class; ?>">
                                <div class="payment-header">
                                    <div class="payment-policy"><?php echo htmlspecialchars($payment['policy_number']); ?></div>
                                    <div class="payment-amount"><?php echo format_currency($payment['premium_amount']); ?></div>
                                </div>
                                <div class="payment-details">
                                    <div class="payment-vehicle">
                                        <?php echo htmlspecialchars($payment['make'] . ' ' . $payment['model']); ?> - <?php echo htmlspecialchars($payment['policy_type_name']); ?>
                                    </div>
                                    <div class="payment-date <?php echo $date_class; ?>">
                                        <?php echo $status_text; ?> - <?php echo date('M d, Y', strtotime($payment['next_payment_date'])); ?>
                                    </div>
                                </div>
                                <div style="margin-top: 10px; text-align: right;">
                                    <a href="make-payment.php?policy_id=<?php echo $payment['Id']; ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-credit-card"></i> Pay Now
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-check"></i>
                            <h3>No Upcoming Payments</h3>
                            <p>All payments are up to date!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
