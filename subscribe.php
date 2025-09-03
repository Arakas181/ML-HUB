<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: index.php');
	exit();
}

$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
	header('Location: index.php?error=invalid_email');
	exit();
}

try {
	// Create table if not exists (safe-guard)
	$pdo->exec("CREATE TABLE IF NOT EXISTS newsletter_subscribers (
		id INT AUTO_INCREMENT PRIMARY KEY,
		email VARCHAR(255) UNIQUE NOT NULL,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

	$stmt = $pdo->prepare("INSERT IGNORE INTO newsletter_subscribers (email) VALUES (?)");
	$stmt->execute([$email]);

	header('Location: index.php?success=subscribed');
	exit();
} catch (PDOException $e) {
	error_log('Subscribe error: ' . $e->getMessage());
	header('Location: index.php?error=subscribe_failed');
	exit();
}


