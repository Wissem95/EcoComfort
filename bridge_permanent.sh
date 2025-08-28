#!/bin/bash

# BRIDGE MQTT PERMANENT - EcoComfort Production
# Redémarre automatiquement le bridge en cas d'arrêt

BRIDGE_LOG="/tmp/ecocomfort-bridge-permanent.log"
BRIDGE_PID_FILE="/tmp/ecocomfort-bridge.pid"
PROJECT_DIR="/Users/wissem/Ecoconfort"

echo "🚀 BRIDGE MQTT PERMANENT - EcoComfort Production" | tee -a $BRIDGE_LOG
echo "📅 Démarré le: $(date)" | tee -a $BRIDGE_LOG
echo "📍 Répertoire: $PROJECT_DIR" | tee -a $BRIDGE_LOG
echo "📋 Log: $BRIDGE_LOG" | tee -a $BRIDGE_LOG
echo "================================================" | tee -a $BRIDGE_LOG

# Function pour arrêter le bridge proprement
cleanup() {
    echo "📡 Signal d'arrêt reçu - Nettoyage..." | tee -a $BRIDGE_LOG
    if [ -f $BRIDGE_PID_FILE ]; then
        local pid=$(cat $BRIDGE_PID_FILE)
        if kill -0 $pid 2>/dev/null; then
            echo "🛑 Arrêt du bridge (PID: $pid)" | tee -a $BRIDGE_LOG
            kill $pid
            wait $pid 2>/dev/null
        fi
        rm -f $BRIDGE_PID_FILE
    fi
    echo "✅ Nettoyage terminé" | tee -a $BRIDGE_LOG
    exit 0
}

# Capturer les signaux pour arrêt propre
trap cleanup SIGTERM SIGINT

# Function pour démarrer le bridge
start_bridge() {
    cd $PROJECT_DIR
    echo "🌉 Démarrage bridge Laravel..." | tee -a $BRIDGE_LOG
    
    # Lancer le bridge en arrière-plan
    php artisan mqtt:listen --bridge >> $BRIDGE_LOG 2>&1 &
    local bridge_pid=$!
    
    # Sauvegarder le PID
    echo $bridge_pid > $BRIDGE_PID_FILE
    
    echo "✅ Bridge démarré (PID: $bridge_pid)" | tee -a $BRIDGE_LOG
    echo "📊 Bridge DUAL: Base de données + HiveMQ Cloud" | tee -a $BRIDGE_LOG
    
    return $bridge_pid
}

# Function pour vérifier si le bridge est vivant
is_bridge_alive() {
    if [ -f $BRIDGE_PID_FILE ]; then
        local pid=$(cat $BRIDGE_PID_FILE)
        if kill -0 $pid 2>/dev/null; then
            return 0  # Vivant
        fi
    fi
    return 1  # Mort
}

# Function pour monitorer et redémarrer
monitor_bridge() {
    local restart_count=0
    
    while true; do
        if is_bridge_alive; then
            # Bridge vivant - attendre et vérifier les données
            sleep 30
            
            # Vérifier les logs récents pour s'assurer que le bridge traite des données
            if tail -10 $BRIDGE_LOG | grep -q "Bridge DUAL\|Bridged RuuviTag" 2>/dev/null; then
                # Activité récente détectée
                echo "✅ Bridge actif - Données traitées $(date)" | tee -a $BRIDGE_LOG
            else
                echo "⚠️ Bridge silencieux depuis 30s" | tee -a $BRIDGE_LOG
            fi
        else
            # Bridge mort - redémarrer
            restart_count=$((restart_count + 1))
            echo "❌ Bridge arrêté détecté (#$restart_count) - $(date)" | tee -a $BRIDGE_LOG
            
            # Nettoyage PID obsolète
            rm -f $BRIDGE_PID_FILE
            
            # Attendre un peu avant redémarrage (éviter loop infini)
            sleep 5
            
            # Redémarrer
            echo "🔄 Redémarrage automatique du bridge..." | tee -a $BRIDGE_LOG
            start_bridge
            
            # Log de redémarrage
            echo "🚀 Bridge redémarré automatiquement (#$restart_count)" | tee -a $BRIDGE_LOG
            
            # Stats
            if [ $((restart_count % 10)) -eq 0 ]; then
                echo "📊 Stats: $restart_count redémarrages automatiques" | tee -a $BRIDGE_LOG
            fi
        fi
    done
}

# Démarrage initial
echo "🎯 Démarrage initial du bridge..." | tee -a $BRIDGE_LOG
start_bridge

# Test de connectivité après démarrage
sleep 10
if is_bridge_alive; then
    echo "✅ Bridge démarré avec succès" | tee -a $BRIDGE_LOG
else
    echo "❌ Erreur de démarrage du bridge" | tee -a $BRIDGE_LOG
    exit 1
fi

# Afficher status
echo "" | tee -a $BRIDGE_LOG
echo "🔄 MONITORING AUTOMATIQUE ACTIVÉ" | tee -a $BRIDGE_LOG
echo "📡 Bridge: Pi MQTT → Laravel → HiveMQ Cloud + Database" | tee -a $BRIDGE_LOG
echo "🔍 Surveillance: Redémarrage automatique en cas d'arrêt" | tee -a $BRIDGE_LOG
echo "📋 Logs: tail -f $BRIDGE_LOG" | tee -a $BRIDGE_LOG
echo "🛑 Arrêt: pkill -f bridge_permanent.sh" | tee -a $BRIDGE_LOG
echo "" | tee -a $BRIDGE_LOG

# Démarrer monitoring infini
monitor_bridge