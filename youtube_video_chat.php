<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];
$userRole = $_SESSION['role'];

// Handle different request methods
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetMessages();
        break;
    case 'POST':
        handlePostMessage();
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function handleGetMessages() {
    global $pdo;
    
    $videoId = $_GET['video_id'] ?? '';
    $lastMessageId = $_GET['last_id'] ?? 0;
    
    if (empty($videoId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Video ID required']);
        return;
    }
    
    try {
        // Get messages for this specific YouTube video
        $stmt = $pdo->prepare("
            SELECT yvc.*, u.username, u.role, u.avatar_url
            FROM youtube_video_comments yvc
            JOIN users u ON yvc.user_id = u.id
            WHERE yvc.video_id = ? AND yvc.id > ? AND yvc.is_deleted = 0
            ORDER BY yvc.created_at ASC
            LIMIT 50
        ");
        $stmt->execute([$videoId, $lastMessageId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total viewer count for this video
        $viewerStmt = $pdo->prepare("
            SELECT COUNT(DISTINCT user_id) as viewer_count
            FROM youtube_video_viewers 
            WHERE video_id = ? AND last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ");
        $viewerStmt->execute([$videoId]);
        $viewerData = $viewerStmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'messages' => $messages,
            'viewer_count' => $viewerData['viewer_count'] ?? 0
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handlePostMessage() {
    global $pdo, $userId, $username, $userRole;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $videoId = $input['video_id'] ?? '';
    $message = trim($input['message'] ?? '');
    $action = $input['action'] ?? 'send_message';
    
    if (empty($videoId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Video ID required']);
        return;
    }
    
    try {
        switch ($action) {
            case 'send_message':
                if (empty($message)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Message cannot be empty']);
                    return;
                }
                
                // Check for spam/rate limiting
                if (isRateLimited($userId, $videoId)) {
                    http_response_code(429);
                    echo json_encode(['error' => 'Please wait before sending another message']);
                    return;
                }
                
                // Save message to database
                $stmt = $pdo->prepare("
                    INSERT INTO youtube_video_comments (video_id, user_id, username, user_role, message)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$videoId, $userId, $username, $userRole, $message]);
                $messageId = $pdo->lastInsertId();
                
                // Update viewer activity
                updateViewerActivity($userId, $videoId);
                
                echo json_encode([
                    'success' => true,
                    'message_id' => $messageId,
                    'message' => 'Message sent successfully'
                ]);
                break;
                
            case 'join_video':
                // Record user as viewing this video
                updateViewerActivity($userId, $videoId);
                
                // Create video session if it doesn't exist
                createVideoSession($videoId);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Joined video chat'
                ]);
                break;
                
            case 'leave_video':
                // Remove user from active viewers
                $stmt = $pdo->prepare("DELETE FROM youtube_video_viewers WHERE user_id = ? AND video_id = ?");
                $stmt->execute([$userId, $videoId]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Left video chat'
                ]);
                break;
                
            case 'clear_video_chat':
                // Clear all messages for a specific video (when video URL changes)
                if ($userRole === 'admin' || $userRole === 'super_admin') {
                    $stmt = $pdo->prepare("DELETE FROM youtube_video_comments WHERE video_id = ?");
                    $stmt->execute([$videoId]);
                    
                    $stmt = $pdo->prepare("DELETE FROM youtube_video_viewers WHERE video_id = ?");
                    $stmt->execute([$videoId]);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Video chat cleared'
                    ]);
                } else {
                    http_response_code(403);
                    echo json_encode(['error' => 'Permission denied']);
                }
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
                break;
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function isRateLimited($userId, $videoId) {
    global $pdo;
    
    try {
        // Check if user sent a message in the last 3 seconds
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as recent_count
            FROM youtube_video_comments 
            WHERE user_id = ? AND video_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 3 SECOND)
        ");
        $stmt->execute([$userId, $videoId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['recent_count'] > 0;
        
    } catch (PDOException $e) {
        return false; // Allow message if we can't check
    }
}

function updateViewerActivity($userId, $videoId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO youtube_video_viewers (user_id, video_id, last_seen)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE last_seen = NOW()
        ");
        $stmt->execute([$userId, $videoId]);
        
    } catch (PDOException $e) {
        // Log error but don't fail the request
        error_log("Failed to update viewer activity: " . $e->getMessage());
    }
}

function createVideoSession($videoId) {
    global $pdo;
    
    try {
        // Create or update video session
        $stmt = $pdo->prepare("
            INSERT INTO youtube_video_sessions (video_id, created_at, last_activity)
            VALUES (?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE last_activity = NOW()
        ");
        $stmt->execute([$videoId]);
        
    } catch (PDOException $e) {
        error_log("Failed to create video session: " . $e->getMessage());
    }
}

function clearOldVideoChat($oldVideoId) {
    global $pdo;
    
    try {
        // Delete messages from old video
        $stmt = $pdo->prepare("DELETE FROM youtube_video_comments WHERE video_id = ?");
        $stmt->execute([$oldVideoId]);
        
        // Delete viewers from old video
        $stmt = $pdo->prepare("DELETE FROM youtube_video_viewers WHERE video_id = ?");
        $stmt->execute([$oldVideoId]);
        
        // Delete old video session
        $stmt = $pdo->prepare("DELETE FROM youtube_video_sessions WHERE video_id = ?");
        $stmt->execute([$oldVideoId]);
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Failed to clear old video chat: " . $e->getMessage());
        return false;
    }
}
?>
