class YouTubeChatClient {
    constructor(videoId, containerId, options = {}) {
        this.videoId = videoId;
        this.container = document.getElementById(containerId);
        this.apiUrl = options.apiUrl || 'youtube_video_chat.php';
        this.refreshInterval = options.refreshInterval || 2000;
        this.lastMessageId = 0;
        this.isActive = false;
        this.refreshTimer = null;
        this.viewerCount = 0;
        
        this.init();
    }
    
    init() {
        this.createChatInterface();
        this.joinVideo();
        this.startPolling();
        
        // Handle page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.stopPolling();
            } else {
                this.startPolling();
            }
        });
        
        // Handle page unload
        window.addEventListener('beforeunload', () => {
            this.leaveVideo();
        });
    }
    
    createChatInterface() {
        this.container.innerHTML = `
            <div class="youtube-chat-wrapper">
                <div class="chat-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="fab fa-youtube text-danger me-2"></i>
                            Video Chat
                        </h6>
                        <div class="viewer-count">
                            <i class="fas fa-eye me-1"></i>
                            <span id="viewer-count">0</span> watching
                        </div>
                    </div>
                </div>
                
                <div class="chat-messages" id="chat-messages">
                    <div class="loading-messages text-center py-3">
                        <i class="fas fa-spinner fa-spin me-2"></i>
                        Loading messages...
                    </div>
                </div>
                
                <div class="chat-input-section" style="display: block !important; visibility: visible !important;">
                    <div class="input-group mb-2">
                        <input type="text" 
                               id="message-input" 
                               class="form-control chat-input" 
                               placeholder="Comment on this video..." 
                               maxlength="500"
                               style="background: rgba(255,255,255,0.1) !important; color: white !important; border: 1px solid rgba(255,255,255,0.3) !important;">
                        <button class="btn btn-danger chat-send-btn" id="send-btn" style="background: #dc3545 !important;">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                    <div class="chat-status" id="chat-status"></div>
                </div>
            </div>
        `;
        
        this.messagesContainer = document.getElementById('chat-messages');
        this.messageInput = document.getElementById('message-input');
        this.sendButton = document.getElementById('send-btn');
        this.statusDiv = document.getElementById('chat-status');
        this.viewerCountSpan = document.getElementById('viewer-count');
        
        this.attachEventListeners();
    }
    
    attachEventListeners() {
        // Send message on button click
        this.sendButton.addEventListener('click', () => {
            this.sendMessage();
        });
        
        // Send message on Enter key
        this.messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
        
        // Auto-scroll to bottom when new messages arrive
        this.messagesContainer.addEventListener('DOMNodeInserted', () => {
            this.scrollToBottom();
        });
    }
    
    async joinVideo() {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'join_video',
                    video_id: this.videoId
                })
            });
            
            if (response.ok) {
                this.isActive = true;
                this.showStatus('Connected to video chat', 'success');
            }
        } catch (error) {
            this.showStatus('Failed to connect to chat', 'error');
        }
    }
    
    async leaveVideo() {
        if (!this.isActive) return;
        
        try {
            await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'leave_video',
                    video_id: this.videoId
                })
            });
            
            this.isActive = false;
            this.stopPolling();
        } catch (error) {
            console.error('Failed to leave video chat:', error);
        }
    }
    
    async sendMessage() {
        const message = this.messageInput.value.trim();
        if (!message) return;
        
        // Disable input while sending
        this.messageInput.disabled = true;
        this.sendButton.disabled = true;
        
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'send_message',
                    video_id: this.videoId,
                    message: message
                })
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                this.messageInput.value = '';
                this.showStatus('Message sent', 'success');
                // Immediately fetch new messages
                this.fetchMessages();
            } else {
                this.showStatus(result.error || 'Failed to send message', 'error');
            }
            
        } catch (error) {
            this.showStatus('Network error', 'error');
        } finally {
            // Re-enable input
            this.messageInput.disabled = false;
            this.sendButton.disabled = false;
            this.messageInput.focus();
        }
    }
    
    async fetchMessages() {
        if (!this.isActive) return;
        
        try {
            const response = await fetch(
                `${this.apiUrl}?video_id=${encodeURIComponent(this.videoId)}&last_id=${this.lastMessageId}`
            );
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.messages) {
                this.displayMessages(data.messages);
                this.updateViewerCount(data.viewer_count || 0);
                
                // Update last message ID
                if (data.messages.length > 0) {
                    this.lastMessageId = Math.max(...data.messages.map(m => parseInt(m.id)));
                }
            }
            
        } catch (error) {
            console.error('Failed to fetch messages:', error);
        }
    }
    
    displayMessages(messages) {
        // Remove loading indicator if present
        const loadingDiv = this.messagesContainer.querySelector('.loading-messages');
        if (loadingDiv) {
            loadingDiv.remove();
        }
        
        messages.forEach(message => {
            this.addMessageToChat(message);
        });
    }
    
    addMessageToChat(message) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `chat-message ${this.getMessageClass(message.user_role)}`;
        messageDiv.setAttribute('data-message-id', message.id);
        
        const timestamp = new Date(message.created_at).toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit'
        });
        
        const roleIcon = this.getRoleIcon(message.user_role);
        const avatarUrl = message.avatar_url || 'https://via.placeholder.com/32x32?text=' + message.username.charAt(0);
        
        messageDiv.innerHTML = `
            <div class="d-flex">
                <img src="${avatarUrl}" alt="${message.username}" class="chat-avatar me-2">
                <div class="flex-grow-1">
                    <div class="chat-header-info">
                        <span class="chat-username">
                            ${roleIcon}${this.escapeHtml(message.username)}
                        </span>
                        <span class="chat-timestamp">${timestamp}</span>
                    </div>
                    <div class="chat-text">${this.escapeHtml(message.message)}</div>
                </div>
            </div>
        `;
        
        this.messagesContainer.appendChild(messageDiv);
        this.scrollToBottom();
        
        // Limit messages to prevent memory issues
        this.limitMessages();
    }
    
    getMessageClass(userRole) {
        switch (userRole) {
            case 'admin':
            case 'super_admin':
                return 'admin';
            case 'squad_leader':
                return 'squad_leader';
            default:
                return 'user';
        }
    }
    
    getRoleIcon(userRole) {
        switch (userRole) {
            case 'admin':
            case 'super_admin':
                return '<i class="fas fa-shield-alt role-icon text-danger"></i>';
            case 'squad_leader':
                return '<i class="fas fa-star role-icon text-warning"></i>';
            default:
                return '';
        }
    }
    
    updateViewerCount(count) {
        this.viewerCount = count;
        this.viewerCountSpan.textContent = count;
    }
    
    scrollToBottom() {
        this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
    }
    
    limitMessages() {
        const messages = this.messagesContainer.querySelectorAll('.chat-message');
        if (messages.length > 100) {
            // Remove oldest messages
            for (let i = 0; i < messages.length - 100; i++) {
                messages[i].remove();
            }
        }
    }
    
    showStatus(message, type) {
        this.statusDiv.innerHTML = `
            <small class="text-${type === 'error' ? 'danger' : 'success'}">
                ${message}
            </small>
        `;
        
        // Clear status after 3 seconds
        setTimeout(() => {
            this.statusDiv.innerHTML = '';
        }, 3000);
    }
    
    startPolling() {
        if (this.refreshTimer) return;
        
        this.refreshTimer = setInterval(() => {
            this.fetchMessages();
        }, this.refreshInterval);
        
        // Initial fetch
        this.fetchMessages();
    }
    
    stopPolling() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
            this.refreshTimer = null;
        }
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    destroy() {
        this.leaveVideo();
        this.stopPolling();
        if (this.container) {
            this.container.innerHTML = '';
        }
    }
}

// CSS Styles for YouTube Chat
const youtubeChatStyles = `
<style>
.youtube-chat-wrapper {
    height: 400px;
    display: flex;
    flex-direction: column;
    background: rgba(0, 0, 0, 0.8);
    border-radius: 8px;
    overflow: hidden;
}

.chat-header {
    background: linear-gradient(135deg, rgba(255, 0, 0, 0.8) 0%, rgba(204, 0, 0, 0.8) 100%);
    padding: 12px 15px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.viewer-count {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.9);
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 10px;
    min-height: 250px;
    max-height: 250px;
}

.chat-message {
    margin-bottom: 12px;
    padding: 8px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.05);
    transition: all 0.2s ease;
}

.chat-message:hover {
    background: rgba(255, 255, 255, 0.1);
}

.chat-message.admin {
    border-left: 3px solid #dc3545;
    background: rgba(220, 53, 69, 0.1);
}

.chat-message.squad_leader {
    border-left: 3px solid #ffc107;
    background: rgba(255, 193, 7, 0.1);
}

.chat-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
}

.chat-header-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}

.chat-username {
    font-weight: 600;
    color: #fff;
    font-size: 0.9rem;
}

.chat-timestamp {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.6);
}

.chat-text {
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.9rem;
    line-height: 1.4;
    word-wrap: break-word;
}

.role-icon {
    margin-right: 4px;
    font-size: 0.8rem;
}

.chat-input-section {
    padding: 10px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(0, 0, 0, 0.3);
    flex-shrink: 0;
}

.chat-input {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: white;
    font-size: 0.9rem;
}

.chat-input:focus {
    background: rgba(255, 255, 255, 0.15);
    border-color: #ff0000;
    box-shadow: 0 0 0 0.2rem rgba(255, 0, 0, 0.25);
    color: white;
}

.chat-input::placeholder {
    color: rgba(255, 255, 255, 0.6);
}

.chat-send-btn {
    background: linear-gradient(135deg, #ff0000 0%, #cc0000 100%);
    border: none;
    padding: 8px 15px;
}

.chat-send-btn:hover {
    background: linear-gradient(135deg, #e60000 0%, #b30000 100%);
}

.chat-send-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.chat-status {
    margin-top: 5px;
    min-height: 20px;
}

.loading-messages {
    color: rgba(255, 255, 255, 0.7);
    font-style: italic;
}

.chat-messages::-webkit-scrollbar {
    width: 6px;
}

.chat-messages::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
}

.chat-messages::-webkit-scrollbar-thumb {
    background: rgba(255, 0, 0, 0.5);
    border-radius: 3px;
}

.chat-messages::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 0, 0, 0.7);
}
</style>
`;

// Inject styles
if (!document.getElementById('youtube-chat-styles')) {
    const styleElement = document.createElement('div');
    styleElement.id = 'youtube-chat-styles';
    styleElement.innerHTML = youtubeChatStyles;
    document.head.appendChild(styleElement);
}
