<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

function getYouTubeVideoId($url) {
    $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/';
    preg_match($pattern, $url, $matches);
    return isset($matches[1]) ? $matches[1] : null;
}

function getYouTubeViewCount($videoId) {
    // Note: You'll need to get a YouTube Data API key from Google Cloud Console
    // For now, we'll return mock data or use a simple scraping method
    
    // Mock data for demonstration
    $mockViewCounts = [
        'dQw4w9WgXcQ' => 1234567890, // Rick Roll
        'kJQP7kiw5Fk' => 987654321,  // Despacito
        // Add more mock data as needed
    ];
    
    if (isset($mockViewCounts[$videoId])) {
        return $mockViewCounts[$videoId] + rand(1, 100); // Simulate real-time changes
    }
    
    // For real implementation, uncomment below and add your API key
    /*
    $apiKey = 'YOUR_YOUTUBE_API_KEY';
    $url = "https://www.googleapis.com/youtube/v3/videos?id={$videoId}&key={$apiKey}&part=statistics";
    
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    
    if (isset($data['items'][0]['statistics']['viewCount'])) {
        return (int)$data['items'][0]['statistics']['viewCount'];
    }
    */
    
    return rand(100000, 999999); // Random view count for demo
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && isset($_GET['url'])) {
    $youtubeUrl = $_GET['url'];
    $videoId = getYouTubeVideoId($youtubeUrl);
    
    if ($videoId) {
        $viewCount = getYouTubeViewCount($videoId);
        echo json_encode([
            'success' => true,
            'videoId' => $videoId,
            'viewCount' => $viewCount,
            'formattedViewCount' => number_format($viewCount)
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid YouTube URL']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'YouTube URL parameter required']);
}
?>
