-- Create tables for YouTube video chat system

-- Table to store comments on YouTube videos
CREATE TABLE IF NOT EXISTS youtube_video_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    video_id VARCHAR(255) NOT NULL,
    user_id INT NOT NULL,
    username VARCHAR(100) NOT NULL,
    user_role ENUM('user', 'squad_leader', 'admin', 'super_admin') DEFAULT 'user',
    message TEXT NOT NULL,
    is_deleted TINYINT(1) DEFAULT 0,
    deleted_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_video_id (video_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Table to track active viewers for each video
CREATE TABLE IF NOT EXISTS youtube_video_viewers (
    user_id INT NOT NULL,
    video_id VARCHAR(255) NOT NULL,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, video_id),
    INDEX idx_video_id (video_id),
    INDEX idx_last_seen (last_seen),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table to store video metadata and settings
CREATE TABLE IF NOT EXISTS youtube_video_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    video_id VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT 1,
    ended_at TIMESTAMP NULL,
    INDEX idx_video_id (video_id),
    INDEX idx_last_activity (last_activity),
    INDEX idx_active (is_active)
);

-- Table for chat moderation actions
CREATE TABLE IF NOT EXISTS youtube_chat_moderation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    video_id VARCHAR(255) NOT NULL,
    moderator_id INT NOT NULL,
    target_user_id INT NOT NULL,
    action_type ENUM('delete_message', 'timeout', 'ban', 'unban') NOT NULL,
    target_message_id INT NULL,
    duration_minutes INT NULL,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_video_id (video_id),
    INDEX idx_moderator_id (moderator_id),
    INDEX idx_target_user_id (target_user_id),
    FOREIGN KEY (moderator_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_message_id) REFERENCES youtube_video_comments(id) ON DELETE SET NULL
);

-- Table for banned users per video
CREATE TABLE IF NOT EXISTS youtube_video_bans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    video_id VARCHAR(255) NOT NULL,
    user_id INT NOT NULL,
    banned_by INT NOT NULL,
    reason TEXT,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_video_user (video_id, user_id),
    INDEX idx_video_id (video_id),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (banned_by) REFERENCES users(id) ON DELETE CASCADE
);
