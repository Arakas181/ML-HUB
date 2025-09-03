<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if viewing another user's profile
$viewingUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $_SESSION['user_id'];
$isOwnProfile = ($viewingUserId === $_SESSION['user_id']);

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$username = $_SESSION['username'];

// Check which columns exist in users table
$hasFullNameColumn = false;
$hasBioColumn = false;
$hasAvatarColumn = false;
$hasBannerColumn = false;

try {
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $hasFullNameColumn = in_array('full_name', $columns);
    $hasBioColumn = in_array('bio', $columns);
    $hasAvatarColumn = in_array('avatar_url', $columns);
    $hasBannerColumn = in_array('banner_url', $columns);
} catch (Exception $e) {
    // If we can't check columns, assume basic structure
    $hasFullNameColumn = false;
    $hasBioColumn = false;
}

// Handle profile update (only for own profile)
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwnProfile) {
    $email = trim($_POST['email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Build dynamic UPDATE query based on available columns
        $updateFields = ["email = ?"];
        $updateValues = [$email];
        
        if ($hasFullNameColumn) {
            $updateFields[] = "full_name = ?";
            $updateValues[] = $fullName;
        }
        
        if ($hasBioColumn) {
            $updateFields[] = "bio = ?";
            $updateValues[] = $bio;
        }
        
        $updateValues[] = $userId;
        
        // Update basic info
        $sql = "UPDATE users SET " . implode(", ", $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateValues);
        
        // Handle password change if requested
        if (!empty($currentPassword) && !empty($newPassword)) {
            if ($newPassword !== $confirmPassword) {
                throw new Exception("New passwords do not match");
            }
            
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!password_verify($currentPassword, $user['password'])) {
                throw new Exception("Current password is incorrect");
            }
            
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $userId]);
        }
        
        $pdo->commit();
        $successMessage = "Profile updated successfully!";
        
        // Update session data
        $_SESSION['username'] = $username;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMessage = $e->getMessage();
    }
}

// Fetch user data with squad information
try {
    $stmt = $pdo->prepare("
        SELECT u.*, s.name as squad_name, s.id as squad_id, s.logo_url as squad_logo
        FROM users u 
        LEFT JOIN squad_members sm ON u.id = sm.user_id 
        LEFT JOIN squads s ON sm.squad_id = s.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$viewingUserId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        if ($isOwnProfile) {
            header('Location: logout.php');
            exit();
        } else {
            // User not found, redirect to index with error
            header('Location: index.php?error=user_not_found');
            exit();
        }
    }
    
    // Check if columns exist before using them
    $hasFullNameColumn = array_key_exists('full_name', $user);
    $hasBioColumn = array_key_exists('bio', $user);
    $hasAvatarColumn = array_key_exists('avatar_url', $user);
    $hasBannerColumn = array_key_exists('banner_url', $user);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $errorMessage = "Failed to load profile data";
    $hasFullNameColumn = false;
    $hasBioColumn = false;
    $hasAvatarColumn = false;
    $hasBannerColumn = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isOwnProfile ? 'My Profile' : htmlspecialchars($user['username']) . "'s Profile" ?> - Esports Platform</title>
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
            color: #333;
        }
        
        .navbar {
            background-color: var(--dark-color);
        }
        
        .navbar-brand {
            font-weight: bold;
            color: var(--secondary-color) !important;
        }
        
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 20px;
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
            background-color: #5a32a3;
            border-color: #5a32a3;
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
                            <li><a class="dropdown-item active" href="profile.php">Profile</a></li>
                            <?php if ($userRole === 'admin' || $userRole === 'super_admin'): ?>
                                <li><a class="dropdown-item" href="admin_dashboard.php">Admin Dashboard</a></li>
                            <?php elseif ($userRole === 'user'): ?>
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

    <!-- Main Content -->
    <div class="container mt-5">
        <!-- Profile Header -->
        <div class="profile-header text-center">
            <?php if ($hasBannerColumn && !empty($user['banner_url'])): ?>
                <div class="profile-banner mb-3" style="background-image: url('<?php echo htmlspecialchars($user['banner_url']); ?>'); height: 200px; background-size: cover; background-position: center; border-radius: 15px;"></div>
            <?php endif; ?>
            
            <div class="profile-avatar">
                <?php if ($hasAvatarColumn && !empty($user['avatar_url'])): ?>
                    <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="Avatar" class="rounded-circle" style="width: 120px; height: 120px; object-fit: cover;">
                <?php else: ?>
                    <i class="fas fa-user"></i>
                <?php endif; ?>
            </div>
            
            <h2><?php echo htmlspecialchars($hasFullNameColumn && $user['full_name'] ? $user['full_name'] : $user['username']); ?></h2>
            <p class="mb-2">@<?php echo htmlspecialchars($user['username']); ?></p>
            
            <?php if ($hasBioColumn && !empty($user['bio'])): ?>
                <p class="mb-3 text-light"><?php echo htmlspecialchars($user['bio']); ?></p>
            <?php endif; ?>
            
            <div class="d-flex justify-content-center gap-2 mb-3">
                <span class="badge bg-light text-dark"><?php echo ucfirst($user['role']); ?></span>
                <?php if (!empty($user['squad_name'])): ?>
                    <span class="badge bg-warning text-dark">
                        <i class="fas fa-users me-1"></i><?php echo htmlspecialchars($user['squad_name']); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($successMessage): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($successMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($errorMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!$isOwnProfile): ?>
        <!-- View-only profile for other users -->
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i><?= htmlspecialchars($user['username']) ?>'s Profile</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong>Username:</strong>
                                <p class="mb-0">@<?= htmlspecialchars($user['username']) ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Role:</strong>
                                <p class="mb-0"><?= ucfirst($user['role']) ?></p>
                            </div>
                        </div>
                        
                        <?php if ($hasFullNameColumn && !empty($user['full_name'])): ?>
                        <div class="mb-3">
                            <strong>Full Name:</strong>
                            <p class="mb-0"><?= htmlspecialchars($user['full_name']) ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($hasBioColumn && !empty($user['bio'])): ?>
                        <div class="mb-3">
                            <strong>Bio:</strong>
                            <p class="mb-0"><?= htmlspecialchars($user['bio']) ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($user['squad_name'])): ?>
                        <div class="mb-3">
                            <strong>Squad:</strong>
                            <p class="mb-0">
                                <span class="badge bg-warning text-dark">
                                    <i class="fas fa-users me-1"></i><?= htmlspecialchars($user['squad_name']) ?>
                                </span>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="javascript:history.back()" class="btn btn-secondary">Back</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Profile Form -->
        <?php if ($isOwnProfile): ?>
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Profile</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                                    <div class="form-text">Username cannot be changed</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="bio" class="form-label">Bio</label>
                                <textarea class="form-control" id="bio" name="bio" rows="3" placeholder="Tell us about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            </div>
                            
                            <hr>
                            
                            <h6 class="mb-3">Change Password</h6>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="<?php echo $userRole === 'admin' || $userRole === 'super_admin' ? 'admin_dashboard.php' : 'user_dashboard.php'; ?>" class="btn btn-secondary me-md-2">Back to Dashboard</a>
                                <button type="submit" class="btn btn-primary">Update Profile</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4 mt-5">
        <div class="container">
            <p>&copy; 2024 Esports Platform. All rights reserved.</p>
        </div>
    </footer>
</body>
</html> 