# Esports Platform - Role-Based Dashboard System

This esports platform implements a comprehensive role-based dashboard system where users are automatically redirected to their appropriate dashboards after login based on their role.

## Features

### 🔐 Role-Based Authentication
- **User**: Regular players who can participate in tournaments
- **Admin**: Moderators with limited administrative privileges
- **Super Admin**: Full system administrators with complete access

### 🎯 Automatic Dashboard Redirection
- **Users** → `user_dashboard.php` - Personal tournament tracking and match history
- **Admins** → `admin_dashboard.php` - Platform management and statistics
- **Super Admins** → `admin_dashboard.php` - Full system control

## File Structure

```
├── index.php              # Main landing page with role-based sidebar
├── login.php              # Login form with role selection
├── authenticate.php       # Authentication handler with role-based redirects
├── user_dashboard.php     # User dashboard for regular players
├── admin_dashboard.php    # Admin dashboard for administrators
├── config.php             # Database configuration
├── logout.php             # Session destruction
└── database_schema.sql    # Database structure and sample data
```

## How It Works

### 1. Login Process
1. User selects their role (Player/Admin/Super Admin)
2. Enters username and password
3. Form submits to `authenticate.php`

### 2. Authentication & Redirection
1. `authenticate.php` validates credentials against database
2. Checks user role from database
3. Automatically redirects to appropriate dashboard:
   - `user` → `user_dashboard.php`
   - `admin` or `super_admin` → `admin_dashboard.php`

### 3. Dashboard Access Control
- Each dashboard checks user authentication and role
- Unauthorized access redirects to login page
- Role-specific features and navigation menus

## Database Requirements

The system requires these tables:
- `users` - User accounts with roles
- `tournaments` - Tournament information
- `tournament_participants` - User tournament registrations
- `matches` - Match details and results
- `teams` - Team information
- `notices` - System announcements
- `content` - News and guides

## Sample Login Credentials

For testing purposes, the database includes sample users:

| Username | Password | Role | Dashboard |
|----------|----------|------|-----------|
| `player1` | `password` | User | User Dashboard |
| `admin` | `password` | Admin | Admin Dashboard |
| `superadmin` | `password` | Super Admin | Admin Dashboard |

**Note**: Passwords are hashed with bcrypt in production. The sample data uses a simple hash for demonstration.

## Security Features

- Session-based authentication
- Role-based access control
- SQL injection prevention with prepared statements
- Password hashing with bcrypt
- Input validation and sanitization

## Customization

### Adding New Roles
1. Update the role ENUM in database schema
2. Modify `authenticate.php` redirect logic
3. Create corresponding dashboard files
4. Update navigation menus

### Dashboard Features
- Each dashboard can be customized independently
- Role-specific functionality and data display
- Consistent UI/UX across all dashboards

## Installation

1. Set up your web server (Apache/Nginx)
2. Configure database connection in `config.php`
3. Import `database_schema.sql` to create tables
4. Ensure proper file permissions
5. Test login with sample credentials

## Browser Support

- Modern browsers with JavaScript enabled
- Responsive design for mobile devices
- Bootstrap 5 for consistent styling

## Support

For issues or questions about the role-based dashboard system, please check the code comments or create an issue in the repository. 