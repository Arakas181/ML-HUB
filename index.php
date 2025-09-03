<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ML HUB - Esports Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Oxanium:wght@400;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #8c52ff;
            --primary-gradient: linear-gradient(135deg, #8c52ff 0%, #5ce1e6 100%);
            --secondary-color: #20c997;
            --accent-color: #ff3e85;
            --dark-color: #121212;
            --darker-color: #0a0a0a;
            --light-color: #f8f9fa;
            --card-bg: rgba(25, 25, 35, 0.85);
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background: var(--darker-color);
            min-height: 100vh;
            color: white;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Animated background elements */
        .bg-grid {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
            opacity: 0.15;
            background: 
                linear-gradient(rgba(92, 225, 230, 0.1) 1px, transparent 1px),
                linear-gradient(90deg, rgba(92, 225, 230, 0.1) 1px, transparent 1px);
            background-size: 40px 40px;
        }
        
        .floating-particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }
        
        .particle {
            position: absolute;
            border-radius: 50%;
            background: var(--primary-gradient);
            opacity: 0.2;
            animation: float 15s infinite linear;
        }
        
        @keyframes float {
            0% {
                transform: translateY(0) translateX(0) rotate(0deg);
            }
            100% {
                transform: translateY(-100vh) translateX(100px) rotate(360deg);
            }
        }
        
        /* Navigation */
        .navbar {
            background: rgba(25, 25, 35, 0.9);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
            padding: 15px 0;
        }
        
        .navbar-brand {
            font-family: 'Oxanium', cursive;
            font-weight: 700;
            color: #5ce1e6 !important;
            font-size: 1.8rem;
        }
        
        .navbar-brand i {
            color: #ffeb3b;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
            transition: all 0.3s;
            position: relative;
            padding: 8px 15px !important;
            border-radius: 5px;
            margin: 0 5px;
        }
        
        .nav-link:hover, .nav-link.active {
            color: white !important;
            background: rgba(140, 82, 255, 0.2);
        }
        
        .nav-link:hover::after, .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 15px;
            right: 15px;
            height: 2px;
            background: var(--primary-gradient);
            border-radius: 2px;
        }
        
        .btn-primary {
            background-color: #3ea6ff !important;
            border: none !important;
            border-radius: 20px !important;
            padding: 10px 20px !important;
            font-weight: 600 !important;
        }

        .btn-primary:hover {
            background-color: #2b619c !important;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(62, 166, 255, 0.3);
        }
        
        /* Hero Section */
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('https://images.unsplash.com/photo-1542751371-adc38448a05e?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1170&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 120px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--primary-gradient);
            opacity: 0.3;
            z-index: -1;
        }
        
        .hero-section h1 {
            font-family: 'Oxanium', cursive;
            font-weight: 700;
            font-size: 3.5rem;
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.5);
            margin-bottom: 20px;
        }
        
        .hero-section p {
            font-size: 1.2rem;
            max-width: 600px;
            margin: 0 auto 30px;
            opacity: 0.9;
        }
        
        /* Cards */
        .card {
            background: var(--card-bg);
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            color: white;
            overflow: hidden;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
        }
        
        .card-header {
            background: var(--primary-gradient);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 20px;
            font-family: 'Oxanium', cursive;
            font-weight: 600;
        }
        
        .card-body {
            padding: 25px;
        }
        
        /* Stream Container */
        .stream-container {
            position: relative;
            padding-bottom: 56.25%; /* 16:9 Aspect Ratio */
            height: 0;
            overflow: hidden;
            border-radius: 8px;
        }
        
        .stream-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 0;
            border-radius: 8px;
        }
        
        /* Chat Messages */
        .chat-messages {
            height: 400px;
            overflow-y: auto;
            padding: 15px;
            background-color: rgba(26, 26, 26, 0.9);
            border-radius: 0;
        }
        
        .chat-message {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 8px;
            background-color: rgba(255, 255, 255, 0.05);
        }
        
        .chat-message.self {
            background-color: rgba(92, 225, 230, 0.1);
            border-left: 3px solid #5ce1e6;
        }
        
        .chat-username {
            font-weight: bold;
            color: #5ce1e6;
        }
        
        .chat-timestamp {
            font-size: 0.8em;
            color: rgba(255, 255, 255, 0.6);
            margin-left: 10px;
        }
        
        .chat-text {
            margin-top: 5px;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .role-icon {
            margin-right: 5px;
        }
        
        /* Poll Styles */
        .poll-option {
            background-color: rgba(255, 255, 255, 0.05);
            padding: 10px;
            border-radius: 6px;
        }
        
        .vote-btn {
            margin-bottom: 5px;
            border-color: rgba(92, 225, 230, 0.3);
        }
        
        .vote-btn:hover {
            background-color: rgba(92, 225, 230, 0.1);
            border-color: #5ce1e6;
        }
        
        /* Footer */
        footer {
            background: rgba(25, 25, 35, 0.9);
            color: white;
            padding: 50px 0 20px;
            margin-top: 50px;
            backdrop-filter: blur(10px);
        }
        
        footer h5 {
            color: #5ce1e6;
            margin-bottom: 20px;
            font-family: 'Oxanium', cursive;
        }
        
        footer a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        footer a:hover {
            color: #5ce1e6;
            text-decoration: underline;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .hero-section {
                padding: 80px 0;
            }
            
            .hero-section h1 {
                font-size: 2.5rem;
            }
        }
        
        /* Utility Classes */
        .text-gradient {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div class="floating-particles" id="particles"></div>
    
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
                        <a class="nav-link active" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_matches.php">Matches</a>
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
                    <?php if (isLoggedIn()): ?>
                        <div class="dropdown">
                            <a class="btn btn-outline-light dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars(getUsername()); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                                <?php if (getUserRole() === 'admin' || getUserRole() === 'super_admin'): ?>
                                    <li><a class="dropdown-item" href="admin_dashboard.php">Admin Dashboard</a></li>
                                <?php elseif (getUserRole() === 'user'): ?>
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
            <h1 class="display-4 fw-bold mb-4">The Ultimate Esports Experience with ML HUB</h1>
            <p class="lead mb-4">Join tournaments, compete with teams, and connect with the gaming community</p>
            <a href="matches.php" class="btn btn-primary btn-lg me-2"><i class="fas fa-gamepad me-1"></i> View Matches</a>
            <a href="tournaments.php" class="btn btn-outline-light btn-lg"><i class="fas fa-trophy me-1"></i> Join Tournament</a>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container mt-5">
        <div class="row">
            <!-- Main Content Area -->
            <div class="col-12">
                <!-- Live Matches Stream Section -->
                <?php
                // Fetch live matches with YouTube video links
                try {
                    $liveMatches = $pdo->query("
                        SELECT m.*, t1.name as team1_name, t2.name as team2_name, 
                               tour.name as tournament_name
                        FROM matches m
                        JOIN teams t1 ON m.team1_id = t1.id
                        JOIN teams t2 ON m.team2_id = t2.id
                        JOIN tournaments tour ON m.tournament_id = tour.id
                        WHERE m.status IN ('live', 'scheduled') 
                        AND m.video_url IS NOT NULL 
                        AND m.video_url != ''
                        ORDER BY 
                            CASE WHEN m.status = 'live' THEN 1 ELSE 2 END,
                            m.scheduled_time ASC
                        LIMIT 1
                    ")->fetch(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    $liveMatches = null;
                }
                ?>
                
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fab fa-youtube me-2"></i>Live Stream</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($liveMatches): ?>
                            <?php
                            // Extract YouTube video ID from URL
                            $videoUrl = $liveMatches['video_url'];
                            $videoId = '';
                            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $videoUrl, $matches)) {
                                $videoId = $matches[1];
                            }
                            ?>
                            <div class="stream-container">
                                <iframe src="https://www.youtube.com/embed/<?php echo htmlspecialchars($videoId); ?>?rel=0&autoplay=0" allowfullscreen></iframe>
                            </div>
                            
                            <div class="p-3">
                                <h5><?php echo htmlspecialchars($liveMatches['team1_name'] . ' vs ' . $liveMatches['team2_name']); ?></h5>
                                <p><?php echo htmlspecialchars($liveMatches['tournament_name']); ?> 
                                   <?php if (!empty($liveMatches['round'])): ?>
                                       - <?php echo htmlspecialchars($liveMatches['round']); ?>
                                   <?php endif; ?>
                                </p>
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <span class="badge <?php echo $liveMatches['status'] === 'live' ? 'bg-danger' : 'bg-warning'; ?> me-2">
                                            <?php echo $liveMatches['status'] === 'live' ? 'LIVE' : 'SCHEDULED'; ?>
                                        </span>
                                        <span class="text-muted">
                                            <?php echo date('M j, Y g:i A', strtotime($liveMatches['scheduled_time'])); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <a href="matches.php" class="btn btn-outline-danger btn-sm me-2">
                                            <i class="fas fa-play me-1"></i> Full Screen
                                        </a>
                                        <a href="streaming_hub.php" class="btn btn-outline-secondary btn-sm">
                                            <i class="fas fa-th me-1"></i> All Streams
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="stream-container">
                                <div class="d-flex align-items-center justify-content-center h-100 bg-dark">
                                    <div class="text-center text-muted">
                                        <i class="fas fa-video fa-3x mb-3"></i>
                                        <h5>No Live Streams</h5>
                                        <p>Check back later for live matches</p>
                                        <a href="streaming_hub.php" class="btn btn-outline-light">
                                            <i class="fas fa-play me-1"></i> View All Streams
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- YouTube Style Video Chat System -->
                <div class="row">
                    <!-- Video Section -->
                    <div class="col-lg-8">
                        <!-- Video Info -->
                        <div class="video-info">
                            <h1 class="video-title">Demo Tournament Stream</h1>
                            <div class="video-stats">
                                <span>1,234 viewers</span> • <span>Demo Match</span> • <span class="badge bg-warning">DEMO</span>
                            </div>
                        </div>
                        
                        <div class="divider"></div>
                        
                        <!-- Polls Widget -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-poll me-2 text-info"></i>Community Poll</h5>
                            </div>
                            <div class="card-body">
                                <h6>Which team will win the tournament?</h6>
                                <div class="progress mb-2">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 65%">Team A (65%)</div>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-info" role="progressbar" style="width: 35%">Team B (35%)</div>
                                </div>
                                <button class="btn btn-primary mt-3">Vote Now</button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Chat Section -->
                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fab fa-youtube me-2 text-danger"></i>Video Chat</h5>
                                <div class="chat-controls">
                                    <small class="text-muted">1,234 viewers</small>
                                </div>
                            </div>
                            
                            <!-- Chat Messages -->
                            <div id="chat-messages" class="chat-messages">
                                <?php if (!isLoggedIn()): ?>
                                <div class="d-flex align-items-center justify-content-center h-100">
                                    <div class="text-center">
                                        <i class="fas fa-sign-in-alt fa-3x mb-3 text-muted"></i>
                                        <h6>Join the Conversation</h6>
                                        <p class="text-muted">Login to chat about this video</p>
                                        <a href="login.php" class="btn btn-primary btn-sm">Login</a>
                                        <a href="register.php" class="btn btn-outline-light btn-sm ms-2">Register</a>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (isLoggedIn()): ?>
                            <!-- Chat Input -->
                            <div class="card-footer">
                                <div class="input-group">
                                    <input type="text" id="message-input" class="form-control" placeholder="Type your message..." maxlength="500">
                                    <button id="send-button" class="btn btn-danger" type="button">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Live Polls Widget -->
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="fas fa-poll-h me-2"></i>Live Poll</h6>
                                </div>
                                <div class="card-body">
                                    <div id="polls-widget-container">
                                        <div class="text-center py-3">
                                            <i class="fas fa-poll-h fa-2x mb-2 text-muted"></i>
                                            <p class="text-muted">Loading polls...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- Latest News Section -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-newspaper me-2"></i>Latest News</h5>
                        <a href="news.php" class="btn btn-sm btn-outline-light">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="card h-100 bg-dark">
                                    <img src="https://via.placeholder.com/300x150" class="card-img-top" alt="News image">
                                    <div class="card-body">
                                        <h6 class="card-title">Winter Championship Announcement</h6>
                                        <p class="card-text small">Registration for the Winter Championship is now open with a $50,000 prize pool.</p>
                                        <a href="#" class="btn btn-sm btn-outline-light">Read More</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100 bg-dark">
                                    <img src="https://via.placeholder.com/300x150" class="card-img-top" alt="News image">
                                    <div class="card-body">
                                        <h6 class="card-title">New Hero Release</h6>
                                        <p class="card-text small">The latest hero has been released with amazing abilities that will change the meta.</p>
                                        <a href="#" class="btn btn-sm btn-outline-light">Read More</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100 bg-dark">
                                    <img src="https://via.placeholder.com/300x150" class="card-img-top" alt="News image">
                                    <div class="card-body">
                                        <h6 class="card-title">Patch Notes 1.7.32</h6>
                                        <p class="card-text small">Balance changes and bug fixes in the latest update. See what's changed.</p>
                                        <a href="#" class="btn btn-sm btn-outline-light">Read More</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5>ML HUB</h5>
                    <p>The ultimate platform for Mobile Legends esports enthusiasts. Join tournaments, watch matches, and connect with the community.</p>
                </div>
                <div class="col-md-2 mb-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="matches.php">Matches</a></li>
                        <li><a href="tournaments.php">Tournaments</a></li>
                        <li><a href="guides.php">Guides</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4">
                    <h5>Legal</h5>
                    <ul class="list-unstyled">
                        <li><a href="#">Terms of Service</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Cookie Policy</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4">
                    <h5>Connect With Us</h5>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                        <a href="#"><i class="fab fa-discord"></i></a>
                    </div>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p>&copy; 2023 ML HUB. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Create floating particles
        document.addEventListener('DOMContentLoaded', function() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 20;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 20 + 's';
                particle.style.animationDuration = `${Math.random() * 20 + 10}s`;
                
                particlesContainer.appendChild(particle);
            }
        });

        // Chat functionality
        $(document).ready(function() {
            <?php if (isLoggedIn()): ?>
            // Load chat messages
            function loadChatMessages() {
                $.get('chat_api.php?limit=50', function(response) {
                    if (response.success) {
                        $('#chat-messages').empty();
                        
                        if (response.messages.length === 0) {
                            $('#chat-messages').html(`
                                <div class="d-flex align-items-center justify-content-center h-100">
                                    <div class="text-center">
                                        <i class="fas fa-comments fa-3x mb-3 text-muted"></i>
                                        <h6>No messages yet</h6>
                                        <p class="text-muted">Be the first to start the conversation!</p>
                                    </div>
                                </div>
                            `);
                            return;
                        }
                        
                        response.messages.forEach(message => {
                            const messageClass = message.user_id === <?php echo getUserId() ?? 'null'; ?> ? 'self' : '';
                            const roleIcon = message.user_role === 'admin' || message.user_role === 'super_admin' 
                                ? '<i class="fas fa-shield-alt role-icon text-warning"></i>' 
                                : (message.user_role === 'squad_leader' ? '<i class="fas fa-crown role-icon text-info"></i>' : '');
                            
                            $('#chat-messages').append(`
                                <div class="chat-message ${messageClass}">
                                    <div>
                                        <span class="chat-username">${roleIcon} ${escapeHtml(message.username)}</span>
                                        <span class="chat-timestamp">${formatTime(message.timestamp)}</span>
                                    </div>
                                    <div class="chat-text">${escapeHtml(message.message)}</div>
                                </div>
                            `);
                        });
                        
                        // Scroll to bottom
                        $('#chat-messages').scrollTop($('#chat-messages')[0].scrollHeight);
                    }
                }).fail(function() {
                    console.error('Failed to load chat messages');
                });
            }
            
            // Send chat message
            $('#send-button').click(sendMessage);
            $('#message-input').keypress(function(e) {
                if (e.which === 13) {
                    sendMessage();
                }
            });
            
            function sendMessage() {
                const message = $('#message-input').val().trim();
                if (message === '') return;
                
                $.post('chat_api.php', JSON.stringify({ message: message }), function(response) {
                    if (response.success) {
                        $('#message-input').val('');
                        loadChatMessages();
                    } else {
                        alert('Error: ' + response.error);
                    }
                }).fail(function() {
                    alert('Failed to send message');
                });
            }
            
            // Initialize chat
            loadChatMessages();
            
            // Refresh chat every 5 seconds
            setInterval(loadChatMessages, 5000);
            <?php endif; ?>
            
            // Load polls
            function loadPolls() {
                $.get('polls_api.php', function(response) {
                    if (response.success && response.polls.length > 0) {
                        const poll = response.polls[0];
                        const pollContainer = $('#polls-widget-container');
                        
                        let pollHtml = `
                            <h6>${escapeHtml(poll.title)}</h6>
                            <p class="text-muted small">${escapeHtml(poll.description || '')}</p>
                            <div class="poll-options mt-3">
                        `;
                        
                        poll.options_with_votes.forEach((option, index) => {
                            const percentage = poll.total_votes > 0 
                                ? Math.round((option.votes / poll.total_votes) * 100) 
                                : 0;
                            
                            pollHtml += `
                                <div class="poll-option mb-2">
                                    <div class="d-flex justify-content-between">
                                        <span>${escapeHtml(option.text)}</span>
                                        <span>${percentage}%</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar" role="progressbar" style="width: ${percentage}%;"></div>
                                    </div>
                                    <small class="text-muted">${option.votes} votes</small>
                                </div>
                            `;
                        });
                        
                        pollHtml += `</div>`;
                        
                        if (!poll.user_has_voted && <?php echo isLoggedIn() ? 'true' : 'false'; ?>) {
                            pollHtml += `
                                <div class="mt-3">
                                    <h6 class="mb-2">Cast your vote:</h6>
                                    <div class="btn-group-vertical w-100" role="group">
                            `;
                            
                            poll.options.forEach((option, index) => {
                                pollHtml += `
                                    <button type="button" class="btn btn-outline-light text-start vote-btn" data-poll-id="${poll.id}" data-option-index="${index}">
                                        ${escapeHtml(option)}
                                    </button>
                                `;
                            });
                            
                            pollHtml += `</div></div>`;
                        } else if (poll.user_has_voted) {
                            pollHtml += `<div class="alert alert-info mt-3">You've already voted in this poll.</div>`;
                        } else {
                            pollHtml += `<div class="alert alert-warning mt-3">Login to vote in this poll.</div>`;
                        }
                        
                        pollContainer.html(pollHtml);
                        
                        // Add vote button handlers
                        $('.vote-btn').click(function() {
                            const pollId = $(this).data('poll-id');
                            const optionIndex = $(this).data('option-index');
                            
                            $.post('polls_api.php', JSON.stringify({
                                poll_id: pollId,
                                option_index: optionIndex
                            }), function(response) {
                                if (response.success) {
                                    loadPolls();
                                } else {
                                    alert('Error: ' + response.error);
                                }
                            }).fail(function() {
                                alert('Failed to submit vote');
                            });
                        });
                    } else {
                        $('#polls-widget-container').html(`
                            <div class="text-center py-3">
                                <i class="fas fa-poll-h fa-2x mb-2 text-muted"></i>
                                <p class="text-muted">No active polls at the moment</p>
                            </div>
                        `);
                    }
                }).fail(function() {
                    $('#polls-widget-container').html(`
                        <div class="alert alert-danger">Failed to load polls</div>
                    `);
                });
            }
            
            // Utility functions
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            function formatTime(timestamp) {
                const date = new Date(timestamp);
                return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }
            
            // Initialize polls
            loadPolls();
            
            // Refresh polls every 30 seconds
            setInterval(loadPolls, 30000);
        });
    </script>
</body>
</html>