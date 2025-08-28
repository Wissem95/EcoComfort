#!/bin/bash

# EcoComfort Development Setup Script
# Quick setup for local development environment

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$PROJECT_DIR/.env"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Setup development environment
setup_dev_env() {
    log_info "Setting up development environment..."
    
    if [ ! -f "$ENV_FILE" ]; then
        cat > "$ENV_FILE" << 'EOF'
APP_NAME=EcoComfort
APP_ENV=local
APP_KEY=base64:4k8JU/KrO5P5VHxPXGOmnZVrXq7nCtWNVG/tTxKcbBw=
APP_DEBUG=true
APP_TIMEZONE=Europe/Paris
APP_URL=http://localhost

# Database Configuration
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=ecocomfort
DB_USERNAME=ecocomfort
DB_PASSWORD=ecocomfort_secret

# Redis Configuration
REDIS_HOST=localhost
REDIS_PASSWORD=null
REDIS_PORT=6379

# MQTT Configuration
MQTT_HOST=localhost
MQTT_PORT=1883
MQTT_CLIENT_ID=ecocomfort_laravel
MQTT_USE_TLS=false
MQTT_USERNAME=ecocomfort
MQTT_PASSWORD=mqtt_secret
MQTT_TOPIC_TEMPERATURE=112
MQTT_TOPIC_HUMIDITY=114
MQTT_TOPIC_ACCELEROMETER=127

# Reverb WebSocket
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# JWT Configuration
JWT_SECRET=YW3fHlRgNfVf8jKzF7V8jYfCkCn5h4Bf8KvFn8KfRtVf
JWT_TTL=15
JWT_REFRESH_TTL=10080

# Frontend Configuration (Vite convention)
VITE_API_URL=http://localhost:8000/api
VITE_WS_URL=ws://localhost:8080
VITE_WS_HOST=localhost
VITE_WS_PORT=8080
VITE_APP_KEY=app-key

# Development Settings
LOG_CHANNEL=single
LOG_LEVEL=debug
DOMAIN=localhost
EOF
        log_success "Development .env file created"
    else
        log_success ".env file already exists"
    fi
}

# Install backend dependencies
setup_backend() {
    log_info "Setting up Laravel backend..."
    
    cd "$PROJECT_DIR"
    
    # Install Composer dependencies
    if command -v composer &> /dev/null; then
        log_info "Installing Composer dependencies..."
        composer install --no-interaction --prefer-dist
    else
        log_warning "Composer not found, using Docker..."
        docker run --rm -v "$(pwd):/app" -w /app composer:latest install --no-interaction --prefer-dist
    fi
    
    # Generate application key if needed
    if ! grep -q "APP_KEY=base64:" "$ENV_FILE"; then
        log_info "Generating application key..."
        php artisan key:generate --no-interaction
    fi
    
    log_success "Backend setup complete"
}

# Setup frontend
setup_frontend() {
    log_info "Setting up React frontend..."
    
    FRONTEND_DIR="$PROJECT_DIR/frontend"
    
    if [ -d "$FRONTEND_DIR" ]; then
        cd "$FRONTEND_DIR"
        
        # Install dependencies
        log_info "Installing frontend dependencies..."
        npm install
        
        log_success "Frontend setup complete"
    else
        log_warning "Frontend directory not found"
    fi
}

# Start development services
start_dev_services() {
    log_info "Starting development services with Docker Compose..."
    
    cd "$PROJECT_DIR"
    
    # Create development docker-compose override
    cat > docker-compose.override.yml << 'EOF'
version: '3.8'

services:
  backend:
    environment:
      - APP_ENV=local
      - APP_DEBUG=true
    volumes:
      - ./:/var/www
    ports:
      - "9000:9000"
  
  postgres:
    ports:
      - "5432:5432"
    environment:
      - POSTGRES_DB=ecocomfort
      - POSTGRES_USER=ecocomfort
      - POSTGRES_PASSWORD=ecocomfort_secret
  
  redis:
    ports:
      - "6379:6379"
  
  mosquitto:
    ports:
      - "1883:1883"
      - "9001:9001"
  
  reverb:
    ports:
      - "8080:8080"
    environment:
      - APP_ENV=local
      - APP_DEBUG=true
  
  nginx:
    ports:
      - "80:80"
    volumes:
      - ./:/var/www
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
EOF
    
    # Start essential services for development
    log_info "Starting database, Redis, and MQTT..."
    docker-compose up -d postgres redis mosquitto
    
    # Wait for services
    sleep 10
    
    # Run migrations
    log_info "Running database migrations..."
    if command -v php &> /dev/null && php artisan --version &> /dev/null; then
        php artisan migrate
    else
        docker-compose exec -T backend php artisan migrate
    fi
    
    log_success "Development services started"
}

# Display development info
display_dev_info() {
    log_success "🚀 Development environment ready!"
    echo
    echo "📋 Development Information:"
    echo "=========================="
    echo "🗄️  Database: localhost:5432 (postgres)"
    echo "🔴 Redis: localhost:6379"
    echo "📡 MQTT: localhost:1883"
    echo "🔌 WebSocket: localhost:8080"
    echo
    echo "🛠️  Development Commands:"
    echo "========================"
    echo "Start backend: php artisan serve"
    echo "Start frontend: cd frontend && npm run dev"
    echo "Start WebSocket: php artisan reverb:start"
    echo "Start MQTT listener: php artisan mqtt:listen"
    echo "Start queue worker: php artisan queue:work"
    echo
    echo "🐳 Docker Services:"
    echo "=================="
    echo "All services: docker-compose up -d"
    echo "Stop services: docker-compose down"
    echo "View logs: docker-compose logs -f [service]"
    echo
    echo "📝 Testing:"
    echo "==========="
    echo "Run tests: php artisan test"
    echo "Frontend tests: cd frontend && npm test"
    echo
    echo "🔍 Useful URLs:"
    echo "==============="
    echo "API Health: http://localhost/api/health"
    echo "Frontend: http://localhost:5173 (Vite dev server)"
}

# Main function
main() {
    echo "🛠️  EcoComfort Development Setup"
    echo "================================"
    
    setup_dev_env
    setup_backend
    setup_frontend
    start_dev_services
    display_dev_info
    
    echo
    log_success "✅ Development environment setup complete!"
    echo "💡 Run 'npm run dev' in the frontend directory to start the development server"
}

# Run main function
main "$@"