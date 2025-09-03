<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is a regular user
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: login.php');
    exit();
}

// Create missing tables if they don't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS squad_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        squad_id INT NOT NULL,
        user_id INT NOT NULL,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (squad_id) REFERENCES squads(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_membership (squad_id, user_id)
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS squad_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        squad_id INT NOT NULL,
        user_id INT NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        reviewed_at TIMESTAMP NULL,
        reviewed_by INT NULL,
        FOREIGN KEY (squad_id) REFERENCES squads(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_application (squad_id, user_id)
    )");
} catch (PDOException $e) {
    // Tables might already exist, continue
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Check if user is already in a squad
$checkSquad = $pdo->prepare("SELECT squad_id FROM squad_members WHERE user_id = ?");
$checkSquad->execute([$userId]);
if ($checkSquad->fetch()) {
    header('Location: user_dashboard.php');
    exit();
}

// Handle squad application
if (isset($_POST['apply_squad'])) {
    $squadId = $_POST['squad_id'];
    
    try {
        // Check if user already applied to this squad
        $checkApplication = $pdo->prepare("SELECT id FROM squad_applications WHERE user_id = ? AND squad_id = ?");
        $checkApplication->execute([$userId, $squadId]);
        
        if ($checkApplication->fetch()) {
            $errorMessage = "You have already applied to this squad.";
        } else {
            // Insert application
            $applySquad = $pdo->prepare("INSERT INTO squad_applications (squad_id, user_id) VALUES (?, ?)");
            $applySquad->execute([$squadId, $userId]);
            $successMessage = "Your application has been sent successfully! The squad leader will review your request.";
        }
    } catch (PDOException $e) {
        error_log("Error applying to squad: " . $e->getMessage());
        $errorMessage = "Failed to send application. Please try again.";
    }
}

// Fetch all available squads
try {
    $squadsQuery = $pdo->prepare("
        SELECT s.*, u.username as leader_name, COUNT(sm.user_id) as member_count,
               CASE WHEN sa.id IS NOT NULL THEN 1 ELSE 0 END as already_applied
        FROM squads s 
        LEFT JOIN users u ON s.leader_id = u.id 
        LEFT JOIN squad_members sm ON s.id = sm.squad_id 
        LEFT JOIN squad_applications sa ON s.id = sa.squad_id AND sa.user_id = ? AND sa.status = 'pending'
        GROUP BY s.id 
        ORDER BY s.name ASC
    ");
    $squadsQuery->execute([$userId]);
    $squads = $squadsQuery->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching squads: " . $e->getMessage());
    $squads = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Squad - EsportsHub</title>
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
            transform: translateY(-5px);
        }
        
        .squad-card {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
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
        
        .apply-btn {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        .apply-btn:hover {
            background-color: #218838;
            border-color: #1e7e34;
            color: white;
        }
        
        .applied-btn {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
            cursor: not-allowed;
        }
        
        .page-header {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
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
                            <li><a class="dropdown-item" href="user_dashboard.php">Dashboard</a></li>
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
            <h1><i class="fas fa-users me-3"></i>Join a Squad</h1>
            <p class="lead">Find the perfect squad to team up with and compete in tournaments!</p>
            <a href="user_dashboard.php" class="btn btn-outline-light">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
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

        <!-- Available Squads -->
        <div class="row">
            <div class="col-12">
                <h3 class="mb-4">Available Squads</h3>
                
                <?php if (!empty($squads)): ?>
                    <div class="row">
                        <?php foreach ($squads as $squad): ?>
                        <div class="col-lg-6 col-xl-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header squad-card">
                                    <h5 class="mb-0">
                                        <i class="fas fa-shield-alt me-2"></i>
                                        <?php echo htmlspecialchars($squad['name']); ?>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <p class="mb-2">
                                            <strong>MLBB ID:</strong> 
                                            <span class="text-primary"><?php echo htmlspecialchars($squad['mlbb_id']); ?></span>
                                        </p>
                                        <p class="mb-2">
                                            <i class="fas fa-crown crown-icon"></i>
                                            <span class="squad-leader">Squad Leader:</span> 
                                            <?php echo htmlspecialchars($squad['leader_name']); ?>
                                        </p>
                                        <p class="mb-2">
                                            <i class="fas fa-users me-2"></i>
                                            <strong>Members:</strong> <?php echo $squad['member_count']; ?>
                                        </p>
                                        <p class="mb-3">
                                            <i class="fas fa-calendar me-2"></i>
                                            <strong>Created:</strong> <?php echo date('M j, Y', strtotime($squad['created_at'])); ?>
                                        </p>
                                    </div>
                                    
                                    <?php if ($squad['description']): ?>
                                        <div class="mb-3">
                                            <p class="text-muted small">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <?php echo htmlspecialchars($squad['description']); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <?php if ($squad['already_applied']): ?>
                                        <button class="btn applied-btn w-100" disabled>
                                            <i class="fas fa-clock me-2"></i>Application Pending
                                        </button>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline-block; width: 100%;">
                                            <input type="hidden" name="squad_id" value="<?php echo $squad['id']; ?>">
                                            <button type="submit" name="apply_squad" class="btn apply-btn w-100">
                                                <i class="fas fa-paper-plane me-2"></i>Apply to Join
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-4x text-muted mb-4"></i>
                        <h4>No Squads Available</h4>
                        <p class="text-muted">There are currently no squads available to join. Check back later or create your own squad during registration!</p>
                        <a href="user_dashboard.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
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
