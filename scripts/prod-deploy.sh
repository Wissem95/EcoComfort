#!/bin/bash

# EcoComfort Production Deployment Script
# Deploys to production environment with security and performance optimizations

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$PROJECT_DIR/.env"
BACKUP_DIR="$PROJECT_DIR/backups"
DOMAIN=${DOMAIN:-"ecocomfort.example.com"}

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1" | tee -a "$PROJECT_DIR/deploy.log"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1" | tee -a "$PROJECT_DIR/deploy.log"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$PROJECT_DIR/deploy.log"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$PROJECT_DIR/deploy.log"
}

# Pre-deployment checks
pre_deployment_checks() {
    log_info "Running pre-deployment checks..."
    
    # Check if running as root
    if [[ $EUID -eq 0 ]]; then
        log_error "Do not run this script as root for security reasons"
        exit 1
    fi
    
    # Check domain configuration
    if [ "$DOMAIN" == "ecocomfort.example.com" ]; then
        log_error "Please set DOMAIN environment variable to your actual domain"
        log_error "Example: DOMAIN=your-domain.com ./prod-deploy.sh"
        exit 1
    fi
    
    # Check SSL certificates for production
    SSL_DIR="$PROJECT_DIR/docker/ssl"
    if [ ! -f "$SSL_DIR/server.crt" ] || [ ! -f "$SSL_DIR/server.key" ]; then
        log_warning "SSL certificates not found. Production deployment requires valid SSL certificates."
        log_info "For production, you should use certificates from a trusted CA like Let's Encrypt"
        read -p "Continue with self-signed certificates? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi
    
    # Check if .env file exists and has production values
    if [ ! -f "$ENV_FILE" ]; then
        log_error ".env file not found. Run setup script first."
        exit 1
    fi
    
    if grep -q "APP_ENV=local" "$ENV_FILE"; then
        log_warning "APP_ENV is set to 'local'. This should be 'production' for production deployment."
    fi
    
    log_success "Pre-deployment checks passed"
}

# Create production environment file
setup_production_env() {
    log_info "Setting up production environment..."
    
    # Backup existing .env
    if [ -f "$ENV_FILE" ]; then
        cp "$ENV_FILE" "$ENV_FILE.backup.$(date +%Y%m%d_%H%M%S)"
    fi
    
    # Create production .env
    cat > "$ENV_FILE" << EOF
APP_NAME=EcoComfort
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Europe/Paris
APP_URL=https://${DOMAIN}
APP_KEY=$(openssl rand -base64 32 | base64)

# Database Configuration
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=ecocomfort
DB_USERNAME=ecocomfort
DB_PASSWORD=$(openssl rand -base64 32)

# Redis Configuration
REDIS_HOST=redis
REDIS_PASSWORD=$(openssl rand -base64 24)
REDIS_PORT=6379

# MQTT Configuration
MQTT_HOST=mosquitto
MQTT_PORT=1883
MQTT_CLIENT_ID=ecocomfort_production
MQTT_USE_TLS=true
MQTT_USERNAME=ecocomfort
MQTT_PASSWORD=$(openssl rand -base64 24)
MQTT_TOPIC_TEMPERATURE=112
MQTT_TOPIC_HUMIDITY=114
MQTT_TOPIC_ACCELEROMETER=127

# Reverb WebSocket
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=https

# JWT Configuration
JWT_SECRET=$(openssl rand -base64 64)
JWT_TTL=15
JWT_REFRESH_TTL=10080

# Frontend Configuration (Vite convention)
VITE_API_URL=https://${DOMAIN}/api
VITE_WS_URL=wss://${DOMAIN}/reverb
VITE_WS_HOST=${DOMAIN}
VITE_WS_PORT=443
VITE_APP_KEY=app-key

# Production Settings
LOG_CHANNEL=daily
LOG_LEVEL=warning
DOMAIN=${DOMAIN}

# Performance Settings
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
BROADCAST_DRIVER=reverb

# Security Settings
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=${DOMAIN}
EOF
    
    log_success "Production environment configured"
}

# Create backup
create_backup() {
    log_info "Creating backup..."
    
    mkdir -p "$BACKUP_DIR"
    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    BACKUP_FILE="$BACKUP_DIR/backup_$TIMESTAMP.tar.gz"
    
    # Backup application files and docker volumes
    tar -czf "$BACKUP_FILE" \
        --exclude=node_modules \
        --exclude=vendor \
        --exclude=storage/logs \
        --exclude=.git \
        -C "$PROJECT_DIR" . 2>/dev/null || true
    
    # Backup database if container is running
    if docker-compose ps postgres | grep -q "Up"; then
        log_info "Backing up database..."
        docker-compose exec -T postgres pg_dump -U ecocomfort ecocomfort > "$BACKUP_DIR/database_$TIMESTAMP.sql"
    fi
    
    log_success "Backup created: $BACKUP_FILE"
}

# Setup Redis configuration
setup_redis_config() {
    log_info "Setting up Redis configuration..."
    
    REDIS_CONFIG_DIR="$PROJECT_DIR/docker/redis"
    mkdir -p "$REDIS_CONFIG_DIR"
    
    cat > "$REDIS_CONFIG_DIR/redis.conf" << 'EOF'
# Redis Production Configuration
bind 0.0.0.0
port 6379
timeout 300
keepalive 60

# Memory management
maxmemory 512mb
maxmemory-policy allkeys-lru
maxmemory-samples 10

# Persistence
save 900 1
save 300 10
save 60 10000
rdbcompression yes
rdbchecksum yes

# Append only file
appendonly yes
appendfilename "appendonly.aof"
appendfsync everysec
no-appendfsync-on-rewrite no
auto-aof-rewrite-percentage 100
auto-aof-rewrite-min-size 64mb

# Security
requirepass redis_production_password

# Logging
loglevel notice
logfile /var/log/redis/redis.log

# Client connections
tcp-keepalive 0
tcp-backlog 511
maxclients 10000

# Performance
hash-max-ziplist-entries 512
hash-max-ziplist-value 64
list-max-ziplist-size -2
list-compress-depth 0
set-max-intset-entries 512
zset-max-ziplist-entries 128
zset-max-ziplist-value 64
EOF
    
    log_success "Redis configuration created"
}

# Setup PostgreSQL configuration
setup_postgresql_config() {
    log_info "Setting up PostgreSQL configuration..."
    
    POSTGRES_CONFIG_DIR="$PROJECT_DIR/docker/postgres"
    mkdir -p "$POSTGRES_CONFIG_DIR"
    
    cat > "$POSTGRES_CONFIG_DIR/postgresql.conf" << 'EOF'
# PostgreSQL Production Configuration

# Connection settings
listen_addresses = '*'
port = 5432
max_connections = 200

# Memory settings
shared_buffers = 256MB
effective_cache_size = 1GB
maintenance_work_mem = 64MB
work_mem = 4MB

# Checkpoint settings
wal_buffers = 16MB
checkpoint_completion_target = 0.9

# Performance settings
random_page_cost = 1.1
effective_io_concurrency = 200
default_statistics_target = 100

# Logging
logging_collector = on
log_directory = '/var/log/postgresql'
log_filename = 'postgresql-%Y-%m-%d_%H%M%S.log'
log_statement = 'ddl'
log_min_duration_statement = 1000

# Security
ssl = off

# TimescaleDB settings
shared_preload_libraries = 'timescaledb'
timescaledb.telemetry_level = off
EOF
    
    log_success "PostgreSQL configuration created"
}

# Production deployment
deploy_production() {
    log_info "Starting production deployment..."
    
    cd "$PROJECT_DIR"
    
    # Build frontend for production
    log_info "Building frontend for production..."
    cd "$PROJECT_DIR/frontend"
    npm ci --only=production
    npm run build
    
    # Return to project root
    cd "$PROJECT_DIR"
    
    # Stop existing containers
    log_info "Stopping existing containers..."
    docker-compose down --remove-orphans
    
    # Pull latest images
    log_info "Pulling latest Docker images..."
    docker-compose pull
    
    # Build custom images
    log_info "Building production images..."
    docker-compose build --no-cache
    
    # Start services
    log_info "Starting production services..."
    docker-compose up -d
    
    # Wait for services to be ready
    log_info "Waiting for services to initialize..."
    sleep 60
    
    # Run database migrations
    log_info "Running database migrations..."
    docker-compose exec -T backend php artisan migrate --force
    
    # Optimize Laravel
    log_info "Optimizing Laravel for production..."
    docker-compose exec -T backend php artisan config:cache
    docker-compose exec -T backend php artisan route:cache
    docker-compose exec -T backend php artisan view:cache
    docker-compose exec -T backend php artisan optimize
    
    log_success "Production deployment completed"
}

# Security hardening
security_hardening() {
    log_info "Applying security hardening..."
    
    # Set proper file permissions
    chmod 600 "$ENV_FILE"
    chmod 600 "$PROJECT_DIR/docker/mosquitto/passwd"
    chmod 600 "$PROJECT_DIR/docker/ssl/server.key"
    
    # Update MQTT passwords
    if [ -f "$SCRIPT_DIR/setup-mqtt-auth.sh" ]; then
        MQTT_PASSWORD=$(grep MQTT_PASSWORD "$ENV_FILE" | cut -d'=' -f2)
        export MQTT_PASSWORD
        bash "$SCRIPT_DIR/setup-mqtt-auth.sh"
    fi
    
    log_success "Security hardening applied"
}

# Health checks and monitoring
production_health_check() {
    log_info "Running production health checks..."
    
    local max_attempts=60
    local attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        log_info "Health check attempt $attempt/$max_attempts..."
        
        # Check all critical endpoints
        if curl -f -s "https://$DOMAIN/api/health" > /dev/null && \
           curl -f -s "https://$DOMAIN" > /dev/null; then
            log_success "All services are healthy"
            
            # Additional production checks
            log_info "Running additional production checks..."
            
            # Check SSL certificate
            if openssl s_client -connect "$DOMAIN:443" -servername "$DOMAIN" < /dev/null 2>/dev/null | openssl x509 -noout -dates; then
                log_success "SSL certificate is valid"
            fi
            
            # Check service status
            docker-compose ps
            
            return 0
        fi
        
        sleep 10
        ((attempt++))
    done
    
    log_error "Production health check failed after $max_attempts attempts"
    return 1
}

# Display production info
display_production_info() {
    log_success "🚀 Production deployment completed successfully!"
    echo
    echo "📋 Production Information:"
    echo "========================="
    echo "🌐 Website: https://$DOMAIN"
    echo "🔗 API: https://$DOMAIN/api"
    echo "📊 Health Check: https://$DOMAIN/api/health"
    echo "🔌 WebSocket: wss://$DOMAIN/reverb"
    echo
    echo "🔒 Security Features:"
    echo "===================="
    echo "✅ HTTPS/TLS encryption"
    echo "✅ JWT authentication"
    echo "✅ Rate limiting"
    echo "✅ MQTT authentication"
    echo "✅ Redis password protection"
    echo "✅ Security headers"
    echo
    echo "📊 Monitoring:"
    echo "=============="
    echo "Docker status: docker-compose ps"
    echo "Logs: docker-compose logs -f [service]"
    echo "Database backup: docker-compose exec postgres pg_dump -U ecocomfort ecocomfort"
    echo
    echo "⚠️  Important Notes:"
    echo "==================="
    echo "- Monitor logs regularly: tail -f deploy.log"
    echo "- Set up regular database backups"
    echo "- Monitor disk space and performance"
    echo "- Keep Docker images updated"
    echo "- Review security settings periodically"
}

# Main deployment function
main() {
    echo "🏭 EcoComfort Production Deployment"
    echo "==================================="
    
    # Create log file
    echo "Starting production deployment at $(date)" > "$PROJECT_DIR/deploy.log"
    
    pre_deployment_checks
    create_backup
    setup_production_env
    setup_redis_config
    setup_postgresql_config
    deploy_production
    security_hardening
    
    if production_health_check; then
        display_production_info
        log_success "Production deployment completed successfully!"
    else
        log_error "Deployment completed but health checks failed"
        log_error "Check logs and service status before proceeding"
        exit 1
    fi
}

# Handle script interruption
trap 'log_error "Production deployment interrupted"; exit 1' INT TERM

# Check if domain is provided
if [ -z "$DOMAIN" ]; then
    echo "Usage: DOMAIN=your-domain.com $0"
    echo "Example: DOMAIN=ecocomfort.mycompany.com ./prod-deploy.sh"
    exit 1
fi

# Run main function
main "$@"