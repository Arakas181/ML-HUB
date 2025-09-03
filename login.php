<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $role = getUserRole();
    switch ($role) {
        case 'admin':
        case 'super_admin':
            header('Location: admin_dashboard.php');
            break;
        case 'squad_leader':
            header('Location: squad_leader_dashboard.php');
            break;
        default:
            header('Location: user_dashboard.php');
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EsportsHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Oxanium:wght@400;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
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
            --card-bg: rgba(25, 25, 35, 0.85);
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background: var(--darker-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
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
        
        .login-container {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.05);
            overflow: hidden;
            max-width: 460px;
            width: 100%;
            backdrop-filter: blur(10px);
            transform: translateY(0);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .login-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.1);
        }
        
        .login-header {
            background: var(--primary-gradient);
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(45deg);
            animation: shine 6s infinite linear;
        }
        
        @keyframes shine {
            0% {
                left: -50%;
            }
            100% {
                left: 150%;
            }
        }
        
        .login-brand {
            font-family: 'Oxanium', cursive;
            font-size: 32px;
            font-weight: 700;
            color: white;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            position: relative;
            letter-spacing: 1px;
        }
        
        .login-brand i {
            margin-right: 12px;
            color: #ffeb3b;
        }
        
        .login-subtitle {
            font-size: 16px;
            margin-top: 8px;
            opacity: 0.9;
            position: relative;
        }
        
        .login-body {
            padding: 30px;
        }
        
        .form-control {
            background-color: rgba(255, 255, 255, 0.08);
            border: none;
            border-radius: 8px;
            color: white;
            padding: 14px 18px;
            margin-bottom: 20px;
            transition: all 0.3s;
            font-size: 16px;
        }
        
        .form-control:focus {
            background-color: rgba(255, 255, 255, 0.12);
            box-shadow: 0 0 0 3px rgba(140, 82, 255, 0.3);
            color: white;
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        
        .btn-login {
            background: var(--primary-gradient);
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-weight: 600;
            width: 100%;
            margin-top: 10px;
            transition: all 0.3s;
            color: white;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
        }
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(140, 82, 255, 0.3);
        }
        
        .btn-login:hover::before {
            left: 100%;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 25px 0;
        }
        
        .divider::before, .divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }
        
        .divider span {
            padding: 0 15px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
        }
        
        .btn-google {
            background: rgba(255, 255, 255, 0.08);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-weight: 500;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            transition: all 0.3s;
        }
        
        .btn-google:hover {
            background: rgba(255, 255, 255, 0.12);
            color: white;
            transform: translateY(-2px);
        }
        
        .login-footer {
            text-align: center;
            padding: 20px;
            background: rgba(0, 0, 0, 0.2);
            font-size: 14px;
        }
        
        .login-footer a {
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .login-footer a:hover {
            color: #ffeb3b;
            text-decoration: underline;
        }
        
        .password-toggle {
            position: relative;
        }
        
        .password-toggle i {
            position: absolute;
            right: 18px;
            top: 15px;
            cursor: pointer;
            color: rgba(255, 255, 255, 0.5);
            transition: color 0.2s;
        }
        
        .password-toggle i:hover {
            color: white;
        }
        
        .floating-label {
            position: relative;
            margin-bottom: 24px;
        }
        
        .floating-label label {
            position: absolute;
            top: 14px;
            left: 18px;
            color: rgba(255, 255, 255, 0.6);
            transition: all 0.3s;
            pointer-events: none;
            font-size: 16px;
        }
        
        .floating-label input:focus ~ label,
        .floating-label input:not(:placeholder-shown) ~ label {
            top: -10px;
            left: 12px;
            font-size: 12px;
            background: var(--card-bg);
            padding: 0 8px;
            color: #5ce1e6;
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
        
        .forgot-password {
            color: #5ce1e6;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .forgot-password:hover {
            color: #8c52ff;
            text-decoration: underline;
        }
        
        .gaming-character {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 180px;
            filter: drop-shadow(0 5px 15px rgba(0, 0, 0, 0.5));
            z-index: 5;
            animation: float-character 4s ease-in-out infinite;
        }
        
        @keyframes float-character {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-15px);
            }
        }
        
        @media (max-width: 768px) {
            .gaming-character {
                display: none;
            }
            
            body {
                padding: 20px;
            }
            
            .login-container {
                max-width: 100%;
            }
        }
        
        .alert {
            border: none;
            border-radius: 8px;
            padding: 12px 16px;
            background: rgba(220, 53, 69, 0.2);
            color: #ff7a7a;
            backdrop-filter: blur(5px);
        }
        
        .btn-close {
            filter: invert(1);
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(140, 82, 255, 0.4);
            }
            70% {
                box-shadow: 0 0 0 12px rgba(140, 82, 255, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(140, 82, 255, 0);
            }
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div class="floating-particles" id="particles"></div>
    
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8">
                <div class="login-container pulse">
                    <div class="login-header">
                        <div class="login-brand">
                            <i class="fas fa-gamepad"></i>EsportsHub
                        </div>
                        <p class="login-subtitle">Enter your credentials to access the arena</p>
                    </div>
                    
                    <div class="login-body">
                        <?php if (isset($_GET['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                                <?php 
                                switch($_GET['error']) {
                                    case 'empty_fields':
                                        echo 'Please fill in all fields.';
                                        break;
                                    case 'invalid_credentials':
                                        echo 'Invalid username, password, or role. Please try again.';
                                        break;
                                    case 'database_error':
                                        echo 'System error. Please try again later.';
                                        break;
                                    default:
                                        echo 'An error occurred. Please try again.';
                                }
                                ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form id="loginForm" action="authenticate.php" method="POST">
                            <input type="hidden" name="role" id="selectedRole" value="user">
                            
                            <div class="floating-label">
                                <input type="text" class="form-control" id="username" name="username" placeholder=" " required>
                                <label for="username">Username or Email</label>
                            </div>
                            
                            <div class="floating-label password-toggle">
                                <input type="password" class="form-control" id="password" name="password" placeholder=" " required>
                                <label for="password">Password</label>
                                <i class="fas fa-eye" id="togglePassword"></i>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                    <label class="form-check-label" for="remember">Remember me</label>
                                </div>
                                <a href="forgot_password.php" class="forgot-password">Forgot Password?</a>
                            </div>
                            
                            <button type="submit" class="btn btn-login">Login to Arena</button>
                            
                            <div class="divider">
                                <span>Or continue with</span>
                            </div>
                            
                            <button type="button" class="btn btn-google">
                                <i class="fab fa-google"></i> Google
                            </button>
                        </form>
                    </div>
                    
                    <div class="login-footer">
                        Don't have an account? <a href="register.php">Join the battle</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        $(document).ready(function() {
            // Create floating particles
            createParticles();
            
            // Password visibility toggle
            $('#togglePassword').click(function() {
                const passwordField = $('#password');
                const type = passwordField.attr('type') === 'password' ? 'text' : 'password';
                passwordField.attr('type', type);
                $(this).toggleClass('fa-eye fa-eye-slash');
            });
            
            // Form validation
            $('#loginForm').submit(function(e) {
                let isValid = true;
                
                // Basic validation
                $('#username, #password').each(function() {
                    if ($(this).val().trim() === '') {
                        $(this).addClass('is-invalid');
                        isValid = false;
                    } else {
                        $(this).removeClass('is-invalid');
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    // Add shake animation to indicate error
                    $('.login-container').css('animation', 'shake 0.5s');
                    $('.login-container').on('animationend', function() {
                        $(this).css('animation', '');
                    });
                }
            });
            
            // Input validation on the fly
            $('#username, #password').on('input', function() {
                if ($(this).val().trim() !== '') {
                    $(this).removeClass('is-invalid');
                }
            });
            
            // Google login handler (placeholder)
            $('.btn-google').click(function() {
                // In a real implementation, this would redirect to Google OAuth
                alert('Google login would be implemented here. This would redirect to Google OAuth for authentication.');
            });
            
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
        
        // Add shake animation for form errors
        document.head.insertAdjacentHTML('beforeend', `
            <style>
                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    10%, 30%, 50%, 70%, 90% { transform: translateX(-10px); }
                    20%, 40%, 60%, 80% { transform: translateX(10px); }
                }
                
                .is-invalid {
                    border: 1px solid #ff3e85 !important;
                    box-shadow: 0 0 0 3px rgba(255, 62, 133, 0.2) !important;
                }
            </style>
        `);
    </script>
</body>
</html>