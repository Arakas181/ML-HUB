<?php
session_start();
require_once 'config.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : 'user';
$username = $isLoggedIn ? $_SESSION['username'] : '';

$noticeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($noticeId <= 0) {
	http_response_code(400);
	die('Invalid notice id.');
}

try {
	// Try to fetch with status first
	$stmt = $pdo->prepare("\n\t\tSELECT n.*, u.username as author_name \n\t\tFROM notices n \n\t\tJOIN users u ON n.author_id = u.id \n\t\tWHERE n.id = ?\n\t");
	$stmt->execute([$noticeId]);
	$notice = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$notice) {
		http_response_code(404);
		die('Notice not found.');
	}

	// Enforce visibility: published for public; admins can view all
	$status = $notice['status'] ?? 'published';
	if ($status !== 'published' && !in_array($userRole, ['admin', 'super_admin'])) {
		http_response_code(403);
		die('This notice is not available.');
	}
} catch (PDOException $e) {
	error_log('Failed to fetch notice: ' . $e->getMessage());
	http_response_code(500);
	die('An error occurred.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo htmlspecialchars($notice['title']); ?> - Notice</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
	<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
		<div class="container">
			<a class="navbar-brand" href="index.php"><i class="fas fa-gamepad me-2"></i>ML HUB</a>
			<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
				<span class="navbar-toggler-icon"></span>
			</button>
			<div class="collapse navbar-collapse" id="navbarNav">
				<ul class="navbar-nav me-auto">
					<li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
				</ul>
				<div class="d-flex align-items-center">
					<?php if ($isLoggedIn): ?>
					<div class="dropdown">
						<a class="btn btn-outline-light dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
							<i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($username); ?>
						</a>
						<ul class="dropdown-menu">
							<li><a class="dropdown-item" href="profile.php">Profile</a></li>
							<?php if ($userRole === 'admin' || $userRole === 'super_admin'): ?>
							<li><a class="dropdown-item" href="admin_dashboard.php">Admin Dashboard</a></li>
							<?php endif; ?>
							<li><hr class="dropdown-divider"></li>
							<li><a class="dropdown-item" href="logout.php">Logout</a></li>
						</ul>
					</div>
					<?php else: ?>
					<a href="login.php" class="btn btn-outline-light me-2">Login</a>
					<a href="register.php" class="btn btn-primary">Register</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</nav>

	<div class="container mt-5">
		<div class="row justify-content-center">
			<div class="col-lg-8">
				<div class="card">
					<div class="card-body">
						<div class="d-flex justify-content-between align-items-start mb-2">
							<h3 class="mb-0"><?php echo htmlspecialchars($notice['title']); ?></h3>
							<?php $priority = $notice['priority'] ?? 'normal'; ?>
							<span class="badge bg-<?php echo $priority === 'high' ? 'danger' : ($priority === 'normal' ? 'info' : 'success'); ?>">
								<?php echo ucfirst((string)$priority); ?>
							</span>
						</div>
						<div class="text-muted mb-3">
							<i class="fas fa-user me-1"></i><?php echo htmlspecialchars($notice['author_name']); ?>
							<span class="ms-3"><i class="fas fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($notice['created_at'])); ?></span>
							<?php if (!empty($notice['status'])): ?>
							<span class="ms-3"><i class="fas fa-toggle-on me-1"></i><?php echo ucfirst($notice['status']); ?></span>
							<?php endif; ?>
						</div>
						<p class="mb-0" style="white-space: pre-line;"><?php echo nl2br(htmlspecialchars($notice['content'])); ?></p>
					</div>
				</div>
				<div class="mt-3">
					<a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
				</div>
			</div>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
