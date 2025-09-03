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
$successMessage = '';
$errorMessage = '';

// Get squad information
try {
    $squadInfo = $pdo->prepare("SELECT * FROM squads WHERE leader_id = ?");
    $squadInfo->execute([$userId]);
    $squad = $squadInfo->fetch();
    
    if (!$squad) {
        $errorMessage = "You don't have a squad yet. Please contact an administrator to create one.";
    } else {
        $squadId = $squad['id'];
        
        // Handle member removal
        if (isset($_POST['remove_member']) && isset($_POST['member_id'])) {
            $memberId = $_POST['member_id'];
            try {
                // Verify the member belongs to the squad leader's squad
                $checkMember = $pdo->prepare("SELECT sm.id FROM squad_members sm 
                                            WHERE sm.user_id = ? AND sm.squad_id = ?");
                $checkMember->execute([$memberId, $squadId]);
                
                if ($checkMember->rowCount() > 0) {
                    // Remove the member
                    $removeMember = $pdo->prepare("DELETE FROM squad_members WHERE user_id = ? AND squad_id = ?");
                    $removeMember->execute([$memberId, $squadId]);
                    $successMessage = "Squad member removed successfully.";
                } else {
                    $errorMessage = "This user is not a member of your squad.";
                }
            } catch (PDOException $e) {
                error_log("Error removing squad member: " . $e->getMessage());
                $errorMessage = "Failed to remove squad member. Please try again.";
            }
        }
        
        // Handle adding new members
        if (isset($_POST['add_member']) && isset($_POST['user_email'])) {
            $userEmail = trim($_POST['user_email']);
            
            try {
                // Check if user exists
                $checkUser = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = 'user'");
                $checkUser->execute([$userEmail]);
                $user = $checkUser->fetch();
                
                if ($user) {
                    $newMemberId = $user['id'];
                    
                    // Check if user is already in a squad
                    $checkExistingMembership = $pdo->prepare("SELECT id FROM squad_members WHERE user_id = ?");
                    $checkExistingMembership->execute([$newMemberId]);
                    
                    if ($checkExistingMembership->rowCount() > 0) {
                        $errorMessage = "This user is already a member of another squad.";
                    } else {
                        // Add user to squad
                        $addMember = $pdo->prepare("INSERT INTO squad_members (squad_id, user_id, joined_at) VALUES (?, ?, NOW())");
                        $addMember->execute([$squadId, $newMemberId]);
                        $successMessage = "New member added to your squad successfully.";
                    }
                } else {
                    $errorMessage = "No user found with that email address or the user is not a regular user.";
                }
            } catch (PDOException $e) {
                error_log("Error adding squad member: " . $e->getMessage());
                $errorMessage = "Failed to add squad member. Please try again.";
            }
        }
        
        // Get squad members
        $squadMembers = $pdo->prepare("SELECT u.id, u.username, u.email, sm.joined_at 
                                    FROM users u 
                                    JOIN squad_members sm ON u.id = sm.user_id 
                                    WHERE sm.squad_id = ? 
                                    ORDER BY sm.joined_at DESC");
        $squadMembers->execute([$squadId]);
        $members = $squadMembers->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Error fetching squad data: " . $e->getMessage());
    $errorMessage = "An error occurred while retrieving squad information.";
    $squad = null;
    $members = [];
}

// Get available users (users who are not in any squad)
try {
    $availableUsers = $pdo->query("SELECT id, username, email FROM users 
                                WHERE role = 'user' 
                                AND id NOT IN (SELECT user_id FROM squad_members)
                                ORDER BY username ASC");
    $availableUsersList = $availableUsers->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching available users: " . $e->getMessage());
    $availableUsersList = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Squad - EsportsHub</title>
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
            transform: translateY(-5px);
        }
        
        .squad-card {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
        }
        
        .member-card {
            border-left: 4px solid var(--primary-color);
        }
        
        .member-card .member-info {
            display: flex;
            align-items: center;
        }
        
        .member-card .member-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 15px;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box .form-control {
            padding-left: 40px;
            border-radius: 20px;
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 12px;
            color: #6c757d;
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
                            <li><a class="dropdown-item" href="squad_leader_dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
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
                            <a class="nav-link" href="squad_leader_dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="manage_squad.php">
                                <i class="fas fa-users"></i> Manage Squad
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
                    </ul>
                </div>
            </div>

            <!-- Main content -->
            <div class="col-md-9 col-lg-10 ms-sm-auto px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Squad</h1>
                </div>

                <?php if ($errorMessage): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $errorMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $successMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if ($squad): ?>
                <!-- Squad Information -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card squad-card">
                            <div class="card-body">
                                <h4 class="card-title"><i class="fas fa-users me-2"></i><?php echo htmlspecialchars($squad['name']); ?></h4>
                                <p class="card-text"><?php echo htmlspecialchars($squad['description']); ?></p>
                                <p class="card-text"><small>Created: <?php echo date('M d, Y', strtotime($squad['created_at'])); ?></small></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Current Squad Members -->
                    <div class="col-lg-8 mb-4">
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
                                                <td><?php echo htmlspecialchars($member['username']); ?></td>
                                                <td><?php echo htmlspecialchars($member['email']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($member['joined_at'])); ?></td>
                                                <td>
                                                    <form method="post" onsubmit="return confirm('Are you sure you want to remove this member from your squad?');">
                                                        <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                                        <button type="submit" name="remove_member" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-user-minus"></i> Remove
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <h5>No Members Yet</h5>
                                    <p class="text-muted">Your squad doesn't have any members yet. Add members using the form on the right.</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Add New Member Form -->
                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Add New Member</h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <div class="mb-3">
                                        <label for="user_email" class="form-label">User Email</label>
                                        <input type="email" class="form-control" id="user_email" name="user_email" required placeholder="Enter user's email address">
                                        <div class="form-text">Enter the email address of the user you want to add to your squad.</div>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" name="add_member" class="btn btn-success">
                                            <i class="fas fa-plus-circle me-1"></i> Add to Squad
                                        </button>
                                    </div>
                                </form>
                                
                                <?php if (!empty($availableUsersList)): ?>
                                <hr>
                                <h6 class="mb-3">Available Users</h6>
                                <div class="list-group" style="max-height: 300px; overflow-y: auto;">
                                    <?php foreach ($availableUsersList as $user): ?>
                                    <div class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($user['username']); ?></h6>
                                                <small><?php echo htmlspecialchars($user['email']); ?></small>
                                            </div>
                                            <form method="post">
                                                <input type="hidden" name="user_email" value="<?php echo htmlspecialchars($user['email']); ?>">
                                                <button type="submit" name="add_member" class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                                <h4>No Squad Found</h4>
                                <p class="text-muted">You don't have a squad yet. Please contact an administrator to create one for you.</p>
                                <a href="squad_leader_dashboard.php" class="btn btn-primary">Return to Dashboard</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Filter available users list
            $("#search-available").on("keyup", function() {
                var value = $(this).val().toLowerCase();
                $(".list-group .list-group-item").filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
                });
            });
        });
    </script>
</body>
</html>