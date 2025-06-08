<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for policyholders
require_role('Policyholder');

$user_id = $_SESSION['user_id'];
$policy_id = filter_var($_GET['policy_id'], FILTER_VALIDATE_INT);
$success_message = '';
$error_message = '';

if (!$policy_id) {
    $_SESSION['error'] = "Invalid policy ID.";
    header("Location: payments.php");
    exit;
}

// Get policy details
$stmt = $conn->prepare("SELECT p.*, c.make, c.model, c.number_plate, pt.name as policy_type_name
                        FROM policy p 
                        JOIN car c ON p.car_id = c.Id 
                        JOIN policy_type pt ON p.policy_type = pt.Id
                        WHERE p.Id = ? AND p.user_id = ?");
$stmt->bind_param("ii", $policy_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Policy not found.";
    header("Location: payments.php");
    exit;
}

$policy = $result->fetch_assoc();

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_payment') {
    $payment_method = sanitize_input($_POST['payment_method']);
    $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
    
    // Validate payment data
    if (!$amount || $amount <= 0) {
        $error_message = "Invalid payment amount.";
    } elseif (empty($payment_method)) {
        $error_message = "Please select a payment method.";
    } else {
        // Simulate payment processing
        $transaction_id = 'TXN_' . uniqid() . '_' . time();
        $payment_status = 'Completed'; // In real app, this would come from payment gateway
        
        // Insert payment record
        $stmt = $conn->prepare("INSERT INTO payments (user_id, policy_id, amount, payment_method, transaction_id, status, payment_date) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iidsss", $user_id, $policy_id, $amount, $payment_method, $transaction_id, $payment_status);
        
        if ($stmt->execute()) {
            // Update policy next payment date
            $next_payment_date = date('Y-m-d', strtotime('+1 year'));
            $stmt = $conn->prepare("UPDATE policy SET next_payment_date = ? WHERE Id = ?");
            $stmt->bind_param("si", $next_payment_date, $policy_id);
            $stmt->execute();
            
            // Create notification
            $notification_title = "Payment Successful";
            $notification_message = "Your payment of " . format_currency($amount) . " for policy {$policy['policy_number']} has been processed successfully.";
            create_notification($user_id, $policy_id, 'Payment', $notification_title, $notification_message);
            
            // Log activity
            log_activity($user_id, "Payment Made", "User made payment of " . format_currency($amount) . " for policy {$policy['policy_number']}");
            
            $success_message = "Payment processed successfully! Transaction ID: " . $transaction_id;
        } else {
            $error_message = "Failed to process payment. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Payment - Apex Assurance</title>
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
        
        .payment-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .policy-summary {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .policy-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .policy-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: var(--user-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 1.5rem;
        }
        
        .policy-details h2 {
            margin-bottom: 5px;
            color: var(--dark-color);
        }
        
        .policy-details p {
            color: #777;
            margin: 0;
        }
        
        .payment-form {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 30px;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--user-color);
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 8px;
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
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .amount-display {
            background-color: #f8f9fa;
            border: 2px solid var(--user-color);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .amount-label {
            font-size: 0.9rem;
            color: #777;
            margin-bottom: 5px;
        }
        
        .amount-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--user-color);
        }
        
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .payment-method {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        
        .payment-method:hover {
            border-color: var(--user-color);
        }
        
        .payment-method.selected {
            border-color: var(--user-color);
            background-color: rgba(0, 123, 255, 0.05);
        }
        
        .payment-method input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .payment-method i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--user-color);
        }
        
        .method-name {
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .card-form {
            display: none;
            margin-top: 20px;
        }
        
        .card-form.active {
            display: block;
        }
        
        .security-info {
            background-color: #e8f4fd;
            border-left: 4px solid var(--user-color);
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .security-info i {
            color: var(--user-color);
            margin-right: 8px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background-color: var(--user-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s;
            width: 100%;
        }
        
        .btn:hover {
            background-color: #0056b3;
        }
        
        .btn:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
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
        
        /* Responsive */
        @media (max-width: 991px) {
            .main-content {
                margin-left: 70px;
            }
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }
            
            .payment-methods {
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
            <div class="payment-container">
                <div class="content-header">
                    <div class="content-title">
                        <h1>Make Payment</h1>
                        <p>Complete your insurance premium payment</p>
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

                <!-- Policy Summary -->
                <div class="policy-summary">
                    <div class="policy-header">
                        <div class="policy-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="policy-details">
                            <h2><?php echo htmlspecialchars($policy['policy_number']); ?></h2>
                            <p><?php echo htmlspecialchars($policy['year'] . ' ' . $policy['make'] . ' ' . $policy['model']); ?> - <?php echo htmlspecialchars($policy['policy_type_name']); ?></p>
                        </div>
                    </div>
                    
                    <div class="amount-display">
                        <div class="amount-label">Premium Amount</div>
                        <div class="amount-value"><?php echo format_currency($policy['premium_amount']); ?></div>
                    </div>
                </div>

                <!-- Payment Form -->
                <?php if (empty($success_message)): ?>
                    <div class="payment-form">
                        <form method="POST" id="paymentForm">
                            <input type="hidden" name="action" value="process_payment">
                            <input type="hidden" name="amount" value="<?php echo $policy['premium_amount']; ?>">
                            
                            <!-- Payment Method Selection -->
                            <div class="form-section">
                                <h3 class="section-title">
                                    <i class="fas fa-credit-card"></i>
                                    Select Payment Method
                                </h3>
                                
                                <div class="payment-methods">
                                    <label class="payment-method" for="credit_card">
                                        <input type="radio" id="credit_card" name="payment_method" value="Credit Card" required>
                                        <i class="fas fa-credit-card"></i>
                                        <div class="method-name">Credit Card</div>
                                    </label>
                                    
                                    <label class="payment-method" for="debit_card">
                                        <input type="radio" id="debit_card" name="payment_method" value="Debit Card" required>
                                        <i class="fas fa-money-check-alt"></i>
                                        <div class="method-name">Debit Card</div>
                                    </label>
                                    
                                    <label class="payment-method" for="bank_transfer">
                                        <input type="radio" id="bank_transfer" name="payment_method" value="Bank Transfer" required>
                                        <i class="fas fa-university"></i>
                                        <div class="method-name">Bank Transfer</div>
                                    </label>
                                    
                                    <label class="payment-method" for="paypal">
                                        <input type="radio" id="paypal" name="payment_method" value="PayPal" required>
                                        <i class="fab fa-paypal"></i>
                                        <div class="method-name">PayPal</div>
                                    </label>
                                </div>
                            </div>

                            <!-- Card Details Form -->
                            <div class="card-form" id="cardForm">
                                <h3 class="section-title">
                                    <i class="fas fa-lock"></i>
                                    Card Details
                                </h3>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="card_number">Card Number</label>
                                        <input type="text" id="card_number" placeholder="1234 5678 9012 3456" maxlength="19">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="card_name">Cardholder Name</label>
                                        <input type="text" id="card_name" placeholder="John Doe">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="expiry_date">Expiry Date</label>
                                        <input type="text" id="expiry_date" placeholder="MM/YY" maxlength="5">
                                    </div>
                                    <div class="form-group">
                                        <label for="cvv">CVV</label>
                                        <input type="text" id="cvv" placeholder="123" maxlength="4">
                                    </div>
                                </div>
                            </div>

                            <!-- Security Information -->
                            <div class="security-info">
                                <i class="fas fa-shield-alt"></i>
                                Your payment information is encrypted and secure. We use industry-standard SSL encryption to protect your data.
                            </div>

                            <!-- Submit Button -->
                            <button type="submit" class="btn" id="submitBtn">
                                <i class="fas fa-credit-card"></i> Process Payment
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; margin-top: 30px;">
                        <a href="payments.php" class="btn">
                            <i class="fas fa-arrow-left"></i> Back to Payments
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Payment method selection
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Update visual selection
                document.querySelectorAll('.payment-method').forEach(method => {
                    method.classList.remove('selected');
                });
                this.closest('.payment-method').classList.add('selected');
                
                // Show/hide card form
                const cardForm = document.getElementById('cardForm');
                if (this.value === 'Credit Card' || this.value === 'Debit Card') {
                    cardForm.classList.add('active');
                } else {
                    cardForm.classList.remove('active');
                }
            });
        });

        // Card number formatting
        document.getElementById('card_number')?.addEventListener('input', function() {
            let value = this.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') ?? value;
            if (formattedValue !== this.value) {
                this.value = formattedValue;
            }
        });

        // Expiry date formatting
        document.getElementById('expiry_date')?.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            this.value = value;
        });

        // CVV validation
        document.getElementById('cvv')?.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Form submission
        document.getElementById('paymentForm')?.addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        });
    </script>
</body>
</html>
