<?php
session_start();
require_once 'config.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : 'user';
$username = $isLoggedIn ? $_SESSION['username'] : '';

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? 'all';
$quality = $_GET['quality'] ?? 'all';
$platform = $_GET['platform'] ?? 'all';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build search query
$whereConditions = ["v.status = 'completed'"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(v.title LIKE ? OR v.description LIKE ? OR u.username LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if ($category !== 'all') {
    $whereConditions[] = "v.category = ?";
    $params[] = $category;
}

if ($quality !== 'all') {
    $whereConditions[] = "v.quality = ?";
    $params[] = $quality;
}

if ($platform !== 'all') {
    $whereConditions[] = "v.platform = ?";
    $params[] = $platform;
}

$whereClause = implode(' AND ', $whereConditions);

// Determine sort order
$orderBy = match($sort) {
    'oldest' => 'v.created_at ASC',
    'most_viewed' => 'v.view_count DESC',
    'longest' => 'v.duration DESC',
    'shortest' => 'v.duration ASC',
    default => 'v.created_at DESC'
};

try {
    // Get total count for pagination
    $countQuery = "
        SELECT COUNT(*) 
        FROM videos v 
        JOIN users u ON v.uploaded_by = u.id 
        WHERE $whereClause
    ";
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalVideos = $stmt->fetchColumn();
    $totalPages = ceil($totalVideos / $perPage);

    // Get videos
    $videosQuery = "
        SELECT v.*, u.username as uploader_name, t.name as tournament_name,
               CASE 
                   WHEN v.tournament_id IS NOT NULL THEN t.name
                   WHEN v.match_id IS NOT NULL THEN CONCAT('Match #', v.match_id)
                   ELSE 'General'
               END as event_name
        FROM videos v
        JOIN users u ON v.uploaded_by = u.id
        LEFT JOIN tournaments t ON v.tournament_id = t.id
        WHERE $whereClause
        ORDER BY $orderBy
        LIMIT $perPage OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($videosQuery);
    $stmt->execute($params);
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get categories for filter
    $categories = $pdo->query("
        SELECT category, COUNT(*) as count 
        FROM videos 
        WHERE status = 'completed' 
        GROUP BY category 
        ORDER BY count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $videos = [];
    $categories = [];
    $totalVideos = 0;
    $totalPages = 0;
}

function formatDuration($seconds) {
    if (!$seconds) return 'N/A';
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;
    
    if ($hours > 0) {
        return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
    }
    return sprintf('%d:%02d', $minutes, $seconds);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VOD Library - ML HUB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --primary-color: #6f42c1;
            --secondary-color: #20c997;
            --dark-color: #212529;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #0f0f0f;
            color: #ffffff;
        }
        
        .navbar {
            background-color: var(--dark-color);
        }
        
        .search-section {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            padding: 40px 0;
            margin-bottom: 30px;
        }
        
        .search-form {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
        }
        
        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(111, 66, 193, 0.25);
            color: white;
        }
        
        .form-select option {
            background: #333;
            color: white;
        }
        
        .vod-card {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .vod-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }
        
        .vod-thumbnail {
            position: relative;
            overflow: hidden;
            height: 200px;
        }
        
        .vod-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .vod-card:hover .vod-thumbnail img {
            transform: scale(1.05);
        }
        
        .duration-badge {
            position: absolute;
            bottom: 8px;
            right: 8px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 4px 8px;
            border-radius: 5px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .quality-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            background: var(--secondary-color);
            color: white;
            padding: 4px 8px;
            border-radius: 5px;
            font-size: 0.7rem;
            font-weight: bold;
        }
        
        .platform-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            padding: 4px 8px;
            border-radius: 5px;
            font-size: 0.7rem;
            font-weight: bold;
        }
        
        .platform-youtube { background: #ff0000; }
        .platform-twitch { background: #9146ff; }
        .platform-facebook { background: #1877f2; }
        .platform-native { background: var(--primary-color); }
        
        .stats-row {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .filter-sidebar {
            background: #1a1a1a;
            border-radius: 10px;
            padding: 20px;
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        
        .pagination .page-link {
            background: #333;
            border-color: #555;
            color: white;
        }
        
        .pagination .page-link:hover {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .pagination .page-item.active .page-link {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #888;
        }
        
        .category-filter {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .category-btn {
            background: #333;
            border: 1px solid #555;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .category-btn.active {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .category-btn:hover {
            background: var(--primary-color);
            color: white;
            text-decoration: none;
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
                    <li class="nav-item">
                        <a class="nav-link" href="streaming_hub.php">
                            <i class="fas fa-broadcast-tower me-1"></i>Streaming Hub
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="vod_library.php">
                            <i class="fas fa-video me-1"></i>VOD Library
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="tournaments.php">
                            <i class="fas fa-trophy me-1"></i>Tournaments
                        </a>
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
                                <li><a class="dropdown-item" href="user_dashboard.php">Dashboard</a></li>
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
    <section class="search-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="search-form">
                        <h2 class="text-center mb-4">
                            <i class="fas fa-video me-2"></i>VOD Library
                        </h2>
                        <form method="GET" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <input type="text" class="form-control" name="search" 
                                           placeholder="Search videos, tournaments, players..." 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <select class="form-select" name="sort">
                                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                        <option value="most_viewed" <?php echo $sort === 'most_viewed' ? 'selected' : ''; ?>>Most Viewed</option>
                                        <option value="longest" <?php echo $sort === 'longest' ? 'selected' : ''; ?>>Longest</option>
                                        <option value="shortest" <?php echo $sort === 'shortest' ? 'selected' : ''; ?>>Shortest</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search me-1"></i>Search
                                    </button>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <select class="form-select" name="category">
                                        <option value="all" <?php echo $category === 'all' ? 'selected' : ''; ?>>All Categories</option>
                                        <option value="tournament" <?php echo $category === 'tournament' ? 'selected' : ''; ?>>Tournaments</option>
                                        <option value="live_match" <?php echo $category === 'live_match' ? 'selected' : ''; ?>>Live Matches</option>
                                        <option value="highlight" <?php echo $category === 'highlight' ? 'selected' : ''; ?>>Highlights</option>
                                        <option value="vod" <?php echo $category === 'vod' ? 'selected' : ''; ?>>VODs</option>
                                        <option value="scrim" <?php echo $category === 'scrim' ? 'selected' : ''; ?>>Scrims</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <select class="form-select" name="quality">
                                        <option value="all" <?php echo $quality === 'all' ? 'selected' : ''; ?>>All Qualities</option>
                                        <option value="4k" <?php echo $quality === '4k' ? 'selected' : ''; ?>>4K</option>
                                        <option value="1440p" <?php echo $quality === '1440p' ? 'selected' : ''; ?>>1440p</option>
                                        <option value="1080p" <?php echo $quality === '1080p' ? 'selected' : ''; ?>>1080p</option>
                                        <option value="720p" <?php echo $quality === '720p' ? 'selected' : ''; ?>>720p</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <select class="form-select" name="platform">
                                        <option value="all" <?php echo $platform === 'all' ? 'selected' : ''; ?>>All Platforms</option>
                                        <option value="youtube" <?php echo $platform === 'youtube' ? 'selected' : ''; ?>>YouTube</option>
                                        <option value="twitch" <?php echo $platform === 'twitch' ? 'selected' : ''; ?>>Twitch</option>
                                        <option value="facebook" <?php echo $platform === 'facebook' ? 'selected' : ''; ?>>Facebook</option>
                                        <option value="native" <?php echo $platform === 'native' ? 'selected' : ''; ?>>Native</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="vod_library.php" class="btn btn-outline-light w-100">
                                        <i class="fas fa-times me-1"></i>Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Results Section -->
    <div class="container">
        <!-- Stats Row -->
        <div class="stats-row">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-0">
                        <i class="fas fa-video me-2"></i>
                        <?php echo number_format($totalVideos); ?> Videos Found
                        <?php if (!empty($search)): ?>
                            for "<?php echo htmlspecialchars($search); ?>"
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="col-md-6 text-md-end">
                    <small class="text-muted">
                        Page <?php echo $page; ?> of <?php echo max(1, $totalPages); ?>
                    </small>
                </div>
            </div>
        </div>

        <!-- Category Quick Filters -->
        <?php if (!empty($categories)): ?>
            <div class="category-filter">
                <a href="?<?php echo http_build_query(array_merge($_GET, ['category' => 'all'])); ?>" 
                   class="category-btn <?php echo $category === 'all' ? 'active' : ''; ?>">
                    All
                </a>
                <?php foreach ($categories as $cat): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['category' => $cat['category']])); ?>" 
                       class="category-btn <?php echo $category === $cat['category'] ? 'active' : ''; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $cat['category'])); ?> (<?php echo $cat['count']; ?>)
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Videos Grid -->
        <?php if (!empty($videos)): ?>
            <div class="row">
                <?php foreach ($videos as $video): ?>
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <div class="vod-card">
                            <div class="vod-thumbnail">
                                <img src="<?php echo $video['thumbnail_url'] ?: 'https://via.placeholder.com/400x200/333/fff?text=Video'; ?>" 
                                     alt="<?php echo htmlspecialchars($video['title']); ?>">
                                
                                <div class="quality-badge"><?php echo $video['quality']; ?></div>
                                
                                <?php if ($video['duration']): ?>
                                    <div class="duration-badge"><?php echo formatDuration($video['duration']); ?></div>
                                <?php endif; ?>
                                
                                <div class="platform-badge platform-<?php echo $video['platform']; ?>">
                                    <?php echo strtoupper($video['platform']); ?>
                                </div>
                            </div>
                            
                            <div class="card-body p-3">
                                <h6 class="card-title text-white mb-2">
                                    <?php echo htmlspecialchars(substr($video['title'], 0, 60)) . (strlen($video['title']) > 60 ? '...' : ''); ?>
                                </h6>
                                
                                <p class="text-muted small mb-2">
                                    <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($video['uploader_name']); ?>
                                </p>
                                
                                <?php if ($video['event_name'] && $video['event_name'] !== 'General'): ?>
                                    <p class="text-warning small mb-2">
                                        <i class="fas fa-trophy me-1"></i><?php echo htmlspecialchars($video['event_name']); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small class="text-muted">
                                        <i class="fas fa-eye me-1"></i><?php echo number_format($video['view_count']); ?>
                                    </small>
                                    <small class="text-muted">
                                        <?php echo date('M j, Y', strtotime($video['created_at'])); ?>
                                    </small>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <a href="watch_vod.php?id=<?php echo $video['id']; ?>" 
                                       class="btn btn-primary btn-sm flex-fill">
                                        <i class="fas fa-play me-1"></i>Watch
                                    </a>
                                    <button class="btn btn-outline-light btn-sm" 
                                            onclick="addToWatchLater(<?php echo $video['id']; ?>)"
                                            title="Add to Watch Later">
                                        <i class="fas fa-bookmark"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="VOD pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="no-results">
                <i class="fas fa-video fa-4x mb-3"></i>
                <h4>No Videos Found</h4>
                <p>Try adjusting your search criteria or browse all videos.</p>
                <a href="vod_library.php" class="btn btn-primary">
                    <i class="fas fa-refresh me-1"></i>View All Videos
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4 mt-5">
        <div class="container">
            <p>&copy; 2024 ML HUB Esports Platform. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Watch Later functionality
        function addToWatchLater(videoId) {
            let watchLater = JSON.parse(localStorage.getItem('watchLater') || '[]');
            
            if (!watchLater.includes(videoId)) {
                watchLater.push(videoId);
                localStorage.setItem('watchLater', JSON.stringify(watchLater));
                showNotification('Added to Watch Later!');
            } else {
                showNotification('Already in Watch Later!');
            }
        }

        function showNotification(message) {
            const notification = document.createElement('div');
            notification.className = 'alert alert-success position-fixed';
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 250px;';
            notification.innerHTML = `
                <i class="fas fa-check-circle me-2"></i>${message}
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 3000);
        }

        // Auto-submit form on filter change
        document.querySelectorAll('select[name="category"], select[name="quality"], select[name="platform"], select[name="sort"]').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Search suggestions (basic implementation)
        const searchInput = document.querySelector('input[name="search"]');
        let searchTimeout;

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length >= 3) {
                searchTimeout = setTimeout(() => {
                    // Could implement AJAX search suggestions here
                    console.log('Search suggestions for:', query);
                }, 300);
            }
        });
    </script>
</body>
</html>
