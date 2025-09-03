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

// Handle notification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'mark_read':
                $notificationId = (int)$_POST['notification_id'];
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
                $stmt->execute([$notificationId, $userId]);
                break;
                
            case 'mark_all_read':
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
                $stmt->execute([$userId]);
                break;
                
            case 'delete':
                $notificationId = (int)$_POST['notification_id'];
                $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
                $stmt->execute([$notificationId, $userId]);
                break;
                
            case 'delete_all_read':
                $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = 1");
                $stmt->execute([$userId]);
                break;
        }
        header('Location: notifications.php');
        exit();
    }
}

// Create notifications table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type ENUM('match_reminder', 'tournament_update', 'squad_invitation', 'squad_update', 'admin_notice', 'system_update', 'achievement', 'friend_request') NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            related_id INT NULL,
            related_type ENUM('match', 'tournament', 'squad', 'user', 'notice') NULL,
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_created_at (created_at),
            INDEX idx_is_read (is_read)
        )
    ");
} catch (PDOException $e) {
    error_log("Error creating notifications table: " . $e->getMessage());
}

// Generate sample notifications for demonstration if none exist
$existingCount = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
$existingCount->execute([$userId]);
if ($existingCount->fetchColumn() == 0) {
    $sampleNotifications = [];
    
    // Role-specific sample notifications
    switch ($userRole) {
        case 'super_admin':
        case 'admin':
            $sampleNotifications = [
                ['type' => 'system_update', 'title' => 'System Maintenance Scheduled', 'message' => 'Server maintenance is scheduled for tomorrow at 2:00 AM UTC. Expected downtime: 1 hour.'],
                ['type' => 'admin_notice', 'title' => 'New User Registration Spike', 'message' => '150 new users registered in the last 24 hours. Consider reviewing server capacity.'],
                ['type' => 'tournament_update', 'title' => 'Tournament Approval Required', 'message' => 'Championship Finals tournament is pending your approval for publication.'],
                ['type' => 'admin_notice', 'title' => 'Content Moderation Alert', 'message' => '5 chat messages have been flagged for review in the past hour.']
            ];
            break;
            
        case 'squad_leader':
            $sampleNotifications = [
                ['type' => 'squad_update', 'title' => 'New Squad Member Application', 'message' => 'PlayerX has applied to join your squad "Team Phoenix". Review their application in the squad dashboard.'],
                ['type' => 'tournament_update', 'title' => 'Tournament Registration Open', 'message' => 'M4 World Championship registration is now open. Register your squad before the deadline.'],
                ['type' => 'match_reminder', 'title' => 'Upcoming Squad Match', 'message' => 'Your squad has a match scheduled in 2 hours against Team Dragons.'],
                ['type' => 'squad_update', 'title' => 'Squad Performance Report', 'message' => 'Your squad\'s weekly performance report is now available. Check your statistics and rankings.']
            ];
            break;
            
        default: // regular users
            $sampleNotifications = [
                ['type' => 'match_reminder', 'title' => 'Live Match Starting Soon', 'message' => 'Team Phoenix vs Team Dragons match starts in 30 minutes. Don\'t miss the action!'],
                ['type' => 'tournament_update', 'title' => 'Tournament Registration Reminder', 'message' => 'Registration for the Championship Finals closes in 24 hours. Sign up now!'],
                ['type' => 'achievement', 'title' => 'Achievement Unlocked!', 'message' => 'Congratulations! You\'ve unlocked the "Active Viewer" achievement for watching 10 live matches.'],
                ['type' => 'squad_invitation', 'title' => 'Squad Invitation Received', 'message' => 'You\'ve been invited to join "Elite Gamers" squad. Accept or decline the invitation.'],
                ['type' => 'friend_request', 'title' => 'New Friend Request', 'message' => 'ProGamer123 has sent you a friend request. Check your profile to accept or decline.']
            ];
            break;
    }
    
    // Insert sample notifications
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, is_read) VALUES (?, ?, ?, ?, ?)");
    foreach ($sampleNotifications as $notification) {
        $stmt->execute([$userId, $notification['type'], $notification['title'], $notification['message'], rand(0, 1)]);
    }
}

// Fetch notifications with pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$whereClause = "WHERE user_id = ?";
$params = [$userId];

if ($filter === 'unread') {
    $whereClause .= " AND is_read = 0";
} elseif ($filter !== 'all') {
    $whereClause .= " AND type = ?";
    $params[] = $filter;
}

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications $whereClause");
$countStmt->execute($params);
$totalNotifications = $countStmt->fetchColumn();
$totalPages = ceil($totalNotifications / $limit);

// Get notifications
$stmt = $pdo->prepare("
    SELECT * FROM notifications 
    $whereClause 
    ORDER BY created_at DESC 
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unread count
$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$unreadStmt->execute([$userId]);
$unreadCount = $unreadStmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - MLBB Esports Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #6a11cb;
            --secondary-color: #2575fc;
            --accent-gold: #ffd700;
            --accent-red: #ff4655;
            --dark-bg: #0f172a;
            --darker-bg: #0a0e1a;
            --card-bg: #1e293b;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
        }
        
        body {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(to bottom, var(--darker-bg), var(--dark-bg));
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
        
        .notification-item {
            border-left: 4px solid var(--secondary-color);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .notification-item:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .notification-item.unread {
            border-left-color: var(--accent-gold);
            background: rgba(255, 215, 0, 0.05);
        }
        
        .notification-type-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
        }
        
        .notification-actions {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .notification-item:hover .notification-actions {
            opacity: 1;
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
        
        .filter-tabs .nav-link {
            color: var(--text-secondary);
            border: none;
            border-radius: 25px;
            padding: 8px 20px;
            margin-right: 10px;
            transition: all 0.3s ease;
        }
        
        .filter-tabs .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .type-match_reminder { background: linear-gradient(135deg, #ff6b6b, #ee5a24); }
        .type-tournament_update { background: linear-gradient(135deg, var(--accent-gold), #ff930f); }
        .type-squad_invitation { background: linear-gradient(135deg, #2ecc71, #27ae60); }
        .type-squad_update { background: linear-gradient(135deg, #3498db, #2980b9); }
        .type-admin_notice { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .type-system_update { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        .type-achievement { background: linear-gradient(135deg, var(--accent-gold), #f39c12); }
        .type-friend_request { background: linear-gradient(135deg, #1abc9c, #16a085); }
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
                        <?php if ($userRole === 'admin' || $userRole === 'super_admin'): ?>
                            <li><a class="dropdown-item" href="admin_dashboard.php"><i class="fas fa-cog me-2"></i>Admin Dashboard</a></li>
                        <?php elseif ($userRole === 'squad_leader'): ?>
                            <li><a class="dropdown-item" href="squad_leader_dashboard.php"><i class="fas fa-crown me-2"></i>Squad Dashboard</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="fas fa-bell me-2"></i>Notifications</h2>
                        <p class="text-muted mb-0">
                            <?php echo $unreadCount; ?> unread notification<?php echo $unreadCount !== 1 ? 's' : ''; ?>
                        </p>
                    </div>
                    <div>
                        <?php if ($unreadCount > 0): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="mark_all_read">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-check-double me-1"></i>Mark All Read
                            </button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="delete_all_read">
                            <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Delete all read notifications?')">
                                <i class="fas fa-trash me-1"></i>Clear Read
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Filter Tabs -->
                <ul class="nav filter-tabs mb-4">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $filter === 'all' ? 'active' : ''; ?>" href="?filter=all">
                            All (<?php echo $totalNotifications; ?>)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $filter === 'unread' ? 'active' : ''; ?>" href="?filter=unread">
                            Unread (<?php echo $unreadCount; ?>)
                        </a>
                    </li>
                    <?php if (in_array($userRole, ['admin', 'super_admin'])): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $filter === 'admin_notice' ? 'active' : ''; ?>" href="?filter=admin_notice">
                            Admin Notices
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $filter === 'system_update' ? 'active' : ''; ?>" href="?filter=system_update">
                            System Updates
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($userRole === 'squad_leader'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $filter === 'squad_update' ? 'active' : ''; ?>" href="?filter=squad_update">
                            Squad Updates
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $filter === 'match_reminder' ? 'active' : ''; ?>" href="?filter=match_reminder">
                            Match Reminders
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $filter === 'tournament_update' ? 'active' : ''; ?>" href="?filter=tournament_update">
                            Tournaments
                        </a>
                    </li>
                </ul>

                <!-- Notifications List -->
                <div class="card">
                    <div class="card-body p-0">
                        <?php if (empty($notifications)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-bell-slash fa-3x mb-3 text-muted"></i>
                            <h5>No notifications found</h5>
                            <p class="text-muted">You're all caught up!</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item p-3 border-bottom <?php echo !$notification['is_read'] ? 'unread' : ''; ?>" 
                             onclick="markAsRead(<?php echo $notification['id']; ?>)">
                            <div class="d-flex align-items-start">
                                <div class="notification-icon type-<?php echo $notification['type']; ?>">
                                    <?php
                                    $icons = [
                                        'match_reminder' => 'fas fa-gamepad',
                                        'tournament_update' => 'fas fa-trophy',
                                        'squad_invitation' => 'fas fa-user-plus',
                                        'squad_update' => 'fas fa-users',
                                        'admin_notice' => 'fas fa-shield-alt',
                                        'system_update' => 'fas fa-cog',
                                        'achievement' => 'fas fa-medal',
                                        'friend_request' => 'fas fa-user-friends'
                                    ];
                                    echo '<i class="' . ($icons[$notification['type']] ?? 'fas fa-bell') . ' text-white"></i>';
                                    ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                        <div class="notification-actions">
                                            <?php if (!$notification['is_read']): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="mark_read">
                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success me-1" title="Mark as read">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete" 
                                                        onclick="event.stopPropagation(); return confirm('Delete this notification?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <p class="mb-2 text-muted"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge notification-type-badge bg-secondary">
                                            <?php echo ucwords(str_replace('_', ' ', $notification['type'])); ?>
                                        </span>
                                        <small class="text-muted">
                                            <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?>">Previous</a>
                        </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?>">Next</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function markAsRead(notificationId) {
            // Create a form and submit it
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="mark_read">
                <input type="hidden" name="notification_id" value="${notificationId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        // Auto-refresh every 30 seconds for new notifications
        setInterval(function() {
            // Only refresh if there are unread notifications
            if (<?php echo $unreadCount; ?> > 0) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>
