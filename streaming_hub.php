<?php
session_start();
require_once 'config.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : 'user';
$username = $isLoggedIn ? $_SESSION['username'] : '';

// Fetch live streams
try {
    $liveStreams = $pdo->query("
        SELECT ls.*, u.username as streamer_name, t.name as tournament_name, m.team1_id, m.team2_id,
               t1.name as team1_name, t2.name as team2_name
        FROM live_streams ls
        JOIN users u ON ls.streamer_id = u.id
        LEFT JOIN tournaments t ON ls.tournament_id = t.id
        LEFT JOIN matches m ON ls.match_id = m.id
        LEFT JOIN teams t1 ON m.team1_id = t1.id
        LEFT JOIN teams t2 ON m.team2_id = t2.id
        WHERE ls.status = 'live'
        ORDER BY ls.viewer_count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch featured VODs
    $featuredVods = $pdo->query("
        SELECT v.*, u.username as uploader_name, t.name as tournament_name
        FROM videos v
        JOIN users u ON v.uploaded_by = u.id
        LEFT JOIN tournaments t ON v.tournament_id = t.id
        WHERE v.is_featured = 1 AND v.status = 'completed'
        ORDER BY v.created_at DESC
        LIMIT 6
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch recent VODs
    $recentVods = $pdo->query("
        SELECT v.*, u.username as uploader_name, t.name as tournament_name
        FROM videos v
        JOIN users u ON v.uploaded_by = u.id
        LEFT JOIN tournaments t ON v.tournament_id = t.id
        WHERE v.status = 'completed'
        ORDER BY v.created_at DESC
        LIMIT 12
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $liveStreams = [];
    $featuredVods = [];
    $recentVods = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Streaming Hub - ML HUB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --primary-color: #6f42c1;
            --secondary-color: #20c997;
            --dark-color: #212529;
            --light-color: #f8f9fa;
            --twitch-color: #9146ff;
            --youtube-color: #ff0000;
            --facebook-color: #1877f2;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #0f0f0f;
            color: #ffffff;
        }
        
        .navbar {
            background-color: var(--dark-color);
        }
        
        .navbar-brand {
            font-weight: bold;
            color: var(--secondary-color) !important;
        }
        
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('https://images.unsplash.com/photo-1560472354-b33ff0c44a43?ixlib=rb-4.0.3');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 80px 0;
            text-align: center;
        }
        
        .stream-card {
            background: linear-gradient(145deg, #1a1a1a 0%, #2d2d2d 100%);
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
        }
        
        .stream-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }
        
        .stream-thumbnail {
            position: relative;
            overflow: hidden;
        }
        
        .stream-thumbnail img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .stream-card:hover .stream-thumbnail img {
            transform: scale(1.05);
        }
        
        .live-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: linear-gradient(45deg, #ff0000, #ff4444);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .viewer-count {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        
        .platform-badge {
            position: absolute;
            bottom: 10px;
            right: 10px;
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: bold;
        }
        
        .platform-twitch { background-color: var(--twitch-color); }
        .platform-youtube { background-color: var(--youtube-color); }
        .platform-facebook { background-color: var(--facebook-color); }
        .platform-multi { background: linear-gradient(45deg, var(--twitch-color), var(--youtube-color), var(--facebook-color)); }
        
        .vod-card {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .vod-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-3px);
        }
        
        .vod-thumbnail {
            position: relative;
            overflow: hidden;
        }
        
        .vod-thumbnail img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        
        .duration-badge {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 2px 6px;
            border-radius: 5px;
            font-size: 0.7rem;
        }
        
        .quality-badge {
            position: absolute;
            top: 5px;
            left: 5px;
            background: var(--secondary-color);
            color: white;
            padding: 2px 6px;
            border-radius: 5px;
            font-size: 0.7rem;
            font-weight: bold;
        }
        
        .multi-stream-btn {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            border: none;
            color: white;
            padding: 12px 24px;
            border-radius: 25px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .multi-stream-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(111, 66, 193, 0.4);
        }
        
        .section-title {
            color: var(--secondary-color);
            font-weight: bold;
            margin-bottom: 30px;
            position: relative;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 50px;
            height: 3px;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
        }
        
        .filter-tabs {
            background: #1a1a1a;
            border-radius: 10px;
            padding: 5px;
            margin-bottom: 30px;
        }
        
        .filter-tab {
            background: transparent;
            border: none;
            color: #888;
            padding: 10px 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .filter-tab.active {
            background: var(--primary-color);
            color: white;
        }
        
        .search-container {
            position: relative;
            margin-bottom: 30px;
        }
        
        .search-input {
            background: #1a1a1a;
            border: 1px solid #333;
            color: white;
            padding: 12px 50px 12px 20px;
            border-radius: 25px;
            width: 100%;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(111, 66, 193, 0.1);
        }
        
        .search-btn {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: var(--primary-color);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
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
                        <a class="nav-link active" href="streaming_hub.php">
                            <i class="fas fa-broadcast-tower me-1"></i>Streaming Hub
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="multi_stream.php">
                            <i class="fas fa-th me-1"></i>Multi-Stream
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="vod_library.php">
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
            <h1 class="display-4 fw-bold mb-4">Live Streaming Hub</h1>
            <p class="lead mb-4">Watch live matches, tournaments, and esports content from multiple platforms</p>
            <div class="d-flex justify-content-center gap-3">
                <a href="#live-streams" class="btn btn-primary btn-lg">
                    <i class="fas fa-broadcast-tower me-2"></i>Live Streams
                </a>
                <button class="multi-stream-btn btn-lg" onclick="window.location.href='multi_stream.php'">
                    <i class="fas fa-th me-2"></i>Multi-Stream View
                </button>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container mt-5">
        <!-- Search Section -->
        <div class="search-container">
            <input type="text" class="search-input" placeholder="Search streams, tournaments, or players..." id="searchInput">
            <button class="search-btn" onclick="performSearch()">
                <i class="fas fa-search"></i>
            </button>
        </div>

        <!-- Live Streams Section -->
        <section id="live-streams" class="mb-5">
            <h2 class="section-title">
                <i class="fas fa-broadcast-tower me-2"></i>Live Streams
                <span class="badge bg-danger ms-2"><?php echo count($liveStreams); ?> LIVE</span>
            </h2>
            
            <?php if (!empty($liveStreams)): ?>
                <div class="row">
                    <?php foreach ($liveStreams as $stream): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="stream-card">
                                <div class="stream-thumbnail">
                                    <img src="<?php echo $stream['thumbnail_url'] ?: 'https://via.placeholder.com/400x200/333/fff?text=Live+Stream'; ?>" alt="<?php echo htmlspecialchars($stream['title']); ?>">
                                    <div class="live-badge">LIVE</div>
                                    <div class="viewer-count">
                                        <i class="fas fa-eye me-1"></i><?php echo number_format($stream['viewer_count']); ?>
                                    </div>
                                    <div class="platform-badge platform-<?php echo $stream['platform']; ?>">
                                        <?php echo strtoupper($stream['platform']); ?>
                                    </div>
                                </div>
                                <div class="card-body p-3">
                                    <h5 class="card-title text-white mb-2"><?php echo htmlspecialchars($stream['title']); ?></h5>
                                    <p class="text-muted mb-2">
                                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($stream['streamer_name']); ?>
                                    </p>
                                    <?php if ($stream['tournament_name']): ?>
                                        <p class="text-warning mb-2">
                                            <i class="fas fa-trophy me-1"></i><?php echo htmlspecialchars($stream['tournament_name']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($stream['team1_name'] && $stream['team2_name']): ?>
                                        <p class="text-info mb-2">
                                            <?php echo htmlspecialchars($stream['team1_name']); ?> vs <?php echo htmlspecialchars($stream['team2_name']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <div class="d-flex gap-2">
                                        <a href="watch_stream.php?id=<?php echo $stream['id']; ?>" class="btn btn-primary btn-sm flex-fill">
                                            <i class="fas fa-play me-1"></i>Watch
                                        </a>
                                        <button class="btn btn-outline-light btn-sm" onclick="addToMultiStream(<?php echo $stream['id']; ?>)">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-broadcast-tower fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">No Live Streams</h4>
                    <p class="text-muted">Check back later for live esports action!</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Featured VODs Section -->
        <section class="mb-5">
            <h2 class="section-title">
                <i class="fas fa-star me-2"></i>Featured Content
            </h2>
            
            <?php if (!empty($featuredVods)): ?>
                <div class="row">
                    <?php foreach ($featuredVods as $vod): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="vod-card">
                                <div class="vod-thumbnail">
                                    <img src="<?php echo $vod['thumbnail_url'] ?: 'https://via.placeholder.com/400x150/333/fff?text=Video'; ?>" alt="<?php echo htmlspecialchars($vod['title']); ?>">
                                    <div class="quality-badge"><?php echo $vod['quality']; ?></div>
                                    <?php if ($vod['duration']): ?>
                                        <div class="duration-badge"><?php echo gmdate('H:i:s', $vod['duration']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body p-3">
                                    <h6 class="card-title text-white mb-2"><?php echo htmlspecialchars($vod['title']); ?></h6>
                                    <p class="text-muted small mb-2">
                                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($vod['uploader_name']); ?>
                                        <span class="ms-2">
                                            <i class="fas fa-eye me-1"></i><?php echo number_format($vod['view_count']); ?>
                                        </span>
                                    </p>
                                    <a href="watch_vod.php?id=<?php echo $vod['id']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-play me-1"></i>Watch
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-muted">No featured content available.</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Recent VODs Section -->
        <section class="mb-5">
            <h2 class="section-title">
                <i class="fas fa-clock me-2"></i>Recent Videos
            </h2>
            
            <!-- Filter Tabs -->
            <div class="filter-tabs d-flex gap-2 mb-4">
                <button class="filter-tab active" onclick="filterVods('all')">All</button>
                <button class="filter-tab" onclick="filterVods('tournament')">Tournaments</button>
                <button class="filter-tab" onclick="filterVods('live_match')">Matches</button>
                <button class="filter-tab" onclick="filterVods('highlight')">Highlights</button>
                <button class="filter-tab" onclick="filterVods('vod')">VODs</button>
            </div>
            
            <?php if (!empty($recentVods)): ?>
                <div class="row" id="vodContainer">
                    <?php foreach ($recentVods as $vod): ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-3 vod-item" data-category="<?php echo $vod['category']; ?>">
                            <div class="vod-card">
                                <div class="vod-thumbnail">
                                    <img src="<?php echo $vod['thumbnail_url'] ?: 'https://via.placeholder.com/300x150/333/fff?text=Video'; ?>" alt="<?php echo htmlspecialchars($vod['title']); ?>">
                                    <div class="quality-badge"><?php echo $vod['quality']; ?></div>
                                    <?php if ($vod['duration']): ?>
                                        <div class="duration-badge"><?php echo gmdate('H:i:s', $vod['duration']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body p-2">
                                    <h6 class="card-title text-white mb-1 small"><?php echo htmlspecialchars(substr($vod['title'], 0, 50)) . (strlen($vod['title']) > 50 ? '...' : ''); ?></h6>
                                    <p class="text-muted small mb-1">
                                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($vod['uploader_name']); ?>
                                    </p>
                                    <p class="text-muted small mb-2">
                                        <i class="fas fa-eye me-1"></i><?php echo number_format($vod['view_count']); ?>
                                        <span class="ms-2"><?php echo date('M j', strtotime($vod['created_at'])); ?></span>
                                    </p>
                                    <a href="watch_vod.php?id=<?php echo $vod['id']; ?>" class="btn btn-outline-primary btn-sm w-100">
                                        <i class="fas fa-play me-1"></i>Watch
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-muted">No videos available.</p>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4 mt-5">
        <div class="container">
            <p>&copy; 2024 ML HUB Esports Platform. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Multi-stream functionality
        let multiStreamList = JSON.parse(localStorage.getItem('multiStreamList') || '[]');

        function addToMultiStream(streamId) {
            if (!multiStreamList.includes(streamId)) {
                multiStreamList.push(streamId);
                localStorage.setItem('multiStreamList', JSON.stringify(multiStreamList));
                
                // Show notification
                showNotification('Stream added to multi-view!');
            } else {
                showNotification('Stream already in multi-view!');
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

        // VOD filtering
        function filterVods(category) {
            const items = document.querySelectorAll('.vod-item');
            const tabs = document.querySelectorAll('.filter-tab');
            
            // Update active tab
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            // Filter items
            items.forEach(item => {
                if (category === 'all' || item.dataset.category === category) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // Search functionality
        function performSearch() {
            const query = document.getElementById('searchInput').value;
            if (query.trim()) {
                window.location.href = `search_results.php?q=${encodeURIComponent(query)}`;
            }
        }

        // Enter key search
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });

        // Auto-refresh live streams every 30 seconds
        setInterval(() => {
            if (document.querySelector('#live-streams')) {
                fetch('api/refresh_live_streams.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update viewer counts
                            data.streams.forEach(stream => {
                                const viewerElement = document.querySelector(`[data-stream-id="${stream.id}"] .viewer-count`);
                                if (viewerElement) {
                                    viewerElement.innerHTML = `<i class="fas fa-eye me-1"></i>${stream.viewer_count.toLocaleString()}`;
                                }
                            });
                        }
                    })
                    .catch(error => console.log('Auto-refresh error:', error));
            }
        }, 30000);
    </script>
    <!-- Chat Integration -->
    <?php if ($isLoggedIn): ?>
    <script src="polling_chat_client.js"></script>
    <script>
        // Initialize chat for streaming hub
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
