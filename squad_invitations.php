<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is a squad leader
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'squad_leader') {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get squad leader's squad
$squadQuery = $pdo->prepare("SELECT * FROM squads WHERE leader_id = ?");
$squadQuery->execute([$userId]);
$squad = $squadQuery->fetch();

if (!$squad) {
    header('Location: user_dashboard.php');
    exit();
}

// Handle invitation sending
if (isset($_POST['send_invitation'])) {
    $inviteUserId = $_POST['user_id'];
    
    try {
        // Check if user is already in a squad
        $checkSquad = $pdo->prepare("SELECT squad_id FROM squad_members WHERE user_id = ?");
        $checkSquad->execute([$inviteUserId]);
        if ($checkSquad->fetch()) {
            $errorMessage = "This user is already in a squad.";
        } else {
            // Check if invitation already exists
            $checkInvite = $pdo->prepare("SELECT id FROM squad_invitations WHERE squad_id = ? AND user_id = ? AND status = 'pending'");
            $checkInvite->execute([$squad['id'], $inviteUserId]);
            
            if ($checkInvite->fetch()) {
                $errorMessage = "An invitation has already been sent to this user.";
            } else {
                // Send invitation
                $sendInvite = $pdo->prepare("INSERT INTO squad_invitations (squad_id, user_id, invited_by) VALUES (?, ?, ?)");
                $sendInvite->execute([$squad['id'], $inviteUserId, $userId]);
                
                // Create notification
                $notification = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, title, message) 
                    VALUES (?, 'squad_invitation', ?, ?)
                ");
                $title = "Squad Invitation from " . $squad['name'];
                $message = "You have been invited to join the squad '" . $squad['name'] . "' by " . $username;
                $notification->execute([$inviteUserId, $title, $message]);
                
                $successMessage = "Invitation sent successfully!";
            }
        }
    } catch (PDOException $e) {
        error_log("Error sending invitation: " . $e->getMessage());
        $errorMessage = "Failed to send invitation. Please try again.";
    }
}

// Fetch users not in any squad
try {
    $availableUsers = $pdo->prepare("
        SELECT u.id, u.username, u.email, u.created_at 
        FROM users u 
        WHERE u.role = 'user' 
        AND u.id NOT IN (SELECT user_id FROM squad_members)
        AND u.id NOT IN (SELECT user_id FROM squad_invitations WHERE squad_id = ? AND status = 'pending')
        ORDER BY u.username ASC
    ");
    $availableUsers->execute([$squad['id']]);
    $users = $availableUsers->fetchAll();
    
    // Fetch pending invitations
    $pendingInvites = $pdo->prepare("
        SELECT si.*, u.username, u.email 
        FROM squad_invitations si
        JOIN users u ON si.user_id = u.id
        WHERE si.squad_id = ? AND si.status = 'pending'
        ORDER BY si.invited_at DESC
    ");
    $pendingInvites->execute([$squad['id']]);
    $invitations = $pendingInvites->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $users = [];
    $invitations = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Squad Invitations - EsportsHub</title>
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
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 30px;
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
                            <li><a class="dropdown-item" href="squad_leader_dashboard.php">Squad Leader Dashboard</a></li>
                            <li><a class="dropdown-item" href="user_dashboard.php">User Dashboard</a></li>
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
            <h1><i class="fas fa-envelope me-3"></i>Squad Invitations</h1>
            <p class="lead">Invite players to join your squad: <strong><?php echo htmlspecialchars($squad['name']); ?></strong></p>
            <a href="squad_leader_dashboard.php" class="btn btn-outline-light">
                <i class="fas fa-arrow-left me-2"></i>Back to Squad Dashboard
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

        <div class="row">
            <!-- Available Users -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-users me-2"></i>Available Players
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($users)): ?>
                            <div class="row">
                                <?php foreach ($users as $user): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <i class="fas fa-user me-2"></i>
                                                <?php echo htmlspecialchars($user['username']); ?>
                                            </h6>
                                            <p class="card-text small text-muted">
                                                <?php echo htmlspecialchars($user['email']); ?><br>
                                                Joined: <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                            </p>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="send_invitation" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-envelope me-1"></i>Send Invitation
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h6>No Available Players</h6>
                                <p class="text-muted">All eligible players are already in squads or have pending invitations.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Pending Invitations -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="fas fa-clock me-2"></i>Pending Invitations
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($invitations)): ?>
                            <?php foreach ($invitations as $invite): ?>
                            <div class="d-flex justify-content-between align-items-center mb-3 p-2 border rounded">
                                <div>
                                    <strong><?php echo htmlspecialchars($invite['username']); ?></strong><br>
                                    <small class="text-muted">
                                        Sent: <?php echo date('M j, Y', strtotime($invite['invited_at'])); ?>
                                    </small>
                                </div>
                                <span class="badge bg-warning">Pending</span>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                <p class="text-muted small mb-0">No pending invitations</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
