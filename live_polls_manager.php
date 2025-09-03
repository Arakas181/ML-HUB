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

// Handle poll management actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_poll':
            handleCreatePoll();
            break;
        case 'end_poll':
            handleEndPoll();
            break;
        case 'delete_poll':
            handleDeletePoll();
            break;
        case 'duplicate_poll':
            handleDuplicatePoll();
            break;
        case 'export_results':
            handleExportResults();
            break;
    }
}

function handleCreatePoll() {
    global $pdo, $user_id;
    
    $chat_room_id = $_POST['chat_room_id'];
    $question = trim($_POST['question']);
    $options = array_filter($_POST['options'], function($option) {
        return !empty(trim($option));
    });
    $duration = (int)$_POST['duration'];
    $allow_multiple = isset($_POST['allow_multiple']) ? 1 : 0;
    $anonymous = isset($_POST['anonymous']) ? 1 : 0;
    
    try {
        if (count($options) < 2) {
            throw new Exception("Poll must have at least 2 options");
        }
        
        $pdo->beginTransaction();
        
        // Create poll with additional settings
        $stmt = $pdo->prepare("
            INSERT INTO live_polls 
            (chat_room_id, creator_id, question, options, duration, allow_multiple_votes, anonymous_voting, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([$chat_room_id, $user_id, $question, json_encode($options), $duration, $allow_multiple, $anonymous]);
        $poll_id = $pdo->lastInsertId();
        
        $pdo->commit();
        $_SESSION['success'] = "Poll created successfully!";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
}

function handleEndPoll() {
    global $pdo;
    
    $poll_id = $_POST['poll_id'];
    
    try {
        $stmt = $pdo->prepare("UPDATE live_polls SET status = 'ended', ended_at = NOW() WHERE id = ?");
        $stmt->execute([$poll_id]);
        $_SESSION['success'] = "Poll ended successfully!";
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

function handleDeletePoll() {
    global $pdo;
    
    $poll_id = $_POST['poll_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Delete votes first
        $stmt = $pdo->prepare("DELETE FROM poll_votes WHERE poll_id = ?");
        $stmt->execute([$poll_id]);
        
        // Delete poll
        $stmt = $pdo->prepare("DELETE FROM live_polls WHERE id = ?");
        $stmt->execute([$poll_id]);
        
        $pdo->commit();
        $_SESSION['success'] = "Poll deleted successfully!";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
}

function handleDuplicatePoll() {
    global $pdo, $user_id;
    
    $poll_id = $_POST['poll_id'];
    
    try {
        // Get original poll
        $stmt = $pdo->prepare("SELECT * FROM live_polls WHERE id = ?");
        $stmt->execute([$poll_id]);
        $original = $stmt->fetch();
        
        if (!$original) {
            throw new Exception("Poll not found");
        }
        
        // Create duplicate
        $stmt = $pdo->prepare("
            INSERT INTO live_polls 
            (chat_room_id, creator_id, question, options, duration, allow_multiple_votes, anonymous_voting, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'draft', NOW())
        ");
        $stmt->execute([
            $original['chat_room_id'], 
            $user_id, 
            $original['question'] . ' (Copy)', 
            $original['options'], 
            $original['duration'],
            $original['allow_multiple_votes'],
            $original['anonymous_voting']
        ]);
        
        $_SESSION['success'] = "Poll duplicated successfully!";
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

function handleExportResults() {
    global $pdo;
    
    $poll_id = $_POST['poll_id'];
    
    try {
        // Get poll details
        $stmt = $pdo->prepare("SELECT * FROM live_polls WHERE id = ?");
        $stmt->execute([$poll_id]);
        $poll = $stmt->fetch();
        
        // Get vote results
        $stmt = $pdo->prepare("
            SELECT pv.option_index, pv.voted_at, u.username
            FROM poll_votes pv
            LEFT JOIN users u ON pv.user_id = u.id
            WHERE pv.poll_id = ?
            ORDER BY pv.voted_at ASC
        ");
        $stmt->execute([$poll_id]);
        $votes = $stmt->fetchAll();
        
        // Generate CSV
        $filename = "poll_results_" . $poll_id . "_" . date('Y-m-d_H-i-s') . ".csv";
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, ['Poll Question', $poll['question']]);
        fputcsv($output, ['Created At', $poll['created_at']]);
        fputcsv($output, ['Total Votes', count($votes)]);
        fputcsv($output, []);
        fputcsv($output, ['Option Index', 'Option Text', 'Username', 'Voted At']);
        
        $options = json_decode($poll['options'], true);
        foreach ($votes as $vote) {
            fputcsv($output, [
                $vote['option_index'],
                $options[$vote['option_index']] ?? 'Unknown',
                $poll['anonymous_voting'] ? 'Anonymous' : $vote['username'],
                $vote['voted_at']
            ]);
        }
        
        fclose($output);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Get all polls for management
$stmt = $pdo->prepare("
    SELECT lp.*, u.username as creator_name, cr.name as room_name,
           (SELECT COUNT(*) FROM poll_votes pv WHERE pv.poll_id = lp.id) as total_votes,
           (SELECT COUNT(DISTINCT user_id) FROM poll_votes pv WHERE pv.poll_id = lp.id) as unique_voters
    FROM live_polls lp
    JOIN users u ON lp.creator_id = u.id
    LEFT JOIN chat_rooms cr ON lp.chat_room_id = cr.id
    ORDER BY lp.created_at DESC
    LIMIT 50
");
$stmt->execute();
$polls = $stmt->fetchAll();

// Get chat rooms for poll creation
$stmt = $pdo->prepare("SELECT * FROM chat_rooms ORDER BY name ASC");
$stmt->execute();
$chat_rooms = $stmt->fetchAll();

// Poll statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_polls,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_polls,
        SUM(CASE WHEN status = 'ended' THEN 1 ELSE 0 END) as ended_polls,
        (SELECT COUNT(*) FROM poll_votes) as total_votes
    FROM live_polls
");
$stmt->execute();
$stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Poll Manager - ML HUB Esports</title>
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
        
        .poll-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .status-active { background: #28a745; }
        .status-ended { background: #6c757d; }
        .status-draft { background: #ffc107; color: #000; }
        
        .table-dark {
            background: rgba(0, 0, 0, 0.3);
        }
        
        .btn-group-sm .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
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
                <a class="nav-link" href="live_polls.php">View Polls</a>
                <a class="nav-link" href="qa_system.php">Q&A System</a>
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
            <div class="col-md-3">
                <div class="stats-card">
                    <h3><?= $stats['total_polls'] ?></h3>
                    <small>Total Polls</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h3><?= $stats['active_polls'] ?></h3>
                    <small>Active Polls</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h3><?= $stats['ended_polls'] ?></h3>
                    <small>Ended Polls</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h3><?= $stats['total_votes'] ?></h3>
                    <small>Total Votes</small>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="admin-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4><i class="fas fa-poll me-2"></i>Poll Management</h4>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPollModal">
                            <i class="fas fa-plus me-2"></i>Create Poll
                        </button>
                    </div>
                    
                    <div class="table-responsive">
                        <table id="pollsTable" class="table table-dark table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Question</th>
                                    <th>Room</th>
                                    <th>Status</th>
                                    <th>Votes</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($polls as $poll): ?>
                                    <tr>
                                        <td><?= $poll['id'] ?></td>
                                        <td>
                                            <div class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($poll['question']) ?>">
                                                <?= htmlspecialchars($poll['question']) ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($poll['room_name'] ?? 'General') ?></td>
                                        <td>
                                            <span class="poll-status status-<?= $poll['status'] ?>">
                                                <?= ucfirst($poll['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= $poll['total_votes'] ?></span>
                                            <small class="text-muted">(<?= $poll['unique_voters'] ?> voters)</small>
                                        </td>
                                        <td>
                                            <small><?= date('M j, g:i A', strtotime($poll['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-info" onclick="viewPollDetails(<?= $poll['id'] ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <?php if ($poll['status'] === 'active'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="end_poll">
                                                        <input type="hidden" name="poll_id" value="<?= $poll['id'] ?>">
                                                        <button type="submit" class="btn btn-outline-warning" onclick="return confirm('End this poll?')">
                                                            <i class="fas fa-stop"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="duplicate_poll">
                                                    <input type="hidden" name="poll_id" value="<?= $poll['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-secondary">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="export_results">
                                                    <input type="hidden" name="poll_id" value="<?= $poll['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-success">
                                                        <i class="fas fa-download"></i>
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="delete_poll">
                                                    <input type="hidden" name="poll_id" value="<?= $poll['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Delete this poll? This cannot be undone.')">
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
            
            <div class="col-lg-4">
                <div class="admin-card p-4">
                    <h5><i class="fas fa-chart-line me-2"></i>Quick Actions</h5>
                    
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-light" onclick="endAllActivePolls()">
                            <i class="fas fa-stop-circle me-2"></i>End All Active Polls
                        </button>
                        
                        <button class="btn btn-outline-light" onclick="exportAllResults()">
                            <i class="fas fa-file-export me-2"></i>Export All Results
                        </button>
                        
                        <button class="btn btn-outline-light" onclick="cleanupOldPolls()">
                            <i class="fas fa-broom me-2"></i>Cleanup Old Polls
                        </button>
                    </div>
                </div>
                
                <div class="admin-card p-4 mt-4">
                    <h5><i class="fas fa-cogs me-2"></i>Poll Settings</h5>
                    
                    <div class="mb-3">
                        <label class="form-label">Default Poll Duration</label>
                        <select class="form-select" id="defaultDuration">
                            <option value="300">5 minutes</option>
                            <option value="600" selected>10 minutes</option>
                            <option value="1800">30 minutes</option>
                            <option value="3600">1 hour</option>
                        </select>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="autoArchive">
                        <label class="form-check-label" for="autoArchive">
                            Auto-archive ended polls after 24 hours
                        </label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="emailNotifications">
                        <label class="form-check-label" for="emailNotifications">
                            Email notifications for new polls
                        </label>
                    </div>
                    
                    <button class="btn btn-primary w-100">Save Settings</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Poll Modal -->
    <div class="modal fade" id="createPollModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Poll</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_poll">
                        
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
                                    <label class="form-label">Duration</label>
                                    <select class="form-select" name="duration" required>
                                        <option value="60">1 minute</option>
                                        <option value="300">5 minutes</option>
                                        <option value="600" selected>10 minutes</option>
                                        <option value="1800">30 minutes</option>
                                        <option value="3600">1 hour</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Question</label>
                            <input type="text" class="form-control" name="question" required maxlength="200" 
                                   placeholder="What's your question?">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Options</label>
                            <div id="pollOptionsContainer">
                                <div class="input-group mb-2">
                                    <input type="text" class="form-control" name="options[]" placeholder="Option 1" required>
                                    <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)" disabled>
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div class="input-group mb-2">
                                    <input type="text" class="form-control" name="options[]" placeholder="Option 2" required>
                                    <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-light btn-sm" onclick="addOption()">
                                <i class="fas fa-plus me-1"></i>Add Option
                            </button>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="allow_multiple" id="allowMultiple">
                                    <label class="form-check-label" for="allowMultiple">
                                        Allow multiple votes per user
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="anonymous" id="anonymous">
                                    <label class="form-check-label" for="anonymous">
                                        Anonymous voting
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Poll</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Poll Details Modal -->
    <div class="modal fade" id="pollDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header">
                    <h5 class="modal-title">Poll Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="pollDetailsContent">
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
            $('#pollsTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 25,
                responsive: true
            });
        });
        
        let optionCount = 2;
        
        function addOption() {
            if (optionCount >= 6) return;
            
            optionCount++;
            const container = document.getElementById('pollOptionsContainer');
            const div = document.createElement('div');
            div.className = 'input-group mb-2';
            div.innerHTML = `
                <input type="text" class="form-control" name="options[]" placeholder="Option ${optionCount}" required>
                <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)">
                    <i class="fas fa-times"></i>
                </button>
            `;
            container.appendChild(div);
        }
        
        function removeOption(button) {
            if (optionCount <= 2) return;
            
            button.parentElement.remove();
            optionCount--;
            
            // Update placeholders
            const inputs = document.querySelectorAll('#pollOptionsContainer input');
            inputs.forEach((input, index) => {
                input.placeholder = `Option ${index + 1}`;
            });
        }
        
        function viewPollDetails(pollId) {
            fetch(`poll_details.php?id=${pollId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('pollDetailsContent').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('pollDetailsModal')).show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load poll details');
                });
        }
        
        function endAllActivePolls() {
            if (!confirm('End all active polls? This cannot be undone.')) return;
            
            // Implementation for bulk ending polls
            alert('Feature coming soon!');
        }
        
        function exportAllResults() {
            // Implementation for bulk export
            alert('Feature coming soon!');
        }
        
        function cleanupOldPolls() {
            if (!confirm('Delete polls older than 30 days? This cannot be undone.')) return;
            
            // Implementation for cleanup
            alert('Feature coming soon!');
        }
    </script>
</body>
</html>
