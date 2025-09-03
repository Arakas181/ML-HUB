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
    <title>FAQ - Esports Platform</title>
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
        
        .faq-category {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .faq-category-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            margin: 0;
        }
        
        .faq-item {
            border-bottom: 1px solid #dee2e6;
        }
        
        .faq-item:last-child {
            border-bottom: none;
        }
        
        .faq-header {
            background: var(--light-color);
            border: none;
            width: 100%;
            text-align: left;
            padding: 20px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        .faq-header:hover {
            background: #e9ecef;
        }
        
        .faq-header:not(.collapsed) {
            background: var(--primary-color);
            color: white;
        }
        
        .faq-body {
            padding: 20px;
            background: white;
        }
        
        .search-box {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .search-input {
            border-radius: 25px;
            padding: 15px 25px;
            border: 2px solid #dee2e6;
            transition: border-color 0.3s ease;
        }
        
        .search-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(111, 66, 193, 0.25);
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
            <h1 class="display-4 fw-bold mb-4">Frequently Asked Questions</h1>
            <p class="lead mb-4">Find quick answers to common questions about our platform</p>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container mt-5">
        <!-- Search Box -->
        <div class="search-box">
            <div class="text-center mb-4">
                <h3><i class="fas fa-search me-2"></i>Search FAQ</h3>
                <p class="text-muted">Can't find what you're looking for? Search our FAQ database</p>
            </div>
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="input-group">
                        <input type="text" class="form-control search-input" id="faqSearch" 
                               placeholder="Type your question here..." aria-label="Search FAQ">
                        <button class="btn btn-primary" type="button" onclick="searchFAQ()">
                            <i class="fas fa-search me-2"></i>Search
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- FAQ Categories -->
        <!-- Account & Registration -->
        <div class="faq-category">
            <h3 class="faq-category-header">
                <i class="fas fa-user me-2"></i>Account & Registration
            </h3>
            <div class="accordion" id="faqAccount">
                <div class="faq-item">
                    <button class="faq-header collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                        <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>How do I create an account?</h6>
                    </button>
                    <div id="faq1" class="collapse faq-body" data-bs-parent="#faqAccount">
                        <p>To create an account, click the "Register" button in the top navigation. Fill in your username, email, and password, then click "Create Account". You'll receive a confirmation email to verify your account.</p>
                        <p><strong>Requirements:</strong></p>
                        <ul>
                            <li>Username: 3-20 characters, letters and numbers only</li>
                            <li>Email: Must be a valid email address</li>
                            <li>Password: Minimum 8 characters with at least one uppercase letter and number</li>
                        </ul>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-header collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                        <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>I forgot my password. What should I do?</h6>
                    </button>
                    <div id="faq2" class="collapse faq-body" data-bs-parent="#faqAccount">
                        <p>Click the "Forgot Password?" link on the login page. Enter your email address and we'll send you a password reset link. Make sure to check your spam folder if you don't receive the email.</p>
                        <p><strong>Note:</strong> Password reset links expire after 1 hour for security reasons.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-header collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                        <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>Can I change my username?</h6>
                    </button>
                    <div id="faq3" class="collapse faq-body" data-bs-parent="#faqAccount">
                        <p>Currently, usernames cannot be changed after account creation. This is to maintain consistency in tournament records and user identification. If you need to change your username for a valid reason, please contact our support team.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tournaments -->
        <div class="faq-category">
            <h3 class="faq-category-header">
                <i class="fas fa-trophy me-2"></i>Tournaments
            </h3>
            <div class="accordion" id="faqTournaments">
                <div class="faq-item">
                    <button class="faq-header collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                        <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>How do I join a tournament?</h6>
                    </button>
                    <div id="faq4" class="collapse faq-body" data-bs-parent="#faqTournaments">
                        <p>Browse available tournaments on the Tournaments page. Click on a tournament to view details, then click "Join Tournament" if you meet the requirements. Make sure to read the tournament rules before joining.</p>
                        <p><strong>Common Requirements:</strong></p>
                        <ul>
                            <li>Verified account (email confirmed)</li>
                            <li>Meet minimum skill level requirements</li>
                            <li>Available during tournament dates</li>
                            <li>Agree to tournament rules and fair play policies</li>
                        </ul>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-header collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                        <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>Can I participate in tournaments as a team?</h6>
                    </button>
                    <div id="faq5" class="collapse faq-body" data-bs-parent="#faqTournaments">
                        <p>Yes! Many tournaments support team participation. You can either join an existing team or create your own. Team tournaments require all members to have verified accounts.</p>
                        <p><strong>Team Requirements:</strong></p>
                        <ul>
                            <li>All members must have verified accounts</li>
                            <li>Team captain must register the team</li>
                            <li>All members must confirm participation</li>
                            <li>Team size must match tournament requirements</li>
                        </ul>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-header collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">
                        <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>What happens if I need to withdraw from a tournament?</h6>
                    </button>
                    <div id="faq6" class="collapse faq-body" data-bs-parent="#faqTournaments">
                        <p>You can withdraw from a tournament up to 24 hours before it starts without penalty. Late withdrawals may result in temporary restrictions on future tournament participation. Contact tournament organizers immediately if you need to withdraw.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Matches -->
        <div class="faq-category">
            <h3 class="faq-category-header">
                <i class="fas fa-gamepad me-2"></i>Matches
            </h3>
            <div class="accordion" id="faqMatches">
                <div class="faq-item">
                    <button class="faq-header collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq7">
                        <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>How do matches work?</h6>
                    </button>
                    <div id="faq7" class="collapse faq-body" data-bs-parent="#faqMatches">
                        <p>Matches are scheduled between teams or players in tournaments. You'll receive notifications about upcoming matches. Make sure to be online at the scheduled time and follow the match rules.</p>
                        <p><strong>Match Process:</strong></p>
                        <ul>
                            <li>Check-in 15 minutes before match time</li>
                            <li>Join the designated game lobby</li>
                            <li>Follow match rules and fair play guidelines</li>
                            <li>Report results to tournament officials</li>
                        </ul>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-header collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq8">
                        <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>What happens if I miss a match?</h6>
                    </button>
                    <div id="faq8" class="collapse faq-body" data-bs-parent="#faqMatches">
                        <p>Missing a match may result in a forfeit. Contact tournament organizers as soon as possible if you can't attend. Some tournaments allow rescheduling under certain circumstances.</p>
                        <p><strong>Penalties:</strong></p>
                        <ul>
                            <li>First offense: Warning</li>
                            <li>Second offense: Temporary suspension</li>
                            <li>Repeated offenses: Tournament ban</li>
                        </ul>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-header collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq9">
                        <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>How are match results determined?</h6>
                    </button>
                    <div id="faq9" class="collapse faq-body" data-bs-parent="#faqMatches">
                        <p>Match results are typically determined by the game's built-in scoring system. Both players/teams must report their results, and discrepancies are resolved by tournament officials using game replays or screenshots.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Platform & Technical -->
        <div class="faq-category">
            <h3 class="faq-category-header">
                <i class="fas fa-cog me-2"></i>Platform & Technical
            </h3>
            <div class="accordion" id="faqTechnical">
                <div class="faq-item">
                    <button class="faq-header collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq10">
                        <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>Is the platform mobile-friendly?</h6>
                    </button>
                    <div id="faq10" class="collapse faq-body" data-bs-parent="#faqTechnical">
                        <p>Yes! Our platform is fully responsive and works on all devices including smartphones and tablets. You can participate in tournaments, watch matches, and manage your account from any device.</p>
                        <p><strong>Mobile Features:</strong></p>
                        <ul>
                            <li>Responsive design for all screen sizes</li>
                            <li>Touch-friendly navigation</li>
                            <li>Mobile-optimized forms</li>
                            <li>Push notifications (if enabled)</li>
                        </ul>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-header collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq11">
                        <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>What browsers are supported?</h6>
                    </button>
                    <div id="faq11" class="collapse faq-body" data-bs-parent="#faqTechnical">
                        <p>Our platform supports all modern browsers including:</p>
                        <ul>
                            <li>Chrome (version 90+)</li>
                            <li>Firefox (version 88+)</li>
                            <li>Safari (version 14+)</li>
                            <li>Edge (version 90+)</li>
                        </ul>
                        <p>For the best experience, we recommend using the latest version of your preferred browser.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-header collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq12">
                        <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>How do you ensure fair play?</h6>
                    </button>
                    <div id="faq12" class="collapse faq-body" data-bs-parent="#faqTechnical">
                        <p>We have strict anti-cheat measures and fair play policies. All matches are monitored, and violations result in immediate disqualification and potential account suspension. Report any suspicious activity to our support team.</p>
                        <p><strong>Anti-Cheat Measures:</strong></p>
                        <ul>
                            <li>Game replay analysis</li>
                            <li>Player behavior monitoring</li>
                            <li>Community reporting system</li>
                            <li>Regular security audits</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Still Need Help Section -->
        <div class="text-center mt-5 mb-5">
            <h3>Still Need Help?</h3>
            <p class="lead">Can't find the answer you're looking for? Our support team is here to help!</p>
            <div class="d-flex justify-content-center gap-3">
                <a href="help.php" class="btn btn-outline-primary">
                    <i class="fas fa-question-circle me-2"></i>Help Center
                </a>
                <a href="contact.php" class="btn btn-primary">
                    <i class="fas fa-envelope me-2"></i>Contact Support
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

    <script>
        function searchFAQ() {
            const searchTerm = document.getElementById('faqSearch').value.toLowerCase();
            const faqItems = document.querySelectorAll('.faq-item');
            
            faqItems.forEach(item => {
                const question = item.querySelector('.faq-header h6').textContent.toLowerCase();
                const answer = item.querySelector('.faq-body').textContent.toLowerCase();
                
                if (question.includes(searchTerm) || answer.includes(searchTerm)) {
                    item.style.display = 'block';
                    // Expand matching items
                    const button = item.querySelector('.faq-header');
                    if (button.classList.contains('collapsed')) {
                        button.click();
                    }
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Show message if no results
            const visibleItems = document.querySelectorAll('.faq-item[style="display: block"]');
            if (visibleItems.length === 0 && searchTerm !== '') {
                alert('No FAQ items found matching your search. Try different keywords.');
            }
        }
        
        // Allow Enter key to trigger search
        document.getElementById('faqSearch').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchFAQ();
            }
        });
    </script>
</body>
</html> 