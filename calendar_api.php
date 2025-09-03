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
    $start = $_GET['start'] ?? '';
    $end = $_GET['end'] ?? '';
    
    if (empty($start) || empty($end)) {
        throw new Exception('Start and end dates are required');
    }
    
    $events = [];
    
    // Get tournaments
    $stmt = $pdo->prepare("
        SELECT 
            id, name, description, start_date, end_date, 
            prize_pool, entry_fee, status, game, skill_level
        FROM tournaments 
        WHERE start_date <= ? AND end_date >= ?
        ORDER BY start_date ASC
    ");
    $stmt->execute([$end, $start]);
    $tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($tournaments as $tournament) {
        $events[] = [
            'id' => 'tournament_' . $tournament['id'],
            'title' => $tournament['name'],
            'start' => $tournament['start_date'],
            'end' => $tournament['end_date'],
            'className' => 'event-tournament',
            'extendedProps' => [
                'type' => 'tournament',
                'id' => $tournament['id'],
                'description' => $tournament['description'],
                'prize_pool' => $tournament['prize_pool'],
                'entry_fee' => $tournament['entry_fee'],
                'status' => $tournament['status'],
                'game' => $tournament['game'],
                'skill_level' => $tournament['skill_level']
            ]
        ];
    }
    
    // Get matches
    $stmt = $pdo->prepare("
        SELECT 
            id, team1, team2, match_date, status, tournament_id
        FROM matches 
        WHERE match_date BETWEEN ? AND ?
        ORDER BY match_date ASC
    ");
    $stmt->execute([$start, $end]);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($matches as $match) {
        $events[] = [
            'id' => 'match_' . $match['id'],
            'title' => $match['team1'] . ' vs ' . $match['team2'],
            'start' => $match['match_date'],
            'className' => 'event-match',
            'extendedProps' => [
                'type' => 'match',
                'id' => $match['id'],
                'team1' => $match['team1'],
                'team2' => $match['team2'],
                'status' => $match['status'],
                'tournament_id' => $match['tournament_id']
            ]
        ];
    }
    
    // Get live streams (if table exists)
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id, title, description, scheduled_start, scheduled_end, 
                platform, status
            FROM live_streams 
            WHERE scheduled_start BETWEEN ? AND ?
            ORDER BY scheduled_start ASC
        ");
        $stmt->execute([$start, $end]);
        $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($streams as $stream) {
            $events[] = [
                'id' => 'stream_' . $stream['id'],
                'title' => $stream['title'],
                'start' => $stream['scheduled_start'],
                'end' => $stream['scheduled_end'],
                'className' => 'event-stream',
                'extendedProps' => [
                    'type' => 'stream',
                    'id' => $stream['id'],
                    'description' => $stream['description'],
                    'platform' => $stream['platform'],
                    'status' => $stream['status']
                ]
            ];
        }
    } catch (PDOException $e) {
        // live_streams table doesn't exist, skip
    }
    
    echo json_encode([
        'success' => true,
        'events' => $events
    ]);
    
} catch (Exception $e) {
    error_log('Calendar API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load calendar events']);
}
?>
