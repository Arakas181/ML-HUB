<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: admin_login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$username = $_SESSION['username'];

// Ensure 'priority' column exists to avoid SQL errors on hosts not yet migrated
$hasPriorityColumn = false;
$hasStatusColumn = false;
try {
	$pdo->query("SELECT priority FROM notices LIMIT 1");
	$hasPriorityColumn = true;
} catch (PDOException $e) {
	try {
		$pdo->exec("ALTER TABLE notices ADD COLUMN priority ENUM('low','normal','high') DEFAULT 'normal' AFTER content");
		$hasPriorityColumn = true;
	} catch (PDOException $e2) {
		// If adding the column fails, fall back at runtime without blocking the page
		error_log('Failed to add priority column to notices: ' . $e2->getMessage());
		$hasPriorityColumn = false;
	}
}

// Ensure 'status' column exists to avoid SQL errors on hosts not yet migrated
try {
	$pdo->query("SELECT status FROM notices LIMIT 1");
	$hasStatusColumn = true;
} catch (PDOException $e) {
	try {
		// Place status after priority if it exists, otherwise after content
		if ($hasPriorityColumn) {
			$pdo->exec("ALTER TABLE notices ADD COLUMN status ENUM('draft','published') DEFAULT 'draft' AFTER priority");
		} else {
			$pdo->exec("ALTER TABLE notices ADD COLUMN status ENUM('draft','published') DEFAULT 'draft' AFTER content");
		}
		$hasStatusColumn = true;
	} catch (PDOException $e2) {
		// If adding the column fails, fall back at runtime without blocking the page
		error_log('Failed to add status column to notices: ' . $e2->getMessage());
		$hasStatusColumn = false;
	}
}

// Handle notice operations
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create' || $action === 'update') {
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $priority = $_POST['priority'] ?? 'normal';
            $status = $_POST['status'] ?? 'draft';
            
            if (empty($title) || empty($content)) {
                throw new Exception("Title and content are required");
            }
            
            if ($action === 'create') {
                if ($hasPriorityColumn && $hasStatusColumn) {
                    $stmt = $pdo->prepare("
                        INSERT INTO notices (title, content, priority, status, author_id, created_at) 
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$title, $content, $priority, $status, $userId]);
                } elseif ($hasPriorityColumn && !$hasStatusColumn) {
                    $stmt = $pdo->prepare("
                        INSERT INTO notices (title, content, priority, author_id, created_at) 
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$title, $content, $priority, $userId]);
                } elseif (!$hasPriorityColumn && $hasStatusColumn) {
                    $stmt = $pdo->prepare("
                        INSERT INTO notices (title, content, status, author_id, created_at) 
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$title, $content, $status, $userId]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO notices (title, content, author_id, created_at) 
                        VALUES (?, ?, ?, NOW())
                    ");
                    $stmt->execute([$title, $content, $userId]);
                }
                $successMessage = "Notice created successfully!";
            } else {
                $noticeId = (int)($_POST['notice_id'] ?? 0);
                if ($noticeId <= 0) { throw new Exception('Invalid notice id'); }
                if ($hasPriorityColumn && $hasStatusColumn) {
                    if ($userRole === 'super_admin') {
                        $stmt = $pdo->prepare("
                            UPDATE notices SET title = ?, content = ?, priority = ?, status = ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$title, $content, $priority, $status, $noticeId]);
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE notices SET title = ?, content = ?, priority = ?, status = ?, updated_at = NOW() 
                            WHERE id = ? AND author_id = ?
                        ");
                        $stmt->execute([$title, $content, $priority, $status, $noticeId, $userId]);
                    }
                } elseif ($hasPriorityColumn && !$hasStatusColumn) {
                    if ($userRole === 'super_admin') {
                        $stmt = $pdo->prepare("
                            UPDATE notices SET title = ?, content = ?, priority = ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$title, $content, $priority, $noticeId]);
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE notices SET title = ?, content = ?, priority = ?, updated_at = NOW() 
                            WHERE id = ? AND author_id = ?
                        ");
                        $stmt->execute([$title, $content, $priority, $noticeId, $userId]);
                    }
                } elseif (!$hasPriorityColumn && $hasStatusColumn) {
                    if ($userRole === 'super_admin') {
                        $stmt = $pdo->prepare("
                            UPDATE notices SET title = ?, content = ?, status = ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$title, $content, $status, $noticeId]);
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE notices SET title = ?, content = ?, status = ?, updated_at = NOW() 
                            WHERE id = ? AND author_id = ?
                        ");
                        $stmt->execute([$title, $content, $status, $noticeId, $userId]);
                    }
                } else {
                    if ($userRole === 'super_admin') {
                        $stmt = $pdo->prepare("
                            UPDATE notices SET title = ?, content = ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$title, $content, $noticeId]);
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE notices SET title = ?, content = ?, updated_at = NOW() 
                            WHERE id = ? AND author_id = ?
                        ");
                        $stmt->execute([$title, $content, $noticeId, $userId]);
                    }
                }
                $successMessage = "Notice updated successfully!";
            }
        } elseif ($action === 'delete') {
            $noticeId = (int)($_POST['notice_id'] ?? 0);
            if ($noticeId <= 0) { throw new Exception('Invalid notice id'); }
            if ($userRole === 'super_admin') {
                $stmt = $pdo->prepare("DELETE FROM notices WHERE id = ?");
                $stmt->execute([$noticeId]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM notices WHERE id = ? AND author_id = ?");
                $stmt->execute([$noticeId, $userId]);
            }
            $successMessage = "Notice deleted successfully!";
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Fetch notices
try {
    $notices = $pdo->query("
        SELECT n.*, u.username as author_name 
        FROM notices n 
        JOIN users u ON n.author_id = u.id 
        ORDER BY n.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $notices = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Notices - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Oxanium:wght@400;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
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
            color: white;
            min-height: 100vh;
        }
        
        .navbar {
            background: var(--card-bg) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .navbar-brand {
            font-family: 'Oxanium', cursive;
            font-weight: 700;
            color: #5ce1e6 !important;
            font-size: 1.5rem;
        }
        
        .sidebar {
            background: var(--card-bg);
            border-radius: 15px;
            margin: 20px 0;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            min-height: calc(100vh - 120px);
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
            padding: 12px 15px !important;
            margin: 5px 0;
            border-radius: 8px;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(140, 82, 255, 0.2) !important;
            color: white !important;
            border-left: 4px solid var(--primary-color);
            transform: translateX(5px);
        }
        
        .card {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            color: white;
        }
        
        .card-header {
            background: var(--primary-gradient);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px 15px 0 0 !important;
            font-family: 'Oxanium', cursive;
            font-weight: 600;
        }
        
        .admin-header {
            background: var(--primary-gradient);
            color: white;
            padding: 40px 0;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(140, 82, 255, 0.3);
        }
        
        .admin-header h1 {
            font-family: 'Oxanium', cursive;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .notice-card {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            margin-bottom: 20px;
            transition: all 0.3s;
            color: white;
        }
        
        .notice-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(140, 82, 255, 0.2);
        }
        
        .priority-high { border-left: 4px solid #ff3e85; }
        .priority-normal { border-left: 4px solid #5ce1e6; }
        .priority-low { border-left: 4px solid #20c997; }
        
        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #7a45e0 0%, #4ec1c6 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(140, 82, 255, 0.3);
        }
        
        .modal-content {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            backdrop-filter: blur(10px);
        }
        
        .modal-header {
            background: var(--primary-gradient);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px 15px 0 0;
        }
        
        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 8px;
        }
        
        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(140, 82, 255, 0.25);
            color: white;
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .form-label {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .alert-success {
            background: rgba(32, 201, 151, 0.2);
            color: #20c997;
            border: 1px solid rgba(32, 201, 151, 0.3);
        }
        
        .alert-danger {
            background: rgba(255, 62, 133, 0.2);
            color: #ff3e85;
            border: 1px solid rgba(255, 62, 133, 0.3);
        }
        
        .badge {
            border-radius: 6px;
            font-weight: 500;
        }
        
        .dropdown-menu {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        
        .dropdown-item {
            color: rgba(255, 255, 255, 0.8);
            transition: all 0.3s;
        }
        
        .dropdown-item:hover {
            background: rgba(140, 82, 255, 0.2);
            color: white;
        }
        
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }
            
            .admin-header {
                padding: 30px 20px;
                margin: 15px 0;
            }
            
            .admin-header h1 {
                font-size: 1.8rem;
            }
            
            .notice-card {
                margin-bottom: 15px;
            }
            
            .card-body {
                padding: 15px;
            }
            
            .btn {
                font-size: 0.9rem;
            }
            
            .modal-dialog {
                margin: 10px;
            }
        }
        
        @media (max-width: 576px) {
            .admin-header h1 {
                font-size: 1.5rem;
            }
            
            .btn {
                font-size: 0.85rem;
                padding: 8px 16px;
            }
            
            .card-body {
                padding: 12px;
            }
            
            .notice-card .card-text {
                font-size: 0.9rem;
            }
        }
        
        /* Sidebar Toggle for Mobile */
        .sidebar-toggle {
            display: none;
        }
        
        @media (max-width: 768px) {
            .sidebar-toggle {
                display: block;
            }
            
            .sidebar {
                position: fixed;
                top: 0;
                left: -100%;
                width: 280px;
                height: 100vh;
                z-index: 1050;
                transition: left 0.3s ease;
                margin: 0;
                border-radius: 0;
            }
            
            .sidebar.show {
                left: 0;
            }
            
            .sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 1040;
                display: none;
            }
            
            .sidebar-overlay.show {
                display: block;
            }
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
                        <a class="nav-link" href="admin_dashboard.php">Admin Dashboard</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="dropdown">
                        <a class="btn btn-outline-light dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($username); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="admin_dashboard.php">Admin Dashboard</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-5">
        <!-- Admin Header -->
        <div class="admin-header text-center">
            <h1><i class="fas fa-bullhorn me-2"></i>Manage Notices</h1>
            <p class="mb-0">Create and manage platform announcements and notices</p>
        </div>

        <!-- Messages -->
        <?php if ($successMessage): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($successMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($errorMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Create Notice Button -->
        <div class="text-end mb-4">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createNoticeModal">
                <i class="fas fa-plus me-2"></i>Create New Notice
            </button>
        </div>

        <!-- Notices List -->
        <div class="row">
            <?php if (empty($notices)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No Notices Yet</h4>
                        <p class="text-muted">Create your first notice to keep users informed!</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($notices as $notice): ?>
                    <div class="col-lg-6 col-xl-4">
                        <div class="notice-card priority-<?php echo htmlspecialchars(($notice['priority'] ?? 'normal')); ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="card-title mb-0"><?php echo htmlspecialchars($notice['title']); ?></h5>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="editNotice(<?php echo htmlspecialchars(json_encode($notice)); ?>)">
                                                <i class="fas fa-edit me-2"></i>Edit
                                            </a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteNotice(<?php echo $notice['id']; ?>)">
                                                <i class="fas fa-trash me-2"></i>Delete
                                            </a></li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <p class="card-text"><?php echo htmlspecialchars(substr($notice['content'], 0, 150)) . '...'; ?></p>
                                
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <small class="text-muted">
                                            <i class="fas fa-flag me-1"></i>Priority
                                        </small>
                                        <div>
                                            <?php $priority = $notice['priority'] ?? 'normal'; ?>
                                            <span class="badge bg-<?php echo $priority === 'high' ? 'danger' : ($priority === 'normal' ? 'info' : 'success'); ?>">
                                                <?php echo ucfirst((string)$priority); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">
                                            <i class="fas fa-toggle-on me-1"></i>Status
                                        </small>
                                        <div>
                                            <?php $status = $notice['status'] ?? 'draft'; ?>
                                            <span class="badge bg-<?php echo $status === 'published' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst((string)$status); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($notice['author_name']); ?>
                                    </small>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($notice['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Notice Modal -->
    <div class="modal fade" id="createNoticeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Notice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="content" class="form-label">Content</label>
                            <textarea class="form-control" id="content" name="content" rows="5" required></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="priority" class="form-label">Priority</label>
                                    <select class="form-select" id="priority" name="priority">
                                        <option value="low">Low</option>
                                        <option value="normal" selected>Normal</option>
                                        <option value="high">High</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="draft">Draft</option>
                                        <option value="published">Published</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Notice</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Notice Modal -->
    <div class="modal fade" id="editNoticeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Notice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="notice_id" id="edit_notice_id">
                        
                        <div class="mb-3">
                            <label for="edit_title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="edit_title" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_content" class="form-label">Content</label>
                            <textarea class="form-control" id="edit_content" name="content" rows="5" required></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_priority" class="form-label">Priority</label>
                                    <select class="form-select" id="edit_priority" name="priority">
                                        <option value="low">Low</option>
                                        <option value="normal">Normal</option>
                                        <option value="high">High</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_status" class="form-label">Status</label>
                                    <select class="form-select" id="edit_status" name="status">
                                        <option value="draft">Draft</option>
                                        <option value="published">Published</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Notice</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Notice Form -->
    <form id="deleteNoticeForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="notice_id" id="delete_notice_id">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editNotice(notice) {
            document.getElementById('edit_notice_id').value = notice.id;
            document.getElementById('edit_title').value = notice.title;
            document.getElementById('edit_content').value = notice.content;
            document.getElementById('edit_priority').value = notice.priority;
            document.getElementById('edit_status').value = notice.status;
            
            new bootstrap.Modal(document.getElementById('editNoticeModal')).show();
        }
        
        function deleteNotice(noticeId) {
            if (confirm('Are you sure you want to delete this notice? This action cannot be undone.')) {
                document.getElementById('delete_notice_id').value = noticeId;
                document.getElementById('deleteNoticeForm').submit();
            }
        }
    </script>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4 mt-5">
        <div class="container">
            <p>&copy; 2024 Esports Platform. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>