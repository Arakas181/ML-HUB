<?php
session_start();
require_once 'config.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    header('Location: admin_login.php');
    exit();
}

// Ensure video_url column exists in matches table
$hasVideoUrlColumn = false;
try {
    $pdo->query("SELECT video_url FROM matches LIMIT 1");
    $hasVideoUrlColumn = true;
} catch (PDOException $e) {
    try {
        $pdo->exec("ALTER TABLE matches ADD COLUMN video_url VARCHAR(500) NULL AFTER round");
        $hasVideoUrlColumn = true;
    } catch (PDOException $e2) {
        error_log('Failed to add video_url column to matches: ' . $e2->getMessage());
    }
}

// Ensure live_match_details table exists
try {
    $pdo->query("SELECT 1 FROM live_match_details LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS live_match_details (
                id INT AUTO_INCREMENT PRIMARY KEY,
                match_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                viewer_count INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
            )
        ");
    } catch (PDOException $e2) {
        error_log('Failed to create live_match_details table: ' . $e2->getMessage());
    }
}

// Handle form submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_live_match'])) {
    $tournament_id = (int)($_POST['tournament_id'] ?? 0);
    $team1_id = (int)($_POST['team1_id'] ?? 0);
    $team2_id = (int)($_POST['team2_id'] ?? 0);
    $scheduled_time = $_POST['scheduled_time'] ?? '';
    $round = trim($_POST['round'] ?? '');
    $video_url = trim($_POST['video_url'] ?? '');
    $match_title = trim($_POST['match_title'] ?? '');
    $match_description = trim($_POST['match_description'] ?? '');
    $viewer_count = (int)($_POST['viewer_count'] ?? 0);

    try {
        if ($tournament_id <= 0 || $team1_id <= 0 || $team2_id <= 0 || $scheduled_time === '' || $team1_id === $team2_id) {
            throw new Exception('All fields are required and teams must be different.');
        }

        // Start transaction
        $pdo->beginTransaction();

        // Insert match
        if ($hasVideoUrlColumn) {
            $stmt = $pdo->prepare("INSERT INTO matches (tournament_id, team1_id, team2_id, scheduled_time, status, round, video_url) VALUES (?, ?, ?, ?, 'live', ?, ?)");
            $stmt->execute([$tournament_id, $team1_id, $team2_id, $scheduled_time, $round, $video_url]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO matches (tournament_id, team1_id, team2_id, scheduled_time, status, round) VALUES (?, ?, ?, ?, 'live', ?)");
            $stmt->execute([$tournament_id, $team1_id, $team2_id, $scheduled_time, $round]);
        }

        $matchId = $pdo->lastInsertId();

        // Insert live match details
        $stmt = $pdo->prepare("INSERT INTO live_match_details (match_id, title, description, viewer_count) VALUES (?, ?, ?, ?)");
        $stmt->execute([$matchId, $match_title, $match_description, $viewer_count]);

        $pdo->commit();
        $success = 'Live match added successfully! It will now appear on the homepage live stream section.';
    } catch (Throwable $e) {
        $pdo->rollBack();
        $error = 'Failed to add live match: ' . $e->getMessage();
    }
}

// Fetch data for dropdowns
try {
    $teams = $pdo->query("SELECT id, name FROM teams ORDER BY name")->fetchAll();
    $tournaments = $pdo->query("SELECT id, name FROM tournaments ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $teams = [];
    $tournaments = [];
    if ($error === '') { $error = 'Error loading data'; }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Live Match - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .live-match-form {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-live {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            border: none;
            color: white;
            font-weight: bold;
        }
        
        .btn-live:hover {
            background: linear-gradient(135deg, #ee5a24, #ff6b6b);
            color: white;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="admin_dashboard.php">
                <i class="fas fa-broadcast me-2"></i>Admin Dashboard
            </a>
            <div class="navbar-nav ms-auto">
                <a href="admin_dashboard.php" class="nav-link">Dashboard</a>
                <a href="logout.php" class="nav-link">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Header -->
                <div class="live-match-form text-center">
                    <h2><i class="fas fa-broadcast me-2"></i>Add Live Match</h2>
                    <p class="mb-0">Create a new live match that will be displayed on the homepage live stream section</p>
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

                <!-- Form -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Live Match Details</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="add_live_match" value="1">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="tournament_id" class="form-label">
                                        <i class="fas fa-trophy me-1"></i>Tournament
                                    </label>
                                    <select class="form-select" id="tournament_id" name="tournament_id" required>
                                        <option value="">Select tournament</option>
                                        <?php foreach ($tournaments as $t): ?>
                                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="round" class="form-label">
                                        <i class="fas fa-layer-group me-1"></i>Round
                                    </label>
                                    <input type="text" class="form-control" id="round" name="round" 
                                           placeholder="e.g., Quarter Finals, Semi Finals" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="team1_id" class="form-label">
                                        <i class="fas fa-users me-1"></i>Team 1
                                    </label>
                                    <select class="form-select" id="team1_id" name="team1_id" required>
                                        <option value="">Select team</option>
                                        <?php foreach ($teams as $team): ?>
                                            <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="team2_id" class="form-label">
                                        <i class="fas fa-users me-1"></i>Team 2
                                    </label>
                                    <select class="form-select" id="team2_id" name="team2_id" required>
                                        <option value="">Select team</option>
                                        <?php foreach ($teams as $team): ?>
                                            <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="scheduled_time" class="form-label">
                                        <i class="fas fa-clock me-1"></i>Start Time
                                    </label>
                                    <input type="datetime-local" class="form-control" id="scheduled_time" name="scheduled_time" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="viewer_count" class="form-label">
                                        <i class="fas fa-eye me-1"></i>Viewer Count
                                    </label>
                                    <input type="number" class="form-control" id="viewer_count" name="viewer_count" 
                                           placeholder="e.g., 25842" value="0" min="0">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="video_url" class="form-label">
                                    <i class="fab fa-youtube me-1"></i>YouTube Live Stream URL
                                </label>
                                <input type="url" class="form-control" id="video_url" name="video_url" 
                                       placeholder="https://www.youtube.com/watch?v=..." required>
                                <div class="form-text">Enter the YouTube live stream URL that will be embedded on the homepage</div>
                            </div>

                            <div class="mb-3">
                                <label for="match_title" class="form-label">
                                    <i class="fas fa-heading me-1"></i>Match Title (for homepage display)
                                </label>
                                <input type="text" class="form-control" id="match_title" name="match_title" 
                                       placeholder="e.g., MSC 2023: Team Phoenix vs Team Hydra - Game 4" required>
                            </div>

                            <div class="mb-3">
                                <label for="match_description" class="form-label">
                                    <i class="fas fa-align-left me-1"></i>Match Description
                                </label>
                                <textarea class="form-control" id="match_description" name="match_description" rows="3" 
                                          placeholder="Brief description of the match for homepage display"></textarea>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-live btn-lg">
                                    <i class="fas fa-broadcast me-2"></i>Add Live Match
                                </button>
                                <a href="admin_dashboard.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Instructions</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Live matches will appear on the homepage live stream section</li>
                            <li><i class="fas fa-check text-success me-2"></i>YouTube URLs will be automatically converted to embed format</li>
                            <li><i class="fas fa-check text-success me-2"></i>Matches are automatically set to 'live' status</li>
                            <li><i class="fas fa-check text-success me-2"></i>Viewer count and match details will be displayed on the homepage</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set current date and time as default
        document.getElementById('scheduled_time').value = new Date().toISOString().slice(0, 16);
        
        // Prevent selecting same team
        document.getElementById('team1_id').addEventListener('change', function() {
            const team2Select = document.getElementById('team2_id');
            const team1Value = this.value;
            
            Array.from(team2Select.options).forEach(option => {
                if (option.value === team1Value) {
                    option.disabled = true;
                } else {
                    option.disabled = false;
                }
            });
        });
        
        document.getElementById('team2_id').addEventListener('change', function() {
            const team1Select = document.getElementById('team1_id');
            const team2Value = this.value;
            
            Array.from(team1Select.options).forEach(option => {
                if (option.value === team2Value) {
                    option.disabled = true;
                } else {
                    option.disabled = false;
                }
            });
        });
    </script>
</body>
</html>
