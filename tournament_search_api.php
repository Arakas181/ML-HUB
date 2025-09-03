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
    // Get search parameters
    $search = $_GET['search'] ?? '';
    $skill_level = $_GET['skill_level'] ?? '';
    $entry_fee_min = isset($_GET['entry_fee_min']) ? (float)$_GET['entry_fee_min'] : null;
    $entry_fee_max = isset($_GET['entry_fee_max']) ? (float)$_GET['entry_fee_max'] : null;
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $format = $_GET['format'] ?? '';
    $status = $_GET['status'] ?? '';
    $game = $_GET['game'] ?? '';
    $region = $_GET['region'] ?? '';
    $sort_by = $_GET['sort_by'] ?? 'start_date';
    $sort_order = $_GET['sort_order'] ?? 'ASC';
    $limit = min((int)($_GET['limit'] ?? 20), 100);
    $offset = (int)($_GET['offset'] ?? 0);

    // Build WHERE conditions
    $whereConditions = [];
    $params = [];

    // Text search in name and description
    if (!empty($search)) {
        $whereConditions[] = "(t.name LIKE ? OR t.description LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // Skill level filter
    if (!empty($skill_level) && in_array($skill_level, ['beginner', 'amateur', 'pro', 'mixed'])) {
        $whereConditions[] = "t.skill_level = ?";
        $params[] = $skill_level;
    }

    // Entry fee range
    if ($entry_fee_min !== null) {
        $whereConditions[] = "t.entry_fee >= ?";
        $params[] = $entry_fee_min;
    }
    if ($entry_fee_max !== null) {
        $whereConditions[] = "t.entry_fee <= ?";
        $params[] = $entry_fee_max;
    }

    // Date range
    if (!empty($date_from)) {
        $whereConditions[] = "DATE(t.start_date) >= ?";
        $params[] = $date_from;
    }
    if (!empty($date_to)) {
        $whereConditions[] = "DATE(t.end_date) <= ?";
        $params[] = $date_to;
    }

    // Tournament format
    if (!empty($format) && in_array($format, ['single_elimination', 'double_elimination', 'round_robin', 'swiss', 'league'])) {
        $whereConditions[] = "t.bracket_type = ?";
        $params[] = $format;
    }

    // Status filter
    if (!empty($status) && in_array($status, ['upcoming', 'ongoing', 'completed', 'cancelled'])) {
        $whereConditions[] = "t.status = ?";
        $params[] = $status;
    }

    // Game filter
    if (!empty($game)) {
        $whereConditions[] = "t.game = ?";
        $params[] = $game;
    }

    // Region filter
    if (!empty($region)) {
        $whereConditions[] = "t.region = ?";
        $params[] = $region;
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // Validate sort parameters
    $allowedSortFields = ['start_date', 'end_date', 'name', 'prize_pool', 'entry_fee', 'max_participants', 'created_at'];
    if (!in_array($sort_by, $allowedSortFields)) {
        $sort_by = 'start_date';
    }
    if (!in_array(strtoupper($sort_order), ['ASC', 'DESC'])) {
        $sort_order = 'ASC';
    }

    // Get total count
    $countQuery = "SELECT COUNT(*) FROM tournaments t $whereClause";
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalCount = $stmt->fetchColumn();

    // Get tournaments with pagination
    $query = "
        SELECT 
            t.*,
            COUNT(tr.id) as participant_count,
            CASE 
                WHEN t.start_date > NOW() THEN 'upcoming'
                WHEN t.end_date < NOW() THEN 'completed'
                ELSE 'ongoing'
            END as computed_status
        FROM tournaments t
        LEFT JOIN tournament_registrations tr ON t.id = tr.tournament_id
        $whereClause
        GROUP BY t.id
        ORDER BY t.$sort_by $sort_order
        LIMIT ? OFFSET ?
    ";

    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format tournaments data
    foreach ($tournaments as &$tournament) {
        $tournament['prize_pool'] = (float)$tournament['prize_pool'];
        $tournament['entry_fee'] = (float)$tournament['entry_fee'];
        $tournament['participant_count'] = (int)$tournament['participant_count'];
        $tournament['max_participants'] = (int)$tournament['max_participants'];
        
        // Calculate spots remaining
        $tournament['spots_remaining'] = max(0, $tournament['max_participants'] - $tournament['participant_count']);
        
        // Format dates
        $tournament['start_date_formatted'] = date('M j, Y g:i A', strtotime($tournament['start_date']));
        $tournament['end_date_formatted'] = date('M j, Y g:i A', strtotime($tournament['end_date']));
        
        // Registration status
        $tournament['registration_open'] = (
            $tournament['computed_status'] === 'upcoming' && 
            (!$tournament['registration_deadline'] || strtotime($tournament['registration_deadline']) > time()) &&
            $tournament['spots_remaining'] > 0
        );
    }

    // Get filter options for frontend
    $filterOptions = [];
    
    // Get available skill levels
    $stmt = $pdo->query("SELECT DISTINCT skill_level FROM tournaments WHERE skill_level IS NOT NULL ORDER BY skill_level");
    $filterOptions['skill_levels'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get available games
    $stmt = $pdo->query("SELECT DISTINCT game FROM tournaments WHERE game IS NOT NULL ORDER BY game");
    $filterOptions['games'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get available regions
    $stmt = $pdo->query("SELECT DISTINCT region FROM tournaments WHERE region IS NOT NULL ORDER BY region");
    $filterOptions['regions'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get entry fee range
    $stmt = $pdo->query("SELECT MIN(entry_fee) as min_fee, MAX(entry_fee) as max_fee FROM tournaments WHERE entry_fee > 0");
    $feeRange = $stmt->fetch(PDO::FETCH_ASSOC);
    $filterOptions['entry_fee_range'] = [
        'min' => (float)($feeRange['min_fee'] ?? 0),
        'max' => (float)($feeRange['max_fee'] ?? 1000)
    ];

    echo json_encode([
        'success' => true,
        'tournaments' => $tournaments,
        'total' => $totalCount,
        'limit' => $limit,
        'offset' => $offset,
        'filter_options' => $filterOptions,
        'search_params' => [
            'search' => $search,
            'skill_level' => $skill_level,
            'entry_fee_min' => $entry_fee_min,
            'entry_fee_max' => $entry_fee_max,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'format' => $format,
            'status' => $status,
            'game' => $game,
            'region' => $region,
            'sort_by' => $sort_by,
            'sort_order' => $sort_order
        ]
    ]);

} catch (PDOException $e) {
    error_log('Tournament search error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Search failed']);
} catch (Exception $e) {
    error_log('General search error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Search failed']);
}
?>
