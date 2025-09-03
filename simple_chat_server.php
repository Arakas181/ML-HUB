<?php
require_once 'config.php';

/**
 * Simplified WebSocket Chat Server for ML HUB Esports
 * No external dependencies required - uses native PHP sockets
 */

class SimpleChatServer {
    private $socket;
    private $clients = [];
    private $rooms = [];
    private $pdo;
    
    public function __construct($host = 'localhost', $port = 8080) {
        $this->pdo = $GLOBALS['pdo'];
        
        // Create socket
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($this->socket, $host, $port);
        socket_listen($this->socket);
        
        echo "Simple Chat Server started on {$host}:{$port}\n";
        echo "Press Ctrl+C to stop the server\n";
    }
    
    public function run() {
        while (true) {
            $read = array_merge([$this->socket], $this->clients);
            $write = null;
            $except = null;
            
            if (socket_select($read, $write, $except, 0, 10000) < 1) {
                continue;
            }
            
            // Handle new connections
            if (in_array($this->socket, $read)) {
                $newSocket = socket_accept($this->socket);
                $this->clients[] = $newSocket;
                
                $header = socket_read($newSocket, 1024);
                $this->performHandshake($header, $newSocket);
                
                echo "New client connected\n";
                
                $key = array_search($this->socket, $read);
                unset($read[$key]);
            }
            
            // Handle client messages
            foreach ($read as $client) {
                $data = @socket_read($client, 1024, PHP_NORMAL_READ);
                
                if ($data === false || $data === '') {
                    $this->disconnect($client);
                    continue;
                }
                
                $decodedData = $this->decode($data);
                if ($decodedData) {
                    $this->handleMessage($client, $decodedData);
                }
            }
        }
    }
    
    private function performHandshake($header, $client) {
        $headers = [];
        $lines = preg_split("/\r\n/", $header);
        
        foreach ($lines as $line) {
            $line = chop($line);
            if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
                $headers[$matches[1]] = $matches[2];
            }
        }
        
        if (!isset($headers['Sec-WebSocket-Key'])) {
            return false;
        }
        
        $key = $headers['Sec-WebSocket-Key'];
        $acceptKey = base64_encode(pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        
        $upgrade = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
                  "Upgrade: websocket\r\n" .
                  "Connection: Upgrade\r\n" .
                  "WebSocket-Origin: *\r\n" .
                  "WebSocket-Location: ws://localhost:8080/\r\n" .
                  "Sec-WebSocket-Accept: $acceptKey\r\n\r\n";
        
        socket_write($client, $upgrade, strlen($upgrade));
        return true;
    }
    
    private function decode($data) {
        $length = ord($data[1]) & 127;
        
        if ($length == 126) {
            $masks = substr($data, 4, 4);
            $data = substr($data, 8);
        } elseif ($length == 127) {
            $masks = substr($data, 10, 4);
            $data = substr($data, 14);
        } else {
            $masks = substr($data, 2, 4);
            $data = substr($data, 6);
        }
        
        $text = '';
        for ($i = 0; $i < strlen($data); ++$i) {
            $text .= $data[$i] ^ $masks[$i % 4];
        }
        
        return json_decode($text, true);
    }
    
    private function encode($message) {
        $data = json_encode($message);
        $length = strlen($data);
        
        if ($length < 126) {
            return pack('CC', 129, $length) . $data;
        } elseif ($length < 65536) {
            return pack('CCn', 129, 126, $length) . $data;
        } else {
            return pack('CCNN', 129, 127, 0, $length) . $data;
        }
    }
    
    private function handleMessage($client, $data) {
        if (!$data || !isset($data['type'])) {
            return;
        }
        
        switch ($data['type']) {
            case 'join_room':
                $this->joinRoom($client, $data);
                break;
            case 'chat_message':
                $this->handleChatMessage($client, $data);
                break;
            case 'poll_vote':
                $this->handlePollVote($client, $data);
                break;
            case 'qa_question':
                $this->handleQAQuestion($client, $data);
                break;
        }
    }
    
    private function joinRoom($client, $data) {
        $roomId = $data['room_id'] ?? 1;
        $userId = $data['user_id'] ?? 0;
        $username = $data['username'] ?? 'Anonymous';
        $userRole = $data['user_role'] ?? 'user';
        
        // Store client info
        $clientId = array_search($client, $this->clients);
        if (!isset($this->rooms[$roomId])) {
            $this->rooms[$roomId] = [];
        }
        
        $this->rooms[$roomId][$clientId] = [
            'socket' => $client,
            'user_id' => $userId,
            'username' => $username,
            'user_role' => $userRole
        ];
        
        // Send confirmation
        $this->sendToClient($client, [
            'type' => 'room_joined',
            'room_id' => $roomId,
            'user_count' => count($this->rooms[$roomId])
        ]);
        
        // Notify room
        $this->broadcastToRoom($roomId, [
            'type' => 'user_joined',
            'username' => $username,
            'user_count' => count($this->rooms[$roomId])
        ], $clientId);
        
        echo "User {$username} joined room {$roomId}\n";
    }
    
    private function handleChatMessage($client, $data) {
        $roomId = $data['room_id'] ?? 1;
        $message = $data['message'] ?? '';
        $userId = $data['user_id'] ?? 0;
        $username = $data['username'] ?? 'Anonymous';
        $userRole = $data['user_role'] ?? 'user';
        
        // Basic spam protection
        if (strlen($message) > 500 || empty(trim($message))) {
            return;
        }
        
        // Save to database
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO enhanced_chat_messages 
                (chat_room_id, user_id, username, user_role, message, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$roomId, $userId, $username, $userRole, $message]);
            $messageId = $this->pdo->lastInsertId();
            
            // Broadcast to room
            $this->broadcastToRoom($roomId, [
                'type' => 'chat_message',
                'id' => $messageId,
                'room_id' => $roomId,
                'user_id' => $userId,
                'username' => $username,
                'user_role' => $userRole,
                'message' => $message,
                'timestamp' => time()
            ]);
            
        } catch (Exception $e) {
            echo "Error saving message: " . $e->getMessage() . "\n";
        }
    }
    
    private function handlePollVote($client, $data) {
        $pollId = $data['poll_id'] ?? 0;
        $optionIndex = $data['option_index'] ?? 0;
        $userId = $data['user_id'] ?? 0;
        
        try {
            // Record vote
            $stmt = $this->pdo->prepare("
                INSERT INTO poll_votes (poll_id, user_id, option_index, voted_at) 
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE option_index = VALUES(option_index), voted_at = NOW()
            ");
            $stmt->execute([$pollId, $userId, $optionIndex]);
            
            // Get poll room
            $stmt = $this->pdo->prepare("SELECT chat_room_id FROM live_polls WHERE id = ?");
            $stmt->execute([$pollId]);
            $poll = $stmt->fetch();
            
            if ($poll) {
                // Get updated results
                $stmt = $this->pdo->prepare("
                    SELECT option_index, COUNT(*) as votes 
                    FROM poll_votes 
                    WHERE poll_id = ? 
                    GROUP BY option_index
                ");
                $stmt->execute([$pollId]);
                $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                
                // Broadcast update
                $this->broadcastToRoom($poll['chat_room_id'], [
                    'type' => 'poll_update',
                    'poll_id' => $pollId,
                    'results' => ['votes' => $results, 'total_votes' => array_sum($results)]
                ]);
            }
            
        } catch (Exception $e) {
            echo "Error recording vote: " . $e->getMessage() . "\n";
        }
    }
    
    private function handleQAQuestion($client, $data) {
        $sessionId = $data['session_id'] ?? 0;
        $question = $data['question'] ?? '';
        $userId = $data['user_id'] ?? 0;
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO qa_questions (qa_session_id, user_id, question, status, created_at) 
                VALUES (?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$sessionId, $userId, $question]);
            $questionId = $this->pdo->lastInsertId();
            
            $this->sendToClient($client, [
                'type' => 'question_submitted',
                'question_id' => $questionId
            ]);
            
        } catch (Exception $e) {
            echo "Error saving question: " . $e->getMessage() . "\n";
        }
    }
    
    private function sendToClient($client, $data) {
        $encoded = $this->encode($data);
        @socket_write($client, $encoded, strlen($encoded));
    }
    
    private function broadcastToRoom($roomId, $data, $excludeClientId = null) {
        if (!isset($this->rooms[$roomId])) {
            return;
        }
        
        foreach ($this->rooms[$roomId] as $clientId => $clientInfo) {
            if ($excludeClientId && $clientId === $excludeClientId) {
                continue;
            }
            
            $this->sendToClient($clientInfo['socket'], $data);
        }
    }
    
    private function disconnect($client) {
        $clientId = array_search($client, $this->clients);
        
        // Remove from rooms
        foreach ($this->rooms as $roomId => $clients) {
            if (isset($clients[$clientId])) {
                unset($this->rooms[$roomId][$clientId]);
                
                // Notify room of user leaving
                $this->broadcastToRoom($roomId, [
                    'type' => 'user_left',
                    'user_count' => count($this->rooms[$roomId])
                ]);
            }
        }
        
        // Remove from clients
        unset($this->clients[$clientId]);
        socket_close($client);
        
        echo "Client disconnected\n";
    }
    
    public function __destruct() {
        if ($this->socket) {
            socket_close($this->socket);
        }
    }
}

// Start the server
if (php_sapi_name() === 'cli') {
    try {
        $server = new SimpleChatServer();
        $server->run();
    } catch (Exception $e) {
        echo "Server error: " . $e->getMessage() . "\n";
    }
} else {
    echo "This script must be run from the command line.\n";
    echo "Usage: php simple_chat_server.php\n";
}
?>
