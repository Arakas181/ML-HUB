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

// Handle search and filtering
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$roleFilter = isset($_GET['role']) ? $_GET['role'] : 'all';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'chat_rank';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build WHERE clause for search and filtering
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(u.username LIKE ? OR u.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($roleFilter !== 'all') {
    $whereConditions[] = "u.role = ?";
    $params[] = $roleFilter;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Define sorting options
$sortOptions = [
    'chat_rank' => 'chat_count DESC, u.username ASC',
    'username' => 'u.username ASC',
    'role' => 'u.role ASC, chat_count DESC',
    'join_date' => 'u.created_at DESC'
];

$orderBy = isset($sortOptions[$sortBy]) ? $sortOptions[$sortBy] : $sortOptions['chat_rank'];

// Get total count for pagination
$countQuery = "
    SELECT COUNT(DISTINCT u.id) 
    FROM users u 
    LEFT JOIN chat_messages cm ON u.id = cm.user_id 
    $whereClause
";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalPlayers = $countStmt->fetchColumn();
$totalPages = ceil($totalPlayers / $limit);

// Main query to get players with chat statistics
$query = "
    SELECT 
        u.id,
        u.username,
        COALESCE(u.full_name, '') as full_name,
        u.email,
        u.role,
        COALESCE(u.avatar_url, '') as avatar_url,
        COALESCE(u.bio, '') as bio,
        u.created_at,
        COUNT(cm.id) as chat_count,
        RANK() OVER (ORDER BY COUNT(cm.id) DESC) as chat_rank,
        MAX(cm.created_at) as last_chat_activity,
        (SELECT COUNT(*) FROM tournaments t WHERE t.created_by = u.id) as tournaments_created,
        (SELECT s.name FROM squads s WHERE s.leader_id = u.id LIMIT 1) as squad_name
    FROM users u
    LEFT JOIN chat_messages cm ON u.id = cm.user_id
    $whereClause
    GROUP BY u.id, u.username, u.email, u.role, u.created_at
    ORDER BY $orderBy
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get chat activity statistics
$statsQuery = "
    SELECT 
        COUNT(DISTINCT u.id) as total_players,
        COUNT(cm.id) as total_messages
    FROM users u
    LEFT JOIN chat_messages cm ON u.id = cm.user_id
";
$statsStmt = $pdo->query($statsQuery);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Get top chatters
$topChattersQuery = "
    SELECT 
        u.username,
        u.avatar_url,
        u.role,
        COUNT(cm.id) as message_count
    FROM users u
    INNER JOIN chat_messages cm ON u.id = cm.user_id
    GROUP BY u.id, u.username, u.avatar_url, u.role
    ORDER BY message_count DESC
    LIMIT 5
";
$topChattersStmt = $pdo->query($topChattersQuery);
$topChatters = $topChattersStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Players Leaderboard - MLBB Esports Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #6a11cb;
            --secondary-color: #2575fc;
            --accent-gold: #ffd700;
            --dark-bg: #0f172a;
            --card-bg: #1e293b;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(to bottom, #0a0e1a, var(--dark-bg));
            color: var(--text-primary);
            min-height: 100vh;
        }
        
        .navbar {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .navbar-brand {
            font-weight: 800;
            background: linear-gradient(135deg, var(--accent-gold), #ff930f);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 1.8rem;
        }
        
        .card {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            color: var(--text-primary);
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.3);
        }
        
        .rank-badge {
            position: absolute;
            top: -10px;
            left: -10px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
            z-index: 10;
        }
        
        .rank-1 { background: linear-gradient(135deg, var(--accent-gold), #ff930f); }
        .rank-2 { background: linear-gradient(135deg, #c0c0c0, #a8a8a8); }
        .rank-3 { background: linear-gradient(135deg, #cd7f32, #b8860b); }
        .rank-default { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); }
        
        .avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid var(--accent-gold);
            object-fit: cover;
        }
        
        .role-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 600;
        }
        
        .role-super_admin { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .role-admin { background: linear-gradient(135deg, #e67e22, #d35400); }
        .role-squad_leader { background: linear-gradient(135deg, #3498db, #2980b9); }
        .role-moderator { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        .role-user { background: linear-gradient(135deg, #2ecc71, #27ae60); }
        
        .stats-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            color: white;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 50px;
            padding: 8px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(106, 17, 203, 0.4);
        }
        
        .activity-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-left: 8px;
        }
        
        .activity-high { background: #2ecc71; }
        .activity-medium { background: #f39c12; }
        .activity-low { background: #e74c3c; }
        .activity-none { background: #95a5a6; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-crown me-2"></i>MLBB ESPORTS
            </a>
            <div class="d-flex align-items-center">
                <a href="index.php" class="btn btn-outline-light me-2">
                    <i class="fas fa-home me-1"></i>Back to Home
                </a>
                <div class="dropdown">
                    <a class="btn btn-outline-light dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($username); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle me-2"></i>Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h2><i class="fas fa-users me-2"></i>Players Leaderboard</h2>
                <p class="text-muted">Rankings based on chat activity and community engagement</p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <h4><?php echo number_format($stats['total_players']); ?></h4>
                        <p class="mb-0">Total Players</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-comments fa-2x mb-2"></i>
                        <h4><?php echo number_format($stats['total_messages']); ?></h4>
                        <p class="mb-0">Total Messages</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- Search Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search players...">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="role">
                                    <option value="all" <?php echo $roleFilter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                                    <option value="super_admin" <?php echo $roleFilter === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                                    <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="squad_leader" <?php echo $roleFilter === 'squad_leader' ? 'selected' : ''; ?>>Squad Leader</option>
                                    <option value="user" <?php echo $roleFilter === 'user' ? 'selected' : ''; ?>>User</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="sort">
                                    <option value="chat_rank" <?php echo $sortBy === 'chat_rank' ? 'selected' : ''; ?>>Chat Activity</option>
                                    <option value="username" <?php echo $sortBy === 'username' ? 'selected' : ''; ?>>Username</option>
                                    <option value="role" <?php echo $sortBy === 'role' ? 'selected' : ''; ?>>Role</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Players List -->
                <div class="row">
                    <?php if (empty($players)): ?>
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-users-slash fa-3x mb-3 text-muted"></i>
                                <h5>No players found</h5>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <?php foreach ($players as $player): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card position-relative">
                            <!-- Rank Badge -->
                            <div class="rank-badge <?php 
                                if ($player['chat_rank'] == 1) echo 'rank-1';
                                elseif ($player['chat_rank'] == 2) echo 'rank-2';
                                elseif ($player['chat_rank'] == 3) echo 'rank-3';
                                else echo 'rank-default';
                            ?>">
                                #<?php echo $player['chat_rank']; ?>
                            </div>
                            
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <img src="<?php echo $player['avatar_url'] ?: 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80'; ?>" 
                                         alt="<?php echo htmlspecialchars($player['username']); ?>" 
                                         class="avatar me-3">
                                    <div class="flex-grow-1">
                                        <h5 class="mb-1"><?php echo htmlspecialchars($player['username']); ?></h5>
                                        <?php if ($player['full_name']): ?>
                                        <p class="text-muted mb-1"><?php echo htmlspecialchars($player['full_name']); ?></p>
                                        <?php endif; ?>
                                        <span class="badge role-<?php echo $player['role']; ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $player['role'])); ?>
                                        </span>
                                        <?php
                                        $activityClass = 'activity-none';
                                        if ($player['chat_count'] > 100) $activityClass = 'activity-high';
                                        elseif ($player['chat_count'] > 50) $activityClass = 'activity-medium';
                                        elseif ($player['chat_count'] > 0) $activityClass = 'activity-low';
                                        ?>
                                        <span class="activity-indicator <?php echo $activityClass; ?>" title="Activity Level"></span>
                                    </div>
                                </div>
                                
                                <div class="row text-center mb-3">
                                    <div class="col-6">
                                        <strong><?php echo number_format($player['chat_count']); ?></strong>
                                        <div class="text-muted small">Messages</div>
                                    </div>
                                    <div class="col-6">
                                        <strong><?php echo number_format($player['tournaments_created']); ?></strong>
                                        <div class="text-muted small">Tournaments</div>
                                    </div>
                                </div>
                                
                                <?php if ($player['squad_name']): ?>
                                <div class="mb-3">
                                    <small class="text-muted">Squad:</small>
                                    <div class="fw-bold"><?php echo htmlspecialchars($player['squad_name']); ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="mt-3">
                                    <a href="profile.php?id=<?php echo $player['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye me-1"></i>View Profile
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $roleFilter; ?>&sort=<?php echo $sortBy; ?>">Previous</a>
                        </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $roleFilter; ?>&sort=<?php echo $sortBy; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $roleFilter; ?>&sort=<?php echo $sortBy; ?>">Next</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Top Chatters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-fire me-2"></i>Top Chatters</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($topChatters)): ?>
                        <p class="text-muted text-center">No chat activity yet</p>
                        <?php else: ?>
                        <?php foreach ($topChatters as $index => $chatter): ?>
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge <?php 
                                if ($index == 0) echo 'rank-1';
                                elseif ($index == 1) echo 'rank-2';
                                elseif ($index == 2) echo 'rank-3';
                                else echo 'rank-default';
                            ?> me-3"><?php echo $index + 1; ?></span>
                            <img src="<?php echo $chatter['avatar_url'] ?: 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80'; ?>" 
                                 alt="<?php echo htmlspecialchars($chatter['username']); ?>" 
                                 class="rounded-circle me-3" style="width: 40px; height: 40px; object-fit: cover;">
                            <div class="flex-grow-1">
                                <div class="fw-bold"><?php echo htmlspecialchars($chatter['username']); ?></div>
                                <small class="text-muted"><?php echo number_format($chatter['message_count']); ?> messages</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Activity Legend -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Activity Levels</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <span class="activity-indicator activity-high me-2"></span>
                            <small>High (100+ messages)</small>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="activity-indicator activity-medium me-2"></span>
                            <small>Medium (50-99 messages)</small>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="activity-indicator activity-low me-2"></span>
                            <small>Low (1-49 messages)</small>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="activity-indicator activity-none me-2"></span>
                            <small>No Activity</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
