<?php
require_once 'config.php';
session_start();

// Sample user data for testing
$user_id = $_SESSION['user_id'] ?? 1;
$username = $_SESSION['username'] ?? 'TestUser';
$user_role = $_SESSION['role'] ?? 'user';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Working Chat Example - ML HUB Esports</title>
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
        
        .chat-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            height: 500px;
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            background: rgba(233, 69, 96, 0.2);
            padding: 1rem;
            border-radius: 15px 15px 0 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            background: rgba(0, 0, 0, 0.2);
        }
        
        .chat-input-container {
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            gap: 0.5rem;
        }
        
        .chat-input {
            flex: 1;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--text-light);
            border-radius: 25px;
            padding: 0.5rem 1rem;
        }
        
        .chat-input:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: var(--highlight-color);
            color: var(--text-light);
            box-shadow: 0 0 0 0.2rem rgba(233, 69, 96, 0.25);
        }
        
        .chat-send-btn {
            background: var(--highlight-color);
            border: none;
            color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .chat-message {
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            border-left: 3px solid var(--highlight-color);
        }
        
        .chat-message.admin {
            border-left-color: #ffc107;
        }
        
        .chat-message.super_admin {
            border-left-color: #dc3545;
        }
        
        .chat-message.moderator {
            border-left-color: #17a2b8;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .username {
            font-weight: bold;
        }
        
        .username.admin {
            color: #ffc107;
        }
        
        .username.super_admin {
            color: #dc3545;
        }
        
        .username.moderator {
            color: #17a2b8;
        }
        
        .timestamp {
            font-size: 0.8rem;
            opacity: 0.7;
        }
        
        .chat-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #dc3545;
        }
        
        .status-indicator.connected {
            background: #28a745;
        }
        
        .status-indicator.disconnected {
            background: #dc3545;
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
                <span class="navbar-text">
                    Welcome, <?= htmlspecialchars($username) ?>!
                </span>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <h2><i class="fas fa-comments me-2"></i>Working Chat System (No WebSocket Required)</h2>
                
                <div class="alert alert-success">
                    <h5><i class="fas fa-check-circle me-2"></i>Ready to Use!</h5>
                    <p class="mb-0">This chat system works immediately without requiring WebSocket server setup. It uses HTTP polling to check for new messages every 2 seconds.</p>
                </div>
                
                <div id="chatContainer" class="chat-container">
                    <!-- Chat UI will be inserted here -->
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card bg-dark">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle me-2"></i>System Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Chat System:</span>
                            <div class="chat-status">
                                <div class="status-indicator connected"></div>
                                <span>HTTP Polling</span>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Database:</span>
                            <div class="chat-status">
                                <div class="status-indicator <?= $pdo ? 'connected' : 'disconnected' ?>"></div>
                                <span><?= $pdo ? 'Connected' : 'Disconnected' ?></span>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Poll Frequency:</span>
                            <span>2 seconds</span>
                        </div>
                    </div>
                </div>
                
                <div class="card bg-dark mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-cogs me-2"></i>Features</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li><i class="fas fa-check text-success me-2"></i>Real-time messaging</li>
                            <li><i class="fas fa-check text-success me-2"></i>Role-based styling</li>
                            <li><i class="fas fa-check text-success me-2"></i>Auto-scroll to new messages</li>
                            <li><i class="fas fa-check text-success me-2"></i>Responsive design</li>
                            <li><i class="fas fa-check text-success me-2"></i>No server setup required</li>
                        </ul>
                    </div>
                </div>
                
                <div class="card bg-dark mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-code me-2"></i>Integration Code</h5>
                    </div>
                    <div class="card-body">
                        <p class="small">Add to any page:</p>
                        <pre class="bg-secondary p-2 rounded small"><code>&lt;script src="polling_chat_client.js"&gt;&lt;/script&gt;
&lt;script&gt;
const chatClient = new ChatClient(
  'chat_api.php', 1, 
  <?= $user_id ?>, '<?= $username ?>', '<?= $user_role ?>'
);
const chatUI = new ChatUI(chatClient, 'chatContainer');
&lt;/script&gt;</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include the polling-based chat client -->
    <script src="polling_chat_client.js"></script>
    
    <script>
        // Initialize chat system
        let chatClient = null;
        let chatUI = null;
        
        // User configuration
        const currentUserId = <?= $user_id ?>;
        const currentUsername = '<?= htmlspecialchars($username) ?>';
        const currentUserRole = '<?= htmlspecialchars($user_role) ?>';
        const roomId = 1; // General chat room
        
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeChat();
        });
        
        function initializeChat() {
            try {
                // Create polling-based chat client
                chatClient = new PollingChatClient(
                    'chat_api.php',
                    roomId,
                    currentUserId,
                    currentUsername,
                    currentUserRole
                );
                
                // Create chat UI
                chatUI = new SimpleChatUI(chatClient, 'chatContainer');
                
                console.log('Chat system initialized successfully');
                
            } catch (error) {
                console.error('Failed to initialize chat:', error);
                showError('Failed to initialize chat system: ' + error.message);
            }
        }
        
        function showError(message) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger alert-dismissible fade show position-fixed';
            alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
            alert.innerHTML = `
                <strong>Error:</strong> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alert);
            
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 10000);
        }
        
        // Handle page unload
        window.addEventListener('beforeunload', function() {
            if (chatClient) {
                chatClient.disconnect();
            }
        });
    </script>
</body>
</html>
