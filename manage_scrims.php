<?php
session_start();
require_once 'config.php';

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['squad_leader', 'admin', 'super_admin'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];
$userRole = $_SESSION['role'];

// Get user's squad if they're a squad leader
$userSquad = null;
if ($userRole === 'squad_leader') {
    $squadQuery = $pdo->prepare("SELECT * FROM squads WHERE leader_id = ?");
    $squadQuery->execute([$userId]);
    $userSquad = $squadQuery->fetch();
}

// Handle scrim cancellation
if (isset($_POST['cancel_scrim'])) {
    $scrimId = $_POST['scrim_id'];
    
    try {
        // Get scrim details for notification
        $scrimQuery = $pdo->prepare("
            SELECT ss.*, hs.name as host_squad_name, os.name as opponent_squad_name 
            FROM squad_scrims ss
            JOIN squads hs ON ss.host_squad_id = hs.id
            JOIN squads os ON ss.opponent_squad_id = os.id
            WHERE ss.id = ?
        ");
        $scrimQuery->execute([$scrimId]);
        $scrim = $scrimQuery->fetch();
        
        if ($scrim) {
            // Update scrim status
            $cancelScrim = $pdo->prepare("
                UPDATE squad_scrims 
                SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = ? 
                WHERE id = ?
            ");
            $cancelScrim->execute([$userId, $scrimId]);
            
            // Notify both squads
            $squadsToNotify = [$scrim['host_squad_id'], $scrim['opponent_squad_id']];
            
            foreach ($squadsToNotify as $squadId) {
                $squadMembers = $pdo->prepare("
                    SELECT u.id FROM users u 
                    JOIN squad_members sm ON u.id = sm.user_id 
                    WHERE sm.squad_id = ?
                ");
                $squadMembers->execute([$squadId]);
                $members = $squadMembers->fetchAll();
                
                foreach ($members as $member) {
                    $notification = $pdo->prepare("
                        INSERT INTO notifications (user_id, type, title, message) 
                        VALUES (?, 'scrim_cancelled', ?, ?)
                    ");
                    $title = "Scrim Cancelled";
                    $message = "The scrim between " . $scrim['host_squad_name'] . " and " . $scrim['opponent_squad_name'] . " scheduled for " . date('M j, Y g:i A', strtotime($scrim['scrim_date'])) . " has been cancelled.";
                    $notification->execute([$member['id'], $title, $message]);
                }
            }
            
            $successMessage = "Scrim cancelled successfully. Both squads have been notified.";
        }
    } catch (PDOException $e) {
        error_log("Error cancelling scrim: " . $e->getMessage());
        $errorMessage = "Failed to cancel scrim. Please try again.";
    }
}

// Fetch scrims based on user role
try {
    if ($userRole === 'squad_leader' && $userSquad) {
        // Squad leaders see scrims for their squad only
        $scrimsQuery = $pdo->prepare("
            SELECT ss.*, hs.name as host_squad_name, os.name as opponent_squad_name,
                   u.username as created_by_name
            FROM squad_scrims ss
            JOIN squads hs ON ss.host_squad_id = hs.id
            JOIN squads os ON ss.opponent_squad_id = os.id
            JOIN users u ON ss.created_by = u.id
            WHERE (ss.host_squad_id = ? OR ss.opponent_squad_id = ?)
            ORDER BY ss.scrim_date DESC
        ");
        $scrimsQuery->execute([$userSquad['id'], $userSquad['id']]);
    } else {
        // Admins see all scrims
        $scrimsQuery = $pdo->prepare("
            SELECT ss.*, hs.name as host_squad_name, os.name as opponent_squad_name,
                   u.username as created_by_name
            FROM squad_scrims ss
            JOIN squads hs ON ss.host_squad_id = hs.id
            JOIN squads os ON ss.opponent_squad_id = os.id
            JOIN users u ON ss.created_by = u.id
            ORDER BY ss.scrim_date DESC
        ");
        $scrimsQuery->execute();
    }
    $scrims = $scrimsQuery->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching scrims: " . $e->getMessage());
    $scrims = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Scrims - EsportsHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        :root {
            --primary-color: #6f42c1;
            --secondary-color: #20c997;
            --dark-color: #212529;
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
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .page-header {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 30px;
        }
        
        .status-scheduled { color: #007bff; }
        .status-confirmed { color: #28a745; }
        .status-cancelled { color: #dc3545; }
        .status-completed { color: #6c757d; }
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
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="matches.php">Matches</a></li>
                    <li class="nav-item"><a class="nav-link" href="tournaments.php">Tournaments</a></li>
                    <li class="nav-item"><a class="nav-link" href="news.php">News</a></li>
                    <li class="nav-item"><a class="nav-link" href="guides.php">Guides</a></li>
                </ul>
                <div class="d-flex align-items-center">
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
                                <li><a class="dropdown-item" href="user_dashboard.php">User Dashboard</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container text-center">
            <h1><i class="fas fa-sword-cross me-3"></i>Manage Scrims</h1>
            <p class="lead">View and manage squad scrimmage matches</p>
            <?php if ($userRole === 'squad_leader'): ?>
                <a href="squad_leader_dashboard.php" class="btn btn-outline-light me-2">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            <?php else: ?>
                <a href="admin_dashboard.php" class="btn btn-outline-light me-2">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            <?php endif; ?>
            <a href="create_scrim.php" class="btn btn-outline-light">
                <i class="fas fa-plus me-2"></i>Create New Scrim
            </a>
        </div>
    </div>

    <div class="container mb-5">
        <!-- Alert Messages -->
        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $successMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $errorMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Scrims List -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    <?php echo $userRole === 'squad_leader' ? 'Your Squad Scrims' : 'All Scrims'; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($scrims)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Host Squad</th>
                                    <th>Opponent Squad</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($scrims as $scrim): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($scrim['host_squad_name']); ?></strong>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($scrim['opponent_squad_name']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y g:i A', strtotime($scrim['scrim_date'])); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $scrim['status'] === 'scheduled' ? 'primary' : 
                                                ($scrim['status'] === 'confirmed' ? 'success' : 
                                                ($scrim['status'] === 'cancelled' ? 'danger' : 'secondary')); 
                                        ?>">
                                            <?php echo ucfirst($scrim['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($scrim['created_by_name']); ?></td>
                                    <td>
                                        <?php if ($scrim['status'] === 'scheduled' || $scrim['status'] === 'confirmed'): ?>
                                            <div class="d-flex gap-1">
                                                <?php if ($scrim['youtube_url']): ?>
                                                    <a href="<?php echo htmlspecialchars($scrim['youtube_url']); ?>" 
                                                       target="_blank" class="btn btn-sm btn-outline-danger" title="Watch Stream">
                                                        <i class="fab fa-youtube"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="scrim_id" value="<?php echo $scrim['id']; ?>">
                                                    <button type="submit" name="cancel_scrim" class="btn btn-sm btn-danger" 
                                                            onclick="return confirm('Are you sure you want to cancel this scrim?')" title="Cancel Scrim">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <?php if ($scrim['youtube_url']): ?>
                                                <a href="<?php echo htmlspecialchars($scrim['youtube_url']); ?>" 
                                                   target="_blank" class="btn btn-sm btn-outline-danger" title="Watch Stream">
                                                    <i class="fab fa-youtube"></i>
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-sword-cross fa-4x text-muted mb-4"></i>
                        <h4>No Scrims Found</h4>
                        <p class="text-muted">No scrims have been scheduled yet.</p>
                        <a href="create_scrim.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Create Your First Scrim
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
