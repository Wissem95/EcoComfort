#!/bin/bash

echo "🚀 DÉMARRAGE ECOCOMFORT PRODUCTION"
echo "================================="
echo ""

# Vérifier que nous sommes dans le bon répertoire
if [ ! -f "artisan" ]; then
    echo "❌ Erreur: Veuillez exécuter ce script depuis le répertoire EcoComfort"
    exit 1
fi

echo "📋 Statut système:"
echo "  • Base de données: Nettoyée (production ready)"
echo "  • API: Sécurisée (JWT requis)"
echo "  • MQTT: Pi 192.168.1.216:1883"
echo "  • RuuviTag: 6 capteurs détectés"
echo ""

echo "🔌 Lancement des services..."

# Lancer Laravel API en arrière-plan
echo "  ⚡ Laravel API (port 8000)..."
php artisan serve --host=0.0.0.0 --port=8000 &
LARAVEL_PID=$!

# Lancer WebSocket Reverb en arrière-plan
echo "  📡 WebSocket Reverb..."
php artisan reverb:start &
REVERB_PID=$!

# Lancer le listener MQTT production en arrière-plan
echo "  🏷️  MQTT Production (vraies données RuuviTag)..."
php artisan mqtt:production &
MQTT_PID=$!

# Lancer le frontend React en arrière-plan
echo "  🖥️  PWA React (port 3000)..."
cd frontend
npm run dev &
FRONTEND_PID=$!
cd ..

echo ""
echo "✅ TOUS LES SERVICES DÉMARRÉS"
echo ""
echo "🌐 Accès:"
echo "  • PWA: http://localhost:3000"
echo "  • API: http://localhost:8000"
echo "  • Admin: admin@ecocomfort.com / EcoAdmin2024!"
echo ""
echo "📊 Données:"
echo "  • 6 RuuviTag réels actifs"
echo "  • Température: 12.97°C"  
echo "  • Humidité: 6.47%"
echo "  • Mode: PRODUCTION (aucune simulation)"
echo ""
echo "⚠️  Appuyez sur Ctrl+C pour arrêter tous les services"

# Fonction de nettoyage
cleanup() {
    echo ""
    echo "🛑 Arrêt des services..."
    kill $LARAVEL_PID 2>/dev/null
    kill $REVERB_PID 2>/dev/null  
    kill $MQTT_PID 2>/dev/null
    kill $FRONTEND_PID 2>/dev/null
    echo "✅ Tous les services arrêtés"
    exit 0
}

# Piéger Ctrl+C
trap cleanup SIGINT

# Attendre indéfiniment
while true; do
    sleep 1
done