# EcoComfort Deployment Scripts

This directory contains deployment and management scripts for the EcoComfort IoT energy management system.

## 📋 Available Scripts

### 🛠️ `dev-setup.sh`
Sets up a local development environment.

```bash
# Quick development setup
./scripts/dev-setup.sh
```

**Features:**
- Creates development `.env` file
- Sets up Laravel backend with local database
- Installs frontend dependencies  
- Starts essential Docker services (PostgreSQL, Redis, MQTT)
- Runs database migrations

**After running:**
- Start backend: `php artisan serve`
- Start frontend: `cd frontend && npm run dev`
- Start WebSocket: `php artisan reverb:start`
- Start MQTT listener: `php artisan mqtt:listen`

### 🚀 `deploy.sh`
Complete production deployment script.

```bash
# Deploy to production
DOMAIN=your-domain.com ./scripts/deploy.sh
```

**Features:**
- Full infrastructure deployment
- SSL certificate generation
- MQTT authentication setup
- Frontend production build
- Docker Compose orchestration
- Health checks and monitoring

**Requirements:**
- Docker and Docker Compose
- Node.js 20+
- Valid domain name
- SSL certificates (self-signed generated if not provided)

### 🏭 `prod-deploy.sh`
Production-specific deployment with enhanced security.

```bash
# Production deployment with custom domain
DOMAIN=ecocomfort.mycompany.com ./scripts/prod-deploy.sh
```

**Features:**
- Production environment configuration
- Enhanced security settings
- Performance optimization
- Automated backups
- Redis and PostgreSQL tuning
- Security hardening

**Security Features:**
- Strong password generation
- File permission hardening
- Production SSL configuration
- Security headers
- Rate limiting configuration

### 🔄 `update.sh`
Rolling update system for running deployments.

```bash
# Apply updates with zero downtime
./scripts/update.sh minor

# Available update types:
./scripts/update.sh major   # Breaking changes
./scripts/update.sh minor   # New features
./scripts/update.sh patch   # Bug fixes  
./scripts/update.sh hotfix  # Critical security fixes
```

**Features:**
- Zero-downtime rolling updates
- Automatic backups before updates
- Health checks with rollback
- Database migration handling
- Frontend rebuild and deployment
- Service-by-service updates

### 🔐 `setup-mqtt-auth.sh`
Sets up MQTT broker authentication.

```bash
# Setup MQTT authentication
./scripts/setup-mqtt-auth.sh
```

**Features:**
- Generates MQTT password hashes
- Creates user authentication file
- Sets proper file permissions
- Configures broker security

## 🎯 Quick Start Workflows

### Development Setup
```bash
# 1. Clone repository and navigate to project
cd ecocomfort

# 2. Run development setup
./scripts/dev-setup.sh

# 3. Start development servers
php artisan serve &
cd frontend && npm run dev &
php artisan reverb:start &
php artisan mqtt:listen &
```

### Production Deployment
```bash
# 1. Set your domain
export DOMAIN=ecocomfort.mycompany.com

# 2. Deploy to production
./scripts/prod-deploy.sh

# 3. Verify deployment
curl https://$DOMAIN/api/health
```

### Updating Production
```bash
# 1. Pull latest changes
git pull origin main

# 2. Apply updates
./scripts/update.sh minor

# 3. Verify update
curl http://localhost/api/health
```

## 🔧 Configuration Files

### Environment Files
- `.env` - Main environment configuration
- `.env.example` - Template for environment variables

### Docker Configuration  
- `docker-compose.yml` - Main service orchestration
- `docker-compose.override.yml` - Development overrides (auto-generated)

### Service Configurations
- `docker/nginx/` - Nginx proxy configuration
- `docker/mosquitto/` - MQTT broker settings
- `docker/ssl/` - SSL certificate storage
- `docker/postgres/` - PostgreSQL optimization
- `docker/redis/` - Redis configuration

## 🔍 Monitoring and Maintenance

### Health Checks
```bash
# Check all services
docker-compose ps

# API health check
curl http://localhost/api/health

# Service logs
docker-compose logs -f [service_name]
```

### Database Operations
```bash
# Database backup
docker-compose exec postgres pg_dump -U ecocomfort ecocomfort > backup.sql

# Database restore  
docker-compose exec -T postgres psql -U ecocomfort -d ecocomfort < backup.sql

# Run migrations
docker-compose exec backend php artisan migrate
```

### Performance Monitoring
```bash
# System statistics
curl http://localhost/api/system/stats

# Container resource usage
docker stats

# Disk usage
df -h
```

## 🚨 Troubleshooting

### Common Issues

**Services not starting:**
```bash
# Check Docker daemon
systemctl status docker

# Check logs
docker-compose logs -f

# Restart services
docker-compose restart
```

**Database connection issues:**
```bash
# Check PostgreSQL
docker-compose exec postgres pg_isready -U ecocomfort

# Reset database
docker-compose down -v
docker-compose up -d postgres
docker-compose exec backend php artisan migrate
```

**MQTT connection problems:**
```bash
# Test MQTT connectivity
mosquitto_pub -h localhost -t test -m "hello"

# Check MQTT logs
docker-compose logs mosquitto
```

**Frontend build failures:**
```bash
# Clear npm cache
cd frontend && npm cache clean --force

# Reinstall dependencies
rm -rf node_modules package-lock.json
npm install
```

### Log Locations
- Application logs: `storage/logs/laravel.log`
- Deployment logs: `deploy.log`
- Update logs: `update.log`
- Docker logs: `docker-compose logs`

### Recovery Procedures

**Rollback deployment:**
```bash
# Automatic rollback during failed update
./scripts/update.sh minor  # Will prompt for rollback on failure

# Manual rollback
cd backups
tar -xzf pre_update_YYYYMMDD_HHMMSS.tar.gz -C ../
docker-compose up -d
```

**Reset to clean state:**
```bash
# Stop all services and remove data
docker-compose down -v

# Remove containers and images
docker system prune -a

# Redeploy
./scripts/deploy.sh
```

## 📚 Additional Resources

- **Laravel Documentation**: https://laravel.com/docs
- **Docker Compose Reference**: https://docs.docker.com/compose/
- **React Documentation**: https://reactjs.org/docs
- **MQTT Documentation**: https://mosquitto.org/documentation/
- **PostgreSQL Tuning**: https://pgtune.leopard.in.ua/

## 🆘 Support

For issues and questions:
1. Check the troubleshooting section above
2. Review service logs: `docker-compose logs -f`
3. Check system health: `curl http://localhost/api/health`
4. Verify environment configuration in `.env`

## 🔄 Contributing

When adding new scripts:
1. Make scripts executable: `chmod +x script.sh`
2. Add error handling: `set -e`
3. Include logging functions
4. Update this README
5. Test in development environment first