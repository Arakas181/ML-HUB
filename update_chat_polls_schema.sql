-- Update script for chat and polling system
-- Run this script to add the necessary tables for chat and polls functionality

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

-- Insert a sample poll for testing
INSERT INTO live_polls (title, description, options, created_by, ends_at) 
VALUES (
    'Which team will win the championship?',
    'Vote for your favorite team in the upcoming championship match!',
    '["Team Phoenix", "Team Hydra", "Team Titans", "Team Warriors"]',
    1,
    DATE_ADD(NOW(), INTERVAL 1 HOUR)
);
