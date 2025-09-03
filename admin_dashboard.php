<?php
require_once 'config.php';

// Check if user is logged in and has admin role
if (!isLoggedIn() || (getUserRole() !== 'admin' && getUserRole() !== 'super_admin')) {
    header('Location: admin_login.php');
    exit();
}

// Get admin stats
try {
    $noticesCount = $pdo->query("SELECT COUNT(*) FROM notices")->fetchColumn();
    $matchesCount = $pdo->query("SELECT COUNT(*) FROM matches")->fetchColumn();
    $usersCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
    $tournamentsCount = $pdo->query("SELECT COUNT(*) FROM tournaments")->fetchColumn();
    $squadsCount = $pdo->query("SELECT COUNT(*) FROM squads")->fetchColumn();
} catch (PDOException $e) {
    die("Error fetching stats: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Esports Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
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
            --card-bg: rgba(25, 25, 35, 0.95);
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, var(--darker-color) 0%, #1a1a2e 100%);
            color: white;
            min-height: 100vh;
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
        
        .sidebar {
            background: var(--card-bg);
            border-radius: 15px;
            margin: 20px 0;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            min-height: calc(100vh - 120px);
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
            padding: 12px 15px !important;
            margin: 5px 0;
            border-radius: 8px;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(140, 82, 255, 0.2) !important;
            color: white !important;
            border-left: 4px solid var(--primary-color);
            transform: translateX(5px);
        }
        
        .card {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            color: white;
        }
        
        .card-header {
            background: var(--primary-gradient);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px 15px 0 0 !important;
            font-family: 'Oxanium', cursive;
            font-weight: 600;
        }
        
        .stats-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s;
            height: 100%;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            background: rgba(140, 82, 255, 0.1);
            box-shadow: 0 15px 35px rgba(140, 82, 255, 0.2);
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #5ce1e6;
            margin-bottom: 10px;
            font-family: 'Oxanium', cursive;
        }
        
        .stats-label {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 15px;
        }
        
        .stats-link {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .stats-link:hover {
            color: #5ce1e6;
            text-decoration: underline;
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
        
        .quick-actions .btn {
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        
        .list-group-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .list-group-item:hover {
            background: rgba(140, 82, 255, 0.1);
        }
        
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .sidebar {
                margin: 10px;
                padding: 15px;
                min-height: auto;
            }
            
            .container-fluid {
                padding: 0 10px;
            }
            
            .navbar-brand {
                font-size: 1.2rem;
            }
            
            .stats-card {
                padding: 20px;
                margin-bottom: 15px;
            }
            
            .stats-number {
                font-size: 2rem;
            }
            
            .quick-actions .btn {
                height: 50px;
                font-size: 0.9rem;
                margin-bottom: 10px;
            }
            
            .card-header h5 {
                font-size: 1.1rem;
            }
            
            .sidebar .nav-link {
                padding: 10px 12px !important;
                font-size: 0.9rem;
            }
            
            .col-md-3, .col-md-4, .col-md-6 {
                margin-bottom: 15px;
            }
        }
        
        @media (max-width: 576px) {
            .navbar-text {
                display: none;
            }
            
            .btn-sm {
                font-size: 0.8rem;
                padding: 6px 12px;
            }
            
            .stats-card {
                padding: 15px;
            }
            
            .stats-number {
                font-size: 1.8rem;
            }
            
            .quick-actions .btn {
                height: 45px;
                font-size: 0.85rem;
            }
            
            .card-body {
                padding: 15px;
            }
            
            .sidebar {
                margin: 5px;
                padding: 10px;
            }
            
            .sidebar .nav-link {
                padding: 8px 10px !important;
                font-size: 0.85rem;
            }
        }
        
        /* Sidebar Toggle for Mobile */
        .sidebar-toggle {
            display: none;
        }
        
        @media (max-width: 768px) {
            .sidebar-toggle {
                display: block;
            }
            
            .sidebar {
                position: fixed;
                top: 0;
                left: -100%;
                width: 280px;
                height: 100vh;
                z-index: 1050;
                transition: left 0.3s ease;
                margin: 0;
                border-radius: 0;
            }
            
            .sidebar.show {
                left: 0;
            }
            
            .sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 1040;
                display: none;
            }
            
            .sidebar-overlay.show {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div class="floating-particles" id="particles"></div>
    
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <button class="btn btn-outline-light btn-sm me-2 sidebar-toggle d-md-none" type="button" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <a class="navbar-brand" href="#">
                <i class="fas fa-shield-alt me-2"></i>Admin Panel
            </a>
            <div class="navbar-nav ms-auto d-flex align-items-center">
                <span class="navbar-text me-3 d-none d-sm-inline">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <?php if ($_SESSION['role'] === 'super_admin'): ?>
                <a href="index.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="fas fa-home me-1 d-none d-sm-inline"></i>Home
                </a>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt me-1 d-none d-sm-inline"></i>Logout
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar" id="sidebar">
                <h5 class="text-center mb-4">Admin Panel</h5>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="#">
                            <i class="fas fa-tachometer-alt me-2"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_notices.php">
                            <i class="fas fa-bullhorn me-2"></i>
                            Notices
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_tournaments.php">
                            <i class="fas fa-trophy me-2"></i>
                            Tournaments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_matches.php">
                            <i class="fas fa-gamepad me-2"></i>
                            Manage Matches
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_matches.php">
                            <i class="fas fa-video me-2"></i>
                            Watch Matches
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_users.php">
                            <i class="fas fa-users me-2"></i>
                            Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_squads.php">
                            <i class="fas fa-users me-2"></i> Manage Squads
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_polls.php">
                            <i class="fas fa-poll-h me-2"></i>
                            Live Polls
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="create_scrim.php">
                            <i class="fas fa-swords me-2"></i> Create Scrim
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="create_training.php">
                            <i class="fas fa-dumbbell me-2"></i> Create Training
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_content.php">
                            <i class="fas fa-newspaper me-2"></i>
                            News & Guides
                        </a>
                    </li>
                    <?php if ($_SESSION['role'] === 'super_admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_admins.php">
                            <i class="fas fa-shield-alt me-2"></i>
                            Manage Admins
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="system_settings.php">
                            <i class="fas fa-cog me-2"></i>
                            System Settings
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="dashboard-header">
                    <h1><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h1>
                </div>

                <!-- Stats cards -->
                <div class="row">
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-4">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $noticesCount; ?></div>
                            <div class="stats-label">Notices</div>
                            <div class="mt-3">
                                <a href="manage_notices.php" class="stats-link">View details <i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-4">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $matchesCount; ?></div>
                            <div class="stats-label">Matches</div>
                            <div class="mt-3">
                                <a href="manage_matches.php" class="stats-link">View details <i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-4">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $usersCount; ?></div>
                            <div class="stats-label">Users</div>
                            <div class="mt-3">
                                <a href="manage_users.php" class="stats-link">View details <i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-4">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $tournamentsCount; ?></div>
                            <div class="stats-label">Tournaments</div>
                            <div class="mt-3">
                                <a href="manage_tournaments.php" class="stats-link">View details <i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Second row of stats -->
                <div class="row">
                    <div class="col-lg-3 col-md-6 col-sm-6 mb-4">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $squadsCount; ?></div>
                            <div class="stats-label">Squads</div>
                            <div class="mt-3">
                                <a href="manage_squads.php" class="stats-link">View details <i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="row quick-actions">
                                    <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                        <a href="manage_matches.php" class="btn btn-primary w-100">
                                            <i class="fas fa-gamepad me-2"></i>Manage Matches
                                        </a>
                                    </div>
                                    <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                        <a href="manage_notices.php" class="btn btn-primary w-100">
                                            <i class="fas fa-bullhorn me-2"></i>Add Notice
                                        </a>
                                    </div>
                                    <div class="col-lg-4 col-md-12 col-sm-12 mb-3">
                                        <a href="manage_tournaments.php" class="btn btn-primary w-100">
                                            <i class="fas fa-trophy me-2"></i>Create Tournament
                                        </a>
                                    </div>
                                    <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
                                        <a href="manage_polls.php" class="btn btn-primary w-100">
                                            <i class="fas fa-poll-h me-2"></i>Manage Polls
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent activities -->
                <div class="row mt-4">
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-bullhorn me-2"></i>Recent Notices</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $stmt = $pdo->query("SELECT n.title, n.created_at, u.username 
                                                    FROM notices n 
                                                    JOIN users u ON n.author_id = u.id 
                                                    ORDER BY n.created_at DESC LIMIT 5");
                                $recentNotices = $stmt->fetchAll();
                                
                                if ($recentNotices) {
                                    echo '<div class="list-group">';
                                    foreach ($recentNotices as $notice) {
                                        echo '<div class="list-group-item">';
                                        echo '<strong>' . htmlspecialchars($notice['title']) . '</strong>';
                                        echo '<br><small>By ' . htmlspecialchars($notice['username']) . ' on ' . 
                                             date('M j, Y', strtotime($notice['created_at'])) . '</small>';
                                        echo '</div>';
                                    }
                                    echo '</div>';
                                } else {
                                    echo '<p>No notices found.</p>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-gamepad me-2"></i>Upcoming Matches</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $stmt = $pdo->query("SELECT m.scheduled_time, t1.name as team1, t2.name as team2 
                                                    FROM matches m 
                                                    JOIN teams t1 ON m.team1_id = t1.id 
                                                    JOIN teams t2 ON m.team2_id = t2.id 
                                                    WHERE m.status = 'scheduled' 
                                                    ORDER BY m.scheduled_time ASC LIMIT 5");
                                $upcomingMatches = $stmt->fetchAll();
                                
                                if ($upcomingMatches) {
                                    echo '<div class="list-group">';
                                    foreach ($upcomingMatches as $match) {
                                        echo '<div class="list-group-item">';
                                        echo '<strong>' . htmlspecialchars($match['team1']) . ' vs ' . 
                                             htmlspecialchars($match['team2']) . '</strong>';
                                        echo '<br><small>' . date('M j, Y g:i A', strtotime($match['scheduled_time'])) . '</small>';
                                        echo '</div>';
                                    }
                                    echo '</div>';
                                } else {
                                    echo '<p>No upcoming matches.</p>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.sidebar-toggle');
            const overlay = document.querySelector('.sidebar-overlay');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                }
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            if (window.innerWidth > 768) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            }
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Create particles
            createParticles();
        });
    </script>
</body>
</html>