<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$success_message = '';
$error_message = '';

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $subject = sanitize_input($_POST['subject']);
    $message = sanitize_input($_POST['message']);
    
    if ($name && $email && $subject && $message) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Insert contact inquiry into database
            $stmt = $conn->prepare("INSERT INTO contact_inquiries (name, email, phone, subject, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("sssss", $name, $email, $phone, $subject, $message);
            
            if ($stmt->execute()) {
                $success_message = "Thank you for your message! We'll get back to you within 24 hours.";
                
                // Clear form data
                $_POST = array();
            } else {
                $error_message = "Sorry, there was an error sending your message. Please try again.";
            }
        } else {
            $error_message = "Please enter a valid email address.";
        }
    } else {
        $error_message = "Please fill in all required fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Apex Assurance</title>
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
            --secondary-color: #f8f9fa;
            --accent-color: #28a745;
            --text-dark: #333;
            --text-light: #666;
        }
        
        body {
            line-height: 1.6;
            color: var(--text-dark);
            background-color: var(--secondary-color);
        }
        
        /* Navigation */
        .navbar {
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            padding: 1rem 0;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
        }
        
        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .nav-links {
            display: flex;
            list-style: none;
            gap: 30px;
        }
        
        .nav-links a {
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 500;
            transition: color 0.3s;
            position: relative;
        }
        
        .nav-links a:hover {
            color: var(--primary-color);
        }
        
        .nav-links a.active {
            color: var(--primary-color);
        }
        
        .nav-buttons {
            display: flex;
            gap: 15px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-outline:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #004494;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 86, 179, 0.3);
        }
        
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--primary-color), #004494);
            color: white;
            padding: 120px 0 60px;
            text-align: center;
        }
        
        .page-header h1 {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .page-header p {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Main Content */
        .main-content {
            max-width: 1200px;
            margin: -40px auto 0;
            padding: 0 20px 80px;
            position: relative;
            z-index: 2;
        }
        
        .contact-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        /* Contact Form */
        .contact-form {
            padding: 50px;
        }
        
        .form-header {
            margin-bottom: 40px;
        }
        
        .form-header h2 {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--primary-color);
        }
        
        .form-header p {
            color: var(--text-light);
            font-size: 1.1rem;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e1e1;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .required {
            color: #dc3545;
        }
        
        .submit-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--primary-color), #004494);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 86, 179, 0.3);
        }
        
        /* Contact Info */
        .contact-info {
            background: linear-gradient(135deg, var(--primary-color), #004494);
            color: white;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .info-header {
            margin-bottom: 40px;
        }
        
        .info-header h2 {
            font-size: 2rem;
            margin-bottom: 15px;
        }
        
        .info-header p {
            opacity: 0.9;
            font-size: 1.1rem;
            line-height: 1.6;
        }
        
        .contact-details {
            margin-bottom: 40px;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        
        .contact-icon {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 1.2rem;
        }
        
        .contact-text h3 {
            margin-bottom: 5px;
            font-size: 1.1rem;
        }
        
        .contact-text p {
            opacity: 0.9;
        }
        
        .office-hours {
            background: rgba(255, 255, 255, 0.1);
            padding: 25px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        
        .office-hours h3 {
            margin-bottom: 15px;
            font-size: 1.2rem;
        }
        
        .hours-list {
            list-style: none;
        }
        
        .hours-list li {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            opacity: 0.9;
        }
        
        /* Alerts */
        .alert {
            padding: 15px 20px;
            margin-bottom: 30px;
            border-radius: 10px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.2);
            color: var(--accent-color);
        }
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }
        
        /* FAQ Section */
        .faq-section {
            background: white;
            margin: 60px auto 0;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 50px;
        }
        
        .faq-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .faq-header h2 {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: var(--primary-color);
        }
        
        .faq-header p {
            color: var(--text-light);
            font-size: 1.1rem;
        }
        
        .faq-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .faq-item {
            background: var(--secondary-color);
            padding: 25px;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .faq-item:hover {
            box-shadow: 0 5px 15px rgba(0, 86, 179, 0.1);
        }
        
        .faq-question {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--primary-color);
        }
        
        .faq-answer {
            color: var(--text-light);
            line-height: 1.6;
        }
        
        /* Footer */
        .footer {
            background-color: #1a1a1a;
            color: white;
            padding: 60px 0 30px;
            margin-top: 80px;
        }
        
        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
        }
        
        .footer-section h3 {
            margin-bottom: 20px;
            color: var(--primary-color);
        }
        
        .footer-section p,
        .footer-section a {
            color: #ccc;
            text-decoration: none;
            line-height: 1.8;
        }
        
        .footer-section a:hover {
            color: white;
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 30px;
            margin-top: 30px;
            border-top: 1px solid #333;
            color: #999;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .contact-container {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .contact-form,
            .contact-info {
                padding: 30px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .faq-grid {
                grid-template-columns: 1fr;
            }
            
            .nav-buttons {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo">Apex Assurance</a>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="about.php">About</a></li>
                <li><a href="contact.php" class="active">Contact</a></li>
            </ul>
            <div class="nav-buttons">
                <a href="login.php" class="btn btn-outline">Login</a>
                <a href="register.php" class="btn btn-primary">Get Started</a>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <div class="page-header">
        <h1>Contact Us</h1>
        <p>Get in touch with our expert team for personalized insurance solutions and exceptional customer service</p>
    </div>

    <!-- Main Content -->
    <div class="main-content">
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

        <div class="contact-container">
            <!-- Contact Form -->
            <div class="contact-form">
                <div class="form-header">
                    <h2>Send us a Message</h2>
                    <p>Have questions about our insurance services? We're here to help you find the perfect coverage.</p>
                </div>

                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Full Name <span class="required">*</span></label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address <span class="required">*</span></label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="subject">Subject <span class="required">*</span></label>
                            <select id="subject" name="subject" required>
                                <option value="">Select a subject</option>
                                <option value="General Inquiry" <?php echo ($_POST['subject'] ?? '') === 'General Inquiry' ? 'selected' : ''; ?>>General Inquiry</option>
                                <option value="Policy Quote" <?php echo ($_POST['subject'] ?? '') === 'Policy Quote' ? 'selected' : ''; ?>>Policy Quote</option>
                                <option value="Claim Support" <?php echo ($_POST['subject'] ?? '') === 'Claim Support' ? 'selected' : ''; ?>>Claim Support</option>
                                <option value="Technical Support" <?php echo ($_POST['subject'] ?? '') === 'Technical Support' ? 'selected' : ''; ?>>Technical Support</option>
                                <option value="Billing Question" <?php echo ($_POST['subject'] ?? '') === 'Billing Question' ? 'selected' : ''; ?>>Billing Question</option>
                                <option value="Partnership" <?php echo ($_POST['subject'] ?? '') === 'Partnership' ? 'selected' : ''; ?>>Partnership Opportunity</option>
                                <option value="Other" <?php echo ($_POST['subject'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="message">Message <span class="required">*</span></label>
                        <textarea id="message" name="message" placeholder="Tell us how we can help you..." required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="submit-btn">
                        <i class="fas fa-paper-plane"></i>
                        Send Message
                    </button>
                </form>
            </div>

            <!-- Contact Information -->
            <div class="contact-info">
                <div class="info-header">
                    <h2>Get in Touch</h2>
                    <p>Our dedicated team is ready to assist you with all your insurance needs. Contact us through any of the following channels.</p>
                </div>

                <div class="contact-details">
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="contact-text">
                            <h3>Our Office</h3>
                            <p>123 Insurance Avenue<br>Business District, City 12345</p>
                        </div>
                    </div>

                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="contact-text">
                            <h3>Phone Support</h3>
                            <p>+1 (555) 123-4567<br>24/7 Emergency Claims</p>
                        </div>
                    </div>

                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="contact-text">
                            <h3>Email Support</h3>
                            <p>support@apexassurance.com<br>claims@apexassurance.com</p>
                        </div>
                    </div>

                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <div class="contact-text">
                            <h3>Live Chat</h3>
                            <p>Available on our website<br>Mon-Fri, 8 AM - 8 PM</p>
                        </div>
                    </div>
                </div>

                <div class="office-hours">
                    <h3>Office Hours</h3>
                    <ul class="hours-list">
                        <li><span>Monday - Friday</span><span>8:00 AM - 6:00 PM</span></li>
                        <li><span>Saturday</span><span>9:00 AM - 4:00 PM</span></li>
                        <li><span>Sunday</span><span>10:00 AM - 2:00 PM</span></li>
                        <li><span>Emergency Claims</span><span>24/7 Available</span></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- FAQ Section -->
        <div class="faq-section">
            <div class="faq-header">
                <h2>Frequently Asked Questions</h2>
                <p>Quick answers to common questions about our services</p>
            </div>

            <div class="faq-grid">
                <div class="faq-item">
                    <div class="faq-question">How quickly can I get a quote?</div>
                    <div class="faq-answer">You can get an instant quote online in just 2-3 minutes. Simply fill out our quick form with your vehicle and personal information.</div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">What types of insurance do you offer?</div>
                    <div class="faq-answer">We offer comprehensive auto insurance, including liability, collision, comprehensive, and additional coverage options to protect you and your vehicle.</div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">How do I file a claim?</div>
                    <div class="faq-answer">You can file a claim 24/7 through our online portal, mobile app, or by calling our claims hotline. Our team will guide you through the entire process.</div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">Can I manage my policy online?</div>
                    <div class="faq-answer">Yes! Our customer portal allows you to view policies, make payments, update information, file claims, and track claim status anytime, anywhere.</div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">Do you offer discounts?</div>
                    <div class="faq-answer">Yes, we offer various discounts including safe driver, multi-vehicle, good student, and bundling discounts. Contact us to see which discounts you qualify for.</div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">What payment methods do you accept?</div>
                    <div class="faq-answer">We accept all major credit cards, debit cards, bank transfers, and automatic monthly payments for your convenience.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>Apex Assurance</h3>
                <p>Your trusted partner for comprehensive auto insurance solutions. Protecting what matters most to you and your family.</p>
            </div>
            <div class="footer-section">
                <h3>Quick Links</h3>
                <p><a href="index.php">Home</a></p>
                <p><a href="about.php">About Us</a></p>
                <p><a href="contact.php">Contact</a></p>
                <p><a href="login.php">Customer Login</a></p>
            </div>
            <div class="footer-section">
                <h3>Services</h3>
                <p><a href="#">Auto Insurance</a></p>
                <p><a href="#">Claims Processing</a></p>
                <p><a href="#">24/7 Support</a></p>
                <p><a href="#">Mobile App</a></p>
            </div>
            <div class="footer-section">
                <h3>Contact Info</h3>
                <p><i class="fas fa-phone"></i> +1 (555) 123-4567</p>
                <p><i class="fas fa-envelope"></i> support@apexassurance.com</p>
                <p><i class="fas fa-map-marker-alt"></i> 123 Insurance Ave, City 12345</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2024 Apex Assurance. All rights reserved. | Privacy Policy | Terms of Service</p>
        </div>
    </footer>

    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = '#dc3545';
                } else {
                    field.style.borderColor = '#e1e1e1';
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });

        // Auto-hide success messages
        const successAlert = document.querySelector('.alert-success');
        if (successAlert) {
            setTimeout(() => {
                successAlert.style.opacity = '0';
                setTimeout(() => {
                    successAlert.style.display = 'none';
                }, 300);
            }, 5000);
        }
    </script>
</body>
</html>
