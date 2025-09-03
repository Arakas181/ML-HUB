<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: login.php');
    exit();
}

// Create missing tables if they don't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS squads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) UNIQUE NOT NULL,
        mlbb_id VARCHAR(50) NOT NULL,
        leader_id INT NOT NULL,
        description TEXT,
        logo_url VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (leader_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS squad_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        squad_id INT NOT NULL,
        user_id INT NOT NULL,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (squad_id) REFERENCES squads(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_membership (squad_id, user_id)
    )");
} catch (PDOException $e) {
    // Tables might already exist, continue
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Handle squad leave request
if (isset($_POST['leave_squad'])) {
    try {
        $leaveSquad = $pdo->prepare("DELETE FROM squad_members WHERE user_id = ?");
        $leaveSquad->execute([$userId]);
        $successMessage = "You have successfully left your squad.";
    } catch (PDOException $e) {
        error_log("Error leaving squad: " . $e->getMessage());
        $errorMessage = "Failed to leave squad. Please try again.";
    }
}

// Fetch user-specific data
try {
    // Get user's squad information
    $userSquad = $pdo->prepare("
        SELECT s.*, u.username as leader_name, sm.joined_at
        FROM squads s 
        JOIN squad_members sm ON s.id = sm.squad_id 
        JOIN users u ON s.leader_id = u.id 
        WHERE sm.user_id = ?
    ");
    $userSquad->execute([$userId]);
    $squad = $userSquad->fetch();
    
    // Get squad members if user is in a squad
    $squadMembers = [];
    if ($squad) {
        $membersQuery = $pdo->prepare("
            SELECT u.id, u.username, u.role, sm.joined_at, 
                   CASE WHEN u.id = s.leader_id THEN 1 ELSE 0 END as is_leader
            FROM users u 
            JOIN squad_members sm ON u.id = sm.user_id 
            JOIN squads s ON sm.squad_id = s.id 
            WHERE sm.squad_id = ? 
            ORDER BY is_leader DESC, sm.joined_at ASC
        ");
        $membersQuery->execute([$squad['id']]);
        $squadMembers = $membersQuery->fetchAll();
    }
    
    // Get user's tournament participations
    $userTournaments = $pdo->prepare("
        SELECT t.*, tp.status as participation_status 
        FROM tournaments t 
        JOIN tournament_participants tp ON t.id = tp.tournament_id 
        WHERE tp.user_id = ? 
        ORDER BY t.start_date DESC
    ");
    $userTournaments->execute([$userId]);
    $tournaments = $userTournaments->fetchAll();
    
    // Get user's match history
    $userMatches = $pdo->prepare("
        SELECT m.*, t1.name as team1_name, t2.name as team2_name, t.name as tournament_name
        FROM matches m 
        JOIN teams t1 ON m.team1_id = t1.id 
        JOIN teams t2 ON m.team2_id = t2.id 
        JOIN tournaments t ON m.tournament_id = t.id 
        WHERE m.status = 'completed' 
        AND EXISTS (
            SELECT 1 FROM tournament_participants tp 
            WHERE tp.tournament_id = t.id AND tp.user_id = ?
        )
        ORDER BY m.scheduled_time DESC 
        LIMIT 10
    ");
    $userMatches->execute([$userId]);
    $matches = $userMatches->fetchAll();
    
    // Get upcoming matches for tournaments user is participating in
    $upcomingMatches = $pdo->prepare("
        SELECT m.*, t1.name as team1_name, t2.name as team2_name, t.name as tournament_name
        FROM matches m 
        JOIN teams t1 ON m.team1_id = t1.id 
        JOIN teams t2 ON m.team2_id = t2.id 
        JOIN tournaments t ON m.tournament_id = t.id 
        WHERE m.status = 'scheduled' 
        AND EXISTS (
            SELECT 1 FROM tournament_participants tp 
            WHERE tp.tournament_id = t.id AND tp.user_id = ?
        )
        ORDER BY m.scheduled_time ASC 
        LIMIT 5
    ");
    $upcomingMatches->execute([$userId]);
    $upcoming = $upcomingMatches->fetchAll();
    
    // Get user's achievements/stats
    $userStats = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT tp.tournament_id) as tournaments_joined,
            COUNT(DISTINCT CASE WHEN tp.status = 'winner' THEN tp.tournament_id END) as tournaments_won,
            COUNT(DISTINCT CASE WHEN tp.status = 'runner_up' THEN tp.tournament_id END) as tournaments_runner_up
        FROM tournament_participants tp 
        WHERE tp.user_id = ?
    ");
    $userStats->execute([$userId]);
    $stats = $userStats->fetch();
    
} catch (PDOException $e) {
    error_log("Error fetching user data: " . $e->getMessage());
    $squad = null;
    $squadMembers = [];
    $tournaments = [];
    $matches = [];
    $upcoming = [];
    $stats = ['tournaments_joined' => 0, 'tournaments_won' => 0, 'tournaments_runner_up' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - EsportsHub</title>
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
        
        .sidebar {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            color: var(--text-primary);
            height: fit-content;
            position: sticky;
            top: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        
        .sidebar h5 {
            font-family: 'Oxanium', sans-serif;
            font-weight: 700;
            color: var(--primary-color);
            text-shadow: 0 0 20px var(--glow-color);
        }
        
        .sidebar .nav-link {
            color: var(--text-secondary);
            padding: 15px 20px;
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
            border-radius: 10px;
            margin: 5px 10px;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: var(--primary-color);
            background: rgba(140, 82, 255, 0.1);
            border-left: 4px solid var(--primary-color);
            text-shadow: 0 0 10px var(--glow-color);
        }
        
        .sidebar .nav-link i {
            width: 25px;
        }
        
        .sidebar .nav-link.text-danger:hover {
            color: #dc3545 !important;
            background: rgba(220, 53, 69, 0.1);
            border-left: 4px solid #dc3545;
        }
        
        .stats-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            text-align: center;
            padding: 30px 20px;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stats-card::before {
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
        
        .stats-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(140, 82, 255, 0.2);
        }
        
        .stats-card:hover::before {
            opacity: 1;
        }
        
        .stats-number {
            font-family: 'Oxanium', sans-serif;
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
            z-index: 1;
        }
        
        .stats-label {
            font-size: 1rem;
            color: var(--text-secondary);
            font-weight: 500;
            position: relative;
            z-index: 1;
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
            background: linear-gradient(135deg, rgba(140, 82, 255, 0.05), rgba(255, 62, 133, 0.05));
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
        
        .card-body, .card-header {
            position: relative;
            z-index: 1;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border-bottom: 1px solid var(--border-color);
            border-radius: 20px 20px 0 0 !important;
        }
        
        .card-header h5 {
            font-family: 'Oxanium', sans-serif;
            font-weight: 700;
            margin: 0;
        }
        
        .tournament-card {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
        }
        
        .match-card {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
        }
        
        .achievement-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
            margin: 5px;
        }
        
        .badge-winner {
            background-color: #ffc107;
            color: #212529;
        }
        
        .badge-runner-up {
            background-color: #6c757d;
            color: white;
        }
        
        .badge-participant {
            background-color: #17a2b8;
            color: white;
        }
        
        .squad-leader {
            color: #ffc107;
            font-weight: bold;
        }
        
        .crown-icon {
            color: #ffc107;
            margin-right: 8px;
        }
        
        .leave-squad-btn {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        
        .leave-squad-btn:hover {
            background-color: #c82333;
            border-color: #bd2130;
            color: white;
        }
        
        .squad-card {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
        }
        
        .btn-light {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }
        
        .btn-light:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: var(--primary-color);
            color: var(--primary-color);
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
        
        .leave-squad-btn {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }
        
        .leave-squad-btn:hover {
            background: linear-gradient(135deg, #c82333, #bd2130);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.4);
            color: white;
        }
        
        .table {
            color: var(--text-primary);
        }
        
        .table th {
            color: var(--text-secondary);
            border-color: var(--border-color);
            font-weight: 600;
        }
        
        .table td {
            border-color: var(--border-color);
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(140, 82, 255, 0.1);
        }
        
        .badge {
            font-weight: 600;
            padding: 8px 12px;
            border-radius: 20px;
        }
        
        .crown-icon {
            color: #ffc107;
            margin-right: 8px;
            text-shadow: 0 0 10px rgba(255, 193, 7, 0.5);
        }
        
        .squad-leader {
            color: #ffc107;
            font-weight: bold;
            text-shadow: 0 0 10px rgba(255, 193, 7, 0.3);
        }
        
        .text-muted {
            color: var(--text-secondary) !important;
        }
        
        .modal-content {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 20px;
        }
        
        .modal-header {
            border-bottom: 1px solid var(--border-color);
        }
        
        .modal-footer {
            border-top: 1px solid var(--border-color);
        }
        
        .modal-title {
            color: var(--text-primary);
            font-family: 'Oxanium', sans-serif;
            font-weight: 700;
        }
        
        .btn-secondary {
            background: rgba(108, 117, 125, 0.8);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: rgba(108, 117, 125, 1);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
        }
        
        @media (max-width: 768px) {
            .stats-number {
                font-size: 2rem;
            }
            
            .sidebar {
                margin-bottom: 20px;
            }
            
            .card {
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
                    <div class="dropdown">
                        <a class="btn btn-outline-light dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($username); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="user_dashboard.php">Dashboard</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Left Sidebar -->
            <div class="col-lg-3 d-none d-lg-block">
                <div class="sidebar rounded p-3">
                    <h5 class="text-center mb-4">User Panel</h5>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="user_dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my_tournaments.php">
                                <i class="fas fa-trophy me-2"></i> My Tournaments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my_matches.php">
                                <i class="fas fa-gamepad me-2"></i> My Matches
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_matches.php">
                                <i class="fas fa-video me-2"></i> Watch Matches
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="fas fa-user me-2"></i> Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="fas fa-cog me-2"></i> Settings
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link text-danger" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="col-lg-9">
                <!-- Welcome Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center">
                                <h2 class="card-title">Welcome back, <?php echo htmlspecialchars($username); ?>!</h2>
                                <p class="card-text">Track your tournament progress, view match history, and manage your esports journey.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Squad Section -->
                <?php if (isset($successMessage)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $successMessage; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($errorMessage)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $errorMessage; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header squad-card">
                        <h5 class="mb-0"><i class="fas fa-users me-2"></i>My Squad</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($squad): ?>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <h6><strong>Squad Name:</strong> <?php echo htmlspecialchars($squad['name']); ?></h6>
                                    <p><strong>MLBB ID:</strong> <?php echo htmlspecialchars($squad['mlbb_id']); ?></p>
                                    <p><strong>Squad Leader:</strong> <?php echo htmlspecialchars($squad['leader_name']); ?></p>
                                    <p><small class="text-muted">Joined: <?php echo date('M j, Y', strtotime($squad['joined_at'])); ?></small></p>
                                </div>
                                <div class="col-md-6 text-end">
                                    <button type="button" class="btn leave-squad-btn" data-bs-toggle="modal" data-bs-target="#leaveSquadModal">
                                        <i class="fas fa-sign-out-alt me-2"></i>Leave Squad
                                    </button>
                                </div>
                            </div>
                            
                            <h6 class="mb-3">Squad Members (<?php echo count($squadMembers); ?>)</h6>
                            <div class="row">
                                <?php foreach ($squadMembers as $member): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="d-flex align-items-center">
                                        <?php if ($member['is_leader']): ?>
                                            <i class="fas fa-crown crown-icon"></i>
                                            <span class="squad-leader"><?php echo htmlspecialchars($member['username']); ?></span>
                                            <small class="text-muted ms-2">(Leader)</small>
                                        <?php else: ?>
                                            <i class="fas fa-user me-2"></i>
                                            <span><?php echo htmlspecialchars($member['username']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">Joined: <?php echo date('M j, Y', strtotime($member['joined_at'])); ?></small>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h6>You're not part of any squad</h6>
                                <p class="text-muted">Join a squad to team up with other players and participate in squad tournaments!</p>
                                <a href="manage_matches.php" class="btn btn-primary">
                                    <i class="fas fa-gamepad me-2"></i>Watch Matches
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Stats Overview -->
                <div class="row mb-4">
                    <div class="col-md-4 col-6">
                        <div class="card stats-card">
                            <div class="stats-number"><?php echo $stats['tournaments_joined']; ?></div>
                            <div class="stats-label">Tournaments Joined</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="card stats-card">
                            <div class="stats-number"><?php echo $stats['tournaments_won']; ?></div>
                            <div class="stats-label">Tournaments Won</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="card stats-card">
                            <div class="stats-number"><?php echo $stats['tournaments_runner_up']; ?></div>
                            <div class="stats-label">Runner Up</div>
                        </div>
                    </div>
                </div>

                <!-- My Tournaments -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>My Tournaments</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($tournaments)): ?>
                            <div class="row">
                                <?php foreach ($tournaments as $tournament): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card tournament-card">
                                        <div class="card-body">
                                            <h6 class="card-title"><?php echo htmlspecialchars($tournament['name']); ?></h6>
                                            <p class="card-text">
                                                <small>Start Date: <?php echo date('M j, Y', strtotime($tournament['start_date'])); ?></small>
                                            </p>
                                            <div class="mb-2">
                                                <?php 
                                                $statusClass = '';
                                                $statusText = '';
                                                switch($tournament['participation_status']) {
                                                    case 'winner':
                                                        $statusClass = 'badge-winner';
                                                        $statusText = 'Winner';
                                                        break;
                                                    case 'runner_up':
                                                        $statusClass = 'badge-runner-up';
                                                        $statusText = 'Runner Up';
                                                        break;
                                                    default:
                                                        $statusClass = 'badge-participant';
                                                        $statusText = 'Participant';
                                                }
                                                ?>
                                                <span class="achievement-badge <?php echo $statusClass; ?>">
                                                    <?php echo $statusText; ?>
                                                </span>
                                            </div>
                                            <a href="tournament_details.php?id=<?php echo $tournament['id']; ?>" class="btn btn-light btn-sm">View Details</a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <p class="mb-0">You haven't joined any tournaments yet.</p>
                                <a href="tournaments.php" class="btn btn-primary mt-2">Browse Tournaments</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Upcoming Matches -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>Upcoming Matches</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($upcoming)): ?>
                            <div class="row">
                                <?php foreach ($upcoming as $match): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card match-card">
                                        <div class="card-body">
                                            <h6 class="card-title"><?php echo htmlspecialchars($match['team1_name']); ?> vs <?php echo htmlspecialchars($match['team2_name']); ?></h6>
                                            <p class="card-text">
                                                <small>Tournament: <?php echo htmlspecialchars($match['tournament_name']); ?></small><br>
                                                <small>Date: <?php echo date('M j, Y g:i A', strtotime($match['scheduled_time'])); ?></small>
                                            </p>
                                            <a href="match_details.php?id=<?php echo $match['id']; ?>" class="btn btn-light btn-sm">View Details</a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <p class="mb-0">No upcoming matches for your tournaments.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Match History -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Match History</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($matches)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Teams</th>
                                            <th>Tournament</th>
                                            <th>Date</th>
                                            <th>Result</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($matches as $match): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($match['team1_name']); ?></strong> vs 
                                                <strong><?php echo htmlspecialchars($match['team2_name']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($match['tournament_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($match['scheduled_time'])); ?></td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo $match['score_team1'] . ' - ' . $match['score_team2']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <p class="mb-0">No match history available yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leave Squad Confirmation Modal -->
    <div class="modal fade" id="leaveSquadModal" tabindex="-1" aria-labelledby="leaveSquadModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="leaveSquadModalLabel">Leave Squad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you really sure you want to leave your squad?</p>
                    <p class="text-muted">This action cannot be undone. You will need to apply to join another squad.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, Stay</button>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="leave_squad" class="btn btn-danger">Yes, Leave Squad</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Create particles
            createParticles();
            
            // Add any interactive features here
            $('.card').hover(
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
        // Initialize chat for user dashboard
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