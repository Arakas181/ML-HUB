<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'super_admin')) {
	header('Location: admin_login.php');
	exit();
}

// Placeholder settings page
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>System Settings</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
	<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
		<div class="container">
			<a class="navbar-brand" href="super_admin_dashboard.php">Super Admin Panel</a>
			<div class="navbar-nav ms-auto">
				<a href="super_admin_dashboard.php" class="nav-link">Dashboard</a>
				<a href="logout.php" class="nav-link">Logout</a>
			</div>
		</div>
	</nav>

	<div class="container my-4">
		<h2 class="mb-3">System Settings</h2>
		<p class="text-muted">This is a placeholder page for platform-wide settings.</p>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


