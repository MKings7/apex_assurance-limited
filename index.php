<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Check if user is already logged in
if (is_logged_in()) {
    // Redirect to appropriate dashboard based on user role
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

// Get statistics for homepage
$user_count = 0;
$claims_count = 0;
$success_rate = 0;

$sql = "SELECT COUNT(*) as count FROM user WHERE is_active = 1";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $user_count = $row['count'];
}

$sql = "SELECT COUNT(*) as count FROM accident_report";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $claims_count = $row['count'];
}

$sql = "SELECT 
        COUNT(*) as total_claims,
        SUM(CASE WHEN status = 'Approved' OR status = 'Paid' THEN 1 ELSE 0 END) as approved_claims
        FROM accident_report";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    if ($row['total_claims'] > 0) {
        $success_rate = round(($row['approved_claims'] / $row['total_claims']) * 100);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apex Assurance - Motor Vehicle Insurance</title>
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
            line-height: 1.6;
            color: var(--dark-color);
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
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 100;
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
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s, transform 0.3s;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #0045a2;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
            margin-left: 10px;
        }
        
        .btn-secondary:hover {
            background-color: #009e4c;
            transform: translateY(-2px);
        }
        
        /* Hero Section */
        .hero {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('https://images.unsplash.com/photo-1526289034009-0240ddb68ce3?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1200&q=80') no-repeat center center/cover;
            height: 100vh;
            display: flex;
            align-items: center;
            color: white;
            text-align: center;
        }
        
        .hero-content {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .hero h1 {
            font-size: 3rem;
            margin-bottom: 20px;
        }
        
        .hero p {
            font-size: 1.2rem;
            margin-bottom: 30px;
        }
        
        .hero-btns {
            display: flex;
            justify-content: center;
            gap: 20px;
        }
        
        .hero-btn {
            padding: 15px 25px;
            font-size: 1.1rem;
        }
        
        /* Features Section */
        .features {
            padding: 100px 0;
            background-color: white;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 70px;
        }
        
        .section-title h2 {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 15px;
            position: relative;
            display: inline-block;
        }
        
        .section-title h2::after {
            content: '';
            display: block;
            width: 50px;
            height: 3px;
            background-color: var(--secondary-color);
            margin: 15px auto 0;
        }
        
        .section-title p {
            font-size: 1.1rem;
            color: #777;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .feature-card {
            background-color: var(--light-color);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            line-height: 80px;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            margin: 30px auto 20px;
            color: white;
            font-size: 2rem;
            text-align: center;
        }
        
        .feature-content {
            padding: 0 30px 30px;
            text-align: center;
        }
        
        .feature-content h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: var(--primary-color);
        }
        
        /* Stats Section */
        .stats {
            padding: 80px 0;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            text-align: center;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
        }
        
        .stat-item h3 {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        
        /* How It Works Section */
        .how-it-works {
            padding: 100px 0;
            background-color: white;
        }
        
        .steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }
        
        .step {
            text-align: center;
            padding: 30px;
            border-radius: 10px;
            background-color: var(--light-color);
            position: relative;
            z-index: 1;
        }
        
        .step-number {
            width: 50px;
            height: 50px;
            line-height: 50px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            font-weight: bold;
            font-size: 1.5rem;
            margin: 0 auto 20px;
        }
        
        .step h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: var(--primary-color);
        }
        
        .steps-connector {
            display: none;
        }
        
        @media (min-width: 992px) {
            .steps {
                position: relative;
            }
            
            .steps-connector {
                display: block;
                position: absolute;
                top: 65px;
                left: 120px;
                right: 120px;
                height: 2px;
                background-color: #ddd;
                z-index: 0;
            }
        }
        
        /* Testimonials Section */
        .testimonials {
            padding: 100px 0;
            background-color: var(--light-color);
        }
        
        .testimonials-slider {
            margin-top: 50px;
            position: relative;
            width: 80%;
            margin-left: auto;
            margin-right: auto;
        }
        
        .testimonial {
            background-color: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            text-align: center;
            margin: 20px 10px;
        }
        
        .testimonial-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 20px;
            border: 3px solid var(--primary-color);
        }
        
        .testimonial-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .testimonial-content {
            margin-bottom: 20px;
            font-style: italic;
            color: #555;
        }
        
        .testimonial-author {
            color: var(--primary-color);
            font-weight: bold;
        }
        
        .testimonial-author span {
            display: block;
            color: #777;
            font-weight: normal;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        /* CTA Section */
        .cta {
            padding: 100px 0;
            background: linear-gradient(rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0.8)), url('https://images.unsplash.com/photo-1541746951004-4e1e7dac6e3d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1200&q=80') no-repeat center center/cover;
            color: white;
            text-align: center;
        }
        
        .cta h2 {
            font-size: 2.5rem;
            margin-bottom: 20px;
        }
        
        .cta p {
            font-size: 1.1rem;
            margin-bottom: 30px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Footer */
        footer {
            background-color: var(--dark-color);
            color: white;
            padding: 70px 0 20px;
        }
        
        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }
        
        .footer-col h3 {
            font-size: 1.3rem;
            margin-bottom: 20px;
            position: relative;
            display: inline-block;
        }
        
        .footer-col h3::after {
            content: '';
            display: block;
            width: 30px;
            height: 2px;
            background-color: var(--secondary-color);
            margin-top: 10px;
        }
        
        .footer-col ul {
            list-style: none;
        }
        
        .footer-col ul li {
            margin-bottom: 10px;
        }
        
        .footer-col ul li a {
            color: #ddd;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-col ul li a:hover {
            color: var(--secondary-color);
        }
        
        .footer-col .contact-item {
            display: flex;
            margin-bottom: 15px;
        }
        
        .footer-col .contact-icon {
            width: 30px;
            color: var(--secondary-color);
        }
        
        .social-icons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .social-icons a {
            display: inline-block;
            width: 40px;
            height: 40px;
            line-height: 40px;
            text-align: center;
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border-radius: 50%;
            transition: all 0.3s;
        }
        
        .social-icons a:hover {
            background-color: var(--secondary-color);
            transform: translateY(-3px);
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .section-title h2 {
                font-size: 2rem;
            }
            
            .feature-card, .step {
                max-width: 400px;
                margin-left: auto;
                margin-right: auto;
            }
            
            .testimonials-slider {
                width: 95%;
            }
        }
        
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .hero {
                height: auto;
                padding: 120px 0 80px;
            }
            
            .hero h1 {
                font-size: 2rem;
            }
            
            .hero p {
                font-size: 1rem;
            }
            
            .hero-btns {
                flex-direction: column;
                gap: 10px;
            }
            
            .features, .how-it-works, .testimonials, .cta {
                padding: 60px 0;
            }
            
            .section-title {
                margin-bottom: 40px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
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
                <div>
                    <a href="login.php" class="btn btn-primary">Login</a>
                    <a href="register.php" class="btn btn-secondary">Register</a>
                </div>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>Secure Your Journey with Apex Assurance</h1>
                <p>Advanced motor vehicle insurance with streamlined claims processing and 24/7 emergency assistance.</p>
                <div class="hero-btns">
                    <a href="register.php" class="btn btn-secondary hero-btn">Get Started</a>
                    <a href="#how-it-works" class="btn btn-primary hero-btn">Learn More</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <div class="section-title">
                <h2>Why Choose Apex Assurance</h2>
                <p>We provide comprehensive coverage with a customer-centric approach</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="feature-content">
                        <h3>Comprehensive Coverage</h3>
                        <p>Protect your vehicle with our all-inclusive insurance plans that cover accidents, theft, natural disasters, and third-party liability.</p>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <div class="feature-content">
                        <h3>Quick Claims Processing</h3>
                        <p>Experience hassle-free claims with our digital submission process and rapid assessment by our expert adjusters.</p>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-phone-alt"></i>
                    </div>
                    <div class="feature-content">
                        <h3>24/7 Emergency Assistance</h3>
                        <p>Access immediate help anytime with our round-the-clock emergency services, including towing and roadside assistance.</p>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <div class="feature-content">
                        <h3>Competitive Rates</h3>
                        <p>Enjoy affordable premiums and flexible payment options tailored to fit your budget without compromising on coverage.</p>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="feature-content">
                        <h3>Quality Repairs</h3>
                        <p>Get your vehicle back in top condition with repairs from our network of certified repair centers using genuine parts.</p>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <div class="feature-content">
                        <h3>Digital Convenience</h3>
                        <p>Manage your policy, report claims, and track repairs all from our user-friendly online platform or mobile app.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item">
                    <h3><?php echo number_format($user_count); ?>+</h3>
                    <p>Satisfied Customers</p>
                </div>
                <div class="stat-item">
                    <h3><?php echo number_format($claims_count); ?>+</h3>
                    <p>Claims Processed</p>
                </div>
                <div class="stat-item">
                    <h3><?php echo $success_rate; ?>%</h3>
                    <p>Claims Approval Rate</p>
                </div>
                <div class="stat-item">
                    <h3>24/7</h3>
                    <p>Customer Support</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="how-it-works" id="how-it-works">
        <div class="container">
            <div class="section-title">
                <h2>How Our Claims Process Works</h2>
                <p>We've simplified the claims process to get you back on the road quickly</p>
            </div>
            <div class="steps">
                <div class="steps-connector"></div>
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Report Accident</h3>
                    <p>Submit your claim through our online portal or mobile app with photos and details of the incident.</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Quick Assessment</h3>
                    <p>Our adjusters review your claim and assess the damage to your vehicle promptly.</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Repair Authorization</h3>
                    <p>Once approved, select a repair center from our network or choose your preferred facility.</p>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <h3>Get Back on Road</h3>
                    <p>Track repairs in real-time and get your vehicle back in perfect condition without the hassle.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials">
        <div class="container">
            <div class="section-title">
                <h2>What Our Customers Say</h2>
                <p>Hear from our satisfied policyholders about their experience with Apex Assurance</p>
            </div>
            <div class="testimonials-slider">
                <div class="testimonial">
                    <div class="testimonial-avatar">
                        <img src="https://randomuser.me/api/portraits/men/32.jpg" alt="John Mwangi">
                    </div>
                    <div class="testimonial-content">
                        "I was amazed at how quickly my claim was processed after my accident. The online reporting system was easy to use, and I received updates at every step. My car was repaired and back to me within a week!"
                    </div>
                    <div class="testimonial-author">
                        John Mwangi
                        <span>Nairobi, Kenya</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <h2>Ready to Protect Your Vehicle?</h2>
            <p>Join thousands of satisfied drivers who trust Apex Assurance for their motor insurance needs. Get comprehensive coverage, competitive rates, and peace of mind on the road.</p>
            <a href="register.php" class="btn btn-secondary hero-btn">Get Started Today</a>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <h3>About Us</h3>
                    <p>Apex Assurance is Kenya's leading motor vehicle insurance provider, offering comprehensive coverage and innovative claims processing.</p>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="footer-col">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="contact.php">Contact Us</a></li>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Register</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h3>Our Services</h3>
                    <ul>
                        <li><a href="#">Comprehensive Insurance</a></li>
                        <li><a href="#">Third Party Insurance</a></li>
                        <li><a href="#">Theft Coverage</a></li>
                        <li><a href="#">Emergency Assistance</a></li>
                        <li><a href="#">Claims Processing</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h3>Contact Info</h3>
                    <div class="contact-item">
                        <div class="contact-icon"><i class="fas fa-map-marker-alt"></i></div>
                        <div>123 Kimathi Street, Nairobi, Kenya</div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon"><i class="fas fa-phone-alt"></i></div>
                        <div>+254 700 123 456</div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon"><i class="fas fa-envelope"></i></div>
                        <div>info@apexassurance.com</div>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 Apex Assurance. All Rights Reserved. Developed by Eunice Kamau BBIT/2022/49483</p>
            </div>
        </div>
    </footer>
</body>
</html>
