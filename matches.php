<?php
session_start();
require_once 'config.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : 'user';
$username = $isLoggedIn ? $_SESSION['username'] : '';

// Ensure video_url column exists in matches table
$hasVideoUrlColumn = false;
try {
    $pdo->query("SELECT video_url FROM matches LIMIT 1");
    $hasVideoUrlColumn = true;
} catch (PDOException $e) {
    try {
        $pdo->exec("ALTER TABLE matches ADD COLUMN video_url VARCHAR(500) NULL AFTER round");
        $hasVideoUrlColumn = true;
    } catch (PDOException $e2) {
        error_log('Failed to add video_url column to matches: ' . $e2->getMessage());
    }
}

// Get filter parameters
$status = $_GET['status'] ?? '';
$tournament = $_GET['tournament'] ?? '';
$search = $_GET['search'] ?? '';

try {
    // Build query based on filters
    $whereConditions = [];
    $params = [];
    
    if ($status) {
        $whereConditions[] = "m.status = ?";
        $params[] = $status;
    }
    
    if ($tournament) {
        $whereConditions[] = "t.id = ?";
        $params[] = $tournament;
    }
    
    if ($search) {
        $whereConditions[] = "(t1.name LIKE ? OR t2.name LIKE ? OR t.name LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get matches with conditional video_url column
    $matchesQuery = "
        SELECT m.id, m.tournament_id, m.team1_id, m.team2_id, m.scheduled_time, 
               m.status, m.score_team1, m.score_team2, m.round, m.created_at" .
               ($hasVideoUrlColumn ? ", m.video_url" : "") . ",
               t1.name as team1_name, t2.name as team2_name, t.name as tournament_name,
               t1.logo_url as team1_logo, t2.logo_url as team2_logo
        FROM matches m 
        JOIN teams t1 ON m.team1_id = t1.id 
        JOIN teams t2 ON m.team2_id = t2.id 
        JOIN tournaments t ON m.tournament_id = t.id 
        $whereClause
        ORDER BY m.scheduled_time DESC
    ";
    
    $stmt = $pdo->prepare($matchesQuery);
    $stmt->execute($params);
    $matches = $stmt->fetchAll();
    
    // Get tournaments for filter
    $tournaments = $pdo->query("SELECT id, name FROM tournaments ORDER BY name")->fetchAll();
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $matches = [];
    $tournaments = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matches - EsportsHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
        
        .match-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
            margin-bottom: 30px;
            overflow: hidden;
            position: relative;
        }
        
        .match-card::before {
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
        
        .match-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(140, 82, 255, 0.2);
        }
        
        .match-card:hover::before {
            opacity: 1;
        }
        
        .match-card.live {
            border-left: 4px solid #dc3545;
            box-shadow: 0 8px 32px rgba(220, 53, 69, 0.3);
        }
        
        .match-card.scheduled {
            border-left: 4px solid #ffc107;
            box-shadow: 0 8px 32px rgba(255, 193, 7, 0.2);
        }
        
        .match-card.completed {
            border-left: 4px solid #28a745;
            box-shadow: 0 8px 32px rgba(40, 167, 69, 0.2);
        }
        
        .card-body {
            position: relative;
            z-index: 1;
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
        }
        
        .team-logo {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 50%;
        }
        
        .score-display {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .filter-section {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
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
        
        .form-control, .form-select {
            background: rgba(30, 30, 30, 0.8);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            background: rgba(30, 30, 30, 0.9);
            border-color: var(--primary-color);
            box-shadow: 0 0 20px rgba(140, 82, 255, 0.3);
            color: var(--text-primary);
        }
        
        .form-label {
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .btn-outline-primary {
            border-color: var(--primary-color);
            color: var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            box-shadow: 0 0 20px var(--glow-color);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            transition: all 0.3s ease;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.4);
        }
        
        .team-logo {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .team-logo:hover {
            border-color: var(--primary-color);
            box-shadow: 0 0 20px var(--glow-color);
        }
        
        .score-display {
            font-size: 2rem;
            font-weight: bold;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .badge {
            font-weight: 600;
            padding: 8px 12px;
            border-radius: 20px;
        }
        
        .text-muted {
            color: var(--text-secondary) !important;
        }
        
        @media (max-width: 768px) {
            .hero-section {
                padding: 80px 0;
            }
            
            .hero-section h1 {
                font-size: 2.5rem;
            }
            
            .filter-section {
                padding: 20px;
            }
            
            .match-card {
                margin-bottom: 20px;
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
                        <a class="nav-link active" href="matches.php">Matches</a>
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
            <h1 class="display-4 fw-bold mb-4">All Matches</h1>
            <p class="lead mb-4">Watch live matches, view results, and stay updated with the latest esports action</p>
        </div>
    </section>

    <div class="container mt-5">
        <!-- Filters Section -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Status</option>
                        <option value="scheduled" <?php echo $status === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                        <option value="live" <?php echo $status === 'live' ? 'selected' : ''; ?>>Live</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="tournament" class="form-label">Tournament</label>
                    <select class="form-select" id="tournament" name="tournament">
                        <option value="">All Tournaments</option>
                        <?php foreach ($tournaments as $t): ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo $tournament == $t['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Search teams or tournaments..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i> Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Matches List -->
        <div class="row">
            <?php if (!empty($matches)): ?>
                <?php foreach ($matches as $match): ?>
                    <div class="col-lg-6 col-xl-4">
                        <div class="card match-card <?php echo $match['status']; ?>">
                            <div class="card-body">
                                <!-- Match Header -->
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($match['round']); ?></span>
                                    <?php 
                                    $statusClass = '';
                                    $statusText = '';
                                    switch($match['status']) {
                                        case 'live':
                                            $statusClass = 'bg-danger';
                                            $statusText = 'LIVE';
                                            break;
                                        case 'scheduled':
                                            $statusClass = 'bg-warning text-dark';
                                            $statusText = 'SCHEDULED';
                                            break;
                                        case 'completed':
                                            $statusClass = 'bg-success';
                                            $statusText = 'COMPLETED';
                                            break;
                                        case 'cancelled':
                                            $statusClass = 'bg-secondary';
                                            $statusText = 'CANCELLED';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?> status-badge"><?php echo $statusText; ?></span>
                                </div>

                                <!-- Teams and Score -->
                                <div class="row align-items-center mb-3">
                                    <div class="col-4 text-center">
                                        <img src="<?php echo $match['team1_logo'] ?: 'https://placehold.co/60x60'; ?>" 
                                             class="team-logo mb-2" alt="<?php echo htmlspecialchars($match['team1_name']); ?>">
                                        <div class="fw-bold"><?php echo htmlspecialchars($match['team1_name']); ?></div>
                                    </div>
                                    <div class="col-4 text-center">
                                        <div class="score-display"><?php echo $match['score_team1'] . ' - ' . $match['score_team2']; ?></div>
                                        <small class="text-muted">VS</small>
                                    </div>
                                    <div class="col-4 text-center">
                                        <img src="<?php echo $match['team2_logo'] ?: 'https://placehold.co/60x60'; ?>" 
                                             class="team-logo mb-2" alt="<?php echo htmlspecialchars($match['team2_name']); ?>">
                                        <div class="fw-bold"><?php echo htmlspecialchars($match['team2_name']); ?></div>
                                    </div>
                                </div>

                                <!-- Tournament Info -->
                                <div class="text-center mb-3">
                                    <small class="text-muted">
                                        <i class="fas fa-trophy me-1"></i>
                                        <?php echo htmlspecialchars($match['tournament_name']); ?>
                                    </small>
                                </div>

                                <!-- Match Time -->
                                <div class="text-center mb-3">
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo date('M j, Y g:i A', strtotime($match['scheduled_time'])); ?>
                                    </small>
                                </div>

                                <!-- Action Buttons -->
                                <div class="d-grid gap-2">
                                    <?php if ($match['status'] === 'live'): ?>
                                        <a href="watch.php?id=<?php echo $match['id']; ?>" class="btn btn-danger">
                                            <i class="fas fa-play-circle me-1"></i> Watch Live
                                        </a>
                                    <?php elseif (!empty($match['video_url'])): ?>
                                        <a href="watch.php?id=<?php echo $match['id']; ?>" class="btn btn-danger">
                                            <i class="fab fa-youtube me-1"></i> Watch Video
                                        </a>
                                    <?php endif; ?>
                                    <a href="match_details.php?id=<?php echo $match['id']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-info-circle me-1"></i> Match Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h4>No matches found</h4>
                    <p class="text-muted">Try adjusting your filters or check back later for new matches.</p>
                    <a href="matches.php" class="btn btn-primary">Clear Filters</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Create particles
            createParticles();
            
            // Auto-submit form when filters change
            $('#status, #tournament').change(function() {
                $(this).closest('form').submit();
            });
            
            // Add hover effects
            $('.match-card').hover(
                function() { $(this).addClass('shadow-lg'); },
                function() { $(this).removeClass('shadow-lg'); }
            );
            
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
    <!-- Chat Integration -->
    <?php if ($isLoggedIn): ?>
    <script src="polling_chat_client.js"></script>
    <script>
        // Initialize chat for matches page
        document.addEventListener('DOMContentLoaded', function() {
            const userId = <?= $_SESSION['user_id'] ?>;
            const username = '<?= htmlspecialchars($_SESSION['username']) ?>';
            const userRole = '<?= htmlspecialchars($_SESSION['role']) ?>';
            
            const chatClient = new PollingChatClient('chat_api.php', 1, userId, username, userRole);
            // Chat UI can be added to specific containers as needed
        });
    </script>
    <?php endif; ?>
</body>
</html>