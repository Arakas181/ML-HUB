<?php
session_start();
require_once 'config.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : 'user';
$return = isset($_GET['return']) ? $_GET['return'] : '';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
	header('Location: matches.php');
	exit();
}

try {
	$stmt = $pdo->prepare("SELECT m.*, t1.name as team1_name, t2.name as team2_name, tr.name as tournament_name
		FROM matches m
		JOIN teams t1 ON m.team1_id = t1.id
		JOIN teams t2 ON m.team2_id = t2.id
		JOIN tournaments tr ON m.tournament_id = tr.id
		WHERE m.id = ?");
	$stmt->execute([$id]);
	$match = $stmt->fetch();

	if (!$match) {
		header('Location: matches.php');
		exit();
	}
} catch (PDOException $e) {
	error_log('Match details error: ' . $e->getMessage());
	$match = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Match: <?php echo htmlspecialchars($match['team1_name'] . ' vs ' . $match['team2_name']); ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
	<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
		<div class="container">
			<a class="navbar-brand" href="index.php">ML HUB</a>
			<div class="navbar-nav ms-auto">
				<?php if ($return === 'manage_matches' && $isLoggedIn && ($userRole === 'admin' || $userRole === 'super_admin')): ?>
					<a href="manage_matches.php" class="nav-link">Back to Manage Matches</a>
				<?php else: ?>
					<a href="matches.php" class="nav-link">Back to Matches</a>
				<?php endif; ?>
			</div>
		</div>
	</nav>

	<div class="container my-4">
		<h3 class="mb-1"><?php echo htmlspecialchars($match['team1_name']); ?> vs <?php echo htmlspecialchars($match['team2_name']); ?></h3>
		<p class="text-muted mb-4">Tournament: <?php echo htmlspecialchars($match['tournament_name']); ?> · <?php echo htmlspecialchars(ucfirst($match['status'])); ?></p>

		<div class="row mb-4">
			<div class="col-md-4">
				<div class="card">
					<div class="card-body text-center">
						<h1><?php echo (int)$match['score_team1']; ?> - <?php echo (int)$match['score_team2']; ?></h1>
						<div class="text-muted">Score</div>
					</div>
				</div>
			</div>
			<div class="col-md-8">
				<ul class="list-group">
					<li class="list-group-item"><strong>Round:</strong> <?php echo htmlspecialchars($match['round']); ?></li>
					<li class="list-group-item"><strong>Scheduled Time:</strong> <?php echo date('M j, Y g:i A', strtotime($match['scheduled_time'])); ?></li>
					<li class="list-group-item"><strong>Status:</strong> <?php echo htmlspecialchars(ucfirst($match['status'])); ?></li>
				</ul>
				<div class="mt-3">
					<?php if ($match['status'] === 'live'): ?>
						<a href="matches.php" class="btn btn-danger"><i class="fas fa-play me-1"></i> Watch Live</a>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<a href="tournament_details.php?id=<?php echo $match['tournament_id']; ?>" class="btn btn-outline-primary">
			<i class="fas fa-info-circle me-1"></i> View Tournament
		</a>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


