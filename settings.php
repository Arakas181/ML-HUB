<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$username = $_SESSION['username'];

// Ensure required columns exist in users table
$hasNotificationColumns = false;
$hasPrivacyColumns = false;
$hasThemeColumns = false;

try {
	$pdo->query("SELECT notification_emails, notification_push FROM users LIMIT 1");
	$hasNotificationColumns = true;
} catch (PDOException $e) {
	try {
		$pdo->exec("ALTER TABLE users ADD COLUMN notification_emails TINYINT(1) DEFAULT 0");
		$pdo->exec("ALTER TABLE users ADD COLUMN notification_push TINYINT(1) DEFAULT 0");
		$hasNotificationColumns = true;
	} catch (PDOException $e2) {
		error_log('Failed to add notification columns: ' . $e2->getMessage());
	}
}

try {
	$pdo->query("SELECT profile_public, show_stats FROM users LIMIT 1");
	$hasPrivacyColumns = true;
} catch (PDOException $e) {
	try {
		$pdo->exec("ALTER TABLE users ADD COLUMN profile_public TINYINT(1) DEFAULT 1");
		$pdo->exec("ALTER TABLE users ADD COLUMN show_stats TINYINT(1) DEFAULT 1");
		$hasPrivacyColumns = true;
	} catch (PDOException $e2) {
		error_log('Failed to add privacy columns: ' . $e2->getMessage());
	}
}

try {
	$pdo->query("SELECT theme, language FROM users LIMIT 1");
	$hasThemeColumns = true;
} catch (PDOException $e) {
	try {
		$pdo->exec("ALTER TABLE users ADD COLUMN theme VARCHAR(10) DEFAULT 'light'");
		$pdo->exec("ALTER TABLE users ADD COLUMN language VARCHAR(5) DEFAULT 'en'");
		$hasThemeColumns = true;
	} catch (PDOException $e2) {
		error_log('Failed to add theme/language columns: ' . $e2->getMessage());
	}
}

// Handle settings update
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notifications = $_POST['notifications'] ?? [];
    $privacy = $_POST['privacy'] ?? [];
    $theme = $_POST['theme'] ?? 'light';
    $language = $_POST['language'] ?? 'en';
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Dynamically build update query based on available columns
        $fields = [];
        $values = [];
        
        if ($hasNotificationColumns) {
            $fields[] = 'notification_emails = ?';
            $fields[] = 'notification_push = ?';
            $values[] = in_array('emails', $notifications) ? 1 : 0;
            $values[] = in_array('push', $notifications) ? 1 : 0;
        }
        if ($hasPrivacyColumns) {
            $fields[] = 'profile_public = ?';
            $fields[] = 'show_stats = ?';
            $values[] = in_array('public', $privacy) ? 1 : 0;
            $values[] = in_array('stats', $privacy) ? 1 : 0;
        }
        if ($hasThemeColumns) {
            $fields[] = 'theme = ?';
            $fields[] = 'language = ?';
            $values[] = $theme;
            $values[] = $language;
        }
        $fields[] = 'updated_at = NOW()';
        
        if (!empty($fields)) {
            $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
            $values[] = $userId;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
        }
        
        $pdo->commit();
        $successMessage = "Settings updated successfully!";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMessage = $e->getMessage();
    }
}

// Fetch current user settings
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header('Location: logout.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $errorMessage = "Failed to load user settings";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Esports Platform</title>
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
        
        .settings-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .settings-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
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
        
        .settings-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .settings-card .card-header {
            background: var(--light-color);
            border-bottom: 1px solid #dee2e6;
            border-radius: 15px 15px 0 0 !important;
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
                            <?php elseif ($userRole === 'user'): ?>
                                <li><a class="dropdown-item" href="user_dashboard.php">User Dashboard</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item active" href="settings.php">Settings</a></li>
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
        <!-- Settings Header -->
        <div class="settings-header text-center">
            <div class="settings-icon">
                <i class="fas fa-cog"></i>
            </div>
            <h2>Account Settings</h2>
            <p class="mb-0">Customize your experience and manage your preferences</p>
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

        <!-- Settings Form -->
        <form method="POST" action="">
            <div class="row">
                <!-- Notifications Settings -->
                <div class="col-lg-6 mb-4">
                    <div class="settings-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Notification Preferences</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="email_notifications" name="notifications[]" value="emails" 
                                           <?php echo ($user['notification_emails'] ?? 0) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="email_notifications">
                                        Email Notifications
                                    </label>
                                    <small class="form-text text-muted d-block">Receive updates about tournaments, matches, and platform news</small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="push_notifications" name="notifications[]" value="push" 
                                           <?php echo ($user['notification_push'] ?? 0) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="push_notifications">
                                        Push Notifications
                                    </label>
                                    <small class="form-text text-muted d-block">Get real-time alerts on your device</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Privacy Settings -->
                <div class="col-lg-6 mb-4">
                    <div class="settings-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Privacy Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="public_profile" name="privacy[]" value="public" 
                                           <?php echo ($user['profile_public'] ?? 0) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="public_profile">
                                        Public Profile
                                    </label>
                                    <small class="form-text text-muted d-block">Allow other users to view your profile</small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="show_stats" name="privacy[]" value="stats" 
                                           <?php echo ($user['show_stats'] ?? 0) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="show_stats">
                                        Show Statistics
                                    </label>
                                    <small class="form-text text-muted d-block">Display your tournament and match statistics publicly</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Appearance Settings -->
                <div class="col-lg-6 mb-4">
                    <div class="settings-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-palette me-2"></i>Appearance</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="theme" class="form-label">Theme</label>
                                <select class="form-select" id="theme" name="theme">
                                    <option value="light" <?php echo ($user['theme'] ?? 'light') === 'light' ? 'selected' : ''; ?>>Light</option>
                                    <option value="dark" <?php echo ($user['theme'] ?? 'light') === 'dark' ? 'selected' : ''; ?>>Dark</option>
                                    <option value="auto" <?php echo ($user['theme'] ?? 'light') === 'auto' ? 'selected' : ''; ?>>Auto (System)</option>
                                </select>
                                <small class="form-text text-muted">Choose your preferred color scheme</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="language" class="form-label">Language</label>
                                <select class="form-select" id="language" name="language">
                                    <option value="en" <?php echo ($user['language'] ?? 'en') === 'en' ? 'selected' : ''; ?>>English</option>
                                    <option value="es" <?php echo ($user['language'] ?? 'en') === 'es' ? 'selected' : ''; ?>>Español</option>
                                    <option value="id" <?php echo ($user['language'] ?? 'en') === 'id' ? 'selected' : ''; ?>>Bahasa Indonesia</option>
                                </select>
                                <small class="form-text text-muted">Select your preferred language</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Account Actions -->
                <div class="col-lg-6 mb-4">
                    <div class="settings-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-tools me-2"></i>Account Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="profile.php" class="btn btn-outline-primary">
                                    <i class="fas fa-edit me-2"></i>Edit Profile
                                </a>
                                <a href="forgot_password.php" class="btn btn-outline-warning">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </a>
                                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                    <i class="fas fa-trash me-2"></i>Delete Account
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="text-center mt-4">
                <a href="<?php echo $userRole === 'admin' || $userRole === 'super_admin' ? 'admin_dashboard.php' : 'user_dashboard.php'; ?>" class="btn btn-secondary me-2">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Save Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Delete Account Modal -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Warning:</strong> This action cannot be undone. All your data, including:</p>
                    <ul>
                        <li>Tournament history</li>
                        <li>Match statistics</li>
                        <li>Profile information</li>
                        <li>Account settings</li>
                    </ul>
                    <p>will be permanently deleted.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Delete Account
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4 mt-5">
        <div class="container">
            <p>&copy; 2024 Esports Platform. All rights reserved.</p>
        </div>
    </footer>
</body>
</html> 