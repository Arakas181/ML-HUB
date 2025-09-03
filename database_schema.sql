-- Database schema for Esports Platform
-- This file contains the necessary tables for the dashboard system to work properly

-- Users table (Enhanced with profile features)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    bio TEXT,
    avatar_url VARCHAR(500),
    banner_url VARCHAR(500),
    role ENUM('user', 'squad_leader', 'admin', 'super_admin') DEFAULT 'user',
    is_verified BOOLEAN DEFAULT FALSE,
    last_active TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Chat messages table
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    user_role ENUM('user', 'squad_leader', 'admin', 'super_admin') NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Live polls table
CREATE TABLE IF NOT EXISTS live_polls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    options JSON NOT NULL,
    status ENUM('active', 'ended', 'cancelled') DEFAULT 'active',
    multiple_choice BOOLEAN DEFAULT FALSE,
    anonymous BOOLEAN DEFAULT TRUE,
    duration_minutes INT DEFAULT 5,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ends_at TIMESTAMP NOT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Poll votes table
CREATE TABLE IF NOT EXISTS poll_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT NOT NULL,
    user_id INT NOT NULL,
    option_index INT NOT NULL,
    voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (poll_id) REFERENCES live_polls(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_vote (poll_id, user_id, option_index)
);

-- Teams table
CREATE TABLE IF NOT EXISTS teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    logo_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tournaments table (Enhanced with bracket types and streaming)
CREATE TABLE IF NOT EXISTS tournaments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'upcoming',
    bracket_type ENUM('single_elimination', 'double_elimination', 'swiss', 'round_robin') DEFAULT 'single_elimination',
    prize_pool DECIMAL(10,2),
    max_participants INT,
    entry_fee DECIMAL(10,2) DEFAULT 0.00,
    rules TEXT,
    stream_url VARCHAR(500),
    twitch_channel VARCHAR(100),
    youtube_channel VARCHAR(100),
    facebook_page VARCHAR(100),
    registration_deadline DATETIME,
    check_in_start DATETIME,
    check_in_end DATETIME,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Tournament participants table
CREATE TABLE IF NOT EXISTS tournament_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    user_id INT NOT NULL,
    squad_name VARCHAR(100) NOT NULL,
    in_game_name VARCHAR(100) NOT NULL,
    status ENUM('registered', 'participant', 'winner', 'runner_up', 'eliminated') DEFAULT 'registered',
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_participation (tournament_id, user_id)
);

-- Chat messages table for real-time chat
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    user_role ENUM('user', 'squad_leader', 'admin', 'super_admin') NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Previous live matches table
CREATE TABLE IF NOT EXISTS previous_live_matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    video_url VARCHAR(500),
    viewer_count INT DEFAULT 0,
    ended_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
);

-- Matches table
CREATE TABLE IF NOT EXISTS matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    team1_id INT NOT NULL,
    team2_id INT NOT NULL,
    scheduled_time DATETIME NOT NULL,
    status ENUM('scheduled', 'live', 'completed', 'cancelled') DEFAULT 'scheduled',
    score_team1 INT DEFAULT 0,
    score_team2 INT DEFAULT 0,
    round VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (team1_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (team2_id) REFERENCES teams(id) ON DELETE CASCADE
);

-- Notices table
CREATE TABLE IF NOT EXISTS notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    author_id INT NOT NULL,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Content table (for news and guides)
CREATE TABLE IF NOT EXISTS content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    type ENUM('news', 'guide') NOT NULL,
    author_id INT NOT NULL,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Squads table
CREATE TABLE IF NOT EXISTS squads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    mlbb_id VARCHAR(50) NOT NULL,
    leader_id INT NOT NULL,
    description TEXT,
    logo_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (leader_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Squad members table
CREATE TABLE IF NOT EXISTS squad_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    squad_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (squad_id) REFERENCES squads(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_membership (squad_id, user_id)
);

-- Squad applications table
CREATE TABLE IF NOT EXISTS squad_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    squad_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    FOREIGN KEY (squad_id) REFERENCES squads(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_application (squad_id, user_id)
);

-- Squad invitations table
CREATE TABLE IF NOT EXISTS squad_invitations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    squad_id INT NOT NULL,
    user_id INT NOT NULL,
    invited_by INT NOT NULL,
    status ENUM('pending', 'accepted', 'declined') DEFAULT 'pending',
    invited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at TIMESTAMP NULL,
    FOREIGN KEY (squad_id) REFERENCES squads(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_invitation (squad_id, user_id)
);

-- Squad tournaments table
CREATE TABLE IF NOT EXISTS squad_tournaments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    squad_id INT NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('registered', 'active', 'eliminated', 'winner', 'runner_up') DEFAULT 'registered',
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (squad_id) REFERENCES squads(id) ON DELETE CASCADE,
    UNIQUE KEY unique_squad_tournament (tournament_id, squad_id)
);

-- Squad messages table
CREATE TABLE IF NOT EXISTS squad_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    squad_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (squad_id) REFERENCES squads(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Squad scrims table
CREATE TABLE IF NOT EXISTS squad_scrims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    host_squad_id INT NOT NULL,
    opponent_squad_id INT NOT NULL,
    scrim_date DATETIME NOT NULL,
    youtube_url VARCHAR(255) NULL,
    status ENUM('scheduled', 'confirmed', 'cancelled', 'completed') DEFAULT 'scheduled',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    cancelled_at TIMESTAMP NULL,
    cancelled_by INT NULL,
    FOREIGN KEY (host_squad_id) REFERENCES squads(id) ON DELETE CASCADE,
    FOREIGN KEY (opponent_squad_id) REFERENCES squads(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Squad training sessions table
CREATE TABLE IF NOT EXISTS squad_training (
    id INT AUTO_INCREMENT PRIMARY KEY,
    squad_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    training_date DATETIME NOT NULL,
    youtube_url VARCHAR(255) NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    FOREIGN KEY (squad_id) REFERENCES squads(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('squad_application', 'squad_invitation', 'scrim_invitation', 'training_session', 'general') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Videos table for all video content (Enhanced VOD system)
CREATE TABLE IF NOT EXISTS videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    video_url VARCHAR(500) NOT NULL,
    thumbnail_url VARCHAR(500),
    category ENUM('live_stream', 'live_match', 'scrim', 'tournament', 'vod', 'highlight') NOT NULL,
    status ENUM('live', 'completed', 'scheduled', 'processing') DEFAULT 'completed',
    view_count INT DEFAULT 0,
    duration INT, -- in seconds
    quality ENUM('720p', '1080p', '1440p', '4k') DEFAULT '1080p',
    platform ENUM('youtube', 'twitch', 'facebook', 'native') DEFAULT 'native',
    platform_video_id VARCHAR(100),
    tags JSON,
    is_featured BOOLEAN DEFAULT FALSE,
    uploaded_by INT NOT NULL,
    tournament_id INT NULL,
    match_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE SET NULL,
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE SET NULL
);

-- Live streams table for multi-platform streaming
CREATE TABLE IF NOT EXISTS live_streams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    stream_key VARCHAR(255) UNIQUE NOT NULL,
    status ENUM('offline', 'live', 'scheduled', 'ended') DEFAULT 'offline',
    platform ENUM('youtube', 'twitch', 'facebook', 'multi') DEFAULT 'multi',
    youtube_stream_id VARCHAR(100),
    twitch_stream_id VARCHAR(100),
    facebook_stream_id VARCHAR(100),
    viewer_count INT DEFAULT 0,
    max_viewers INT DEFAULT 0,
    thumbnail_url VARCHAR(500),
    streamer_id INT NOT NULL,
    tournament_id INT NULL,
    match_id INT NULL,
    started_at TIMESTAMP NULL,
    ended_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (streamer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE SET NULL,
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE SET NULL
);

-- Multi-stream viewing sessions
CREATE TABLE IF NOT EXISTS multi_stream_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_name VARCHAR(255) NOT NULL,
    streams JSON NOT NULL, -- Array of stream IDs and positions
    layout ENUM('grid_2x2', 'grid_3x3', 'pip', 'side_by_side') DEFAULT 'grid_2x2',
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Enhanced chat system with external platform sync
CREATE TABLE IF NOT EXISTS chat_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('tournament', 'match', 'general', 'squad') NOT NULL,
    tournament_id INT NULL,
    match_id INT NULL,
    squad_id INT NULL,
    twitch_channel VARCHAR(100),
    youtube_channel VARCHAR(100),
    facebook_page VARCHAR(100),
    sync_external BOOLEAN DEFAULT FALSE,
    moderation_level ENUM('none', 'basic', 'strict') DEFAULT 'basic',
    slow_mode_seconds INT DEFAULT 0,
    subscriber_only BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    FOREIGN KEY (squad_id) REFERENCES squads(id) ON DELETE CASCADE
);

-- Chat moderators
CREATE TABLE IF NOT EXISTS chat_moderators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_room_id INT NOT NULL,
    user_id INT NOT NULL,
    permissions JSON, -- Array of permissions like 'timeout', 'ban', 'delete_messages'
    assigned_by INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_moderator (chat_room_id, user_id)
);

-- Enhanced chat messages with moderation features
CREATE TABLE IF NOT EXISTS enhanced_chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_room_id INT NOT NULL,
    user_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    user_role ENUM('user', 'squad_leader', 'admin', 'super_admin', 'moderator') NOT NULL,
    message TEXT NOT NULL,
    message_type ENUM('text', 'emote', 'system', 'poll_vote') DEFAULT 'text',
    is_deleted BOOLEAN DEFAULT FALSE,
    deleted_by INT NULL,
    deleted_reason VARCHAR(255) NULL,
    external_platform ENUM('native', 'twitch', 'youtube', 'facebook') DEFAULT 'native',
    external_message_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Live polls system
CREATE TABLE IF NOT EXISTS live_polls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    chat_room_id INT NOT NULL,
    tournament_id INT NULL,
    match_id INT NULL,
    options JSON NOT NULL, -- Array of poll options
    status ENUM('active', 'ended', 'cancelled') DEFAULT 'active',
    multiple_choice BOOLEAN DEFAULT FALSE,
    anonymous BOOLEAN DEFAULT TRUE,
    duration_minutes INT DEFAULT 5,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ends_at TIMESTAMP NOT NULL,
    FOREIGN KEY (chat_room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Poll votes
CREATE TABLE IF NOT EXISTS poll_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT NOT NULL,
    user_id INT NOT NULL,
    option_index INT NOT NULL,
    voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (poll_id) REFERENCES live_polls(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_vote (poll_id, user_id, option_index)
);

-- Q&A system
CREATE TABLE IF NOT EXISTS qa_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    chat_room_id INT NOT NULL,
    tournament_id INT NULL,
    match_id INT NULL,
    status ENUM('active', 'ended') DEFAULT 'active',
    moderated BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    FOREIGN KEY (chat_room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Q&A questions
CREATE TABLE IF NOT EXISTS qa_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    qa_session_id INT NOT NULL,
    user_id INT NOT NULL,
    question TEXT NOT NULL,
    answer TEXT NULL,
    status ENUM('pending', 'approved', 'answered', 'rejected') DEFAULT 'pending',
    upvotes INT DEFAULT 0,
    answered_by INT NULL,
    asked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    answered_at TIMESTAMP NULL,
    FOREIGN KEY (qa_session_id) REFERENCES qa_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (answered_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Tournament brackets (for complex bracket management)
CREATE TABLE IF NOT EXISTS tournament_brackets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    bracket_data JSON NOT NULL, -- Complete bracket structure
    current_round INT DEFAULT 1,
    total_rounds INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE
);

-- Tournament check-ins
CREATE TABLE IF NOT EXISTS tournament_checkins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    user_id INT NOT NULL,
    squad_id INT NULL,
    checked_in_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (squad_id) REFERENCES squads(id) ON DELETE CASCADE,
    UNIQUE KEY unique_checkin (tournament_id, user_id)
);

-- User preferences for streaming and notifications
CREATE TABLE IF NOT EXISTS user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    stream_quality ENUM('auto', '720p', '1080p', '1440p', '4k') DEFAULT 'auto',
    chat_notifications BOOLEAN DEFAULT TRUE,
    tournament_notifications BOOLEAN DEFAULT TRUE,
    match_notifications BOOLEAN DEFAULT TRUE,
    email_notifications BOOLEAN DEFAULT TRUE,
    preferred_language VARCHAR(10) DEFAULT 'en',
    timezone VARCHAR(50) DEFAULT 'UTC',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_prefs (user_id)
);

-- Insert sample data for testing
INSERT INTO users (username, email, password, role) VALUES
('admin', 'admin@esports.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('superadmin', 'superadmin@esports.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin'),
('player1', 'player1@esports.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user'),
('squadleader1', 'squadleader1@esports.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'squad_leader');

-- Insert sample teams
INSERT INTO teams (name, description) VALUES
('Team Phoenix', 'Rising from the ashes'),
('Team Hydra', 'Multiple heads, multiple strategies'),
('Team Titans', 'Unstoppable force'),
('Team Warriors', 'Brave and fierce');

-- Insert sample squads
INSERT INTO squads (name, mlbb_id, leader_id, description) VALUES
('Phoenix Squad', 'PHX123456', 4, 'The elite squad led by squadleader1');

-- Insert sample squad members
INSERT INTO squad_members (squad_id, user_id) VALUES
(1, 3);

-- Insert sample tournaments
INSERT INTO tournaments (name, description, start_date, end_date, status, prize_pool) VALUES
('MSC 2023', 'Mobile Legends Southeast Asia Cup 2023', '2023-12-01', '2023-12-15', 'ongoing', 100000.00),
('Winter Championship', 'Annual winter esports championship', '2024-01-15', '2024-02-01', 'upcoming', 50000.00);

-- Insert sample matches
INSERT INTO matches (tournament_id, team1_id, team2_id, scheduled_time, status, score_team1, score_team2, round) VALUES
(1, 1, 2, '2023-12-10 14:00:00', 'live', 2, 1, 'Quarter Finals'),
(1, 3, 4, '2023-12-10 16:00:00', 'scheduled', 0, 0, 'Quarter Finals');

-- Insert sample notices
INSERT INTO notices (title, content, author_id, status) VALUES
('Welcome to EsportsHub', 'Welcome to our new esports platform!', 1, 'published'),
('Tournament Registration Open', 'Registration for Winter Championship is now open!', 1, 'published');

-- Insert sample content
INSERT INTO content (title, content, type, author_id, status, published_at) VALUES
('How to Improve Your Game', 'Tips and tricks to enhance your gaming skills...', 'guide', 1, 'published', NOW()),
('Latest Tournament Results', 'Check out the latest results from MSC 2023...', 'news', 1, 'published', NOW());