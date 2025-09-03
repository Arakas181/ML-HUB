<?php
session_start();
require_once 'config.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : 'user';
$username = $isLoggedIn ? $_SESSION['username'] : '';

// Handle contact form submission
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $category = $_POST['category'] ?? 'general';
    
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $errorMessage = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Please enter a valid email address.";
    } else {
        try {
            // In a real application, you would send an email here
            // For now, we'll just show a success message
            $successMessage = "Thank you for your message! We'll get back to you within 24 hours.";
            
            // Clear form data
            $name = $email = $subject = $message = '';
            $category = 'general';
        } catch (Exception $e) {
            $errorMessage = "An error occurred. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Esports Platform</title>
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
        
        .contact-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        
        .contact-info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(111, 66, 193, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 12px 30px;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background-color: #5a32a3;
            border-color: #5a32a3;
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
            <h1 class="display-4 fw-bold mb-4">Contact Us</h1>
            <p class="lead mb-4">Get in touch with our support team</p>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container mt-5">
        <div class="row">
            <!-- Contact Information -->
            <div class="col-lg-4 mb-4">
                <h3 class="mb-4"><i class="fas fa-info-circle me-2"></i>Get in Touch</h3>
                
                <div class="contact-info-card">
                    <i class="fas fa-envelope fa-2x mb-3"></i>
                    <h5>Email Support</h5>
                    <p class="mb-0">support@esportsplatform.com</p>
                    <small>Response within 24 hours</small>
                </div>
                
                <div class="contact-info-card">
                    <i class="fas fa-comments fa-2x mb-3"></i>
                    <h5>Live Chat</h5>
                    <p class="mb-0">Available during business hours</p>
                    <small>Monday - Friday, 9 AM - 6 PM EST</small>
                </div>
                
                <div class="contact-info-card">
                    <i class="fas fa-phone fa-2x mb-3"></i>
                    <h5>Phone Support</h5>
                    <p class="mb-0">+1 (555) 123-4567</p>
                    <small>Monday - Friday, 9 AM - 6 PM EST</small>
                </div>
                
                <div class="contact-info-card">
                    <i class="fas fa-map-marker-alt fa-2x mb-3"></i>
                    <h5>Office Address</h5>
                    <p class="mb-0">123 Gaming Street<br>Esports City, EC 12345</p>
                    <small>United States</small>
                </div>
            </div>
            
            <!-- Contact Form -->
            <div class="col-lg-8">
                <div class="contact-card">
                    <h3 class="mb-4"><i class="fas fa-paper-plane me-2"></i>Send us a Message</h3>
                    
                    <!-- Messages -->
                    <?php if ($successMessage): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($successMessage); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($errorMessage): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($errorMessage); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="subject" class="form-label">Subject *</label>
                                <input type="text" class="form-control" id="subject" name="subject" 
                                       value="<?php echo htmlspecialchars($subject ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="general" <?php echo ($category ?? 'general') === 'general' ? 'selected' : ''; ?>>General Inquiry</option>
                                    <option value="technical" <?php echo ($category ?? 'general') === 'technical' ? 'selected' : ''; ?>>Technical Support</option>
                                    <option value="tournament" <?php echo ($category ?? 'general') === 'tournament' ? 'selected' : ''; ?>>Tournament Support</option>
                                    <option value="billing" <?php echo ($category ?? 'general') === 'billing' ? 'selected' : ''; ?>>Billing & Payments</option>
                                    <option value="partnership" <?php echo ($category ?? 'general') === 'partnership' ? 'selected' : ''; ?>>Partnership & Business</option>
                                    <option value="other" <?php echo ($category ?? 'general') === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="message" class="form-label">Message *</label>
                            <textarea class="form-control" id="message" name="message" rows="6" 
                                      placeholder="Please describe your inquiry in detail..." required><?php echo htmlspecialchars($message ?? ''); ?></textarea>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Send Message
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Additional Information -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="contact-card">
                    <h4 class="mb-4"><i class="fas fa-clock me-2"></i>Response Times</h4>
                    <div class="row text-center">
                        <div class="col-md-3 mb-3">
                            <div class="p-3">
                                <i class="fas fa-bolt fa-2x text-warning mb-2"></i>
                                <h6>Urgent Issues</h6>
                                <p class="text-muted">2-4 hours</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="p-3">
                                <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                                <h6>Technical Support</h6>
                                <p class="text-muted">4-8 hours</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="p-3">
                                <i class="fas fa-question-circle fa-2x text-info mb-2"></i>
                                <h6>General Inquiries</h6>
                                <p class="text-muted">24 hours</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="p-3">
                                <i class="fas fa-lightbulb fa-2x text-success mb-2"></i>
                                <h6>Feature Requests</h6>
                                <p class="text-muted">48 hours</p>
                            </div>
                        </div>
                    </div>
                </div>
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