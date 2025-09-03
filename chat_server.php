<?php
require_once 'config.php';
require_once 'vendor/autoload.php'; // For WebSocket library

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class ChatServer implements MessageComponentInterface {
    protected $clients;
    protected $rooms;
    protected $pdo;
    
    public function __construct($pdo) {
        $this->clients = new \SplObjectStorage;
        $this->rooms = [];
        $this->pdo = $pdo;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        switch ($data['type']) {
            case 'join_room':
                $this->joinRoom($from, $data);
                break;
            case 'chat_message':
                $this->handleChatMessage($from, $data);
                break;
            case 'poll_vote':
                $this->handlePollVote($from, $data);
                break;
            case 'qa_question':
                $this->handleQAQuestion($from, $data);
                break;
            case 'moderate_message':
                $this->handleModeration($from, $data);
                break;
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        
        // Remove from all rooms
        foreach ($this->rooms as $roomId => $room) {
            if (isset($room['clients'][$conn->resourceId])) {
                unset($this->rooms[$roomId]['clients'][$conn->resourceId]);
            }
        }
        
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    private function joinRoom(ConnectionInterface $conn, $data) {
        $roomId = $data['room_id'];
        $userId = $data['user_id'];
        $username = $data['username'];
        $userRole = $data['user_role'];
        
        // Verify user permissions for room
        if (!$this->canJoinRoom($userId, $roomId)) {
            $conn->send(json_encode([
                'type' => 'error',
                'message' => 'Access denied to this chat room'
            ]));
            return;
        }
        
        // Initialize room if not exists
        if (!isset($this->rooms[$roomId])) {
            $this->rooms[$roomId] = [
                'clients' => [],
                'settings' => $this->getRoomSettings($roomId)
            ];
        }
        
        // Add client to room
        $this->rooms[$roomId]['clients'][$conn->resourceId] = [
            'connection' => $conn,
            'user_id' => $userId,
            'username' => $username,
            'user_role' => $userRole
        ];
        
        // Send room info to client
        $conn->send(json_encode([
            'type' => 'room_joined',
            'room_id' => $roomId,
            'settings' => $this->rooms[$roomId]['settings'],
            'user_count' => count($this->rooms[$roomId]['clients'])
        ]));
        
        // Notify room of new user
        $this->broadcastToRoom($roomId, [
            'type' => 'user_joined',
            'username' => $username,
            'user_count' => count($this->rooms[$roomId]['clients'])
        ], $conn->resourceId);
        
        // Send recent messages
        $this->sendRecentMessages($conn, $roomId);
    }

    private function handleChatMessage(ConnectionInterface $from, $data) {
        $roomId = $data['room_id'];
        $message = $data['message'];
        $userId = $data['user_id'];
        $username = $data['username'];
        $userRole = $data['user_role'];
        
        // Check if user is in room
        if (!isset($this->rooms[$roomId]['clients'][$from->resourceId])) {
            return;
        }
        
        // Apply moderation filters
        if ($this->isMessageBlocked($message, $roomId)) {
            $from->send(json_encode([
                'type' => 'message_blocked',
                'reason' => 'Message contains blocked content'
            ]));
            return;
        }
        
        // Check slow mode
        if ($this->isSlowModeActive($userId, $roomId)) {
            $from->send(json_encode([
                'type' => 'slow_mode',
                'message' => 'Please wait before sending another message'
            ]));
            return;
        }
        
        // Save message to database
        $messageId = $this->saveMessage($roomId, $userId, $username, $userRole, $message);
        
        // Broadcast message to room
        $messageData = [
            'type' => 'chat_message',
            'id' => $messageId,
            'room_id' => $roomId,
            'user_id' => $userId,
            'username' => $username,
            'user_role' => $userRole,
            'message' => $message,
            'timestamp' => time()
        ];
        
        $this->broadcastToRoom($roomId, $messageData);
        
        // Sync with external platforms if enabled
        $this->syncToExternalPlatforms($roomId, $messageData);
    }

    private function handlePollVote(ConnectionInterface $from, $data) {
        $pollId = $data['poll_id'];
        $optionIndex = $data['option_index'];
        $userId = $data['user_id'];
        
        try {
            // Check if poll is active
            $stmt = $this->pdo->prepare("SELECT * FROM live_polls WHERE id = ? AND status = 'active'");
            $stmt->execute([$pollId]);
            $poll = $stmt->fetch();
            
            if (!$poll) {
                $from->send(json_encode(['type' => 'error', 'message' => 'Poll not found or inactive']));
                return;
            }
            
            // Record vote
            $stmt = $this->pdo->prepare("
                INSERT INTO poll_votes (poll_id, user_id, option_index) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE option_index = VALUES(option_index)
            ");
            $stmt->execute([$pollId, $userId, $optionIndex]);
            
            // Get updated results
            $results = $this->getPollResults($pollId);
            
            // Broadcast results to room
            $this->broadcastToRoom($poll['chat_room_id'], [
                'type' => 'poll_update',
                'poll_id' => $pollId,
                'results' => $results
            ]);
            
        } catch (Exception $e) {
            $from->send(json_encode(['type' => 'error', 'message' => 'Vote failed']));
        }
    }

    private function handleQAQuestion(ConnectionInterface $from, $data) {
        $sessionId = $data['session_id'];
        $question = $data['question'];
        $userId = $data['user_id'];
        
        try {
            // Save question
            $stmt = $this->pdo->prepare("
                INSERT INTO qa_questions (qa_session_id, user_id, question) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$sessionId, $userId, $question]);
            $questionId = $this->pdo->lastInsertId();
            
            // Get session info
            $stmt = $this->pdo->prepare("SELECT chat_room_id FROM qa_sessions WHERE id = ?");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch();
            
            if ($session) {
                // Notify moderators
                $this->broadcastToModerators($session['chat_room_id'], [
                    'type' => 'new_question',
                    'question_id' => $questionId,
                    'question' => $question,
                    'user_id' => $userId
                ]);
            }
            
            $from->send(json_encode([
                'type' => 'question_submitted',
                'question_id' => $questionId
            ]));
            
        } catch (Exception $e) {
            $from->send(json_encode(['type' => 'error', 'message' => 'Question submission failed']));
        }
    }

    private function handleModeration(ConnectionInterface $from, $data) {
        $action = $data['action'];
        $targetUserId = $data['target_user_id'];
        $roomId = $data['room_id'];
        $moderatorId = $data['moderator_id'];
        
        // Verify moderator permissions
        if (!$this->isModerator($moderatorId, $roomId)) {
            return;
        }
        
        switch ($action) {
            case 'delete_message':
                $this->deleteMessage($data['message_id'], $moderatorId);
                break;
            case 'timeout_user':
                $this->timeoutUser($targetUserId, $roomId, $data['duration']);
                break;
            case 'ban_user':
                $this->banUser($targetUserId, $roomId);
                break;
        }
    }

    private function canJoinRoom($userId, $roomId) {
        try {
            $stmt = $this->pdo->prepare("SELECT type FROM chat_rooms WHERE id = ?");
            $stmt->execute([$roomId]);
            $room = $stmt->fetch();
            
            if (!$room) return false;
            
            // Add specific permission checks based on room type
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private function getRoomSettings($roomId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM chat_rooms WHERE id = ?");
            $stmt->execute([$roomId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            return [];
        }
    }

    private function saveMessage($roomId, $userId, $username, $userRole, $message) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO enhanced_chat_messages 
                (chat_room_id, user_id, username, user_role, message) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$roomId, $userId, $username, $userRole, $message]);
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            return null;
        }
    }

    private function sendRecentMessages(ConnectionInterface $conn, $roomId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM enhanced_chat_messages 
                WHERE chat_room_id = ? AND is_deleted = 0 
                ORDER BY created_at DESC 
                LIMIT 50
            ");
            $stmt->execute([$roomId]);
            $messages = array_reverse($stmt->fetchAll());
            
            foreach ($messages as $msg) {
                $conn->send(json_encode([
                    'type' => 'chat_message',
                    'id' => $msg['id'],
                    'room_id' => $roomId,
                    'user_id' => $msg['user_id'],
                    'username' => $msg['username'],
                    'user_role' => $msg['user_role'],
                    'message' => $msg['message'],
                    'timestamp' => strtotime($msg['created_at'])
                ]));
            }
        } catch (Exception $e) {
            // Handle error
        }
    }

    private function broadcastToRoom($roomId, $data, $excludeId = null) {
        if (!isset($this->rooms[$roomId])) return;
        
        foreach ($this->rooms[$roomId]['clients'] as $clientId => $client) {
            if ($excludeId && $clientId === $excludeId) continue;
            $client['connection']->send(json_encode($data));
        }
    }

    private function broadcastToModerators($roomId, $data) {
        if (!isset($this->rooms[$roomId])) return;
        
        foreach ($this->rooms[$roomId]['clients'] as $client) {
            if (in_array($client['user_role'], ['admin', 'super_admin', 'moderator'])) {
                $client['connection']->send(json_encode($data));
            }
        }
    }

    private function isMessageBlocked($message, $roomId) {
        // Implement spam/toxicity filters
        $blockedWords = ['spam', 'toxic']; // This would be more comprehensive
        
        foreach ($blockedWords as $word) {
            if (stripos($message, $word) !== false) {
                return true;
            }
        }
        
        return false;
    }

    private function isSlowModeActive($userId, $roomId) {
        // Check if user sent message recently based on slow mode settings
        return false; // Simplified for now
    }

    private function syncToExternalPlatforms($roomId, $messageData) {
        // Sync with Twitch, YouTube, Facebook chat APIs
        // This would require API integrations for each platform
    }

    private function getPollResults($pollId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT option_index, COUNT(*) as votes 
                FROM poll_votes 
                WHERE poll_id = ? 
                GROUP BY option_index
            ");
            $stmt->execute([$pollId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    private function isModerator($userId, $roomId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 1 FROM chat_moderators 
                WHERE user_id = ? AND chat_room_id = ?
            ");
            $stmt->execute([$userId, $roomId]);
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    private function deleteMessage($messageId, $moderatorId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE enhanced_chat_messages 
                SET is_deleted = 1, deleted_by = ? 
                WHERE id = ?
            ");
            $stmt->execute([$moderatorId, $messageId]);
        } catch (Exception $e) {
            // Handle error
        }
    }

    private function timeoutUser($userId, $roomId, $duration) {
        // Implement user timeout logic
    }

    private function banUser($userId, $roomId) {
        // Implement user ban logic
    }
}

// Start the server
if (php_sapi_name() === 'cli') {
    $server = IoServer::factory(
        new HttpServer(
            new WsServer(
                new ChatServer($pdo)
            )
        ),
        8080
    );

    echo "Chat server started on port 8080\n";
    $server->run();
}
?>
