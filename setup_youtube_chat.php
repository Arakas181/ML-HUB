<?php
require_once 'config.php';

echo "Setting up YouTube Chat Database Tables...\n";

try {
    // Read and execute the SQL schema
    $sql = file_get_contents('update_youtube_chat_schema.sql');
    
    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
            echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
        }
    }
    
    echo "\n✅ YouTube Chat database tables created successfully!\n";
    
    // Test the tables by inserting sample data
    echo "\nTesting tables with sample data...\n";
    
    // Insert a sample video session
    $stmt = $pdo->prepare("
        INSERT INTO youtube_video_sessions (video_id, title, is_live, chat_enabled) 
        VALUES (?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE title = VALUES(title)
    ");
    $stmt->execute(['dQw4w9WgXcQ', 'Sample Tournament Stream', 1, 1]);
    echo "✓ Sample video session created\n";
    
    // Check if users table exists and has sample users
    $userCheck = $pdo->query("SELECT COUNT(*) as count FROM users LIMIT 1")->fetch();
    if ($userCheck['count'] == 0) {
        echo "⚠️  No users found. You may need to register users to test the chat system.\n";
    } else {
        echo "✓ Users table ready\n";
    }
    
    echo "\n🎉 YouTube Chat system is ready to use!\n";
    echo "\nNext steps:\n";
    echo "1. Make sure you're logged in to the platform\n";
    echo "2. Visit index.php to see the YouTube chat in action\n";
    echo "3. The chat will appear when a YouTube video is playing in the live stream section\n";
    
} catch (PDOException $e) {
    echo "❌ Error setting up database: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
