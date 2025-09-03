<?php
session_start();
require_once 'config.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : 'user';
$username = $isLoggedIn ? $_SESSION['username'] : '';

// Ensure previous_live_matches table exists
try {
    $pdo->query("SELECT 1 FROM previous_live_matches LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS previous_live_matches (
                id INT AUTO_INCREMENT PRIMARY KEY,
                match_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                video_url VARCHAR(500),
                viewer_count INT DEFAULT 0,
                ended_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
            )
        ");
    } catch (PDOException $e2) {
        error_log('Failed to create previous_live_matches table: ' . $e2->getMessage());
    }
}

// Get previous live matches
try {
    $stmt = $pdo->prepare("
        SELECT plm.*, m.team1_id, m.team2_id, m.round, m.scheduled_time,
               t1.name as team1_name, t2.name as team2_name, t.name as tournament_name
        FROM previous_live_matches plm
        JOIN matches m ON plm.match_id = m.id
        JOIN teams t1 ON m.team1_id = t1.id
        JOIN teams t2 ON m.team2_id = t2.id
        JOIN tournaments t ON m.tournament_id = t.id
        ORDER BY plm.ended_at DESC
    ");
    $stmt->execute();
    $previousMatches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error fetching previous matches: ' . $e->getMessage());
    $previousMatches = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Previous Live Matches - EsportsHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 80px 0;
            text-align: center;
        }
        
        .match-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }
        
        .match-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .match-header {
            background: linear-gradient(45deg, #dc3545, #fd7e14);
            color: white;
            padding: 15px;
        }
        
        .team-vs {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px 0;
        }
        
        .team {
            text-align: center;
            flex: 1;
        }
        
        .team img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin-bottom: 10px;
        }
        
        .vs-text {
            font-size: 2rem;
            font-weight: bold;
            color: #6c757d;
            margin: 0 20px;
        }
        
        .match-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 0 0 15px 15px;
        }
        
        .viewer-count {
            color: #dc3545;
            font-weight: bold;
        }
        
        .back-button {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">EsportsHub</a>
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
            <h1 class="display-4 fw-bold mb-4">Previous Live Matches</h1>
            <p class="lead mb-4">Relive the excitement of past live matches and tournaments</p>
            <a href="index.php" class="btn btn-light btn-lg back-button">
                <i class="fas fa-arrow-left me-2"></i>Back to Home
            </a>
        </div>
    </section>

    <div class="container mt-5">
        <?php if (!empty($previousMatches)): ?>
            <div class="row">
                <?php foreach ($previousMatches as $match): ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="card match-card">
                            <div class="match-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($match['round']); ?></span>
                                    <span class="badge bg-secondary">ENDED</span>
                                </div>
                                <h5 class="mt-2 mb-0"><?php echo htmlspecialchars($match['title'] ?: $match['team1_name'] . ' vs ' . $match['team2_name']); ?></h5>
                            </div>
                            
                            <div class="card-body">
                                <div class="team-vs">
                                    <div class="team">
                                        <img src="https://placehold.co/80x80" alt="<?php echo htmlspecialchars($match['team1_name']); ?>">
                                        <h6><?php echo htmlspecialchars($match['team1_name']); ?></h6>
                                    </div>
                                    <div class="vs-text">VS</div>
                                    <div class="team">
                                        <img src="https://placehold.co/80x80" alt="<?php echo htmlspecialchars($match['team2_name']); ?>">
                                        <h6><?php echo htmlspecialchars($match['team2_name']); ?></h6>
                                    </div>
                                </div>
                                
                                <?php if ($match['description']): ?>
                                    <p class="text-muted text-center mb-3"><?php echo htmlspecialchars($match['description']); ?></p>
                                <?php endif; ?>
                                
                                <div class="match-info">
                                    <div class="row text-center">
                                        <div class="col-6">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i><br>
                                                <?php echo date('M j, Y', strtotime($match['scheduled_time'])); ?>
                                            </small>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">
                                                <i class="fas fa-eye me-1"></i><br>
                                                <span class="viewer-count"><?php echo number_format($match['viewer_count']); ?></span> viewers
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <?php if ($match['video_url']): ?>
                                        <div class="text-center mt-3">
                                            <a href="<?php echo htmlspecialchars($match['video_url']); ?>" target="_blank" class="btn btn-outline-danger btn-sm">
                                                <i class="fab fa-youtube me-1"></i>Watch on YouTube
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-video-slash fa-3x text-muted mb-3"></i>
                <h4>No Previous Live Matches</h4>
                <p class="text-muted">Previous live matches will appear here once they are completed.</p>
                <a href="index.php" class="btn btn-primary">Go to Home</a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
