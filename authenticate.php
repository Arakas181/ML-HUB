<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Validate input
    if (empty($identifier) || empty($password)) {
        header('Location: login.php?error=empty_fields');
        exit();
    }
    
    try {
        // Lookup by username OR email; do not trust submitted role
        $stmt = $pdo->prepare("SELECT id, username, password, role, email FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Login successful - set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['login_time'] = time();
            
            // Redirect based on role
            switch ($user['role']) {
                case 'admin':
                case 'super_admin':
                    header('Location: admin_dashboard.php');
                    break;
                case 'squad_leader':
                    header('Location: squad_leader_dashboard.php');
                    break;
                case 'user':
                    header('Location: user_dashboard.php');
                    break;
                default:
                    header('Location: index.php');
            }
            exit();
        } else {
            // Invalid credentials
            header('Location: login.php?error=invalid_credentials');
            exit();
        }
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        header('Location: login.php?error=database_error');
        exit();
    }
} else {
    // If not POST request, redirect to login
    header('Location: login.php');
    exit();
}
?>