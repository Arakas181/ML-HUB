<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    $userId = $_SESSION['user_id'] ?? null;
    $type = $_GET['type'] ?? 'tournaments';
    $limit = min((int)($_GET['limit'] ?? 10), 20);
    
    switch ($type) {
        case 'tournaments':
            $recommendations = getPersonalizedTournaments($userId, $limit);
            break;
        case 'matches':
            $recommendations = getRecommendedMatches($userId, $limit);
            break;
        case 'streams':
            $recommendations = getRecommendedStreams($userId, $limit);
            break;
        case 'players':
            $recommendations = getRecommendedPlayers($userId, $limit);
            break;
        default:
            throw new Exception('Invalid recommendation type');
    }
    
    echo json_encode([
        'success' => true,
        'type' => $type,
        'recommendations' => $recommendations,
        'personalized' => $userId !== null
    ]);
    
} catch (Exception $e) {
    error_log('Recommendation engine error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to get recommendations']);
}

function getPersonalizedTournaments($userId, $limit) {
    global $pdo;
    
    if ($userId) {
        // Get user preferences and history
        $userPrefs = getUserPreferences($userId);
        $participationHistory = getUserTournamentHistory($userId);
        
        // Build personalized query
        $whereConditions = ["t.status = 'upcoming'"];
        $params = [];
        $orderBy = "ORDER BY ";
        $orderParts = [];
        
        // Prefer user's favorite games
        if (!empty($userPrefs['favorite_games'])) {
            $gamesList = implode(',', array_fill(0, count($userPrefs['favorite_games']), '?'));
            $whereConditions[] = "(t.game IN ($gamesList) OR t.game IS NULL)";
            $params = array_merge($params, $userPrefs['favorite_games']);
            $orderParts[] = "CASE WHEN t.game IN ($gamesList) THEN 1 ELSE 2 END";
        }
        
        // Match skill level
        if (!empty($userPrefs['skill_level'])) {
            $whereConditions[] = "(t.skill_level = ? OR t.skill_level = 'mixed')";
            $params[] = $userPrefs['skill_level'];
            $orderParts[] = "CASE WHEN t.skill_level = ? THEN 1 ELSE 2 END";
            $params[] = $userPrefs['skill_level'];
        }
        
        // Prefer tournaments with similar entry fees to user's history
        if (!empty($participationHistory['avg_entry_fee'])) {
            $avgFee = $participationHistory['avg_entry_fee'];
            $orderParts[] = "ABS(t.entry_fee - $avgFee)";
        }
        
        // Boost tournaments with friends participating
        $friendsSubquery = "
            SELECT COUNT(*) FROM tournament_registrations tr2 
            JOIN user_friends uf ON tr2.user_id = uf.friend_id 
            WHERE tr2.tournament_id = t.id AND uf.user_id = ?
        ";
        $params[] = $userId;
        $orderParts[] = "($friendsSubquery) DESC";
        
        if (!empty($orderParts)) {
            $orderBy .= implode(', ', $orderParts) . ", t.start_date ASC";
        } else {
            $orderBy = "ORDER BY t.start_date ASC";
        }
        
    } else {
        // Non-personalized recommendations
        $whereConditions = ["t.status = 'upcoming'"];
        $params = [];
        $orderBy = "ORDER BY t.prize_pool DESC, t.start_date ASC";
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $stmt = $pdo->prepare("
        SELECT t.*, 
               COUNT(tr.id) as participant_count,
               CASE WHEN t.start_date > NOW() THEN 'upcoming'
                    WHEN t.end_date < NOW() THEN 'completed'
                    ELSE 'ongoing' END as status,
               ($friendsSubquery) as friends_participating
        FROM tournaments t
        LEFT JOIN tournament_registrations tr ON t.id = tr.tournament_id
        WHERE $whereClause
        GROUP BY t.id
        $orderBy
        LIMIT ?
    ");
    
    $params[] = $limit;
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRecommendedMatches($userId, $limit) {
    global $pdo;
    
    $whereConditions = ["m.match_date > NOW()"];
    $params = [];
    $orderBy = "ORDER BY m.match_date ASC";
    
    if ($userId) {
        $userPrefs = getUserPreferences($userId);
        
        // Prefer matches from user's favorite tournaments
        if (!empty($userPrefs['favorite_games'])) {
            $gamesList = implode(',', array_fill(0, count($userPrefs['favorite_games']), '?'));
            $whereConditions[] = "(t.game IN ($gamesList) OR t.game IS NULL)";
            $params = array_merge($params, $userPrefs['favorite_games']);
        }
        
        // Boost matches from tournaments user is registered for
        $orderBy = "ORDER BY 
            CASE WHEN tr_user.user_id IS NOT NULL THEN 1 ELSE 2 END,
            m.match_date ASC";
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $stmt = $pdo->prepare("
        SELECT m.*, t.name as tournament_name, t.game,
               CASE WHEN tr_user.user_id IS NOT NULL THEN 1 ELSE 0 END as user_registered
        FROM matches m
        LEFT JOIN tournaments t ON m.tournament_id = t.id
        LEFT JOIN tournament_registrations tr_user ON t.id = tr_user.tournament_id AND tr_user.user_id = ?
        WHERE $whereClause
        $orderBy
        LIMIT ?
    ");
    
    $params = array_merge([$userId], $params, [$limit]);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRecommendedStreams($userId, $limit) {
    global $pdo;
    
    // This would integrate with live streaming data
    // For now, return sample data structure
    return [
        [
            'id' => 1,
            'title' => 'Championship Finals Live',
            'streamer' => 'ProStreamer',
            'game' => 'Valorant',
            'viewers' => 15420,
            'thumbnail' => 'stream1.jpg',
            'platform' => 'twitch',
            'is_live' => true
        ],
        [
            'id' => 2,
            'title' => 'Tutorial: Advanced Tactics',
            'streamer' => 'CoachGaming',
            'game' => 'CS:GO',
            'viewers' => 3240,
            'thumbnail' => 'stream2.jpg',
            'platform' => 'youtube',
            'is_live' => true
        ]
    ];
}

function getRecommendedPlayers($userId, $limit) {
    global $pdo;
    
    $whereConditions = ["u.id != ?"];
    $params = [$userId ?? 0];
    $orderBy = "ORDER BY u.created_at DESC";
    
    if ($userId) {
        // Recommend players with similar skill levels and games
        $userPrefs = getUserPreferences($userId);
        
        if (!empty($userPrefs['skill_level'])) {
            // This would require a user_skills or user_games table
            $orderBy = "ORDER BY RAND()"; // Simplified for now
        }
        
        // Exclude already connected friends
        $whereConditions[] = "u.id NOT IN (
            SELECT friend_id FROM user_friends WHERE user_id = ?
        )";
        $params[] = $userId;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.role, 
               COALESCE(u.full_name, u.username) as display_name,
               u.avatar_url, u.bio,
               COUNT(tr.id) as tournaments_participated
        FROM users u
        LEFT JOIN tournament_registrations tr ON u.id = tr.user_id
        WHERE $whereClause
        GROUP BY u.id
        $orderBy
        LIMIT ?
    ");
    
    $params[] = $limit;
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserPreferences($userId) {
    global $pdo;
    
    // Get user's tournament participation history to infer preferences
    $stmt = $pdo->prepare("
        SELECT t.game, t.skill_level, t.entry_fee
        FROM tournament_registrations tr
        JOIN tournaments t ON tr.tournament_id = t.id
        WHERE tr.user_id = ?
        ORDER BY tr.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$userId]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $preferences = [
        'favorite_games' => [],
        'skill_level' => null,
        'avg_entry_fee' => 0
    ];
    
    if (!empty($history)) {
        // Extract favorite games
        $games = array_column($history, 'game');
        $gameFreq = array_count_values(array_filter($games));
        arsort($gameFreq);
        $preferences['favorite_games'] = array_keys(array_slice($gameFreq, 0, 3));
        
        // Determine preferred skill level
        $skillLevels = array_column($history, 'skill_level');
        $skillFreq = array_count_values(array_filter($skillLevels));
        if (!empty($skillFreq)) {
            $preferences['skill_level'] = array_keys($skillFreq)[0];
        }
        
        // Calculate average entry fee
        $entryFees = array_filter(array_column($history, 'entry_fee'));
        if (!empty($entryFees)) {
            $preferences['avg_entry_fee'] = array_sum($entryFees) / count($entryFees);
        }
    }
    
    return $preferences;
}

function getUserTournamentHistory($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_tournaments,
               AVG(t.entry_fee) as avg_entry_fee,
               AVG(t.prize_pool) as avg_prize_pool
        FROM tournament_registrations tr
        JOIN tournaments t ON tr.tournament_id = t.id
        WHERE tr.user_id = ?
    ");
    $stmt->execute([$userId]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
        'total_tournaments' => 0,
        'avg_entry_fee' => 0,
        'avg_prize_pool' => 0
    ];
}
?>
