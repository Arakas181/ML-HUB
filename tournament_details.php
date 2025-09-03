<?php
session_start();
require_once 'config.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
	header('Location: tournaments.php');
	exit();
}

try {
	$stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
	$stmt->execute([$id]);
	$tournament = $stmt->fetch();

	if (!$tournament) {
		header('Location: tournaments.php');
		exit();
	}

	$matchesStmt = $pdo->prepare("SELECT m.*, t1.name as team1_name, t2.name as team2_name
		FROM matches m
		JOIN teams t1 ON m.team1_id = t1.id
		JOIN teams t2 ON m.team2_id = t2.id
		WHERE m.tournament_id = ?
		ORDER BY m.scheduled_time ASC");
	$matchesStmt->execute([$id]);
	$matches = $matchesStmt->fetchAll();
} catch (PDOException $e) {
	error_log('Tournament details error: ' . $e->getMessage());
	$tournament = null;
	$matches = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo htmlspecialchars($tournament['name'] ?? 'Tournament'); ?> - Details</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
	<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
		<div class="container">
			<a class="navbar-brand" href="index.php">ML HUB</a>
			<div class="navbar-nav ms-auto">
				<a href="tournaments.php" class="nav-link">Back to Tournaments</a>
			</div>
		</div>
	</nav>

	<div class="container my-4">
		<h2 class="mb-3"><?php echo htmlspecialchars($tournament['name']); ?></h2>
		<p class="text-muted"><?php echo nl2br(htmlspecialchars($tournament['description'])); ?></p>
		<div class="row mb-4">
			<div class="col-md-3"><strong>Status:</strong> <?php echo htmlspecialchars($tournament['status']); ?></div>
			<div class="col-md-3"><strong>Start:</strong> <?php echo date('M j, Y', strtotime($tournament['start_date'])); ?></div>
			<div class="col-md-3"><strong>End:</strong> <?php echo date('M j, Y', strtotime($tournament['end_date'])); ?></div>
			<div class="col-md-3"><strong>Prize Pool:</strong> $<?php echo number_format((float)($tournament['prize_pool'] ?? 0), 2); ?></div>
		</div>

		<h4 class="mb-3">Matches</h4>
		<?php if ($matches): ?>
			<div class="list-group">
				<?php foreach ($matches as $match): ?>
					<a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="match_details.php?id=<?php echo $match['id']; ?>">
						<span>
							<i class="fas fa-gamepad me-2"></i>
							<?php echo htmlspecialchars($match['team1_name']); ?> vs <?php echo htmlspecialchars($match['team2_name']); ?>
						</span>
						<small><?php echo date('M j, Y g:i A', strtotime($match['scheduled_time'])); ?></small>
					</a>
				<?php endforeach; ?>
			</div>
		<?php else: ?>
			<p class="text-muted">No matches scheduled for this tournament yet.</p>
		<?php endif; ?>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


