<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for policy holders
require_role('PolicyHolder');

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $first_name = sanitize_input($_POST['first_name']);
                $last_name = sanitize_input($_POST['last_name']);
                $email = sanitize_input($_POST['email']);
                $phone_number = sanitize_input($_POST['phone_number']);
                $address = sanitize_input($_POST['address']);
                $date_of_birth = sanitize_input($_POST['date_of_birth']);
                $gender = sanitize_input($_POST['gender']);
                
                if ($first_name && $last_name && $email) {
                    // Check if email is already used by another user
                    $stmt = $conn->prepare("SELECT Id FROM user WHERE email = ? AND Id != ?");
                    $stmt->bind_param("si", $email, $user_id);
                    $stmt->execute();
                    
                    if ($stmt->get_result()->num_rows == 0) {
                        $stmt = $conn->prepare("UPDATE user SET first_name = ?, last_name = ?, email = ?, phone_number = ?, address = ?, date_of_birth = ?, gender = ? WHERE Id = ?");
                        $stmt->bind_param("sssssssi", $first_name, $last_name, $email, $phone_number, $address, $date_of_birth, $gender, $user_id);
                        
                        if ($stmt->execute()) {
                            $success_message = "Profile updated successfully!";
                            $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                            log_activity($user_id, "Profile Updated", "User updated their profile information");
                        } else {
                            $error_message = "Failed to update profile. Please try again.";
                        }
                    } else {
                        $error_message = "Email address is already in use by another account.";
                    }
                } else {
                    $error_message = "Please fill in all required fields.";
                }
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                if ($current_password && $new_password && $confirm_password) {
                    // Verify current password
                    $stmt = $conn->prepare("SELECT password FROM user WHERE Id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();
                    
                    if (password_verify($current_password, $user['password'])) {
                        if ($new_password === $confirm_password) {
                            if (strlen($new_password) >= 8) {
                                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                                $stmt = $conn->prepare("UPDATE user SET password = ? WHERE Id = ?");
                                $stmt->bind_param("si", $hashed_password, $user_id);
                                
                                if ($stmt->execute()) {
                                    $success_message = "Password changed successfully!";
                                    log_activity($user_id, "Password Changed", "User changed their password");
                                } else {
                                    $error_message = "Failed to change password. Please try again.";
                                }
                            } else {
                                $error_message = "New password must be at least 8 characters long.";
                            }
                        } else {
                            $error_message = "New passwords do not match.";
                        }
                    } else {
                        $error_message = "Current password is incorrect.";
                    }
                } else {
                    $error_message = "Please fill in all password fields.";
                }
                break;
        }
    }
}

// Get user information
$stmt = $conn->prepare("SELECT * FROM user WHERE Id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get user statistics
$user_stats = [
    'account_age' => 0,
    'total_policies' => 0,
    'total_claims' => 0,
    'total_vehicles' => 0
];

// Calculate account age
if ($user['created_at']) {
    $account_age = (new DateTime())->diff(new DateTime($user['created_at']));
    $user_stats['account_age'] = $account_age->days;
}

// Get counts
$result = $conn->query("SELECT COUNT(*) as count FROM policy WHERE user_id = $user_id");
if ($row = $result->fetch_assoc()) {
    $user_stats['total_policies'] = $row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM accident_report WHERE user_id = $user_id");
if ($row = $result->fetch_assoc()) {
    $user_stats['total_claims'] = $row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM car WHERE user_id = $user_id");
if ($row = $result->fetch_assoc()) {
    $user_stats['total_vehicles'] = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Customer Portal - Apex Assurance</title>
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
        
        /* Profile Header */
        .profile-header {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            padding: 30px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--customer-color), #0056b3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            margin-right: 30px;
        }
        
        .profile-info h1 {
            font-size: 2rem;
            margin-bottom: 8px;
            color: var(--customer-color);
        }
        
        .profile-info p {
            color: #777;
            margin-bottom: 5px;
        }
        
        .profile-badge {
            background: rgba(0, 123, 255, 0.1);
            color: var(--customer-color);
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
            margin-top: 10px;
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
        
        .stat-icon.days { background-color: var(--customer-color); }
        .stat-icon.policies { background-color: var(--success-color); }
        .stat-icon.claims { background-color: var(--warning-color); }
        .stat-icon.vehicles { background-color: #6f42c1; }
        
        .stat-details h3 {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-details p {
            color: #777;
            font-size: 0.8rem;
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
            padding: 30px;
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Form Styles */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--customer-color);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }
        
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        
        .btn {
            padding: 12px 24px;
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
        
        .btn-customer {
            background-color: var(--customer-color);
            color: white;
        }
        
        .btn-customer:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.3);
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #ddd;
            color: #555;
        }
        
        .btn-outline:hover {
            background-color: #f8f9fa;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        .password-requirements {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .password-requirements ul {
            margin: 10px 0 0 20px;
            color: #666;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .main-content {
                margin-left: 70px;
            }
        }
        
        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-avatar {
                margin-right: 0;
                margin-bottom: 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .tabs-nav {
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
            <!-- Page Header -->
            <div class="page-header">
                <h1>My Profile</h1>
                <p>Manage your personal information and account settings</p>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                </div>
                <div class="profile-info">
                    <h1><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone_number'] ?: 'Not provided'); ?></p>
                    <p><i class="fas fa-calendar"></i> Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
                    <div class="profile-badge">
                        <i class="fas fa-user"></i> Policy Holder
                    </div>
                </div>
            </div>

            <!-- Account Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon days">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($user_stats['account_age']); ?></h3>
                        <p>Days with us</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon policies">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($user_stats['total_policies']); ?></h3>
                        <p>Total Policies</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon claims">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($user_stats['total_claims']); ?></h3>
                        <p>Claims Filed</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon vehicles">
                        <i class="fas fa-car"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($user_stats['total_vehicles']); ?></h3>
                        <p>Registered Vehicles</p>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="tabs-container">
                <div class="tabs-nav">
                    <div class="tab active" onclick="showTab('personal')">
                        <i class="fas fa-user"></i> Personal Information
                    </div>
                    <div class="tab" onclick="showTab('security')">
                        <i class="fas fa-lock"></i> Security
                    </div>
                </div>

                <!-- Personal Information Tab -->
                <div id="personal" class="tab-content active">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name *</label>
                                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name *</label>
                                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="phone_number">Phone Number</label>
                                <input type="tel" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user['phone_number']); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="date_of_birth">Date of Birth</label>
                                <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo $user['date_of_birth']; ?>">
                            </div>
                            <div class="form-group">
                                <label for="gender">Gender</label>
                                <select id="gender" name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?php echo $user['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo $user['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo $user['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" placeholder="Enter your full address"><?php echo htmlspecialchars($user['address']); ?></textarea>
                        </div>
                        
                        <div style="text-align: right; margin-top: 30px;">
                            <button type="submit" class="btn btn-customer">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Security Tab -->
                <div id="security" class="tab-content">
                    <div class="password-requirements">
                        <h4><i class="fas fa-info-circle"></i> Password Requirements</h4>
                        <ul>
                            <li>At least 8 characters long</li>
                            <li>Include both letters and numbers</li>
                            <li>Use special characters for better security</li>
                        </ul>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label for="current_password">Current Password *</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_password">New Password *</label>
                                <input type="password" id="new_password" name="new_password" required minlength="8">
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password *</label>
                                <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                            </div>
                        </div>
                        
                        <div style="text-align: right; margin-top: 30px;">
                            <button type="submit" class="btn btn-customer">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
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
        
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        document.getElementById('new_password').addEventListener('input', function() {
            const confirmPassword = document.getElementById('confirm_password');
            if (confirmPassword.value) {
                confirmPassword.dispatchEvent(new Event('input'));
            }
        });
    </script>
</body>
</html>
