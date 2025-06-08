<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Check if user is already logged in
if (is_logged_in()) {
    header("Location: users/index.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $first_name = sanitize_input($_POST['first_name']);
    $second_name = sanitize_input($_POST['second_name']);
    $last_name = sanitize_input($_POST['last_name']);
    $phone_number = sanitize_input($_POST['phone_number']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $gender = sanitize_input($_POST['gender']);
    $national_id = sanitize_input($_POST['national_id']);
    $user_type = 'Policyholder'; // Default to policyholder
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($phone_number) || empty($email) || empty($password) || empty($national_id)) {
        $error = "Please fill in all required fields.";
    } elseif (!validate_email($email)) {
        $error = "Please enter a valid email address.";
    } elseif (!validate_phone($phone_number)) {
        $error = "Please enter a valid Kenyan phone number.";
    } elseif (!validate_national_id($national_id)) {
        $error = "Please enter a valid national ID number.";
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if email or phone already exists
        $stmt = $conn->prepare("SELECT Id FROM user WHERE email = ? OR phone_number = ? OR national_id = ?");
        $stmt->bind_param("sss", $email, $phone_number, $national_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Email, phone number, or national ID already registered.";
        } else {
            // Hash password and insert user
            $hashed_password = hash_password($password);
            
            $stmt = $conn->prepare("INSERT INTO user (first_name, second_name, last_name, phone_number, email, password, gender, national_id, user_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssss", $first_name, $second_name, $last_name, $phone_number, $email, $hashed_password, $gender, $national_id, $user_type);
            
            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;
                
                // Log the registration
                log_activity($user_id, "User Registration", "New user registered: $email");
                
                // Create welcome notification
                create_notification($user_id, $user_id, 'User', 'Welcome to Apex Assurance', 'Welcome! Your account has been successfully created.');
                
                $success = "Registration successful! You can now log in.";
                
                // Clear form data
                $first_name = $second_name = $last_name = $phone_number = $email = $gender = $national_id = '';
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Apex Assurance</title>
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
            --light-color: #f4f4f4;
            --danger-color: #dc3545;
            --success-color: #28a745;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            min-height: 100vh;
            padding: 20px 0;
        }
        
        .register-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .register-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .register-header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .register-header p {
            opacity: 0.9;
        }
        
        .register-form {
            padding: 40px;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            flex: 1;
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-color);
            font-weight: 500;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-group input,
        .input-group select {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #eee;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .input-group input:focus,
        .input-group select:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        .btn-register {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 20px;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 86, 179, 0.3);
        }
        
        .links {
            text-align: center;
        }
        
        .links a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .links a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .alert-error {
            background-color: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.2);
            color: var(--danger-color);
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.2);
            color: var(--success-color);
        }
        
        .required {
            color: var(--danger-color);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .register-form {
                padding: 30px 20px;
            }
            
            .register-header {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>Create Account</h1>
            <p>Join Apex Assurance for comprehensive motor vehicle insurance</p>
        </div>
        
        <div class="register-form">
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name <span class="required">*</span></label>
                        <div class="input-group">
                            <i class="fas fa-user"></i>
                            <input type="text" id="first_name" name="first_name" placeholder="First Name" 
                                   value="<?php echo isset($first_name) ? htmlspecialchars($first_name) : ''; ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="second_name">Second Name</label>
                        <div class="input-group">
                            <i class="fas fa-user"></i>
                            <input type="text" id="second_name" name="second_name" placeholder="Second Name" 
                                   value="<?php echo isset($second_name) ? htmlspecialchars($second_name) : ''; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name <span class="required">*</span></label>
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" id="last_name" name="last_name" placeholder="Last Name" 
                               value="<?php echo isset($last_name) ? htmlspecialchars($last_name) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone_number">Phone Number <span class="required">*</span></label>
                        <div class="input-group">
                            <i class="fas fa-phone"></i>
                            <input type="tel" id="phone_number" name="phone_number" placeholder="e.g., 0700123456" 
                                   value="<?php echo isset($phone_number) ? htmlspecialchars($phone_number) : ''; ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <div class="input-group">
                            <i class="fas fa-venus-mars"></i>
                            <select id="gender" name="gender">
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo (isset($gender) && $gender === 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (isset($gender) && $gender === 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo (isset($gender) && $gender === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" placeholder="your.email@example.com" 
                               value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="national_id">National ID Number <span class="required">*</span></label>
                    <div class="input-group">
                        <i class="fas fa-id-card"></i>
                        <input type="text" id="national_id" name="national_id" placeholder="12345678" 
                               value="<?php echo isset($national_id) ? htmlspecialchars($national_id) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password <span class="required">*</span></label>
                        <div class="input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" placeholder="Minimum 8 characters" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                        <div class="input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm password" required>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn-register">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>
            
            <div class="links">
                <p>Already have an account? <a href="login.php">Sign In</a></p>
                <p><a href="index.php">Back to Home</a></p>
            </div>
        </div>
    </div>
</body>
</html>