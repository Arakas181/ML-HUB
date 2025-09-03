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
    <title>Chat Integration Example - ML HUB Esports</title>
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
        
        .username.moderator {
            color: #17a2b8;
        }
        
        .timestamp {
            font-size: 0.8rem;
            opacity: 0.7;
        }
        
        .connection-status {
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
        
        .polls-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            height: 400px;
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
                <h2><i class="fas fa-comments me-2"></i>Live Chat Integration Test</h2>
                
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle me-2"></i>Setup Instructions</h5>
                    <ol>
                        <li><strong>Start Chat Server:</strong> Double-click <code>start_chat_server.bat</code> or run <code>php simple_chat_server.php</code></li>
                        <li><strong>Check Connection:</strong> The status indicator below will turn green when connected</li>
                        <li><strong>Test Chat:</strong> Type messages in the chat box below</li>
                    </ol>
                </div>
                
                <div id="chatContainer" class="chat-container">
                    <!-- Chat UI will be inserted here -->
                </div>
            </div>
            
            <div class="col-md-4">
                <h4><i class="fas fa-poll me-2"></i>Live Polls</h4>
                <div id="pollsContainer" class="polls-container">
                    <!-- Polls widget will be inserted here -->
                </div>
                
                <div class="mt-4">
                    <h5><i class="fas fa-cogs me-2"></i>Server Status</h5>
                    <div class="card bg-dark">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Chat Server:</span>
                                <div class="connection-status">
                                    <div class="status-indicator" id="chatStatus"></div>
                                    <span id="chatStatusText">Disconnected</span>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Database:</span>
                                <div class="connection-status">
                                    <div class="status-indicator connected"></div>
                                    <span>Connected</span>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span>WebSocket Port:</span>
                                <span>8080</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <h5><i class="fas fa-terminal me-2"></i>Quick Actions</h5>
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-light" onclick="testConnection()">
                            <i class="fas fa-plug me-2"></i>Test Connection
                        </button>
                        <button class="btn btn-outline-light" onclick="clearChat()">
                            <i class="fas fa-broom me-2"></i>Clear Chat
                        </button>
                        <a href="live_polls_manager.php" class="btn btn-outline-primary">
                            <i class="fas fa-poll me-2"></i>Manage Polls
                        </a>
                        <a href="qa_session_manager.php" class="btn btn-outline-success">
                            <i class="fas fa-question-circle me-2"></i>Manage Q&A
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include chat client and polls widget -->
    <script src="chat_client.js"></script>
    <script src="polls_widget.js"></script>
    
    <script>
        // Initialize chat system
        let chatClient = null;
        let chatUI = null;
        let pollsWidget = null;
        
        // User configuration
        const currentUserId = <?= $user_id ?>;
        const currentUsername = '<?= htmlspecialchars($username) ?>';
        const currentUserRole = '<?= htmlspecialchars($user_role) ?>';
        const roomId = 1; // General chat room
        
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeChat();
            initializePolls();
        });
        
        function initializeChat() {
            try {
                // Create chat client
                chatClient = new ChatClient(
                    'ws://localhost:8080',
                    roomId,
                    currentUserId,
                    currentUsername,
                    currentUserRole
                );
                
                // Create chat UI
                chatUI = new ChatUI(chatClient, 'chatContainer');
                
                // Handle connection events
                chatClient.on('connected', function() {
                    updateConnectionStatus(true);
                });
                
                chatClient.on('disconnected', function() {
                    updateConnectionStatus(false);
                });
                
                chatClient.on('error', function(error) {
                    console.error('Chat error:', error);
                    updateConnectionStatus(false);
                });
                
            } catch (error) {
                console.error('Failed to initialize chat:', error);
                showError('Failed to initialize chat system. Make sure the chat server is running.');
            }
        }
        
        function initializePolls() {
            try {
                pollsWidget = initializePollsWidget('pollsContainer', {
                    websocketUrl: 'ws://localhost:8080',
                    apiUrl: 'live_polls.php',
                    roomId: roomId,
                    userId: currentUserId,
                    username: currentUsername,
                    userRole: currentUserRole,
                    autoRefresh: true
                });
            } catch (error) {
                console.error('Failed to initialize polls:', error);
            }
        }
        
        function updateConnectionStatus(connected) {
            const statusIndicator = document.getElementById('chatStatus');
            const statusText = document.getElementById('chatStatusText');
            
            if (connected) {
                statusIndicator.classList.add('connected');
                statusText.textContent = 'Connected';
            } else {
                statusIndicator.classList.remove('connected');
                statusText.textContent = 'Disconnected';
            }
        }
        
        function testConnection() {
            if (chatClient) {
                chatClient.send({
                    type: 'ping',
                    timestamp: Date.now()
                });
                showMessage('Connection test sent', 'info');
            } else {
                showMessage('Chat client not initialized', 'warning');
            }
        }
        
        function clearChat() {
            const messagesContainer = document.getElementById('chatMessages');
            if (messagesContainer) {
                messagesContainer.innerHTML = '';
                showMessage('Chat cleared', 'success');
            }
        }
        
        function showMessage(message, type = 'info') {
            const alertClass = type === 'error' ? 'danger' : type;
            const alert = document.createElement('div');
            alert.className = `alert alert-${alertClass} alert-dismissible fade show position-fixed`;
            alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 300px;';
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alert);
            
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 5000);
        }
        
        function showError(message) {
            showMessage(message, 'error');
        }
        
        // Handle page unload
        window.addEventListener('beforeunload', function() {
            if (chatClient) {
                chatClient.disconnect();
            }
            if (pollsWidget) {
                pollsWidget.destroy();
            }
        });
    </script>
</body>
</html>
