<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$username = $_SESSION['username'];

// Fetch user's matches
try {
    // Get matches where user is participating
    $userMatches = $pdo->query("
        SELECT m.*, t.name as tournament_name, t1.name as team1_name, t2.name as team2_name
        FROM matches m 
        JOIN tournaments t ON m.tournament_id = t.id 
        JOIN teams t1 ON m.team1_id = t1.id 
        JOIN teams t2 ON m.team2_id = t2.id 
        WHERE m.team1_id IN (SELECT team_id FROM team_members WHERE user_id = $userId) 
           OR m.team2_id IN (SELECT team_id FROM team_members WHERE user_id = $userId)
        ORDER BY m.scheduled_time DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user's match statistics
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total_matches,
            SUM(CASE WHEN m.status = 'completed' THEN 1 ELSE 0 END) as completed_matches,
            SUM(CASE WHEN m.status = 'live' THEN 1 ELSE 0 END) as live_matches,
            SUM(CASE WHEN m.status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_matches
        FROM matches m 
        JOIN tournaments t ON m.tournament_id = t.id 
        JOIN teams t1 ON m.team1_id = t1.id 
        JOIN teams t2 ON m.team2_id = t2.id 
        WHERE m.team1_id IN (SELECT team_id FROM team_members WHERE user_id = $userId) 
           OR m.team2_id IN (SELECT team_id FROM team_members WHERE user_id = $userId)
    ")->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $userMatches = [];
    $stats = ['total_matches' => 0, 'completed_matches' => 0, 'live_matches' => 0, 'scheduled_matches' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Matches - Esports Platform</title>
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
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .match-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            margin-bottom: 20px;
        }
        
        .match-card:hover {
            transform: translateY(-5px);
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
        }
        
        .status-scheduled { background-color: #17a2b8; }
        .status-live { background-color: #28a745; }
        .status-completed { background-color: #6c757d; }
        .status-cancelled { background-color: #dc3545; }
        
        .team-vs {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .match-time {
            background: var(--light-color);
            border-radius: 10px;
            padding: 10px;
            text-align: center;
            margin: 15px 0;
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
                    <div class="dropdown">
                        <a class="btn btn-outline-light dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($username); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item active" href="user_dashboard.php">Dashboard</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-5">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-gamepad me-2"></i>My Matches</h1>
            <a href="matches.php" class="btn btn-primary">
                <i class="fas fa-search me-2"></i>Browse All Matches
            </a>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h3 class="mb-0"><?php echo $stats['total_matches']; ?></h3>
                    <p class="mb-0">Total Matches</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h3 class="mb-0"><?php echo $stats['scheduled_matches']; ?></h3>
                    <p class="mb-0">Scheduled</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h3 class="mb-0"><?php echo $stats['live_matches']; ?></h3>
                    <p class="mb-0">Live</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h3 class="mb-0"><?php echo $stats['completed_matches']; ?></h3>
                    <p class="mb-0">Completed</p>
                </div>
            </div>
        </div>

        <!-- Matches List -->
        <?php if (empty($userMatches)): ?>
            <div class="text-center py-5">
                <i class="fas fa-gamepad fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No Matches Yet</h4>
                <p class="text-muted">You haven't participated in any matches yet. Join a tournament to get started!</p>
                <a href="tournaments.php" class="btn btn-primary">
                    <i class="fas fa-trophy me-2"></i>Join Tournament
                </a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($userMatches as $match): ?>
                    <div class="col-lg-6 col-xl-4">
                        <div class="match-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h6 class="card-subtitle text-muted mb-0"><?php echo htmlspecialchars($match['tournament_name']); ?></h6>
                                    <span class="badge status-<?php echo $match['status']; ?> status-badge">
                                        <?php echo ucfirst($match['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="match-time">
                                    <i class="fas fa-clock me-2"></i>
                                    <?php echo date('M d, Y H:i', strtotime($match['scheduled_time'])); ?>
                                </div>
                                
                                <div class="row text-center mb-3">
                                    <div class="col-5">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($match['team1_name']); ?></h6>
                                        <small class="text-muted">Team 1</small>
                                    </div>
                                    <div class="col-2">
                                        <div class="team-vs">VS</div>
                                    </div>
                                    <div class="col-5">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($match['team2_name']); ?></h6>
                                        <small class="text-muted">Team 2</small>
                                    </div>
                                </div>
                                
                                <?php if ($match['status'] === 'completed' && isset($match['team1_score']) && isset($match['team2_score'])): ?>
                                    <div class="row text-center mb-3">
                                        <div class="col-5">
                                            <h4 class="mb-0"><?php echo $match['team1_score']; ?></h4>
                                        </div>
                                        <div class="col-2">
                                            <small class="text-muted">Score</small>
                                        </div>
                                        <div class="col-5">
                                            <h4 class="mb-0"><?php echo $match['team2_score']; ?></h4>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <small class="text-muted">
                                            <i class="fas fa-gamepad me-1"></i>Game
                                        </small>
                                        <div><?php echo htmlspecialchars($match['game'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker-alt me-1"></i>Format
                                        </small>
                                        <div><?php echo htmlspecialchars($match['format'] ?? 'N/A'); ?></div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-info">
                                        <?php echo ucfirst($match['status']); ?>
                                    </span>
                                    <a href="matches.php?id=<?php echo $match['id']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-eye me-1"></i>View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4 mt-5">
        <div class="container">
            <p>&copy; 2024 Esports Platform. All rights reserved.</p>
        </div>
    </footer>
</body>
</html> 