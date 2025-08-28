# 🌱 EcoComfort - Système IoT de Gestion Énergétique

## 📋 Vue d'ensemble

EcoComfort est un système IoT avancé de gestion énergétique qui surveille en temps réel les capteurs RuuviTag pour détecter les ouvertures de portes/fenêtres et calculer les pertes énergétiques. Le système intègre la gamification pour encourager les comportements éco-responsables.

### 🏗️ Architecture

```
RuuviTag → Raspberry Pi → MQTT → Laravel Subscriber → Redis Cache → PostgreSQL → WebSocket → Frontend
```

**Stack Technique :**
- **Backend :** Laravel 11 (PHP 8.4)
- **Base de données :** PostgreSQL avec TimescaleDB (séries temporelles)
- **Cache :** Redis (cache + queues)
- **Messaging :** MQTT Mosquitto
- **WebSocket :** Laravel Reverb
- **Containerisation :** Docker + Docker Compose

### ⚡ Performances

- **Latence :** < 25ms end-to-end
- **WebSocket :** < 100ms temps réel
- **Cache Redis :** TTL 5 minutes
- **Détection portes :** 95% précision (Filtre Kalman)

## 🚀 Installation

### Prérequis

- Docker et Docker Compose
- Git

### Installation rapide

```bash
# Cloner le repository
git clone <repository-url>
cd Ecoconfort

# Copier et configurer l'environnement
cp .env.example .env
# Éditer le fichier .env avec vos paramètres

# Lancer avec Docker
docker-compose up -d

# Installer les dépendances
docker-compose exec app composer install

# Générer la clé d'application
docker-compose exec app php artisan key:generate

# Exécuter les migrations
docker-compose exec app php artisan migrate

# Créer les partitions initiales
docker-compose exec app php artisan db:seed --class=InitialPartitionsSeeder
```

## 🔧 Configuration

### Variables d'environnement

```bash
# Application
APP_NAME=EcoComfort
APP_URL=http://localhost:8000

# Base de données
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_DATABASE=ecocomfort
DB_USERNAME=ecocomfort
DB_PASSWORD=ecocomfort_secret

# Redis
REDIS_HOST=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# WebSocket (Reverb)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=app-id
REVERB_APP_KEY=app-key
REVERB_APP_SECRET=app-secret
REVERB_HOST=localhost
REVERB_PORT=8080

# MQTT
MQTT_HOST=mosquitto
MQTT_PORT=1883
MQTT_CLIENT_ID=ecocomfort_laravel
MQTT_TOPIC_TEMPERATURE=112
MQTT_TOPIC_HUMIDITY=114
MQTT_TOPIC_ACCELEROMETER=127

# Énergie
ENERGY_PRICE_PER_KWH=0.15
```

### Configuration MQTT

Le système écoute 3 topics MQTT :
- **Topic 112** : Données de température
- **Topic 114** : Données d'humidité  
- **Topic 127** : Données d'accéléromètre (détection portes)

Format JSON des messages :
```json
{
  "mac": "AA:BB:CC:DD:EE:FF",
  "temperature": 21.5,
  "humidity": 45.2,
  "acceleration": {"x": 0.1, "y": -0.05, "z": 9.8},
  "battery": 85
}
```

## 🏃‍♂️ Utilisation

### Démarrage des services

```bash
# Lancer tous les services
docker-compose up -d

# Démarrer l'écoute MQTT
docker-compose exec app php artisan mqtt:listen

# Démarrer le serveur WebSocket
docker-compose exec reverb php artisan reverb:start

# Démarrer les queues
docker-compose exec queue php artisan queue:work
```

### API REST

L'API REST est disponible sur `http://localhost:8000/api/`

**Endpoints principaux :**

- `GET /api/dashboard/overview` - Vue d'ensemble du système
- `GET /api/dashboard/sensor-data` - Données capteurs en temps réel
- `GET /api/dashboard/alerts` - Alertes et notifications
- `GET /api/sensors` - Gestion des capteurs
- `GET /api/events` - Événements et alertes
- `GET /api/gamification/profile` - Profil gamification

### WebSocket

Connexion WebSocket via Laravel Reverb sur le port 8080.

**Canaux disponibles :**
- `organization.{id}` - Événements organisation
- `room.{id}` - Événements salle
- `sensor.{id}` - Données capteur spécifique
- `alerts` - Alertes système

## 📊 Modèle de données

### Structure principale

```sql
ORGANIZATION (uuid, name, surface_m2, target_percent)
├── USER (uuid, org_id, email, role, points)
├── BUILDING 
    ├── ROOM
        ├── SENSOR (uuid, room_id, mac_address, position, battery_level)
            ├── SENSOR_DATA (bigint id PK, sensor_id, timestamp, temperature, humidity, acceleration_xyz, door_state, energy_loss_watts)
            └── EVENT (uuid, sensor_id, type, severity, cost_impact)
└── GAMIFICATION (uuid, user_id, action, points, created_at)
```

### Partitioning

Les tables `sensor_data` sont partitionnées par mois automatiquement :
```sql
-- Exemple de partition mensuelle
sensor_data_2024_01 FOR VALUES FROM ('2024-01-01') TO ('2024-02-01')
```

## 🧠 Services Core

### MQTTSubscriberService
- Écoute les topics MQTT en temps réel
- Traite les données des capteurs RuuviTag
- Gère la calibration automatique
- Déclenche les alertes

### DoorDetectionService
- **Filtre Kalman** pour 95% de précision
- Détection d'ouverture/fermeture de portes
- Machine à états (fermé → ouverture → ouvert → fermeture)
- Analyse des patterns de mouvement

### EnergyCalculatorService
- Calcul des pertes énergétiques en temps réel
- Formules thermodynamiques : `Q = m × c × ΔT`
- Prise en compte du vent, humidité, effet de cheminée
- Projections et économies potentielles

### NotificationService
- **Alertes graduées** : info → warning → critical
- Throttling intelligent pour éviter le spam
- Diffusion WebSocket temps réel
- Calcul d'impact financier

### GamificationService
- **Système de points** et niveaux
- **Badges** et récompenses
- **Leaderboards** par période
- **Challenges** d'équipe

## 🎮 Gamification

### Système de points
- Fermer une porte : **5 points**
- Reconnaître une alerte : **3 points**  
- Réponse rapide (<30s) : **8 points**
- Connexion quotidienne : **2 points**
- Objectif d'équipe : **50 points**

### Badges disponibles
- 🏆 **Energy Saver** - Économisé 100 kWh
- 🚪 **Door Guardian** - Fermé 50 portes rapidement
- 🔔 **Alert Master** - Reconnu 100 alertes
- ⚡ **Streak Legend** - 30 jours de connexion
- 👥 **Team Player** - 10 objectifs d'équipe

### Niveaux
10 niveaux avec seuils croissants :
- Niveau 1 : 0 points
- Niveau 2 : 100 points
- Niveau 5 : 1000 points
- Niveau 10 : 5200 points

## 🔍 Monitoring

### Logs
```bash
# Logs MQTT
tail -f storage/logs/mqtt.log

# Logs capteurs
tail -f storage/logs/sensors.log

# Logs énergétiques
tail -f storage/logs/energy.log
```

### Métriques de santé
```bash
# Health check
curl http://localhost:8000/api/health

# Statistiques système (admin)
curl -H "Authorization: Bearer <token>" http://localhost:8000/api/system/stats
```

### Commandes utiles
```bash
# Réinitialiser la détection d'un capteur
php artisan sensor:reset-detection {sensor-id}

# Nettoyer les anciennes partitions
php artisan partition:cleanup --older-than=90d

# Recalculer les statistiques énergétiques
php artisan energy:recalculate --period=7d

# Synchroniser la gamification
php artisan gamification:sync
```

## 🧪 Tests

```bash
# Tests unitaires
docker-compose exec app php artisan test

# Tests d'intégration
docker-compose exec app php artisan test --testsuite=Integration

# Tests de performance
docker-compose exec app php artisan test --testsuite=Performance
```

## 📈 Performance et Optimisation

### Optimisations implémentées
- **Cache Redis** avec TTL intelligent
- **Partitioning PostgreSQL** par mois
- **Index optimisés** sur timestamp, sensor_id, door_state
- **Kalman Filter** optimisé pour temps réel
- **WebSocket** avec channels privés
- **Queue processing** asynchrone

### Seuils de performance
- Traitement MQTT : < 25ms
- Calculs énergétiques : < 50ms  
- WebSocket broadcast : < 100ms
- Requêtes API : < 200ms
- Détection porte : < 500ms

## 🔐 Sécurité

- **Authentication** via Laravel Sanctum
- **Authorization** par organisation/rôle
- **Channels WebSocket** privés et autorisés
- **Validation** des données capteurs
- **Rate limiting** sur l'API
- **Logs sécurisés** sans données sensibles

## 🤝 Contribution

1. Fork le projet
2. Créer une branche feature (`git checkout -b feature/AmazingFeature`)
3. Commit vos changements (`git commit -m 'Add some AmazingFeature'`)
4. Push vers la branche (`git push origin feature/AmazingFeature`)
5. Ouvrir une Pull Request

## 📝 Licence

Ce projet est sous licence propriétaire. Tous droits réservés.

## 🆘 Support

Pour toute question ou problème :
- 📧 Email : support@ecocomfort.com
- 📚 Documentation : [docs.ecocomfort.com](docs.ecocomfort.com)
- 🐛 Issues : GitHub Issues

---

**Développé avec ❤️ pour un avenir énergétique durable** 🌍
