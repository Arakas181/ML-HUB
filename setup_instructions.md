# ML HUB Esports Platform Setup Instructions

## Prerequisites

### 1. Install Composer (PHP Package Manager)
Download and install Composer from: https://getcomposer.org/download/

Or use the Windows installer: https://getcomposer.org/Composer-Setup.exe

### 2. Install WebSocket Dependencies
Once Composer is installed, run these commands in your project directory:

```bash
cd c:\xampp\htdocs\Esports
composer require ratchet/pawl textalk/websocket-client
```

## Alternative: Manual WebSocket Setup (No Composer Required)

If you prefer not to use Composer, I've created a simplified WebSocket server that works without external dependencies.

### 3. Database Setup
1. Import the database schema:
   ```sql
   -- Run database_schema.sql in phpMyAdmin or MySQL command line
   ```

2. Update config.php with your database credentials

### 4. Start the Chat Server

#### Option A: With Composer Dependencies
```bash
php chat_server.php
```

#### Option B: Simplified Server (No Dependencies)
```bash
php simple_chat_server.php
```

### 5. Integration Examples

#### Basic Chat Integration
```javascript
// Add to any page where you want chat
const chatClient = new ChatClient('ws://localhost:8080', roomId, userId, username, userRole);
const chatUI = new ChatUI(chatClient, 'chatContainer');
```

#### Polls Widget Integration
```javascript
// Add to streaming or tournament pages
const pollsWidget = initializePollsWidget('pollsContainer', {
    websocketUrl: 'ws://localhost:8080',
    roomId: 1,
    userId: currentUserId,
    username: currentUsername,
    userRole: currentUserRole
});
```

## File Structure Overview

```
Esports/
├── chat_server.php              # WebSocket chat server (requires Composer)
├── simple_chat_server.php       # Simplified server (no dependencies)
├── chat_client.js               # Client-side chat functionality
├── polls_widget.js              # Real-time polling widget
├── live_polls_manager.php       # Admin poll management
├── qa_session_manager.php       # Admin Q&A management
├── tournament_registration.php   # Tournament registration system
├── streaming_hub.php            # Multi-platform streaming
├── multi_stream.php             # Multi-stream viewer
├── vod_library.php              # VOD library with search
├── tournament_bracket_generator.php # Bracket generation
└── database_schema.sql          # Complete database structure
```

## Testing the Setup

1. **Start XAMPP** - Ensure Apache and MySQL are running
2. **Import Database** - Run database_schema.sql
3. **Start Chat Server** - Run one of the chat server options
4. **Access Platform** - Navigate to http://localhost/Esports/
5. **Test Features** - Try registration, tournaments, streaming, polls, Q&A

## Troubleshooting

### Common Issues:

1. **Port 8080 in use**: Change port in chat_server.php and update client connections
2. **Database connection errors**: Check config.php credentials
3. **WebSocket connection failed**: Ensure chat server is running and firewall allows port 8080
4. **Composer not found**: Install Composer or use the simplified server option

### Performance Tips:

1. **Production Deployment**: Use a proper WebSocket server like Node.js with Socket.io
2. **Database Optimization**: Add indexes for frequently queried columns
3. **Caching**: Implement Redis for session storage and real-time data
4. **Load Balancing**: Use multiple server instances for high traffic

## Security Considerations

1. **Input Validation**: All user inputs are sanitized and validated
2. **SQL Injection Protection**: Using prepared statements throughout
3. **XSS Prevention**: HTML escaping for all user-generated content
4. **Rate Limiting**: Implemented for chat, polls, and Q&A submissions
5. **Authentication**: Session-based with role verification

## Next Steps

1. Configure external streaming API keys (Twitch, YouTube, Facebook)
2. Set up email server for tournament notifications
3. Customize themes and branding
4. Add additional game-specific features
5. Implement advanced analytics and reporting

## Support

For issues or questions about the platform setup, check the troubleshooting section above or review the individual PHP files for specific functionality.
