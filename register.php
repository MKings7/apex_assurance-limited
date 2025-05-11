<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$errors = [];
$success = false;

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $first_name = sanitize_input($_POST['first_name']);
    $last_name = sanitize_input($_POST['last_name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $id_number = sanitize_input($_POST['id_number']);
    $user_type = sanitize_input($_POST['user_type']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($first_name)) {
        $errors[] = "First name is required";
    }
    
    if (empty($last_name)) {
        $errors[] = "Last name is required";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT Id FROM user WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "Email already in use";
        }
        $stmt->close();
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    } else {
        // Check if phone already exists
        $stmt = $conn->prepare("SELECT Id FROM user WHERE phone_number = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "Phone number already in use";
        }
        $stmt->close();
    }
    
    if (empty($id_number)) {
        $errors[] = "National ID number is required";
    } else {
        // Check if ID already exists
        $stmt = $conn->prepare("SELECT Id FROM user WHERE national_id = ?");
        $stmt->bind_param("s", $id_number);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "National ID already in use";
        }
        $stmt->close();
    }
    
    if (empty($user_type)) {
        $errors[] = "Account type is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user into the database
        $stmt = $conn->prepare("INSERT INTO user (first_name, last_name, phone_number, email, password, national_id, user_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $first_name, $last_name, $phone, $email, $hashed_password, $id_number, $user_type);
        
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            log_activity($user_id, "User Registration", "New user registered: $email");
            $success = true;
            
            // Redirect based on user type after brief success message
            $redirect_url = 'login.php?registered=1';
            
            if ($user_type == 'RepairCenter') {
                // Create empty repair center record to be completed later
                $repair_stmt = $conn->prepare("INSERT INTO repair_center (user_id, name, location, contact_person, contact_phone, email) VALUES (?, CONCAT(?, ' Auto Repair'), 'Please update', ?, ?, ?)");
                $repair_stmt->bind_param("issss", $user_id, $first_name, $first_name, $phone, $email);
                $repair_stmt->execute();
            } elseif ($user_type == 'EmergencyService') {
                // Create empty emergency service record to be completed later
                $emergency_stmt = $conn->prepare("INSERT INTO emergency_service (user_id, service_type, name, location, contact_phone, email) VALUES (?, 'Towing', CONCAT(?, ' Services'), 'Please update', ?, ?)");
                $emergency_stmt->bind_param("isss", $user_id, $first_name, $phone, $email);
                $emergency_stmt->execute();
            }
        } else {
            $errors[] = "Registration failed: " . $stmt->error;
        }
        
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en"></html>
<head></head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Apex Assurance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style></style>
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
            line-height: 1.6;
            background-color: var(--light-color);
            color: var(--dark-color);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header Styles */
        header {
            background-color: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
        }
        
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        
        .logo a {
            text-decoration: none;
            color: var(--dark-color);
        }
        
        .nav-links {
            display: flex;
            list-style: none;
        }
        
        .nav-links li {
            margin-left: 25px;
        }
        
        .nav-links a {
            text-decoration: none;
            color: var(--dark-color);
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .nav-links a:hover {
            color: var(--primary-color);
        }
        
        /* Register Form */
        .register-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 0;
        }
        
        .register-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 700px;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-header h2 {
            font-size: 2rem;
            color: var(--primary-color);
        }
        
        .register-form .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }
        
        .register-form .form-col {
            flex: 1 0 calc(50% - 20px);
            margin: 0 10px 20px;
        }
        
        .register-form .form-group {
            margin-bottom: 20px;
        }
        
        .register-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .register-form input,
        .register-form select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .register-form input:focus,
        .register-form select:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        .input-icon-wrapper {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #888;
        }
        
        .terms-policy {
            margin-bottom: 20px;
        }
        
        .terms-policy label {
            display: flex;
            align-items: flex-start;
        }
        
        .terms-policy input[type="checkbox"] {
            width: auto;
            margin-right: 10px;
            margin-top: 5px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 20px;
            cursor: pointer;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            font-size: 1rem;
            text-align: center;
            transition: background-color 0.3s, transform 0.3s;
            width: 100%;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #0045a2;
            transform: translateY(-2px);
        }
        
        .register-footer {
            text-align: center;
            margin-top: 30px;
            font-size: 0.9rem;
        }
        
        .register-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .register-footer a:hover {
            text-decoration: underline;
        }
        
        /* Footer */
        footer {
            background-color: var(--dark-color);
            color: white;
            padding: 20px 0;
            margin-top: auto;
        }
        
        .footer-bottom {
            text-align: center;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .register-container {
                padding: 30px 20px;
            }
            
            .register-form .form-col {
                flex: 1 0 100%;
            }
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
    </style>
</head>
<body>
    <!-- Header with Navigation -->
    <header>
        <div class="container">
            <nav>
                <div class="logo">
                    <a href="index.php">
                        <h2>APEX ASSURANCE</h2>
                    </a>
                </div>
                <ul class="nav-links">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="contact.php">Contact</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Register Section -->
    <section class="register-section">
        <div class="register-container">
            <div class="register-header">
                <h2>Create an Account</h2>
                <p>Join Apex Assurance to manage your insurance needs</p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <p>Registration successful! You can now <a href="login.php">login</a>.</p>
                </div>
                <script>
                    // Redirect after 3 seconds
                    setTimeout(function() {
                        window.location.href = "login.php?registered=1";
                    }, 3000);
                </script>
            <?php else: ?>
                <form class="register-form" action="register.php" method="POST">
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" placeholder="Enter your first name" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" placeholder="Enter your last name" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <div class="input-icon-wrapper">
                                    <input type="email" id="email" name="email" placeholder="Enter your email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                                    <span class="input-icon">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <div class="input-icon-wrapper">
                                    <input type="tel" id="phone" name="phone" placeholder="Enter your phone number" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
                                    <span class="input-icon">
                                        <i class="fas fa-phone"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="id_number">National ID Number</label>
                                <input type="text" id="id_number" name="id_number" placeholder="Enter your ID number" value="<?php echo isset($_POST['id_number']) ? htmlspecialchars($_POST['id_number']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="user_type">Account Type</label>
                                <select id="user_type" name="user_type" required>
                                    <option value="">Select account type</option>
                                    <option value="Policyholder" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'Policyholder') ? 'selected' : ''; ?>>Policyholder</option>
                                    <option value="RepairCenter" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'RepairCenter') ? 'selected' : ''; ?>>Repair Center</option>
                                    <option value="EmergencyService" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'EmergencyService') ? 'selected' : ''; ?>>Emergency Service</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="password">Password</label>
                                <div class="input-icon-wrapper">
                                    <input type="password" id="password" name="password" placeholder="Create a password" required>
                                    <span class="input-icon">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="confirm_password">Confirm Password</label>
                                <div class="input-icon-wrapper">
                                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                                    <span class="input-icon">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="terms-policy">
                        <label for="terms">
                            <input type="checkbox" id="terms" name="terms" required>
                            I agree to Apex Assurance's <a href="#">Terms & Conditions</a> and <a href="#">Privacy Policy</a>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Create Account</button>
                </form>
            <?php endif; ?>
            
            <div class="register-footer">
                <p>Already have an account? <a href="login.php">Sign in</a></p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; 2025 Apex Assurance. All Rights Reserved. Developed by Eunice Kamau BBIT/2022/49483</p>
            </div>
        </div>
    </footer>
</body>
</html>