# 🎮 Esports Platform - Complete Installation Guide

This comprehensive guide will help you set up the fully-featured esports platform with all modern enhancements.

## 📋 Prerequisites

### System Requirements
- **PHP 8.1+** with extensions: `pdo`, `pdo_mysql`, `redis`, `gd`, `zip`, `xml`, `mbstring`
- **MySQL 8.0+** or **MariaDB 10.6+**
- **Redis 6.0+** (for caching and sessions)
- **Node.js 16+** (for WebSocket server and frontend build)
- **Docker & Docker Compose** (recommended for development)

### Development Tools
- **Composer** (PHP dependency manager)
- **npm** (Node.js package manager)
- **Git** (version control)

## 🚀 Quick Start with Docker

The fastest way to get started is using Docker Compose:

```bash
# Clone the repository
git clone https://github.com/yourusername/esports-platform.git
cd esports-platform

# Copy environment file
cp .env.example .env

# Build and start all services
docker-compose up -d

# Install dependencies
docker-compose exec app composer install
docker-compose exec app npm install

# Build frontend assets
docker-compose exec app npm run build

# Run database migrations
docker-compose exec app php -f database_setup.php
```

Your platform will be available at:
- **Main App**: http://localhost:8080
- **WebSocket Server**: http://localhost:3001
- **Database**: localhost:3306
- **Redis**: localhost:6379
- **Grafana Dashboard**: http://localhost:3000
- **Prometheus**: http://localhost:9090

## 🛠️ Manual Installation

### 1. Database Setup

```sql
-- Create database
CREATE DATABASE esports_platform;
CREATE USER 'esports_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON esports_platform.* TO 'esports_user'@'localhost';
FLUSH PRIVILEGES;
```

```bash
# Import database schema
mysql -u esports_user -p esports_platform < database_schema.sql
```

### 2. PHP Application Setup

```bash
# Install PHP dependencies
composer install

# Copy and configure environment file
cp config.example.php config.php
# Edit config.php with your database credentials

# Set file permissions
chmod -R 755 .
chmod -R 777 logs/ uploads/ cache/
```

### 3. Redis Setup

```bash
# Ubuntu/Debian
sudo apt install redis-server
sudo systemctl start redis
sudo systemctl enable redis

# Or using Docker
docker run -d --name esports-redis -p 6379:6379 redis:alpine
```

### 4. Node.js WebSocket Server

```bash
# Navigate to websocket directory
cd websocket

# Install dependencies
npm install

# Start the server
npm run dev
```

### 5. Frontend Build System

```bash
# Install frontend dependencies
npm install

# Development build (with watch)
npm run dev

# Production build
npm run build
```

## ⚙️ Configuration

### Environment Variables

Create `.env` file in the root directory:

```env
# Database Configuration
DB_HOST=localhost
DB_NAME=esports_platform
DB_USER=esports_user
DB_PASS=secure_password
DB_PORT=3306

# Redis Configuration
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DATABASE=0

# Security
JWT_SECRET=your-very-secure-jwt-secret-key-here
CSRF_SECRET=your-csrf-secret-key

# WebSocket Server
WEBSOCKET_PORT=3001
ALLOWED_ORIGINS=http://localhost,http://localhost:3000,http://localhost:8080

# Monitoring & Analytics
SENTRY_DSN=your-sentry-dsn-here
ENVIRONMENT=development

# External APIs (Optional)
TWITCH_CLIENT_ID=your-twitch-client-id
YOUTUBE_API_KEY=your-youtube-api-key
FACEBOOK_APP_ID=your-facebook-app-id
```

### Web Server Configuration

#### Apache (.htaccess)
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^api/(.*)$ api/v1/index.php [QSA,L]
RewriteRule ^(.*)$ index.php [QSA,L]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
```

#### Nginx
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/esports-platform;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location /api/ {
        try_files $uri $uri/ /api/v1/index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";
}
```

## 🧪 Testing Setup

### PHP Unit Tests

```bash
# Install PHPUnit
composer require --dev phpunit/phpunit

# Run tests
./vendor/bin/phpunit

# Run tests with coverage
./vendor/bin/phpunit --coverage-html tests/coverage
```

### JavaScript Tests

```bash
# Run Jest tests
npm test

# Run tests in watch mode
npm run test:watch

# Run E2E tests
npm run test:e2e
```

## 📊 Monitoring & Analytics Setup

### Prometheus Configuration

Create `docker/prometheus.yml`:

```yaml
global:
  scrape_interval: 15s

scrape_configs:
  - job_name: 'esports-app'
    static_configs:
      - targets: ['app:80']
    metrics_path: '/metrics'
    scrape_interval: 30s

  - job_name: 'websocket-server'
    static_configs:
      - targets: ['websocket:3001']
    metrics_path: '/metrics'
    scrape_interval: 15s
```

### Grafana Dashboard

1. Access Grafana at http://localhost:3000
2. Login with `admin/admin`
3. Import dashboards from `docker/grafana/dashboards/`

## 🔐 Security Setup

### SSL Certificate (Production)

```bash
# Using Let's Encrypt
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

### Security Hardening

```bash
# Set proper file permissions
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod 600 config.php .env

# Secure sensitive directories
echo "deny from all" > logs/.htaccess
echo "deny from all" > uploads/.htaccess
```

## 🚀 Deployment

### Production Deployment

```bash
# Build for production
npm run build
composer install --no-dev --optimize-autoloader

# Clear development caches
rm -rf cache/* logs/*

# Set production environment
export APP_ENV=production
```

### Using CI/CD Pipeline

The included GitHub Actions workflow will:
1. Run all tests
2. Build Docker images
3. Deploy to staging/production
4. Run performance tests
5. Send notifications

Configure these secrets in your GitHub repository:
- `GITHUB_TOKEN`
- `DOCKER_REGISTRY_TOKEN`
- `SENTRY_DSN`
- `SLACK_WEBHOOK`

## 🔧 Maintenance

### Database Maintenance

```bash
# Optimize database tables
php -f maintenance/optimize_database.php

# Clean up old logs and cache
php -f maintenance/cleanup.php

# Backup database
mysqldump -u esports_user -p esports_platform > backup_$(date +%Y%m%d).sql
```

### Log Rotation

Add to `/etc/logrotate.d/esports-platform`:

```bash
/var/www/esports-platform/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    notifempty
    create 644 www-data www-data
}
```

## 🐛 Troubleshooting

### Common Issues

**Database Connection Failed**
```bash
# Check MySQL service
sudo systemctl status mysql

# Check credentials in config.php
# Verify database exists and user has permissions
```

**WebSocket Connection Failed**
```bash
# Check if Node.js server is running
netstat -tlnp | grep 3001

# Check firewall settings
sudo ufw allow 3001

# Verify CORS settings in websocket/server.js
```

**Permission Denied Errors**
```bash
# Fix file permissions
sudo chown -R www-data:www-data .
chmod -R 755 .
chmod -R 777 logs/ uploads/ cache/
```

**Redis Connection Issues**
```bash
# Check Redis status
redis-cli ping

# Check Redis configuration
sudo systemctl status redis
```

### Performance Issues

**Slow Database Queries**
```bash
# Enable MySQL slow query log
sudo mysql -e "SET GLOBAL slow_query_log = 'ON';"
sudo mysql -e "SET GLOBAL long_query_time = 2;"

# Monitor slow queries
sudo tail -f /var/log/mysql/mysql-slow.log
```

**High Memory Usage**
```bash
# Check PHP memory limit
php -i | grep memory_limit

# Monitor memory usage
php -f maintenance/memory_report.php
```

## 📚 Additional Resources

### API Documentation
- Access Swagger documentation at `/api/v1/docs`
- View API examples in `api/examples/`

### Development Tools
- **PHPStorm**: Use included `.idea/` configuration
- **VSCode**: Use included `.vscode/` settings
- **Postman**: Import collection from `api/postman/`

### Community & Support
- **GitHub Issues**: Report bugs and feature requests
- **Discord**: Join our developer community
- **Documentation**: Visit the wiki for detailed guides

## 🎯 What's Next?

After installation, you can:

1. **Customize the Theme**: Edit SCSS files in `assets/scss/`
2. **Add Game Integrations**: Implement APIs for specific games
3. **Configure Streaming**: Set up Twitch/YouTube integration
4. **Setup Analytics**: Configure Google Analytics or similar
5. **Add Payment Processing**: Integrate Stripe or PayPal
6. **Enable Push Notifications**: Configure web push notifications
7. **Setup CDN**: Configure CloudFlare or AWS CloudFront
8. **Scale the Infrastructure**: Add load balancers and multiple servers

## 🏆 Congratulations!

You now have a fully-featured, production-ready esports platform with:

- ✅ **Modern Security**: CSRF protection, rate limiting, input validation
- ✅ **Scalable Architecture**: Docker containers, Redis caching, API-first design
- ✅ **Real-time Features**: WebSocket chat, live match updates, polls
- ✅ **Mobile-Responsive**: Modern CSS framework with mobile optimization
- ✅ **Comprehensive Testing**: Unit, integration, and E2E tests
- ✅ **CI/CD Pipeline**: Automated testing, building, and deployment
- ✅ **Monitoring & Analytics**: Performance tracking, error monitoring, user analytics
- ✅ **Professional DevOps**: Docker, monitoring, alerts, and logging

Your esports platform is now ready to host tournaments, engage communities, and scale to thousands of users!
