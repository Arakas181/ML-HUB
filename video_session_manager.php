<?php
session_start();
require_once 'config.php';

// Video Session Manager - Handles automatic chat clearing when video changes
class VideoSessionManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function checkVideoChange($newVideoId) {
        try {
            // Get current active video session
            $stmt = $this->pdo->prepare("
                SELECT video_id, created_at 
                FROM youtube_video_sessions 
                WHERE is_active = 1 
                ORDER BY last_activity DESC 
                LIMIT 1
            ");
            $stmt->execute();
            $currentSession = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($currentSession && $currentSession['video_id'] !== $newVideoId) {
                // Video has changed - clear old chat
                $this->clearVideoChat($currentSession['video_id']);
                $this->deactivateSession($currentSession['video_id']);
                
                // Create new session for new video
                $this->createNewSession($newVideoId);
                
                return [
                    'video_changed' => true,
                    'old_video_id' => $currentSession['video_id'],
                    'new_video_id' => $newVideoId
                ];
            } elseif (!$currentSession && !empty($newVideoId)) {
                // No active session, create new one
                $this->createNewSession($newVideoId);
                
                return [
                    'video_changed' => false,
                    'new_session' => true,
                    'video_id' => $newVideoId
                ];
            }
            
            return [
                'video_changed' => false,
                'video_id' => $newVideoId
            ];
            
        } catch (PDOException $e) {
            error_log("Video session check error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    
    private function clearVideoChat($videoId) {
        try {
            // Delete all messages for this video
            $stmt = $this->pdo->prepare("DELETE FROM youtube_video_comments WHERE video_id = ?");
            $stmt->execute([$videoId]);
            
            // Delete all viewers for this video
            $stmt = $this->pdo->prepare("DELETE FROM youtube_video_viewers WHERE video_id = ?");
            $stmt->execute([$videoId]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Failed to clear video chat: " . $e->getMessage());
            return false;
        }
    }
    
    private function deactivateSession($videoId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE youtube_video_sessions 
                SET is_active = 0, ended_at = NOW() 
                WHERE video_id = ?
            ");
            $stmt->execute([$videoId]);
            
        } catch (PDOException $e) {
            error_log("Failed to deactivate session: " . $e->getMessage());
        }
    }
    
    private function createNewSession($videoId) {
        try {
            // Deactivate any existing sessions first
            $stmt = $this->pdo->prepare("UPDATE youtube_video_sessions SET is_active = 0");
            $stmt->execute();
            
            // Create new active session
            $stmt = $this->pdo->prepare("
                INSERT INTO youtube_video_sessions (video_id, created_at, last_activity, is_active)
                VALUES (?, NOW(), NOW(), 1)
                ON DUPLICATE KEY UPDATE 
                    last_activity = NOW(), 
                    is_active = 1,
                    ended_at = NULL
            ");
            $stmt->execute([$videoId]);
            
        } catch (PDOException $e) {
            error_log("Failed to create new session: " . $e->getMessage());
        }
    }
    
    public function getAllMessages($videoId) {
        try {
            // Get all messages for this video (for persistence)
            $stmt = $this->pdo->prepare("
                SELECT yvc.*, u.username, u.role, u.avatar_url
                FROM youtube_video_comments yvc
                JOIN users u ON yvc.user_id = u.id
                WHERE yvc.video_id = ? AND yvc.is_deleted = 0
                ORDER BY yvc.created_at ASC
            ");
            $stmt->execute([$videoId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Failed to get all messages: " . $e->getMessage());
            return [];
        }
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    $manager = new VideoSessionManager($pdo);
    
    switch ($action) {
        case 'check_video_change':
            $newVideoId = $input['video_id'] ?? '';
            $result = $manager->checkVideoChange($newVideoId);
            echo json_encode($result);
            break;
            
        case 'get_all_messages':
            $videoId = $input['video_id'] ?? '';
            $messages = $manager->getAllMessages($videoId);
            echo json_encode([
                'success' => true,
                'messages' => $messages
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}
?>
