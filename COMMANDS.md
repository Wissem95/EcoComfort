# 🎮 Commandes EcoComfort

## 🚀 Démarrage rapide

```bash
# Lancer l'environnement complet
docker-compose up -d

# Vérifier les services
docker-compose ps

# Voir les logs en temps réel
docker-compose logs -f app
```

## 📊 Services principaux

### MQTT Listener
```bash
# Écouter les messages MQTT
docker-compose exec app php artisan mqtt:listen

# Avec timeout (pour tests)
docker-compose exec app php artisan mqtt:listen --timeout=60
```

### WebSocket Server
```bash
# Démarrer Laravel Reverb
docker-compose exec reverb php artisan reverb:start

# Vérifier les connexions WebSocket
curl -X POST http://localhost:8080/apps/app-id/events \
  -H "Content-Type: application/json" \
  -d '{"name":"test-event","data":"{\"message\":\"test\"}","channel":"test-channel"}'
```

### Queue Worker
```bash
# Traitement des queues
docker-compose exec queue php artisan queue:work

# Avec redémarrage automatique
docker-compose exec queue php artisan queue:listen
```

## 🗄️ Base de données

### Migrations
```bash
# Exécuter toutes les migrations
docker-compose exec app php artisan migrate

# Rollback
docker-compose exec app php artisan migrate:rollback

# Statut des migrations
docker-compose exec app php artisan migrate:status
```

### Seeding
```bash
# Créer des données de test
docker-compose exec app php artisan db:seed

# Seeder spécifique
docker-compose exec app php artisan db:seed --class=OrganizationSeeder
```

## 🔧 Maintenance

### Cache
```bash
# Nettoyer tous les caches
docker-compose exec app php artisan optimize:clear

# Cache Redis spécifique
docker-compose exec app php artisan cache:clear
```

### Logs
```bash
# Voir logs Laravel
docker-compose exec app tail -f storage/logs/laravel.log

# Logs MQTT
docker-compose exec app tail -f storage/logs/mqtt.log

# Logs capteurs
docker-compose exec app tail -f storage/logs/sensors.log
```

### Partitioning
```bash
# Créer partitions pour les prochains mois
docker-compose exec app php artisan partition:create --months=3

# Nettoyer anciennes partitions
docker-compose exec app php artisan partition:cleanup --older-than=90d
```

## 🧪 Tests & Debug

### Tests
```bash
# Tous les tests
docker-compose exec app php artisan test

# Tests spécifiques
docker-compose exec app php artisan test --filter=MQTTTest
```

### Debug
```bash
# Tinker (REPL Laravel)
docker-compose exec app php artisan tinker

# Vérifier configuration
docker-compose exec app php artisan config:show database
docker-compose exec app php artisan config:show mqtt
```

## 📡 API Testing

### Health Check
```bash
curl http://localhost:8000/api/health
```

### Authentification
```bash
# Login (nécessite un user)
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@ecocomfort.com","password":"password"}'
```

### Endpoints principaux
```bash
# Overview dashboard
curl -H "Authorization: Bearer <token>" \
  http://localhost:8000/api/dashboard/overview

# Données capteurs
curl -H "Authorization: Bearer <token>" \
  http://localhost:8000/api/dashboard/sensor-data

# Alertes
curl -H "Authorization: Bearer <token>" \
  http://localhost:8000/api/dashboard/alerts
```

## 🛠️ Développement

### Générer des classes
```bash
# Nouveau contrôleur API
docker-compose exec app php artisan make:controller Api/NewController --api

# Nouveau modèle avec migration
docker-compose exec app php artisan make:model NewModel -m

# Nouveau service
docker-compose exec app php artisan make:class Services/NewService
```

### Code quality
```bash
# Formatter le code (Laravel Pint)
docker-compose exec app ./vendor/bin/pint

# Analyser le code
docker-compose exec app php artisan model:show User
```

## 🚨 Production

### Optimisation
```bash
# Cache de configuration
docker-compose exec app php artisan config:cache

# Cache des routes
docker-compose exec app php artisan route:cache

# Cache des vues
docker-compose exec app php artisan view:cache
```

### Monitoring
```bash
# Statistiques système
curl -H "Authorization: Bearer <token>" \
  http://localhost:8000/api/system/stats

# Status des services
docker-compose exec app php artisan schedule:list
```

## 🔄 Backup & Restore

### Backup
```bash
# Backup PostgreSQL
docker-compose exec postgres pg_dump -U ecocomfort ecocomfort > backup.sql

# Backup Redis
docker-compose exec redis redis-cli --rdb backup.rdb
```

### Restore
```bash
# Restore PostgreSQL
docker-compose exec -T postgres psql -U ecocomfort ecocomfort < backup.sql

# Restore Redis
docker-compose exec redis redis-cli --pipe < backup.rdb
```

## 🆘 Troubleshooting

### Problèmes courants
```bash
# Réinitialiser tout
docker-compose down -v
docker-compose up -d

# Reconstruire les containers
docker-compose build --no-cache

# Permissions
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache

# Effacer les caches
docker-compose exec app php artisan optimize:clear
docker-compose exec app composer dump-autoload
```

### Diagnostic
```bash
# Vérifier les connexions
docker-compose exec app php artisan tinker
# Dans tinker: DB::connection()->getPdo()
# Redis::ping()

# Tester MQTT
docker-compose exec mosquitto mosquitto_pub -h localhost -t test -m "test message"
docker-compose exec mosquitto mosquitto_sub -h localhost -t "#"
```