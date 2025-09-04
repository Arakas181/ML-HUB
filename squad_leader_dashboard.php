<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is a squad leader
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Allow access for squad leaders and users who might need to create a squad
if ($_SESSION['role'] !== 'squad_leader' && $_SESSION['role'] !== 'user') {
    header('Location: user_dashboard.php');
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Handle squad member removal if requested
if (isset($_POST['remove_member']) && isset($_POST['member_id'])) {
    $memberId = $_POST['member_id'];
    try {
        // Verify the member belongs to the squad leader's squad
        $checkMember = $pdo->prepare("SELECT sm.id FROM squad_members sm 
                                    JOIN squads s ON sm.squad_id = s.id 
                                    WHERE sm.user_id = ? AND s.leader_id = ?");
        $checkMember->execute([$memberId, $userId]);
        
        if ($checkMember->rowCount() > 0) {
            // Remove the member
            $removeMember = $pdo->prepare("DELETE FROM squad_members WHERE user_id = ? AND squad_id IN (SELECT id FROM squads WHERE leader_id = ?)");
            $removeMember->execute([$memberId, $userId]);
            $successMessage = "Squad member removed successfully.";
        }
    } catch (PDOException $e) {
        error_log("Error removing squad member: " . $e->getMessage());
        $errorMessage = "Failed to remove squad member. Please try again.";
    }
}

// Handle squad creation for new squad leaders
if ($_SESSION['role'] === 'squad_leader' && isset($_POST['create_squad'])) {
    $squadName = trim($_POST['squad_name']);
    $squadMlbbId = trim($_POST['squad_mlbb_id']);
    
    if (!empty($squadName)) {
        try {
            $pdo->beginTransaction();
            
            // Check if squad name already exists
            $checkSquad = $pdo->prepare("SELECT id FROM squads WHERE name = ?");
            $checkSquad->execute([$squadName]);
            
            if ($checkSquad->rowCount() == 0) {
                // Create new squad
                $createSquad = $pdo->prepare("INSERT INTO squads (name, mlbb_id, leader_id, created_at) VALUES (?, ?, ?, NOW())");
                $createSquad->execute([$squadName, $squadMlbbId, $userId]);
                $newSquadId = $pdo->lastInsertId();
                
                // Add leader as squad member
                $addMember = $pdo->prepare("INSERT INTO squad_members (squad_id, user_id, joined_at) VALUES (?, ?, NOW())");
                $addMember->execute([$newSquadId, $userId]);
                
                $pdo->commit();
                $successMessage = "Squad created successfully!";
            } else {
                $pdo->rollBack();
                $errorMessage = "Squad name already exists. Please choose a different name.";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error creating squad: " . $e->getMessage());
            $errorMessage = "Failed to create squad. Please try again.";
        }
    } else {
        $errorMessage = "Squad name is required.";
    }
}

// Fetch squad leader-specific data
try {
    // Get squad information
    $squadInfo = $pdo->prepare("SELECT * FROM squads WHERE leader_id = ?");
    $squadInfo->execute([$userId]);
    $squad = $squadInfo->fetch();
    
    if ($squad) {
        // Get squad members
        $squadMembers = $pdo->prepare("SELECT u.id, u.username, u.email, sm.joined_at 
                                    FROM users u 
                                    JOIN squad_members sm ON u.id = sm.user_id 
                                    WHERE sm.squad_id = ? 
                                    ORDER BY sm.joined_at DESC");
        $squadMembers->execute([$squad['id']]);
        $members = $squadMembers->fetchAll();
    } else {
        $members = [];
    }
    
    // Get squad's tournament participations
    $squadTournaments = $pdo->prepare("SELECT t.*, tp.status as participation_status 
                                    FROM tournaments t 
                                    JOIN tournament_participants tp ON t.id = tp.tournament_id 
                                    WHERE tp.user_id = ? 
                                    ORDER BY t.start_date DESC");
    $squadTournaments->execute([$userId]);
    $tournaments = $squadTournaments->fetchAll();
    
    // Get squad's match history
    $squadMatches = $pdo->prepare("SELECT m.*, t1.name as team1_name, t2.name as team2_name, t.name as tournament_name
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
                                LIMIT 10");
    $squadMatches->execute([$userId]);
    $matches = $squadMatches->fetchAll();
    
    // Get upcoming matches for tournaments squad is participating in
    $upcomingMatches = $pdo->prepare("SELECT m.*, t1.name as team1_name, t2.name as team2_name, t.name as tournament_name
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
                                    LIMIT 5");
    $upcomingMatches->execute([$userId]);
    $upcoming = $upcomingMatches->fetchAll();
    
    // Get squad's achievements/stats
    $squadStats = $pdo->prepare("SELECT 
                                COUNT(DISTINCT tp.tournament_id) as tournaments_joined,
                                COUNT(DISTINCT CASE WHEN tp.status = 'winner' THEN tp.tournament_id END) as tournaments_won,
                                COUNT(DISTINCT CASE WHEN tp.status = 'runner_up' THEN tp.tournament_id END) as tournaments_runner_up
                            FROM tournament_participants tp 
                            WHERE tp.user_id = ?");
    $squadStats->execute([$userId]);
    $stats = $squadStats->fetch();
    
} catch (PDOException $e) {
    error_log("Error fetching squad leader data: " . $e->getMessage());
    $squad = null;
    $members = [];
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
    <title>Squad Leader Dashboard - EsportsHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
        
        .navbar {
            background-color: var(--dark-color);
        }
        
        .navbar-brand {
            font-weight: bold;
            color: var(--secondary-color) !important;
        }
        
        .sidebar {
            background-color: var(--dark-color);
            color: white;
            height: 100vh;
            position: sticky;
            top: 0;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 15px 20px;
            border-left: 4px solid transparent;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
            border-left: 4px solid var(--secondary-color);
        }
        
        .sidebar .nav-link i {
            width: 25px;
        }
        
        .stats-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .stats-label {
            font-size: 1rem;
            color: #6c757d;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .tournament-card {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
        }
        
        .match-card {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
        }
        
        .squad-card {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
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
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-gamepad me-2"></i>EsportsHub
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($username); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-id-card me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="squad_leader_dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="tournaments.php">
                                <i class="fas fa-trophy"></i> Tournaments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="matches.php">
                                <i class="fas fa-gamepad"></i> Matches
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_squad.php">
                                <i class="fas fa-users"></i> Manage Squad
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my_tournaments.php">
                                <i class="fas fa-list"></i> My Tournaments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my_matches.php">
                                <i class="fas fa-history"></i> Match History
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="news.php">
                                <i class="fas fa-newspaper"></i> News
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="guides.php">
                                <i class="fas fa-book"></i> Guides
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main content -->
            <div class="col-md-9 col-lg-10 ms-sm-auto px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Squad Leader Dashboard</h1>
                </div>

                <?php if (isset($errorMessage)): ?>
                <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
                <?php endif; ?>
                
                <?php if (isset($successMessage)): ?>
                <div class="alert alert-success"><?php echo $successMessage; ?></div>
                <?php endif; ?>

                <!-- Squad Information -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card squad-card">
                            <div class="card-body">
                                <?php if ($squad): ?>
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h4 class="card-title"><i class="fas fa-users me-2"></i><?php echo htmlspecialchars($squad['name']); ?></h4>
                                        <p class="card-text"><strong>MLBB ID:</strong> <?php echo htmlspecialchars($squad['mlbb_id']); ?></p>
                                        <p class="card-text"><?php echo htmlspecialchars($squad['description']); ?></p>
                                        <p class="card-text"><small>Created: <?php echo date('M d, Y', strtotime($squad['created_at'])); ?></small></p>
                                        <p class="card-text"><small>Squad Members: <?php echo count($members); ?></small></p>
                                    </div>
                                    <div>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <a href="squad_applications.php" class="btn btn-outline-light">
                                                <i class="fas fa-clipboard-list me-2"></i>Manage Applications
                                            </a>
                                            <a href="squad_invitations.php" class="btn btn-outline-light">
                                                <i class="fas fa-envelope me-2"></i>Send Invitations
                                            </a>
                                            <a href="create_scrim.php" class="btn btn-outline-light">
                                                <i class="fas fa-sword-cross me-2"></i>Create Scrim
                                            </a>
                                            <a href="create_training.php" class="btn btn-outline-light">
                                                <i class="fas fa-dumbbell me-2"></i>Create Training
                                            </a>
                                            <a href="manage_scrims.php" class="btn btn-outline-light">
                                                <i class="fas fa-list me-2"></i>Manage Scrims
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php else: ?>
                                <h4 class="card-title"><i class="fas fa-plus-circle me-2"></i>Create Your Squad</h4>
                                <p class="card-text">As a squad leader, you need to create your squad first.</p>
                                
                                <form method="POST" action="" class="mt-3">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="squad_name" class="form-label">Squad Name *</label>
                                            <input type="text" class="form-control" id="squad_name" name="squad_name" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="squad_mlbb_id" class="form-label">Squad MLBB ID</label>
                                            <input type="text" class="form-control" id="squad_mlbb_id" name="squad_mlbb_id">
                                        </div>
                                    </div>
                                    <button type="submit" name="create_squad" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Create Squad
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Squad Members -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-users me-2"></i>Squad Members</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($members)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Username</th>
                                                <th>Email</th>
                                                <th>Joined Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($members as $member): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($member['id'] == $squad['leader_id']): ?>
                                                        <i class="fas fa-crown" style="color: #ffc107; margin-right: 8px;"></i>
                                                        <span style="color: #ffc107; font-weight: bold;"><?php echo htmlspecialchars($member['username']); ?></span>
                                                        <small class="text-muted ms-2">(Leader)</small>
                                                    <?php else: ?>
                                                        <i class="fas fa-user me-2"></i>
                                                        <?php echo htmlspecialchars($member['username']); ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($member['email']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($member['joined_at'])); ?></td>
                                                <td>
                                                    <?php if ($member['id'] != $squad['leader_id']): ?>
                                                    <form method="post" onsubmit="return confirm('Are you sure you want to remove this member from your squad?');">
                                                        <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                                        <button type="submit" name="remove_member" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-user-minus"></i> Remove
                                                        </button>
                                                    </form>
                                                    <?php else: ?>
                                                    <span class="text-muted">Squad Leader</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-muted">No members in your squad yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="stats-card bg-white">
                            <div class="stats-number"><?php echo $stats['tournaments_joined']; ?></div>
                            <div class="stats-label">Tournaments Joined</div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stats-card bg-white">
                            <div class="stats-number"><?php echo $stats['tournaments_won']; ?></div>
                            <div class="stats-label">Tournaments Won</div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stats-card bg-white">
                            <div class="stats-number"><?php echo $stats['tournaments_runner_up']; ?></div>
                            <div class="stats-label">Runner-up Finishes</div>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Matches -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Upcoming Matches</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($upcoming)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Tournament</th>
                                                <th>Match</th>
                                                <th>Date & Time</th>
                                                <th>Round</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($upcoming as $match): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($match['tournament_name']); ?></td>
                                                <td>
                                                    <a href="match_details.php?id=<?php echo $match['id']; ?>">
                                                        <?php echo htmlspecialchars($match['team1_name']); ?> vs <?php echo htmlspecialchars($match['team2_name']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo date('M d, Y - h:i A', strtotime($match['scheduled_time'])); ?></td>
                                                <td><?php echo htmlspecialchars($match['round']); ?></td>
                                                <td>
                                                    <span class="badge bg-warning text-dark"><?php echo ucfirst($match['status']); ?></span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-muted">No upcoming matches scheduled.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Tournaments -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Recent Tournaments</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($tournaments)): ?>
                                <div class="row">
                                    <?php foreach (array_slice($tournaments, 0, 3) as $tournament): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($tournament['name']); ?></h5>
                                                <p class="card-text"><?php echo substr(htmlspecialchars($tournament['description']), 0, 100); ?>...</p>
                                                <p class="card-text">
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y', strtotime($tournament['start_date'])); ?> - 
                                                        <?php echo date('M d, Y', strtotime($tournament['end_date'])); ?>
                                                    </small>
                                                </p>
                                                <div>
                                                    <span class="badge bg-<?php echo $tournament['status'] === 'completed' ? 'secondary' : ($tournament['status'] === 'ongoing' ? 'success' : 'primary'); ?>">
                                                        <?php echo ucfirst($tournament['status']); ?>
                                                    </span>
                                                    <span class="badge bg-info">
                                                        <?php echo ucfirst($tournament['participation_status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="card-footer bg-transparent border-top-0">
                                                <a href="tournament_details.php?id=<?php echo $tournament['id']; ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <p class="text-muted">You haven't participated in any tournaments yet.</p>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer bg-transparent">
                                <a href="my_tournaments.php" class="btn btn-outline-primary">View All Tournaments</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Enable tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            })
        });
    </script>
    <!-- Chat Integration -->
    <?php if ($isLoggedIn): ?>
    <script src="polling_chat_client.js"></script>
    <script>
        // Initialize chat for squad leader dashboard
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