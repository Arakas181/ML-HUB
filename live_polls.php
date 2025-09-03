<?php
require_once 'config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Handle poll actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_poll':
            if (in_array($user_role, ['admin', 'super_admin', 'moderator'])) {
                handleCreatePoll();
            }
            break;
        case 'vote':
            handleVote();
            break;
        case 'end_poll':
            if (in_array($user_role, ['admin', 'super_admin', 'moderator'])) {
                handleEndPoll();
            }
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
    
    try {
        if (count($options) < 2) {
            throw new Exception("Poll must have at least 2 options");
        }
        
        $pdo->beginTransaction();
        
        // Create poll
        $stmt = $pdo->prepare("
            INSERT INTO live_polls (chat_room_id, creator_id, question, options, duration, status, created_at) 
            VALUES (?, ?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([$chat_room_id, $user_id, $question, json_encode($options), $duration]);
        $poll_id = $pdo->lastInsertId();
        
        $pdo->commit();
        
        // Return poll data for real-time broadcast
        echo json_encode([
            'success' => true,
            'poll_id' => $poll_id,
            'question' => $question,
            'options' => $options,
            'duration' => $duration
        ]);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

function handleVote() {
    global $pdo, $user_id;
    
    $poll_id = $_POST['poll_id'];
    $option_index = (int)$_POST['option_index'];
    
    try {
        // Check if poll is active
        $stmt = $pdo->prepare("SELECT * FROM live_polls WHERE id = ? AND status = 'active'");
        $stmt->execute([$poll_id]);
        $poll = $stmt->fetch();
        
        if (!$poll) {
            throw new Exception("Poll not found or inactive");
        }
        
        // Check if poll has expired
        $created_time = strtotime($poll['created_at']);
        if (time() > ($created_time + $poll['duration'])) {
            // Auto-end expired poll
            $stmt = $pdo->prepare("UPDATE live_polls SET status = 'ended' WHERE id = ?");
            $stmt->execute([$poll_id]);
            throw new Exception("Poll has expired");
        }
        
        // Record vote (update if already voted)
        $stmt = $pdo->prepare("
            INSERT INTO poll_votes (poll_id, user_id, option_index, voted_at) 
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE option_index = VALUES(option_index), voted_at = NOW()
        ");
        $stmt->execute([$poll_id, $user_id, $option_index]);
        
        // Get updated results
        $results = getPollResults($poll_id);
        
        echo json_encode([
            'success' => true,
            'results' => $results
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

function handleEndPoll() {
    global $pdo;
    
    $poll_id = $_POST['poll_id'];
    
    try {
        $stmt = $pdo->prepare("UPDATE live_polls SET status = 'ended' WHERE id = ?");
        $stmt->execute([$poll_id]);
        
        $results = getPollResults($poll_id);
        
        echo json_encode([
            'success' => true,
            'results' => $results
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

function getPollResults($poll_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT option_index, COUNT(*) as votes 
        FROM poll_votes 
        WHERE poll_id = ? 
        GROUP BY option_index
        ORDER BY option_index
    ");
    $stmt->execute([$poll_id]);
    $votes = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_votes FROM poll_votes WHERE poll_id = ?");
    $stmt->execute([$poll_id]);
    $total_votes = $stmt->fetch()['total_votes'];
    
    return [
        'votes' => $votes,
        'total_votes' => $total_votes
    ];
}

// Get active polls for current room
$room_id = $_GET['room_id'] ?? 1;
$stmt = $pdo->prepare("
    SELECT lp.*, u.username as creator_name,
           (SELECT COUNT(*) FROM poll_votes pv WHERE pv.poll_id = lp.id) as total_votes
    FROM live_polls lp
    JOIN users u ON lp.creator_id = u.id
    WHERE lp.chat_room_id = ? AND lp.status = 'active'
    ORDER BY lp.created_at DESC
");
$stmt->execute([$room_id]);
$active_polls = $stmt->fetchAll();

// Get recent ended polls
$stmt = $pdo->prepare("
    SELECT lp.*, u.username as creator_name,
           (SELECT COUNT(*) FROM poll_votes pv WHERE pv.poll_id = lp.id) as total_votes
    FROM live_polls lp
    JOIN users u ON lp.creator_id = u.id
    WHERE lp.chat_room_id = ? AND lp.status = 'ended'
    ORDER BY lp.created_at DESC
    LIMIT 5
");
$stmt->execute([$room_id]);
$recent_polls = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Polls - ML HUB Esports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        
        .poll-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            margin-bottom: 1rem;
        }
        
        .poll-option {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .poll-option:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--highlight-color);
        }
        
        .poll-option.voted {
            border-color: var(--highlight-color);
            background: rgba(233, 69, 96, 0.2);
        }
        
        .vote-bar {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            background: linear-gradient(90deg, var(--highlight-color), rgba(233, 69, 96, 0.3));
            transition: width 0.5s ease;
            z-index: 1;
        }
        
        .option-content {
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .poll-timer {
            font-size: 1.2em;
            font-weight: bold;
            color: var(--highlight-color);
        }
        
        .create-poll-form {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 1.5rem;
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
                <a class="nav-link" href="streaming_hub.php">Streaming</a>
                <a class="nav-link" href="tournaments.php">Tournaments</a>
                <a class="nav-link" href="profile.php">Profile</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-8">
                <h2><i class="fas fa-poll me-2"></i>Live Polls</h2>
                
                <div id="activePolls">
                    <?php if (empty($active_polls)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>No active polls at the moment.
                        </div>
                    <?php else: ?>
                        <?php foreach ($active_polls as $poll): ?>
                            <div class="poll-card p-4" data-poll-id="<?= $poll['id'] ?>">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5><?= htmlspecialchars($poll['question']) ?></h5>
                                        <small class="text-muted">
                                            by <?= htmlspecialchars($poll['creator_name']) ?> • 
                                            <?= $poll['total_votes'] ?> votes
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <div class="poll-timer" data-end-time="<?= strtotime($poll['created_at']) + $poll['duration'] ?>">
                                            --:--
                                        </div>
                                        <?php if (in_array($user_role, ['admin', 'super_admin', 'moderator'])): ?>
                                            <button class="btn btn-sm btn-outline-danger mt-1" onclick="endPoll(<?= $poll['id'] ?>)">
                                                End Poll
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="poll-options" data-poll-id="<?= $poll['id'] ?>">
                                    <?php 
                                    $options = json_decode($poll['options'], true);
                                    $results = getPollResults($poll['id']);
                                    
                                    foreach ($options as $index => $option): 
                                        $votes = $results['votes'][$index] ?? 0;
                                        $percentage = $results['total_votes'] > 0 ? ($votes / $results['total_votes']) * 100 : 0;
                                    ?>
                                        <div class="poll-option" onclick="vote(<?= $poll['id'] ?>, <?= $index ?>)">
                                            <div class="vote-bar" style="width: <?= $percentage ?>%"></div>
                                            <div class="option-content">
                                                <span><?= htmlspecialchars($option) ?></span>
                                                <span class="badge bg-secondary"><?= $votes ?> (<?= number_format($percentage, 1) ?>%)</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($recent_polls)): ?>
                    <h4 class="mt-4"><i class="fas fa-history me-2"></i>Recent Polls</h4>
                    <?php foreach ($recent_polls as $poll): ?>
                        <div class="poll-card p-3">
                            <h6><?= htmlspecialchars($poll['question']) ?></h6>
                            <small class="text-muted">
                                by <?= htmlspecialchars($poll['creator_name']) ?> • 
                                <?= $poll['total_votes'] ?> votes • 
                                Ended <?= date('M j, g:i A', strtotime($poll['created_at'])) ?>
                            </small>
                            
                            <div class="mt-2">
                                <?php 
                                $options = json_decode($poll['options'], true);
                                $results = getPollResults($poll['id']);
                                
                                foreach ($options as $index => $option): 
                                    $votes = $results['votes'][$index] ?? 0;
                                    $percentage = $results['total_votes'] > 0 ? ($votes / $results['total_votes']) * 100 : 0;
                                ?>
                                    <div class="poll-option">
                                        <div class="vote-bar" style="width: <?= $percentage ?>%"></div>
                                        <div class="option-content">
                                            <span><?= htmlspecialchars($option) ?></span>
                                            <span class="badge bg-secondary"><?= $votes ?> (<?= number_format($percentage, 1) ?>%)</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="col-lg-4">
                <?php if (in_array($user_role, ['admin', 'super_admin', 'moderator'])): ?>
                    <div class="create-poll-form">
                        <h4><i class="fas fa-plus-circle me-2"></i>Create Poll</h4>
                        
                        <form id="createPollForm">
                            <input type="hidden" name="action" value="create_poll">
                            <input type="hidden" name="chat_room_id" value="<?= $room_id ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Question</label>
                                <input type="text" class="form-control" name="question" required maxlength="200">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Options</label>
                                <div id="pollOptions">
                                    <input type="text" class="form-control mb-2" name="options[]" placeholder="Option 1" required>
                                    <input type="text" class="form-control mb-2" name="options[]" placeholder="Option 2" required>
                                </div>
                                <button type="button" class="btn btn-outline-light btn-sm" onclick="addOption()">
                                    <i class="fas fa-plus me-1"></i>Add Option
                                </button>
                            </div>
                            
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
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-rocket me-2"></i>Create Poll
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
                
                <div class="mt-4">
                    <h5><i class="fas fa-chart-bar me-2"></i>Poll Statistics</h5>
                    <div class="poll-card p-3">
                        <div class="row text-center">
                            <div class="col-6">
                                <h4 class="text-primary"><?= count($active_polls) ?></h4>
                                <small>Active Polls</small>
                            </div>
                            <div class="col-6">
                                <h4 class="text-success"><?= count($recent_polls) ?></h4>
                                <small>Recent Polls</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let optionCount = 2;
        
        function addOption() {
            if (optionCount >= 6) return; // Max 6 options
            
            optionCount++;
            const container = document.getElementById('pollOptions');
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'form-control mb-2';
            input.name = 'options[]';
            input.placeholder = `Option ${optionCount}`;
            input.required = true;
            container.appendChild(input);
        }
        
        function vote(pollId, optionIndex) {
            fetch('live_polls.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=vote&poll_id=${pollId}&option_index=${optionIndex}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updatePollResults(pollId, data.results);
                    // Mark as voted
                    const options = document.querySelectorAll(`[data-poll-id="${pollId}"] .poll-option`);
                    options.forEach((option, index) => {
                        option.classList.toggle('voted', index === optionIndex);
                    });
                } else {
                    alert(data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to submit vote');
            });
        }
        
        function endPoll(pollId) {
            if (!confirm('End this poll?')) return;
            
            fetch('live_polls.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=end_poll&poll_id=${pollId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error);
                }
            });
        }
        
        function updatePollResults(pollId, results) {
            const pollElement = document.querySelector(`[data-poll-id="${pollId}"]`);
            const options = pollElement.querySelectorAll('.poll-option');
            
            options.forEach((option, index) => {
                const votes = results.votes[index] || 0;
                const percentage = results.total_votes > 0 ? (votes / results.total_votes) * 100 : 0;
                
                const bar = option.querySelector('.vote-bar');
                const badge = option.querySelector('.badge');
                
                bar.style.width = percentage + '%';
                badge.textContent = `${votes} (${percentage.toFixed(1)}%)`;
            });
        }
        
        // Update poll timers
        function updateTimers() {
            document.querySelectorAll('.poll-timer').forEach(timer => {
                const endTime = parseInt(timer.dataset.endTime) * 1000;
                const now = Date.now();
                const remaining = Math.max(0, endTime - now);
                
                if (remaining === 0) {
                    timer.textContent = 'ENDED';
                    timer.classList.add('text-danger');
                } else {
                    const minutes = Math.floor(remaining / 60000);
                    const seconds = Math.floor((remaining % 60000) / 1000);
                    timer.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                }
            });
        }
        
        // Create poll form handler
        document.getElementById('createPollForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('live_polls.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to create poll');
            });
        });
        
        // Update timers every second
        setInterval(updateTimers, 1000);
        updateTimers();
    </script>
    <!-- Chat Integration -->
    <?php if ($isLoggedIn): ?>
    <script src="polling_chat_client.js"></script>
    <script>
        // Initialize chat for live polls page
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
