<?php
session_start();
require_once 'config.php';

// Allow only super_admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'super_admin')) {
	header('Location: admin_login.php');
	exit();
}

try {
	$adminCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('admin','super_admin')")->fetchColumn();
	$userCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
	$tournamentsCount = $pdo->query("SELECT COUNT(*) FROM tournaments")->fetchColumn();
	$matchesCount = $pdo->query("SELECT COUNT(*) FROM matches")->fetchColumn();
} catch (PDOException $e) {
	die('Error fetching stats: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Super Admin Dashboard</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<link href="https://fonts.googleapis.com/css2?family=Oxanium:wght@300;400;600;700;800&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
	<style>
		:root {
			--primary-color: #8c52ff;
			--secondary-color: #20c997;
			--accent-color: #ff3e85;
			--dark-bg: #121212;
			--darker-bg: #0a0a0a;
			--card-bg: rgba(30, 30, 30, 0.8);
			--text-primary: #ffffff;
			--text-secondary: #b0b0b0;
			--border-color: rgba(140, 82, 255, 0.3);
			--glow-color: rgba(140, 82, 255, 0.4);
		}
		
		* {
			margin: 0;
			padding: 0;
			box-sizing: border-box;
		}
		
		body {
			font-family: 'Roboto', sans-serif;
			background: linear-gradient(135deg, var(--dark-bg) 0%, var(--darker-bg) 100%);
			color: var(--text-primary);
			min-height: 100vh;
			position: relative;
			overflow-x: hidden;
		}
		
		.bg-grid {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background-image: 
				linear-gradient(rgba(140, 82, 255, 0.1) 1px, transparent 1px),
				linear-gradient(90deg, rgba(140, 82, 255, 0.1) 1px, transparent 1px);
			background-size: 50px 50px;
			z-index: -2;
			animation: gridMove 20s linear infinite;
		}
		
		@keyframes gridMove {
			0% { transform: translate(0, 0); }
			100% { transform: translate(50px, 50px); }
		}
		
		.floating-particles {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			pointer-events: none;
			z-index: -1;
		}
		
		.particle {
			position: absolute;
			background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
			border-radius: 50%;
			animation: float 15s infinite linear;
			opacity: 0.1;
		}
		
		@keyframes float {
			0% {
				transform: translateY(100vh) rotate(0deg);
				opacity: 0;
			}
			10% {
				opacity: 0.1;
			}
			90% {
				opacity: 0.1;
			}
			100% {
				transform: translateY(-100px) rotate(360deg);
				opacity: 0;
			}
		}
		
		.navbar {
			background: rgba(18, 18, 18, 0.95) !important;
			backdrop-filter: blur(20px);
			border-bottom: 1px solid var(--border-color);
			box-shadow: 0 4px 20px rgba(140, 82, 255, 0.1);
		}
		
		.navbar-brand {
			font-family: 'Oxanium', sans-serif;
			font-weight: 800;
			font-size: 1.5rem;
			color: var(--primary-color) !important;
			text-shadow: 0 0 20px var(--glow-color);
		}
		
		.btn-outline-light {
			border-color: var(--primary-color);
			color: var(--primary-color);
		}
		
		.btn-outline-light:hover {
			background-color: var(--primary-color);
			border-color: var(--primary-color);
			box-shadow: 0 0 20px var(--glow-color);
		}
		
		.sidebar {
			background: var(--card-bg) !important;
			backdrop-filter: blur(20px);
			border: 1px solid var(--border-color);
			border-radius: 20px;
			box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
			margin: 20px;
			height: fit-content;
		}
		
		.nav-link {
			color: var(--text-secondary) !important;
			font-weight: 500;
			transition: all 0.3s ease;
			border-radius: 10px;
			margin: 5px 10px;
			padding: 12px 15px;
		}
		
		.nav-link:hover, .nav-link.active {
			color: var(--primary-color) !important;
			background: rgba(140, 82, 255, 0.1);
			text-shadow: 0 0 10px var(--glow-color);
		}
		
		.card {
			background: var(--card-bg);
			backdrop-filter: blur(20px);
			border: 1px solid var(--border-color);
			border-radius: 20px;
			box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
			transition: all 0.3s ease;
			position: relative;
			overflow: hidden;
		}
		
		.card::before {
			content: '';
			position: absolute;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: linear-gradient(135deg, rgba(140, 82, 255, 0.1), rgba(255, 62, 133, 0.1));
			opacity: 0;
			transition: opacity 0.3s ease;
		}
		
		.card:hover {
			transform: translateY(-10px);
			box-shadow: 0 20px 40px rgba(140, 82, 255, 0.2);
		}
		
		.card:hover::before {
			opacity: 1;
		}
		
		.card-body, .card-header {
			position: relative;
			z-index: 1;
		}
		
		.card-header {
			background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
			border-bottom: 1px solid var(--border-color);
			border-radius: 20px 20px 0 0 !important;
		}
		
		.card-header h5 {
			font-family: 'Oxanium', sans-serif;
			font-weight: 700;
			margin: 0;
			color: white;
		}
		
		.bg-primary {
			background: linear-gradient(135deg, var(--primary-color), var(--accent-color)) !important;
		}
		
		.bg-success {
			background: linear-gradient(135deg, var(--secondary-color), #17a2b8) !important;
		}
		
		.bg-info {
			background: linear-gradient(135deg, #17a2b8, var(--primary-color)) !important;
		}
		
		.bg-warning {
			background: linear-gradient(135deg, #ffc107, var(--accent-color)) !important;
		}
		
		.btn {
			border-radius: 10px;
			font-weight: 500;
			transition: all 0.3s ease;
		}
		
		.btn-danger {
			background: linear-gradient(135deg, #dc3545, #c82333);
			border: none;
		}
		
		.btn-primary {
			background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
			border: none;
		}
		
		.btn-success {
			background: linear-gradient(135deg, var(--secondary-color), #17a2b8);
			border: none;
		}
		
		.btn-warning {
			background: linear-gradient(135deg, #ffc107, var(--accent-color));
			border: none;
			color: white;
		}
		
		.btn:hover {
			transform: translateY(-2px);
			box-shadow: 0 8px 25px rgba(140, 82, 255, 0.4);
		}
		
		h1, h2, h5 {
			font-family: 'Oxanium', sans-serif;
			font-weight: 700;
			color: var(--primary-color);
			text-shadow: 0 0 20px var(--glow-color);
		}
		
		.card-title {
			font-size: 2.5rem;
			font-weight: 800;
			margin-bottom: 0;
		}
		
		.border-bottom {
			border-color: var(--border-color) !important;
		}
		
		.navbar-text {
			color: var(--text-secondary) !important;
		}
		
		@media (max-width: 768px) {
			.sidebar {
				margin: 10px;
			}
		}
	</style>
</head>
<body>
	<div class="bg-grid"></div>
	<div class="floating-particles" id="particles"></div>
	
	<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
		<div class="container">
			<a class="navbar-brand" href="#">Esports Platform Super Admin</a>
			<div class="navbar-nav ms-auto">
				<span class="navbar-text me-3">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
				<a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
			</div>
		</div>
	</nav>

	<div class="container-fluid mt-4">
		<div class="row">
			<div class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
				<div class="position-sticky pt-3">
					<ul class="nav flex-column">
						<li class="nav-item">
							<a class="nav-link active" href="#"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
						</li>
						<li class="nav-item"><a class="nav-link" href="manage_admins.php"><i class="bi bi-shield-lock me-2"></i>Manage Admins</a></li>
						<li class="nav-item"><a class="nav-link" href="system_settings.php"><i class="bi bi-gear me-2"></i>System Settings</a></li>
						<li class="nav-item"><a class="nav-link" href="matches.php"><i class="bi bi-play-circle me-2"></i>Watch Matches</a></li>
						<li class="nav-item"><a class="nav-link" href="admin_dashboard.php"><i class="bi bi-layout-text-sidebar me-2"></i>Admin Dashboard</a></li>
					</ul>
				</div>
			</div>
			<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
				<div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
					<h1 class="h2">Super Admin Overview</h1>
				</div>
				<div class="row">
					<div class="col-md-3">
						<div class="card text-white bg-primary mb-3"><div class="card-body"><h5 class="card-title"><?php echo $adminCount; ?></h5><p class="card-text">Admins</p></div></div>
					</div>
					<div class="col-md-3">
						<div class="card text-white bg-success mb-3"><div class="card-body"><h5 class="card-title"><?php echo $userCount; ?></h5><p class="card-text">Users</p></div></div>
					</div>
					<div class="col-md-3">
						<div class="card text-white bg-info mb-3"><div class="card-body"><h5 class="card-title"><?php echo $tournamentsCount; ?></h5><p class="card-text">Tournaments</p></div></div>
					</div>
					<div class="col-md-3">
						<div class="card text-white bg-warning mb-3"><div class="card-body"><h5 class="card-title"><?php echo $matchesCount; ?></h5><p class="card-text">Matches</p></div></div>
					</div>
				</div>

				<!-- Quick Actions -->
				<div class="row mt-4">
					<div class="col-12">
						<div class="card">
							<div class="card-header">
								<h5><i class="bi bi-lightning me-2"></i>Quick Actions</h5>
							</div>
							<div class="card-body">
								<div class="row">
									<div class="col-md-3 mb-3">
										<a href="add_live_match.php" class="btn btn-danger w-100">
											<i class="bi bi-broadcast me-2"></i>Add Live Stream
										</a>
									</div>
									<div class="col-md-3 mb-3">
										<a href="manage_admins.php" class="btn btn-primary w-100">
											<i class="bi bi-shield-lock me-2"></i>Manage Admins
										</a>
									</div>
									<div class="col-md-3 mb-3">
										<a href="admin_dashboard.php" class="btn btn-success w-100">
											<i class="bi bi-layout-text-sidebar me-2"></i>Admin Dashboard
										</a>
									</div>
									<div class="col-md-3 mb-3">
										<a href="system_settings.php" class="btn btn-warning w-100">
											<i class="bi bi-gear me-2"></i>System Settings
										</a>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</main>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
	<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
	<script>
		$(document).ready(function() {
			// Create particles
			createParticles();
			
			// Create particles function
			function createParticles() {
				const particlesContainer = $('#particles');
				const particleCount = 15;
				
				for (let i = 0; i < particleCount; i++) {
					const size = Math.random() * 20 + 10;
					const posX = Math.random() * 100;
					const posY = Math.random() * 100;
					const animationDelay = Math.random() * 15;
					const opacity = Math.random() * 0.2 + 0.1;
					
					const particle = $('<div class="particle"></div>').css({
						width: size + 'px',
						height: size + 'px',
						left: posX + 'vw',
						top: posY + 'vh',
						opacity: opacity,
						animationDelay: animationDelay + 's'
					});
					
					particlesContainer.append(particle);
				}
			}
		});
	</script>
</body>
</html>


