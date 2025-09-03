<?php
// Database configuration
$host = getenv('DB_HOST') !== false ? getenv('DB_HOST') : 'localhost';
$dbname = getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'esports_platform';
$username = getenv('DB_USER') !== false ? getenv('DB_USER') : 'root';
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$port = getenv('DB_PORT') !== false ? (int) getenv('DB_PORT') : 3306;

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
	$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
	$pdo = new PDO($dsn, $username, $password, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
	]);
	// Connection successful
} catch (PDOException $e) {
	// More detailed error message for debugging
	$error_message = "Database connection failed: " . $e->getMessage();
	
	// Log the error
	error_log($error_message);
	
	// Display user-friendly error message
	die("We're experiencing technical difficulties. Please try again later.");
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Get current user ID
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Get user role
function getUserRole() {
    return $_SESSION['role'] ?? 'user';
}

// Get username
function getUsername() {
    return $_SESSION['username'] ?? 'Guest';
}

// Create a new poll
function createPoll($title, $description, $options, $durationMinutes = 5, $multipleChoice = false) {
    global $pdo;
    
    try {
        // Calculate ends_at based on current time and duration
        $endsAt = date('Y-m-d H:i:s', time() + ($durationMinutes * 60));
        
        $stmt = $pdo->prepare("
            INSERT INTO live_polls (title, description, options, duration_minutes, multiple_choice, created_by, ends_at) 
            VALUES (:title, :description, :options, :duration_minutes, :multiple_choice, :created_by, :ends_at)
        ");
        
        $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':options' => json_encode($options),
            ':duration_minutes' => $durationMinutes,
            ':multiple_choice' => $multipleChoice,
            ':created_by' => getUserId(),
            ':ends_at' => $endsAt
        ]);
        
        return [
            'success' => true,
            'message' => 'Poll created successfully',
            'poll_id' => $pdo->lastInsertId()
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => 'Failed to create poll: ' . $e->getMessage()
        ];
    }
}

// End a poll
function endPoll($pollId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE live_polls SET status = 'ended' WHERE id = :id");
        $stmt->execute([':id' => $pollId]);
        
        return [
            'success' => true,
            'message' => 'Poll ended successfully'
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => 'Failed to end poll: ' . $e->getMessage()
        ];
    }
}

// Get active polls
function getActivePolls() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM live_polls 
            WHERE status = 'active' AND ends_at > NOW()
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        
        return [
            'success' => true,
            'polls' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => 'Failed to fetch polls: ' . $e->getMessage()
        ];
    }
}
?>