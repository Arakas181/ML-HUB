<?php
session_start();
require_once 'config.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : 'user';
$username = $isLoggedIn ? $_SESSION['username'] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournament Calendar - ML HUB Esports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1a1a2e;
            --secondary-color: #16213e;
            --accent-color: #0f3460;
            --highlight-color: #e94560;
            --text-light: #f1f1f1;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--text-light);
            min-height: 100vh;
        }
        
        .calendar-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .fc {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 1rem;
        }
        
        .fc-toolbar {
            background: rgba(255, 255, 255, 0.1);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        
        .fc-button {
            background: var(--highlight-color) !important;
            border-color: var(--highlight-color) !important;
        }
        
        .fc-button:hover {
            background: #ff6b8a !important;
            border-color: #ff6b8a !important;
        }
        
        .fc-event {
            border-radius: 5px;
            border: none;
            padding: 2px 4px;
        }
        
        .event-tournament {
            background: var(--highlight-color);
        }
        
        .event-stream {
            background: #28a745;
        }
        
        .event-match {
            background: #ffc107;
            color: #000;
        }
        
        .legend {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .legend-item {
            display: inline-flex;
            align-items: center;
            margin-right: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 3px;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-trophy me-2"></i>ML HUB Esports
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="tournaments.php">Tournaments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="calendar.php">Calendar</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <?php if ($isLoggedIn): ?>
                        <div class="dropdown">
                            <a class="btn btn-outline-light dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($username); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                                <li><a class="dropdown-item" href="user_dashboard.php">Dashboard</a></li>
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

    <!-- Main Content -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="text-center mb-4">
                    <i class="fas fa-calendar-alt me-2"></i>Tournament & Events Calendar
                </h1>
                <p class="text-center text-muted mb-4">
                    View all upcoming tournaments, matches, and streaming events
                </p>
            </div>
        </div>

        <!-- Legend -->
        <div class="legend">
            <h6 class="mb-3">Event Types</h6>
            <div class="legend-item">
                <div class="legend-color event-tournament"></div>
                <span>Tournaments</span>
            </div>
            <div class="legend-item">
                <div class="legend-color event-match"></div>
                <span>Matches</span>
            </div>
            <div class="legend-item">
                <div class="legend-color event-stream"></div>
                <span>Live Streams</span>
            </div>
        </div>

        <!-- Calendar Container -->
        <div class="calendar-container">
            <div id="calendar"></div>
        </div>
    </div>

    <!-- Event Details Modal -->
    <div class="modal fade" id="eventModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="eventModalTitle"></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="eventModalBody">
                    <!-- Event details will be loaded here -->
                </div>
                <div class="modal-footer border-secondary" id="eventModalFooter">
                    <!-- Action buttons will be added here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                themeSystem: 'bootstrap5',
                height: 'auto',
                events: function(info, successCallback, failureCallback) {
                    fetch(`calendar_api.php?start=${info.startStr}&end=${info.endStr}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                successCallback(data.events);
                            } else {
                                failureCallback(data.error);
                            }
                        })
                        .catch(error => {
                            console.error('Calendar error:', error);
                            failureCallback(error);
                        });
                },
                eventClick: function(info) {
                    showEventDetails(info.event);
                },
                eventDidMount: function(info) {
                    // Add custom styling based on event type
                    const eventType = info.event.extendedProps.type;
                    if (eventType === 'tournament') {
                        info.el.classList.add('event-tournament');
                    } else if (eventType === 'match') {
                        info.el.classList.add('event-match');
                    } else if (eventType === 'stream') {
                        info.el.classList.add('event-stream');
                    }
                }
            });
            
            calendar.render();
            
            function showEventDetails(event) {
                const modal = new bootstrap.Modal(document.getElementById('eventModal'));
                const title = document.getElementById('eventModalTitle');
                const body = document.getElementById('eventModalBody');
                const footer = document.getElementById('eventModalFooter');
                
                title.textContent = event.title;
                
                let bodyHtml = `
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Type:</strong> ${event.extendedProps.type || 'Event'}
                        </div>
                        <div class="col-md-6">
                            <strong>Date:</strong> ${event.start.toLocaleDateString()}
                        </div>
                    </div>
                `;
                
                if (event.extendedProps.description) {
                    bodyHtml += `<div class="mt-3"><strong>Description:</strong><br>${event.extendedProps.description}</div>`;
                }
                
                if (event.extendedProps.prize_pool) {
                    bodyHtml += `<div class="mt-2"><strong>Prize Pool:</strong> $${parseFloat(event.extendedProps.prize_pool).toLocaleString()}</div>`;
                }
                
                if (event.extendedProps.entry_fee) {
                    bodyHtml += `<div class="mt-2"><strong>Entry Fee:</strong> $${parseFloat(event.extendedProps.entry_fee).toFixed(2)}</div>`;
                }
                
                body.innerHTML = bodyHtml;
                
                let footerHtml = '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>';
                
                if (event.extendedProps.type === 'tournament' && event.extendedProps.id) {
                    footerHtml += `<a href="tournament_details.php?id=${event.extendedProps.id}" class="btn btn-primary ms-2">View Details</a>`;
                } else if (event.extendedProps.type === 'match' && event.extendedProps.id) {
                    footerHtml += `<a href="match_details.php?id=${event.extendedProps.id}" class="btn btn-primary ms-2">View Match</a>`;
                }
                
                footer.innerHTML = footerHtml;
                
                modal.show();
            }
        });
    </script>

    <!-- Chat Integration -->
    <?php if ($isLoggedIn): ?>
    <script src="polling_chat_client.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const userId = <?= $_SESSION['user_id'] ?>;
            const username = '<?= htmlspecialchars($_SESSION['username']) ?>';
            const userRole = '<?= htmlspecialchars($_SESSION['role']) ?>';
            
            const chatClient = new PollingChatClient('chat_api.php', 1, userId, username, userRole);
        });
    </script>
    <?php endif; ?>
</body>
</html>
