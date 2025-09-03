<?php
require_once 'config.php';
session_start();

// Check if user is admin/moderator
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin', 'moderator'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Handle Q&A session management actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_session':
            handleCreateSession();
            break;
        case 'end_session':
            handleEndSession();
            break;
        case 'delete_session':
            handleDeleteSession();
            break;
        case 'bulk_approve':
            handleBulkApprove();
            break;
        case 'export_session':
            handleExportSession();
            break;
        case 'update_settings':
            handleUpdateSettings();
            break;
    }
}

function handleCreateSession() {
    global $pdo, $user_id;
    
    $chat_room_id = $_POST['chat_room_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $auto_approve = isset($_POST['auto_approve']) ? 1 : 0;
    $allow_anonymous = isset($_POST['allow_anonymous']) ? 1 : 0;
    $rate_limit = (int)$_POST['rate_limit'];
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO qa_sessions 
            (chat_room_id, moderator_id, title, description, auto_approve, allow_anonymous, rate_limit, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([$chat_room_id, $user_id, $title, $description, $auto_approve, $allow_anonymous, $rate_limit]);
        $_SESSION['success'] = "Q&A session created successfully!";
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

function handleEndSession() {
    global $pdo;
    
    $session_id = $_POST['session_id'];
    
    try {
        $stmt = $pdo->prepare("UPDATE qa_sessions SET status = 'ended', ended_at = NOW() WHERE id = ?");
        $stmt->execute([$session_id]);
        $_SESSION['success'] = "Q&A session ended successfully!";
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

function handleDeleteSession() {
    global $pdo;
    
    $session_id = $_POST['session_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Delete questions first
        $stmt = $pdo->prepare("DELETE FROM qa_questions WHERE qa_session_id = ?");
        $stmt->execute([$session_id]);
        
        // Delete session
        $stmt = $pdo->prepare("DELETE FROM qa_sessions WHERE id = ?");
        $stmt->execute([$session_id]);
        
        $pdo->commit();
        $_SESSION['success'] = "Q&A session deleted successfully!";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
}

function handleBulkApprove() {
    global $pdo;
    
    $session_id = $_POST['session_id'];
    
    try {
        $stmt = $pdo->prepare("
            UPDATE qa_questions 
            SET status = 'approved', moderated_at = NOW() 
            WHERE qa_session_id = ? AND status = 'pending'
        ");
        $stmt->execute([$session_id]);
        $affected = $stmt->rowCount();
        
        $_SESSION['success'] = "Approved $affected pending questions!";
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

function handleExportSession() {
    global $pdo;
    
    $session_id = $_POST['session_id'];
    
    try {
        // Get session details
        $stmt = $pdo->prepare("
            SELECT qs.*, u.username as moderator_name, cr.name as room_name
            FROM qa_sessions qs
            JOIN users u ON qs.moderator_id = u.id
            LEFT JOIN chat_rooms cr ON qs.chat_room_id = cr.id
            WHERE qs.id = ?
        ");
        $stmt->execute([$session_id]);
        $session = $stmt->fetch();
        
        // Get all questions
        $stmt = $pdo->prepare("
            SELECT qq.*, u.username, au.username as answerer_name
            FROM qa_questions qq
            JOIN users u ON qq.user_id = u.id
            LEFT JOIN users au ON qq.answered_by = au.id
            WHERE qq.qa_session_id = ?
            ORDER BY qq.created_at ASC
        ");
        $stmt->execute([$session_id]);
        $questions = $stmt->fetchAll();
        
        // Generate CSV
        $filename = "qa_session_" . $session_id . "_" . date('Y-m-d_H-i-s') . ".csv";
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, ['Session Title', $session['title']]);
        fputcsv($output, ['Moderator', $session['moderator_name']]);
        fputcsv($output, ['Room', $session['room_name']]);
        fputcsv($output, ['Created At', $session['created_at']]);
        fputcsv($output, ['Total Questions', count($questions)]);
        fputcsv($output, []);
        fputcsv($output, ['Question ID', 'Username', 'Question', 'Status', 'Answer', 'Answerer', 'Created At', 'Answered At']);
        
        foreach ($questions as $question) {
            fputcsv($output, [
                $question['id'],
                $question['username'],
                $question['question'],
                $question['status'],
                $question['answer'] ?? '',
                $question['answerer_name'] ?? '',
                $question['created_at'],
                $question['answered_at'] ?? ''
            ]);
        }
        
        fclose($output);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

function handleUpdateSettings() {
    global $pdo;
    
    $session_id = $_POST['session_id'];
    $auto_approve = isset($_POST['auto_approve']) ? 1 : 0;
    $allow_anonymous = isset($_POST['allow_anonymous']) ? 1 : 0;
    $rate_limit = (int)$_POST['rate_limit'];
    
    try {
        $stmt = $pdo->prepare("
            UPDATE qa_sessions 
            SET auto_approve = ?, allow_anonymous = ?, rate_limit = ? 
            WHERE id = ?
        ");
        $stmt->execute([$auto_approve, $allow_anonymous, $rate_limit, $session_id]);
        $_SESSION['success'] = "Session settings updated!";
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Get all Q&A sessions
$stmt = $pdo->prepare("
    SELECT qs.*, u.username as moderator_name, cr.name as room_name,
           (SELECT COUNT(*) FROM qa_questions qq WHERE qq.qa_session_id = qs.id) as total_questions,
           (SELECT COUNT(*) FROM qa_questions qq WHERE qq.qa_session_id = qs.id AND qq.status = 'pending') as pending_questions,
           (SELECT COUNT(*) FROM qa_questions qq WHERE qq.qa_session_id = qs.id AND qq.status = 'answered') as answered_questions
    FROM qa_sessions qs
    JOIN users u ON qs.moderator_id = u.id
    LEFT JOIN chat_rooms cr ON qs.chat_room_id = cr.id
    ORDER BY qs.created_at DESC
    LIMIT 50
");
$stmt->execute();
$sessions = $stmt->fetchAll();

// Get chat rooms
$stmt = $pdo->prepare("SELECT * FROM chat_rooms ORDER BY name ASC");
$stmt->execute();
$chat_rooms = $stmt->fetchAll();

// Session statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_sessions,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_sessions,
        SUM(CASE WHEN status = 'ended' THEN 1 ELSE 0 END) as ended_sessions,
        (SELECT COUNT(*) FROM qa_questions) as total_questions,
        (SELECT COUNT(*) FROM qa_questions WHERE status = 'pending') as pending_questions
    FROM qa_sessions
");
$stmt->execute();
$stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Q&A Session Manager - ML HUB Esports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1a1a2e;
            --secondary-color: #16213e;
            --accent-color: #0f3460;
            --highlight-color: #e94560;
            --text-light: #f1f1f1;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--text-light);
            min-height: 100vh;
        }
        
        .admin-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
        }
        
        .stats-card {
            background: linear-gradient(135deg, var(--highlight-color), #ff6b8a);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .session-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .status-active { background: #28a745; }
        .status-ended { background: #6c757d; }
        
        .table-dark {
            background: rgba(0, 0, 0, 0.3);
        }
        
        .question-preview {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .pending-badge {
            background: #ffc107;
            color: #000;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-trophy me-2"></i>ML HUB Esports
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="admin_dashboard.php">Admin Dashboard</a>
                <a class="nav-link" href="live_polls_manager.php">Poll Manager</a>
                <a class="nav-link" href="qa_system.php">View Q&A</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-2">
                <div class="stats-card">
                    <h3><?= $stats['total_sessions'] ?></h3>
                    <small>Total Sessions</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card">
                    <h3><?= $stats['active_sessions'] ?></h3>
                    <small>Active Sessions</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card">
                    <h3><?= $stats['ended_sessions'] ?></h3>
                    <small>Ended Sessions</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h3><?= $stats['total_questions'] ?></h3>
                    <small>Total Questions</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h3 class="text-warning"><?= $stats['pending_questions'] ?></h3>
                    <small>Pending Questions</small>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-9">
                <div class="admin-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4><i class="fas fa-question-circle me-2"></i>Q&A Session Management</h4>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSessionModal">
                            <i class="fas fa-plus me-2"></i>Create Session
                        </button>
                    </div>
                    
                    <div class="table-responsive">
                        <table id="sessionsTable" class="table table-dark table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Room</th>
                                    <th>Moderator</th>
                                    <th>Status</th>
                                    <th>Questions</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $session): ?>
                                    <tr>
                                        <td><?= $session['id'] ?></td>
                                        <td>
                                            <div class="question-preview" title="<?= htmlspecialchars($session['title']) ?>">
                                                <?= htmlspecialchars($session['title']) ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($session['room_name'] ?? 'General') ?></td>
                                        <td><?= htmlspecialchars($session['moderator_name']) ?></td>
                                        <td>
                                            <span class="session-status status-<?= $session['status'] ?>">
                                                <?= ucfirst($session['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= $session['total_questions'] ?></span>
                                            <?php if ($session['pending_questions'] > 0): ?>
                                                <span class="badge pending-badge"><?= $session['pending_questions'] ?> pending</span>
                                            <?php endif; ?>
                                            <span class="badge bg-success"><?= $session['answered_questions'] ?> answered</span>
                                        </td>
                                        <td>
                                            <small><?= date('M j, g:i A', strtotime($session['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-info" onclick="viewSessionDetails(<?= $session['id'] ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <?php if ($session['status'] === 'active'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="end_session">
                                                        <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                                        <button type="submit" class="btn btn-outline-warning" onclick="return confirm('End this Q&A session?')">
                                                            <i class="fas fa-stop"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <?php if ($session['pending_questions'] > 0): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="bulk_approve">
                                                        <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                                        <button type="submit" class="btn btn-outline-success" onclick="return confirm('Approve all pending questions?')">
                                                            <i class="fas fa-check-double"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <button class="btn btn-outline-secondary" onclick="showSettings(<?= $session['id'] ?>)">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                                
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="export_session">
                                                    <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-primary">
                                                        <i class="fas fa-download"></i>
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="delete_session">
                                                    <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Delete this session? This cannot be undone.')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3">
                <div class="admin-card p-4">
                    <h5><i class="fas fa-chart-line me-2"></i>Quick Actions</h5>
                    
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-light" onclick="endAllActiveSessions()">
                            <i class="fas fa-stop-circle me-2"></i>End All Active Sessions
                        </button>
                        
                        <button class="btn btn-outline-light" onclick="approveAllPending()">
                            <i class="fas fa-check-circle me-2"></i>Approve All Pending
                        </button>
                        
                        <button class="btn btn-outline-light" onclick="exportAllSessions()">
                            <i class="fas fa-file-export me-2"></i>Export All Sessions
                        </button>
                        
                        <button class="btn btn-outline-light" onclick="cleanupOldSessions()">
                            <i class="fas fa-broom me-2"></i>Cleanup Old Sessions
                        </button>
                    </div>
                </div>
                
                <div class="admin-card p-4 mt-4">
                    <h5><i class="fas fa-cogs me-2"></i>Global Settings</h5>
                    
                    <div class="mb-3">
                        <label class="form-label">Default Rate Limit</label>
                        <select class="form-select" id="defaultRateLimit">
                            <option value="3">3 questions per 5 min</option>
                            <option value="5" selected>5 questions per 5 min</option>
                            <option value="10">10 questions per 5 min</option>
                        </select>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="autoModeration">
                        <label class="form-check-label" for="autoModeration">
                            Auto-approve questions from verified users
                        </label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="emailNotifications">
                        <label class="form-check-label" for="emailNotifications">
                            Email notifications for new questions
                        </label>
                    </div>
                    
                    <button class="btn btn-primary w-100">Save Settings</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Session Modal -->
    <div class="modal fade" id="createSessionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header">
                    <h5 class="modal-title">Create Q&A Session</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_session">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Chat Room</label>
                                    <select class="form-select" name="chat_room_id" required>
                                        <?php foreach ($chat_rooms as $room): ?>
                                            <option value="<?= $room['id'] ?>"><?= htmlspecialchars($room['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Rate Limit (questions per 5 min)</label>
                                    <select class="form-select" name="rate_limit" required>
                                        <option value="3">3 questions</option>
                                        <option value="5" selected>5 questions</option>
                                        <option value="10">10 questions</option>
                                        <option value="0">No limit</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Session Title</label>
                            <input type="text" class="form-control" name="title" required maxlength="200" 
                                   placeholder="Tournament Q&A Session">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" maxlength="500"
                                      placeholder="Ask questions about the tournament..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="auto_approve" id="autoApprove">
                                    <label class="form-check-label" for="autoApprove">
                                        Auto-approve questions
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="allow_anonymous" id="allowAnonymous">
                                    <label class="form-check-label" for="allowAnonymous">
                                        Allow anonymous questions
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Session</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Session Details Modal -->
    <div class="modal fade" id="sessionDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header">
                    <h5 class="modal-title">Session Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="sessionDetailsContent">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#sessionsTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 25,
                responsive: true
            });
        });
        
        function viewSessionDetails(sessionId) {
            fetch(`qa_session_details.php?id=${sessionId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('sessionDetailsContent').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('sessionDetailsModal')).show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load session details');
                });
        }
        
        function showSettings(sessionId) {
            // Implementation for session settings modal
            alert('Session settings feature coming soon!');
        }
        
        function endAllActiveSessions() {
            if (!confirm('End all active Q&A sessions? This cannot be undone.')) return;
            alert('Feature coming soon!');
        }
        
        function approveAllPending() {
            if (!confirm('Approve all pending questions across all sessions?')) return;
            alert('Feature coming soon!');
        }
        
        function exportAllSessions() {
            alert('Feature coming soon!');
        }
        
        function cleanupOldSessions() {
            if (!confirm('Delete sessions older than 30 days? This cannot be undone.')) return;
            alert('Feature coming soon!');
        }
    </script>
</body>
</html>
