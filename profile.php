<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Ensure the user is logged in
require_login();

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'update_profile') {
        $first_name = sanitize_input($_POST['first_name']);
        $last_name = sanitize_input($_POST['last_name']);
        $email = sanitize_input($_POST['email']);
        $phone_number = sanitize_input($_POST['phone_number']);
        $address = sanitize_input($_POST['address']);
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else {
            // Check if email is already taken by another user
            $stmt = $conn->prepare("SELECT Id FROM user WHERE email = ? AND Id != ?");
            $stmt->bind_param("si", $email, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error_message = "Email address is already in use by another account.";
            } else {
                // Update profile
                $stmt = $conn->prepare("UPDATE user SET first_name = ?, last_name = ?, email = ?, phone_number = ?, address = ? WHERE Id = ?");
                $stmt->bind_param("sssssi", $first_name, $last_name, $email, $phone_number, $address, $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                    $success_message = "Profile updated successfully.";
                    log_activity($user_id, "Profile Updated", "User updated their profile information");
                } else {
                    $error_message = "Failed to update profile. Please try again.";
                }
            }
        }
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate passwords
        if ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error_message = "Password must be at least 6 characters long.";
        } else {
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM user WHERE Id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user_data = $result->fetch_assoc();
            
            if (!password_verify($current_password, $user_data['password'])) {
                $error_message = "Current password is incorrect.";
            } else {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE user SET password = ? WHERE Id = ?");
                $stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($stmt->execute()) {
                    $success_message = "Password changed successfully.";
                    log_activity($user_id, "Password Changed", "User changed their password");
                } else {
                    $error_message = "Failed to change password. Please try again.";
                }
            }
        }
    }
}

// Get user data
$stmt = $conn->prepare("SELECT * FROM user WHERE Id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get user statistics
$stats = [
    'total_policies' => 0,
    'total_claims' => 0,
    'total_vehicles' => 0
];

if ($user['user_type'] === 'Policyholder') {
    $result = $conn->query("SELECT COUNT(*) as count FROM policy WHERE user_id = $user_id");
    if ($row = $result->fetch_assoc()) {
        $stats['total_policies'] = $row['count'];
    }
    
    $result = $conn->query("SELECT COUNT(*) as count FROM accident_report WHERE user_id = $user_id");
    if ($row = $result->fetch_assoc()) {
        $stats['total_claims'] = $row['count'];
    }
    
    $result = $conn->query("SELECT COUNT(*) as count FROM car WHERE user_id = $user_id");
    if ($row = $result->fetch_assoc()) {
        $stats['total_vehicles'] = $row['count'];
    }
}

// Get user initials for avatar
$user_initials = '';
$names = explode(' ', $user['first_name'] . ' ' . $user['last_name']);
foreach ($names as $name) {
    $user_initials .= strtoupper(substr($name, 0, 1));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Apex Assurance</title>
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
            --secondary-color: #00b359;
            --dark-color: #333;
            --user-color: #007bff;
            --danger-color: #dc3545;
            --success-color: #28a745;
            --warning-color: #ffc107;
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
            margin-left: <?php echo $user['user_type'] === 'Policyholder' ? '250px' : '260px'; ?>;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .profile-header {
            background: linear-gradient(135deg, var(--user-color), var(--primary-color));
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            margin-right: 30px;
            border: 4px solid rgba(255, 255, 255, 0.3);
        }
        
        .profile-info h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .profile-info p {
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .user-type-badge {
            background-color: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            display: inline-block;
            margin-top: 10px;
        }
        
        /* Profile Statistics */
        <?php if ($user['user_type'] === 'Policyholder'): ?>
        .profile-stats {
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
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--user-color);
        }
        
        .stat-label {
            color: #777;
            font-size: 0.9rem;
        }
        <?php endif; ?>
        
        /* Profile Forms */
        .profile-section {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--dark-color);
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 10px;
            color: var(--user-color);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--user-color);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
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
        
        .btn-user {
            background-color: var(--user-color);
        }
        
        .btn-user:hover {
            background-color: #0056b3;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.2);
            color: var(--success-color);
        }
        
        .alert-error {
            background-color: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.2);
            color: var(--danger-color);
        }
        
        /* Account Actions */
        .account-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .main-content {
                margin-left: 70px;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-avatar {
                margin-right: 0;
                margin-bottom: 20px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .account-actions {
                flex-direction: column;
            }
            
            .profile-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php if ($user['user_type'] === 'Policyholder'): ?>
            <?php include 'users/sidebar.php'; ?>
        <?php elseif ($user['user_type'] === 'Admin'): ?>
            <?php include 'admin/sidebar.php'; ?>
        <?php elseif ($user['user_type'] === 'Adjuster'): ?>
            <?php include 'insurance/sidebar.php'; ?>
        <?php elseif ($user['user_type'] === 'RepairCenter'): ?>
            <?php include 'repair-centers/sidebar.php'; ?>
        <?php endif; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php echo $user_initials; ?>
                </div>
                <div class="profile-info">
                    <h1><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone_number']); ?></p>
                    <p><i class="fas fa-calendar"></i> Member since <?php echo date('M Y', strtotime($user['created_at'])); ?></p>
                    <div class="user-type-badge"><?php echo $user['user_type']; ?></div>
                </div>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Profile Statistics (Only for Policyholders) -->
            <?php if ($user['user_type'] === 'Policyholder'): ?>
                <div class="profile-stats">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['total_policies']); ?></div>
                        <div class="stat-label">Insurance Policies</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['total_claims']); ?></div>
                        <div class="stat-label">Filed Claims</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['total_vehicles']); ?></div>
                        <div class="stat-label">Registered Vehicles</div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Profile Information -->
            <div class="profile-section">
                <h2 class="section-title">
                    <i class="fas fa-user"></i>
                    Personal Information
                </h2>
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="phone_number">Phone Number</label>
                            <input type="tel" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user['phone_number']); ?>" required>
                        </div>
                        <div class="form-group full-width">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" placeholder="Enter your full address"><?php echo htmlspecialchars($user['address']); ?></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-user">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="profile-section">
                <h2 class="section-title">
                    <i class="fas fa-lock"></i>
                    Change Password
                </h2>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" minlength="6" required>
                        </div>
                        <div class="form-group full-width">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" minlength="6" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>

            <!-- Account Actions -->
            <div class="profile-section">
                <h2 class="section-title">
                    <i class="fas fa-cog"></i>
                    Account Settings
                </h2>
                <div class="account-actions">
                    <a href="logout.php" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                    <?php if ($user['user_type'] === 'Policyholder'): ?>
                        <a href="users/index.php" class="btn btn-user">
                            <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
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
    </script>
</body>
</html>
