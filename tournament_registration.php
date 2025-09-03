<?php
require_once 'config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Handle registration actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'register_team':
            handleTeamRegistration();
            break;
        case 'invite_player':
            handlePlayerInvite();
            break;
        case 'respond_invite':
            handleInviteResponse();
            break;
        case 'check_in':
            handlePlayerCheckIn();
            break;
        case 'seed_tournament':
            if (in_array($user_role, ['admin', 'super_admin'])) {
                handleTournamentSeeding();
            }
            break;
    }
}

function handleTeamRegistration() {
    global $pdo, $user_id;
    
    $tournament_id = $_POST['tournament_id'];
    $team_name = trim($_POST['team_name']);
    $team_members = $_POST['team_members'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        // Check tournament registration deadline
        $stmt = $pdo->prepare("SELECT registration_deadline, max_teams, team_size FROM tournaments WHERE id = ?");
        $stmt->execute([$tournament_id]);
        $tournament = $stmt->fetch();
        
        if (!$tournament) {
            throw new Exception("Tournament not found");
        }
        
        if (strtotime($tournament['registration_deadline']) < time()) {
            throw new Exception("Registration deadline has passed");
        }
        
        // Check if tournament is full
        $stmt = $pdo->prepare("SELECT COUNT(*) as team_count FROM tournament_registrations WHERE tournament_id = ?");
        $stmt->execute([$tournament_id]);
        $team_count = $stmt->fetch()['team_count'];
        
        if ($team_count >= $tournament['max_teams']) {
            throw new Exception("Tournament is full");
        }
        
        // Create team registration
        $stmt = $pdo->prepare("
            INSERT INTO tournament_registrations 
            (tournament_id, team_name, captain_id, registration_date, status) 
            VALUES (?, ?, ?, NOW(), 'pending')
        ");
        $stmt->execute([$tournament_id, $team_name, $user_id]);
        $registration_id = $pdo->lastInsertId();
        
        // Add captain as team member
        $stmt = $pdo->prepare("
            INSERT INTO tournament_team_members 
            (registration_id, user_id, role, status) 
            VALUES (?, ?, 'captain', 'confirmed')
        ");
        $stmt->execute([$registration_id, $user_id]);
        
        // Send invites to team members
        foreach ($team_members as $member_email) {
            if (!empty($member_email)) {
                sendPlayerInvite($registration_id, $member_email, $user_id);
            }
        }
        
        $pdo->commit();
        $_SESSION['success'] = "Team registered successfully! Invites sent to team members.";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
}

function handlePlayerInvite() {
    global $pdo, $user_id;
    
    $registration_id = $_POST['registration_id'];
    $player_email = $_POST['player_email'];
    
    try {
        // Verify user is team captain
        $stmt = $pdo->prepare("
            SELECT tr.tournament_id, tr.team_name 
            FROM tournament_registrations tr 
            WHERE tr.id = ? AND tr.captain_id = ?
        ");
        $stmt->execute([$registration_id, $user_id]);
        $team = $stmt->fetch();
        
        if (!$team) {
            throw new Exception("Not authorized to invite players to this team");
        }
        
        sendPlayerInvite($registration_id, $player_email, $user_id);
        $_SESSION['success'] = "Invite sent to $player_email";
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

function sendPlayerInvite($registration_id, $player_email, $captain_id) {
    global $pdo;
    
    // Check if player exists
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
    $stmt->execute([$player_email]);
    $player = $stmt->fetch();
    
    if (!$player) {
        throw new Exception("Player with email $player_email not found");
    }
    
    // Check if already invited or member
    $stmt = $pdo->prepare("
        SELECT id FROM tournament_team_members 
        WHERE registration_id = ? AND user_id = ?
    ");
    $stmt->execute([$registration_id, $player['id']]);
    
    if ($stmt->fetch()) {
        throw new Exception("Player already invited or part of team");
    }
    
    // Create invite
    $invite_token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare("
        INSERT INTO tournament_team_members 
        (registration_id, user_id, role, status, invite_token, invited_at) 
        VALUES (?, ?, 'player', 'invited', ?, NOW())
    ");
    $stmt->execute([$registration_id, $player['id'], $invite_token]);
    
    // Get team and tournament info
    $stmt = $pdo->prepare("
        SELECT tr.team_name, t.name as tournament_name, u.username as captain_name
        FROM tournament_registrations tr
        JOIN tournaments t ON tr.tournament_id = t.id
        JOIN users u ON tr.captain_id = u.id
        WHERE tr.id = ?
    ");
    $stmt->execute([$registration_id]);
    $info = $stmt->fetch();
    
    // Send email notification
    sendInviteEmail($player_email, $player['username'], $info, $invite_token);
}

function sendInviteEmail($email, $username, $team_info, $invite_token) {
    $subject = "Tournament Team Invite - {$team_info['tournament_name']}";
    $invite_link = "http://localhost/Esports/tournament_registration.php?action=view_invite&token=$invite_token";
    
    $message = "
    <html>
    <head><title>Tournament Team Invitation</title></head>
    <body>
        <h2>You've been invited to join a tournament team!</h2>
        <p>Hi $username,</p>
        <p><strong>{$team_info['captain_name']}</strong> has invited you to join team <strong>{$team_info['team_name']}</strong> 
        for the tournament <strong>{$team_info['tournament_name']}</strong>.</p>
        
        <p><a href='$invite_link' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
        Accept Invitation</a></p>
        
        <p>Or copy this link: $invite_link</p>
        
        <p>This invitation will expire in 48 hours.</p>
        
        <p>Best regards,<br>ML HUB Esports Team</p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: noreply@mlhub-esports.com" . "\r\n";
    
    mail($email, $subject, $message, $headers);
}

function handleInviteResponse() {
    global $pdo, $user_id;
    
    $invite_token = $_POST['invite_token'];
    $response = $_POST['response']; // 'accept' or 'decline'
    
    try {
        // Find invite
        $stmt = $pdo->prepare("
            SELECT ttm.*, tr.team_name, t.name as tournament_name
            FROM tournament_team_members ttm
            JOIN tournament_registrations tr ON ttm.registration_id = tr.id
            JOIN tournaments t ON tr.tournament_id = t.id
            WHERE ttm.invite_token = ? AND ttm.user_id = ? AND ttm.status = 'invited'
        ");
        $stmt->execute([$invite_token, $user_id]);
        $invite = $stmt->fetch();
        
        if (!$invite) {
            throw new Exception("Invalid or expired invitation");
        }
        
        // Check if invite is still valid (48 hours)
        if (strtotime($invite['invited_at']) < strtotime('-48 hours')) {
            throw new Exception("Invitation has expired");
        }
        
        if ($response === 'accept') {
            // Accept invitation
            $stmt = $pdo->prepare("
                UPDATE tournament_team_members 
                SET status = 'confirmed', responded_at = NOW() 
                WHERE invite_token = ?
            ");
            $stmt->execute([$invite_token]);
            
            $_SESSION['success'] = "Successfully joined team {$invite['team_name']} for {$invite['tournament_name']}!";
            
        } else {
            // Decline invitation
            $stmt = $pdo->prepare("
                UPDATE tournament_team_members 
                SET status = 'declined', responded_at = NOW() 
                WHERE invite_token = ?
            ");
            $stmt->execute([$invite_token]);
            
            $_SESSION['info'] = "Invitation declined.";
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

function handlePlayerCheckIn() {
    global $pdo, $user_id;
    
    $tournament_id = $_POST['tournament_id'];
    
    try {
        // Check if user is registered for tournament
        $stmt = $pdo->prepare("
            SELECT ttm.registration_id, tr.team_name
            FROM tournament_team_members ttm
            JOIN tournament_registrations tr ON ttm.registration_id = tr.id
            WHERE tr.tournament_id = ? AND ttm.user_id = ? AND ttm.status = 'confirmed'
        ");
        $stmt->execute([$tournament_id, $user_id]);
        $registration = $stmt->fetch();
        
        if (!$registration) {
            throw new Exception("You are not registered for this tournament");
        }
        
        // Check tournament check-in window
        $stmt = $pdo->prepare("SELECT checkin_start, checkin_end FROM tournaments WHERE id = ?");
        $stmt->execute([$tournament_id]);
        $tournament = $stmt->fetch();
        
        $now = time();
        if ($now < strtotime($tournament['checkin_start']) || $now > strtotime($tournament['checkin_end'])) {
            throw new Exception("Check-in is not currently available");
        }
        
        // Record check-in
        $stmt = $pdo->prepare("
            INSERT INTO tournament_checkins (tournament_id, user_id, registration_id, checkin_time) 
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE checkin_time = NOW()
        ");
        $stmt->execute([$tournament_id, $user_id, $registration['registration_id']]);
        
        $_SESSION['success'] = "Successfully checked in for the tournament!";
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

function handleTournamentSeeding() {
    global $pdo;
    
    $tournament_id = $_POST['tournament_id'];
    $seeding_method = $_POST['seeding_method']; // 'random', 'ranking', 'manual'
    
    try {
        // Get all confirmed teams
        $stmt = $pdo->prepare("
            SELECT tr.id, tr.team_name, tr.captain_id,
                   COUNT(ttm.id) as team_size,
                   AVG(u.ranking_points) as avg_ranking
            FROM tournament_registrations tr
            LEFT JOIN tournament_team_members ttm ON tr.id = ttm.registration_id AND ttm.status = 'confirmed'
            LEFT JOIN users u ON ttm.user_id = u.id
            WHERE tr.tournament_id = ? AND tr.status = 'confirmed'
            GROUP BY tr.id
            ORDER BY avg_ranking DESC
        ");
        $stmt->execute([$tournament_id]);
        $teams = $stmt->fetchAll();
        
        // Apply seeding based on method
        switch ($seeding_method) {
            case 'random':
                shuffle($teams);
                break;
            case 'ranking':
                // Already ordered by avg_ranking DESC
                break;
            case 'manual':
                // Manual seeding would be handled separately
                break;
        }
        
        // Update team seeds
        foreach ($teams as $index => $team) {
            $seed = $index + 1;
            $stmt = $pdo->prepare("UPDATE tournament_registrations SET seed = ? WHERE id = ?");
            $stmt->execute([$seed, $team['id']]);
        }
        
        $_SESSION['success'] = "Tournament seeding completed using $seeding_method method";
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Handle invite view
if (isset($_GET['action']) && $_GET['action'] === 'view_invite' && isset($_GET['token'])) {
    $invite_token = $_GET['token'];
    
    $stmt = $pdo->prepare("
        SELECT ttm.*, tr.team_name, t.name as tournament_name, t.description,
               u.username as captain_name
        FROM tournament_team_members ttm
        JOIN tournament_registrations tr ON ttm.registration_id = tr.id
        JOIN tournaments t ON tr.tournament_id = t.id
        JOIN users u ON tr.captain_id = u.id
        WHERE ttm.invite_token = ? AND ttm.status = 'invited'
    ");
    $stmt->execute([$invite_token]);
    $invite = $stmt->fetch();
}

// Get user's tournaments
$stmt = $pdo->prepare("
    SELECT DISTINCT t.*, tr.team_name, tr.seed, ttm.role,
           (SELECT COUNT(*) FROM tournament_checkins tc WHERE tc.tournament_id = t.id AND tc.user_id = ?) as checked_in
    FROM tournaments t
    LEFT JOIN tournament_registrations tr ON t.id = tr.tournament_id
    LEFT JOIN tournament_team_members ttm ON tr.id = ttm.registration_id AND ttm.user_id = ?
    WHERE ttm.status IN ('confirmed', 'invited')
    ORDER BY t.start_date ASC
");
$stmt->execute([$user_id, $user_id]);
$user_tournaments = $stmt->fetchAll();

// Get available tournaments for registration
$stmt = $pdo->prepare("
    SELECT t.*, 
           (SELECT COUNT(*) FROM tournament_registrations tr WHERE tr.tournament_id = t.id) as registered_teams
    FROM tournaments t
    WHERE t.registration_deadline > NOW() 
    AND t.status = 'upcoming'
    AND t.id NOT IN (
        SELECT DISTINCT tr.tournament_id 
        FROM tournament_registrations tr
        JOIN tournament_team_members ttm ON tr.id = ttm.registration_id
        WHERE ttm.user_id = ?
    )
    ORDER BY t.registration_deadline ASC
");
$stmt->execute([$user_id]);
$available_tournaments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournament Registration - ML HUB Esports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1a1a2e;
            --secondary-color: #16213e;
            --accent-color: #0f3460;
            --highlight-color: #e94560;
            --text-light: #f1f1f1;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--text-light);
            min-height: 100vh;
        }
        
        .tournament-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            transition: transform 0.3s ease;
        }
        
        .tournament-card:hover {
            transform: translateY(-5px);
        }
        
        .status-badge {
            font-size: 0.8em;
            padding: 0.3em 0.8em;
            border-radius: 20px;
        }
        
        .invite-card {
            background: linear-gradient(135deg, var(--highlight-color), #ff6b8a);
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-trophy me-2"></i>ML HUB Esports
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="tournaments.php">Tournaments</a>
                <a class="nav-link" href="profile.php">Profile</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($invite)): ?>
            <div class="invite-card text-center">
                <h2><i class="fas fa-envelope-open me-2"></i>Tournament Invitation</h2>
                <p class="lead">You've been invited to join <strong><?= htmlspecialchars($invite['team_name']) ?></strong></p>
                <p>Tournament: <strong><?= htmlspecialchars($invite['tournament_name']) ?></strong></p>
                <p>Captain: <strong><?= htmlspecialchars($invite['captain_name']) ?></strong></p>
                
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="respond_invite">
                    <input type="hidden" name="invite_token" value="<?= htmlspecialchars($invite_token) ?>">
                    <button type="submit" name="response" value="accept" class="btn btn-success btn-lg me-3">
                        <i class="fas fa-check me-2"></i>Accept
                    </button>
                    <button type="submit" name="response" value="decline" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-times me-2"></i>Decline
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <h2><i class="fas fa-trophy me-2"></i>My Tournaments</h2>
                
                <?php if (empty($user_tournaments)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>You're not registered for any tournaments yet.
                    </div>
                <?php else: ?>
                    <?php foreach ($user_tournaments as $tournament): ?>
                        <div class="tournament-card p-4 mb-3">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h5><?= htmlspecialchars($tournament['name']) ?></h5>
                                    <p class="text-muted mb-1">Team: <?= htmlspecialchars($tournament['team_name']) ?></p>
                                    <p class="text-muted mb-1">Role: 
                                        <span class="badge bg-primary"><?= ucfirst($tournament['role']) ?></span>
                                    </p>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?= date('M j, Y g:i A', strtotime($tournament['start_date'])) ?>
                                    </small>
                                </div>
                                <div class="col-md-4 text-end">
                                    <?php if ($tournament['seed']): ?>
                                        <div class="mb-2">
                                            <span class="badge bg-warning">Seed #<?= $tournament['seed'] ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($tournament['checked_in']): ?>
                                        <span class="status-badge bg-success">
                                            <i class="fas fa-check me-1"></i>Checked In
                                        </span>
                                    <?php else: ?>
                                        <?php 
                                        $now = time();
                                        $checkin_start = strtotime($tournament['checkin_start']);
                                        $checkin_end = strtotime($tournament['checkin_end']);
                                        ?>
                                        
                                        <?php if ($now >= $checkin_start && $now <= $checkin_end): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="check_in">
                                                <input type="hidden" name="tournament_id" value="<?= $tournament['id'] ?>">
                                                <button type="submit" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-sign-in-alt me-1"></i>Check In
                                                </button>
                                            </form>
                                        <?php elseif ($now < $checkin_start): ?>
                                            <span class="status-badge bg-secondary">Check-in opens soon</span>
                                        <?php else: ?>
                                            <span class="status-badge bg-danger">Check-in closed</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="col-lg-4">
                <h4><i class="fas fa-plus-circle me-2"></i>Available Tournaments</h4>
                
                <?php if (empty($available_tournaments)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No tournaments available for registration.
                    </div>
                <?php else: ?>
                    <?php foreach ($available_tournaments as $tournament): ?>
                        <div class="tournament-card p-3 mb-3">
                            <h6><?= htmlspecialchars($tournament['name']) ?></h6>
                            <p class="small text-muted mb-2"><?= htmlspecialchars($tournament['description']) ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <?= $tournament['registered_teams'] ?>/<?= $tournament['max_teams'] ?> teams
                                </small>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" 
                                        data-bs-target="#registerModal" 
                                        data-tournament-id="<?= $tournament['id'] ?>"
                                        data-tournament-name="<?= htmlspecialchars($tournament['name']) ?>"
                                        data-team-size="<?= $tournament['team_size'] ?>">
                                    Register
                                </button>
                            </div>
                            <small class="text-warning">
                                <i class="fas fa-clock me-1"></i>
                                Deadline: <?= date('M j, g:i A', strtotime($tournament['registration_deadline'])) ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Registration Modal -->
    <div class="modal fade" id="registerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header">
                    <h5 class="modal-title">Register Team</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="register_team">
                        <input type="hidden" name="tournament_id" id="modalTournamentId">
                        
                        <div class="mb-3">
                            <label class="form-label">Team Name</label>
                            <input type="text" class="form-control" name="team_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Team Members (Email addresses)</label>
                            <div id="teamMembersContainer">
                                <input type="email" class="form-control mb-2" name="team_members[]" placeholder="Player email">
                            </div>
                            <button type="button" class="btn btn-outline-light btn-sm" onclick="addMemberField()">
                                <i class="fas fa-plus me-1"></i>Add Member
                            </button>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            You will be the team captain. Invitations will be sent to team members.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Register Team</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let memberCount = 1;
        
        function addMemberField() {
            const container = document.getElementById('teamMembersContainer');
            const input = document.createElement('input');
            input.type = 'email';
            input.className = 'form-control mb-2';
            input.name = 'team_members[]';
            input.placeholder = 'Player email';
            container.appendChild(input);
            memberCount++;
        }
        
        // Handle modal data
        document.getElementById('registerModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const tournamentId = button.getAttribute('data-tournament-id');
            const tournamentName = button.getAttribute('data-tournament-name');
            const teamSize = button.getAttribute('data-team-size');
            
            document.getElementById('modalTournamentId').value = tournamentId;
            document.querySelector('.modal-title').textContent = `Register for ${tournamentName}`;
            
            // Add appropriate number of member fields
            const container = document.getElementById('teamMembersContainer');
            container.innerHTML = '';
            for (let i = 0; i < teamSize - 1; i++) {
                addMemberField();
            }
        });
    </script>
</body>
</html>
