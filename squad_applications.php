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

// Handle application actions
if (isset($_POST['action']) && isset($_POST['application_id'])) {
    $applicationId = $_POST['application_id'];
    $action = $_POST['action'];
    
    try {
        if ($action === 'approve') {
            // Get application details
            $appQuery = $pdo->prepare("SELECT * FROM squad_applications WHERE id = ? AND squad_id = ?");
            $appQuery->execute([$applicationId, $squad['id']]);
            $application = $appQuery->fetch();
            
            if ($application) {
                // Start transaction
                $pdo->beginTransaction();
                
                // Add user to squad
                $addMember = $pdo->prepare("INSERT INTO squad_members (squad_id, user_id) VALUES (?, ?)");
                $addMember->execute([$squad['id'], $application['user_id']]);
                
                // Update application status
                $updateApp = $pdo->prepare("UPDATE squad_applications SET status = 'approved', reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
                $updateApp->execute([$userId, $applicationId]);
                
                $pdo->commit();
                $successMessage = "Application approved successfully! User has been added to your squad.";
            }
        } elseif ($action === 'reject') {
            // Update application status
            $updateApp = $pdo->prepare("UPDATE squad_applications SET status = 'rejected', reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
            $updateApp->execute([$userId, $applicationId]);
            $successMessage = "Application rejected.";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error processing application: " . $e->getMessage());
        $errorMessage = "Failed to process application. Please try again.";
    }
}

// Fetch pending applications for this squad
try {
    $applicationsQuery = $pdo->prepare("
        SELECT sa.*, u.username, u.email, u.created_at as user_created
        FROM squad_applications sa
        JOIN users u ON sa.user_id = u.id
        WHERE sa.squad_id = ? AND sa.status = 'pending'
        ORDER BY sa.applied_at DESC
    ");
    $applicationsQuery->execute([$squad['id']]);
    $applications = $applicationsQuery->fetchAll();
    
    // Fetch recent processed applications
    $recentQuery = $pdo->prepare("
        SELECT sa.*, u.username, ur.username as reviewed_by_name
        FROM squad_applications sa
        JOIN users u ON sa.user_id = u.id
        LEFT JOIN users ur ON sa.reviewed_by = ur.id
        WHERE sa.squad_id = ? AND sa.status != 'pending'
        ORDER BY sa.reviewed_at DESC
        LIMIT 10
    ");
    $recentQuery->execute([$squad['id']]);
    $recentApplications = $recentQuery->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error fetching applications: " . $e->getMessage());
    $applications = [];
    $recentApplications = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Squad Applications - EsportsHub</title>
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
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
        }
        
        .page-header {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 30px;
        }
        
        .approve-btn {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        .approve-btn:hover {
            background-color: #218838;
            border-color: #1e7e34;
            color: white;
        }
        
        .reject-btn {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        
        .reject-btn:hover {
            background-color: #c82333;
            border-color: #bd2130;
            color: white;
        }
        
        .status-approved {
            color: #28a745;
            font-weight: bold;
        }
        
        .status-rejected {
            color: #dc3545;
            font-weight: bold;
        }
        
        .application-card {
            border-left: 4px solid #007bff;
        }
        
        .no-applications {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
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
            <h1><i class="fas fa-clipboard-list me-3"></i>Squad Applications</h1>
            <p class="lead">Manage applications to join your squad: <strong><?php echo htmlspecialchars($squad['name']); ?></strong></p>
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

        <!-- Pending Applications -->
        <div class="row">
            <div class="col-12">
                <h3 class="mb-4">
                    <i class="fas fa-clock me-2"></i>Pending Applications 
                    <span class="badge bg-primary"><?php echo count($applications); ?></span>
                </h3>
                
                <?php if (!empty($applications)): ?>
                    <div class="row">
                        <?php foreach ($applications as $app): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="card application-card h-100">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-user me-2"></i>
                                        <?php echo htmlspecialchars($app['username']); ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <p class="mb-2">
                                            <strong>Email:</strong> 
                                            <span class="text-muted"><?php echo htmlspecialchars($app['email']); ?></span>
                                        </p>
                                        <p class="mb-2">
                                            <strong>Applied:</strong> 
                                            <?php echo date('M j, Y g:i A', strtotime($app['applied_at'])); ?>
                                        </p>
                                        <p class="mb-0">
                                            <strong>User Since:</strong> 
                                            <?php echo date('M j, Y', strtotime($app['user_created'])); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <div class="d-flex gap-2">
                                        <form method="POST" style="flex: 1;">
                                            <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn approve-btn w-100" onclick="return confirm('Are you sure you want to approve this application?')">
                                                <i class="fas fa-check me-2"></i>Approve
                                            </button>
                                        </form>
                                        <form method="POST" style="flex: 1;">
                                            <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn reject-btn w-100" onclick="return confirm('Are you sure you want to reject this application?')">
                                                <i class="fas fa-times me-2"></i>Reject
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="card no-applications">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-inbox fa-4x mb-4"></i>
                            <h4>No Pending Applications</h4>
                            <p class="mb-0">There are currently no pending applications to join your squad.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Applications History -->
        <?php if (!empty($recentApplications)): ?>
        <div class="row mt-5">
            <div class="col-12">
                <h3 class="mb-4">
                    <i class="fas fa-history me-2"></i>Recent Application History
                </h3>
                
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Applicant</th>
                                        <th>Applied Date</th>
                                        <th>Status</th>
                                        <th>Reviewed Date</th>
                                        <th>Reviewed By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentApplications as $recent): ?>
                                    <tr>
                                        <td>
                                            <i class="fas fa-user me-2"></i>
                                            <?php echo htmlspecialchars($recent['username']); ?>
                                        </td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($recent['applied_at'])); ?></td>
                                        <td>
                                            <span class="status-<?php echo $recent['status']; ?>">
                                                <i class="fas fa-<?php echo $recent['status'] === 'approved' ? 'check' : 'times'; ?> me-1"></i>
                                                <?php echo ucfirst($recent['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $recent['reviewed_at'] ? date('M j, Y g:i A', strtotime($recent['reviewed_at'])) : '-'; ?></td>
                                        <td><?php echo $recent['reviewed_by_name'] ? htmlspecialchars($recent['reviewed_by_name']) : '-'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Add hover effects to cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.classList.add('shadow-lg');
                });
                card.addEventListener('mouseleave', function() {
                    this.classList.remove('shadow-lg');
                });
            });
        });
    </script>
</body>
</html>
