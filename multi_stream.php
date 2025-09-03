<?php
session_start();
require_once 'config.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : 'user';
$username = $isLoggedIn ? $_SESSION['username'] : '';

// Fetch available streams
try {
    $streams = $pdo->query("
        SELECT ls.*, u.username as streamer_name, t.name as tournament_name
        FROM live_streams ls
        JOIN users u ON ls.streamer_id = u.id
        LEFT JOIN tournaments t ON ls.tournament_id = t.id
        WHERE ls.status = 'live'
        ORDER BY ls.viewer_count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $streams = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Stream View - ML HUB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --primary-color: #6f42c1;
            --secondary-color: #20c997;
            --dark-color: #212529;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #0f0f0f;
            color: #ffffff;
            margin: 0;
            padding: 0;
        }
        
        .navbar {
            background-color: var(--dark-color);
            z-index: 1000;
        }
        
        .multi-stream-container {
            display: grid;
            gap: 5px;
            height: calc(100vh - 56px);
            padding: 5px;
        }
        
        .grid-2x2 { grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr; }
        .grid-3x3 { grid-template-columns: 1fr 1fr 1fr; grid-template-rows: 1fr 1fr 1fr; }
        .grid-pip { grid-template-columns: 3fr 1fr; grid-template-rows: 1fr; }
        .side-by-side { grid-template-columns: 1fr 1fr; grid-template-rows: 1fr; }
        
        .stream-window {
            background: #1a1a1a;
            border: 2px solid #333;
            border-radius: 8px;
            overflow: hidden;
            position: relative;
            display: flex;
            flex-direction: column;
        }
        
        .stream-window.active {
            border-color: var(--primary-color);
        }
        
        .stream-header {
            background: rgba(0, 0, 0, 0.8);
            padding: 8px 12px;
            display: flex;
            justify-content: between;
            align-items: center;
            font-size: 0.9rem;
        }
        
        .stream-content {
            flex: 1;
            position: relative;
        }
        
        .stream-iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        
        .stream-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            background: #2a2a2a;
            color: #888;
        }
        
        .controls-panel {
            position: fixed;
            top: 70px;
            right: 20px;
            background: rgba(0, 0, 0, 0.9);
            padding: 15px;
            border-radius: 10px;
            min-width: 250px;
            z-index: 1001;
        }
        
        .layout-btn {
            background: #333;
            border: 1px solid #555;
            color: white;
            padding: 8px 12px;
            margin: 2px;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .layout-btn.active {
            background: var(--primary-color);
        }
        
        .stream-selector {
            background: #333;
            border: 1px solid #555;
            color: white;
            padding: 8px;
            border-radius: 5px;
            width: 100%;
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-gamepad me-2"></i>ML HUB
            </a>
            <div class="d-flex align-items-center">
                <button class="btn btn-outline-light me-2" onclick="toggleControls()">
                    <i class="fas fa-cog"></i> Controls
                </button>
                <a href="streaming_hub.php" class="btn btn-outline-light">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </nav>

    <!-- Multi-Stream Container -->
    <div id="multiStreamContainer" class="multi-stream-container grid-2x2">
        <div class="stream-window" data-position="0">
            <div class="stream-header">
                <span>Stream 1</span>
                <button class="btn btn-sm btn-outline-light" onclick="removeStream(0)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="stream-content">
                <div class="stream-placeholder">
                    <div class="text-center">
                        <i class="fas fa-plus fa-2x mb-2"></i>
                        <p>Click to add stream</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="stream-window" data-position="1">
            <div class="stream-header">
                <span>Stream 2</span>
                <button class="btn btn-sm btn-outline-light" onclick="removeStream(1)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="stream-content">
                <div class="stream-placeholder">
                    <div class="text-center">
                        <i class="fas fa-plus fa-2x mb-2"></i>
                        <p>Click to add stream</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="stream-window" data-position="2">
            <div class="stream-header">
                <span>Stream 3</span>
                <button class="btn btn-sm btn-outline-light" onclick="removeStream(2)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="stream-content">
                <div class="stream-placeholder">
                    <div class="text-center">
                        <i class="fas fa-plus fa-2x mb-2"></i>
                        <p>Click to add stream</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="stream-window" data-position="3">
            <div class="stream-header">
                <span>Stream 4</span>
                <button class="btn btn-sm btn-outline-light" onclick="removeStream(3)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="stream-content">
                <div class="stream-placeholder">
                    <div class="text-center">
                        <i class="fas fa-plus fa-2x mb-2"></i>
                        <p>Click to add stream</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Controls Panel -->
    <div id="controlsPanel" class="controls-panel" style="display: none;">
        <h6 class="text-white mb-3">Multi-Stream Controls</h6>
        
        <div class="mb-3">
            <label class="text-white small">Layout:</label>
            <div>
                <button class="layout-btn active" onclick="changeLayout('grid-2x2')">2x2</button>
                <button class="layout-btn" onclick="changeLayout('side-by-side')">Side by Side</button>
                <button class="layout-btn" onclick="changeLayout('grid-pip')">PiP</button>
            </div>
        </div>
        
        <div class="mb-3">
            <label class="text-white small">Add Stream:</label>
            <select class="stream-selector" id="streamSelector">
                <option value="">Select a stream...</option>
                <?php foreach ($streams as $stream): ?>
                    <option value="<?php echo $stream['id']; ?>" 
                            data-title="<?php echo htmlspecialchars($stream['title']); ?>"
                            data-url="<?php echo htmlspecialchars($stream['youtube_stream_id'] ? 'https://www.youtube.com/embed/' . $stream['youtube_stream_id'] : ''); ?>">
                        <?php echo htmlspecialchars($stream['title']); ?> - <?php echo htmlspecialchars($stream['streamer_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary btn-sm w-100 mt-2" onclick="addSelectedStream()">
                Add Stream
            </button>
        </div>
        
        <div class="mb-3">
            <button class="btn btn-success btn-sm w-100" onclick="saveSession()">
                <i class="fas fa-save me-1"></i>Save Session
            </button>
            <button class="btn btn-info btn-sm w-100 mt-2" onclick="loadSession()">
                <i class="fas fa-folder-open me-1"></i>Load Session
            </button>
        </div>
    </div>

    <script>
        let currentLayout = 'grid-2x2';
        let activeStreams = {};
        let selectedPosition = 0;

        // Toggle controls panel
        function toggleControls() {
            const panel = document.getElementById('controlsPanel');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        }

        // Change layout
        function changeLayout(layout) {
            currentLayout = layout;
            const container = document.getElementById('multiStreamContainer');
            container.className = `multi-stream-container ${layout}`;
            
            // Update active button
            document.querySelectorAll('.layout-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Adjust stream windows based on layout
            const windows = document.querySelectorAll('.stream-window');
            if (layout === 'side-by-side') {
                windows.forEach((window, index) => {
                    window.style.display = index < 2 ? 'flex' : 'none';
                });
            } else if (layout === 'grid-pip') {
                windows.forEach((window, index) => {
                    window.style.display = index < 2 ? 'flex' : 'none';
                });
            } else {
                windows.forEach(window => window.style.display = 'flex');
            }
        }

        // Add selected stream
        function addSelectedStream() {
            const selector = document.getElementById('streamSelector');
            const selectedOption = selector.options[selector.selectedIndex];
            
            if (selectedOption.value) {
                const streamData = {
                    id: selectedOption.value,
                    title: selectedOption.dataset.title,
                    url: selectedOption.dataset.url
                };
                
                // Find first available position
                const availablePosition = findAvailablePosition();
                if (availablePosition !== -1) {
                    addStreamToPosition(streamData, availablePosition);
                    selector.selectedIndex = 0;
                }
            }
        }

        // Find available position
        function findAvailablePosition() {
            const windows = document.querySelectorAll('.stream-window');
            for (let i = 0; i < windows.length; i++) {
                if (!activeStreams[i] && windows[i].style.display !== 'none') {
                    return i;
                }
            }
            return -1;
        }

        // Add stream to specific position
        function addStreamToPosition(streamData, position) {
            const window = document.querySelector(`[data-position="${position}"]`);
            const content = window.querySelector('.stream-content');
            const header = window.querySelector('.stream-header span');
            
            header.textContent = streamData.title;
            
            if (streamData.url) {
                content.innerHTML = `
                    <iframe class="stream-iframe" 
                            src="${streamData.url}?autoplay=1&mute=1" 
                            allowfullscreen>
                    </iframe>
                `;
            } else {
                content.innerHTML = `
                    <div class="stream-placeholder">
                        <div class="text-center">
                            <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                            <p>Stream not available</p>
                        </div>
                    </div>
                `;
            }
            
            activeStreams[position] = streamData;
            window.classList.add('active');
        }

        // Remove stream
        function removeStream(position) {
            const window = document.querySelector(`[data-position="${position}"]`);
            const content = window.querySelector('.stream-content');
            const header = window.querySelector('.stream-header span');
            
            header.textContent = `Stream ${position + 1}`;
            content.innerHTML = `
                <div class="stream-placeholder">
                    <div class="text-center">
                        <i class="fas fa-plus fa-2x mb-2"></i>
                        <p>Click to add stream</p>
                    </div>
                </div>
            `;
            
            delete activeStreams[position];
            window.classList.remove('active');
        }

        // Save session
        function saveSession() {
            const sessionData = {
                layout: currentLayout,
                streams: activeStreams
            };
            
            localStorage.setItem('multiStreamSession', JSON.stringify(sessionData));
            
            // Show notification
            showNotification('Session saved successfully!');
        }

        // Load session
        function loadSession() {
            const sessionData = JSON.parse(localStorage.getItem('multiStreamSession') || '{}');
            
            if (sessionData.layout) {
                changeLayout(sessionData.layout);
            }
            
            if (sessionData.streams) {
                // Clear current streams
                Object.keys(activeStreams).forEach(pos => removeStream(parseInt(pos)));
                
                // Load saved streams
                Object.entries(sessionData.streams).forEach(([position, streamData]) => {
                    addStreamToPosition(streamData, parseInt(position));
                });
                
                showNotification('Session loaded successfully!');
            } else {
                showNotification('No saved session found!');
            }
        }

        // Show notification
        function showNotification(message) {
            const notification = document.createElement('div');
            notification.className = 'alert alert-success position-fixed';
            notification.style.cssText = 'top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999;';
            notification.innerHTML = `
                <i class="fas fa-check-circle me-2"></i>${message}
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 3000);
        }

        // Click to add stream functionality
        document.querySelectorAll('.stream-placeholder').forEach((placeholder, index) => {
            placeholder.addEventListener('click', () => {
                selectedPosition = index;
                toggleControls();
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey) {
                switch(e.key) {
                    case '1':
                        e.preventDefault();
                        changeLayout('grid-2x2');
                        break;
                    case '2':
                        e.preventDefault();
                        changeLayout('side-by-side');
                        break;
                    case '3':
                        e.preventDefault();
                        changeLayout('grid-pip');
                        break;
                    case 's':
                        e.preventDefault();
                        saveSession();
                        break;
                    case 'o':
                        e.preventDefault();
                        loadSession();
                        break;
                }
            }
        });

        // Auto-load session on page load
        window.addEventListener('load', () => {
            const hasSession = localStorage.getItem('multiStreamSession');
            if (hasSession) {
                setTimeout(() => {
                    if (confirm('Load your previous multi-stream session?')) {
                        loadSession();
                    }
                }, 1000);
            }
        });
    </script>
    <!-- Chat Integration -->
    <?php if ($isLoggedIn): ?>
    <script src="polling_chat_client.js"></script>
    <script>
        // Initialize chat for multi-stream page
        document.addEventListener('DOMContentLoaded', function() {
            const userId = <?= $_SESSION['user_id'] ?>;
            const username = '<?= htmlspecialchars($_SESSION['username']) ?>';
            const userRole = '<?= htmlspecialchars($_SESSION['role']) ?>';
            
            const chatClient = new PollingChatClient('chat_api.php', 1, userId, username, userRole);
            // Chat UI can be added to specific containers as needed
        });
    </script>
    <?php endif; ?>
</body>
</html>
