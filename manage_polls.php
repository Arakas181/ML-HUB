<?php
require_once 'config.php';

// Check if user is admin
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'super_admin'])) {
    header('Location: login.php');
    exit;
}

// Handle poll creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_poll'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $options = array_filter(array_map('trim', $_POST['options']));
    $duration = (int)$_POST['duration'];
    $multipleChoice = isset($_POST['multiple_choice']) ? true : false;
    
    if (!empty($title) && count($options) >= 2) {
        $result = createPoll($title, $description, array_values($options), $duration, $multipleChoice);
        
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['error'];
        }
    } else {
        $error = "Please provide a title and at least 2 options.";
    }
}

// Handle poll status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $pollId = (int)$_POST['poll_id'];
    $status = $_POST['status'];
    
    try {
        $stmt = $pdo->prepare("UPDATE live_polls SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $status, ':id' => $pollId]);
        $success = "Poll status updated successfully!";
    } catch (PDOException $e) {
        $error = "Failed to update poll: " . $e->getMessage();
    }
}

// Get all polls
try {
    $stmt = $pdo->prepare("
        SELECT lp.*, u.username as creator_name,
               COUNT(pv.id) as total_votes
        FROM live_polls lp 
        LEFT JOIN users u ON lp.created_by = u.id
        LEFT JOIN poll_votes pv ON lp.id = pv.poll_id
        GROUP BY lp.id
        ORDER BY lp.created_at DESC
    ");
    $stmt->execute();
    $polls = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $polls = [];
    $error = "Failed to load polls: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Polls - ML HUB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e, #16213e, #0f3460);
            min-height: 100vh;
            color: white;
        }
        .card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .btn-primary {
            background: linear-gradient(45deg, #5ce1e6, #3b82f6);
            border: none;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-poll-h me-2"></i>Manage Live Polls</h2>
                    <a href="admin_dashboard.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>

                <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Create New Poll -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Create New Poll</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Poll Title</label>
                                        <input type="text" class="form-control" id="title" name="title" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="duration" class="form-label">Duration (minutes)</label>
                                        <select class="form-control" id="duration" name="duration">
                                            <option value="5">5 minutes</option>
                                            <option value="10">10 minutes</option>
                                            <option value="15">15 minutes</option>
                                            <option value="30">30 minutes</option>
                                            <option value="60">1 hour</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description (optional)</label>
                                <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="multiple_choice" name="multiple_choice">
                                    <label class="form-check-label" for="multiple_choice">
                                        Allow multiple choice voting
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Poll Options</label>
                                <div id="poll-options">
                                    <div class="input-group mb-2">
                                        <span class="input-group-text">1</span>
                                        <input type="text" class="form-control" name="options[]" placeholder="Option 1" required>
                                    </div>
                                    <div class="input-group mb-2">
                                        <span class="input-group-text">2</span>
                                        <input type="text" class="form-control" name="options[]" placeholder="Option 2" required>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-outline-light btn-sm" onclick="addOption()">
                                    <i class="fas fa-plus me-1"></i>Add Option
                                </button>
                            </div>
                            
                            <button type="submit" name="create_poll" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Create Poll
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Existing Polls -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Existing Polls</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($polls)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-poll-h fa-3x mb-3 text-muted"></i>
                            <h6>No polls created yet</h6>
                            <p class="text-muted">Create your first poll using the form above.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-striped">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Status</th>
                                        <th>Total Votes</th>
                                        <th>Created By</th>
                                        <th>Ends At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($polls as $poll): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($poll['title']); ?></strong>
                                            <?php if ($poll['description']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($poll['description']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $poll['status'] === 'active' ? 'success' : ($poll['status'] === 'ended' ? 'secondary' : 'danger'); ?>">
                                                <?php echo ucfirst($poll['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $poll['total_votes']; ?></td>
                                        <td><?php echo htmlspecialchars($poll['creator_name']); ?></td>
                                        <td><?php echo date('M j, Y H:i', strtotime($poll['ends_at'])); ?></td>
                                        <td>
                                            <?php if ($poll['status'] === 'active'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="poll_id" value="<?php echo $poll['id']; ?>">
                                                <input type="hidden" name="status" value="ended">
                                                <button type="submit" name="update_status" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-stop me-1"></i>End Poll
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let optionCount = 2;
        
        function addOption() {
            if (optionCount >= 6) {
                alert('Maximum 6 options allowed');
                return;
            }
            
            optionCount++;
            const optionsContainer = document.getElementById('poll-options');
            const newOption = document.createElement('div');
            newOption.className = 'input-group mb-2';
            newOption.innerHTML = `
                <span class="input-group-text">${optionCount}</span>
                <input type="text" class="form-control" name="options[]" placeholder="Option ${optionCount}">
                <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)">
                    <i class="fas fa-times"></i>
                </button>
            `;
            optionsContainer.appendChild(newOption);
        }
        
        function removeOption(button) {
            if (optionCount <= 2) {
                alert('Minimum 2 options required');
                return;
            }
            
            button.parentElement.remove();
            optionCount--;
            
            // Renumber options
            const options = document.querySelectorAll('#poll-options .input-group-text');
            options.forEach((option, index) => {
                option.textContent = index + 1;
            });
        }
    </script>
</body>
</html>
