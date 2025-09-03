<?php
session_start();
require_once 'config.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
	header('Location: index.php');
	exit();
}

try {
	$stmt = $pdo->prepare("SELECT c.*, u.username as author_name FROM content c JOIN users u ON c.author_id = u.id WHERE c.id = ? AND c.status = 'published'");
	$stmt->execute([$id]);
	$article = $stmt->fetch();

	if (!$article) {
		header('Location: index.php');
		exit();
	}
} catch (PDOException $e) {
	error_log('Article error: ' . $e->getMessage());
	$article = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo htmlspecialchars($article['title'] ?? 'Article'); ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
	<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
		<div class="container">
			<a class="navbar-brand" href="index.php">ML HUB</a>
			<div class="navbar-nav ms-auto">
				<a href="news.php" class="nav-link">Back to News</a>
			</div>
		</div>
	</nav>

	<div class="container my-4">
		<h2 class="mb-2"><?php echo htmlspecialchars($article['title']); ?></h2>
		<p class="text-muted">By <?php echo htmlspecialchars($article['author_name']); ?> · <?php echo date('M j, Y', strtotime($article['published_at'])); ?></p>
		<hr>
		<div><?php echo nl2br($article['content']); ?></div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


