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
    <title>Terms of Service - Esports Platform</title>
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
            line-height: 1.6;
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
        
        .terms-content {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .section-title {
            color: var(--primary-color);
            border-bottom: 2px solid var(--secondary-color);
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .highlight-box {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-left: 4px solid var(--primary-color);
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
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
            <h1 class="display-4 fw-bold mb-4">Terms of Service</h1>
            <p class="lead mb-4">Please read these terms carefully before using our platform</p>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container mt-5">
        <div class="terms-content">
            <div class="text-center mb-4">
                <p class="text-muted">Last updated: <?php echo date('F d, Y'); ?></p>
            </div>

            <h2 class="section-title">1. Acceptance of Terms</h2>
            <p>By accessing and using the Esports Platform ("Platform"), you accept and agree to be bound by the terms and provision of this agreement. If you do not agree to abide by the above, please do not use this service.</p>

            <h2 class="section-title">2. Description of Service</h2>
            <p>The Platform provides an online esports community where users can:</p>
            <ul>
                <li>Watch live and recorded esports matches</li>
                <li>Participate in tournaments and competitions</li>
                <li>Access gaming guides and tutorials</li>
                <li>Read esports news and updates</li>
                <li>Interact with other community members</li>
            </ul>

            <h2 class="section-title">3. User Accounts</h2>
            <p>To access certain features of the Platform, you must create an account. You are responsible for:</p>
            <ul>
                <li>Maintaining the confidentiality of your account credentials</li>
                <li>All activities that occur under your account</li>
                <li>Providing accurate and complete information</li>
                <li>Notifying us immediately of any unauthorized use</li>
            </ul>

            <div class="highlight-box">
                <strong>Important:</strong> You must be at least 13 years old to create an account. If you are under 18, you must have parental consent to use the Platform.
            </div>

            <h2 class="section-title">4. User Conduct</h2>
            <p>You agree not to:</p>
            <ul>
                <li>Use the Platform for any illegal or unauthorized purpose</li>
                <li>Harass, abuse, or harm other users</li>
                <li>Post inappropriate, offensive, or harmful content</li>
                <li>Attempt to gain unauthorized access to the Platform</li>
                <li>Interfere with the proper functioning of the Platform</li>
                <li>Use automated systems to access the Platform</li>
            </ul>

            <h2 class="section-title">5. Content and Intellectual Property</h2>
            <p>The Platform contains content owned by us and our licensors. You retain ownership of content you create, but grant us a license to use, display, and distribute it on the Platform.</p>

            <h2 class="section-title">6. Privacy and Data Protection</h2>
            <p>Your privacy is important to us. Please review our <a href="privacy.php" class="text-primary">Privacy Policy</a> to understand how we collect, use, and protect your information.</p>

            <h2 class="section-title">7. Tournament Rules</h2>
            <p>Participation in tournaments is subject to additional rules and regulations. By participating, you agree to:</p>
            <ul>
                <li>Follow fair play principles</li>
                <li>Respect other participants</li>
                <li>Accept decisions made by tournament officials</li>
                <li>Not use cheats, hacks, or exploits</li>
            </ul>

            <h2 class="section-title">8. Disclaimers and Limitations</h2>
            <p>The Platform is provided "as is" without warranties of any kind. We are not responsible for:</p>
            <ul>
                <li>Content posted by users</li>
                <li>Technical issues or service interruptions</li>
                <li>Loss of data or account access</li>
                <li>Actions of other users</li>
            </ul>

            <h2 class="section-title">9. Termination</h2>
            <p>We may terminate or suspend your account at any time for violations of these terms. You may also terminate your account at any time by contacting us.</p>

            <h2 class="section-title">10. Changes to Terms</h2>
            <p>We reserve the right to modify these terms at any time. Continued use of the Platform after changes constitutes acceptance of the new terms.</p>

            <h2 class="section-title">11. Contact Information</h2>
            <p>If you have questions about these terms, please contact us at:</p>
            <div class="highlight-box">
                <strong>Email:</strong> support@esportsplatform.com<br>
                <strong>Support Hours:</strong> Monday - Friday, 9:00 AM - 6:00 PM EST
            </div>

            <div class="text-center mt-5">
                <a href="index.php" class="btn btn-primary me-2">
                    <i class="fas fa-home me-2"></i>Back to Home
                </a>
                <a href="privacy.php" class="btn btn-outline-primary">
                    <i class="fas fa-shield-alt me-2"></i>Privacy Policy
                </a>
            </div>
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