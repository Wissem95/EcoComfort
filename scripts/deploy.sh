#!/bin/bash

# EcoComfort Infrastructure Deployment Script
# This script deploys the complete EcoComfort application

set -e  # Exit on any error

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$PROJECT_DIR/.env"
DOMAIN=${DOMAIN:-localhost}

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
check_root() {
    if [[ $EUID -eq 0 ]]; then
        log_warning "Running as root. This is not recommended for development."
        read -p "Continue anyway? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi
}

# Check prerequisites
check_prerequisites() {
    log_info "Checking prerequisites..."
    
    # Check if Docker is installed
    if ! command -v docker &> /dev/null; then
        log_error "Docker is not installed. Please install Docker first."
        exit 1
    fi
    
    # Check if Docker Compose is installed
    if ! command -v docker-compose &> /dev/null; then
        log_error "Docker Compose is not installed. Please install Docker Compose first."
        exit 1
    fi
    
    # Check if Docker daemon is running
    if ! docker info &> /dev/null; then
        log_error "Docker daemon is not running. Please start Docker first."
        exit 1
    fi
    
    # Check if Node.js is installed (for frontend build)
    if ! command -v node &> /dev/null; then
        log_error "Node.js is not installed. Please install Node.js 20+ first."
        exit 1
    fi
    
    # Check Node.js version
    NODE_VERSION=$(node --version | cut -d 'v' -f 2 | cut -d '.' -f 1)
    if [ "$NODE_VERSION" -lt 20 ]; then
        log_error "Node.js version must be 20 or higher. Current version: $(node --version)"
        exit 1
    fi
    
    log_success "Prerequisites check passed"
}

# Setup environment
setup_environment() {
    log_info "Setting up environment..."
    
    if [ ! -f "$ENV_FILE" ]; then
        log_info "Creating .env file from .env.example..."
        if [ -f "$PROJECT_DIR/.env.example" ]; then
            cp "$PROJECT_DIR/.env.example" "$ENV_FILE"
        else
            log_warning ".env.example not found, creating basic .env file..."
            cat > "$ENV_FILE" << EOF
APP_NAME=EcoComfort
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_TIMEZONE=Europe/Paris
APP_URL=https://${DOMAIN}

# Database Configuration
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=ecocomfort
DB_USERNAME=ecocomfort
DB_PASSWORD=ecocomfort_secret

# Redis Configuration
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# MQTT Configuration
MQTT_HOST=mosquitto
MQTT_PORT=1883
MQTT_CLIENT_ID=ecocomfort_laravel
MQTT_USE_TLS=false
MQTT_USERNAME=ecocomfort
MQTT_PASSWORD=mqtt_secret

# Reverb WebSocket
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http

# JWT Configuration
JWT_SECRET=
JWT_TTL=15
JWT_REFRESH_TTL=10080

# Frontend Configuration (Vite convention)
VITE_API_URL=https://${DOMAIN}/api
VITE_WS_URL=wss://${DOMAIN}/reverb
VITE_WS_HOST=${DOMAIN}
VITE_WS_PORT=443
VITE_APP_KEY=app-key

# SSL/TLS
DOMAIN=${DOMAIN}
EOF
        fi
        
        # Generate Laravel app key
        log_info "Generating Laravel application key..."
        cd "$PROJECT_DIR"
        docker run --rm -v "$(pwd):/app" -w /app php:8.4-cli php artisan key:generate --no-interaction
        
        # Generate JWT secret
        log_info "Generating JWT secret..."
        JWT_SECRET=$(openssl rand -base64 32)
        sed -i.bak "s/JWT_SECRET=/JWT_SECRET=$JWT_SECRET/" "$ENV_FILE"
        rm -f "$ENV_FILE.bak"
    else
        log_success ".env file already exists"
    fi
}

# Setup SSL certificates
setup_ssl() {
    log_info "Setting up SSL certificates..."
    
    SSL_DIR="$PROJECT_DIR/docker/ssl"
    mkdir -p "$SSL_DIR"
    
    if [ ! -f "$SSL_DIR/server.crt" ] || [ ! -f "$SSL_DIR/server.key" ]; then
        log_info "Generating self-signed SSL certificates..."
        "$SSL_DIR/generate-certs.sh" "$DOMAIN"
    else
        log_success "SSL certificates already exist"
    fi
}

# Setup MQTT authentication
setup_mqtt_auth() {
    log_info "Setting up MQTT authentication..."
    
    if [ -f "$SCRIPT_DIR/setup-mqtt-auth.sh" ]; then
        bash "$SCRIPT_DIR/setup-mqtt-auth.sh"
    else
        log_warning "MQTT authentication setup script not found"
    fi
}

# Build frontend
build_frontend() {
    log_info "Building React frontend..."
    
    FRONTEND_DIR="$PROJECT_DIR/frontend"
    
    if [ -d "$FRONTEND_DIR" ]; then
        cd "$FRONTEND_DIR"
        
        # Install dependencies
        log_info "Installing frontend dependencies..."
        npm ci --production=false
        
        # Build production assets
        log_info "Building production frontend..."
        npm run build
        
        log_success "Frontend build completed"
    else
        log_error "Frontend directory not found at $FRONTEND_DIR"
        exit 1
    fi
}

# Create frontend production Dockerfile
create_frontend_dockerfile() {
    log_info "Creating frontend production Dockerfile..."
    
    FRONTEND_DIR="$PROJECT_DIR/frontend"
    DOCKERFILE_PROD="$FRONTEND_DIR/Dockerfile.prod"
    
    cat > "$DOCKERFILE_PROD" << 'EOF'
# Frontend Production Dockerfile for EcoComfort
FROM node:20-alpine AS builder

WORKDIR /app

# Copy package files
COPY package*.json ./

# Install dependencies
RUN npm ci --only=production

# Copy source code
COPY . .

# Build the application
RUN npm run build

# Production stage
FROM nginx:alpine

# Install envsubst for environment variable substitution
RUN apk add --no-cache gettext

# Copy built assets from builder stage
COPY --from=builder /app/dist /usr/share/nginx/html

# Copy nginx configuration template
COPY nginx.conf.template /etc/nginx/nginx.conf.template

# Create startup script
RUN echo '#!/bin/sh' > /docker-entrypoint.sh && \
    echo 'envsubst < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf' >> /docker-entrypoint.sh && \
    echo 'exec nginx -g "daemon off;"' >> /docker-entrypoint.sh && \
    chmod +x /docker-entrypoint.sh

EXPOSE 80

CMD ["/docker-entrypoint.sh"]
EOF

    # Create nginx configuration template for frontend
    cat > "$FRONTEND_DIR/nginx.conf.template" << 'EOF'
events {
    worker_connections 1024;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;
    
    sendfile on;
    keepalive_timeout 65;
    
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;
    
    server {
        listen 80;
        server_name localhost;
        root /usr/share/nginx/html;
        index index.html;
        
        # Handle React Router
        location / {
            try_files $uri $uri/ /index.html;
        }
        
        # Cache static assets
        location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
            expires 1y;
            add_header Cache-Control "public, no-transform";
        }
    }
}
EOF

    log_success "Frontend production Dockerfile created"
}

# Deploy with Docker Compose
deploy_containers() {
    log_info "Deploying containers with Docker Compose..."
    
    cd "$PROJECT_DIR"
    
    # Pull latest images
    log_info "Pulling latest Docker images..."
    docker-compose pull
    
    # Build custom images
    log_info "Building custom images..."
    docker-compose build --no-cache
    
    # Start services
    log_info "Starting services..."
    docker-compose up -d
    
    # Wait for services to be ready
    log_info "Waiting for services to be ready..."
    sleep 30
    
    # Run database migrations
    log_info "Running database migrations..."
    docker-compose exec -T backend php artisan migrate --force
    
    # Clear caches
    log_info "Clearing application caches..."
    docker-compose exec -T backend php artisan config:cache
    docker-compose exec -T backend php artisan route:cache
    docker-compose exec -T backend php artisan view:cache
    
    log_success "Containers deployed successfully"
}

# Health check
health_check() {
    log_info "Performing health checks..."
    
    local max_attempts=30
    local attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        log_info "Health check attempt $attempt/$max_attempts..."
        
        # Check backend health
        if curl -f -s "http://localhost/api/health" > /dev/null; then
            log_success "Backend is healthy"
            
            # Check frontend
            if curl -f -s "http://localhost" > /dev/null; then
                log_success "Frontend is accessible"
                
                # Check HTTPS if available
                if curl -f -s -k "https://localhost" > /dev/null; then
                    log_success "HTTPS is working"
                fi
                
                return 0
            fi
        fi
        
        sleep 10
        ((attempt++))
    done
    
    log_error "Health check failed after $max_attempts attempts"
    return 1
}

# Display deployment info
display_info() {
    log_success "🎉 EcoComfort deployment completed!"
    echo
    echo "📋 Deployment Information:"
    echo "========================="
    echo "🌐 Frontend URL: https://$DOMAIN"
    echo "🔗 API URL: https://$DOMAIN/api"
    echo "📊 Health Check: https://$DOMAIN/api/health"
    echo "🔌 WebSocket: wss://$DOMAIN/reverb"
    echo
    echo "🐳 Docker Services:"
    echo "=================="
    docker-compose ps
    echo
    echo "📝 Useful Commands:"
    echo "==================="
    echo "View logs: docker-compose logs -f [service]"
    echo "Stop services: docker-compose down"
    echo "Update services: docker-compose pull && docker-compose up -d"
    echo "Backend shell: docker-compose exec backend bash"
    echo
    echo "⚠️  Important Notes:"
    echo "==================="
    echo "- SSL certificates are self-signed (for development)"
    echo "- Default MQTT credentials: ecocomfort / mqtt_secret"
    echo "- Database data is persisted in Docker volumes"
    echo "- Check docker-compose.yml for service configurations"
}

# Main deployment function
main() {
    echo "🚀 Starting EcoComfort Infrastructure Deployment"
    echo "================================================"
    
    check_root
    check_prerequisites
    setup_environment
    setup_ssl
    setup_mqtt_auth
    create_frontend_dockerfile
    build_frontend
    deploy_containers
    
    if health_check; then
        display_info
    else
        log_error "Deployment completed but health checks failed"
        log_info "Check logs with: docker-compose logs"
        exit 1
    fi
}

# Handle script interruption
trap 'log_error "Deployment interrupted"; exit 1' INT TERM

# Run main function
main "$@"