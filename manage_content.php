<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
	header('Location: admin_login.php');
	exit();
}

$success = '';
$error = '';

// Load notices only
try {
	$notices = $pdo->query("\n\t\tSELECT n.id, n.title, n.status, n.priority, n.created_at, n.updated_at, u.username AS author_name\n\t\tFROM notices n\n\t\tJOIN users u ON n.author_id = u.id\n\t\tORDER BY n.created_at DESC\n\t")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
	$error = 'Failed to load notices: ' . $e->getMessage();
	$notices = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Manage Content</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
		<h2 class="mb-3">Manage Notices</h2>
		<?php if (!empty($success)): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
		<?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

		<div class="d-flex justify-content-end mb-2">
			<a href="manage_notices.php" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i> Create Notice</a>
		</div>
		<div class="table-responsive">
			<table class="table table-striped align-middle">
				<thead>
					<tr>
						<th>Title</th>
						<th>Status</th>
						<th>Priority</th>
						<th>Author</th>
						<th>Created</th>
						<th>Updated</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if (!empty($notices)): ?>
						<?php foreach ($notices as $n): ?>
						<tr>
							<td><?php echo htmlspecialchars($n['title']); ?></td>
							<td><span class="badge bg-<?php echo ($n['status'] ?? 'draft') === 'published' ? 'success' : 'warning'; ?>"><?php echo ucfirst($n['status'] ?? 'draft'); ?></span></td>
							<td><span class="badge bg-<?php $p=$n['priority'] ?? 'normal'; echo $p==='high'?'danger':($p==='normal'?'info':'success'); ?>"><?php echo ucfirst($n['priority'] ?? 'normal'); ?></span></td>
							<td><?php echo htmlspecialchars($n['author_name']); ?></td>
							<td><?php echo htmlspecialchars(date('M j, Y', strtotime($n['created_at']))); ?></td>
							<td><?php echo htmlspecialchars(isset($n['updated_at']) ? date('M j, Y', strtotime($n['updated_at'])) : '-'); ?></td>
							<td class="text-end">
								<a href="notice.php?id=<?php echo $n['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
								<a href="manage_notices.php" class="btn btn-sm btn-outline-secondary">Manage</a>
							</td>
						</tr>
						<?php endforeach; ?>
					<?php else: ?>
					<tr><td colspan="7" class="text-center text-muted py-4">No notices found.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


