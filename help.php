<?php
session_start();
require_once 'config.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : 'user';
$username = $isLoggedIn ? $_SESSION['username'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Center - Esports Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        :root {
            --primary-color: #6f42c1;
            --secondary-color: #20c997;
            --dark-color: #212529;
            --light-color: #f8f9fa;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .navbar {
            background-color: var(--dark-color);
        }
        
        .navbar-brand {
            font-weight: bold;
            color: var(--secondary-color) !important;
        }
        
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('https://images.unsplash.com/photo-1542751371-adc38448a05e?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1170&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 80px 0;
            text-align: center;
        }
        
        .help-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }
        
        .help-card:hover {
            transform: translateY(-5px);
        }
        
        .faq-item {
            background: white;
            border-radius: 10px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .faq-header {
            background: var(--light-color);
            border-radius: 10px 10px 0 0;
            padding: 15px;
            cursor: pointer;
            border: none;
            width: 100%;
            text-align: left;
        }
        
        .faq-header:hover {
            background: #e9ecef;
        }
        
        .faq-body {
            padding: 15px;
            border-top: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-gamepad me-2"></i>ML HUB
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="matches.php">Matches</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="tournaments.php">Tournaments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="news.php">News</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="guides.php">Guides</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <?php if ($isLoggedIn): ?>
                        <div class="dropdown">
                            <a class="btn btn-outline-light dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($username); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                                <?php if ($userRole === 'admin' || $userRole === 'super_admin'): ?>
                                    <li><a class="dropdown-item" href="admin_dashboard.php">Admin Dashboard</a></li>
                                <?php elseif ($userRole === 'user'): ?>
                                    <li><a class="dropdown-item" href="user_dashboard.php">User Dashboard</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-light me-2">Login</a>
                        <a href="register.php" class="btn btn-primary">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <h1 class="display-4 fw-bold mb-4">Help Center</h1>
            <p class="lead mb-4">Find answers to common questions and get support</p>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container mt-5">
        <!-- Quick Help Cards -->
        <div class="row mb-5">
            <div class="col-md-4 mb-4">
                <div class="help-card text-center p-4">
                    <i class="fas fa-question-circle fa-3x text-primary mb-3"></i>
                    <h5>FAQ</h5>
                    <p class="text-muted">Find answers to frequently asked questions</p>
                    <a href="#faq" class="btn btn-outline-primary">Browse FAQ</a>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="help-card text-center p-4">
                    <i class="fas fa-envelope fa-3x text-primary mb-3"></i>
                    <h5>Contact Support</h5>
                    <p class="text-muted">Get in touch with our support team</p>
                    <a href="contact.php" class="btn btn-outline-primary">Contact Us</a>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="help-card text-center p-4">
                    <i class="fas fa-book fa-3x text-primary mb-3"></i>
                    <h5>Documentation</h5>
                    <p class="text-muted">Read our platform documentation</p>
                    <a href="guides.php" class="btn btn-outline-primary">View Guides</a>
                </div>
            </div>
        </div>

        <!-- FAQ Section -->
        <div id="faq" class="mb-5">
            <h2 class="text-center mb-4"><i class="fas fa-question-circle me-2"></i>Frequently Asked Questions</h2>
            
            <div class="accordion" id="faqAccordion">
                <!-- Account & Registration -->
                <div class="faq-item">
                    <button class="faq-header" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                        <h6 class="mb-0"><i class="fas fa-user me-2"></i>How do I create an account?</h6>
                    </button>
                    <div id="faq1" class="collapse faq-body" data-bs-parent="#faqAccordion">
                        <p>To create an account, click the "Register" button in the top navigation. Fill in your username, email, and password, then click "Create Account". You'll receive a confirmation email to verify your account.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-header" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                        <h6 class="mb-0"><i class="fas fa-key me-2"></i>I forgot my password. What should I do?</h6>
                    </button>
                    <div id="faq2" class="collapse faq-body" data-bs-parent="#faqAccordion">
                        <p>Click the "Forgot Password?" link on the login page. Enter your email address and we'll send you a password reset link. Make sure to check your spam folder if you don't receive the email.</p>
                    </div>
                </div>

                <!-- Tournaments -->
                <div class="faq-item">
                    <button class="faq-header" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                        <h6 class="mb-0"><i class="fas fa-trophy me-2"></i>How do I join a tournament?</h6>
                    </button>
                    <div id="faq3" class="collapse faq-body" data-bs-parent="#faqAccordion">
                        <p>Browse available tournaments on the Tournaments page. Click on a tournament to view details, then click "Join Tournament" if you meet the requirements. Make sure to read the tournament rules before joining.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-header" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                        <h6 class="mb-0"><i class="fas fa-users me-2"></i>Can I participate in tournaments as a team?</h6>
                    </button>
                    <div id="faq4" class="collapse faq-body" data-bs-parent="#faqAccordion">
                        <p>Yes! Many tournaments support team participation. You can either join an existing team or create your own. Team tournaments require all members to have verified accounts.</p>
                    </div>
                </div>

                <!-- Matches -->
                <div class="faq-item">
                    <button class="faq-header" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                        <h6 class="mb-0"><i class="fas fa-gamepad me-2"></i>How do matches work?</h6>
                    </button>
                    <div id="faq5" class="collapse faq-body" data-bs-parent="#faqAccordion">
                        <p>Matches are scheduled between teams or players in tournaments. You'll receive notifications about upcoming matches. Make sure to be online at the scheduled time and follow the match rules.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-header" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">
                        <h6 class="mb-0"><i class="fas fa-clock me-2"></i>What happens if I miss a match?</h6>
                    </button>
                    <div id="faq6" class="collapse faq-body" data-bs-parent="#faqAccordion">
                        <p>Missing a match may result in a forfeit. Contact tournament organizers as soon as possible if you can't attend. Some tournaments allow rescheduling under certain circumstances.</p>
                    </div>
                </div>

                <!-- Platform -->
                <div class="faq-item">
                    <button class="faq-header" type="button" data-bs-toggle="collapse" data-bs-target="#faq7">
                        <h6 class="mb-0"><i class="fas fa-mobile-alt me-2"></i>Is the platform mobile-friendly?</h6>
                    </button>
                    <div id="faq7" class="collapse faq-body" data-bs-parent="#faqAccordion">
                        <p>Yes! Our platform is fully responsive and works on all devices including smartphones and tablets. You can participate in tournaments, watch matches, and manage your account from any device.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-header" type="button" data-bs-toggle="collapse" data-bs-target="#faq8">
                        <h6 class="mb-0"><i class="fas fa-shield-alt me-2"></i>How do you ensure fair play?</h6>
                    </button>
                    <div id="faq8" class="collapse faq-body" data-bs-parent="#faqAccordion">
                        <p>We have strict anti-cheat measures and fair play policies. All matches are monitored, and violations result in immediate disqualification and potential account suspension. Report any suspicious activity to our support team.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Support Section -->
        <div class="text-center mb-5">
            <h3>Still Need Help?</h3>
            <p class="lead">Our support team is here to help you!</p>
            <div class="row justify-content-center">
                <div class="col-md-4 mb-3">
                    <div class="help-card p-4">
                        <i class="fas fa-envelope fa-2x text-primary mb-3"></i>
                        <h5>Email Support</h5>
                        <p class="text-muted">support@esportsplatform.com</p>
                        <small class="text-muted">Response within 24 hours</small>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="help-card p-4">
                        <i class="fas fa-comments fa-2x text-primary mb-3"></i>
                        <h5>Live Chat</h5>
                        <p class="text-muted">Available during business hours</p>
                        <small class="text-muted">Monday - Friday, 9 AM - 6 PM EST</small>
                    </div>
                </div>
            </div>
            <a href="contact.php" class="btn btn-primary btn-lg">
                <i class="fas fa-headset me-2"></i>Contact Support
            </a>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4 mt-5">
        <div class="container">
            <p>&copy; 2024 Esports Platform. All rights reserved.</p>
        </div>
    </footer>
</body>
</html> 