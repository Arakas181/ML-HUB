<?php
session_start();
require_once 'config.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : 'user';
$username = $isLoggedIn ? $_SESSION['username'] : '';

// Fetch news and guides from database
try {
    $news = $pdo->query("
        SELECT c.*, u.username as author_name 
        FROM content c 
        JOIN users u ON c.author_id = u.id 
        WHERE c.status = 'published' AND c.type = 'news'
        ORDER BY c.published_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $guides = $pdo->query("
        SELECT c.*, u.username as author_name 
        FROM content c 
        JOIN users u ON c.author_id = u.id 
        WHERE c.status = 'published' AND c.type = 'guide'
        ORDER BY c.published_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $news = [];
    $guides = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News & Guides - Esports Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Oxanium:wght@300;400;600;700;800&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #8c52ff;
            --secondary-color: #20c997;
            --accent-color: #ff3e85;
            --dark-bg: #121212;
            --darker-bg: #0a0a0a;
            --card-bg: rgba(30, 30, 30, 0.8);
            --text-primary: #ffffff;
            --text-secondary: #b0b0b0;
            --border-color: rgba(140, 82, 255, 0.3);
            --glow-color: rgba(140, 82, 255, 0.4);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, var(--dark-bg) 0%, var(--darker-bg) 100%);
            color: var(--text-primary);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }
        
        .bg-grid {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(140, 82, 255, 0.1) 1px, transparent 1px),
                linear-gradient(90deg, rgba(140, 82, 255, 0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            z-index: -2;
            animation: gridMove 20s linear infinite;
        }
        
        @keyframes gridMove {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }
        
        .floating-particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }
        
        .particle {
            position: absolute;
            background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
            border-radius: 50%;
            animation: float 15s infinite linear;
            opacity: 0.1;
        }
        
        @keyframes float {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 0.1;
            }
            90% {
                opacity: 0.1;
            }
            100% {
                transform: translateY(-100px) rotate(360deg);
                opacity: 0;
            }
        }
        
        .navbar {
            background: rgba(18, 18, 18, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 4px 20px rgba(140, 82, 255, 0.1);
        }
        
        .navbar-brand {
            font-family: 'Oxanium', sans-serif;
            font-weight: 800;
            font-size: 1.5rem;
            color: var(--primary-color) !important;
            text-shadow: 0 0 20px var(--glow-color);
        }
        
        .nav-link {
            color: var(--text-secondary) !important;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .nav-link:hover, .nav-link.active {
            color: var(--primary-color) !important;
            text-shadow: 0 0 10px var(--glow-color);
        }
        
        .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            transform: translateX(-50%);
            width: 30px;
            height: 2px;
            background: var(--primary-color);
            border-radius: 2px;
        }
        
        .btn-outline-light {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-outline-light:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            box-shadow: 0 0 20px var(--glow-color);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border: none;
            box-shadow: 0 4px 15px rgba(140, 82, 255, 0.3);
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(140, 82, 255, 0.4);
        }
        
        .card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(140, 82, 255, 0.1), rgba(255, 62, 133, 0.1));
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(140, 82, 255, 0.2);
        }
        
        .card:hover::before {
            opacity: 1;
        }
        
        .card-body {
            position: relative;
            z-index: 1;
        }
        
        .content-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: 1px solid rgba(140, 82, 255, 0.5);
        }
        
        .guide-card {
            background: linear-gradient(135deg, var(--accent-color), var(--primary-color));
            color: white;
            border: 1px solid rgba(255, 62, 133, 0.5);
        }
        
        .hero-section {
            background: linear-gradient(135deg, rgba(140, 82, 255, 0.8), rgba(255, 62, 133, 0.6)),
                        linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.6)),
                        url('https://images.unsplash.com/photo-1542751371-adc38448a05e?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1170&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
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
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(140, 82, 255, 0.1) 50%, transparent 70%);
            animation: shimmer 3s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .hero-section h1 {
            font-family: 'Oxanium', sans-serif;
            font-weight: 800;
            text-shadow: 0 0 30px rgba(140, 82, 255, 0.8);
            margin-bottom: 20px;
        }
        
        .hero-section .lead {
            font-size: 1.3rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
        }
        
        .alert {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 15px;
        }
        
        .alert-info {
            border-color: rgba(32, 201, 151, 0.3);
            background: rgba(32, 201, 151, 0.1);
        }
        
        h2 {
            font-family: 'Oxanium', sans-serif;
            font-weight: 700;
            color: var(--primary-color);
            text-shadow: 0 0 20px var(--glow-color);
        }
        
        footer {
            background: var(--card-bg) !important;
            backdrop-filter: blur(20px);
            border-top: 1px solid var(--border-color);
            color: var(--text-secondary) !important;
        }
        
        @media (max-width: 768px) {
            .hero-section {
                padding: 80px 0;
            }
            
            .hero-section h1 {
                font-size: 2.5rem;
            }
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
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="matches.php">Matches</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="tournaments.php">Tournaments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="news.php">News</a>
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
            <h1 class="display-4 fw-bold mb-4">Latest News & Guides</h1>
            <p class="lead mb-4">Stay updated with the latest esports news and improve your skills with our guides</p>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container mt-5">
        <!-- News Section -->
        <div class="row mb-5">
            <div class="col-12">
                <h2 class="mb-4"><i class="fas fa-newspaper me-2"></i>Latest News</h2>
            </div>
            <?php if (empty($news)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No news articles available at the moment.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($news as $article): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card content-card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($article['title']); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars(substr($article['content'], 0, 150)) . '...'; ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-light">
                                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($article['author_name']); ?>
                                    </small>
                                    <small class="text-light">
                                        <i class="fas fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($article['published_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Guides Section -->
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4"><i class="fas fa-book me-2"></i>Gaming Guides</h2>
            </div>
            <?php if (empty($guides)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No guides available at the moment.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($guides as $guide): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card guide-card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($guide['title']); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars(substr($guide['content'], 0, 150)) . '...'; ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-light">
                                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($guide['author_name']); ?>
                                    </small>
                                    <small class="text-light">
                                        <i class="fas fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($guide['published_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4 mt-5">
        <div class="container">
            <p>&copy; 2024 Esports Platform. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Create particles
            createParticles();
            
            // Create particles function
            function createParticles() {
                const particlesContainer = $('#particles');
                const particleCount = 15;
                
                for (let i = 0; i < particleCount; i++) {
                    const size = Math.random() * 20 + 10;
                    const posX = Math.random() * 100;
                    const posY = Math.random() * 100;
                    const animationDelay = Math.random() * 15;
                    const opacity = Math.random() * 0.2 + 0.1;
                    
                    const particle = $('<div class="particle"></div>').css({
                        width: size + 'px',
                        height: size + 'px',
                        left: posX + 'vw',
                        top: posY + 'vh',
                        opacity: opacity,
                        animationDelay: animationDelay + 's'
                    });
                    
                    particlesContainer.append(particle);
                }
            }
        });
    </script>
</body>
</html> 