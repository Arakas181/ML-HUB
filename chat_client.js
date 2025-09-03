class ChatClient {
    constructor(serverUrl, roomId, userId, username, userRole) {
        this.serverUrl = serverUrl;
        this.roomId = roomId;
        this.userId = userId;
        this.username = username;
        this.userRole = userRole;
        this.socket = null;
        this.connected = false;
        this.messageQueue = [];
        this.callbacks = {};
        
        this.init();
    }

    init() {
        this.connect();
        this.setupEventHandlers();
    }

    connect() {
        try {
            this.socket = new WebSocket(this.serverUrl);
            
            this.socket.onopen = (event) => {
                console.log('Connected to chat server');
                this.connected = true;
                this.joinRoom();
                this.processMessageQueue();
                this.trigger('connected');
            };

            this.socket.onmessage = (event) => {
                const data = JSON.parse(event.data);
                this.handleMessage(data);
            };

            this.socket.onclose = (event) => {
                console.log('Disconnected from chat server');
                this.connected = false;
                this.trigger('disconnected');
                
                // Attempt to reconnect after 3 seconds
                setTimeout(() => {
                    if (!this.connected) {
                        this.connect();
                    }
                }, 3000);
            };

            this.socket.onerror = (error) => {
                console.error('WebSocket error:', error);
                this.trigger('error', error);
            };

        } catch (error) {
            console.error('Failed to connect to chat server:', error);
            this.trigger('error', error);
        }
    }

    joinRoom() {
        this.send({
            type: 'join_room',
            room_id: this.roomId,
            user_id: this.userId,
            username: this.username,
            user_role: this.userRole
        });
    }

    sendMessage(message) {
        if (!message.trim()) return;

        this.send({
            type: 'chat_message',
            room_id: this.roomId,
            user_id: this.userId,
            username: this.username,
            user_role: this.userRole,
            message: message.trim()
        });
    }

    votePoll(pollId, optionIndex) {
        this.send({
            type: 'poll_vote',
            poll_id: pollId,
            option_index: optionIndex,
            user_id: this.userId
        });
    }

    submitQuestion(sessionId, question) {
        this.send({
            type: 'qa_question',
            session_id: sessionId,
            question: question,
            user_id: this.userId
        });
    }

    moderateMessage(action, messageId, targetUserId = null, duration = null) {
        if (!this.isModerator()) return;

        this.send({
            type: 'moderate_message',
            action: action,
            message_id: messageId,
            target_user_id: targetUserId,
            room_id: this.roomId,
            moderator_id: this.userId,
            duration: duration
        });
    }

    send(data) {
        if (this.connected && this.socket.readyState === WebSocket.OPEN) {
            this.socket.send(JSON.stringify(data));
        } else {
            this.messageQueue.push(data);
        }
    }

    processMessageQueue() {
        while (this.messageQueue.length > 0) {
            const message = this.messageQueue.shift();
            this.send(message);
        }
    }

    handleMessage(data) {
        switch (data.type) {
            case 'room_joined':
                this.trigger('room_joined', data);
                break;
            case 'chat_message':
                this.trigger('message', data);
                break;
            case 'user_joined':
                this.trigger('user_joined', data);
                break;
            case 'poll_update':
                this.trigger('poll_update', data);
                break;
            case 'new_question':
                this.trigger('new_question', data);
                break;
            case 'message_blocked':
                this.trigger('message_blocked', data);
                break;
            case 'slow_mode':
                this.trigger('slow_mode', data);
                break;
            case 'error':
                this.trigger('error', data);
                break;
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

    isModerator() {
        return ['admin', 'super_admin', 'moderator'].includes(this.userRole);
    }

    disconnect() {
        if (this.socket) {
            this.socket.close();
        }
    }

    setupEventHandlers() {
        // Handle page unload
        window.addEventListener('beforeunload', () => {
            this.disconnect();
        });
    }
}

// Chat UI Manager
class ChatUI {
    constructor(chatClient, containerId) {
        this.chatClient = chatClient;
        this.container = document.getElementById(containerId);
        this.messagesContainer = null;
        this.inputField = null;
        this.sendButton = null;
        this.userCount = 0;
        
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
                    <div class="chat-stats">
                        <span class="user-count">0 users</span>
                        <div class="chat-controls">
                            ${this.chatClient.isModerator() ? '<button class="btn btn-sm btn-outline-light" id="moderationBtn"><i class="fas fa-shield-alt"></i></button>' : ''}
                            <button class="btn btn-sm btn-outline-light" id="settingsBtn"><i class="fas fa-cog"></i></button>
                        </div>
                    </div>
                </div>
                
                <div class="chat-messages" id="chatMessages"></div>
                
                <div class="chat-input-container">
                    <input type="text" class="chat-input" id="messageInput" placeholder="Type your message..." maxlength="500">
                    <button class="chat-send-btn" id="sendBtn">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
                
                <div class="chat-status" id="chatStatus" style="display: none;"></div>
            </div>
        `;

        this.messagesContainer = document.getElementById('chatMessages');
        this.inputField = document.getElementById('messageInput');
        this.sendButton = document.getElementById('sendBtn');
    }

    bindEvents() {
        // Send message on button click
        this.sendButton.addEventListener('click', () => {
            this.sendMessage();
        });

        // Send message on Enter key
        this.inputField.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Moderation button
        const moderationBtn = document.getElementById('moderationBtn');
        if (moderationBtn) {
            moderationBtn.addEventListener('click', () => {
                this.openModerationPanel();
            });
        }

        // Settings button
        document.getElementById('settingsBtn').addEventListener('click', () => {
            this.openSettings();
        });
    }

    setupChatClientEvents() {
        this.chatClient.on('connected', () => {
            this.showStatus('Connected to chat', 'success');
        });

        this.chatClient.on('disconnected', () => {
            this.showStatus('Disconnected from chat. Reconnecting...', 'warning');
        });

        this.chatClient.on('room_joined', (data) => {
            this.updateUserCount(data.user_count);
            this.showStatus('Joined chat room', 'success');
        });

        this.chatClient.on('message', (data) => {
            this.addMessage(data);
        });

        this.chatClient.on('user_joined', (data) => {
            this.updateUserCount(data.user_count);
            this.addSystemMessage(`${data.username} joined the chat`);
        });

        this.chatClient.on('message_blocked', (data) => {
            this.showStatus(data.reason, 'error');
        });

        this.chatClient.on('slow_mode', (data) => {
            this.showStatus(data.message, 'warning');
        });

        this.chatClient.on('error', (data) => {
            this.showStatus('Chat error occurred', 'error');
        });
    }

    sendMessage() {
        const message = this.inputField.value.trim();
        if (!message) return;

        this.chatClient.sendMessage(message);
        this.inputField.value = '';
    }

    addMessage(data) {
        const messageElement = document.createElement('div');
        messageElement.className = `chat-message ${data.user_role}`;
        messageElement.dataset.messageId = data.id;
        messageElement.dataset.userId = data.user_id;

        const timestamp = new Date(data.timestamp * 1000).toLocaleTimeString();
        
        messageElement.innerHTML = `
            <div class="message-header">
                <span class="username ${data.user_role}">
                    ${this.getRoleIcon(data.user_role)}${data.username}
                </span>
                <span class="timestamp">${timestamp}</span>
                ${this.chatClient.isModerator() ? `
                    <div class="message-actions">
                        <button class="btn btn-sm btn-outline-danger" onclick="chatUI.deleteMessage(${data.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                ` : ''}
            </div>
            <div class="message-content">${this.formatMessage(data.message)}</div>
        `;

        this.messagesContainer.appendChild(messageElement);
        this.scrollToBottom();
    }

    addSystemMessage(message) {
        const messageElement = document.createElement('div');
        messageElement.className = 'chat-message system';
        messageElement.innerHTML = `
            <div class="message-content">
                <i class="fas fa-info-circle me-1"></i>${message}
            </div>
        `;
        this.messagesContainer.appendChild(messageElement);
        this.scrollToBottom();
    }

    formatMessage(message) {
        // Basic message formatting (links, emotes, etc.)
        return message
            .replace(/https?:\/\/[^\s]+/g, '<a href="$&" target="_blank" rel="noopener">$&</a>')
            .replace(/:\w+:/g, '<span class="emote">$&</span>');
    }

    getRoleIcon(role) {
        const icons = {
            'admin': '<i class="fas fa-crown text-warning me-1"></i>',
            'super_admin': '<i class="fas fa-star text-danger me-1"></i>',
            'moderator': '<i class="fas fa-shield-alt text-info me-1"></i>',
            'squad_leader': '<i class="fas fa-users text-success me-1"></i>',
            'user': ''
        };
        return icons[role] || '';
    }

    updateUserCount(count) {
        this.userCount = count;
        document.querySelector('.user-count').textContent = `${count} user${count !== 1 ? 's' : ''}`;
    }

    showStatus(message, type = 'info') {
        const statusElement = document.getElementById('chatStatus');
        statusElement.className = `chat-status alert alert-${type === 'error' ? 'danger' : type === 'warning' ? 'warning' : type === 'success' ? 'success' : 'info'}`;
        statusElement.textContent = message;
        statusElement.style.display = 'block';

        setTimeout(() => {
            statusElement.style.display = 'none';
        }, 3000);
    }

    scrollToBottom() {
        this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
    }

    deleteMessage(messageId) {
        if (confirm('Delete this message?')) {
            this.chatClient.moderateMessage('delete_message', messageId);
            
            // Remove from UI immediately
            const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
            if (messageElement) {
                messageElement.style.opacity = '0.5';
                messageElement.querySelector('.message-content').innerHTML = '<em>Message deleted</em>';
            }
        }
    }

    openModerationPanel() {
        // Create moderation modal/panel
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content bg-dark text-white">
                    <div class="modal-header">
                        <h5 class="modal-title">Chat Moderation</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Timeout User</label>
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Username" id="timeoutUsername">
                                <select class="form-select" id="timeoutDuration">
                                    <option value="60">1 minute</option>
                                    <option value="300">5 minutes</option>
                                    <option value="600">10 minutes</option>
                                    <option value="3600">1 hour</option>
                                </select>
                                <button class="btn btn-warning" onclick="chatUI.timeoutUser()">Timeout</button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Ban User</label>
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Username" id="banUsername">
                                <button class="btn btn-danger" onclick="chatUI.banUser()">Ban</button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Clear Chat</label>
                            <button class="btn btn-outline-danger w-100" onclick="chatUI.clearChat()">Clear All Messages</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        const bootstrapModal = new bootstrap.Modal(modal);
        bootstrapModal.show();
        
        modal.addEventListener('hidden.bs.modal', () => {
            document.body.removeChild(modal);
        });
    }

    timeoutUser() {
        const username = document.getElementById('timeoutUsername').value;
        const duration = document.getElementById('timeoutDuration').value;
        
        if (username) {
            // Find user ID by username (simplified)
            this.chatClient.moderateMessage('timeout_user', null, username, duration);
            this.showStatus(`${username} has been timed out for ${duration} seconds`, 'success');
        }
    }

    banUser() {
        const username = document.getElementById('banUsername').value;
        
        if (username && confirm(`Ban ${username} from this chat?`)) {
            this.chatClient.moderateMessage('ban_user', null, username);
            this.showStatus(`${username} has been banned`, 'success');
        }
    }

    clearChat() {
        if (confirm('Clear all chat messages? This cannot be undone.')) {
            this.messagesContainer.innerHTML = '';
            this.addSystemMessage('Chat has been cleared by a moderator');
        }
    }

    openSettings() {
        // Chat settings modal
        console.log('Opening chat settings...');
    }
}

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ChatClient, ChatUI };
}
