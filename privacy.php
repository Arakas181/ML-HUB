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
    <title>Privacy Policy - Esports Platform</title>
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
        
        .privacy-content {
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
        
        .info-table {
            background: var(--light-color);
            border-radius: 10px;
            padding: 20px;
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
            <h1 class="display-4 fw-bold mb-4">Privacy Policy</h1>
            <p class="lead mb-4">How we collect, use, and protect your information</p>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container mt-5">
        <div class="privacy-content">
            <div class="text-center mb-4">
                <p class="text-muted">Last updated: <?php echo date('F d, Y'); ?></p>
            </div>

            <h2 class="section-title">1. Information We Collect</h2>
            <p>We collect information you provide directly to us, such as when you create an account, participate in tournaments, or contact us for support.</p>
            
            <div class="info-table">
                <h5><i class="fas fa-user me-2"></i>Personal Information</h5>
                <ul>
                    <li>Username and email address</li>
                    <li>Full name and profile information</li>
                    <li>Gaming preferences and statistics</li>
                    <li>Communication preferences</li>
                </ul>
            </div>

            <div class="info-table">
                <h5><i class="fas fa-gamepad me-2"></i>Usage Information</h5>
                <ul>
                    <li>Tournament participation and results</li>
                    <li>Match viewing history</li>
                    <li>Content interaction and preferences</li>
                    <li>Platform usage patterns</li>
                </ul>
            </div>

            <h2 class="section-title">2. How We Use Your Information</h2>
            <p>We use the information we collect to:</p>
            <ul>
                <li>Provide and maintain our services</li>
                <li>Process tournament registrations and results</li>
                <li>Send important updates and notifications</li>
                <li>Improve our platform and user experience</li>
                <li>Ensure platform security and prevent fraud</li>
                <li>Comply with legal obligations</li>
            </ul>

            <h2 class="section-title">3. Information Sharing</h2>
            <p>We do not sell, trade, or otherwise transfer your personal information to third parties, except in the following circumstances:</p>
            <ul>
                <li><strong>Service Providers:</strong> We may share information with trusted third-party service providers who assist us in operating our platform</li>
                <li><strong>Legal Requirements:</strong> We may disclose information if required by law or to protect our rights and safety</li>
                <li><strong>Business Transfers:</strong> In the event of a merger or acquisition, user information may be transferred</li>
                <li><strong>Consent:</strong> We may share information with your explicit consent</li>
            </ul>

            <h2 class="section-title">4. Data Security</h2>
            <p>We implement appropriate security measures to protect your personal information:</p>
            <div class="highlight-box">
                <ul>
                    <li>Encryption of sensitive data in transit and at rest</li>
                    <li>Regular security audits and updates</li>
                    <li>Access controls and authentication measures</li>
                    <li>Secure hosting and infrastructure</li>
                </ul>
            </div>

            <h2 class="section-title">5. Data Retention</h2>
            <p>We retain your personal information for as long as necessary to:</p>
            <ul>
                <li>Provide our services</li>
                <li>Comply with legal obligations</li>
                <li>Resolve disputes and enforce agreements</li>
                <li>Maintain platform security</li>
            </ul>
            <p>You may request deletion of your account and associated data at any time.</p>

            <h2 class="section-title">6. Your Rights and Choices</h2>
            <p>You have the right to:</p>
            <ul>
                <li>Access and review your personal information</li>
                <li>Update or correct inaccurate information</li>
                <li>Request deletion of your account and data</li>
                <li>Opt-out of marketing communications</li>
                <li>Control cookie preferences</li>
            </ul>

            <h2 class="section-title">7. Cookies and Tracking</h2>
            <p>We use cookies and similar technologies to:</p>
            <ul>
                <li>Remember your preferences and settings</li>
                <li>Analyze platform usage and performance</li>
                <li>Provide personalized content and features</li>
                <li>Ensure platform security</li>
            </ul>
            <p>You can control cookie settings through your browser preferences.</p>

            <h2 class="section-title">8. Children's Privacy</h2>
            <p>Our platform is not intended for children under 13 years of age. We do not knowingly collect personal information from children under 13. If you are a parent or guardian and believe your child has provided us with personal information, please contact us immediately.</p>

            <h2 class="section-title">9. International Data Transfers</h2>
            <p>Your information may be transferred to and processed in countries other than your own. We ensure appropriate safeguards are in place to protect your information in accordance with this privacy policy.</p>

            <h2 class="section-title">10. Changes to This Policy</h2>
            <p>We may update this privacy policy from time to time. We will notify you of any material changes by posting the new policy on this page and updating the "Last updated" date.</p>

            <h2 class="section-title">11. Contact Us</h2>
            <p>If you have questions about this privacy policy or our data practices, please contact us:</p>
            <div class="highlight-box">
                <strong>Email:</strong> privacy@esportsplatform.com<br>
                <strong>Data Protection Officer:</strong> dpo@esportsplatform.com<br>
                <strong>Support Hours:</strong> Monday - Friday, 9:00 AM - 6:00 PM EST
            </div>

            <div class="text-center mt-5">
                <a href="index.php" class="btn btn-primary me-2">
                    <i class="fas fa-home me-2"></i>Back to Home
                </a>
                <a href="terms.php" class="btn btn-outline-primary">
                    <i class="fas fa-file-contract me-2"></i>Terms of Service
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