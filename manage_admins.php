<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'super_admin')) {
	header('Location: admin_login.php');
	exit();
}

try {
	$admins = $pdo->query("SELECT id, username, email, role, created_at FROM users WHERE role IN ('admin','super_admin') ORDER BY created_at DESC")->fetchAll();
} catch (PDOException $e) {
	$admins = [];
	$error = 'Error loading admins';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Manage Admins</title>
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
		
		.nav-link {
			color: var(--text-secondary) !important;
			font-weight: 500;
			transition: all 0.3s ease;
		}
		
		.nav-link:hover {
			color: var(--primary-color) !important;
			text-shadow: 0 0 10px var(--glow-color);
		}
		
		h2 {
			font-family: 'Oxanium', sans-serif;
			font-weight: 700;
			color: var(--primary-color);
			text-shadow: 0 0 20px var(--glow-color);
			margin-bottom: 30px;
		}
		
		.table-responsive {
			background: var(--card-bg);
			backdrop-filter: blur(20px);
			border: 1px solid var(--border-color);
			border-radius: 20px;
			box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
			padding: 20px;
		}
		
		.table {
			color: var(--text-primary);
			margin-bottom: 0;
		}
		
		.table th {
			color: var(--primary-color);
			border-color: var(--border-color);
			font-weight: 600;
			font-family: 'Oxanium', sans-serif;
			text-shadow: 0 0 10px var(--glow-color);
		}
		
		.table td {
			border-color: var(--border-color);
			vertical-align: middle;
		}
		
		.table-striped tbody tr:nth-of-type(odd) {
			background-color: rgba(140, 82, 255, 0.05);
		}
		
		.table tbody tr:hover {
			background-color: rgba(140, 82, 255, 0.1);
		}
		
		.alert {
			background: var(--card-bg);
			border: 1px solid var(--border-color);
			color: var(--text-primary);
			border-radius: 15px;
		}
		
		.alert-danger {
			border-color: rgba(220, 53, 69, 0.3);
			background: rgba(220, 53, 69, 0.1);
		}
		
		.badge {
			font-weight: 600;
			padding: 8px 12px;
			border-radius: 20px;
		}
		
		.badge-super-admin {
			background: linear-gradient(135deg, var(--accent-color), var(--primary-color));
			color: white;
		}
		
		.badge-admin {
			background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
			color: white;
		}
	</style>
</head>
<body>
	<div class="bg-grid"></div>
	<div class="floating-particles" id="particles"></div>
	
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
		<h2 class="mb-4"><i class="fas fa-shield-alt me-2"></i>Admin Management</h2>
		<?php if (!empty($error)): ?><div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
		<div class="table-responsive">
			<table class="table table-striped">
				<thead>
					<tr>
						<th><i class="fas fa-user me-2"></i>Username</th>
						<th><i class="fas fa-envelope me-2"></i>Email</th>
						<th><i class="fas fa-crown me-2"></i>Role</th>
						<th><i class="fas fa-calendar me-2"></i>Joined</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($admins as $a): ?>
					<tr>
						<td><strong><?php echo htmlspecialchars($a['username']); ?></strong></td>
						<td><?php echo htmlspecialchars($a['email']); ?></td>
						<td>
							<?php if ($a['role'] === 'super_admin'): ?>
								<span class="badge badge-super-admin"><i class="fas fa-crown me-1"></i>Super Admin</span>
							<?php else: ?>
								<span class="badge badge-admin"><i class="fas fa-shield me-1"></i>Admin</span>
							<?php endif; ?>
						</td>
						<td><?php echo date('M j, Y', strtotime($a['created_at'])); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
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


