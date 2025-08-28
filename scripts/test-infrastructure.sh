#!/bin/bash

# EcoComfort Infrastructure Test Script
# Tests all components of the infrastructure

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
DOMAIN=${DOMAIN:-"localhost"}

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Test results
TESTS_PASSED=0
TESTS_FAILED=0
TESTS_TOTAL=0

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[✓]${NC} $1"
    ((TESTS_PASSED++))
}

log_failure() {
    echo -e "${RED}[✗]${NC} $1"
    ((TESTS_FAILED++))
}

log_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

# Run a test and track results
run_test() {
    local test_name="$1"
    local test_command="$2"
    
    ((TESTS_TOTAL++))
    log_info "Running test: $test_name"
    
    if eval "$test_command"; then
        log_success "$test_name"
        return 0
    else
        log_failure "$test_name"
        return 1
    fi
}

# Test Docker services
test_docker_services() {
    log_info "Testing Docker services..."
    
    cd "$PROJECT_DIR"
    
    # Check if Docker is running
    run_test "Docker daemon" "docker info >/dev/null 2>&1"
    
    # Check if Docker Compose is available
    run_test "Docker Compose" "docker-compose version >/dev/null 2>&1"
    
    # Check if services are defined
    run_test "Docker Compose config" "docker-compose config >/dev/null 2>&1"
    
    # List expected services
    local expected_services=("backend" "frontend" "nginx" "postgres" "redis" "mosquitto" "reverb")
    
    for service in "${expected_services[@]}"; do
        run_test "Service definition: $service" "docker-compose config --services | grep -q '^$service$'"
    done
}

# Test network connectivity
test_network_connectivity() {
    log_info "Testing network connectivity..."
    
    # Test if we can reach Docker networks
    run_test "Docker network" "docker network ls | grep -q ecocomfort"
    
    # Test localhost connectivity
    run_test "Localhost connectivity" "ping -c 1 localhost >/dev/null 2>&1"
    
    # Test DNS resolution
    run_test "DNS resolution" "nslookup google.com >/dev/null 2>&1"
}

# Test file permissions and structure
test_file_structure() {
    log_info "Testing file structure and permissions..."
    
    # Check critical directories
    local critical_dirs=("docker/nginx" "docker/mosquitto" "docker/ssl" "scripts")
    
    for dir in "${critical_dirs[@]}"; do
        run_test "Directory exists: $dir" "[ -d '$PROJECT_DIR/$dir' ]"
    done
    
    # Check critical files
    local critical_files=(
        "docker-compose.yml"
        "docker/nginx/nginx.conf"
        "docker/nginx/ssl.conf"
        "docker/mosquitto/mosquitto.conf"
        "config/mqtt.php"
        "scripts/deploy.sh"
    )
    
    for file in "${critical_files[@]}"; do
        run_test "File exists: $file" "[ -f '$PROJECT_DIR/$file' ]"
    done
    
    # Check script permissions
    local scripts=("deploy.sh" "dev-setup.sh" "prod-deploy.sh" "update.sh" "setup-mqtt-auth.sh")
    
    for script in "${scripts[@]}"; do
        if [ -f "$PROJECT_DIR/scripts/$script" ]; then
            run_test "Script executable: $script" "[ -x '$PROJECT_DIR/scripts/$script' ]"
        fi
    done
}

# Test configuration files
test_configuration() {
    log_info "Testing configuration files..."
    
    cd "$PROJECT_DIR"
    
    # Test docker-compose.yml syntax
    run_test "Docker Compose syntax" "docker-compose config >/dev/null 2>&1"
    
    # Test nginx configuration syntax
    if [ -f "docker/nginx/nginx.conf" ]; then
        run_test "Nginx config syntax" "docker run --rm -v '$PWD/docker/nginx/nginx.conf:/etc/nginx/nginx.conf:ro' nginx:alpine nginx -t"
    fi
    
    # Test MQTT configuration
    if [ -f "docker/mosquitto/mosquitto.conf" ]; then
        # Check if configuration has basic required settings
        run_test "MQTT config has persistence" "grep -q '^persistence ' docker/mosquitto/mosquitto.conf"
        run_test "MQTT config has listeners" "grep -q '^listener ' docker/mosquitto/mosquitto.conf"
    fi
    
    # Test SSL certificate structure
    run_test "SSL cert generation script" "[ -f 'docker/ssl/generate-certs.sh' ]"
    
    if [ -f "docker/ssl/generate-certs.sh" ]; then
        run_test "SSL script executable" "[ -x 'docker/ssl/generate-certs.sh' ]"
    fi
}

# Test Laravel backend configuration
test_backend_config() {
    log_info "Testing Laravel backend configuration..."
    
    cd "$PROJECT_DIR"
    
    # Check composer.json
    run_test "Composer.json exists" "[ -f 'composer.json' ]"
    
    if [ -f "composer.json" ]; then
        # Check for required packages
        run_test "JWT package in composer" "grep -q 'tymon/jwt-auth' composer.json"
        run_test "MQTT package in composer" "grep -q 'php-mqtt/client' composer.json"
        run_test "Laravel Reverb in composer" "grep -q 'laravel/reverb' composer.json"
    fi
    
    # Check Laravel structure
    local laravel_dirs=("app/Http/Controllers" "app/Models" "app/Services" "config" "routes")
    
    for dir in "${laravel_dirs[@]}"; do
        run_test "Laravel dir: $dir" "[ -d '$dir' ]"
    done
    
    # Check middleware
    run_test "JWT middleware" "[ -f 'app/Http/Middleware/JwtMiddleware.php' ]"
    run_test "Rate limiter middleware" "[ -f 'app/Http/Middleware/ApiRateLimiter.php' ]"
    run_test "CORS middleware" "[ -f 'app/Http/Middleware/Cors.php' ]"
    
    # Check configuration files
    local config_files=("mqtt.php" "jwt.php")
    
    for config in "${config_files[@]}"; do
        run_test "Config file: $config" "[ -f 'config/$config' ]"
    done
    
    # Check models
    local models=("User.php" "Sensor.php" "SensorData.php" "Event.php")
    
    for model in "${models[@]}"; do
        run_test "Model: $model" "[ -f 'app/Models/$model' ]"
    done
}

# Test frontend configuration
test_frontend_config() {
    log_info "Testing React frontend configuration..."
    
    FRONTEND_DIR="$PROJECT_DIR/frontend"
    
    if [ -d "$FRONTEND_DIR" ]; then
        cd "$FRONTEND_DIR"
        
        # Check package.json
        run_test "Frontend package.json" "[ -f 'package.json' ]"
        
        if [ -f "package.json" ]; then
            # Check for required packages
            run_test "React in package.json" "grep -q '\"react\"' package.json"
            run_test "TypeScript in package.json" "grep -q '\"typescript\"' package.json"
            run_test "Tailwind in package.json" "grep -q '\"tailwindcss\"' package.json"
            run_test "Vite in package.json" "grep -q '\"vite\"' package.json"
        fi
        
        # Check configuration files
        local config_files=("vite.config.ts" "tailwind.config.js" "tsconfig.json")
        
        for config in "${config_files[@]}"; do
            run_test "Frontend config: $config" "[ -f '$config' ]"
        done
        
        # Check source structure
        local src_dirs=("src/components" "src/pages" "src/services" "src/types")
        
        for dir in "${src_dirs[@]}"; do
            run_test "Frontend dir: $dir" "[ -d '$dir' ]"
        done
        
        cd "$PROJECT_DIR"
    else
        log_warning "Frontend directory not found, skipping frontend tests"
    fi
}

# Test environment configuration
test_environment() {
    log_info "Testing environment configuration..."
    
    cd "$PROJECT_DIR"
    
    # Check if .env.example exists
    run_test ".env.example exists" "[ -f '.env.example' ]"
    
    # If .env exists, check basic structure
    if [ -f ".env" ]; then
        run_test ".env file exists" "true"
        
        # Check for required variables
        local required_vars=("APP_NAME" "DB_CONNECTION" "REDIS_HOST" "MQTT_HOST" "JWT_SECRET")
        
        for var in "${required_vars[@]}"; do
            run_test "Env var: $var" "grep -q '^$var=' .env"
        done
    else
        log_warning ".env file not found (normal for fresh setup)"
    fi
}

# Test deployment readiness
test_deployment_readiness() {
    log_info "Testing deployment readiness..."
    
    cd "$PROJECT_DIR"
    
    # Check Node.js version
    if command -v node >/dev/null 2>&1; then
        NODE_VERSION=$(node --version | cut -d'v' -f2 | cut -d'.' -f1)
        run_test "Node.js version >= 20" "[ '$NODE_VERSION' -ge 20 ]"
    else
        log_failure "Node.js not installed"
    fi
    
    # Check if required commands are available
    local commands=("curl" "openssl" "tar" "gzip")
    
    for cmd in "${commands[@]}"; do
        run_test "Command available: $cmd" "command -v $cmd >/dev/null 2>&1"
    done
    
    # Check system resources
    # Check available memory (at least 1GB)
    if command -v free >/dev/null 2>&1; then
        MEMORY_MB=$(free -m | awk 'NR==2{print $2}')
        run_test "Sufficient memory (>1GB)" "[ '$MEMORY_MB' -gt 1024 ]"
    elif command -v sysctl >/dev/null 2>&1; then
        # macOS
        MEMORY_BYTES=$(sysctl -n hw.memsize)
        MEMORY_MB=$((MEMORY_BYTES / 1024 / 1024))
        run_test "Sufficient memory (>1GB)" "[ '$MEMORY_MB' -gt 1024 ]"
    fi
    
    # Check available disk space (at least 5GB)
    DISK_AVAIL_GB=$(df "$PROJECT_DIR" | tail -1 | awk '{print int($4/1024/1024)}')
    run_test "Sufficient disk space (>5GB)" "[ '$DISK_AVAIL_GB' -gt 5 ]"
}

# Test security configuration
test_security() {
    log_info "Testing security configuration..."
    
    cd "$PROJECT_DIR"
    
    # Check file permissions on sensitive files
    if [ -f ".env" ]; then
        run_test ".env file permissions" "[ '$(stat -c %a .env 2>/dev/null || stat -f %A .env)' -le 600 ]"
    fi
    
    # Check SSL configuration
    run_test "SSL cert generation script" "[ -f 'docker/ssl/generate-certs.sh' ]"
    
    # Check MQTT auth files
    run_test "MQTT password file protection" "[ -f 'docker/mosquitto/passwd' ]"
    
    # Check for security middleware
    run_test "Security headers middleware" "[ -f 'app/Http/Middleware/SecurityHeaders.php' ]"
    
    # Check JWT configuration
    if [ -f "config/jwt.php" ]; then
        run_test "JWT config exists" "[ -f 'config/jwt.php' ]"
        run_test "JWT TTL configured" "grep -q 'ttl.*15' config/jwt.php"
    fi
}

# Generate test report
generate_report() {
    echo
    echo "🧪 Infrastructure Test Report"
    echo "=============================="
    echo "📊 Total Tests: $TESTS_TOTAL"
    echo "✅ Passed: $TESTS_PASSED"
    echo "❌ Failed: $TESTS_FAILED"
    echo "📈 Success Rate: $(( (TESTS_PASSED * 100) / TESTS_TOTAL ))%"
    echo
    
    if [ $TESTS_FAILED -eq 0 ]; then
        log_success "🎉 All tests passed! Infrastructure is ready for deployment."
        echo
        echo "✅ Next Steps:"
        echo "1. Run './scripts/dev-setup.sh' for development"
        echo "2. Run 'DOMAIN=your-domain.com ./scripts/deploy.sh' for production"
        echo "3. Monitor logs with 'docker-compose logs -f'"
        
        return 0
    else
        log_failure "❌ Some tests failed. Please address the issues above."
        echo
        echo "🔧 Common Solutions:"
        echo "1. Install missing dependencies"
        echo "2. Fix file permissions: chmod +x scripts/*.sh"
        echo "3. Create missing directories: mkdir -p docker/{nginx,ssl,mosquitto}"
        echo "4. Review configuration files for syntax errors"
        
        return 1
    fi
}

# Main test runner
main() {
    echo "🔍 EcoComfort Infrastructure Test Suite"
    echo "========================================"
    echo "Testing infrastructure components and configuration..."
    echo
    
    # Run all test suites
    test_docker_services
    echo
    
    test_network_connectivity
    echo
    
    test_file_structure
    echo
    
    test_configuration
    echo
    
    test_backend_config
    echo
    
    test_frontend_config
    echo
    
    test_environment
    echo
    
    test_deployment_readiness
    echo
    
    test_security
    echo
    
    # Generate final report
    generate_report
}

# Run main function
main "$@"