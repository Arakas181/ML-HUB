<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Handle Q&A actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_session':
            if (in_array($user_role, ['admin', 'super_admin', 'moderator'])) {
                handleCreateSession();
            }
            break;
        case 'submit_question':
            handleSubmitQuestion();
            break;
        case 'moderate_question':
            if (in_array($user_role, ['admin', 'super_admin', 'moderator'])) {
                handleModerateQuestion();
            }
            break;
        case 'answer_question':
            if (in_array($user_role, ['admin', 'super_admin', 'moderator'])) {
                handleAnswerQuestion();
            }
            break;
    }
}

function handleCreateSession() {
    global $pdo, $user_id;
    
    $chat_room_id = $_POST['chat_room_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO qa_sessions (chat_room_id, moderator_id, title, description, status, created_at) 
            VALUES (?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([$chat_room_id, $user_id, $title, $description]);
        $session_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'session_id' => $session_id,
            'title' => $title,
            'description' => $description
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

function handleSubmitQuestion() {
    global $pdo, $user_id;
    
    $session_id = $_POST['session_id'];
    $question = trim($_POST['question']);
    
    try {
        // Check if session is active
        $stmt = $pdo->prepare("SELECT * FROM qa_sessions WHERE id = ? AND status = 'active'");
        $stmt->execute([$session_id]);
        $session = $stmt->fetch();
        
        if (!$session) {
            throw new Exception("Q&A session not found or inactive");
        }
        
        // Check rate limiting (max 3 questions per 5 minutes)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as recent_questions 
            FROM qa_questions 
            WHERE qa_session_id = ? AND user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ");
        $stmt->execute([$session_id, $user_id]);
        $recent_count = $stmt->fetch()['recent_questions'];
        
        if ($recent_count >= 3) {
            throw new Exception("Please wait before submitting another question");
        }
        
        // Submit question
        $stmt = $pdo->prepare("
            INSERT INTO qa_questions (qa_session_id, user_id, question, status, created_at) 
            VALUES (?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$session_id, $user_id, $question]);
        $question_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'question_id' => $question_id,
            'message' => 'Question submitted for moderation'
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

function handleModerateQuestion() {
    global $pdo;
    
    $question_id = $_POST['question_id'];
    $action_type = $_POST['action_type']; // 'approve', 'reject'
    
    try {
        $new_status = ($action_type === 'approve') ? 'approved' : 'rejected';
        
        $stmt = $pdo->prepare("
            UPDATE qa_questions 
            SET status = ?, moderated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$new_status, $question_id]);
        
        echo json_encode(['success' => true, 'status' => $new_status]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

function handleAnswerQuestion() {
    global $pdo, $user_id;
    
    $question_id = $_POST['question_id'];
    $answer = trim($_POST['answer']);
    
    try {
        $stmt = $pdo->prepare("
            UPDATE qa_questions 
            SET answer = ?, answered_by = ?, answered_at = NOW(), status = 'answered' 
            WHERE id = ?
        ");
        $stmt->execute([$answer, $user_id, $question_id]);
        
        echo json_encode(['success' => true, 'answer' => $answer]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Get active Q&A sessions
$room_id = $_GET['room_id'] ?? 1;
$stmt = $pdo->prepare("
    SELECT qs.*, u.username as moderator_name,
           (SELECT COUNT(*) FROM qa_questions qq WHERE qq.qa_session_id = qs.id) as total_questions,
           (SELECT COUNT(*) FROM qa_questions qq WHERE qq.qa_session_id = qs.id AND qq.status = 'pending') as pending_questions
    FROM qa_sessions qs
    JOIN users u ON qs.moderator_id = u.id
    WHERE qs.chat_room_id = ? AND qs.status = 'active'
    ORDER BY qs.created_at DESC
");
$stmt->execute([$room_id]);
$active_sessions = $stmt->fetchAll();

// Get questions for moderation (if moderator)
$pending_questions = [];
if (in_array($user_role, ['admin', 'super_admin', 'moderator'])) {
    $stmt = $pdo->prepare("
        SELECT qq.*, u.username, qs.title as session_title
        FROM qa_questions qq
        JOIN users u ON qq.user_id = u.id
        JOIN qa_sessions qs ON qq.qa_session_id = qs.id
        WHERE qs.chat_room_id = ? AND qq.status = 'pending'
        ORDER BY qq.created_at ASC
    ");
    $stmt->execute([$room_id]);
    $pending_questions = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Q&A System - ML HUB Esports</title>
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
        
        .qa-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            margin-bottom: 1rem;
        }
        
        .question-item {
            background: rgba(255, 255, 255, 0.05);
            border-left: 4px solid var(--highlight-color);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .question-item.answered {
            border-left-color: #28a745;
            background: rgba(40, 167, 69, 0.1);
        }
        
        .question-item.pending {
            border-left-color: #ffc107;
            background: rgba(255, 193, 7, 0.1);
        }
        
        .answer-box {
            background: rgba(40, 167, 69, 0.2);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 0.5rem;
        }
        
        .session-header {
            background: linear-gradient(135deg, var(--highlight-color), #ff6b8a);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
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
                <a class="nav-link" href="live_polls.php">Polls</a>
                <a class="nav-link" href="streaming_hub.php">Streaming</a>
                <a class="nav-link" href="tournaments.php">Tournaments</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-8">
                <h2><i class="fas fa-question-circle me-2"></i>Q&A Sessions</h2>
                
                <?php if (empty($active_sessions)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No active Q&A sessions at the moment.
                    </div>
                <?php else: ?>
                    <?php foreach ($active_sessions as $session): ?>
                        <div class="session-header">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h4><?= htmlspecialchars($session['title']) ?></h4>
                                    <p class="mb-2"><?= htmlspecialchars($session['description']) ?></p>
                                    <small>
                                        Hosted by <?= htmlspecialchars($session['moderator_name']) ?> • 
                                        <?= $session['total_questions'] ?> questions • 
                                        <?= $session['pending_questions'] ?> pending
                                    </small>
                                </div>
                                <?php if (in_array($user_role, ['admin', 'super_admin', 'moderator'])): ?>
                                    <button class="btn btn-outline-light btn-sm" onclick="endSession(<?= $session['id'] ?>)">
                                        End Session
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Question submission form -->
                        <div class="qa-card p-4 mb-4">
                            <h5><i class="fas fa-edit me-2"></i>Ask a Question</h5>
                            <form onsubmit="submitQuestion(event, <?= $session['id'] ?>)">
                                <div class="mb-3">
                                    <textarea class="form-control" rows="3" placeholder="Type your question here..." 
                                              maxlength="500" required id="questionText_<?= $session['id'] ?>"></textarea>
                                    <div class="form-text">Questions are moderated before appearing publicly.</div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Question
                                </button>
                            </form>
                        </div>
                        
                        <!-- Approved questions -->
                        <div id="questions_<?= $session['id'] ?>">
                            <?php
                            $stmt = $pdo->prepare("
                                SELECT qq.*, u.username, au.username as answerer_name
                                FROM qa_questions qq
                                JOIN users u ON qq.user_id = u.id
                                LEFT JOIN users au ON qq.answered_by = au.id
                                WHERE qq.qa_session_id = ? AND qq.status IN ('approved', 'answered')
                                ORDER BY qq.created_at DESC
                            ");
                            $stmt->execute([$session['id']]);
                            $questions = $stmt->fetchAll();
                            ?>
                            
                            <?php foreach ($questions as $question): ?>
                                <div class="question-item <?= $question['status'] ?>" id="question_<?= $question['id'] ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <strong><?= htmlspecialchars($question['username']) ?></strong>
                                        <small class="text-muted">
                                            <?= date('M j, g:i A', strtotime($question['created_at'])) ?>
                                        </small>
                                    </div>
                                    <p class="mb-2"><?= htmlspecialchars($question['question']) ?></p>
                                    
                                    <?php if ($question['status'] === 'answered'): ?>
                                        <div class="answer-box">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-reply me-2 text-success"></i>
                                                <strong class="text-success">
                                                    Answer by <?= htmlspecialchars($question['answerer_name']) ?>
                                                </strong>
                                            </div>
                                            <p class="mb-0"><?= htmlspecialchars($question['answer']) ?></p>
                                        </div>
                                    <?php elseif (in_array($user_role, ['admin', 'super_admin', 'moderator'])): ?>
                                        <div class="mt-2">
                                            <button class="btn btn-success btn-sm" onclick="showAnswerForm(<?= $question['id'] ?>)">
                                                <i class="fas fa-reply me-1"></i>Answer
                                            </button>
                                        </div>
                                        
                                        <div id="answerForm_<?= $question['id'] ?>" class="mt-3" style="display: none;">
                                            <form onsubmit="submitAnswer(event, <?= $question['id'] ?>)">
                                                <div class="mb-2">
                                                    <textarea class="form-control" rows="3" placeholder="Type your answer..." 
                                                              required id="answerText_<?= $question['id'] ?>"></textarea>
                                                </div>
                                                <button type="submit" class="btn btn-success btn-sm me-2">Submit Answer</button>
                                                <button type="button" class="btn btn-secondary btn-sm" onclick="hideAnswerForm(<?= $question['id'] ?>)">Cancel</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="col-lg-4">
                <?php if (in_array($user_role, ['admin', 'super_admin', 'moderator'])): ?>
                    <div class="qa-card p-4 mb-4">
                        <h5><i class="fas fa-plus-circle me-2"></i>Create Q&A Session</h5>
                        
                        <form id="createSessionForm">
                            <input type="hidden" name="action" value="create_session">
                            <input type="hidden" name="chat_room_id" value="<?= $room_id ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Title</label>
                                <input type="text" class="form-control" name="title" required maxlength="100">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3" maxlength="500"></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-rocket me-2"></i>Start Session
                            </button>
                        </form>
                    </div>
                    
                    <?php if (!empty($pending_questions)): ?>
                        <div class="qa-card p-4">
                            <h5><i class="fas fa-clock me-2"></i>Pending Questions (<?= count($pending_questions) ?>)</h5>
                            
                            <?php foreach ($pending_questions as $question): ?>
                                <div class="question-item pending" id="pending_<?= $question['id'] ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <strong><?= htmlspecialchars($question['username']) ?></strong>
                                        <small class="text-muted">
                                            <?= date('g:i A', strtotime($question['created_at'])) ?>
                                        </small>
                                    </div>
                                    <p class="mb-2"><?= htmlspecialchars($question['question']) ?></p>
                                    <small class="text-muted mb-2 d-block">Session: <?= htmlspecialchars($question['session_title']) ?></small>
                                    
                                    <div class="btn-group btn-group-sm w-100">
                                        <button class="btn btn-success" onclick="moderateQuestion(<?= $question['id'] ?>, 'approve')">
                                            <i class="fas fa-check me-1"></i>Approve
                                        </button>
                                        <button class="btn btn-danger" onclick="moderateQuestion(<?= $question['id'] ?>, 'reject')">
                                            <i class="fas fa-times me-1"></i>Reject
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="qa-card p-3 mt-4">
                    <h6><i class="fas fa-info-circle me-2"></i>How it works</h6>
                    <ul class="small mb-0">
                        <li>Submit questions during active Q&A sessions</li>
                        <li>Questions are moderated before appearing publicly</li>
                        <li>Moderators will answer approved questions</li>
                        <li>Rate limit: 3 questions per 5 minutes</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function submitQuestion(event, sessionId) {
            event.preventDefault();
            
            const questionText = document.getElementById(`questionText_${sessionId}`).value;
            
            fetch('qa_system.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=submit_question&session_id=${sessionId}&question=${encodeURIComponent(questionText)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById(`questionText_${sessionId}`).value = '';
                    showAlert('Question submitted for moderation!', 'success');
                } else {
                    showAlert(data.error, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Failed to submit question', 'danger');
            });
        }
        
        function moderateQuestion(questionId, actionType) {
            fetch('qa_system.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=moderate_question&question_id=${questionId}&action_type=${actionType}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById(`pending_${questionId}`).remove();
                    if (actionType === 'approve') {
                        showAlert('Question approved!', 'success');
                        // Refresh to show in main feed
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showAlert('Question rejected', 'info');
                    }
                } else {
                    showAlert(data.error, 'danger');
                }
            });
        }
        
        function showAnswerForm(questionId) {
            document.getElementById(`answerForm_${questionId}`).style.display = 'block';
        }
        
        function hideAnswerForm(questionId) {
            document.getElementById(`answerForm_${questionId}`).style.display = 'none';
        }
        
        function submitAnswer(event, questionId) {
            event.preventDefault();
            
            const answerText = document.getElementById(`answerText_${questionId}`).value;
            
            fetch('qa_system.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=answer_question&question_id=${questionId}&answer=${encodeURIComponent(answerText)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Answer submitted!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(data.error, 'danger');
                }
            });
        }
        
        // Create session form handler
        document.getElementById('createSessionForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('qa_system.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Q&A session created!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(data.error, 'danger');
                }
            });
        });
        
        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.top = '20px';
            alertDiv.style.right = '20px';
            alertDiv.style.zIndex = '9999';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.parentNode.removeChild(alertDiv);
                }
            }, 5000);
        }
    </script>
</body>
</html>
