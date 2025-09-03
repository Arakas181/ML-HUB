<?php
require_once 'config.php';

header('Content-Type: application/json');

// Get active polls
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM live_polls 
            WHERE status = 'active' AND ends_at > NOW()
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        
        $polls = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get vote counts for each poll
        foreach ($polls as &$poll) {
            $options = json_decode($poll['options'], true);
            $poll['options_with_votes'] = [];
            
            $stmt = $pdo->prepare("
                SELECT option_index, COUNT(*) as vote_count 
                FROM poll_votes 
                WHERE poll_id = :poll_id 
                GROUP BY option_index
            ");
            $stmt->execute([':poll_id' => $poll['id']]);
            $voteCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Initialize all options with 0 votes
            foreach ($options as $index => $option) {
                $poll['options_with_votes'][$index] = [
                    'text' => $option,
                    'votes' => 0
                ];
            }
            
            // Update with actual vote counts
            foreach ($voteCounts as $vote) {
                if (isset($poll['options_with_votes'][$vote['option_index']])) {
                    $poll['options_with_votes'][$vote['option_index']]['votes'] = (int)$vote['vote_count'];
                }
            }
            
            // Check if current user has voted
            if (isLoggedIn()) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as has_voted 
                    FROM poll_votes 
                    WHERE poll_id = :poll_id AND user_id = :user_id
                ");
                $stmt->execute([
                    ':poll_id' => $poll['id'],
                    ':user_id' => getUserId()
                ]);
                $hasVoted = $stmt->fetch(PDO::FETCH_ASSOC);
                $poll['user_has_voted'] = (bool)$hasVoted['has_voted'];
            } else {
                $poll['user_has_voted'] = false;
            }
            
            // Calculate total votes
            $poll['total_votes'] = array_sum(array_column($poll['options_with_votes'], 'votes'));
        }
        
        echo json_encode([
            'success' => true,
            'polls' => $polls
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to fetch polls: ' . $e->getMessage()
        ]);
    }
}

// Submit a vote
else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isLoggedIn()) {
        echo json_encode([
            'success' => false,
            'error' => 'You must be logged in to vote'
        ]);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $pollId = $data['poll_id'] ?? null;
    $optionIndex = $data['option_index'] ?? null;
    
    if ($pollId === null || $optionIndex === null) {
        echo json_encode([
            'success' => false,
            'error' => 'Poll ID and option index are required'
        ]);
        exit;
    }
    
    try {
        // Check if poll exists and is active
        $stmt = $pdo->prepare("
            SELECT id, options FROM live_polls 
            WHERE id = :poll_id AND status = 'active' AND ends_at > NOW()
        ");
        $stmt->execute([':poll_id' => $pollId]);
        $poll = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$poll) {
            echo json_encode([
                'success' => false,
                'error' => 'Poll not found or not active'
            ]);
            exit;
        }
        
        // Check if option index is valid
        $options = json_decode($poll['options'], true);
        if (!isset($options[$optionIndex])) {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid option index'
            ]);
            exit;
        }
        
        // Check if user has already voted (if not multiple choice)
        $stmt = $pdo->prepare("
            SELECT id FROM poll_votes 
            WHERE poll_id = :poll_id AND user_id = :user_id
        ");
        $stmt->execute([
            ':poll_id' => $pollId,
            ':user_id' => getUserId()
        ]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => false,
                'error' => 'You have already voted in this poll'
            ]);
            exit;
        }
        
        // Record the vote
        $stmt = $pdo->prepare("
            INSERT INTO poll_votes (poll_id, user_id, option_index) 
            VALUES (:poll_id, :user_id, :option_index)
        ");
        $stmt->execute([
            ':poll_id' => $pollId,
            ':user_id' => getUserId(),
            ':option_index' => $optionIndex
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Vote recorded successfully'
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to record vote: ' . $e->getMessage()
        ]);
    }
}
?>
