/**
 * Real-time Polling Widget for ML HUB Esports
 * Handles live poll display, voting, and real-time updates
 */

class PollsWidget {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        this.options = {
            websocketUrl: options.websocketUrl || 'ws://localhost:8080',
            apiUrl: options.apiUrl || 'live_polls.php',
            roomId: options.roomId || 1,
            userId: options.userId || null,
            username: options.username || 'Anonymous',
            userRole: options.userRole || 'user',
            autoRefresh: options.autoRefresh !== false,
            refreshInterval: options.refreshInterval || 30000,
            showResults: options.showResults !== false,
            allowVoting: options.allowVoting !== false,
            theme: options.theme || 'dark',
            ...options
        };
        
        this.polls = new Map();
        this.websocket = null;
        this.refreshTimer = null;
        this.voteCooldowns = new Map();
        
        this.init();
    }

    init() {
        this.createContainer();
        this.connectWebSocket();
        this.loadActivePolls();
        this.setupEventListeners();
        
        if (this.options.autoRefresh) {
            this.startAutoRefresh();
        }
    }

    createContainer() {
        this.container.className = `polls-widget theme-${this.options.theme}`;
        this.container.innerHTML = `
            <div class="polls-header">
                <h4><i class="fas fa-poll me-2"></i>Live Polls</h4>
                <div class="polls-controls">
                    <button class="btn btn-sm btn-outline-light refresh-btn" onclick="pollsWidget.refresh()">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                    ${this.canCreatePolls() ? `
                        <button class="btn btn-sm btn-primary create-poll-btn" onclick="pollsWidget.showCreateForm()">
                            <i class="fas fa-plus"></i>
                        </button>
                    ` : ''}
                </div>
            </div>
            <div class="polls-content" id="pollsContent">
                <div class="loading-spinner">
                    <i class="fas fa-spinner fa-spin"></i> Loading polls...
                </div>
            </div>
            <div class="polls-footer">
                <small class="connection-status" id="connectionStatus">
                    <i class="fas fa-circle text-success"></i> Connected
                </small>
            </div>
        `;
    }

    connectWebSocket() {
        if (!this.options.websocketUrl) return;

        try {
            this.websocket = new WebSocket(this.options.websocketUrl);
            
            this.websocket.onopen = () => {
                console.log('Connected to polls WebSocket');
                this.updateConnectionStatus(true);
                
                // Join room for poll updates
                this.websocket.send(JSON.stringify({
                    type: 'join_room',
                    room_id: this.options.roomId,
                    user_id: this.options.userId,
                    username: this.options.username,
                    user_role: this.options.userRole
                }));
            };

            this.websocket.onmessage = (event) => {
                const data = JSON.parse(event.data);
                this.handleWebSocketMessage(data);
            };

            this.websocket.onclose = () => {
                console.log('Disconnected from polls WebSocket');
                this.updateConnectionStatus(false);
                
                // Attempt to reconnect after 3 seconds
                setTimeout(() => {
                    if (!this.websocket || this.websocket.readyState === WebSocket.CLOSED) {
                        this.connectWebSocket();
                    }
                }, 3000);
            };

            this.websocket.onerror = (error) => {
                console.error('WebSocket error:', error);
                this.updateConnectionStatus(false);
            };

        } catch (error) {
            console.error('Failed to connect to WebSocket:', error);
            this.updateConnectionStatus(false);
        }
    }

    handleWebSocketMessage(data) {
        switch (data.type) {
            case 'poll_created':
                this.addPoll(data.poll);
                break;
            case 'poll_update':
                this.updatePollResults(data.poll_id, data.results);
                break;
            case 'poll_ended':
                this.endPoll(data.poll_id);
                break;
            case 'new_vote':
                this.handleNewVote(data);
                break;
        }
    }

    async loadActivePolls() {
        try {
            const response = await fetch(`${this.options.apiUrl}?action=get_active&room_id=${this.options.roomId}`);
            const data = await response.json();
            
            if (data.success) {
                this.renderPolls(data.polls);
            } else {
                this.showError('Failed to load polls');
            }
        } catch (error) {
            console.error('Error loading polls:', error);
            this.showError('Failed to load polls');
        }
    }

    renderPolls(polls) {
        const content = document.getElementById('pollsContent');
        
        if (!polls || polls.length === 0) {
            content.innerHTML = `
                <div class="no-polls">
                    <i class="fas fa-vote-yea fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No active polls at the moment</p>
                </div>
            `;
            return;
        }

        content.innerHTML = polls.map(poll => this.renderPoll(poll)).join('');
        
        // Start timers for active polls
        polls.forEach(poll => {
            if (poll.status === 'active') {
                this.startPollTimer(poll.id, poll.created_at, poll.duration);
            }
        });
    }

    renderPoll(poll) {
        const options = JSON.parse(poll.options);
        const results = poll.results || {};
        const totalVotes = poll.total_votes || 0;
        const userVote = poll.user_vote || null;
        const timeRemaining = this.getTimeRemaining(poll.created_at, poll.duration);
        
        return `
            <div class="poll-card ${poll.status}" data-poll-id="${poll.id}">
                <div class="poll-header">
                    <div class="poll-question">
                        <h6>${this.escapeHtml(poll.question)}</h6>
                        <small class="text-muted">
                            by ${this.escapeHtml(poll.creator_name)} • ${totalVotes} votes
                        </small>
                    </div>
                    <div class="poll-timer" data-poll-id="${poll.id}">
                        ${poll.status === 'active' ? this.formatTime(timeRemaining) : 'ENDED'}
                    </div>
                </div>
                
                <div class="poll-options">
                    ${options.map((option, index) => {
                        const votes = results[index] || 0;
                        const percentage = totalVotes > 0 ? (votes / totalVotes) * 100 : 0;
                        const isSelected = userVote === index;
                        const canVote = this.canVote(poll);
                        
                        return `
                            <div class="poll-option ${isSelected ? 'selected' : ''} ${canVote ? 'clickable' : ''}" 
                                 onclick="${canVote ? `pollsWidget.vote(${poll.id}, ${index})` : ''}">
                                <div class="option-bar" style="width: ${percentage}%"></div>
                                <div class="option-content">
                                    <span class="option-text">${this.escapeHtml(option)}</span>
                                    <div class="option-stats">
                                        <span class="vote-count">${votes}</span>
                                        <span class="vote-percentage">${percentage.toFixed(1)}%</span>
                                    </div>
                                </div>
                                ${isSelected ? '<i class="fas fa-check-circle selected-icon"></i>' : ''}
                            </div>
                        `;
                    }).join('')}
                </div>
                
                ${this.canManagePoll(poll) ? `
                    <div class="poll-actions">
                        ${poll.status === 'active' ? `
                            <button class="btn btn-sm btn-outline-warning" onclick="pollsWidget.endPoll(${poll.id})">
                                <i class="fas fa-stop me-1"></i>End Poll
                            </button>
                        ` : ''}
                        <button class="btn btn-sm btn-outline-info" onclick="pollsWidget.showPollDetails(${poll.id})">
                            <i class="fas fa-chart-bar me-1"></i>Details
                        </button>
                    </div>
                ` : ''}
            </div>
        `;
    }

    async vote(pollId, optionIndex) {
        if (!this.options.allowVoting || !this.options.userId) {
            this.showMessage('Voting not available', 'warning');
            return;
        }

        // Check cooldown
        const cooldownKey = `${pollId}_${this.options.userId}`;
        if (this.voteCooldowns.has(cooldownKey)) {
            this.showMessage('Please wait before voting again', 'warning');
            return;
        }

        try {
            const response = await fetch(this.options.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=vote&poll_id=${pollId}&option_index=${optionIndex}`
            });

            const data = await response.json();
            
            if (data.success) {
                this.updatePollResults(pollId, data.results);
                this.markUserVote(pollId, optionIndex);
                
                // Set cooldown
                this.voteCooldowns.set(cooldownKey, true);
                setTimeout(() => {
                    this.voteCooldowns.delete(cooldownKey);
                }, 2000);
                
                this.showMessage('Vote recorded!', 'success');
            } else {
                this.showMessage(data.error || 'Failed to vote', 'error');
            }
        } catch (error) {
            console.error('Error voting:', error);
            this.showMessage('Failed to vote', 'error');
        }
    }

    async endPoll(pollId) {
        if (!confirm('End this poll?')) return;

        try {
            const response = await fetch(this.options.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=end_poll&poll_id=${pollId}`
            });

            const data = await response.json();
            
            if (data.success) {
                this.endPollUI(pollId);
                this.showMessage('Poll ended', 'success');
            } else {
                this.showMessage(data.error || 'Failed to end poll', 'error');
            }
        } catch (error) {
            console.error('Error ending poll:', error);
            this.showMessage('Failed to end poll', 'error');
        }
    }

    updatePollResults(pollId, results) {
        const pollCard = document.querySelector(`[data-poll-id="${pollId}"]`);
        if (!pollCard) return;

        const options = pollCard.querySelectorAll('.poll-option');
        const totalVotes = Object.values(results.votes || {}).reduce((sum, votes) => sum + votes, 0);

        options.forEach((option, index) => {
            const votes = results.votes[index] || 0;
            const percentage = totalVotes > 0 ? (votes / totalVotes) * 100 : 0;
            
            const bar = option.querySelector('.option-bar');
            const voteCount = option.querySelector('.vote-count');
            const votePercentage = option.querySelector('.vote-percentage');
            
            if (bar) bar.style.width = percentage + '%';
            if (voteCount) voteCount.textContent = votes;
            if (votePercentage) votePercentage.textContent = percentage.toFixed(1) + '%';
        });

        // Update total votes in header
        const header = pollCard.querySelector('.poll-header small');
        if (header) {
            header.textContent = header.textContent.replace(/\d+ votes/, `${totalVotes} votes`);
        }
    }

    markUserVote(pollId, optionIndex) {
        const pollCard = document.querySelector(`[data-poll-id="${pollId}"]`);
        if (!pollCard) return;

        // Remove previous selection
        pollCard.querySelectorAll('.poll-option').forEach(option => {
            option.classList.remove('selected');
            const icon = option.querySelector('.selected-icon');
            if (icon) icon.remove();
        });

        // Mark new selection
        const selectedOption = pollCard.querySelectorAll('.poll-option')[optionIndex];
        if (selectedOption) {
            selectedOption.classList.add('selected');
            selectedOption.insertAdjacentHTML('beforeend', '<i class="fas fa-check-circle selected-icon"></i>');
        }
    }

    startPollTimer(pollId, createdAt, duration) {
        const timer = setInterval(() => {
            const timeRemaining = this.getTimeRemaining(createdAt, duration);
            const timerElement = document.querySelector(`[data-poll-id="${pollId}"] .poll-timer`);
            
            if (timerElement) {
                if (timeRemaining <= 0) {
                    timerElement.textContent = 'ENDED';
                    timerElement.classList.add('expired');
                    this.endPollUI(pollId);
                    clearInterval(timer);
                } else {
                    timerElement.textContent = this.formatTime(timeRemaining);
                }
            } else {
                clearInterval(timer);
            }
        }, 1000);
    }

    endPollUI(pollId) {
        const pollCard = document.querySelector(`[data-poll-id="${pollId}"]`);
        if (!pollCard) return;

        pollCard.classList.add('ended');
        pollCard.querySelectorAll('.poll-option').forEach(option => {
            option.classList.remove('clickable');
            option.onclick = null;
        });

        const actions = pollCard.querySelector('.poll-actions');
        if (actions) {
            const endButton = actions.querySelector('button[onclick*="endPoll"]');
            if (endButton) endButton.remove();
        }
    }

    getTimeRemaining(createdAt, duration) {
        const created = new Date(createdAt).getTime();
        const now = Date.now();
        const endTime = created + (duration * 1000);
        return Math.max(0, endTime - now);
    }

    formatTime(milliseconds) {
        const seconds = Math.floor(milliseconds / 1000);
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
    }

    canVote(poll) {
        return this.options.allowVoting && 
               poll.status === 'active' && 
               this.options.userId &&
               this.getTimeRemaining(poll.created_at, poll.duration) > 0;
    }

    canCreatePolls() {
        return ['admin', 'super_admin', 'moderator'].includes(this.options.userRole);
    }

    canManagePoll(poll) {
        return this.canCreatePolls() || poll.creator_id === this.options.userId;
    }

    showCreateForm() {
        // This would integrate with the main poll creation modal
        const event = new CustomEvent('showCreatePoll', {
            detail: { roomId: this.options.roomId }
        });
        document.dispatchEvent(event);
    }

    showPollDetails(pollId) {
        // This would show detailed poll analytics
        const event = new CustomEvent('showPollDetails', {
            detail: { pollId: pollId }
        });
        document.dispatchEvent(event);
    }

    addPoll(poll) {
        const content = document.getElementById('pollsContent');
        const noPollsMessage = content.querySelector('.no-polls');
        
        if (noPollsMessage) {
            content.innerHTML = '';
        }
        
        content.insertAdjacentHTML('afterbegin', this.renderPoll(poll));
        
        if (poll.status === 'active') {
            this.startPollTimer(poll.id, poll.created_at, poll.duration);
        }
    }

    refresh() {
        const refreshBtn = document.querySelector('.refresh-btn i');
        if (refreshBtn) {
            refreshBtn.classList.add('fa-spin');
            setTimeout(() => refreshBtn.classList.remove('fa-spin'), 1000);
        }
        
        this.loadActivePolls();
    }

    startAutoRefresh() {
        this.refreshTimer = setInterval(() => {
            this.loadActivePolls();
        }, this.options.refreshInterval);
    }

    stopAutoRefresh() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
            this.refreshTimer = null;
        }
    }

    updateConnectionStatus(connected) {
        const status = document.getElementById('connectionStatus');
        if (status) {
            status.innerHTML = connected 
                ? '<i class="fas fa-circle text-success"></i> Connected'
                : '<i class="fas fa-circle text-danger"></i> Disconnected';
        }
    }

    showMessage(message, type = 'info') {
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

    showError(message) {
        const content = document.getElementById('pollsContent');
        content.innerHTML = `
            <div class="error-message">
                <i class="fas fa-exclamation-triangle text-warning"></i>
                <p>${message}</p>
                <button class="btn btn-outline-light btn-sm" onclick="pollsWidget.refresh()">
                    <i class="fas fa-retry me-1"></i>Retry
                </button>
            </div>
        `;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    setupEventListeners() {
        // Handle window unload
        window.addEventListener('beforeunload', () => {
            this.destroy();
        });

        // Handle visibility change
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.stopAutoRefresh();
            } else {
                if (this.options.autoRefresh) {
                    this.startAutoRefresh();
                }
                this.refresh();
            }
        });
    }

    destroy() {
        this.stopAutoRefresh();
        
        if (this.websocket) {
            this.websocket.close();
        }
        
        this.polls.clear();
        this.voteCooldowns.clear();
    }

    // Public API methods
    updateOptions(newOptions) {
        this.options = { ...this.options, ...newOptions };
        this.refresh();
    }

    setRoom(roomId) {
        this.options.roomId = roomId;
        this.refresh();
    }

    setUser(userId, username, userRole) {
        this.options.userId = userId;
        this.options.username = username;
        this.options.userRole = userRole;
        this.refresh();
    }
}

// CSS Styles for the widget
const pollsWidgetCSS = `
.polls-widget {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 15px;
    overflow: hidden;
}

.polls-widget.theme-dark {
    background: rgba(0, 0, 0, 0.3);
    color: #f1f1f1;
}

.polls-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.polls-header h4 {
    margin: 0;
    font-size: 1.1rem;
}

.polls-controls {
    display: flex;
    gap: 0.5rem;
}

.polls-content {
    max-height: 400px;
    overflow-y: auto;
    padding: 1rem;
}

.poll-card {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.poll-card.ended {
    opacity: 0.7;
}

.poll-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.poll-question h6 {
    margin: 0 0 0.25rem 0;
    font-weight: 600;
}

.poll-timer {
    font-weight: bold;
    color: #e94560;
    font-size: 0.9rem;
}

.poll-timer.expired {
    color: #6c757d;
}

.poll-option {
    position: relative;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    margin-bottom: 0.5rem;
    overflow: hidden;
    transition: all 0.3s ease;
}

.poll-option.clickable {
    cursor: pointer;
}

.poll-option.clickable:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: #e94560;
}

.poll-option.selected {
    border-color: #e94560;
    background: rgba(233, 69, 96, 0.2);
}

.option-bar {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    background: linear-gradient(90deg, #e94560, rgba(233, 69, 96, 0.3));
    transition: width 0.5s ease;
    z-index: 1;
}

.option-content {
    position: relative;
    z-index: 2;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
}

.option-stats {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.vote-count {
    font-weight: bold;
}

.vote-percentage {
    font-size: 0.8rem;
    opacity: 0.8;
}

.selected-icon {
    color: #e94560;
    margin-left: 0.5rem;
}

.poll-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.polls-footer {
    padding: 0.5rem 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    text-align: center;
}

.connection-status {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
}

.no-polls, .error-message, .loading-spinner {
    text-align: center;
    padding: 2rem;
}

.no-polls i, .error-message i {
    display: block;
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .polls-content {
        max-height: 300px;
    }
    
    .poll-header {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .option-content {
        padding: 0.5rem;
    }
}
`;

// Inject CSS if not already present
if (!document.getElementById('polls-widget-css')) {
    const style = document.createElement('style');
    style.id = 'polls-widget-css';
    style.textContent = pollsWidgetCSS;
    document.head.appendChild(style);
}

// Global instance for easy access
let pollsWidget = null;

// Initialize widget function
function initializePollsWidget(containerId, options = {}) {
    pollsWidget = new PollsWidget(containerId, options);
    return pollsWidget;
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { PollsWidget, initializePollsWidget };
}
