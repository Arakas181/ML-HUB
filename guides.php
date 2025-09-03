<?php
session_start();
require_once 'config.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : 'user';
$username = $isLoggedIn ? $_SESSION['username'] : '';

// Fetch guides from database
try {
    $guides = $pdo->query("
        SELECT c.*, u.username as author_name 
        FROM content c 
        JOIN users u ON c.author_id = u.id 
        WHERE c.status = 'published' AND c.type = 'guide'
        ORDER BY c.published_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get guide categories if they exist
    $categories = $pdo->query("
        SELECT DISTINCT category FROM content 
        WHERE type = 'guide' AND status = 'published' AND category IS NOT NULL
    ")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $guides = [];
    $categories = [];
}

// Filter by category if requested
$selectedCategory = $_GET['category'] ?? '';
if ($selectedCategory && !empty($guides)) {
    $guides = array_filter($guides, function($guide) use ($selectedCategory) {
        return $guide['category'] === $selectedCategory;
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gaming Guides - Esports Platform</title>
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
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .guide-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('https://images.unsplash.com/photo-1542751371-adc38448a05e?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1170&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 80px 0;
            text-align: center;
        }
        
        .category-filter {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .category-btn {
            margin: 5px;
            border-radius: 20px;
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
                        <a class="nav-link active" href="guides.php">Guides</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <?php if ($isLoggedIn): ?>
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
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-light me-2">Login</a>
                        <a href="register.php" class="btn btn-primary">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <h1 class="display-4 fw-bold mb-4">Gaming Guides & Tutorials</h1>
            <p class="lead mb-4">Master your favorite games with expert tips, strategies, and tutorials</p>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container mt-5">
        <!-- Category Filter -->
        <?php if (!empty($categories)): ?>
        <div class="category-filter mb-4">
            <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Filter by Category</h5>
            <a href="guides.php" class="btn btn-outline-primary category-btn <?php echo empty($selectedCategory) ? 'active' : ''; ?>">
                All Categories
            </a>
            <?php foreach ($categories as $category): ?>
                <a href="guides.php?category=<?php echo urlencode($category); ?>" 
                   class="btn btn-outline-primary category-btn <?php echo $selectedCategory === $category ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($category); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Guides Grid -->
        <div class="row">
            <?php if (empty($guides)): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle me-2"></i>
                        <?php if ($selectedCategory): ?>
                            No guides found in the "<?php echo htmlspecialchars($selectedCategory); ?>" category.
                        <?php else: ?>
                            No guides available at the moment.
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($guides as $guide): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card guide-card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($guide['title']); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars(substr($guide['content'], 0, 150)) . '...'; ?></p>
                                
                                <?php if (!empty($guide['category'])): ?>
                                    <div class="mb-3">
                                        <span class="badge bg-light text-dark"><?php echo htmlspecialchars($guide['category']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-light">
                                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($guide['author_name']); ?>
                                    </small>
                                    <small class="text-light">
                                        <i class="fas fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($guide['published_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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