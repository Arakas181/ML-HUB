<?php
require_once 'config.php';

try {
    // Check if super admin exists
    $checkSuperAdmin = $pdo->prepare("SELECT id FROM users WHERE role = 'super_admin'");
    $checkSuperAdmin->execute();
    
    if ($checkSuperAdmin->rowCount() == 0) {
        // Create super admin user
        $password = password_hash('password', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'super_admin')");
        $stmt->execute(['superadmin', 'superadmin@esports.com', $password]);
        echo "Super admin user created successfully!<br>";
        echo "Username: superadmin<br>";
        echo "Password: password<br>";
        echo "<a href='login.php'>Login here</a>";
    } else {
        echo "Super admin user already exists.<br>";
        echo "Username: superadmin<br>";
        echo "Password: password<br>";
        echo "<a href='login.php'>Login here</a>";
    }
    
    // Also check current user role if logged in
    session_start();
    if (isset($_SESSION['user_id'])) {
        echo "<br><br>Current user: " . $_SESSION['username'] . "<br>";
        echo "Current role: " . $_SESSION['role'] . "<br>";
        
        if ($_SESSION['role'] === 'super_admin') {
            echo "<a href='super_admin_dashboard.php'>Go to Super Admin Dashboard</a>";
        } else {
            echo "You need to login as super admin to access the super admin dashboard.";
        }
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
