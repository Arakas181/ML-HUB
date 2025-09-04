<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'User not authenticated']);
    exit();
}

// Ensure chat_messages table exists
try {
    $pdo->query("SELECT 1 FROM chat_messages LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS chat_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                username VARCHAR(50) NOT NULL,
                user_role ENUM('user', 'squad_leader', 'moderator', 'admin', 'super_admin') NOT NULL DEFAULT 'user',
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
    } catch (PDOException $e2) {
        error_log('Failed to create chat_messages table: ' . $e2->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
        exit();
    }
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        // Send a new message
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['message']) || empty(trim($input['message']))) {
            http_response_code(400);
            echo json_encode(['error' => 'Message is required']);
            exit();
        }
        
        $message = trim($input['message']);
        $userId = $_SESSION['user_id'];
        $username = $_SESSION['username'] ?? 'Anonymous';
        $userRole = $_SESSION['role'] ?? 'user';
        
        try {
            // Check if chat_messages table exists and has correct structure
            $tableExists = false;
            try {
                $result = $pdo->query("DESCRIBE chat_messages");
                $columns = $result->fetchAll(PDO::FETCH_COLUMN);
                
                // Check if username column exists
                if (!in_array('username', $columns)) {
                    // Add missing username column
                    $pdo->exec("ALTER TABLE chat_messages ADD COLUMN username VARCHAR(50) NOT NULL DEFAULT 'Anonymous' AFTER user_id");
                }
                
                // Check if user_role column exists and has correct values
                if (!in_array('user_role', $columns)) {
                    $pdo->exec("ALTER TABLE chat_messages ADD COLUMN user_role ENUM('user', 'squad_leader', 'moderator', 'admin', 'super_admin') NOT NULL DEFAULT 'user' AFTER username");
                }
                
                $tableExists = true;
            } catch (PDOException $e) {
                // Table doesn't exist, create it
                $pdo->exec("
                    CREATE TABLE chat_messages (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        username VARCHAR(50) NOT NULL,
                        user_role ENUM('user', 'squad_leader', 'moderator', 'admin', 'super_admin') NOT NULL DEFAULT 'user',
                        message TEXT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                $tableExists = true;
            }
            
            if ($tableExists) {
                $stmt = $pdo->prepare("INSERT INTO chat_messages (user_id, username, user_role, message) VALUES (?, ?, ?, ?)");
                $stmt->execute([$userId, $username, $userRole, $message]);
                
                $messageId = $pdo->lastInsertId();
                
                // Return the new message
                echo json_encode([
                    'success' => true,
                    'message' => [
                        'id' => $messageId,
                        'username' => $username,
                        'user_role' => $userRole,
                        'message' => $message,
                        'created_at' => date('Y-m-d H:i:s')
                    ]
                ]);
            } else {
                throw new Exception('Unable to create or access chat_messages table');
            }
        } catch (PDOException $e) {
            error_log('Database error sending message: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        } catch (Exception $e) {
            error_log('Error sending message: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to send message: ' . $e->getMessage()]);
        }
        break;
        
    case 'GET':
        // Get recent messages
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $limit = min(max($limit, 1), 100); // Limit between 1 and 100
        $since = isset($_GET['since']) ? (int)$_GET['since'] : 0;
        
        try {
            // Ensure table exists and has correct structure before querying
            try {
                $result = $pdo->query("DESCRIBE chat_messages");
                $columns = $result->fetchAll(PDO::FETCH_COLUMN);
                
                // Check if username column exists
                if (!in_array('username', $columns)) {
                    $pdo->exec("ALTER TABLE chat_messages ADD COLUMN username VARCHAR(50) NOT NULL DEFAULT 'Anonymous' AFTER user_id");
                }
                
                // Check if user_role column exists
                if (!in_array('user_role', $columns)) {
                    $pdo->exec("ALTER TABLE chat_messages ADD COLUMN user_role ENUM('user', 'squad_leader', 'moderator', 'admin', 'super_admin') NOT NULL DEFAULT 'user' AFTER username");
                }
            } catch (PDOException $e) {
                // Table doesn't exist, create it
                $pdo->exec("
                    CREATE TABLE chat_messages (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        username VARCHAR(50) NOT NULL,
                        user_role ENUM('user', 'squad_leader', 'moderator', 'admin', 'super_admin') NOT NULL DEFAULT 'user',
                        message TEXT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ");
            }
            
            if ($since > 0) {
                // Get new messages since last ID
                $stmt = $pdo->prepare("
                    SELECT id, username, user_role, message, created_at
                    FROM chat_messages 
                    WHERE id > ?
                    ORDER BY created_at ASC 
                    LIMIT ?
                ");
                $stmt->execute([$since, $limit]);
            } else {
                // Get recent messages
                $stmt = $pdo->prepare("
                    SELECT id, username, user_role, message, created_at
                    FROM chat_messages 
                    ORDER BY created_at DESC 
                    LIMIT ?
                ");
                $stmt->execute([$limit]);
            }
            
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Reverse to show oldest first only for initial load
            if ($since == 0) {
                $messages = array_reverse($messages);
            }
            
            echo json_encode([
                'success' => true,
                'messages' => $messages
            ]);
        } catch (PDOException $e) {
            error_log('Error fetching messages: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch messages']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
?>
