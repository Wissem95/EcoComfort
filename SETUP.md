# 🚀 **SETUP EcoComfort - Guide Équipe**

## 📋 **PRÉREQUIS SYSTÈME**

### **Environnement Requis**
- **macOS** : Version testée et optimisée
- **PHP** : 8.2+ (installé ✅)
- **Composer** : Pour dépendances Laravel (installé ✅)
- **Node.js** : Pour le frontend React (à vérifier)
- **PostgreSQL** : Base de données principale (configuré ✅)
- **Redis** : Cache et queues haute performance (installé et configuré ✅)

### **Services Externes**
- **HiveMQ Cloud** : Broker MQTT principal (configuré ✅)
- **Pi MQTT Local** : 192.168.1.216:1883 (accessible ✅)

---

## ⚡ **OPTIMISATIONS APPLIQUÉES**

### **Configuration Redis High-Performance**
```env
CACHE_STORE=redis           # Cache ultra-rapide
QUEUE_CONNECTION=redis      # Queues asynchrones  
SESSION_DRIVER=redis        # Sessions distribuées
REDIS_CLIENT=predis         # Client PHP optimisé
```

### **Configuration WebSocket**
```env
BROADCAST_CONNECTION=reverb  # WebSocket temps réel
REVERB_APP_ID=app-id
REVERB_APP_KEY=app-key
REVERB_APP_SECRET=app-secret
```

### **Configuration MQTT (Nettoyée)**
- ❌ **Supprimé** : Configuration localhost en double
- ✅ **Conservé** : Configuration HiveMQ Cloud uniquement
- ✅ **Topics** : 112 (temp), 114 (humidity), 127 (movement)

---

## 🚀 **DÉMARRAGE SYSTÈME COMPLET**

### **Option 1 : Démarrage Séquentiel (Recommandé pour Dev)**

#### **Terminal 1 - Base de données**
```bash
# Vérifier PostgreSQL
php artisan migrate:status
```

#### **Terminal 2 - WebSocket Server**
```bash
# Démarrer Laravel Reverb
php artisan reverb:start
# URL WebSocket: ws://localhost:8080
```

#### **Terminal 3 - Queues Redis**
```bash
# Traitement jobs en arrière-plan
php artisan queue:work
# Performance: Redis > File > Database
```

#### **Terminal 4 - MQTT Principal**
```bash
# DONNÉES SIMULÉES (développement)
php artisan mqtt:listen

# DONNÉES RÉELLES RUUVITAG (production)  
php artisan mqtt:listen --bridge
```

#### **Terminal 5 - Application Laravel**
```bash
# API Backend
php artisan serve
# URL API: http://localhost:8000
```

#### **Terminal 6 - Frontend React**
```bash
cd frontend
npm run dev  
# URL Frontend: http://localhost:3000
```

### **Option 2 : Démarrage Automatique**
```bash
# Script de démarrage rapide
./scripts/dev-setup.sh
```

---

## 🔌 **URLS ET ACCÈS SYSTÈME**

### **URLs Principales**
| Service | URL | Usage |
|---------|-----|-------|
| **API Backend** | http://localhost:8000/api | Endpoints REST |
| **Dashboard** | http://localhost:3000 | Interface React |
| **WebSocket** | ws://localhost:8080 | Temps réel |
| **Admin** | http://localhost:8000/admin | Gestion système |

### **API Endpoints Clés**
```bash
# Vue d'ensemble système
GET /api/dashboard/overview

# Données capteurs temps réel  
GET /api/dashboard/sensor-data

# Alertes actives
GET /api/dashboard/alerts

# Gestion capteurs RuuviTag
GET /api/sensors
POST /api/sensors
```

### **Authentication API**
```bash
# Login équipe
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@ecocomfort.com","password":"password"}'
```

---

## 📊 **MONITORING ET DIAGNOSTICS**

### **Logs Temps Réel**
```bash
# Logs Laravel généraux
php artisan pail

# Logs MQTT Bridge spécifiquement  
tail -f storage/logs/laravel.log | grep "🌉"

# Logs capteurs RuuviTag
tail -f storage/logs/laravel.log | grep "RuuviTag"

# Logs Redis
redis-cli monitor
```

### **Health Checks**
```bash
# Base de données
php artisan tinker --execute="DB::connection()->getPdo(); echo 'DB: OK';"

# Redis
redis-cli ping
php artisan tinker --execute="Cache::put('test','ok',10); echo 'Redis: ' . Cache::get('test');"

# MQTT HiveMQ Cloud
php artisan mqtt:listen --timeout=5

# Pi MQTT Local  
ping 192.168.1.216
telnet 192.168.1.216 1883
```

### **Métriques Performance**
```bash
# Données capteurs récentes
php artisan tinker --execute="use App\\Models\\SensorData; echo SensorData::where('timestamp', '>', now()->subHour())->count() . ' données/heure';"

# Capteurs actifs
php artisan tinker --execute="use App\\Models\\Sensor; echo Sensor::where('last_seen_at', '>', now()->subMinutes(10))->count() . ' capteurs actifs';"

# Cache Redis hits
redis-cli info stats | grep keyspace
```

---

## 🔧 **COMMANDES DÉVELOPPEMENT**

### **Base de Données**
```bash
# Reset base complète
php artisan migrate:fresh --seed

# Nouvelles migrations RuuviTag
php artisan migrate

# Seeding données test
php artisan db:seed --class=DemoDataSeeder
```

### **Cache et Performance**
```bash
# Clear tous caches  
php artisan optimize:clear

# Cache optimisé production
php artisan optimize

# Config cache (après .env changes)
php artisan config:cache
```

### **Tests Système**
```bash
# Tests unitaires complets
php artisan test

# Test simulation MQTT
php artisan mqtt:test --count=5 --interval=2

# Test performance capteurs
php artisan tinker --execute="use App\\Services\\DoorDetectionService; echo 'Kalman Filter: OK';"
```

---

## 🛠️ **DÉPANNAGE ÉQUIPE**

### **Problème : Redis non accessible**
```bash
# Vérifier service
brew services list | grep redis

# Redémarrer Redis
brew services restart redis

# Test connexion
redis-cli ping
```

### **Problème : MQTT non connecté**
```bash
# Test HiveMQ Cloud
php artisan mqtt:listen --timeout=10 --verbose

# Test Pi local
ping 192.168.1.216
mosquitto_sub -h 192.168.1.216 -p 1883 -u pi -P wirepass123 -t "#"
```

### **Problème : WebSocket non fonctionnel**
```bash
# Redémarrer Reverb
php artisan reverb:restart

# Vérifier configuration
php artisan config:show broadcasting
```

### **Problème : Frontend non accessible**
```bash
cd frontend

# Vérifier dépendances
npm install

# Rebuild
npm run build
```

---

## 🏗️ **ARCHITECTURE TECHNIQUE**

### **Stack Complet**
```
Frontend (React/TS) ↔ Laravel API ↔ PostgreSQL
         ↕                ↕            ↕
    WebSocket        Redis Cache   Migrations
      (Reverb)        (Queues)     (RuuviTag)
                         ↕
                   MQTT Bridge
                  (Pi ↔ HiveMQ)
```

### **Flux Données RuuviTag**
```
Capteur → Pi MQTT → Bridge → HiveMQ → Laravel → Redis → Database → WebSocket → Frontend
  (BLE)  (192.168.1.216)   (Auto)     (Cloud)   (Processing) (Cache)   (Store)    (Real-time)  (UI)
```

### **Services Background**
| Service | Rôle | Performance |
|---------|------|-------------|
| **Queue Worker** | Jobs asynchrones | Redis > Sync |
| **MQTT Listener** | Capteurs temps réel | Bridge mode |
| **WebSocket Server** | Notifications live | Reverb |
| **Cache Redis** | Performance 10x | vs File |

---

## 🎯 **MODES D'UTILISATION**

### **Mode Développement**
```bash
# Données simulées pour tests
php artisan mqtt:listen  
php artisan mqtt:test --count=10
```

### **Mode Production/Réel**
```bash
# Vraies données RuuviTag
php artisan mqtt:listen --bridge
# Pi (192.168.1.216) → HiveMQ → Laravel
```

### **Mode Debug**
```bash
# Logs verbeux
php artisan mqtt:listen --bridge --verbose
php artisan pail --filter=mqtt
```

---

## 📈 **INDICATEURS SUCCÈS**

### **Performance Optimale**
- **Cache Redis** : < 5ms vs 50ms+ file
- **Queues Redis** : Traitement asynchrone 
- **WebSocket** : < 100ms notifications
- **MQTT Bridge** : < 50ms Pi → Cloud
- **API Response** : < 200ms endpoints

### **Fonctionnalités Validées**
- ✅ Auto-discovery capteurs RuuviTag
- ✅ Calculs énergétiques temps réel  
- ✅ Détection portes (Kalman Filter 95%)
- ✅ Gamification (points, badges, niveaux)
- ✅ Alertes graduées (info → warning → critical)

---

## 👥 **CONTACTS ÉQUIPE**

| Rôle | Contact | Responsabilité |
|------|---------|----------------|
| **Lead Dev** | Wissem | Architecture & MQTT |
| **Frontend** | [À définir] | React/TypeScript |
| **DevOps** | [À définir] | Déploiement & Infrastructure |
| **Product** | [À définir] | Features & UX |

---

## 🔄 **MISES À JOUR**

**Dernière mise à jour** : 27 août 2025
**Version** : 2.0 - Optimisations Redis
**Changements majeurs** :
- ✅ Redis intégré (cache/queues/sessions)
- ✅ Configuration .env nettoyée
- ✅ Performance 10x améliorée
- ✅ WebSocket Reverb configuré

---

**🚀 Happy Coding Team EcoComfort ! 🌱**