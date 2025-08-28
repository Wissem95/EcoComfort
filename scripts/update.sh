#!/bin/bash

# EcoComfort Update Script
# Updates running deployment with zero-downtime strategy

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
BACKUP_DIR="$PROJECT_DIR/backups"
UPDATE_TYPE=${1:-"minor"} # major, minor, patch, hotfix

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1" | tee -a "$PROJECT_DIR/update.log"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1" | tee -a "$PROJECT_DIR/update.log"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$PROJECT_DIR/update.log"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$PROJECT_DIR/update.log"
}

# Pre-update checks
pre_update_checks() {
    log_info "Running pre-update checks..."
    
    cd "$PROJECT_DIR"
    
    # Check if services are running
    if ! docker-compose ps | grep -q "Up"; then
        log_error "No running services found. Please deploy first."
        exit 1
    fi
    
    # Check Git status
    if [ -d ".git" ]; then
        if [ -n "$(git status --porcelain)" ]; then
            log_warning "Working directory has uncommitted changes"
            git status --short
            read -p "Continue with update? (y/N): " -n 1 -r
            echo
            if [[ ! $REPLY =~ ^[Yy]$ ]]; then
                exit 1
            fi
        fi
    fi
    
    # Check disk space
    DISK_USAGE=$(df "$PROJECT_DIR" | tail -1 | awk '{print $5}' | sed 's/%//')
    if [ "$DISK_USAGE" -gt 85 ]; then
        log_warning "Disk usage is high: ${DISK_USAGE}%"
    fi
    
    log_success "Pre-update checks completed"
}

# Create update backup
create_update_backup() {
    log_info "Creating update backup..."
    
    mkdir -p "$BACKUP_DIR"
    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    
    # Backup current state
    tar -czf "$BACKUP_DIR/pre_update_$TIMESTAMP.tar.gz" \
        --exclude=node_modules \
        --exclude=vendor \
        --exclude=storage/logs \
        --exclude=.git \
        -C "$PROJECT_DIR" . 2>/dev/null || true
    
    # Backup database
    log_info "Backing up database..."
    docker-compose exec -T postgres pg_dump -U ecocomfort ecocomfort > "$BACKUP_DIR/database_pre_update_$TIMESTAMP.sql"
    
    # Store current container versions
    docker-compose images > "$BACKUP_DIR/container_versions_$TIMESTAMP.txt"
    
    log_success "Backup created for rollback: pre_update_$TIMESTAMP"
}

# Update backend
update_backend() {
    log_info "Updating backend..."
    
    cd "$PROJECT_DIR"
    
    case $UPDATE_TYPE in
        "major"|"minor")
            log_info "Updating Composer dependencies..."
            docker-compose exec -T backend composer update --no-dev --optimize-autoloader
            ;;
        "patch"|"hotfix")
            log_info "Installing/updating Composer dependencies..."
            docker-compose exec -T backend composer install --no-dev --optimize-autoloader
            ;;
    esac
    
    # Run database migrations
    log_info "Running database migrations..."
    docker-compose exec -T backend php artisan migrate --force
    
    # Clear and cache configurations
    log_info "Clearing caches..."
    docker-compose exec -T backend php artisan config:clear
    docker-compose exec -T backend php artisan cache:clear
    docker-compose exec -T backend php artisan route:clear
    docker-compose exec -T backend php artisan view:clear
    
    # Rebuild caches
    log_info "Rebuilding caches..."
    docker-compose exec -T backend php artisan config:cache
    docker-compose exec -T backend php artisan route:cache
    docker-compose exec -T backend php artisan view:cache
    
    log_success "Backend updated"
}

# Update frontend
update_frontend() {
    log_info "Updating frontend..."
    
    FRONTEND_DIR="$PROJECT_DIR/frontend"
    
    if [ -d "$FRONTEND_DIR" ]; then
        cd "$FRONTEND_DIR"
        
        case $UPDATE_TYPE in
            "major"|"minor")
                log_info "Updating npm dependencies..."
                npm update
                ;;
            "patch"|"hotfix")
                log_info "Installing npm dependencies..."
                npm ci --only=production
                ;;
        esac
        
        # Build production assets
        log_info "Building production frontend..."
        npm run build
        
        cd "$PROJECT_DIR"
        log_success "Frontend updated"
    else
        log_warning "Frontend directory not found, skipping frontend update"
    fi
}

# Rolling update strategy
rolling_update() {
    log_info "Performing rolling update..."
    
    cd "$PROJECT_DIR"
    
    # Update images
    log_info "Pulling latest Docker images..."
    docker-compose pull
    
    # Rebuild custom images
    log_info "Rebuilding custom images..."
    docker-compose build --no-cache
    
    # Rolling restart services (maintain availability)
    SERVICES=($(docker-compose config --services))
    
    for service in "${SERVICES[@]}"; do
        # Skip database services during rolling update
        if [[ "$service" =~ ^(postgres|redis)$ ]]; then
            log_info "Skipping $service (persistent service)"
            continue
        fi
        
        log_info "Rolling update for service: $service"
        
        # Get current container ID
        CONTAINER_ID=$(docker-compose ps -q "$service")
        
        if [ -n "$CONTAINER_ID" ]; then
            # Start new container
            docker-compose up -d --no-deps "$service"
            
            # Wait for health check
            sleep 10
            
            # Verify service is healthy
            if docker-compose ps "$service" | grep -q "Up"; then
                log_success "Service $service updated successfully"
            else
                log_error "Service $service failed to start after update"
                return 1
            fi
        else
            log_warning "Service $service was not running"
            docker-compose up -d --no-deps "$service"
        fi
    done
}

# Health check after update
post_update_health_check() {
    log_info "Running post-update health checks..."
    
    local max_attempts=30
    local attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        log_info "Health check attempt $attempt/$max_attempts..."
        
        # Check API health
        if curl -f -s "http://localhost/api/health" > /dev/null; then
            log_success "API is healthy"
            
            # Check frontend
            if curl -f -s "http://localhost" > /dev/null; then
                log_success "Frontend is accessible"
                
                # Check specific functionality
                if curl -f -s "http://localhost/api/system/stats" > /dev/null; then
                    log_success "System stats endpoint working"
                fi
                
                return 0
            fi
        fi
        
        sleep 5
        ((attempt++))
    done
    
    log_error "Post-update health check failed"
    return 1
}

# Rollback function
rollback() {
    log_error "Rolling back update..."
    
    cd "$PROJECT_DIR"
    
    # Find latest backup
    LATEST_BACKUP=$(ls -t "$BACKUP_DIR"/pre_update_*.tar.gz 2>/dev/null | head -1)
    
    if [ -n "$LATEST_BACKUP" ]; then
        log_info "Rolling back to: $LATEST_BACKUP"
        
        # Stop current services
        docker-compose down
        
        # Restore backup
        tar -xzf "$LATEST_BACKUP" -C "$PROJECT_DIR"
        
        # Restore database
        BACKUP_DATE=$(basename "$LATEST_BACKUP" .tar.gz | sed 's/pre_update_//')
        DB_BACKUP="$BACKUP_DIR/database_pre_update_${BACKUP_DATE}.sql"
        
        if [ -f "$DB_BACKUP" ]; then
            log_info "Restoring database..."
            docker-compose up -d postgres
            sleep 30
            docker-compose exec -T postgres psql -U ecocomfort -d ecocomfort < "$DB_BACKUP"
        fi
        
        # Restart services
        docker-compose up -d
        
        log_warning "Rollback completed. Please investigate the update failure."
    else
        log_error "No backup found for rollback"
    fi
}

# Cleanup old backups
cleanup_backups() {
    log_info "Cleaning up old backups..."
    
    # Keep last 5 backups
    find "$BACKUP_DIR" -name "pre_update_*.tar.gz" -type f | sort -r | tail -n +6 | xargs rm -f
    find "$BACKUP_DIR" -name "database_pre_update_*.sql" -type f | sort -r | tail -n +6 | xargs rm -f
    find "$BACKUP_DIR" -name "container_versions_*.txt" -type f | sort -r | tail -n +6 | xargs rm -f
    
    log_success "Old backups cleaned up"
}

# Display update summary
display_update_summary() {
    log_success "🎉 Update completed successfully!"
    echo
    echo "📋 Update Summary:"
    echo "=================="
    echo "🔄 Update Type: $UPDATE_TYPE"
    echo "⏰ Update Time: $(date)"
    echo "📦 Services Updated: $(docker-compose ps --services | wc -l)"
    echo
    echo "🔍 Service Status:"
    echo "=================="
    docker-compose ps
    echo
    echo "📊 System Health:"
    echo "=================="
    if curl -f -s "http://localhost/api/health" | jq . 2>/dev/null; then
        log_success "System is healthy"
    else
        log_warning "Could not retrieve health status"
    fi
    echo
    echo "📝 Post-Update Tasks:"
    echo "===================="
    echo "- Monitor application logs: docker-compose logs -f"
    echo "- Check system performance"
    echo "- Verify all features are working"
    echo "- Update monitoring dashboards if needed"
}

# Main update function
main() {
    echo "🔄 EcoComfort Update Process"
    echo "============================"
    echo "Update Type: $UPDATE_TYPE"
    echo
    
    # Create log file
    echo "Starting update process at $(date)" > "$PROJECT_DIR/update.log"
    
    pre_update_checks
    create_update_backup
    
    # Perform update based on type
    case $UPDATE_TYPE in
        "major"|"minor"|"patch")
            update_backend
            update_frontend
            rolling_update
            ;;
        "hotfix")
            # Faster update for critical fixes
            update_backend
            rolling_update
            ;;
        *)
            log_error "Invalid update type: $UPDATE_TYPE"
            log_info "Valid types: major, minor, patch, hotfix"
            exit 1
            ;;
    esac
    
    # Health check and rollback if needed
    if ! post_update_health_check; then
        log_error "Update failed health check"
        read -p "Rollback to previous version? (y/N): " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            rollback
            exit 1
        fi
    fi
    
    cleanup_backups
    display_update_summary
    
    log_success "Update process completed successfully!"
}

# Handle script interruption
trap 'log_error "Update process interrupted"; rollback; exit 1' INT TERM

# Show usage if no valid update type
if [ $# -eq 0 ]; then
    echo "Usage: $0 [major|minor|patch|hotfix]"
    echo
    echo "Update Types:"
    echo "  major   - Major version update (breaking changes possible)"
    echo "  minor   - Minor version update (new features, backward compatible)"
    echo "  patch   - Patch update (bug fixes, backward compatible)"
    echo "  hotfix  - Critical hotfix (security fixes, minimal changes)"
    echo
    echo "Examples:"
    echo "  $0 minor    # Update to next minor version"
    echo "  $0 hotfix   # Apply critical security patches"
    exit 1
fi

# Run main function
main "$@"