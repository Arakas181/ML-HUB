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
    
    if (!$userSquad) {
        header('Location: user_dashboard.php');
        exit();
    }
}

// Handle training creation
if (isset($_POST['create_training'])) {
    $squadId = $_POST['squad_id'];
    $trainingDate = $_POST['training_date'];
    $trainingTime = $_POST['training_time'];
    $youtubeUrl = $_POST['youtube_url'];
    
    $trainingDateTime = $trainingDate . ' ' . $trainingTime;
    
    try {
        // Insert training session
        $createTraining = $pdo->prepare("
            INSERT INTO squad_training (squad_id, title, training_date, youtube_url, created_by) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $createTraining->execute([$squadId, 'Squad Training Session', $trainingDateTime, $youtubeUrl, $userId]);
        
        // Get squad name for notification
        $squadQuery = $pdo->prepare("SELECT name FROM squads WHERE id = ?");
        $squadQuery->execute([$squadId]);
        $squadName = $squadQuery->fetchColumn();
        
        // Notify squad members
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
                VALUES (?, 'training_scheduled', ?, ?)
            ");
            $notificationTitle = "Training Session Scheduled";
            $message = "A training session has been scheduled for " . $squadName . " on " . date('M j, Y g:i A', strtotime($trainingDateTime));
            $notification->execute([$member['id'], $notificationTitle, $message]);
        }
        
        $successMessage = "Training session created successfully! Squad members have been notified.";
        
    } catch (PDOException $e) {
        error_log("Error creating training: " . $e->getMessage());
        $errorMessage = "Failed to create training session. Please try again.";
    }
}

// Fetch available squads for admins/super admins
try {
    if ($userRole !== 'squad_leader') {
        $squadsQuery = $pdo->prepare("SELECT id, name, mlbb_id FROM squads ORDER BY name ASC");
        $squadsQuery->execute();
        $allSquads = $squadsQuery->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Error fetching squads: " . $e->getMessage());
    $allSquads = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Training Session - EsportsHub</title>
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
        }
        
        .page-header {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 30px;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(111, 66, 193, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #5a2d91;
            border-color: #5a2d91;
        }
        
        .training-icon {
            color: #2ecc71;
            font-size: 1.2em;
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
            <h1><i class="fas fa-dumbbell me-3 training-icon"></i>Create Training Session</h1>
            <p class="lead">Schedule a training session for your squad</p>
            <?php if ($userRole === 'squad_leader'): ?>
                <a href="squad_leader_dashboard.php" class="btn btn-outline-light">
                    <i class="fas fa-arrow-left me-2"></i>Back to Squad Dashboard
                </a>
            <?php else: ?>
                <a href="admin_dashboard.php" class="btn btn-outline-light">
                    <i class="fas fa-arrow-left me-2"></i>Back to Admin Dashboard
                </a>
            <?php endif; ?>
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

        <!-- Create Training Form -->
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-dumbbell me-2"></i>Training Session Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php if ($userRole !== 'squad_leader'): ?>
                            <!-- Squad Selection (Admin/Super Admin only) -->
                            <div class="mb-3">
                                <label for="squad_id" class="form-label">
                                    <i class="fas fa-users me-2"></i>Squad
                                </label>
                                <select class="form-select" id="squad_id" name="squad_id" required>
                                    <option value="">Select squad...</option>
                                    <?php foreach ($allSquads as $squad): ?>
                                        <option value="<?php echo $squad['id']; ?>">
                                            <?php echo htmlspecialchars($squad['name']); ?> (<?php echo htmlspecialchars($squad['mlbb_id']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php else: ?>
                            <!-- Auto-fill squad for squad leaders -->
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-users me-2"></i>Your Squad
                                </label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($userSquad['name']); ?> (<?php echo htmlspecialchars($userSquad['mlbb_id']); ?>)" readonly>
                                <input type="hidden" name="squad_id" value="<?php echo $userSquad['id']; ?>">
                            </div>
                            <?php endif; ?>


                            <!-- Training Date -->
                            <div class="mb-3">
                                <label for="training_date" class="form-label">
                                    <i class="fas fa-calendar me-2"></i>Training Date
                                </label>
                                <input type="date" class="form-control" id="training_date" name="training_date" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <!-- Training Time -->
                            <div class="mb-3">
                                <label for="training_time" class="form-label">
                                    <i class="fas fa-clock me-2"></i>Training Time
                                </label>
                                <input type="time" class="form-control" id="training_time" name="training_time" required>
                            </div>

                            <!-- YouTube URL -->
                            <div class="mb-3">
                                <label for="youtube_url" class="form-label">
                                    <i class="fab fa-youtube me-2"></i>YouTube Stream URL (Optional)
                                </label>
                                <input type="url" class="form-control" id="youtube_url" name="youtube_url" 
                                       placeholder="https://youtube.com/watch?v=...">
                                <div class="form-text">Add a YouTube link if you plan to stream the training session</div>
                            </div>

                            <!-- Submit Button -->
                            <div class="d-grid">
                                <button type="submit" name="create_training" class="btn btn-success btn-lg">
                                    <i class="fas fa-plus me-2"></i>Create Training Session
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Set minimum date to today
        document.getElementById('training_date').min = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>
