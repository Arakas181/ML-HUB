<?php
require_once 'config.php';

echo "<h2>Setting up Chat and Polls Database Tables</h2>";

try {
    // Create chat_messages table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chat_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            username VARCHAR(50) NOT NULL,
            user_role ENUM('user', 'squad_leader', 'admin', 'super_admin') NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "<p>✅ Chat messages table created successfully</p>";

    // Create live_polls table
    $pdo->exec("
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
        )
    ");
    echo "<p>✅ Live polls table created successfully</p>";

    // Create poll_votes table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS poll_votes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            poll_id INT NOT NULL,
            user_id INT NOT NULL,
            option_index INT NOT NULL,
            voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (poll_id) REFERENCES live_polls(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_vote (poll_id, user_id, option_index)
        )
    ");
    echo "<p>✅ Poll votes table created successfully</p>";

    // Check if there's at least one user to create sample poll
    $stmt = $pdo->query("SELECT id FROM users LIMIT 1");
    $user = $stmt->fetch();
    
    if ($user) {
        // Insert a sample poll for testing
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO live_polls (title, description, options, created_by, ends_at) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $endsAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $options = json_encode(['Team Phoenix', 'Team Hydra', 'Team Titans', 'Team Warriors']);
        
        $stmt->execute([
            'Which team will win the championship?',
            'Vote for your favorite team in the upcoming championship match!',
            $options,
            $user['id'],
            $endsAt
        ]);
        
        echo "<p>✅ Sample poll created successfully</p>";
    } else {
        echo "<p>⚠️ No users found - sample poll not created</p>";
    }

    echo "<h3>🎉 Setup Complete!</h3>";
    echo "<p>Your chat and polling system is now ready to use.</p>";
    echo "<p><a href='index.php'>Go to Homepage</a> | <a href='manage_polls.php'>Manage Polls</a></p>";

} catch (PDOException $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>
