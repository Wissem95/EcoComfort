#!/bin/bash

# SCRIPT DE DÉMARRAGE PRODUCTION COMPLÈTE - EcoComfort

PROJECT_DIR="/Users/wissem/Ecoconfort"
cd $PROJECT_DIR

echo "🚀 DÉMARRAGE PRODUCTION EcoComfort"
echo "=================================="
echo "📅 $(date)"
echo ""

# Function pour démarrer un service en arrière-plan
start_service() {
    local service_name="$1"
    local command="$2"
    local log_file="$3"
    
    echo "🔄 Démarrage $service_name..."
    eval "$command" > "$log_file" 2>&1 &
    local pid=$!
    echo "✅ $service_name démarré (PID: $pid) - Logs: $log_file"
    return $pid
}

echo "1️⃣ LARAVEL SERVER"
start_service "Laravel API" "php artisan serve" "/tmp/laravel-server.log"

echo ""
echo "2️⃣ WEBSOCKET REVERB"
start_service "WebSocket Reverb" "php artisan reverb:start" "/tmp/websocket-reverb.log"

echo ""
echo "3️⃣ FRONTEND REACT"
cd frontend
start_service "Frontend React" "npm run dev" "/tmp/frontend-react.log"
cd ..

echo ""
echo "4️⃣ BRIDGE MQTT PERMANENT"
start_service "Bridge MQTT Permanent" "./bridge_permanent.sh" "/tmp/bridge-startup.log"

echo ""
echo "🎯 SERVICES DÉMARRÉS"
echo "===================="
echo "• Laravel API:     http://localhost:8000"
echo "• Frontend:        http://localhost:5173"  
echo "• WebSocket:       ws://localhost:8080"
echo "• Bridge MQTT:     Pi → HiveMQ Cloud + Database"
echo ""
echo "📋 LOGS:"
echo "• Laravel:         tail -f /tmp/laravel-server.log"
echo "• WebSocket:       tail -f /tmp/websocket-reverb.log"
echo "• Frontend:        tail -f /tmp/frontend-react.log"
echo "• Bridge:          tail -f /tmp/ecocomfort-bridge-permanent.log"
echo ""
echo "🛑 ARRÊT COMPLET:"
echo "• pkill -f 'artisan serve'"
echo "• pkill -f 'artisan reverb'"
echo "• pkill -f 'npm run dev'"
echo "• pkill -f 'bridge_permanent.sh'"
echo ""
echo "✅ EcoComfort Production Ready !"
echo "📊 Données temps réel: RuuviTag → Frontend + Collègues (HiveMQ)"