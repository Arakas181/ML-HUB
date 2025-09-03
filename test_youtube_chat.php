<?php
session_start();
require_once 'config.php';

// Simple test script for YouTube chat functionality
echo "<!DOCTYPE html>\n";
echo "<html><head><title>YouTube Chat Test</title></head><body>\n";
echo "<h2>YouTube Chat System Test</h2>\n";

// Test 1: Check if tables exist
echo "<h3>1. Database Tables Check</h3>\n";
try {
    $tables = ['youtube_video_comments', 'youtube_video_viewers', 'youtube_video_sessions'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Table '$table' exists<br>\n";
        } else {
            echo "❌ Table '$table' missing<br>\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>\n";
}

// Test 2: API endpoint test
echo "<h3>2. API Endpoint Test</h3>\n";
if (file_exists('youtube_video_chat.php')) {
    echo "✅ youtube_video_chat.php exists<br>\n";
} else {
    echo "❌ youtube_video_chat.php missing<br>\n";
}

// Test 3: JavaScript client test
echo "<h3>3. JavaScript Client Test</h3>\n";
if (file_exists('youtube_chat_client.js')) {
    echo "✅ youtube_chat_client.js exists<br>\n";
} else {
    echo "❌ youtube_chat_client.js missing<br>\n";
}

// Test 4: Sample data insertion
echo "<h3>4. Sample Data Test</h3>\n";
if (isset($_SESSION['user_id'])) {
    try {
        // Insert test comment
        $stmt = $pdo->prepare("
            INSERT INTO youtube_video_comments (video_id, user_id, username, user_role, message)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute(['dQw4w9WgXcQ', $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], 'Test message from ' . $_SESSION['username']]);
        
        echo "✅ Test comment inserted successfully<br>\n";
        
        // Retrieve and display comments
        $stmt = $pdo->prepare("
            SELECT * FROM youtube_video_comments 
            WHERE video_id = 'dQw4w9WgXcQ' 
            ORDER BY created_at DESC LIMIT 5
        ");
        $stmt->execute();
        $comments = $stmt->fetchAll();
        
        echo "📝 Recent comments for test video:<br>\n";
        foreach ($comments as $comment) {
            echo "- " . htmlspecialchars($comment['username']) . ": " . htmlspecialchars($comment['message']) . " (" . $comment['created_at'] . ")<br>\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Error testing sample data: " . $e->getMessage() . "<br>\n";
    }
} else {
    echo "⚠️ Not logged in - cannot test comment insertion<br>\n";
    echo "<a href='login.php'>Login to test comment functionality</a><br>\n";
}

// Test 5: Integration test
echo "<h3>5. Integration Test</h3>\n";
echo "<p>To fully test the YouTube chat system:</p>\n";
echo "<ol>\n";
echo "<li><a href='index.php'>Go to the main page (index.php)</a></li>\n";
echo "<li>Make sure you're logged in</li>\n";
echo "<li>Look for the 'Video Chat' section below the YouTube video</li>\n";
echo "<li>Try typing a message in the chat input</li>\n";
echo "<li>The message should appear in real-time</li>\n";
echo "</ol>\n";

echo "<h3>6. Live Test Interface</h3>\n";
if (isset($_SESSION['user_id'])) {
    echo "<div id='test-chat-container' style='border: 1px solid #ccc; height: 300px; margin: 10px 0;'></div>\n";
    echo "<script src='youtube_chat_client.js'></script>\n";
    echo "<script>\n";
    echo "// Initialize test chat for demo video\n";
    echo "const testChat = new YouTubeChatClient('dQw4w9WgXcQ', 'test-chat-container');\n";
    echo "</script>\n";
} else {
    echo "<p>Please <a href='login.php'>login</a> to see the live chat interface.</p>\n";
}

echo "</body></html>\n";
?>
