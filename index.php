<?php
session_start();
require_once 'config.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : 'user';
$username = $isLoggedIn ? $_SESSION['username'] : '';

// Fetch data from database
try {
    // Get live matches - fallback to sample data if no live matches
    $liveMatches = [];
    try {
        $liveMatches = $pdo->query("
            SELECT m.*, t1.name as team1_name, t2.name as team2_name, t.name as tournament_name,
                   m.video_url
            FROM matches m 
            JOIN teams t1 ON m.team1_id = t1.id 
            JOIN teams t2 ON m.team2_id = t2.id 
            JOIN tournaments t ON m.tournament_id = t.id 
            WHERE m.video_url IS NOT NULL AND m.video_url != '' 
            ORDER BY m.scheduled_time DESC 
            LIMIT 2
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Tables might not exist, create sample data
    }
    
    // If no live matches found, create sample data for demonstration
    if (empty($liveMatches)) {
        $liveMatches = [
            [
                'id' => 1,
                'team1_name' => 'Team Phoenix',
                'team2_name' => 'Team Dragons',
                'tournament_name' => 'Championship Finals',
                'match_title' => 'Grand Finals - Best of 5',
                'match_description' => 'The ultimate showdown between two legendary teams',
                'viewer_count' => 15420,
                'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'status' => 'live',
                'round' => 'Finals'
            ]
        ];
    }
    
    // Get upcoming matches
    $upcomingMatches = $pdo->query("
        SELECT m.*, t1.name as team1_name, t2.name as team2_name, t.name as tournament_name 
        FROM matches m 
        JOIN teams t1 ON m.team1_id = t1.id 
        JOIN teams t2 ON m.team2_id = t2.id 
        JOIN tournaments t ON m.tournament_id = t.id 
        WHERE m.status = 'scheduled' 
        ORDER BY m.scheduled_time ASC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get tournament brackets
    $tournaments = $pdo->query("
        SELECT * FROM tournaments WHERE status = 'ongoing' OR status = 'upcoming' ORDER BY start_date DESC LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    
    // Get news and guides
    $news = $pdo->query("
        SELECT c.*, u.username as author_name 
        FROM content c 
        JOIN users u ON c.author_id = u.id 
        WHERE c.status = 'published' 
        ORDER BY c.published_at DESC 
        LIMIT 3
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Get recent notices (prefer published; fallback if status not available)
    $notices = [];
    try {
        $notices = $pdo->query("
            SELECT n.*, u.username as author_name 
            FROM notices n 
            JOIN users u ON n.author_id = u.id 
            WHERE n.status = 'published' 
            ORDER BY n.created_at DESC 
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        try {
            $notices = $pdo->query("
                SELECT n.*, u.username as author_name 
                FROM notices n 
                JOIN users u ON n.author_id = u.id 
                ORDER BY n.created_at DESC 
                LIMIT 5
            ")->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e3) {
            $notices = [];
        }
    }

    // Build unified latest items feed (news + notices)
    $latestItems = [];
    foreach ($news as $item) {
        $latestItems[] = [
            'source' => 'content',
            'id' => $item['id'],
            'title' => $item['title'],
            'content' => $item['content'],
            'type' => $item['type'],
            'author_name' => $item['author_name'],
            'date' => $item['published_at'] ?? ($item['created_at'] ?? null)
        ];
    }
    foreach ($notices as $n) {
        $latestItems[] = [
            'source' => 'notice',
            'id' => $n['id'],
            'title' => $n['title'],
            'content' => $n['content'],
            'type' => 'notice',
            'author_name' => $n['author_name'],
            'date' => $n['created_at'] ?? null
        ];
    }
    usort($latestItems, function($a, $b) {
        return strtotime($b['date'] ?? '1970-01-01') <=> strtotime($a['date'] ?? '1970-01-01');
    });
    $latestItems = array_slice($latestItems, 0, 3);
    
    // Get stats for dashboard
    if ($isLoggedIn && ($userRole === 'admin' || $userRole === 'super_admin')) {
        $noticesCount = $pdo->query("SELECT COUNT(*) FROM notices")->fetchColumn();
        $matchesCount = $pdo->query("SELECT COUNT(*) FROM matches")->fetchColumn();
        $usersCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
        $tournamentsCount = $pdo->query("SELECT COUNT(*) FROM tournaments")->fetchColumn();
    }
} catch (PDOException $e) {
    // Handle error gracefully
    error_log("Database error: " . $e->getMessage());
    $liveMatches = [];
    $upcomingMatches = [];
    $tournaments = [];
    $news = [];
    $notices = [];
    $latestItems = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Esports Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Oxanium:wght@400;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="polling_chat_client.js"></script>
    <script src="polls_widget.js"></script>
    <style>
        :root {
            --primary-color: #8c52ff;
            --primary-gradient: linear-gradient(135deg, #8c52ff 0%, #5ce1e6 100%);
            --secondary-color: #20c997;
            --accent-color: #ff3e85;
            --dark-color: #121212;
            --darker-color: #0a0a0a;
            --light-color: #f8f9fa;
            --card-bg: rgba(25, 25, 35, 0.85);
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background: var(--darker-color);
            min-height: 100vh;
            color: white;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Animated background elements */
        .bg-grid {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
            opacity: 0.15;
            background: 
                linear-gradient(rgba(92, 225, 230, 0.1) 1px, transparent 1px),
                linear-gradient(90deg, rgba(92, 225, 230, 0.1) 1px, transparent 1px);
            background-size: 40px 40px;
        }
        
        .floating-particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }
        
        .particle {
            position: absolute;
            border-radius: 50%;
            background: var(--primary-gradient);
            opacity: 0.2;
            animation: float 15s infinite linear;
        }
        
        @keyframes float {
            0% {
                transform: translateY(0) translateX(0) rotate(0deg);
            }
            100% {
                transform: translateY(-100vh) translateX(100px) rotate(360deg);
            }
        }
        
        /* Navigation */
        .navbar {
            background: rgba(25, 25, 35, 0.9);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
            padding: 15px 0;
        }
        
        .navbar-brand {
            font-family: 'Oxanium', cursive;
            font-weight: 700;
            color: #5ce1e6 !important;
            font-size: 1.8rem;
        }
        
        .navbar-brand i {
            color: #ffeb3b;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
            transition: all 0.3s;
            position: relative;
            padding: 8px 15px !important;
            border-radius: 5px;
            margin: 0 5px;
        }
        
        .nav-link:hover, .nav-link.active {
            color: white !important;
            background: rgba(140, 82, 255, 0.2);
        }
        
        .nav-link:hover::after, .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 15px;
            right: 15px;
            height: 2px;
            background: var(--primary-gradient);
            border-radius: 2px;
        }
        
        .dropdown-menu {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .dropdown-item {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .dropdown-item:hover {
            background: rgba(140, 82, 255, 0.2);
            color: white;
        }
        
        .btn-outline-light {
            border-color: rgba(255, 255, 255, 0.3);
            color: rgba(255, 255, 255, 0.8);
        }
        
        .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.5);
            color: white;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #7a45e0 0%, #4ec1c6 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(140, 82, 255, 0.3);
        }
        
        /* Hero Section */
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('https://images.unsplash.com/photo-1542751371-adc38448a05e?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1170&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 120px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--primary-gradient);
            opacity: 0.3;
            z-index: -1;
        }
        
        .hero-section h1 {
            font-family: 'Oxanium', cursive;
            font-weight: 700;
            font-size: 3.5rem;
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.5);
            margin-bottom: 20px;
        }
        
        .hero-section p {
            font-size: 1.2rem;
            max-width: 600px;
            margin: 0 auto 30px;
            opacity: 0.9;
        }
        
        /* Cards */
        .card {
            background: var(--card-bg);
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            color: white;
            overflow: hidden;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
        }
        
        .card-header {
            background: var(--primary-gradient);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 20px;
            font-family: 'Oxanium', cursive;
            font-weight: 600;
        }
        
        .card-body {
            padding: 25px;
        }
        
        /* Match Cards */
        .card-match {
            background: linear-gradient(135deg, rgba(140, 82, 255, 0.2) 0%, rgba(92, 225, 230, 0.2) 100%);
            border: 1px solid rgba(140, 82, 255, 0.3);
        }
        
        .card-match .team {
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .progress {
            background: rgba(255, 255, 255, 0.1);
            height: 6px;
            border-radius: 3px;
        }
        
        .progress-bar {
            background: var(--primary-gradient);
        }
        
        /* Buttons */
        .btn-light {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            transition: all 0.3s;
        }
        
        .btn-light:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            transform: translateY(-2px);
        }
        
        /* Badges */
        .badge {
            font-weight: 500;
            padding: 8px 12px;
            border-radius: 20px;
        }
        
        .live-badge {
            background: #ff3e85;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { 
                box-shadow: 0 0 0 0 rgba(255, 62, 133, 0.7);
            }
            70% { 
                box-shadow: 0 0 0 10px rgba(255, 62, 133, 0);
            }
            100% { 
                box-shadow: 0 0 0 0 rgba(255, 62, 133, 0);
            }
        }
        
        
        /* Tournament Bracket */
        .bracket-container {
            overflow-x: auto;
            padding-bottom: 15px;
        }
        
        .bracket {
            display: flex;
            min-width: 1000px;
        }
        
        .bracket-round {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-around;
            padding: 0 15px;
        }
        
        .bracket-round h6 {
            text-align: center;
            margin-bottom: 20px;
            color: #5ce1e6;
            font-family: 'Oxanium', cursive;
        }
        
        .bracket-match {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .team-win {
            color: #5ce1e6;
        }
        
        .team-loss {
            color: #ff3e85;
            opacity: 0.7;
        }
        
        /* Stream Container */
        .stream-container {
            position: relative;
            padding-bottom: 56.25%; /* 16:9 Aspect Ratio */
            height: 0;
            overflow: hidden;
            border-radius: 8px;
        }
        
        .stream-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 0;
            border-radius: 8px;
        }
        
        /* Chat System */
        .chat-container {
            height: 400px;
            overflow-y: auto;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .chat-message {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 12px 16px;
            margin-bottom: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #6c757d;
            animation: fadeInUp 0.3s ease;
            transition: all 0.3s;
        }
        
        .chat-message:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
        }
        
        .chat-message.self {
            background: linear-gradient(135deg, rgba(140, 82, 255, 0.3) 0%, rgba(92, 225, 230, 0.3) 100%);
            border-left: 4px solid #8c52ff;
        }
        
        .chat-message.admin {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.3) 0%, rgba(200, 35, 51, 0.3) 100%);
            border-left: 4px solid #dc3545;
        }
        
        .chat-message.squad_leader {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.3) 0%, rgba(224, 168, 0, 0.3) 100%);
            border-left: 4px solid #ffc107;
            color: white;
        }
        
        .chat-username {
            font-weight: bold;
            margin-right: 8px;
        }
        
        .chat-timestamp {
            font-size: 0.75rem;
            opacity: 0.7;
            float: right;
        }
        
        .chat-input-container {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 25px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .chat-input-container:focus-within {
            border-color: #8c52ff;
            box-shadow: 0 0 0 0.2rem rgba(140, 82, 255, 0.25);
        }
        
        .chat-input {
            background: transparent;
            border: none;
            outline: none;
            padding: 12px 20px;
            color: white;
        }
        
        .chat-input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .chat-send-btn {
            background: var(--primary-gradient);
            border: none;
            padding: 12px 20px;
            color: white;
            transition: all 0.3s;
        }
        
        .chat-send-btn:hover {
            background: linear-gradient(135deg, #7a45e0 0%, #4ec1c6 100%);
            transform: translateY(-1px);
        }
        
        .youtube-stats {
            background: linear-gradient(135deg, rgba(255, 0, 0, 0.3) 0%, rgba(204, 0, 0, 0.3) 100%);
            color: white;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            border: 1px solid rgba(255, 0, 0, 0.2);
        }
        
        .view-count {
            font-size: 1.2rem;
            font-weight: bold;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .typing-indicator {
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            margin-bottom: 10px;
            font-style: italic;
            color: rgba(255, 255, 255, 0.7);
            animation: fadeInUp 0.3s ease;
        }
        
        .chat-message .role-icon {
            margin-right: 5px;
            font-size: 0.9rem;
        }
        
        .chat-container::-webkit-scrollbar {
            width: 8px;
        }
        
        .chat-container::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }
        
        .chat-container::-webkit-scrollbar-thumb {
            background: rgba(140, 82, 255, 0.5);
            border-radius: 10px;
        }
        
        .chat-container::-webkit-scrollbar-thumb:hover {
            background: rgba(140, 82, 255, 0.7);
        }
        
        .typing-dots {
            display: inline-block;
        }
        
        .typing-dots::after {
            content: '';
            animation: dots 1.5s infinite;
        }
        
        @keyframes dots {
            0%, 20% { content: '.'; }
            40% { content: '..'; }
            60%, 100% { content: '...'; }
        }
        
        /* News Cards */
        .card-news {
            background: linear-gradient(135deg, rgba(46, 204, 113, 0.2) 0%, rgba(39, 174, 96, 0.2) 100%);
            border: 1px solid rgba(46, 204, 113, 0.3);
            height: 100%;
        }
        
        /* Sidebar */
        .sidebar {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
        }
        
        .sidebar h5 {
            color: #5ce1e6;
            font-family: 'Oxanium', cursive;
            margin-bottom: 20px;
            text-align: center;
            border-bottom: 2px solid rgba(92, 225, 230, 0.3);
            padding-bottom: 10px;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
            padding: 12px 15px !important;
            margin: 5px 0;
            border-radius: 8px;
            border-left: 4px solid transparent;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(140, 82, 255, 0.2);
            color: white !important;
            border-left: 4px solid #8c52ff;
        }
        
        /* Stats Cards */
        .stats-card {
            text-align: center;
            padding: 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            background: rgba(140, 82, 255, 0.1);
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #5ce1e6;
            margin-bottom: 5px;
        }
        
        .stats-label {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.7);
        }
        
        /* Footer */
        footer {
            background: rgba(25, 25, 35, 0.9);
            color: white;
            padding: 50px 0 20px;
            margin-top: 50px;
            backdrop-filter: blur(10px);
        }
        
        footer h5 {
            color: #5ce1e6;
            margin-bottom: 20px;
            font-family: 'Oxanium', cursive;
        }
        
        footer a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        footer a:hover {
            color: #5ce1e6;
            text-decoration: underline;
        }
        
        .social-icons a {
            display: inline-block;
            width: 40px;
            height: 40px;
            line-height: 40px;
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            margin-right: 10px;
            transition: all 0.3s;
        }
        
        .social-icons a:hover {
            background: var(--primary-gradient);
            transform: translateY(-3px);
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .hero-section {
                padding: 80px 0;
            }
            
            .hero-section h1 {
                font-size: 2.5rem;
            }
            
            .sidebar {
                margin-bottom: 30px;
            }
        }
        
        /* Utility Classes */
        .text-gradient {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .bg-gradient {
            background: var(--primary-gradient) !important;
        }
        
        /* Modal Styles */
        .modal-content {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .modal-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .modal-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .form-control:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: #8c52ff;
            box-shadow: 0 0 0 0.2rem rgba(140, 82, 255, 0.25);
            color: white;
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .form-select {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .form-select:focus {
            border-color: #8c52ff;
            box-shadow: 0 0 0 0.2rem rgba(140, 82, 255, 0.25);
        }
        
        /* Alert Styles */
        .alert {
            border: none;
            border-radius: 8px;
            padding: 12px 16px;
            backdrop-filter: blur(10px);
        }
        
        .alert-danger {
            background: rgba(220, 53, 69, 0.2);
            color: #ff7a7a;
        }
        
        .alert-info {
            background: rgba(23, 162, 184, 0.2);
            color: #5ce1e6;
        }
        
        .btn-close {
            filter: invert(1);
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div class="floating-particles" id="particles"></div>
    
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
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-home me-1"></i>Home
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-play-circle me-1"></i>Watch
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="watch_live_stream.php">
                                <i class="fas fa-broadcast-tower me-2"></i>Live Stream
                            </a></li>
                            <li><a class="dropdown-item" href="watch_live_match.php">
                            <i class="fas fa-trophy me-2"></i>Live Match
                            </a></li>
                            <li><a class="dropdown-item" href="watch_scrim.php">
                                <i class="fas fa-sword me-2"></i>Scrim
                            </a></li>
                            <li><a class="dropdown-item" href="watch_tournament.php">
                                <i class="fas fa-crown me-2"></i>Tournament
                            </a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="matches.php">
                            <i class="fas fa-gamepad me-1"></i>Matches
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="tournaments.php">
                            <i class="fas fa-trophy me-1"></i>Tournaments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="guides.php">
                            <i class="fas fa-book me-1"></i>Guides
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell me-1"></i>Notices
                        </a>
                        <ul class="dropdown-menu" style="min-width: 350px; max-height: 400px; overflow-y: auto;">
                            <li><h6 class="dropdown-header">Latest Notices</h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT n.id, n.title, n.content, n.created_at, u.username 
                                                    FROM notices n 
                                                    JOIN users u ON n.author_id = u.id 
                                                    WHERE n.status = 'published'
                                                    ORDER BY n.created_at DESC LIMIT 5");
                                $navNotices = $stmt->fetchAll();
                                
                                if ($navNotices) {
                                    foreach ($navNotices as $notice) {
                                        $shortContent = strlen($notice['content']) > 80 ? substr($notice['content'], 0, 80) . '...' : $notice['content'];
                                        echo '<li>';
                                        echo '<a class="dropdown-item" href="notice.php?id=' . $notice['id'] . '" style="white-space: normal; padding: 12px 16px;">';
                                        echo '<strong class="d-block mb-1">' . htmlspecialchars($notice['title']) . '</strong>';
                                        echo '<small class="text-muted d-block mb-1">' . htmlspecialchars($shortContent) . '</small>';
                                        echo '<small class="text-muted">By ' . htmlspecialchars($notice['username']) . ' - ' . date('M j, Y', strtotime($notice['created_at'])) . '</small>';
                                        echo '</a>';
                                        echo '</li>';
                                        if ($notice !== end($navNotices)) {
                                            echo '<li><hr class="dropdown-divider"></li>';
                                        }
                                    }
                                    echo '<li><hr class="dropdown-divider"></li>';
                                    echo '<li><a class="dropdown-item text-center fw-bold" href="news.php">View All Notices</a></li>';
                                } else {
                                    echo '<li><span class="dropdown-item-text text-muted">No notices available</span></li>';
                                }
                            } catch (PDOException $e) {
                                echo '<li><span class="dropdown-item-text text-muted">Error loading notices</span></li>';
                            }
                            ?>
                        </ul>
                    </li>
                    <?php if ($isLoggedIn): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#userSearchModal">
                            <i class="fas fa-search me-1"></i>Find Players
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="dropdown me-3 language-selector">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-globe me-1"></i> EN
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">English</a></li>
                            <li><a class="dropdown-item" href="#">Indonesian</a></li>
                            <li><a class="dropdown-item" href="#">Spanish</a></li>
                        </ul>
                    </div>
                    <?php if ($isLoggedIn): ?>
                        <div class="dropdown">
                            <a class="btn btn-outline-light dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($username); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                                <?php if ($userRole === 'admin' || $userRole === 'super_admin'): ?>
                                    <li><a class="dropdown-item" href="admin_dashboard.php">Admin Dashboard</a></li>
                                <?php elseif ($userRole === 'squad_leader'): ?>
                                    <li><a class="dropdown-item" href="squad_leader_dashboard.php">Squad Leader Dashboard</a></li>
                                    <li><a class="dropdown-item" href="user_dashboard.php">User Dashboard</a></li>
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

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <h1 class="display-4 fw-bold mb-4">The Ultimate Esports Experience with ML HUB</h1>
            <p class="lead mb-4">Join tournaments, compete with teams, and connect with the gaming community</p>
            <a href="matches.php" class="btn btn-primary btn-lg me-2"><i class="fas fa-gamepad me-1"></i> View Matches</a>
            <a href="tournaments.php" class="btn btn-outline-light btn-lg"><i class="fas fa-trophy me-1"></i> Join Tournament</a>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container mt-5">
        <div class="row">
            <!-- Left Sidebar (Admin Panel) -->
            <?php if ($isLoggedIn && ($userRole === 'admin' || $userRole === 'super_admin')): ?>
            <div class="col-lg-3 d-none d-lg-block">
                <div class="sidebar">
                    <h5 class="text-center mb-4">Admin Panel</h5>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="admin_dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_notices.php">
                                <i class="fas fa-bullhorn me-2"></i> Notices
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_tournaments.php">
                                <i class="fas fa-trophy me-2"></i> Tournaments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_matches.php">
                                <i class="fas fa-gamepad me-2"></i> Matches
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_users.php">
                                <i class="fas fa-users me-2"></i> Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_content.php">
                                <i class="fas fa-newspaper me-2"></i> News & Guides
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="fas fa-cog me-2"></i> Settings
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link text-danger" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <!-- Main Content Area -->
            <div class="<?php echo ($isLoggedIn && ($userRole === 'admin' || $userRole === 'super_admin')) ? 'col-lg-9' : 'col-12'; ?>">
                <!-- Stats Overview (for admin users) -->
                <?php if ($isLoggedIn && ($userRole === 'admin' || $userRole === 'super_admin')): ?>
                <div class="row mb-4">
                    <div class="col-md-3 col-6">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $noticesCount ?? 0; ?></div>
                            <div class="stats-label">Notices</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $matchesCount ?? 0; ?></div>
                            <div class="stats-label">Matches</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $usersCount ?? 0; ?></div>
                            <div class="stats-label">Users</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $tournamentsCount ?? 0; ?></div>
                            <div class="stats-label">Tournaments</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>


              
                <!-- YouTube Stream Section -->
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fab fa-youtube me-2"></i>Live Stream</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($liveMatches)): ?>
                            <?php 
                            $currentLiveMatch = $liveMatches[0]; // Get the most recent live match
                            
                            // Convert YouTube URL to embed URL
                            function getYouTubeEmbedUrl($url) {
                                if (empty($url)) return null;
                                
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
                            
                            $embedUrl = getYouTubeEmbedUrl($currentLiveMatch['video_url'] ?? '');
                            ?>
                            <div class="stream-container">
                                <?php if ($embedUrl): ?>
                                    <iframe src="<?php echo htmlspecialchars($embedUrl); ?>?rel=0&autoplay=1" allowfullscreen></iframe>
                                <?php else: ?>
                                    <iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ?rel=0&autoplay=1" allowfullscreen></iframe>
                                <?php endif; ?>
                            </div>
                            
                            <div class="p-3">
                                <?php if ($embedUrl): ?>
                                    <h5><?php echo htmlspecialchars($currentLiveMatch['team1_name'] . ' vs ' . $currentLiveMatch['team2_name']); ?></h5>
                                    <p><?php echo htmlspecialchars($currentLiveMatch['tournament_name'] . ' - ' . ($currentLiveMatch['round'] ?: 'Match')); ?></p>
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <span class="badge bg-danger me-2">LIVE</span>
                                            <span class="text-muted"><?php echo number_format(rand(1000, 50000)); ?> viewers</span>
                                        </div>
                                        <div>
                                            <a href="watch.php?id=<?php echo $currentLiveMatch['id']; ?>" class="btn btn-outline-danger btn-sm me-2">
                                                <i class="fas fa-play me-1"></i> Watch Full Screen
                                            </a>
                                            <button class="btn btn-outline-secondary btn-sm"><i class="fas fa-share-alt me-1"></i> Share</button>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <h5>Demo Tournament Stream</h5>
                                    <p>Sample esports content - Live matches will appear here when available</p>
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <span class="badge bg-warning me-2">DEMO</span>
                                            <span class="text-muted">1,234 viewers</span>
                                        </div>
                                        <div>
                                            <a href="streaming_hub.php" class="btn btn-outline-danger btn-sm me-2">
                                                <i class="fas fa-play me-1"></i> View Streaming Hub
                                            </a>
                                            <button class="btn btn-outline-secondary btn-sm"><i class="fas fa-share-alt me-1"></i> Share</button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="stream-container">
                                <div class="no-stream" style="height: 400px; display: flex; align-items: center; justify-content: center; background: #000; color: white;">
                                    <div class="text-center">
                                        <i class="fas fa-video-slash fa-3x mb-3"></i>
                                        <h5>No Live Matches Currently</h5>
                                        <p>Check back later for exciting live matches</p>
                                        <a href="matches.php" class="btn btn-outline-light mt-2">View All Matches</a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>


              

                <!-- Enhanced Chat & Polls System -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-comments me-2"></i>Live Chat</h5>
                                <div class="chat-controls">
                                    <!-- YouTube Stats -->
                                    <div class="youtube-stats" id="youtube-stats" style="display: none;">
                                        <div class="d-flex align-items-center">
                                            <span><i class="fab fa-youtube me-2"></i></span>
                                            <span class="view-count" id="view-count">Loading...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div id="enhanced-chat-container" style="height: 400px;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div id="polls-widget-container"></div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5>EsportsHub</h5>
                    <p>The ultimate platform for esports enthusiasts. Watch matches, join tournaments, and connect with the gaming community.</p>
                    <div class="social-icons">
                        <a href="#" class="text-white me-2"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-youtube"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-discord"></i></a>
                    </div>
                </div>
                <div class="col-md-2 mb-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-white">Home</a></li>
                        <li><a href="matches.php" class="text-white">Matches</a></li>
                        <li><a href="tournaments.php" class="text-white">Tournaments</a></li>
                        <li><a href="news.php" class="text-white">News</a></li>
                    </ul>
                </div>
                <div class="col-md-2 mb-4">
                    <h5>Support</h5>
                    <ul class="list-unstyled">
                        <li><a href="help.php" class="text-white">Help Center</a></li>
                        <li><a href="faq.php" class="text-white">FAQ</a></li>
                        <li><a href="contact.php" class="text-white">Contact Us</a></li>
                        <li><a href="privacy.php" class="text-white">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-4">
                    <h5>Subscribe to Newsletter</h5>
                    <p>Get the latest updates on tournaments and matches</p>
                    <form action="subscribe.php" method="POST">
                        <div class="input-group">
                            <input type="email" name="email" class="form-control" placeholder="Your email address" required>
                            <button type="submit" class="btn btn-primary">Subscribe</button>
                        </div>
                    </form>
                </div>
            </div>
            <hr class="bg-light">
            <div class="text-center py-3">
                <p class="mb-0">&copy; 2023 EsportsHub. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Create floating particles
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
        
        $(document).ready(function() {
            // Create particles
            createParticles();
            
            // Enhanced Chat & Polls System
            let chatClient = null;
            let pollsWidget = null;
            let youtubeUrl = '';

            // Initialize enhanced chat system
            <?php if ($isLoggedIn): ?>
            chatClient = new PollingChatClient('chat_api.php', 1, <?php echo $_SESSION['user_id']; ?>, '<?php echo addslashes($username); ?>', '<?php echo $userRole; ?>');
            
            // Initialize chat UI
            const chatUI = new SimpleChatUI(chatClient, 'enhanced-chat-container');
            
            // Initialize polls widget
            pollsWidget = initializePollsWidget('polls-widget-container', {
                apiUrl: 'live_polls.php',
                roomId: 1,
                userId: <?php echo $_SESSION['user_id']; ?>,
                username: '<?php echo addslashes($username); ?>',
                userRole: '<?php echo $userRole; ?>',
                theme: 'dark',
                autoRefresh: true,
                refreshInterval: 30000
            });
            <?php else: ?>
            // Show login message for non-logged in users
            document.getElementById('enhanced-chat-container').innerHTML = `
                <div class="d-flex align-items-center justify-content-center h-100">
                    <div class="text-center">
                        <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Join the Conversation</h5>
                        <p class="text-muted mb-3">Please login to participate in live chat</p>
                        <a href="login.php" class="btn btn-primary">Login</a>
                        <a href="register.php" class="btn btn-outline-secondary ms-2">Register</a>
                    </div>
                </div>
            `;
            
            // Show polls widget for non-logged users (read-only)
            pollsWidget = initializePollsWidget('polls-widget-container', {
                apiUrl: 'live_polls.php',
                roomId: 1,
                userId: null,
                username: 'Guest',
                userRole: 'guest',
                theme: 'dark',
                allowVoting: false,
                autoRefresh: true,
                refreshInterval: 30000
            });
            <?php endif; ?>
            
            // Check for YouTube URL in live matches
            checkYouTubeStream();
            
            // Set up YouTube stats updates
            setInterval(updateYouTubeStats, 15000);
            
            // Simulate live match updates
            setInterval(function() {
                const progressBar = $('.progress-bar');
                
                progressBar.each(function() {
                    const currentStyle = $(this).attr('style') || '';
                    const widthMatch = currentStyle.match(/width:\s*(\d+)%/);
                    let currentWidth = widthMatch ? parseInt(widthMatch[1]) : 0;
                    
                    if (currentWidth < 100) {
                        const newWidth = currentWidth + 1;
                        $(this).css('width', newWidth + '%');
                        $(this).closest('.card-match').find('.d-flex.justify-content-between small:last-child').text(newWidth + '% Complete');
                    }
                });
            }, 5000);
        });
            
            // Remove old messages if too many
            const messages = $('#chat-messages .chat-message');
            if (messages.length > 100) {
                messages.first().fadeOut(200, function() {
                    $(this).remove();
                });
            }
        }

        function showTypingIndicator() {
            $('#typing-indicator').show();
            scrollToBottom();
        }

        function hideTypingIndicator() {
            $('#typing-indicator').hide();
        }

        function scrollToBottom() {
            const chatContainer = $('#chat-messages')[0];
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }

        function isScrolledToBottom() {
            const chatContainer = $('#chat-messages')[0];
            return chatContainer.scrollTop + chatContainer.clientHeight >= chatContainer.scrollHeight - 10;
        }

        function checkYouTubeStream() {
            // Check if there's a live match with YouTube URL
            <?php if (!empty($liveMatches) && !empty($liveMatches[0]['video_url'])): ?>
                youtubeUrl = '<?php echo addslashes($liveMatches[0]['video_url']); ?>';
                $('#youtube-stats').show();
                updateYouTubeStats();
            <?php endif; ?>
        }
        
        // Enhanced event handlers for polls integration
        document.addEventListener('showCreatePoll', function(event) {
            // Handle poll creation modal
            showCreatePollModal(event.detail.roomId);
        });
        
        document.addEventListener('showPollDetails', function(event) {
            // Handle poll details modal
            showPollDetailsModal(event.detail.pollId);
        });
        
        function showCreatePollModal(roomId) {
            // This would open a modal for creating polls
            // For now, redirect to polls manager
            window.open('live_polls_manager.php?room_id=' + roomId, '_blank', 'width=800,height=600');
        }
        
        function showPollDetailsModal(pollId) {
            // This would show detailed poll analytics
            // For now, show basic info
            alert('Poll details for ID: ' + pollId + '\nDetailed analytics coming soon!');
        }

        function updateYouTubeStats() {
            if (youtubeUrl) {
                $.get('youtube_api.php', { url: youtubeUrl })
                    .done(function(response) {
                        if (response.success) {
                            $('#view-count').text(response.formattedViewCount + ' views');
                        } else {
                            $('#view-count').text('Stats unavailable');
                        }
                    })
                    .fail(function() {
                        $('#view-count').text('Connection error');
                    });
            } else {
                $('#view-count').text('No active stream');
            }
        }


    </script>

    <!-- User Search Modal -->
    <?php if ($isLoggedIn): ?>
    <div class="modal fade" id="userSearchModal" tabindex="-1" aria-labelledby="userSearchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userSearchModalLabel">
                        <i class="fas fa-search me-2"></i>Find Players
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Search Form -->
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" id="userSearchInput" class="form-control" 
                                       placeholder="Search by username, email, or full name...">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select id="roleFilter" class="form-select">
                                <option value="">All Roles</option>
                                <option value="user">Users</option>
                                <option value="squad_leader">Squad Leaders</option>
                                <option value="moderator">Moderators</option>
                                <?php if (in_array($userRole, ['admin', 'super_admin'])): ?>
                                <option value="admin">Admins</option>
                                <option value="super_admin">Super Admins</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Search Results -->
                    <div id="searchResults">
                        <div class="text-center text-muted">
                            <i class="fas fa-users fa-3x mb-3"></i>
                            <p>Enter a search term to find other players</p>
                        </div>
                    </div>

                    <!-- Loading Indicator -->
                    <div id="searchLoading" class="text-center d-none">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Searching players...</p>
                    </div>

                    <!-- Pagination -->
                    <nav id="searchPagination" class="d-none">
                        <ul class="pagination justify-content-center">
                            <!-- Pagination will be inserted here -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <script>
        // User Search Functionality
        let currentSearchPage = 0;
        let searchTimeout = null;
        const searchLimit = 10;

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('userSearchInput');
            const roleFilter = document.getElementById('roleFilter');

            // Search on input with debounce
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    currentSearchPage = 0;
                    performSearch();
                }, 500);
            });

            // Search on role filter change
            roleFilter.addEventListener('change', function() {
                currentSearchPage = 0;
                performSearch();
            });
        });

        function performSearch() {
            const searchTerm = document.getElementById('userSearchInput').value.trim();
            const roleFilter = document.getElementById('roleFilter').value;

            if (searchTerm.length < 2 && !roleFilter) {
                showEmptyState();
                return;
            }

            showLoading();

            const params = new URLSearchParams({
                search: searchTerm,
                role: roleFilter,
                limit: searchLimit,
                offset: currentSearchPage * searchLimit
            });

            fetch(`user_search_api.php?${params}`)
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        displaySearchResults(data.users, data.total);
                        updatePagination(data.total);
                    } else {
                        showError(data.error || 'Search failed');
                    }
                })
                .catch(error => {
                    hideLoading();
                    showError('Network error occurred');
                    console.error('Search error:', error);
                });
        }

        function displaySearchResults(users, total) {
            const resultsContainer = document.getElementById('searchResults');
            
            if (users.length === 0) {
                resultsContainer.innerHTML = `
                    <div class="text-center text-muted">
                        <i class="fas fa-user-slash fa-3x mb-3"></i>
                        <p>No players found matching your search</p>
                    </div>
                `;
                return;
            }

            let html = `<div class="mb-3"><small class="text-muted">Found ${total} player(s)</small></div>`;
            
            users.forEach(user => {
                const roleColor = getRoleColor(user.role);
                const roleIcon = getRoleIcon(user.role);
                const avatar = user.avatar_url || 'https://via.placeholder.com/50x50?text=' + user.username.charAt(0).toUpperCase();
                
                html += `
                    <div class="card mb-2">
                        <div class="card-body p-3">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <img src="${avatar}" alt="${user.username}" class="rounded-circle" width="50" height="50" 
                                         onerror="this.src='https://via.placeholder.com/50x50?text=${user.username.charAt(0).toUpperCase()}'">
                                </div>
                                <div class="col">
                                    <div class="d-flex align-items-center mb-1">
                                        <h6 class="mb-0 me-2">${user.username}</h6>
                                        <span class="badge ${roleColor}">${roleIcon} ${user.role.replace('_', ' ').toUpperCase()}</span>
                                    </div>
                                    ${user.full_name ? `<p class="mb-1 text-muted">${user.full_name}</p>` : ''}
                                    ${user.bio ? `<p class="mb-0 small text-muted">${user.bio}</p>` : ''}
                                    ${user.email && <?= json_encode(in_array($userRole, ['admin', 'super_admin'])) ?> ? `<p class="mb-0 small text-info">${user.email}</p>` : ''}
                                </div>
                                <div class="col-auto">
                                    <button class="btn btn-outline-primary btn-sm" onclick="viewProfile(${user.id})">
                                        <i class="fas fa-eye me-1"></i>View Profile
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            resultsContainer.innerHTML = html;
        }

        function getRoleColor(role) {
            const colors = {
                'user': 'bg-primary',
                'squad_leader': 'bg-info',
                'moderator': 'bg-warning',
                'admin': 'bg-success',
                'super_admin': 'bg-danger'
            };
            return colors[role] || 'bg-secondary';
        }

        function getRoleIcon(role) {
            const icons = {
                'user': '<i class="fas fa-user"></i>',
                'squad_leader': '<i class="fas fa-users"></i>',
                'moderator': '<i class="fas fa-shield-alt"></i>',
                'admin': '<i class="fas fa-crown"></i>',
                'super_admin': '<i class="fas fa-star"></i>'
            };
            return icons[role] || '<i class="fas fa-user"></i>';
        }

        function updatePagination(total) {
            const totalPages = Math.ceil(total / searchLimit);
            const paginationContainer = document.getElementById('searchPagination');
            
            if (totalPages <= 1) {
                paginationContainer.classList.add('d-none');
                return;
            }

            paginationContainer.classList.remove('d-none');
            const pagination = paginationContainer.querySelector('.pagination');
            
            let html = '';
            
            // Previous button
            html += `
                <li class="page-item ${currentSearchPage === 0 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="changePage(${currentSearchPage - 1})">Previous</a>
                </li>
            `;
            
            // Page numbers
            for (let i = 0; i < totalPages; i++) {
                if (i === currentSearchPage || i === 0 || i === totalPages - 1 || Math.abs(i - currentSearchPage) <= 1) {
                    html += `
                        <li class="page-item ${i === currentSearchPage ? 'active' : ''}">
                            <a class="page-link" href="#" onclick="changePage(${i})">${i + 1}</a>
                        </li>
                    `;
                } else if (i === currentSearchPage - 2 || i === currentSearchPage + 2) {
                    html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
            }
            
            // Next button
            html += `
                <li class="page-item ${currentSearchPage === totalPages - 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="changePage(${currentSearchPage + 1})">Next</a>
                </li>
            `;
            
            pagination.innerHTML = html;
        }

        function changePage(page) {
            if (page >= 0) {
                currentSearchPage = page;
                performSearch();
            }
        }

        function viewProfile(userId) {
            window.open(`profile.php?user_id=${userId}`, '_blank');
        }

        function showLoading() {
            document.getElementById('searchLoading').classList.remove('d-none');
            document.getElementById('searchResults').classList.add('d-none');
            document.getElementById('searchPagination').classList.add('d-none');
        }

        function hideLoading() {
            document.getElementById('searchLoading').classList.add('d-none');
            document.getElementById('searchResults').classList.remove('d-none');
        }

        function showEmptyState() {
            document.getElementById('searchResults').innerHTML = `
                <div class="text-center text-muted">
                    <i class="fas fa-users fa-3x mb-3"></i>
                    <p>Enter a search term to find other players</p>
                </div>
            `;
            document.getElementById('searchPagination').classList.add('d-none');
        }

        function showError(message) {
            document.getElementById('searchResults').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>${message}
                </div>
            `;
        }
    </script>
    <?php endif; ?>
</body>
</html>