class EnhancedWebSocketClient {
    constructor(serverUrl, roomId, userId, username, userRole) {
        this.serverUrl = serverUrl;
        this.roomId = roomId;
        this.userId = userId;
        this.username = username;
        this.userRole = userRole;
        this.socket = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 1000;
        this.isConnected = false;
        this.messageQueue = [];
        this.typingTimeout = null;
        this.lastActivity = Date.now();
        
        this.connect();
        this.setupHeartbeat();
    }
    
    connect() {
        try {
            this.socket = new WebSocket(this.serverUrl);
            this.bindEvents();
        } catch (error) {
            console.error('WebSocket connection failed:', error);
            this.handleReconnect();
        }
    }
    
    bindEvents() {
        this.socket.onopen = (event) => {
            console.log('Connected to enhanced chat server');
            this.isConnected = true;
            this.reconnectAttempts = 0;
            
            // Join room
            this.send({
                type: 'join',
                room_id: this.roomId,
                user_id: this.userId,
                username: this.username,
                user_role: this.userRole
            });
            
            // Process queued messages
            this.processMessageQueue();
            
            this.onConnectionStatusChange(true);
        };
        
        this.socket.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                this.handleMessage(data);
            } catch (error) {
                console.error('Failed to parse message:', error);
            }
        };
        
        this.socket.onclose = (event) => {
            console.log('Disconnected from chat server');
            this.isConnected = false;
            this.onConnectionStatusChange(false);
            
            if (!event.wasClean) {
                this.handleReconnect();
            }
        };
        
        this.socket.onerror = (error) => {
            console.error('WebSocket error:', error);
            this.onConnectionStatusChange(false);
        };
    }
    
    handleMessage(data) {
        switch (data.type) {
            case 'joined':
                this.onJoined(data);
                break;
            case 'message':
                this.onMessage(data);
                break;
            case 'user_joined':
                this.onUserJoined(data);
                break;
            case 'user_left':
                this.onUserLeft(data);
                break;
            case 'typing':
                this.onTyping(data);
                break;
            case 'user_timeout':
                this.onUserTimeout(data);
                break;
            case 'user_banned':
                this.onUserBanned(data);
                break;
            case 'message_deleted':
                this.onMessageDeleted(data);
                break;
            case 'poll_update':
                this.onPollUpdate(data);
                break;
            case 'error':
                this.onError(data);
                break;
        }
    }
    
    send(data) {
        if (this.isConnected && this.socket.readyState === WebSocket.OPEN) {
            this.socket.send(JSON.stringify(data));
            this.lastActivity = Date.now();
        } else {
            // Queue message for when connection is restored
            this.messageQueue.push(data);
        }
    }
    
    sendMessage(message) {
        if (!message.trim()) return;
        
        this.send({
            type: 'message',
            room_id: this.roomId,
            user_id: this.userId,
            message: message
        });
    }
    
    sendTyping(isTyping) {
        this.send({
            type: 'typing',
            room_id: this.roomId,
            is_typing: isTyping
        });
    }
    
    moderateUser(action, targetUser, options = {}) {
        if (!this.canModerate()) {
            console.warn('Insufficient permissions for moderation');
            return;
        }
        
        this.send({
            type: 'moderation',
            room_id: this.roomId,
            action: action,
            target_user: targetUser,
            ...options
        });
    }
    
    votePoll(pollId, option) {
        this.send({
            type: 'poll_vote',
            poll_id: pollId,
            option: option,
            user_id: this.userId
        });
    }
    
    handleReconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            console.error('Max reconnection attempts reached');
            this.onMaxReconnectAttemptsReached();
            return;
        }
        
        this.reconnectAttempts++;
        const delay = this.reconnectDelay * Math.pow(2, this.reconnectAttempts - 1);
        
        console.log(`Attempting to reconnect in ${delay}ms (attempt ${this.reconnectAttempts})`);
        
        setTimeout(() => {
            this.connect();
        }, delay);
    }
    
    processMessageQueue() {
        while (this.messageQueue.length > 0) {
            const message = this.messageQueue.shift();
            this.send(message);
        }
    }
    
    setupHeartbeat() {
        setInterval(() => {
            if (this.isConnected) {
                // Send ping to keep connection alive
                this.send({ type: 'ping' });
            }
            
            // Check for inactivity
            if (Date.now() - this.lastActivity > 300000) { // 5 minutes
                this.onInactivityDetected();
            }
        }, 30000); // Every 30 seconds
    }
    
    canModerate() {
        return ['moderator', 'admin', 'super_admin'].includes(this.userRole);
    }
    
    disconnect() {
        if (this.socket) {
            this.socket.close(1000, 'Client disconnect');
        }
    }
    
    // Event handlers (to be overridden)
    onConnectionStatusChange(isConnected) {
        const statusElement = document.getElementById('connectionStatus');
        if (statusElement) {
            statusElement.className = isConnected ? 'connected' : 'disconnected';
            statusElement.textContent = isConnected ? 'Connected' : 'Disconnected';
        }
    }
    
    onJoined(data) {
        console.log('Successfully joined room:', data.room_id);
        const userCountElement = document.getElementById('userCount');
        if (userCountElement) {
            userCountElement.textContent = data.user_count;
        }
    }
    
    onMessage(data) {
        this.displayMessage({
            id: data.id,
            username: data.username,
            userRole: data.user_role,
            message: data.message,
            timestamp: data.timestamp
        });
    }
    
    onUserJoined(data) {
        this.displaySystemMessage(`${data.username} joined the chat`);
        const userCountElement = document.getElementById('userCount');
        if (userCountElement) {
            userCountElement.textContent = data.user_count;
        }
    }
    
    onUserLeft(data) {
        this.displaySystemMessage(`${data.user} left the chat`);
    }
    
    onTyping(data) {
        this.displayTypingIndicator(data.username, data.is_typing);
    }
    
    onUserTimeout(data) {
        this.displaySystemMessage(`${data.username} was timed out for ${data.duration} seconds`, 'warning');
    }
    
    onUserBanned(data) {
        this.displaySystemMessage(`${data.username} was banned`, 'danger');
    }
    
    onMessageDeleted(data) {
        const messageElement = document.querySelector(`[data-message-id="${data.message_id}"]`);
        if (messageElement) {
            messageElement.classList.add('deleted');
            messageElement.innerHTML = '<em class="text-muted">Message deleted by moderator</em>';
        }
    }
    
    onPollUpdate(data) {
        this.updatePollResults(data.poll_id, data.results);
    }
    
    onError(data) {
        console.error('Chat error:', data.error);
        this.displaySystemMessage(`Error: ${data.error}`, 'danger');
    }
    
    onMaxReconnectAttemptsReached() {
        this.displaySystemMessage('Connection lost. Please refresh the page.', 'danger');
    }
    
    onInactivityDetected() {
        console.log('User inactive for 5 minutes');
        // Could implement away status or other features
    }
    
    displayMessage(message) {
        const chatContainer = document.getElementById('chatMessages');
        if (!chatContainer) return;
        
        const messageElement = document.createElement('div');
        messageElement.className = 'chat-message mb-2';
        messageElement.setAttribute('data-message-id', message.id);
        
        const roleClass = this.getRoleClass(message.userRole);
        const timestamp = new Date(message.timestamp).toLocaleTimeString();
        
        messageElement.innerHTML = `
            <div class="d-flex justify-content-between align-items-start">
                <div class="flex-grow-1">
                    <strong class="username ${roleClass}">${message.username}</strong>
                    <span class="message-text ms-2">${this.escapeHtml(message.message)}</span>
                </div>
                <small class="text-muted timestamp">${timestamp}</small>
                ${this.canModerate() ? `
                    <div class="dropdown ms-2">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark">
                            <li><a class="dropdown-item" href="#" onclick="chatClient.moderateUser('delete_message', '${message.username}', {message_id: ${message.id}})">
                                <i class="fas fa-trash me-2"></i>Delete Message
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="chatClient.moderateUser('timeout', '${message.username}', {duration: 300})">
                                <i class="fas fa-clock me-2"></i>Timeout (5min)
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="chatClient.moderateUser('ban', '${message.username}')">
                                <i class="fas fa-ban me-2"></i>Ban User
                            </a></li>
                        </ul>
                    </div>
                ` : ''}
            </div>
        `;
        
        chatContainer.appendChild(messageElement);
        chatContainer.scrollTop = chatContainer.scrollHeight;
        
        // Limit message history
        const messages = chatContainer.querySelectorAll('.chat-message');
        if (messages.length > 100) {
            messages[0].remove();
        }
    }
    
    displaySystemMessage(message, type = 'info') {
        const chatContainer = document.getElementById('chatMessages');
        if (!chatContainer) return;
        
        const messageElement = document.createElement('div');
        messageElement.className = `chat-system-message text-center py-2 text-${type}`;
        messageElement.innerHTML = `<em><i class="fas fa-info-circle me-1"></i>${message}</em>`;
        
        chatContainer.appendChild(messageElement);
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }
    
    displayTypingIndicator(username, isTyping) {
        const typingContainer = document.getElementById('typingIndicators');
        if (!typingContainer) return;
        
        const indicatorId = `typing-${username}`;
        let indicator = document.getElementById(indicatorId);
        
        if (isTyping) {
            if (!indicator) {
                indicator = document.createElement('div');
                indicator.id = indicatorId;
                indicator.className = 'typing-indicator text-muted small';
                indicator.innerHTML = `<em>${username} is typing...</em>`;
                typingContainer.appendChild(indicator);
            }
        } else {
            if (indicator) {
                indicator.remove();
            }
        }
    }
    
    updatePollResults(pollId, results) {
        const pollElement = document.querySelector(`[data-poll-id="${pollId}"]`);
        if (!pollElement) return;
        
        const total = results.reduce((sum, result) => sum + parseInt(result.votes), 0);
        
        results.forEach(result => {
            const percentage = total > 0 ? (result.votes / total * 100).toFixed(1) : 0;
            const optionElement = pollElement.querySelector(`[data-option="${result.option_selected}"]`);
            
            if (optionElement) {
                const progressBar = optionElement.querySelector('.progress-bar');
                const percentageText = optionElement.querySelector('.percentage');
                
                if (progressBar) progressBar.style.width = percentage + '%';
                if (percentageText) percentageText.textContent = percentage + '%';
            }
        });
    }
    
    getRoleClass(role) {
        const roleClasses = {
            'super_admin': 'text-danger',
            'admin': 'text-warning',
            'moderator': 'text-info',
            'squad_leader': 'text-success',
            'user': 'text-light'
        };
        return roleClasses[role] || 'text-light';
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Auto-initialize if elements exist
document.addEventListener('DOMContentLoaded', function() {
    const chatContainer = document.getElementById('chatMessages');
    if (chatContainer && typeof userId !== 'undefined') {
        window.chatClient = new EnhancedWebSocketClient(
            'ws://localhost:8080',
            1, // room ID
            userId,
            username,
            userRole
        );
        
        // Setup chat input
        const chatInput = document.getElementById('chatInput');
        const sendButton = document.getElementById('sendChat');
        
        if (chatInput && sendButton) {
            let typingTimer;
            
            chatInput.addEventListener('input', function() {
                chatClient.sendTyping(true);
                
                clearTimeout(typingTimer);
                typingTimer = setTimeout(() => {
                    chatClient.sendTyping(false);
                }, 1000);
            });
            
            chatInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });
            
            sendButton.addEventListener('click', sendMessage);
            
            function sendMessage() {
                const message = chatInput.value.trim();
                if (message) {
                    chatClient.sendMessage(message);
                    chatInput.value = '';
                    chatClient.sendTyping(false);
                }
            }
        }
    }
});
