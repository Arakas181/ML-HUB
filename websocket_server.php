<?php
require_once 'vendor/autoload.php';
require_once 'config.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class EnhancedChatServer implements MessageComponentInterface {
    protected $clients;
    protected $rooms;
    protected $userConnections;
    protected $pdo;
    
    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->rooms = [];
        $this->userConnections = [];
        
        // Database connection
        global $pdo;
        $this->pdo = $pdo;
        
        echo "Enhanced Chat Server Started\n";
    }
    
    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }
    
    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if (!$data) {
            $from->send(json_encode(['error' => 'Invalid JSON']));
            return;
        }
        
        switch ($data['type']) {
            case 'join':
                $this->handleJoin($from, $data);
                break;
            case 'message':
                $this->handleMessage($from, $data);
                break;
            case 'typing':
                $this->handleTyping($from, $data);
                break;
            case 'moderation':
                $this->handleModeration($from, $data);
                break;
            case 'poll_vote':
                $this->handlePollVote($from, $data);
                break;
            default:
                $from->send(json_encode(['error' => 'Unknown message type']));
        }
    }
    
    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        
        // Remove from rooms and user connections
        foreach ($this->rooms as $roomId => &$room) {
            if (isset($room['connections'][$conn->resourceId])) {
                unset($room['connections'][$conn->resourceId]);
                $this->broadcastToRoom($roomId, [
                    'type' => 'user_left',
                    'user' => $room['users'][$conn->resourceId] ?? 'Unknown'
                ]);
            }
        }
        
        echo "Connection {$conn->resourceId} has disconnected\n";
    }
    
    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
    
    private function handleJoin($conn, $data) {
        $roomId = $data['room_id'] ?? 1;
        $userId = $data['user_id'] ?? null;
        $username = $data['username'] ?? 'Anonymous';
        $userRole = $data['user_role'] ?? 'user';
        
        if (!isset($this->rooms[$roomId])) {
            $this->rooms[$roomId] = [
                'connections' => [],
                'users' => [],
                'moderators' => []
            ];
        }
        
        $this->rooms[$roomId]['connections'][$conn->resourceId] = $conn;
        $this->rooms[$roomId]['users'][$conn->resourceId] = [
            'id' => $userId,
            'username' => $username,
            'role' => $userRole
        ];
        
        if (in_array($userRole, ['moderator', 'admin', 'super_admin'])) {
            $this->rooms[$roomId]['moderators'][$conn->resourceId] = true;
        }
        
        $conn->send(json_encode([
            'type' => 'joined',
            'room_id' => $roomId,
            'user_count' => count($this->rooms[$roomId]['connections'])
        ]));
        
        $this->broadcastToRoom($roomId, [
            'type' => 'user_joined',
            'username' => $username,
            'user_count' => count($this->rooms[$roomId]['connections'])
        ], $conn->resourceId);
    }
    
    private function handleMessage($conn, $data) {
        $roomId = $data['room_id'] ?? 1;
        $message = $data['message'] ?? '';
        $userId = $data['user_id'] ?? null;
        
        if (!isset($this->rooms[$roomId]['connections'][$conn->resourceId])) {
            $conn->send(json_encode(['error' => 'Not joined to room']));
            return;
        }
        
        $user = $this->rooms[$roomId]['users'][$conn->resourceId];
        
        // Store message in database
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO enhanced_chat_messages 
                (room_id, user_id, username, user_role, message, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $roomId, $userId, $user['username'], $user['role'], $message
            ]);
            
            $messageId = $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log('Chat message storage error: ' . $e->getMessage());
            $messageId = null;
        }
        
        // Broadcast message to room
        $this->broadcastToRoom($roomId, [
            'type' => 'message',
            'id' => $messageId,
            'username' => $user['username'],
            'user_role' => $user['role'],
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    private function handleTyping($conn, $data) {
        $roomId = $data['room_id'] ?? 1;
        $isTyping = $data['is_typing'] ?? false;
        
        if (!isset($this->rooms[$roomId]['connections'][$conn->resourceId])) {
            return;
        }
        
        $user = $this->rooms[$roomId]['users'][$conn->resourceId];
        
        $this->broadcastToRoom($roomId, [
            'type' => 'typing',
            'username' => $user['username'],
            'is_typing' => $isTyping
        ], $conn->resourceId);
    }
    
    private function handleModeration($conn, $data) {
        $roomId = $data['room_id'] ?? 1;
        $action = $data['action'] ?? '';
        $targetUser = $data['target_user'] ?? '';
        
        // Check if user is moderator
        if (!isset($this->rooms[$roomId]['moderators'][$conn->resourceId])) {
            $conn->send(json_encode(['error' => 'Insufficient permissions']));
            return;
        }
        
        switch ($action) {
            case 'timeout':
                $this->timeoutUser($roomId, $targetUser, $data['duration'] ?? 300);
                break;
            case 'ban':
                $this->banUser($roomId, $targetUser);
                break;
            case 'delete_message':
                $this->deleteMessage($roomId, $data['message_id'] ?? null);
                break;
        }
    }
    
    private function handlePollVote($conn, $data) {
        $pollId = $data['poll_id'] ?? null;
        $option = $data['option'] ?? null;
        $userId = $data['user_id'] ?? null;
        
        if (!$pollId || !$option || !$userId) {
            $conn->send(json_encode(['error' => 'Invalid poll vote data']));
            return;
        }
        
        try {
            // Store vote
            $stmt = $this->pdo->prepare("
                INSERT INTO poll_votes (poll_id, user_id, option_selected, created_at) 
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE option_selected = VALUES(option_selected)
            ");
            $stmt->execute([$pollId, $userId, $option]);
            
            // Get updated results
            $stmt = $this->pdo->prepare("
                SELECT option_selected, COUNT(*) as votes 
                FROM poll_votes 
                WHERE poll_id = ? 
                GROUP BY option_selected
            ");
            $stmt->execute([$pollId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Broadcast updated poll results
            $this->broadcastToAllRooms([
                'type' => 'poll_update',
                'poll_id' => $pollId,
                'results' => $results
            ]);
            
        } catch (PDOException $e) {
            error_log('Poll vote error: ' . $e->getMessage());
            $conn->send(json_encode(['error' => 'Vote failed']));
        }
    }
    
    private function broadcastToRoom($roomId, $data, $excludeId = null) {
        if (!isset($this->rooms[$roomId])) return;
        
        $message = json_encode($data);
        
        foreach ($this->rooms[$roomId]['connections'] as $connId => $conn) {
            if ($excludeId && $connId === $excludeId) continue;
            $conn->send($message);
        }
    }
    
    private function broadcastToAllRooms($data) {
        $message = json_encode($data);
        
        foreach ($this->clients as $client) {
            $client->send($message);
        }
    }
    
    private function timeoutUser($roomId, $username, $duration) {
        // Implementation for timing out users
        $this->broadcastToRoom($roomId, [
            'type' => 'user_timeout',
            'username' => $username,
            'duration' => $duration
        ]);
    }
    
    private function banUser($roomId, $username) {
        // Implementation for banning users
        $this->broadcastToRoom($roomId, [
            'type' => 'user_banned',
            'username' => $username
        ]);
    }
    
    private function deleteMessage($roomId, $messageId) {
        if (!$messageId) return;
        
        try {
            $stmt = $this->pdo->prepare("UPDATE enhanced_chat_messages SET deleted = 1 WHERE id = ?");
            $stmt->execute([$messageId]);
            
            $this->broadcastToRoom($roomId, [
                'type' => 'message_deleted',
                'message_id' => $messageId
            ]);
        } catch (PDOException $e) {
            error_log('Message deletion error: ' . $e->getMessage());
        }
    }
}

// Start the server
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new EnhancedChatServer()
        )
    ),
    8080
);

echo "Enhanced WebSocket Chat Server running on port 8080\n";
$server->run();
?>
