<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
	header('Location: admin_login.php');
	exit();
}

// Ensure video_url column exists
$hasVideoUrlColumn = false;
try {
	$pdo->query("SELECT video_url FROM matches LIMIT 1");
	$hasVideoUrlColumn = true;
} catch (PDOException $e) {
	try {
		$pdo->exec("ALTER TABLE matches ADD COLUMN video_url VARCHAR(500) NULL AFTER round");
		$hasVideoUrlColumn = true;
	} catch (PDOException $e2) {
		error_log('Failed to add video_url column: ' . $e2->getMessage());
	}
}

// Handle create match
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_match'])) {
	$tournament_id = (int)($_POST['tournament_id'] ?? 0);
	$team1_id = (int)($_POST['team1_id'] ?? 0);
	$team2_id = (int)($_POST['team2_id'] ?? 0);
	$scheduled_time = $_POST['scheduled_time'] ?? '';
	$status = $_POST['status'] ?? 'scheduled';
	$round = trim($_POST['round'] ?? '');
	$video_url = trim($_POST['video_url'] ?? '');

	try {
		if ($tournament_id <= 0 || $team1_id <= 0 || $team2_id <= 0 || $scheduled_time === '' || $team1_id === $team2_id) {
			throw new Exception('All fields are required and teams must be different.');
		}
		
		if ($hasVideoUrlColumn) {
			$stmt = $pdo->prepare("INSERT INTO matches (tournament_id, team1_id, team2_id, scheduled_time, status, round, video_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
			$stmt->execute([$tournament_id, $team1_id, $team2_id, $scheduled_time, $status, $round, $video_url]);
		} else {
			$stmt = $pdo->prepare("INSERT INTO matches (tournament_id, team1_id, team2_id, scheduled_time, status, round) VALUES (?, ?, ?, ?, ?, ?)");
			$stmt->execute([$tournament_id, $team1_id, $team2_id, $scheduled_time, $status, $round]);
		}
		$success = 'Match created successfully.';
	} catch (Throwable $e) {
		$error = 'Failed to create match: ' . $e->getMessage();
	}
}

try {
	$matches = $pdo->query("SELECT m.id, m.status, m.scheduled_time, m.video_url, t1.name as team1, t2.name as team2
		FROM matches m
		JOIN teams t1 ON m.team1_id = t1.id
		JOIN teams t2 ON m.team2_id = t2.id
		ORDER BY m.scheduled_time DESC")->fetchAll();
    $teams = $pdo->query("SELECT id, name FROM teams ORDER BY name")->fetchAll();
    $tournaments = $pdo->query("SELECT id, name FROM tournaments ORDER BY name")->fetchAll();
} catch (PDOException $e) {
	$matches = [];
	if ($error === '') { $error = 'Error loading matches'; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Manage Matches - Admin Dashboard</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<link href="https://fonts.googleapis.com/css2?family=Oxanium:wght@400;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
	<style>
		:root {
			--primary-color: #8c52ff;
			--primary-gradient: linear-gradient(135deg, #8c52ff 0%, #5ce1e6 100%);
			--secondary-color: #20c997;
			--accent-color: #ff3e85;
			--dark-color: #121212;
			--darker-color: #0a0a0a;
			--light-color: #f8f9fa;
			--card-bg: rgba(25, 25, 35, 0.95);
		}
		
		body {
			font-family: 'Roboto', sans-serif;
			background: linear-gradient(135deg, var(--darker-color) 0%, #1a1a2e 100%);
			color: white;
			min-height: 100vh;
		}
		
		.navbar {
			background: var(--card-bg) !important;
			backdrop-filter: blur(10px);
			box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
		}
		
		.navbar-brand {
			font-family: 'Oxanium', cursive;
			font-weight: 700;
			color: #5ce1e6 !important;
			font-size: 1.5rem;
		}
		
		.card {
			background: var(--card-bg);
			border: 1px solid rgba(255, 255, 255, 0.1);
			border-radius: 15px;
			box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
			backdrop-filter: blur(10px);
			color: white;
		}
		
		.card-header {
			background: var(--primary-gradient);
			border-bottom: 1px solid rgba(255, 255, 255, 0.1);
			border-radius: 15px 15px 0 0 !important;
			font-family: 'Oxanium', cursive;
			font-weight: 600;
		}
		
		.admin-header {
			background: var(--primary-gradient);
			color: white;
			padding: 40px 0;
			border-radius: 15px;
			margin-bottom: 30px;
			text-align: center;
			box-shadow: 0 10px 30px rgba(140, 82, 255, 0.3);
		}
		
		.admin-header h2 {
			font-family: 'Oxanium', cursive;
			font-weight: 700;
			margin-bottom: 10px;
		}
		
		.btn-primary {
			background: var(--primary-gradient);
			border: none;
			border-radius: 10px;
			padding: 12px 20px;
			font-weight: 600;
			transition: all 0.3s;
		}
		
		.btn-primary:hover {
			background: linear-gradient(135deg, #7a45e0 0%, #4ec1c6 100%);
			transform: translateY(-2px);
			box-shadow: 0 10px 20px rgba(140, 82, 255, 0.3);
		}
		
		.form-control, .form-select {
			background: rgba(255, 255, 255, 0.1);
			border: 1px solid rgba(255, 255, 255, 0.2);
			color: white;
			border-radius: 8px;
		}
		
		.form-control:focus, .form-select:focus {
			background: rgba(255, 255, 255, 0.15);
			border-color: var(--primary-color);
			box-shadow: 0 0 0 0.2rem rgba(140, 82, 255, 0.25);
			color: white;
		}
		
		.form-control::placeholder {
			color: rgba(255, 255, 255, 0.6);
		}
		
		.form-label {
			color: rgba(255, 255, 255, 0.9);
			font-weight: 500;
		}
		
		.table {
			color: white;
		}
		
		.table-responsive {
			background: var(--card-bg);
			border-radius: 15px;
			padding: 20px;
			box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
			backdrop-filter: blur(10px);
			border: 1px solid rgba(255, 255, 255, 0.1);
		}
		
		.table thead th {
			background: var(--primary-gradient);
			border: none;
			color: white;
			font-weight: 600;
			padding: 15px;
		}
		
		.table tbody td {
			border-color: rgba(255, 255, 255, 0.1);
			padding: 15px;
			vertical-align: middle;
		}
		
		.table-striped > tbody > tr:nth-of-type(odd) > td {
			background: rgba(255, 255, 255, 0.05);
		}
		
		.alert {
			border-radius: 10px;
			border: none;
		}
		
		.alert-success {
			background: rgba(32, 201, 151, 0.2);
			color: #20c997;
			border: 1px solid rgba(32, 201, 151, 0.3);
		}
		
		.alert-danger {
			background: rgba(255, 62, 133, 0.2);
			color: #ff3e85;
			border: 1px solid rgba(255, 62, 133, 0.3);
		}
		
		.btn-outline-primary {
			border-color: var(--primary-color);
			color: var(--primary-color);
		}
		
		.btn-outline-primary:hover {
			background: var(--primary-color);
			border-color: var(--primary-color);
		}
		
		.btn-danger {
			background: linear-gradient(135deg, #ff3e85 0%, #ff6b9d 100%);
			border: none;
		}
		
		.btn-danger:hover {
			background: linear-gradient(135deg, #e6356b 0%, #e55a8a 100%);
		}
		
		/* Mobile Responsive Styles */
		@media (max-width: 768px) {
			.container {
				padding: 0 15px;
			}
			
			.admin-header {
				padding: 30px 20px;
				margin: 15px 0;
			}
			
			.admin-header h2 {
				font-size: 1.8rem;
			}
			
			.card-body {
				padding: 15px;
			}
			
			.btn {
				font-size: 0.9rem;
			}
			
			.table-responsive {
				padding: 15px;
			}
			
			.table thead th, .table tbody td {
				padding: 10px 8px;
				font-size: 0.9rem;
			}
		}
		
		@media (max-width: 576px) {
			.admin-header h2 {
				font-size: 1.5rem;
			}
			
			.btn {
				font-size: 0.85rem;
				padding: 8px 16px;
			}
			
			.card-body {
				padding: 12px;
			}
			
			.table thead th, .table tbody td {
				padding: 8px 6px;
				font-size: 0.85rem;
			}
			
			.table-responsive {
				padding: 10px;
			}
		}
	</style>
</head>
<body>
	<nav class="navbar navbar-expand-lg navbar-dark">
		<div class="container">
			<a class="navbar-brand" href="admin_dashboard.php">
				<i class="fas fa-shield-alt me-2"></i>Admin Panel
			</a>
			<div class="navbar-nav ms-auto">
				<a href="admin_dashboard.php" class="nav-link">
					<i class="fas fa-tachometer-alt me-1"></i>Dashboard
				</a>
				<a href="logout.php" class="nav-link">
					<i class="fas fa-sign-out-alt me-1"></i>Logout
				</a>
			</div>
		</div>
	</nav>

	<div class="container my-4">
		<!-- Admin Header -->
		<div class="admin-header">
			<h2><i class="fas fa-gamepad me-2"></i>Manage Matches</h2>
			<p class="mb-0">Create and manage tournament matches and competitions</p>
		</div>
		
		<!-- Messages -->
		<?php if (!empty($success)): ?>
			<div class="alert alert-success alert-dismissible fade show" role="alert">
				<i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
				<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
			</div>
		<?php endif; ?>
		<?php if (!empty($error)): ?>
			<div class="alert alert-danger alert-dismissible fade show" role="alert">
				<i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
				<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
			</div>
		<?php endif; ?>

		<div class="card mb-4">
			<div class="card-header">
				<h5 class="mb-0"><i class="fas fa-plus me-2"></i>Create New Match</h5>
			</div>
			<div class="card-body">
				<form method="POST" action="">
					<input type="hidden" name="create_match" value="1">
					<div class="row g-3">
						<div class="col-md-4">
							<label class="form-label">Tournament</label>
							<select class="form-select" name="tournament_id" required>
								<option value="">Select tournament</option>
								<?php foreach ($tournaments as $t): ?>
								<option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-md-3">
							<label class="form-label">Team 1</label>
							<select class="form-select" name="team1_id" required>
								<option value="">Select team</option>
								<?php foreach ($teams as $team): ?>
								<option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['name']); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-md-3">
							<label class="form-label">Team 2</label>
							<select class="form-select" name="team2_id" required>
								<option value="">Select team</option>
								<?php foreach ($teams as $team): ?>
								<option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['name']); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-md-2">
							<label class="form-label">Round</label>
							<input type="text" class="form-control" name="round" placeholder="Quarter Finals">
						</div>
						<div class="col-md-4">
							<label class="form-label">Scheduled Time</label>
							<input type="datetime-local" class="form-control" name="scheduled_time" required>
						</div>
						<div class="col-md-2">
							<label class="form-label">Status</label>
							<select class="form-select" name="status">
								<option value="scheduled">Scheduled</option>
								<option value="live">Live</option>
								<option value="completed">Completed</option>
								<option value="cancelled">Cancelled</option>
							</select>
						</div>
						<div class="col-md-6">
							<label class="form-label">
								<i class="fab fa-youtube me-1"></i>YouTube Video URL
							</label>
							<input type="url" class="form-control" name="video_url" placeholder="https://www.youtube.com/watch?v=...">
							<div class="form-text">Optional: Add YouTube video link for live/recorded matches</div>
						</div>
						<div class="col-md-2 align-self-end">
							<button type="submit" class="btn btn-primary w-100">
								<i class="fas fa-plus me-1"></i>Create Match
							</button>
						</div>
					</div>
				</form>
			</div>
		</div>
		<div class="table-responsive">
			<h5 class="mb-3"><i class="fas fa-list me-2"></i>All Matches</h5>
			<table class="table table-striped">
				<thead>
					<tr>
						<th><i class="fas fa-users me-1"></i>Teams</th>
						<th><i class="fas fa-info-circle me-1"></i>Status</th>
						<th><i class="fas fa-calendar me-1"></i>Scheduled</th>
						<th><i class="fas fa-video me-1"></i>Video</th>
						<th><i class="fas fa-cog me-1"></i>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($matches as $m): ?>
					<tr>
						<td><?php echo htmlspecialchars($m['team1'] . ' vs ' . $m['team2']); ?></td>
						<td><?php echo htmlspecialchars(ucfirst($m['status'])); ?></td>
						<td><?php echo date('M j, Y g:i A', strtotime($m['scheduled_time'])); ?></td>
						<td>
							<?php if (!empty($m['video_url'])): ?>
								<a href="watch.php?id=<?php echo $m['id']; ?>" class="btn btn-sm btn-danger">
									<i class="fab fa-youtube me-1"></i>Watch
								</a>
							<?php else: ?>
								<span class="text-muted">No video</span>
							<?php endif; ?>
						</td>
						<td class="text-end">
							<a class="btn btn-sm btn-outline-primary" href="match_details.php?id=<?php echo $m['id']; ?>&return=manage_matches">
								<i class="fas fa-eye me-1"></i>View Details
							</a>
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


