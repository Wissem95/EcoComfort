# 🌉 MQTT Bridge Integration - EcoComfort

## 🎯 **INTEGRATION RÉUSSIE**

Le pont MQTT automatique a été **intégré nativement** dans votre architecture Laravel existante, **sans aucun fichier supplémentaire**.

## 🏗️ **ARCHITECTURE FINALE**

```
RuuviTag → Pi (Bluetooth→MQTT) → Laravel Bridge → HiveMQ Cloud → Laravel Processing → Database → Frontend
   ↓              ↓                     ↓                ↓                  ↓            ↓
Capteur       MQTT local        Pont automatique    Cloud MQTT        Processing     Dashboard
```

## ⚡ **FONCTIONNEMENT**

### **Mode Normal** (existant, inchangé)
```bash
php artisan mqtt:listen
```
- Se connecte à HiveMQ Cloud directement
- Traite les messages reçus
- Met à jour la base de données
- Compatible avec tous vos services existants

### **Mode Bridge** (nouveau)
```bash
php artisan mqtt:listen --bridge
```
- Se connecte au Pi (192.168.1.216:1883) ET à HiveMQ Cloud
- **Double traitement** :
  1. Republication instantanée vers HiveMQ Cloud
  2. Processing local immédiat en base de données
- Logs détaillés de tous les messages bridgés
- Reconnexion automatique en cas d'erreur

## 📡 **COMMANDES DISPONIBLES**

```bash
# Mode normal - Écoute HiveMQ Cloud
php artisan mqtt:listen

# Mode bridge - Pont Pi → HiveMQ 
php artisan mqtt:listen --bridge

# Avec timeout (5 secondes)
php artisan mqtt:listen --timeout=5

# Mode bridge avec logs détaillés
php artisan mqtt:listen --bridge --verbose

# Aide complète
php artisan mqtt:listen --help
```

## 🔧 **CONFIGURATION**

### **Pi MQTT** (hardcodé dans le bridge)
- **Host**: `192.168.1.216:1883`
- **Auth**: `pi/wirepass123`
- **Topics**: `112`, `114`, `127`

### **HiveMQ Cloud** (utilise votre .env existant)
- **Host**: Depuis `MQTT_HOST`
- **Port**: Depuis `MQTT_PORT`
- **Auth**: Depuis `MQTT_USERNAME` / `MQTT_PASSWORD`
- **TLS**: Selon `MQTT_USE_TLS`

## 🚀 **DÉPLOIEMENT EN PRODUCTION**

### **Option 1: Service systemd**
```bash
# Créer le service
sudo nano /etc/systemd/system/mqtt-bridge.service
```

```ini
[Unit]
Description=EcoComfort MQTT Bridge
After=network.target postgresql.service redis.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/ecocomfort
ExecStart=/usr/bin/php artisan mqtt:listen --bridge
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
# Activer le service
sudo systemctl enable mqtt-bridge
sudo systemctl start mqtt-bridge

# Voir les logs
journalctl -u mqtt-bridge -f
```

### **Option 2: Supervisor**
```ini
[program:mqtt-bridge]
command=php artisan mqtt:listen --bridge
directory=/var/www/ecocomfort
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/mqtt-bridge.log
```

## 📊 **MONITORING**

### **Logs Laravel**
```bash
# Voir les logs du bridge en temps réel
tail -f storage/logs/laravel.log | grep "Bridge\|🌉"

# Rechercher les erreurs
grep "Bridge mode error" storage/logs/laravel.log

# Statistiques des messages bridgés
grep "Bridged message" storage/logs/laravel.log | wc -l
```

### **Messages typiques**
```
🌉 Starting MQTT Bridge Mode
📥 Source: Pi MQTT (192.168.1.216:1883)
📤 Destination: HiveMQ Cloud (d3d4e2f99dec42d1b9a73709c8fa4af0.s1.eu.hivemq.cloud:8883)
✅ Connected to Pi MQTT broker
✅ Connected to HiveMQ Cloud broker
🔔 Subscribed to Pi topic: 112
🌉 Bridged message: topic=112, from=Pi, to=HiveMQ Cloud, data_type=temperature
🌉 Bridge active - processed 100 iterations
```

## 🔍 **DIAGNOSTIC**

### **Tester la connectivité Pi**
```bash
# Test ping
ping 192.168.1.216

# Test port MQTT
telnet 192.168.1.216 1883

# Test authentification
mosquitto_sub -h 192.168.1.216 -p 1883 -u pi -P wirepass123 -t 112
```

### **Tester HiveMQ Cloud**
```bash
# Mode normal pour vérifier HiveMQ
php artisan mqtt:listen --timeout=10 --verbose
```

### **Debug bridge**
```bash
# Mode bridge avec logs détaillés
php artisan mqtt:listen --bridge --verbose

# Vérifier les processus
ps aux | grep mqtt:listen
```

## 🎯 **AVANTAGES DE CETTE INTÉGRATION**

### ✅ **Architecture Native**
- **Zéro fichier supplémentaire** créé
- Extension propre du service existant
- Réutilise 100% de la logique Laravel
- Compatible avec tous vos services actuels

### ✅ **Double Traitement Intelligent**
- **Republication** : Messages bridgés vers HiveMQ Cloud
- **Processing local** : Données immédiatement en base
- **Performance** : Pas d'attente réseau pour la DB
- **Fiabilité** : Données disponibles même si HiveMQ inaccessible

### ✅ **Maintenance Zero**
- **Une seule commande** : `php artisan mqtt:listen --bridge`
- **Reconnexion automatique** des deux côtés
- **Logs intégrés** dans le système Laravel
- **Configuration centralisée** via .env

### ✅ **Flexibilité Totale**
- **Mode normal** : Pour développement/test
- **Mode bridge** : Pour production
- **Même codebase** : Pas de duplication
- **Switch instantané** : Juste une option --bridge

## 📝 **RÉSUMÉ TECHNIQUE**

| Composant | Avant | Après |
|-----------|-------|-------|
| **MQTTSubscriberService** | 1 client MQTT | 1 + 2 clients optionnels (bridge) |
| **MqttListenerCommand** | Mode unique | 2 modes (normal + bridge) |
| **Configuration** | HiveMQ uniquement | HiveMQ + Pi optionnel |
| **Architecture** | Cloud direct | Cloud + Bridge optionnel |
| **Commandes** | `mqtt:listen` | `mqtt:listen` + `--bridge` |

## 🎉 **READY TO USE !**

Votre pont MQTT est **opérationnel immédiatement** :

```bash
# Démarrer le bridge maintenant
php artisan mqtt:listen --bridge
```

**L'architecture fonctionne maintenant comme prévu** :
```
RuuviTag → Pi → MQTT → Bridge Laravel → HiveMQ → Processing → Database → Frontend
```

🚀 **Profitez de vos données RuuviTag en temps réel !**