<?php
session_start();
require_once 'config.php';

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];
$userRole = $_SESSION['role'];

// Handle squad member removal (super admin only)
if (isset($_POST['remove_member']) && isset($_POST['member_id']) && $userRole === 'super_admin') {
    $memberId = $_POST['member_id'];
    try {
        $removeMember = $pdo->prepare("DELETE FROM squad_members WHERE user_id = ?");
        $removeMember->execute([$memberId]);
        $successMessage = "Squad member removed successfully.";
    } catch (PDOException $e) {
        error_log("Error removing squad member: " . $e->getMessage());
        $errorMessage = "Failed to remove squad member. Please try again.";
    }
}

// Handle squad deletion (super admin only)
if (isset($_POST['delete_squad']) && isset($_POST['squad_id']) && $userRole === 'super_admin') {
    $squadId = $_POST['squad_id'];
    try {
        $pdo->beginTransaction();
        
        // Remove all squad members first
        $removeMembers = $pdo->prepare("DELETE FROM squad_members WHERE squad_id = ?");
        $removeMembers->execute([$squadId]);
        
        // Delete the squad
        $deleteSquad = $pdo->prepare("DELETE FROM squads WHERE id = ?");
        $deleteSquad->execute([$squadId]);
        
        $pdo->commit();
        $successMessage = "Squad deleted successfully.";
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error deleting squad: " . $e->getMessage());
        $errorMessage = "Failed to delete squad. Please try again.";
    }
}

// Fetch all squads with their information
try {
    $squadsQuery = $pdo->query("
        SELECT s.*, u.username as leader_name, 
               COUNT(sm.user_id) as member_count
        FROM squads s 
        LEFT JOIN users u ON s.leader_id = u.id 
        LEFT JOIN squad_members sm ON s.id = sm.squad_id 
        GROUP BY s.id 
        ORDER BY s.created_at DESC
    ");
    $squads = $squadsQuery->fetchAll();
    
    // Get detailed squad information if viewing specific squad
    $selectedSquad = null;
    $squadMembers = [];
    if (isset($_GET['squad_id'])) {
        $squadId = $_GET['squad_id'];
        
        // Get squad details
        $squadQuery = $pdo->prepare("
            SELECT s.*, u.username as leader_name, u.email as leader_email
            FROM squads s 
            LEFT JOIN users u ON s.leader_id = u.id 
            WHERE s.id = ?
        ");
        $squadQuery->execute([$squadId]);
        $selectedSquad = $squadQuery->fetch();
        
        // Get squad members
        if ($selectedSquad) {
            $membersQuery = $pdo->prepare("
                SELECT u.id, u.username, u.email, u.role, sm.joined_at
                FROM users u 
                JOIN squad_members sm ON u.id = sm.user_id 
                WHERE sm.squad_id = ? 
                ORDER BY sm.joined_at DESC
            ");
            $membersQuery->execute([$squadId]);
            $squadMembers = $membersQuery->fetchAll();
        }
    }
    
} catch (PDOException $e) {
    error_log("Error fetching squads data: " . $e->getMessage());
    $squads = [];
    $selectedSquad = null;
    $squadMembers = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Squads - EsportsHub</title>
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
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
        }
        
        .squad-card {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
        }
        
        .member-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: bold;
            margin: 2px;
        }
        
        .badge-leader {
            background-color: #ffc107;
            color: #212529;
        }
        
        .badge-member {
            background-color: #17a2b8;
            color: white;
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
                </ul>
                <div class="d-flex align-items-center">
                    <div class="dropdown">
                        <a class="btn btn-outline-light dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($username); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="admin_dashboard.php">Admin Dashboard</a></li>
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
                    <h5 class="text-center mb-4">Admin Panel</h5>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="admin_dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_notices.php">
                                <i class="fas fa-bullhorn me-2"></i> Notices
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_tournaments.php">
                                <i class="fas fa-trophy me-2"></i> Tournaments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_matches.php">
                                <i class="fas fa-gamepad me-2"></i> Matches
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_users.php">
                                <i class="fas fa-users me-2"></i> Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="manage_squads.php">
                                <i class="fas fa-users-cog me-2"></i> Squads
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_content.php">
                                <i class="fas fa-newspaper me-2"></i> News & Guides
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
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Squads</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="manage_squads.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-list me-1"></i> All Squads
                            </a>
                        </div>
                    </div>
                </div>

                <?php if (isset($errorMessage)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $errorMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if (isset($successMessage)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $successMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($selectedSquad): ?>
                <!-- Squad Details View -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-users me-2"></i><?php echo htmlspecialchars($selectedSquad['name']); ?>
                        </h5>
                        <a href="manage_squads.php" class="btn btn-light btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Back to All Squads
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Squad Name:</strong> <?php echo htmlspecialchars($selectedSquad['name']); ?></p>
                                <p><strong>MLBB ID:</strong> <?php echo htmlspecialchars($selectedSquad['mlbb_id']); ?></p>
                                <p><strong>Leader:</strong> <?php echo htmlspecialchars($selectedSquad['leader_name']); ?></p>
                                <p><strong>Leader Email:</strong> <?php echo htmlspecialchars($selectedSquad['leader_email']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Description:</strong> <?php echo htmlspecialchars($selectedSquad['description'] ?: 'No description provided'); ?></p>
                                <p><strong>Created:</strong> <?php echo date('M d, Y g:i A', strtotime($selectedSquad['created_at'])); ?></p>
                                <p><strong>Total Members:</strong> <?php echo count($squadMembers); ?></p>
                            </div>
                        </div>
                        
                        <?php if ($userRole === 'super_admin'): ?>
                        <div class="mt-3">
                            <form method="post" onsubmit="return confirm('Are you sure you want to delete this squad? This action cannot be undone.');" class="d-inline">
                                <input type="hidden" name="squad_id" value="<?php echo $selectedSquad['id']; ?>">
                                <button type="submit" name="delete_squad" class="btn btn-danger btn-sm">
                                    <i class="fas fa-trash me-1"></i> Delete Squad
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Squad Members -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-users me-2"></i>Squad Members</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($squadMembers)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Joined Date</th>
                                        <?php if ($userRole === 'super_admin'): ?>
                                        <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($squadMembers as $member): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($member['username']); ?>
                                            <?php if ($member['id'] == $selectedSquad['leader_id']): ?>
                                                <span class="member-badge badge-leader">Leader</span>
                                            <?php else: ?>
                                                <span class="member-badge badge-member">Member</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($member['email']); ?></td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo ucfirst($member['role']); ?></span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($member['joined_at'])); ?></td>
                                        <?php if ($userRole === 'super_admin'): ?>
                                        <td>
                                            <?php if ($member['id'] != $selectedSquad['leader_id']): ?>
                                            <form method="post" onsubmit="return confirm('Are you sure you want to remove this member from the squad?');" class="d-inline">
                                                <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                                <button type="submit" name="remove_member" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-user-minus me-1"></i> Remove
                                                </button>
                                            </form>
                                            <?php else: ?>
                                            <span class="text-muted">Squad Leader</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-muted">No members in this squad.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php else: ?>
                <!-- All Squads View -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-users-cog me-2"></i>All Squads</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($squads)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Squad Name</th>
                                        <th>MLBB ID</th>
                                        <th>Leader</th>
                                        <th>Members</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($squads as $squad): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($squad['name']); ?></strong>
                                            <?php if ($squad['description']): ?>
                                            <br><small class="text-muted"><?php echo substr(htmlspecialchars($squad['description']), 0, 50); ?>...</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><code><?php echo htmlspecialchars($squad['mlbb_id']); ?></code></td>
                                        <td><?php echo htmlspecialchars($squad['leader_name']); ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $squad['member_count']; ?> members</span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($squad['created_at'])); ?></td>
                                        <td>
                                            <a href="manage_squads.php?squad_id=<?php echo $squad['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye me-1"></i> View Details
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No squads have been created yet.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
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
</body>
</html>
