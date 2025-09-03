<?php
session_start();
require_once 'config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Handle form submission
$errors = [];
$success = '';
$step = isset($_POST['step']) ? (int)$_POST['step'] : 1;

// Get available squads for dropdown
$squads = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM squads ORDER BY name");
    $squads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input data
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $part_of_squad = isset($_POST['part_of_squad']) ? $_POST['part_of_squad'] : '';
    $squad_action = isset($_POST['squad_action']) ? $_POST['squad_action'] : '';
    $squad_id = isset($_POST['squad_id']) ? (int)$_POST['squad_id'] : null;
    $new_squad_name = isset($_POST['new_squad_name']) ? trim($_POST['new_squad_name']) : '';
    $new_squad_mlbb_id = isset($_POST['new_squad_mlbb_id']) ? trim($_POST['new_squad_mlbb_id']) : '';

    // Validate inputs
    if (empty($username)) {
        $errors['username'] = 'Username is required';
    } elseif (strlen($username) < 3) {
        $errors['username'] = 'Username must be at least 3 characters';
    } elseif (strlen($username) > 50) {
        $errors['username'] = 'Username must be less than 50 characters';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors['username'] = 'Username can only contain letters, numbers, and underscores';
    }

    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    } elseif (strlen($email) > 100) {
        $errors['email'] = 'Email must be less than 100 characters';
    }

    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    } elseif (strlen($password) > 255) {
        $errors['password'] = 'Password is too long';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
        $errors['password'] = 'Password must contain at least one lowercase letter, one uppercase letter, and one number';
    }

    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match';
    }

    // Validate squad-related fields
    if ($part_of_squad === 'yes') {
        if ($squad_action === 'join' && !$squad_id) {
            $errors['squad_id'] = 'Please select a squad to join';
        } elseif ($squad_action === 'create') {
            if (empty($new_squad_name)) {
                $errors['new_squad_name'] = 'Squad name is required';
            } elseif (strlen($new_squad_name) < 3) {
                $errors['new_squad_name'] = 'Squad name must be at least 3 characters';
            } elseif (strlen($new_squad_name) > 100) {
                $errors['new_squad_name'] = 'Squad name must be less than 100 characters';
            }
            if (empty($new_squad_mlbb_id)) {
                $errors['new_squad_mlbb_id'] = 'Squad MLBB ID is required';
            } elseif (strlen($new_squad_mlbb_id) > 50) {
                $errors['new_squad_mlbb_id'] = 'Squad MLBB ID must be less than 50 characters';
            } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $new_squad_mlbb_id)) {
                $errors['new_squad_mlbb_id'] = 'Squad MLBB ID can only contain letters, numbers, underscores, and hyphens';
            }
        }
    }

    // Check if username or email already exists
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);

            if ($stmt->rowCount() > 0) {
                $errors['general'] = 'Username or email already exists';
            }

            // Check if squad name already exists when creating new squad
            if ($part_of_squad === 'yes' && $squad_action === 'create' && !empty($new_squad_name)) {
                $stmt = $pdo->prepare("SELECT id FROM squads WHERE name = ?");
                $stmt->execute([$new_squad_name]);
                if ($stmt->rowCount() > 0) {
                    $errors['new_squad_name'] = 'Squad name already exists';
                }
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $errors['general'] = 'Registration failed. Please try again later.';
        }
    }

    // If no errors, insert new user
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $default_avatar = 'https://ui-avatars.com/api/?name=' . urlencode($username) . '&background=6f42c1&color=fff';

            // Determine user role
            $user_role = ($part_of_squad === 'yes' && $squad_action === 'create') ? 'squad_leader' : 'user';

            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, avatar_url, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$username, $email, $hashed_password, $default_avatar, $user_role]);
            $user_id = $pdo->lastInsertId();

            // Handle squad operations
            if ($part_of_squad === 'yes') {
                if ($squad_action === 'join' && $squad_id) {
                    // Join existing squad
                    $stmt = $pdo->prepare("INSERT INTO squad_members (squad_id, user_id) VALUES (?, ?)");
                    $stmt->execute([$squad_id, $user_id]);
                } elseif ($squad_action === 'create') {
                    // Create new squad
                    $stmt = $pdo->prepare("INSERT INTO squads (name, mlbb_id, leader_id, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$new_squad_name, $new_squad_mlbb_id, $user_id]);
                    $new_squad_id = $pdo->lastInsertId();

                    // Add leader as squad member
                    $stmt = $pdo->prepare("INSERT INTO squad_members (squad_id, user_id) VALUES (?, ?)");
                    $stmt->execute([$new_squad_id, $user_id]);
                }
            }

            $pdo->commit();
            
            // Set session data and redirect based on role
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $user_role;
            $_SESSION['email'] = $email;
            
            // Redirect based on user role
            if ($user_role === 'squad_leader') {
                header('Location: squad_leader_dashboard.php');
                exit();
            } else {
                header('Location: user_dashboard.php');
                exit();
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Database error: " . $e->getMessage());
            $errors['general'] = 'Registration failed. Please try again later.';
        }
    }
}

// Google OAuth Configuration (Disabled until properly configured)
$google_client_id = '';
$google_redirect_uri = '';
$google_auth_url = '#';
$google_oauth_enabled = false;

// To enable Google OAuth:
// 1. Get credentials from Google Developer Console
// 2. Set $google_client_id and $google_redirect_uri
// 3. Set $google_oauth_enabled = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - EsportsHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Oxanium:wght@400;500;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --primary-color: #8c52ff;
            --primary-gradient: linear-gradient(135deg, #8c52ff 0%, #5ce1e6 100%);
            --secondary-color: #20c997;
            --accent-color: #ff3e85;
            --dark-color: #121212;
            --darker-color: #0a0a0a;
            --light-color: #f8f9fa;
            --card-bg: rgba(25, 25, 35, 0.95);
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, var(--darker-color) 0%, #1a1a2e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px 0;
            color: white;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Animated background elements */
        .bg-grid {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
            opacity: 0.15;
            background: 
                linear-gradient(rgba(92, 225, 230, 0.1) 1px, transparent 1px),
                linear-gradient(90deg, rgba(92, 225, 230, 0.1) 1px, transparent 1px);
            background-size: 40px 40px;
        }
        
        .floating-particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }
        
        .particle {
            position: absolute;
            border-radius: 50%;
            background: var(--primary-gradient);
            opacity: 0.2;
            animation: float 15s infinite linear;
        }
        
        @keyframes float {
            0% {
                transform: translateY(0) translateX(0) rotate(0deg);
            }
            100% {
                transform: translateY(-100vh) translateX(100px) rotate(360deg);
            }
        }
        
        .register-container {
            background: var(--card-bg);
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.05);
            overflow: hidden;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .register-left {
            background: var(--primary-gradient);
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .register-left::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.2);
            z-index: 1;
        }
        
        .register-left > * {
            position: relative;
            z-index: 2;
        }
        
        .register-right {
            padding: 40px;
            position: relative;
        }
        
        .ml-character {
            max-width: 100%;
            filter: drop-shadow(0 0 15px rgba(255, 255, 255, 0.3));
            transition: transform 0.3s ease;
            margin-bottom: 20px;
        }
        
        .ml-character:hover {
            transform: translateY(-5px);
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
            margin: 20px 0;
            text-align: left;
        }
        
        .feature-list li {
            margin-bottom: 12px;
            display: flex;
            align-items: center;
        }
        
        .feature-list i {
            color: #ffeb3b;
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: white;
            padding: 14px 16px 14px 45px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 0 0 0 0.2rem rgba(140, 82, 255, 0.25);
            color: white;
            border-color: #8c52ff;
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .input-icon {
            position: relative;
        }
        
        .input-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.6);
            z-index: 10;
        }
        
        .input-icon .form-control {
            padding-left: 45px;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            padding: 12px 20px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(140, 82, 255, 0.3);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #7a45e0 0%, #4ec1c6 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(140, 82, 255, 0.4);
        }
        
        .google-btn {
            background: linear-gradient(135deg, #4285F4 0%, #356ac3 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(66, 133, 244, 0.3);
        }
        
        .google-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, #5691f8 0%, #4285F4 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(66, 133, 244, 0.4);
        }
        
        .google-btn:disabled {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 25px 0;
        }
        
        .divider::before, .divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .divider span {
            padding: 0 15px;
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.9rem;
        }
        
        .password-strength {
            height: 6px;
            margin-top: 8px;
            border-radius: 3px;
            background-color: rgba(255, 255, 255, 0.1);
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            border-radius: 3px;
            width: 0%;
            transition: width 0.4s ease;
        }
        
        .form-check-input {
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .form-check-label {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .form-text {
            color: rgba(255, 255, 255, 0.6) !important;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Oxanium', cursive;
            font-weight: 700;
        }
        
        .register-title {
            font-size: 2.5rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(140, 82, 255, 0.3);
        }
        
        .floating-element {
            animation: float-character 6s ease-in-out infinite;
        }
        
        @keyframes float-character {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
            100% { transform: translateY(0px); }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(140, 82, 255, 0.4); }
            70% { box-shadow: 0 0 0 15px rgba(140, 82, 255, 0); }
            100% { box-shadow: 0 0 0 0 rgba(140, 82, 255, 0); }
        }
        
        .glow-text {
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.7);
        }
        
        .btn-outline-light {
            border-color: rgba(255, 255, 255, 0.3);
            color: rgba(255, 255, 255, 0.8);
        }
        
        .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.5);
            color: white;
        }
        
        .alert {
            border: none;
            border-radius: 8px;
            padding: 12px 16px;
            backdrop-filter: blur(10px);
        }
        
        .alert-danger {
            background: rgba(220, 53, 69, 0.2);
            color: #ff7a7a;
        }
        
        .alert-success {
            background: rgba(32, 201, 151, 0.2);
            color: #20c997;
        }
        
        .text-primary {
            color: #5ce1e6 !important;
        }
        
        @media (max-width: 768px) {
            .register-left {
                padding: 30px 20px;
            }
            
            .register-right {
                padding: 30px 20px;
            }
            
            .ml-character {
                max-height: 180px;
            }
            
            body {
                padding: 10px 0;
            }
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div class="floating-particles" id="particles"></div>
    
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-9 col-xl-8">
                <div class="register-container">
                    <div class="row no-gutters">
                        <div class="col-md-5 register-left">
                            <img src="https://i.imgur.com/9VQJz7G.png" alt="Mobile Legends Character" class="ml-character floating-element">
                            <h3 class="glow-text">Join the Ultimate Esports Community</h3>
                            <ul class="feature-list">
                                <li><i class="fas fa-trophy"></i> Compete in tournaments</li>
                                <li><i class="fas fa-users"></i> Build your squad</li>
                                <li><i class="fas fa-stream"></i> Watch live matches</li>
                                <li><i class="fas fa-award"></i> Earn rewards & recognition</li>
                            </ul>
                            <a href="index.php" class="btn btn-outline-light mt-3 pulse"><i class="fas fa-home me-2"></i>Back to Home</a>
                        </div>
                        <div class="col-md-7 register-right">
                            <h2 class="register-title text-center mb-4">CREATE ACCOUNT</h2>
                            
                            <?php if (!empty($errors['general'])): ?>
                                <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success"><?php echo $success; ?></div>
                            <?php else: ?>
                            
                            <?php if ($google_oauth_enabled): ?>
                            <a href="<?php echo $google_auth_url; ?>" class="btn google-btn w-100 mb-3">
                                <i class="fab fa-google me-2"></i>Sign up with Google
                            </a>
                            <?php else: ?>
                            <button type="button" class="btn google-btn w-100 mb-3" disabled title="Google OAuth not configured">
                                <i class="fab fa-google me-2"></i>Sign up with Google (Disabled)
                            </button>
                            <?php endif; ?>
                            
                            <?php if ($google_oauth_enabled): ?>
                            <div class="divider">
                                <span>OR REGISTER WITH EMAIL</span>
                            </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                <div class="mb-3 input-icon">
                                    <i class="fas fa-user"></i>
                                    <input type="text" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" 
                                           id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                           placeholder="Enter your username" required>
                                    <?php if (isset($errors['username'])): ?>
                                        <div class="invalid-feedback d-block"><?php echo $errors['username']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3 input-icon">
                                    <i class="fas fa-envelope"></i>
                                    <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                           id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                           placeholder="Enter your email" required>
                                    <?php if (isset($errors['email'])): ?>
                                        <div class="invalid-feedback d-block"><?php echo $errors['email']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3 input-icon">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                                           id="password" name="password" placeholder="Create a password" required>
                                    <div class="password-strength mt-2">
                                        <div class="password-strength-bar" id="password-strength-bar"></div>
                                    </div>
                                    <?php if (isset($errors['password'])): ?>
                                        <div class="invalid-feedback d-block"><?php echo $errors['password']; ?></div>
                                    <?php endif; ?>
                                    <small class="form-text">Use at least 8 characters with a mix of letters, numbers & symbols</small>
                                </div>
                                
                                <div class="mb-4 input-icon">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                           id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                                    <?php if (isset($errors['confirm_password'])): ?>
                                        <div class="invalid-feedback d-block"><?php echo $errors['confirm_password']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">Are you part of a squad?</label>
                                    <div class="mb-3">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="part_of_squad" id="squad_no" value="no" 
                                                   <?php echo (!isset($_POST['part_of_squad']) || $_POST['part_of_squad'] === 'no') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="squad_no">
                                                No, I want to play solo
                                            </label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="part_of_squad" id="squad_yes" value="yes"
                                                   <?php echo (isset($_POST['part_of_squad']) && $_POST['part_of_squad'] === 'yes') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="squad_yes">
                                                Yes, I want to join or create a squad
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div id="squad-options" class="mb-4" style="display: none;">
                                    <label class="form-label">Squad Options</label>
                                    <div class="mb-3">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="squad_action" id="join_squad" value="join"
                                                   <?php echo (isset($_POST['squad_action']) && $_POST['squad_action'] === 'join') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="join_squad">
                                                Join an existing squad
                                            </label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="squad_action" id="create_squad" value="create"
                                                   <?php echo (isset($_POST['squad_action']) && $_POST['squad_action'] === 'create') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="create_squad">
                                                Create a new squad
                                            </label>
                                        </div>
                                    </div>

                                    <div id="join-squad-section" class="mb-3" style="display: none;">
                                        <div class="input-icon">
                                            <i class="fas fa-users"></i>
                                            <select class="form-control <?php echo isset($errors['squad_id']) ? 'is-invalid' : ''; ?>" id="squad_id" name="squad_id">
                                                <option value="">-- Select a Squad --</option>
                                                <?php foreach ($squads as $squad): ?>
                                                    <option value="<?php echo $squad['id']; ?>" <?php echo (isset($_POST['squad_id']) && $_POST['squad_id'] == $squad['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($squad['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <?php if (isset($errors['squad_id'])): ?>
                                            <div class="invalid-feedback d-block"><?php echo $errors['squad_id']; ?></div>
                                        <?php endif; ?>
                                        <small class="form-text">Choose from existing squads (alphabetically ordered)</small>
                                    </div>

                                    <div id="create-squad-section" style="display: none;">
                                        <div class="mb-3 input-icon">
                                            <i class="fas fa-flag"></i>
                                            <input type="text" class="form-control <?php echo isset($errors['new_squad_name']) ? 'is-invalid' : ''; ?>" 
                                                   id="new_squad_name" name="new_squad_name" 
                                                   value="<?php echo isset($_POST['new_squad_name']) ? htmlspecialchars($_POST['new_squad_name']) : ''; ?>" 
                                                   placeholder="Enter your squad name">
                                            <?php if (isset($errors['new_squad_name'])): ?>
                                                <div class="invalid-feedback d-block"><?php echo $errors['new_squad_name']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mb-3 input-icon">
                                            <i class="fas fa-id-card"></i>
                                            <input type="text" class="form-control <?php echo isset($errors['new_squad_mlbb_id']) ? 'is-invalid' : ''; ?>" 
                                                   id="new_squad_mlbb_id" name="new_squad_mlbb_id" 
                                                   value="<?php echo isset($_POST['new_squad_mlbb_id']) ? htmlspecialchars($_POST['new_squad_mlbb_id']) : ''; ?>" 
                                                   placeholder="Enter your squad's MLBB ID">
                                            <?php if (isset($errors['new_squad_mlbb_id'])): ?>
                                                <div class="invalid-feedback d-block"><?php echo $errors['new_squad_mlbb_id']; ?></div>
                                            <?php endif; ?>
                                            <small class="form-text">This will be your squad's unique Mobile Legends ID</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="terms" required>
                                    <label class="form-check-label" for="terms">I agree to the <a href="terms.php" class="text-primary">Terms of Service</a> and <a href="privacy.php" class="text-primary">Privacy Policy</a></label>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100 py-3 mb-3">
                                    <i class="fas fa-user-plus me-2"></i>Create Account
                                </button>
                                
                                <div class="text-center">
                                    <span>Already have an account? <a href="login.php" class="text-primary">Sign In</a></span>
                                </div>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Create particles
            createParticles();
            
            // Password strength indicator
            $('#password').on('input', function() {
                const password = $(this).val();
                let strength = 0;
                
                if (password.length >= 8) strength += 20;
                if (password.match(/[a-z]+/)) strength += 20;
                if (password.match(/[A-Z]+/)) strength += 20;
                if (password.match(/[0-9]+/)) strength += 20;
                if (password.match(/[!@#$%^&*(),.?":{}|<>]/)) strength += 20;
                
                const $bar = $('#password-strength-bar');
                $bar.css('width', strength + '%');
                
                if (strength < 40) {
                    $bar.css('background-color', '#dc3545');
                } else if (strength < 80) {
                    $bar.css('background-color', '#ffc107');
                } else {
                    $bar.css('background-color', '#28a745');
                }
            });
            
            // Confirm password validation
            $('#confirm_password').on('input', function() {
                const password = $('#password').val();
                const confirmPassword = $(this).val();
                
                if (confirmPassword && password !== confirmPassword) {
                    $(this).get(0).setCustomValidity("Passwords don't match");
                } else {
                    $(this).get(0).setCustomValidity('');
                }
            });
            
            // Squad selection logic
            $('input[name="part_of_squad"]').on('change', function() {
                if ($(this).val() === 'yes') {
                    $('#squad-options').slideDown(300);
                } else {
                    $('#squad-options').slideUp(300);
                    $('#join-squad-section').slideUp(300);
                    $('#create-squad-section').slideUp(300);
                }
            });
            
            $('input[name="squad_action"]').on('change', function() {
                if ($(this).val() === 'join') {
                    $('#join-squad-section').slideDown(300);
                    $('#create-squad-section').slideUp(300);
                } else if ($(this).val() === 'create') {
                    $('#join-squad-section').slideUp(300);
                    $('#create-squad-section').slideDown(300);
                }
            });
            
            // Initialize form state based on existing values
            if ($('input[name="part_of_squad"]:checked').val() === 'yes') {
                $('#squad-options').show();
                
                if ($('input[name="squad_action"]:checked').val() === 'join') {
                    $('#join-squad-section').show();
                } else if ($('input[name="squad_action"]:checked').val() === 'create') {
                    $('#create-squad-section').show();
                }
            }
            
            // Create particles function
            function createParticles() {
                const particlesContainer = $('#particles');
                const particleCount = 15;
                
                for (let i = 0; i < particleCount; i++) {
                    const size = Math.random() * 20 + 10;
                    const posX = Math.random() * 100;
                    const posY = Math.random() * 100;
                    const animationDelay = Math.random() * 15;
                    const opacity = Math.random() * 0.2 + 0.1;
                    
                    const particle = $('<div class="particle"></div>').css({
                        width: size + 'px',
                        height: size + 'px',
                        left: posX + 'vw',
                        top: posY + 'vh',
                        opacity: opacity,
                        animationDelay: animationDelay + 's'
                    });
                    
                    particlesContainer.append(particle);
                }
            }
        });
    </script>
</body>
</html>