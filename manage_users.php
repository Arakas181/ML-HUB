<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
	header('Location: admin_login.php');
	exit();
}

// Only super admin can create users
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
	if ($_SESSION['role'] !== 'super_admin') {
		$error = 'Only super admins can create users.';
	} else {
		$username = trim($_POST['username'] ?? '');
		$email = trim($_POST['email'] ?? '');
		$password = $_POST['password'] ?? '';
		$role = $_POST['role'] ?? 'user';
		try {
			if ($username === '' || $email === '' || $password === '') { throw new Exception('All fields are required.'); }
			if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new Exception('Invalid email.'); }
			$hash = password_hash($password, PASSWORD_BCRYPT);
			$stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
			$stmt->execute([$username, $email, $hash, $role]);
			$success = 'User created successfully.';
		} catch (Throwable $e) {
			$error = 'Failed to create user: ' . $e->getMessage();
		}
	}
}

try {
	$users = $pdo->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC")->fetchAll();
} catch (PDOException $e) {
	$users = [];
	$error = 'Error loading users';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Manage Users</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
	<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
		<div class="container">
			<a class="navbar-brand" href="admin_dashboard.php">Admin Panel</a>
			<div class="navbar-nav ms-auto">
				<a href="admin_dashboard.php" class="nav-link">Dashboard</a>
				<a href="logout.php" class="nav-link">Logout</a>
			</div>
		</div>
	</nav>

	<div class="container my-4">
		<h2 class="mb-3">Users</h2>
		<?php if (!empty($success)): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
		<?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

		<?php if ($_SESSION['role'] === 'super_admin'): ?>
		<div class="card mb-4">
			<div class="card-header">Create New User</div>
			<div class="card-body">
				<form method="POST" action="">
					<input type="hidden" name="create_user" value="1">
					<div class="row g-3">
						<div class="col-md-4">
							<label class="form-label">Username</label>
							<input type="text" class="form-control" name="username" required>
						</div>
						<div class="col-md-4">
							<label class="form-label">Email</label>
							<input type="email" class="form-control" name="email" required>
						</div>
						<div class="col-md-4">
							<label class="form-label">Password</label>
							<input type="password" class="form-control" name="password" required>
						</div>
						<div class="col-md-4">
							<label class="form-label">Role</label>
							<select class="form-select" name="role">
								<option value="user">User</option>
								<option value="admin">Admin</option>
								<option value="super_admin">Super Admin</option>
							</select>
						</div>
						<div class="col-md-2 align-self-end">
							<button type="submit" class="btn btn-primary w-100">Create</button>
						</div>
					</div>
				</form>
			</div>
		</div>
		<?php endif; ?>
		<div class="table-responsive">
			<table class="table table-striped">
				<thead><tr><th>Username</th><th>Email</th><th>Role</th><th>Joined</th></tr></thead>
				<tbody>
					<?php foreach ($users as $u): ?>
					<tr>
						<td><?php echo htmlspecialchars($u['username']); ?></td>
						<td><?php echo htmlspecialchars($u['email']); ?></td>
						<td><?php echo htmlspecialchars($u['role']); ?></td>
						<td><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


