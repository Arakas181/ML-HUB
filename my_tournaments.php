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

// Fetch user's tournaments
try {
    // Get tournaments where user is participating
    $participatingTournaments = $pdo->query("
        SELECT t.*, tp.registration_date, tp.status as participation_status
        FROM tournaments t 
        JOIN tournament_participants tp ON t.id = tp.tournament_id 
        WHERE tp.user_id = $userId 
        ORDER BY t.start_date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user's tournament statistics
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total_tournaments,
            SUM(CASE WHEN tp.status = 'completed' THEN 1 ELSE 0 END) as completed_tournaments,
            SUM(CASE WHEN tp.status = 'ongoing' THEN 1 ELSE 0 END) as ongoing_tournaments,
            SUM(CASE WHEN tp.status = 'upcoming' THEN 1 ELSE 0 END) as upcoming_tournaments
        FROM tournament_participants tp 
        JOIN tournaments t ON tp.tournament_id = t.id 
        WHERE tp.user_id = $userId
    ")->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $participatingTournaments = [];
    $stats = ['total_tournaments' => 0, 'completed_tournaments' => 0, 'ongoing_tournaments' => 0, 'upcoming_tournaments' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tournaments - Esports Platform</title>
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
        
        .tournament-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            margin-bottom: 20px;
        }
        
        .tournament-card:hover {
            transform: translateY(-5px);
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
        }
        
        .status-upcoming { background-color: #17a2b8; }
        .status-ongoing { background-color: #28a745; }
        .status-completed { background-color: #6c757d; }
        .status-cancelled { background-color: #dc3545; }
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
            <h1><i class="fas fa-trophy me-2"></i>My Tournaments</h1>
            <a href="tournaments.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Join New Tournament
            </a>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h3 class="mb-0"><?php echo $stats['total_tournaments']; ?></h3>
                    <p class="mb-0">Total Tournaments</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h3 class="mb-0"><?php echo $stats['upcoming_tournaments']; ?></h3>
                    <p class="mb-0">Upcoming</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h3 class="mb-0"><?php echo $stats['ongoing_tournaments']; ?></h3>
                    <p class="mb-0">Ongoing</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h3 class="mb-0"><?php echo $stats['completed_tournaments']; ?></h3>
                    <p class="mb-0">Completed</p>
                </div>
            </div>
        </div>

        <!-- Tournaments List -->
        <?php if (empty($participatingTournaments)): ?>
            <div class="text-center py-5">
                <i class="fas fa-trophy fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No Tournaments Yet</h4>
                <p class="text-muted">You haven't joined any tournaments yet. Start your esports journey today!</p>
                <a href="tournaments.php" class="btn btn-primary">
                    <i class="fas fa-search me-2"></i>Browse Tournaments
                </a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($participatingTournaments as $tournament): ?>
                    <div class="col-lg-6 col-xl-4">
                        <div class="tournament-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="card-title mb-0"><?php echo htmlspecialchars($tournament['name']); ?></h5>
                                    <span class="badge status-<?php echo $tournament['status']; ?> status-badge">
                                        <?php echo ucfirst($tournament['status']); ?>
                                    </span>
                                </div>
                                
                                <p class="card-text text-muted">
                                    <?php echo htmlspecialchars(substr($tournament['description'], 0, 100)) . '...'; ?>
                                </p>
                                
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>Start Date
                                        </small>
                                        <div><?php echo date('M d, Y', strtotime($tournament['start_date'])); ?></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">
                                            <i class="fas fa-users me-1"></i>Participants
                                        </small>
                                        <div><?php echo $tournament['max_participants']; ?> max</div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <small class="text-muted">
                                            <i class="fas fa-gamepad me-1"></i>Game
                                        </small>
                                        <div><?php echo isset($tournament['game']) ? htmlspecialchars($tournament['game']) : 'Not specified'; ?></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>Registration
                                        </small>
                                        <div><?php echo date('M d, Y', strtotime($tournament['registration_date'])); ?></div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-info">
                                        <?php echo ucfirst($tournament['participation_status']); ?>
                                    </span>
                                    <a href="tournaments.php?id=<?php echo $tournament['id']; ?>" class="btn btn-outline-primary btn-sm">
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