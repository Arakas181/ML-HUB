<?php
session_start();
require_once 'config.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : 'user';
$username = $isLoggedIn ? $_SESSION['username'] : '';

$matchId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($matchId <= 0) {
	header('Location: matches.php');
	exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT m.*, t1.name as team1_name, t2.name as team2_name, tr.name as tournament_name
		FROM matches m
		JOIN teams t1 ON m.team1_id = t1.id
		JOIN teams t2 ON m.team2_id = t2.id
		JOIN tournaments tr ON m.tournament_id = tr.id
        WHERE m.id = ?
    ");
    $stmt->execute([$matchId]);
	$match = $stmt->fetch();

	if (!$match) {
		header('Location: matches.php');
		exit();
	}

    // Convert YouTube URL to embed URL
    function getYouTubeEmbedUrl($url) {
        if (empty($url)) return null;
        
        // Extract video ID from various YouTube URL formats
        $patterns = [
            '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/',
            '/youtu\.be\/([a-zA-Z0-9_-]+)/',
            '/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return 'https://www.youtube.com/embed/' . $matches[1];
            }
        }
        
        return null;
    }

    $embedUrl = getYouTubeEmbedUrl($match['video_url'] ?? '');

    // Determine a safe back URL (fallback to matches.php)
    $backUrl = 'matches.php';
    if (!empty($_SERVER['HTTP_REFERER'])) {
        $referer = $_SERVER['HTTP_REFERER'];
        // Basic safety: only allow same host referrers
        $parsedRef = parse_url($referer);
        if (!empty($parsedRef['host']) && (!isset($_SERVER['HTTP_HOST']) || strcasecmp($parsedRef['host'], $_SERVER['HTTP_HOST']) === 0)) {
            $backUrl = $referer;
        }
    }


} catch (PDOException $e) {
    error_log('Watch page error: ' . $e->getMessage());
    header('Location: matches.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Watch: <?php echo htmlspecialchars($match['team1_name'] . ' vs ' . $match['team2_name']); ?> - ML HUB</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<style>
        :root {
            --primary-color: #6f42c1;
            --secondary-color: #20c997;
            --dark-color: #212529;
            --light-color: #f8f9fa;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
        }
        
        .navbar {
            background-color: var(--dark-color);
        }
        
        .navbar-brand {
            font-weight: bold;
            color: var(--secondary-color) !important;
        }
        
        .video-container {
            position: relative;
            width: 100%;
            background: #000;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .video-wrapper {
            position: relative;
            padding-bottom: 56.25%; /* 16:9 Aspect Ratio */
            height: 0;
            overflow: hidden;
        }
        
        .video-wrapper iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 0;
        }
        
        .match-info {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .team-logo {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid var(--primary-color);
        }
        
        .score-display {
            font-size: 3rem;
            font-weight: bold;
            color: var(--primary-color);
            text-align: center;
        }
        
        .status-badge {
            font-size: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 25px;
        }
        
        .live-indicator {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .chat-container {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-top: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            height: 400px;
            overflow-y: auto;
        }
        
        .chat-message {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 0.75rem 1rem;
            margin-bottom: 0.75rem;
            border-left: 4px solid var(--primary-color);
        }
        
        .chat-message.self {
            background: #e3f2fd;
            border-left-color: var(--secondary-color);
            margin-left: 2rem;
        }
        
        .no-video {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4rem 2rem;
            text-align: center;
            border-radius: 15px;
        }
        
        .no-video i {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
	</style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
		<div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-gamepad me-2"></i>ML HUB
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="matches.php">Matches</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="tournaments.php">Tournaments</a>
                    </li>
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
                                <?php elseif ($userRole === 'user'): ?>
                                    <li><a class="dropdown-item" href="user_dashboard.php">User Dashboard</a></li>
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

    <div class="container mt-4">
        <div class="row">
            <!-- Video Player -->
            <div class="col-lg-8">
                <div class="video-container">
                    <?php if ($embedUrl): ?>
                        <div class="video-wrapper">
                            <iframe src="<?php echo htmlspecialchars($embedUrl); ?>?rel=0&autoplay=1" 
                                    allowfullscreen 
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture">
                            </iframe>
                        </div>
                    <?php else: ?>
                        <div class="no-video">
                            <i class="fas fa-video-slash"></i>
                            <h3>No Video Available</h3>
                            <p>This match doesn't have an associated video yet.</p>
                            <a href="match_details.php?id=<?php echo $match['id']; ?>" class="btn btn-light">
                                <i class="fas fa-info-circle me-1"></i>View Match Details
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Match Information -->
                <div class="match-info">
                    <div class="row align-items-center">
                        <div class="col-md-4 text-center">
                            <img src="https://placehold.co/80x80" class="team-logo mb-2" alt="<?php echo htmlspecialchars($match['team1_name']); ?>">
                            <h5 class="mb-0"><?php echo htmlspecialchars($match['team1_name']); ?></h5>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="score-display"><?php echo $match['score_team1'] . ' - ' . $match['score_team2']; ?></div>
                            <div class="mt-2">
                                <?php 
                                $statusClass = '';
                                $statusText = '';
                                switch($match['status']) {
                                    case 'live':
                                        $statusClass = 'bg-danger live-indicator';
                                        $statusText = 'LIVE';
                                        break;
                                    case 'scheduled':
                                        $statusClass = 'bg-warning text-dark';
                                        $statusText = 'SCHEDULED';
                                        break;
                                    case 'completed':
                                        $statusClass = 'bg-success';
                                        $statusText = 'COMPLETED';
                                        break;
                                    case 'cancelled':
                                        $statusClass = 'bg-secondary';
                                        $statusText = 'CANCELLED';
                                        break;
                                }
                                ?>
                                <span class="badge <?php echo $statusClass; ?> status-badge"><?php echo $statusText; ?></span>
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            <img src="https://placehold.co/80x80" class="team-logo mb-2" alt="<?php echo htmlspecialchars($match['team2_name']); ?>">
                            <h5 class="mb-0"><?php echo htmlspecialchars($match['team2_name']); ?></h5>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row text-center">
                        <div class="col-md-6">
                            <h6><i class="fas fa-trophy me-2"></i>Tournament</h6>
                            <p class="mb-0"><?php echo htmlspecialchars($match['tournament_name']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-calendar me-2"></i>Date & Time</h6>
                            <p class="mb-0"><?php echo date('M j, Y g:i A', strtotime($match['scheduled_time'])); ?></p>
                        </div>
                    </div>
                    
                    <?php if (!empty($match['round'])): ?>
                    <div class="text-center mt-3">
                        <span class="badge bg-primary"><?php echo htmlspecialchars($match['round']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Live Chat -->
            <div class="col-lg-4">
                <div class="chat-container">
                    <h5 class="mb-3">
                        <i class="fas fa-comments me-2"></i>Live Chat
                    </h5>
                    
                    <div id="chat-messages">
                        <div class="chat-message">
                            <strong>EsportsFan99:</strong> This is going to be an amazing match!
                        </div>
                        <div class="chat-message">
                            <strong>GameMaster42:</strong> Both teams are looking strong today
                        </div>
                        <div class="chat-message self">
                            <strong>You:</strong> Can't wait to see how this plays out!
                        </div>
                    </div>
                    
                    <?php if ($isLoggedIn): ?>
                        <div class="input-group mt-3">
                            <input type="text" class="form-control" id="chat-input" placeholder="Type your message...">
                            <button class="btn btn-primary" type="button" id="send-message">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-1"></i>
                            Please <a href="login.php">login</a> to participate in the chat.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="text-center mt-4">
            <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-secondary me-2" onclick="if (window.history.length > 1) { history.back(); return false; }">
                <i class="fas fa-arrow-left me-1"></i>Back
            </a>
            <a href="match_details.php?id=<?php echo $match['id']; ?>" class="btn btn-outline-primary">
                <i class="fas fa-info-circle me-1"></i>Match Details
            </a>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-scroll chat to bottom
        function scrollChatToBottom() {
            const chatMessages = document.getElementById('chat-messages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Send message functionality
        document.getElementById('send-message')?.addEventListener('click', function() {
            const input = document.getElementById('chat-input');
            const message = input.value.trim();
            
            if (message) {
                const chatMessages = document.getElementById('chat-messages');
                const messageDiv = document.createElement('div');
                messageDiv.className = 'chat-message self';
                messageDiv.innerHTML = `<strong>You:</strong> ${message}`;
                chatMessages.appendChild(messageDiv);
                
                input.value = '';
                scrollChatToBottom();
                
                // Simulate response after 2 seconds
                setTimeout(() => {
                    const responses = [
                        "Great point!",
                        "I agree with that",
                        "This match is getting intense!",
                        "Who do you think will win?",
                        "Amazing play by the team!"
                    ];
                    const randomResponse = responses[Math.floor(Math.random() * responses.length)];
                    const botMessage = document.createElement('div');
                    botMessage.className = 'chat-message';
                    botMessage.innerHTML = `<strong>EsportsFan${Math.floor(Math.random() * 1000)}:</strong> ${randomResponse}`;
                    chatMessages.appendChild(botMessage);
                    scrollChatToBottom();
                }, 2000);
            }
        });
        
        // Allow Enter key to send message
        document.getElementById('chat-input')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('send-message').click();
            }
        });
        
        // Initial scroll to bottom
        scrollChatToBottom();
    </script>
</body>
</html>


