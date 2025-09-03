/**
 * Polling-based Chat Client for ML HUB Esports
 * Works without WebSocket - uses HTTP polling instead
 */

class PollingChatClient {
    constructor(apiUrl, roomId, userId, username, userRole) {
        this.apiUrl = apiUrl || 'chat_api.php';
        this.roomId = roomId;
        this.userId = userId;
        this.username = username;
        this.userRole = userRole;
        this.connected = false;
        this.callbacks = {};
        this.pollInterval = null;
        this.lastMessageId = 0;
        this.pollFrequency = 2000; // Poll every 2 seconds
        
        this.init();
    }

    init() {
        this.connect();
        this.setupEventHandlers();
    }

    connect() {
        this.connected = true;
        this.startPolling();
        this.trigger('connected');
        console.log('Connected to polling chat system');
    }

    startPolling() {
        this.loadMessages();
        
        this.pollInterval = setInterval(() => {
            if (this.connected) {
                this.loadNewMessages();
            }
        }, this.pollFrequency);
    }

    async loadMessages() {
        try {
            const response = await fetch(`${this.apiUrl}?limit=50`);
            const data = await response.json();
            
            if (data.success) {
                data.messages.forEach(message => {
                    this.trigger('message', {
                        id: message.id,
                        user_id: message.user_id || 0,
                        username: message.username,
                        user_role: message.user_role,
                        message: message.message,
                        timestamp: new Date(message.created_at).getTime() / 1000
                    });
                    
                    if (message.id > this.lastMessageId) {
                        this.lastMessageId = message.id;
                    }
                });
            }
        } catch (error) {
            console.error('Error loading messages:', error);
            this.trigger('error', error);
        }
    }

    async loadNewMessages() {
        try {
            const response = await fetch(`${this.apiUrl}?since=${this.lastMessageId}&limit=10`);
            const data = await response.json();
            
            if (data.success && data.messages) {
                data.messages.forEach(message => {
                    if (message.id > this.lastMessageId) {
                        this.trigger('message', {
                            id: message.id,
                            user_id: message.user_id || 0,
                            username: message.username,
                            user_role: message.user_role,
                            message: message.message,
                            timestamp: new Date(message.created_at).getTime() / 1000
                        });
                        
                        this.lastMessageId = message.id;
                    }
                });
            }
        } catch (error) {
            console.error('Error loading new messages:', error);
        }
    }

    async sendMessage(message) {
        if (!message.trim()) return;

        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message: message.trim(),
                    room_id: this.roomId
                })
            });

            const data = await response.json();
            
            if (data.success) {
                // Message will be picked up by polling
                return true;
            } else {
                this.trigger('error', data.error || 'Failed to send message');
                return false;
            }
        } catch (error) {
            console.error('Error sending message:', error);
            this.trigger('error', 'Failed to send message');
            return false;
        }
    }

    on(event, callback) {
        if (!this.callbacks[event]) {
            this.callbacks[event] = [];
        }
        this.callbacks[event].push(callback);
    }

    trigger(event, data = null) {
        if (this.callbacks[event]) {
            this.callbacks[event].forEach(callback => callback(data));
        }
    }

    disconnect() {
        this.connected = false;
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
        this.trigger('disconnected');
        console.log('Disconnected from polling chat system');
    }

    // Compatibility methods for WebSocket ChatClient
    send(data) {
        if (data.type === 'chat_message') {
            return this.sendMessage(data.message);
        }
    }

    isModerator() {
        return ['admin', 'super_admin', 'moderator'].includes(this.userRole);
    }

    setupEventHandlers() {
        window.addEventListener('beforeunload', () => {
            this.disconnect();
        });

        // Handle visibility change to reduce polling when tab is hidden
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pollFrequency = 10000; // Poll every 10 seconds when hidden
            } else {
                this.pollFrequency = 2000; // Poll every 2 seconds when visible
                this.loadNewMessages(); // Immediate check when tab becomes visible
            }
            
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.startPolling();
            }
        });
    }
}

// Simple Chat UI that works with polling client
class SimpleChatUI {
    constructor(chatClient, containerId) {
        this.chatClient = chatClient;
        this.container = document.getElementById(containerId);
        this.messagesContainer = null;
        this.inputField = null;
        this.sendButton = null;
        
        this.init();
    }

    init() {
        this.createUI();
        this.bindEvents();
        this.setupChatClientEvents();
    }

    createUI() {
        this.container.innerHTML = `
            <div class="chat-container">
                <div class="chat-header">
                    <div class="chat-title">
                        <i class="fas fa-comments me-2"></i>Live Chat
                    </div>
                    <div class="chat-status">
                        <span class="status-indicator connected"></span>
                        <span>Connected</span>
                    </div>
                </div>
                
                <div class="chat-messages" id="chatMessages"></div>
                
                <div class="chat-input-container">
                    <input type="text" class="chat-input" id="messageInput" 
                           placeholder="Type your message..." maxlength="500">
                    <button class="chat-send-btn" id="sendBtn">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        `;

        this.messagesContainer = document.getElementById('chatMessages');
        this.inputField = document.getElementById('messageInput');
        this.sendButton = document.getElementById('sendBtn');
    }

    bindEvents() {
        this.sendButton.addEventListener('click', () => {
            this.sendMessage();
        });

        this.inputField.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
    }

    setupChatClientEvents() {
        this.chatClient.on('connected', () => {
            this.updateStatus('Connected', true);
        });

        this.chatClient.on('disconnected', () => {
            this.updateStatus('Disconnected', false);
        });

        this.chatClient.on('message', (data) => {
            this.addMessage(data);
        });

        this.chatClient.on('error', (error) => {
            this.showError(error);
        });
    }

    async sendMessage() {
        const message = this.inputField.value.trim();
        if (!message) return;

        const success = await this.chatClient.sendMessage(message);
        if (success) {
            this.inputField.value = '';
        }
    }

    addMessage(data) {
        const messageElement = document.createElement('div');
        messageElement.className = `chat-message ${data.user_role}`;
        
        const timestamp = new Date(data.timestamp * 1000).toLocaleTimeString();
        
        messageElement.innerHTML = `
            <div class="message-header">
                <span class="username ${data.user_role}">
                    ${this.getRoleIcon(data.user_role)}${this.escapeHtml(data.username)}
                </span>
                <span class="timestamp">${timestamp}</span>
            </div>
            <div class="message-content">${this.escapeHtml(data.message)}</div>
        `;

        this.messagesContainer.appendChild(messageElement);
        this.scrollToBottom();
    }

    getRoleIcon(role) {
        const icons = {
            'admin': '<i class="fas fa-crown text-warning me-1"></i>',
            'super_admin': '<i class="fas fa-star text-danger me-1"></i>',
            'moderator': '<i class="fas fa-shield-alt text-info me-1"></i>',
            'user': ''
        };
        return icons[role] || '';
    }

    updateStatus(text, connected) {
        const statusElement = this.container.querySelector('.chat-status');
        if (statusElement) {
            const indicator = statusElement.querySelector('.status-indicator');
            const textElement = statusElement.querySelector('span:last-child');
            
            if (connected) {
                indicator.classList.add('connected');
                indicator.classList.remove('disconnected');
            } else {
                indicator.classList.add('disconnected');
                indicator.classList.remove('connected');
            }
            
            textElement.textContent = text;
        }
    }

    showError(message) {
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger alert-dismissible fade show position-fixed';
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

    scrollToBottom() {
        this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Export for compatibility
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { PollingChatClient, SimpleChatUI };
}

// Global compatibility aliases
window.ChatClient = PollingChatClient;
window.ChatUI = SimpleChatUI;
