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
?>