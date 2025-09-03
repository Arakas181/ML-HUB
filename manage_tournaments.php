<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
	header('Location: admin_login.php');
	exit();
}

// Ensure 'max_participants' column exists (graceful runtime migration)
$hasMaxParticipantsColumn = false;
try {
	$pdo->query("SELECT max_participants FROM tournaments LIMIT 1");
	$hasMaxParticipantsColumn = true;
} catch (PDOException $e) {
	try {
		$pdo->exec("ALTER TABLE tournaments ADD COLUMN max_participants INT NULL AFTER prize_pool");
		$hasMaxParticipantsColumn = true;
	} catch (PDOException $e2) {
		// Log and continue without the column
		error_log('Failed to add max_participants column: ' . $e2->getMessage());
		$hasMaxParticipantsColumn = false;
	}
}

// Handle create tournament
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_tournament'])) {
	$name = trim($_POST['name'] ?? '');
	$description = trim($_POST['description'] ?? '');
	$start_date = $_POST['start_date'] ?? '';
	$end_date = $_POST['end_date'] ?? '';
	$status = $_POST['status'] ?? 'upcoming';
	$prize_pool = $_POST['prize_pool'] !== '' ? (float)$_POST['prize_pool'] : null;
	$max_participants = $_POST['max_participants'] !== '' ? (int)$_POST['max_participants'] : null;

	try {
		if ($name === '' || $start_date === '' || $end_date === '') {
			throw new Exception('Name, start date, and end date are required.');
		}
		if ($hasMaxParticipantsColumn) {
			$stmt = $pdo->prepare("INSERT INTO tournaments (name, description, start_date, end_date, status, prize_pool, max_participants) VALUES (?, ?, ?, ?, ?, ?, ?)");
			$stmt->execute([$name, $description, $start_date, $end_date, $status, $prize_pool, $max_participants]);
		} else {
			$stmt = $pdo->prepare("INSERT INTO tournaments (name, description, start_date, end_date, status, prize_pool) VALUES (?, ?, ?, ?, ?, ?)");
			$stmt->execute([$name, $description, $start_date, $end_date, $status, $prize_pool]);
		}
		$success = 'Tournament created successfully.';
	} catch (Throwable $e) {
		$error = 'Failed to create tournament: ' . $e->getMessage();
	}
}

try {
	$tournaments = $pdo->query("SELECT id, name, status, start_date, end_date FROM tournaments ORDER BY start_date DESC")->fetchAll();
} catch (PDOException $e) {
	$tournaments = [];
	if ($error === '') { $error = 'Error loading tournaments'; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Manage Tournaments</title>
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
		<h2 class="mb-3">Tournaments</h2>
		<?php if (!empty($success)): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
		<?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

		<div class="card mb-4">
			<div class="card-header">Create New Tournament</div>
			<div class="card-body">
				<form method="POST" action="">
					<input type="hidden" name="create_tournament" value="1">
					<div class="row g-3">
						<div class="col-md-6">
							<label class="form-label">Name</label>
							<input type="text" class="form-control" name="name" required>
						</div>
						<div class="col-md-3">
							<label class="form-label">Start Date</label>
							<input type="date" class="form-control" name="start_date" required>
						</div>
						<div class="col-md-3">
							<label class="form-label">End Date</label>
							<input type="date" class="form-control" name="end_date" required>
						</div>
						<div class="col-md-12">
							<label class="form-label">Description</label>
							<textarea class="form-control" name="description" rows="3"></textarea>
						</div>
						<div class="col-md-3">
							<label class="form-label">Status</label>
							<select class="form-select" name="status">
								<option value="upcoming">Upcoming</option>
								<option value="ongoing">Ongoing</option>
								<option value="completed">Completed</option>
								<option value="cancelled">Cancelled</option>
							</select>
						</div>
						<div class="col-md-3">
							<label class="form-label">Prize Pool (USD)</label>
							<input type="number" step="0.01" min="0" class="form-control" name="prize_pool">
						</div>
						<div class="col-md-3">
							<label class="form-label">Max Participants</label>
							<input type="number" min="2" class="form-control" name="max_participants">
						</div>

						<div class="col-md-3 align-self-end">
							<button type="submit" class="btn btn-primary w-100">Create</button>
						</div>
					</div>
				</form>
			</div>
		</div>
		<div class="table-responsive">
			<table class="table table-striped">
				<thead><tr><th>Name</th><th>Status</th><th>Start</th><th>End</th><th></th></tr></thead>
				<tbody>
					<?php foreach ($tournaments as $t): ?>
					<tr>
						<td><?php echo htmlspecialchars($t['name']); ?></td>
						<td><?php echo htmlspecialchars($t['status']); ?></td>
						<td><?php echo date('M j, Y', strtotime($t['start_date'])); ?></td>
						<td><?php echo date('M j, Y', strtotime($t['end_date'])); ?></td>
						<td class="text-end">
							<a class="btn btn-sm btn-outline-primary" href="tournament_details.php?id=<?php echo $t['id']; ?>">View</a>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


