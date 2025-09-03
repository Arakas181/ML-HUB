<?php
session_start();
require_once 'config.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : 'user';
$username = $isLoggedIn ? $_SESSION['username'] : '';

// Get filter parameters
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Ensure tournament_participants table exists
try {
    $pdo->query("SELECT 1 FROM tournament_participants LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tournament_participants (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tournament_id INT NOT NULL,
                user_id INT NOT NULL,
                squad_name VARCHAR(255) NOT NULL,
                in_game_name VARCHAR(255) NOT NULL,
                status ENUM('registered', 'participant', 'winner', 'runner_up', 'eliminated') DEFAULT 'registered',
                registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_participation (tournament_id, user_id)
            )
        ");
    } catch (PDOException $e2) {
        error_log('Failed to create tournament_participants table: ' . $e2->getMessage());
    }
}

// Ensure teams table exists and has data
try {
    $teamCount = $pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn();
    if ($teamCount == 0) {
        // Insert sample teams if none exist
        $pdo->exec("
            INSERT INTO teams (name, description) VALUES
            ('Team Phoenix', 'Rising from the ashes'),
            ('Team Hydra', 'Multiple heads, multiple strategies'),
            ('Team Titans', 'Unstoppable force'),
            ('Team Warriors', 'Brave and fierce')
        ");
    }
} catch (PDOException $e) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS teams (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                logo_url VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        // Insert sample teams
        $pdo->exec("
            INSERT INTO teams (name, description) VALUES
            ('Team Phoenix', 'Rising from the ashes'),
            ('Team Hydra', 'Multiple heads, multiple strategies'),
            ('Team Titans', 'Unstoppable force'),
            ('Team Warriors', 'Brave and fierce')
        ");
    } catch (PDOException $e2) {
        error_log('Failed to create teams table: ' . $e2->getMessage());
    }
}

// Ensure tournaments table has data
try {
    $tournamentCount = $pdo->query("SELECT COUNT(*) FROM tournaments")->fetchColumn();
    if ($tournamentCount == 0) {
        // Insert sample tournaments if none exist
        $pdo->exec("
            INSERT INTO tournaments (name, description, start_date, end_date, status, prize_pool) VALUES
            ('MSC 2023', 'Mobile Legends Southeast Asia Cup 2023', '2023-12-01', '2023-12-15', 'ongoing', 100000.00),
            ('Winter Championship', 'Annual winter esports championship', '2024-01-15', '2024-02-01', 'upcoming', 50000.00)
        ");
    }
} catch (PDOException $e) {
    error_log('Failed to check/create tournaments: ' . $e->getMessage());
}

// Ensure matches table has data
try {
    $matchCount = $pdo->query("SELECT COUNT(*) FROM matches")->fetchColumn();
    if ($matchCount == 0) {
        // Insert sample matches if none exist
        $pdo->exec("
            INSERT INTO matches (tournament_id, team1_id, team2_id, scheduled_time, status, score_team1, score_team2, round) VALUES
            (1, 1, 2, '2023-12-10 14:00:00', 'live', 2, 1, 'Quarter Finals'),
            (1, 3, 4, '2023-12-10 16:00:00', 'scheduled', 0, 0, 'Quarter Finals')
        ");
    }
} catch (PDOException $e) {
    error_log('Failed to check/create matches: ' . $e->getMessage());
}

try {
    // Build query based on filters
    $whereConditions = [];
    $params = [];
    
    if ($status) {
        $whereConditions[] = "t.status = ?";
        $params[] = $status;
    }
    
    if ($search) {
        $whereConditions[] = "(t.name LIKE ? OR t.description LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get tournaments
    $tournamentsQuery = "
        SELECT t.*, 
               COUNT(tp.id) as participant_count,
               CASE WHEN ? > 0 THEN 
                   (SELECT COUNT(*) FROM tournament_participants WHERE tournament_id = t.id AND user_id = ?) > 0
               ELSE 0 END as is_registered
        FROM tournaments t 
        LEFT JOIN tournament_participants tp ON t.id = tp.tournament_id
        $whereClause
        GROUP BY t.id
        ORDER BY 
            CASE t.status 
                WHEN 'ongoing' THEN 1 
                WHEN 'upcoming' THEN 2 
                WHEN 'completed' THEN 3 
                ELSE 4 
            END,
            t.start_date ASC
    ";
    
    $stmt = $pdo->prepare($tournamentsQuery);
    $stmt->execute(array_merge([$isLoggedIn ? 1 : 0, $isLoggedIn ? $_SESSION['user_id'] : 0], $params));
    $tournaments = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $tournaments = [];
}

// Handle tournament registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn && isset($_POST['register_tournament'])) {
    // Check if user has permission to register (squad leader, admin, or super admin)
    if ($userRole !== 'squad_leader' && $userRole !== 'admin' && $userRole !== 'super_admin') {
        header("Location: tournaments.php?error=permission_denied");
        exit();
    }
    
    $tournamentId = $_POST['tournament_id'];
    $squadName = trim($_POST['squad_name']);
    $inGameName = trim($_POST['in_game_name']);
    
    // Validate required fields
    if (empty($squadName) || empty($inGameName)) {
        header("Location: tournaments.php?error=missing_fields");
        exit();
    }
    
    try {
        // Check if already registered
        $checkStmt = $pdo->prepare("SELECT id FROM tournament_participants WHERE tournament_id = ? AND user_id = ?");
        $checkStmt->execute([$tournamentId, $_SESSION['user_id']]);
        
        if (!$checkStmt->fetch()) {
            // Register user with squad name and in-game name
            $registerStmt = $pdo->prepare("INSERT INTO tournament_participants (tournament_id, user_id, squad_name, in_game_name, status) VALUES (?, ?, ?, ?, 'registered')");
            $registerStmt->execute([$tournamentId, $_SESSION['user_id'], $squadName, $inGameName]);
            
            header("Location: tournaments.php?success=registered");
            exit();
        } else {
            header("Location: tournaments.php?error=already_registered");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Registration error: " . $e->getMessage());
        header("Location: tournaments.php?error=registration_failed");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournaments - EsportsHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --primary-color: #8c52ff;
            --primary-gradient: linear-gradient(135deg, #8c52ff 0%, #5ce1e6 100%);
            --secondary-color: #20c997;
            --accent-color: #ff3e85;
            --dark-color: #121212;
            --darker-color: #0a0a0a;
            --light-color: #f8f9fa;
            --card-bg: rgba(25, 25, 35, 0.95);
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, var(--darker-color) 0%, #1a1a2e 100%);
            color: white;
            min-height: 100vh;
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
        
        .navbar {
            background: var(--card-bg) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .navbar-brand {
            font-family: 'Oxanium', cursive;
            font-weight: 700;
            color: #5ce1e6 !important;
            font-size: 1.5rem;
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
        
        .dropdown-menu {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .dropdown-item {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .dropdown-item:hover {
            background: rgba(140, 82, 255, 0.2);
            color: white;
        }
        
        .tournament-card {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            margin-bottom: 20px;
            overflow: hidden;
            color: white;
        }
        
        .tournament-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
        }
        
        .tournament-card.ongoing {
            border-left: 5px solid #ff3e85;
        }
        
        .tournament-card.upcoming {
            border-left: 5px solid #ffc107;
        }
        
        .tournament-card.completed {
            border-left: 5px solid #20c997;
        }
        
        .card {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            color: white;
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 8px 12px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .prize-pool {
            background: var(--primary-gradient);
            color: white;
            padding: 12px 20px;
            border-radius: 25px;
            font-weight: bold;
            text-align: center;
            box-shadow: 0 4px 15px rgba(140, 82, 255, 0.3);
        }
        
        .filter-section {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
        }
        
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), 
                        url('https://images.unsplash.com/photo-1542751371-adc38448a05e?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1170&q=80');
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
        
        .participant-count {
            background: rgba(140, 82, 255, 0.3);
            color: #5ce1e6;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            border: 1px solid rgba(140, 82, 255, 0.5);
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #7a45e0 0%, #4ec1c6 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(140, 82, 255, 0.3);
        }
        
        .btn-outline-primary {
            border: 2px solid rgba(140, 82, 255, 0.5);
            color: #8c52ff;
            background: transparent;
        }
        
        .btn-outline-primary:hover {
            background: rgba(140, 82, 255, 0.2);
            border-color: #8c52ff;
            color: white;
        }
        
        .btn-outline-light {
            border-color: rgba(255, 255, 255, 0.3);
            color: rgba(255, 255, 255, 0.8);
        }
        
        .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.5);
            color: white;
        }
        
        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            border-radius: 8px;
        }
        
        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: #8c52ff;
            box-shadow: 0 0 0 0.2rem rgba(140, 82, 255, 0.25);
            color: white;
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .form-label {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }
        
        .alert {
            border: none;
            border-radius: 8px;
            padding: 12px 16px;
            backdrop-filter: blur(10px);
        }
        
        .alert-success {
            background: rgba(32, 201, 151, 0.2);
            color: #20c997;
        }
        
        .alert-danger {
            background: rgba(220, 53, 69, 0.2);
            color: #ff7a7a;
        }
        
        .alert-warning {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }
        
        .modal-content {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .modal-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .btn-close {
            filter: invert(1);
        }
        
        /* Mobile Responsive */
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
                        <a class="nav-link active" href="tournaments.php">Tournaments</a>
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
                                <?php elseif ($userRole === 'squad_leader'): ?>
                                    <li><a class="dropdown-item" href="squad_leader_dashboard.php">Squad Leader Dashboard</a></li>
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
            <h1 class="display-4 fw-bold mb-4">Tournaments</h1>
            <p class="lead mb-4">Join exciting tournaments, compete with the best, and win amazing prizes</p>
        </div>
    </section>

    <div class="container mt-5">
        <!-- Alerts -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                switch($_GET['success']) {
                    case 'registered':
                        echo 'Successfully registered for the tournament!';
                        break;
                    default:
                        echo 'Action completed successfully.';
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php 
                switch($_GET['error']) {
                    case 'already_registered':
                        echo 'You are already registered for this tournament.';
                        break;
                    case 'registration_failed':
                        echo 'Registration failed. Please try again.';
                        break;
                    case 'missing_fields':
                        echo 'Squad name and In-Game name are required for registration.';
                        break;
                    case 'permission_denied':
                        echo 'Only squad leaders, admins, and super admins can register for tournaments.';
                        break;
                    default:
                        echo 'An error occurred. Please try again.';
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filters Section -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Status</option>
                        <option value="upcoming" <?php echo $status === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                        <option value="ongoing" <?php echo $status === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Search tournaments..." value="<?php echo htmlspecialchars($search); ?>">
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

        <!-- Tournaments List -->
        <div class="row">
            <?php if (!empty($tournaments)): ?>
                <?php foreach ($tournaments as $tournament): ?>
                    <div class="col-lg-6 col-xl-4">
                        <div class="card tournament-card <?php echo $tournament['status']; ?>">
                            <div class="card-body">
                                <!-- Tournament Header -->
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($tournament['round'] ?? 'Tournament'); ?></span>
                                    <?php 
                                    $statusClass = '';
                                    $statusText = '';
                                    switch($tournament['status']) {
                                        case 'ongoing':
                                            $statusClass = 'bg-danger';
                                            $statusText = 'ONGOING';
                                            break;
                                        case 'upcoming':
                                            $statusClass = 'bg-warning text-dark';
                                            $statusText = 'UPCOMING';
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

                                <!-- Tournament Name -->
                                <h5 class="card-title text-center mb-3"><?php echo htmlspecialchars($tournament['name']); ?></h5>
                                
                                <!-- Description -->
                                <p class="card-text text-muted text-center mb-3">
                                    <?php echo strlen($tournament['description']) > 100 ? 
                                          substr($tournament['description'], 0, 100) . '...' : 
                                          $tournament['description']; ?>
                                </p>

                                <!-- Prize Pool -->
                                <?php if ($tournament['prize_pool']): ?>
                                    <div class="prize-pool mb-3">
                                        <i class="fas fa-trophy me-2"></i>
                                        $<?php echo number_format($tournament['prize_pool'], 2); ?> Prize Pool
                                    </div>
                                <?php endif; ?>

                                <!-- Tournament Info -->
                                <div class="row text-center mb-3">
                                    <div class="col-6">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i><br>
                                            Start: <?php echo date('M j, Y', strtotime($tournament['start_date'])); ?>
                                        </small>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar-check me-1"></i><br>
                                            End: <?php echo date('M j, Y', strtotime($tournament['end_date'])); ?>
                                        </small>
                                    </div>
                                </div>

                                <!-- Participant Count -->
                                <div class="text-center mb-3">
                                    <span class="participant-count">
                                        <i class="fas fa-users me-1"></i>
                                        <?php echo $tournament['participant_count']; ?> Participants
                                    </span>
                                </div>

                                <!-- Action Buttons -->
                                <div class="d-grid gap-2">
                                    <?php if ($tournament['status'] === 'upcoming' && $isLoggedIn): ?>
                                        <?php if ($tournament['is_registered']): ?>
                                            <button class="btn btn-success" disabled>
                                                <i class="fas fa-check me-1"></i> Already Registered
                                            </button>
                                        <?php elseif ($userRole === 'squad_leader' || $userRole === 'admin' || $userRole === 'super_admin'): ?>
                                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registerModal<?php echo $tournament['id']; ?>">
                                                <i class="fas fa-sign-in-alt me-1"></i> Register Now
                                            </button>
                                        <?php else: ?>
                                            <div class="alert alert-warning" role="alert">
                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                <strong>Note:</strong> Please contact your squad leader to register for this tournament.
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <a href="tournament_details.php?id=<?php echo $tournament['id']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-info-circle me-1"></i> View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <i class="fas fa-trophy fa-3x text-muted mb-3"></i>
                    <h4>No tournaments found</h4>
                    <p class="text-muted">Try adjusting your filters or check back later for new tournaments.</p>
                    <a href="tournaments.php" class="btn btn-primary">Clear Filters</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php foreach ($tournaments as $tournament): ?>
        <?php if ($tournament['status'] === 'upcoming' && !$tournament['is_registered'] && $isLoggedIn && ($userRole === 'squad_leader' || $userRole === 'admin' || $userRole === 'super_admin')): ?>
            <div class="modal fade" id="registerModal<?php echo $tournament['id']; ?>" tabindex="-1" aria-labelledby="registerModalLabel<?php echo $tournament['id']; ?>" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="registerModalLabel<?php echo $tournament['id']; ?>">Register for <?php echo htmlspecialchars($tournament['name']); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form method="POST">
                                <input type="hidden" name="tournament_id" value="<?php echo $tournament['id']; ?>">
                                <div class="mb-3">
                                    <label for="squad_name<?php echo $tournament['id']; ?>" class="form-label">Squad Name</label>
                                    <input type="text" class="form-control" id="squad_name<?php echo $tournament['id']; ?>" name="squad_name" required>
                                </div>
                                <div class="mb-3">
                                    <label for="in_game_name<?php echo $tournament['id']; ?>" class="form-label">In-Game Name</label>
                                    <input type="text" class="form-control" id="in_game_name<?php echo $tournament['id']; ?>" name="in_game_name" required>
                                </div>
                                <div class="d-grid gap-2">
                                    <button type="submit" name="register_tournament" class="btn btn-primary">
                                        <i class="fas fa-check-circle me-1"></i> Confirm Registration
                                    </button>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <script>
        $(document).ready(function() {
            // Create particles
            createParticles();
            
            // Auto-submit form when filters change
            $('#status').change(function() {
                $(this).closest('form').submit();
            });
            
            // Add hover effects
            $('.tournament-card').hover(
                function() { $(this).addClass('shadow-lg'); },
                function() { $(this).removeClass('shadow-lg'); }
            );
        });
        
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
    </script>
    <!-- Chat Integration -->
    <?php if ($isLoggedIn): ?>
    <script src="polling_chat_client.js"></script>
    <script>
        // Initialize chat for tournaments page
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