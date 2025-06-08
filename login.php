<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Check if user is already logged in
if (is_logged_in()) {
    switch ($_SESSION['user_type']) {
        case 'Admin':
            header("Location: admin/index.php");
            break;
        case 'Adjuster':
            header("Location: insurance/index.php");
            break;
        case 'RepairCenter':
            header("Location: repair/index.php");
            break;
        case 'EmergencyService':
            header("Location: emergency/index.php");
            break;
        default:
            header("Location: users/index.php");
            break;
    }
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    
    // Rate limiting
    if (!rate_limit_check($email, LOGIN_ATTEMPTS_LIMIT, LOGIN_LOCKOUT_TIME)) {
        $error = "Too many login attempts. Please try again later.";
    } elseif (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        // Check user credentials
        $stmt = $conn->prepare("SELECT Id, first_name, second_name, last_name, email, password, user_type, is_active FROM user WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (!$user['is_active']) {
                $error = "Your account has been deactivated. Please contact support.";
            } elseif (verify_password($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['Id'];
                $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['second_name'] . ' ' . $user['last_name']);
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_type'] = $user['user_type'];
                
                // Log the login
                log_activity($user['Id'], "User Login", "User logged in successfully");
                
                // Redirect based on user type
                switch ($user['user_type']) {
                    case 'Admin':
                        header("Location: admin/index.php");
                        break;
                    case 'Adjuster':
                        header("Location: insurance/index.php");
                        break;
                    case 'RepairCenter':
                        header("Location: repair/index.php");
                        break;
                    case 'EmergencyService':
                        header("Location: emergency/index.php");
                        break;
                    default:
                        header("Location: users/index.php");
                        break;
                }
                exit;
            } else {
                $error = "Invalid email or password.";
            }
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Apex Assurance</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background-color: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 600px;
        }
        
        .login-form {
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-info {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: var(--primary-color);
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .logo p {
            color: #777;
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 25px;
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
        
        .input-group input {
            width: 100%;
            padding: 15px 20px 15px 50px;
            border: 2px solid #eee;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .input-group input:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        .input-group i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        .btn-login {
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
        
        .btn-login:hover {
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
        
        .divider {
            text-align: center;
            margin: 20px 0;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background-color: #eee;
        }
        
        .divider span {
            background-color: white;
            padding: 0 15px;
            color: #999;
            font-size: 0.9rem;
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
        
        .info-content h2 {
            font-size: 2rem;
            margin-bottom: 20px;
        }
        
        .info-content p {
            font-size: 1.1rem;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        
        .features {
            list-style: none;
            text-align: left;
        }
        
        .features li {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .features li i {
            margin-right: 15px;
            font-size: 1.2rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .login-container {
                grid-template-columns: 1fr;
                max-width: 400px;
            }
            
            .login-info {
                display: none;
            }
            
            .login-form {
                padding: 40px 30px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-form">
            <div class="logo">
                <h1>APEX ASSURANCE</h1>
                <p>Motor Vehicle Insurance System</p>
            </div>
            
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
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" placeholder="Enter your email" 
                               value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    </div>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>
            
            <div class="divider">
                <span>or</span>
            </div>
            
            <div class="links">
                <p>Don't have an account? <a href="register.php">Create Account</a></p>
                <p><a href="forgot-password.php">Forgot Password?</a></p>
                <p><a href="index.php">Back to Home</a></p>
            </div>
        </div>
        
        <div class="login-info">
            <div class="info-content">
                <h2>Welcome Back!</h2>
                <p>Access your insurance dashboard and manage your motor vehicle policies with ease.</p>
                
                <ul class="features">
                    <li><i class="fas fa-car-crash"></i> Report accidents instantly</li>
                    <li><i class="fas fa-chart-line"></i> Track claim progress</li>
                    <li><i class="fas fa-shield-alt"></i> Manage your policies</li>
                    <li><i class="fas fa-phone-alt"></i> 24/7 emergency support</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>