<?php
session_start();
require_once 'config.php';

// Create videos table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS videos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        video_url VARCHAR(500) NOT NULL,
        thumbnail_url VARCHAR(500),
        category ENUM('live_stream', 'live_match', 'scrim', 'tournament') NOT NULL,
        status ENUM('live', 'completed', 'scheduled') DEFAULT 'completed',
        view_count INT DEFAULT 0,
        duration INT,
        uploaded_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
    )");
} catch (PDOException $e) {
    // Table might already exist
}

$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : 'user';
$username = $isLoggedIn ? $_SESSION['username'] : '';

// Handle search
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchCondition = '';
$searchParams = [];

if (!empty($searchTerm)) {
    $searchCondition = "AND (title LIKE ? OR description LIKE ?)";
    $searchParams = ["%$searchTerm%", "%$searchTerm%"];
}

// Get live matches
try {
    $stmt = $pdo->prepare("
        SELECT v.*, u.username as uploader_name 
        FROM videos v 
        JOIN users u ON v.uploaded_by = u.id 
        WHERE v.category = 'live_match' $searchCondition
        ORDER BY v.status = 'live' DESC, v.created_at DESC
    ");
    $stmt->execute($searchParams);
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $videos = [];
}

// Handle video upload (admin/super_admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_video']) && ($userRole === 'admin' || $userRole === 'super_admin')) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $video_url = trim($_POST['video_url']);
    $status = $_POST['status'];
    
    if (!empty($title) && !empty($video_url)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO videos (title, description, video_url, category, status, uploaded_by) VALUES (?, ?, ?, 'live_match', ?, ?)");
            $stmt->execute([$title, $description, $video_url, $status, $_SESSION['user_id']]);
            header('Location: watch_live_match.php');
            exit();
        } catch (PDOException $e) {
            $error = "Error adding video: " . $e->getMessage();
        }
    }
}

function getYouTubeEmbedUrl($url) {
    $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/';
    preg_match($pattern, $url, $matches);
    if (isset($matches[1])) {
        return "https://www.youtube.com/embed/" . $matches[1];
    }
    return $url;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Watch Live Match - ML HUB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        :root {
            --primary-color: #6f42c1;
            --secondary-color: #20c997;
            --dark-color: #212529;
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
        
        .video-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            background: white;
        }
        
        .video-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .video-thumbnail {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
        }
        
        .video-thumbnail iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 0;
        }
        
        .live-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: linear-gradient(45deg, #ff0000, #cc0000);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
            animation: pulse 2s infinite;
        }
        
        .match-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: linear-gradient(45deg, #ffc107, #e0a800);
            color: #212529;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .search-container {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            padding: 40px 0;
            color: white;
        }
        
        .search-box {
            border-radius: 25px;
            border: none;
            padding: 12px 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .btn-search {
            border-radius: 25px;
            padding: 12px 25px;
            background: white;
            color: #ff6b6b;
            border: none;
            font-weight: bold;
        }
        
        .btn-search:hover {
            background: #f8f9fa;
            color: #ff6b6b;
        }
        
        .admin-controls {
            background: linear-gradient(135deg, #28a745, #20c997);
            border-radius: 15px;
            padding: 20px;
            color: white;
            margin-bottom: 30px;
        }
        
        .video-meta {
            padding: 20px;
        }
        
        .video-title {
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        
        .video-description {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .video-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: #999;
        }
        
        .status-live {
            color: #dc3545;
            font-weight: bold;
        }
        
        .status-completed {
            color: #28a745;
        }
        
        .status-scheduled {
            color: #ffc107;
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
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home me-1"></i>Home
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-play-circle me-1"></i>Watch
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="watch_live_stream.php">
                                <i class="fas fa-broadcast-tower me-2"></i>Live Stream
                            </a></li>
                            <li><a class="dropdown-item active" href="watch_live_match.php">
                                <i class="fas fa-trophy me-2"></i>Live Match
                            </a></li>
                            <li><a class="dropdown-item" href="watch_scrim.php">
                                <i class="fas fa-sword me-2"></i>Scrim
                            </a></li>
                            <li><a class="dropdown-item" href="watch_tournament.php">
                                <i class="fas fa-crown me-2"></i>Tournament
                            </a></li>
                        </ul>
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
                                <?php elseif ($userRole === 'squad_leader'): ?>
                                    <li><a class="dropdown-item" href="squad_leader_dashboard.php">Squad Leader Dashboard</a></li>
                                    <li><a class="dropdown-item" href="user_dashboard.php">User Dashboard</a></li>
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

    <!-- Search Section -->
    <div class="search-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h1 class="text-center mb-4">
                        <i class="fas fa-trophy me-3"></i>Live Matches
                    </h1>
                    <form method="GET" class="d-flex gap-3">
                        <input type="text" name="search" class="form-control search-box" 
                               placeholder="Search live matches..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                        <button type="submit" class="btn btn-search">
                            <i class="fas fa-search me-2"></i>Search
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="container my-5">
        <!-- Admin Controls -->
        <?php if ($userRole === 'admin' || $userRole === 'super_admin'): ?>
        <div class="admin-controls">
            <h4><i class="fas fa-plus-circle me-2"></i>Add New Live Match</h4>
            <form method="POST" class="row g-3">
                <div class="col-md-6">
                    <input type="text" name="title" class="form-control" placeholder="Match Title (e.g., Team A vs Team B)" required>
                </div>
                <div class="col-md-6">
                    <select name="status" class="form-select" required>
                        <option value="live">Live Now</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div class="col-12">
                    <input type="url" name="video_url" class="form-control" placeholder="YouTube URL" required>
                </div>
                <div class="col-12">
                    <textarea name="description" class="form-control" rows="2" placeholder="Match Description"></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" name="add_video" class="btn btn-light">
                        <i class="fas fa-plus me-2"></i>Add Match
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Videos Grid -->
        <div class="row">
            <?php if (empty($videos)): ?>
                <div class="col-12 text-center py-5">
                    <i class="fas fa-trophy fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">No live matches found</h4>
                    <p class="text-muted">
                        <?php if (!empty($searchTerm)): ?>
                            Try adjusting your search terms.
                        <?php else: ?>
                            Check back later for new matches!
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($videos as $video): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card video-card h-100">
                        <div class="video-thumbnail">
                            <?php if ($video['status'] === 'live'): ?>
                                <div class="live-badge">
                                    <i class="fas fa-circle me-1"></i>LIVE
                                </div>
                            <?php endif; ?>
                            <div class="match-badge">
                                <i class="fas fa-trophy me-1"></i>MATCH
                            </div>
                            <iframe src="<?php echo getYouTubeEmbedUrl($video['video_url']); ?>" 
                                    allowfullscreen></iframe>
                        </div>
                        <div class="video-meta">
                            <div class="video-title"><?php echo htmlspecialchars($video['title']); ?></div>
                            <?php if (!empty($video['description'])): ?>
                                <div class="video-description"><?php echo htmlspecialchars(substr($video['description'], 0, 100)) . (strlen($video['description']) > 100 ? '...' : ''); ?></div>
                            <?php endif; ?>
                            <div class="video-stats">
                                <span>
                                    <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($video['uploader_name']); ?>
                                </span>
                                <span class="status-<?php echo $video['status']; ?>">
                                    <?php echo ucfirst($video['status']); ?>
                                </span>
                            </div>
                            <div class="video-stats mt-2">
                                <span>
                                    <i class="fas fa-eye me-1"></i><?php echo number_format($video['view_count']); ?> views
                                </span>
                                <span>
                                    <i class="fas fa-calendar me-1"></i><?php echo date('M j, Y', strtotime($video['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p>&copy; 2024 ML HUB. All rights reserved.</p>
        </div>
    </footer>
    <!-- Chat Integration -->
    <?php if ($isLoggedIn): ?>
    <script src="polling_chat_client.js"></script>
    <script>
        // Initialize chat for live match page
        document.addEventListener('DOMContentLoaded', function() {
            const userId = <?= $_SESSION['user_id'] ?>;
            const username = '<?= htmlspecialchars($_SESSION['username']) ?>';
            const userRole = '<?= htmlspecialchars($_SESSION['role']) ?>';
            
            const chatClient = new PollingChatClient('chat_api.php', 1, userId, username, userRole);
            // Chat UI can be added to specific containers as needed
        });
    </script>
    <?php endif; ?>
</body>
</html>
