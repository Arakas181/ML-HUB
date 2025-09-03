<?php
require_once 'config.php';

header('Content-Type: application/json');

// Get chat messages
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    
    try {
        $stmt = $pdo->prepare("
            SELECT cm.*, u.username, u.role as user_role 
            FROM chat_messages cm 
            JOIN users u ON cm.user_id = u.id 
            ORDER BY cm.created_at DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format messages for frontend
        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = [
                'id' => $message['id'],
                'user_id' => $message['user_id'],
                'username' => $message['username'],
                'user_role' => $message['user_role'],
                'message' => $message['message'],
                'timestamp' => $message['created_at']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'messages' => array_reverse($formattedMessages) // Reverse to show oldest first
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to fetch messages: ' . $e->getMessage()
        ]);
    }
}

// Send a new message
else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isLoggedIn()) {
        echo json_encode([
            'success' => false,
            'error' => 'You must be logged in to send messages'
        ]);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $message = trim($data['message'] ?? '');
    
    if (empty($message)) {
        echo json_encode([
            'success' => false,
            'error' => 'Message cannot be empty'
        ]);
        exit;
    }
    
    if (strlen($message) > 500) {
        echo json_encode([
            'success' => false,
            'error' => 'Message is too long (max 500 characters)'
        ]);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (user_id, username, user_role, message) 
            VALUES (:user_id, :username, :user_role, :message)
        ");
        
        $stmt->execute([
            ':user_id' => getUserId(),
            ':username' => getUsername(),
            ':user_role' => getUserRole(),
            ':message' => htmlspecialchars($message)
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Message sent successfully'
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to send message: ' . $e->getMessage()
        ]);
    }
}
?>
