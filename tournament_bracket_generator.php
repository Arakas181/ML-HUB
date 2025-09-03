<?php
session_start();
require_once 'config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: login.php');
    exit();
}

$tournamentId = $_GET['id'] ?? 0;
$message = '';
$error = '';

// Fetch tournament details
try {
    $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$tournamentId]);
    $tournament = $stmt->fetch();
    
    if (!$tournament) {
        header('Location: tournaments.php');
        exit();
    }
    
    // Get registered participants
    $stmt = $pdo->prepare("
        SELECT tp.*, u.username, s.name as squad_name 
        FROM tournament_participants tp
        JOIN users u ON tp.user_id = u.id
        LEFT JOIN squads s ON tp.squad_name = s.name
        WHERE tp.tournament_id = ? AND tp.status = 'registered'
        ORDER BY tp.registration_date ASC
    ");
    $stmt->execute([$tournamentId]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if bracket already exists
    $stmt = $pdo->prepare("SELECT * FROM tournament_brackets WHERE tournament_id = ?");
    $stmt->execute([$tournamentId]);
    $existingBracket = $stmt->fetch();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle bracket generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_bracket'])) {
    try {
        $bracketType = $tournament['bracket_type'];
        $participantCount = count($participants);
        
        if ($participantCount < 2) {
            throw new Exception("Need at least 2 participants to generate bracket");
        }
        
        $bracketData = generateBracket($participants, $bracketType);
        
        // Save bracket to database
        if ($existingBracket) {
            $stmt = $pdo->prepare("UPDATE tournament_brackets SET bracket_data = ?, total_rounds = ? WHERE tournament_id = ?");
            $stmt->execute([json_encode($bracketData), $bracketData['total_rounds'], $tournamentId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO tournament_brackets (tournament_id, bracket_data, total_rounds) VALUES (?, ?, ?)");
            $stmt->execute([$tournamentId, json_encode($bracketData), $bracketData['total_rounds']]);
        }
        
        $message = "Bracket generated successfully!";
        
        // Refresh bracket data
        $stmt = $pdo->prepare("SELECT * FROM tournament_brackets WHERE tournament_id = ?");
        $stmt->execute([$tournamentId]);
        $existingBracket = $stmt->fetch();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

function generateBracket($participants, $bracketType) {
    switch ($bracketType) {
        case 'single_elimination':
            return generateSingleElimination($participants);
        case 'double_elimination':
            return generateDoubleElimination($participants);
        case 'swiss':
            return generateSwiss($participants);
        case 'round_robin':
            return generateRoundRobin($participants);
        default:
            return generateSingleElimination($participants);
    }
}

function generateSingleElimination($participants) {
    $count = count($participants);
    $rounds = ceil(log($count, 2));
    $nextPowerOf2 = pow(2, $rounds);
    
    // Add byes if needed
    $bracket = [
        'type' => 'single_elimination',
        'total_rounds' => $rounds,
        'rounds' => []
    ];
    
    // First round
    $firstRound = [];
    $participantIndex = 0;
    
    for ($i = 0; $i < $nextPowerOf2 / 2; $i++) {
        $match = [
            'match_id' => $i + 1,
            'round' => 1,
            'player1' => $participantIndex < $count ? $participants[$participantIndex] : null,
            'player2' => ($participantIndex + 1) < $count ? $participants[$participantIndex + 1] : null,
            'winner' => null,
            'score1' => 0,
            'score2' => 0,
            'status' => 'pending'
        ];
        
        // Handle byes
        if ($match['player2'] === null && $match['player1'] !== null) {
            $match['winner'] = $match['player1'];
            $match['status'] = 'bye';
        }
        
        $firstRound[] = $match;
        $participantIndex += 2;
    }
    
    $bracket['rounds'][1] = $firstRound;
    
    // Generate subsequent rounds
    for ($round = 2; $round <= $rounds; $round++) {
        $prevRoundMatches = count($bracket['rounds'][$round - 1]);
        $currentRoundMatches = $prevRoundMatches / 2;
        
        $currentRound = [];
        for ($i = 0; $i < $currentRoundMatches; $i++) {
            $currentRound[] = [
                'match_id' => $i + 1,
                'round' => $round,
                'player1' => null,
                'player2' => null,
                'winner' => null,
                'score1' => 0,
                'score2' => 0,
                'status' => 'pending',
                'depends_on' => [
                    ($i * 2) + 1,
                    ($i * 2) + 2
                ]
            ];
        }
        $bracket['rounds'][$round] = $currentRound;
    }
    
    return $bracket;
}

function generateDoubleElimination($participants) {
    $singleBracket = generateSingleElimination($participants);
    
    return [
        'type' => 'double_elimination',
        'total_rounds' => $singleBracket['total_rounds'] * 2,
        'winners_bracket' => $singleBracket['rounds'],
        'losers_bracket' => generateLosersBracket(count($participants)),
        'grand_final' => [
            'match_id' => 1,
            'player1' => null,
            'player2' => null,
            'winner' => null,
            'status' => 'pending'
        ]
    ];
}

function generateLosersBracket($participantCount) {
    $rounds = ceil(log($participantCount, 2)) * 2 - 1;
    $bracket = [];
    
    for ($round = 1; $round <= $rounds; $round++) {
        $bracket[$round] = [];
    }
    
    return $bracket;
}

function generateSwiss($participants) {
    $rounds = ceil(log(count($participants), 2));
    
    return [
        'type' => 'swiss',
        'total_rounds' => $rounds,
        'rounds' => [],
        'standings' => array_map(function($p) {
            return [
                'participant' => $p,
                'wins' => 0,
                'losses' => 0,
                'points' => 0
            ];
        }, $participants)
    ];
}

function generateRoundRobin($participants) {
    $count = count($participants);
    $rounds = $count - 1;
    $matchesPerRound = $count / 2;
    
    $bracket = [
        'type' => 'round_robin',
        'total_rounds' => $rounds,
        'rounds' => []
    ];
    
    for ($round = 1; $round <= $rounds; $round++) {
        $bracket['rounds'][$round] = [];
    }
    
    return $bracket;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournament Bracket Generator - ML HUB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        :root {
            --primary-color: #6f42c1;
            --secondary-color: #20c997;
            --dark-color: #212529;
        }
        
        body {
            background-color: #0f0f0f;
            color: #ffffff;
        }
        
        .bracket-container {
            overflow-x: auto;
            padding: 20px;
            background: #1a1a1a;
            border-radius: 10px;
        }
        
        .bracket-round {
            display: inline-block;
            vertical-align: top;
            margin-right: 50px;
            min-width: 200px;
        }
        
        .bracket-match {
            background: #2a2a2a;
            border: 2px solid #444;
            border-radius: 8px;
            margin-bottom: 20px;
            padding: 15px;
            position: relative;
        }
        
        .bracket-match.winner {
            border-color: var(--secondary-color);
        }
        
        .bracket-match.bye {
            border-color: #666;
            opacity: 0.7;
        }
        
        .participant {
            padding: 8px;
            margin: 2px 0;
            background: #333;
            border-radius: 5px;
            display: flex;
            justify-content: between;
            align-items: center;
        }
        
        .participant.winner {
            background: var(--primary-color);
        }
        
        .participant-name {
            flex: 1;
        }
        
        .participant-score {
            font-weight: bold;
            margin-left: 10px;
        }
        
        .round-title {
            text-align: center;
            font-weight: bold;
            color: var(--secondary-color);
            margin-bottom: 20px;
            padding: 10px;
            background: rgba(32, 201, 151, 0.1);
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-gamepad me-2"></i>ML HUB
            </a>
            <div class="d-flex">
                <a href="manage_tournaments.php" class="btn btn-outline-light">
                    <i class="fas fa-arrow-left me-1"></i>Back to Tournaments
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card bg-dark">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-sitemap me-2"></i>
                            Tournament Bracket: <?php echo htmlspecialchars($tournament['name']); ?>
                        </h4>
                        <small class="text-muted">
                            Type: <?php echo ucfirst(str_replace('_', ' ', $tournament['bracket_type'])); ?> | 
                            Participants: <?php echo count($participants); ?>
                        </small>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Participants List -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5><i class="fas fa-users me-2"></i>Registered Participants</h5>
                                <div class="list-group">
                                    <?php foreach ($participants as $participant): ?>
                                        <div class="list-group-item bg-secondary text-white">
                                            <strong><?php echo htmlspecialchars($participant['squad_name'] ?: $participant['in_game_name']); ?></strong>
                                            <small class="text-muted d-block">
                                                Player: <?php echo htmlspecialchars($participant['username']); ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h5><i class="fas fa-cog me-2"></i>Bracket Controls</h5>
                                
                                <?php if (!$existingBracket): ?>
                                    <form method="POST">
                                        <button type="submit" name="generate_bracket" class="btn btn-primary btn-lg w-100">
                                            <i class="fas fa-magic me-2"></i>Generate Bracket
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-success" onclick="window.location.href='tournament_live.php?id=<?php echo $tournamentId; ?>'">
                                            <i class="fas fa-play me-2"></i>View Live Bracket
                                        </button>
                                        <form method="POST" class="d-inline">
                                            <button type="submit" name="generate_bracket" class="btn btn-warning w-100" 
                                                    onclick="return confirm('This will regenerate the bracket. Continue?')">
                                                <i class="fas fa-redo me-2"></i>Regenerate Bracket
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Bracket Display -->
                        <?php if ($existingBracket): ?>
                            <div class="bracket-container">
                                <?php
                                $bracketData = json_decode($existingBracket['bracket_data'], true);
                                if ($bracketData['type'] === 'single_elimination'):
                                ?>
                                    <?php foreach ($bracketData['rounds'] as $roundNum => $matches): ?>
                                        <div class="bracket-round">
                                            <div class="round-title">
                                                <?php
                                                $roundNames = [
                                                    1 => 'First Round',
                                                    2 => 'Second Round',
                                                    3 => 'Quarterfinals',
                                                    4 => 'Semifinals',
                                                    5 => 'Finals'
                                                ];
                                                echo $roundNames[$roundNum] ?? "Round $roundNum";
                                                ?>
                                            </div>
                                            
                                            <?php foreach ($matches as $match): ?>
                                                <div class="bracket-match <?php echo $match['status']; ?>">
                                                    <div class="match-header text-center mb-2">
                                                        <small class="text-muted">Match #<?php echo $match['match_id']; ?></small>
                                                    </div>
                                                    
                                                    <?php if ($match['player1']): ?>
                                                        <div class="participant <?php echo $match['winner'] && $match['winner']['id'] === $match['player1']['id'] ? 'winner' : ''; ?>">
                                                            <span class="participant-name">
                                                                <?php echo htmlspecialchars($match['player1']['squad_name'] ?: $match['player1']['in_game_name']); ?>
                                                            </span>
                                                            <span class="participant-score"><?php echo $match['score1']; ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($match['player2']): ?>
                                                        <div class="participant <?php echo $match['winner'] && $match['winner']['id'] === $match['player2']['id'] ? 'winner' : ''; ?>">
                                                            <span class="participant-name">
                                                                <?php echo htmlspecialchars($match['player2']['squad_name'] ?: $match['player2']['in_game_name']); ?>
                                                            </span>
                                                            <span class="participant-score"><?php echo $match['score2']; ?></span>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="participant">
                                                            <span class="participant-name text-muted">BYE</span>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($match['status'] === 'pending' && $match['player1'] && $match['player2']): ?>
                                                        <div class="text-center mt-2">
                                                            <button class="btn btn-sm btn-primary" 
                                                                    onclick="updateMatch(<?php echo $match['match_id']; ?>, <?php echo $roundNum; ?>)">
                                                                Update Score
                                                            </button>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateMatch(matchId, round) {
            // This would open a modal or redirect to match update page
            window.location.href = `update_match.php?tournament=<?php echo $tournamentId; ?>&match=${matchId}&round=${round}`;
        }
    </script>
</body>
</html>
